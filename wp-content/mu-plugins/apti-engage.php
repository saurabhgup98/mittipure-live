<?php
/**
 * Plugin Name: APTI Engage (server-side)
 * Description: Talks to APTI from the server with the site's secret key (no
 *              SDK script needed):
 *                - login (phone or email)  → `login_success` with the phone, so
 *                  APTI keeps a customer record
 *                - checkout open 25 s without placing the order, or left →
 *                  APTI's cart recovery WhatsApp (mittipure_cart_recovery, at
 *                  most once per customer per day), offering the discount set
 *                  in the admin
 *                - that discount is applied to the cart automatically while
 *                  it's unused (24 h), and marked used on the order
 *                - a paid order → `payment_completed`
 *
 * Config: APTI_API_BASE (optional constant, defaults to APTI dev) and the
 * secret key from the APTI_SECRET_KEY environment variable (local Docker:
 * .env) or Pantheon secret of the same name. Without the key nothing is sent.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Apti_Engage
{
    const DEFAULT_API_BASE   = 'https://3p7to6mit2.execute-api.ap-south-1.amazonaws.com/dev';
    const TEMPLATE           = 'mittipure_cart_recovery';
    const RECOVERY_AFTER_MS  = 25000;
    const TIMEOUT_SECONDS    = 6;
    // How long a looked-up discount is reused before asking APTI again.
    const DISCOUNT_CACHE_SECONDS = 120;
    const SESSION_KEY        = 'apti_recovery_discount';
    const FEE_NAME           = 'WhatsApp offer';
    const AJAX_ACTION        = 'apti_cart_recovery';

    public static function init(): void
    {
        add_action('wp_login', [self::class, 'on_login'], 10, 2);
        add_action('wp_enqueue_scripts', [self::class, 'checkout_script']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajax_cart_recovery']);
        add_action('woocommerce_cart_calculate_fees', [self::class, 'apply_discount']);
        add_action('woocommerce_checkout_order_processed', [self::class, 'redeem_on_order'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'redeem_on_order']);
        foreach (['woocommerce_payment_complete', 'woocommerce_order_status_processing', 'woocommerce_order_status_completed'] as $hook) {
            add_action($hook, [self::class, 'payment_completed']);
        }
    }

    /* ------------------------------------------------------------ config */

    private static function api_base(): string
    {
        $base = defined('APTI_API_BASE') && APTI_API_BASE !== '' ? APTI_API_BASE : self::DEFAULT_API_BASE;
        return rtrim($base, '/');
    }

    private static function secret_key(): string
    {
        $key = getenv('APTI_SECRET_KEY');
        if (!$key && function_exists('pantheon_get_secret')) {
            $key = pantheon_get_secret('APTI_SECRET_KEY');
        }
        return is_string($key) ? trim($key) : '';
    }

    /**
     * One call to APTI. Returns the response's `data` (array) or null on any
     * failure (logged, never with the key). Non-blocking calls return null.
     */
    private static function call(string $method, string $path, array $body, bool $blocking = true): ?array
    {
        $key = self::secret_key();
        if ($key === '') {
            return null;
        }
        $response = wp_remote_request(self::api_base() . $path, [
            'method'   => $method,
            'timeout'  => $blocking ? self::TIMEOUT_SECONDS : 2,
            'blocking' => $blocking,
            'headers'  => ['Content-Type' => 'application/json', 'x-apti-key' => $key],
            'body'     => wp_json_encode($body),
        ]);
        if (!$blocking) {
            return null;
        }
        if (is_wp_error($response)) {
            error_log('[apti-engage] ' . $path . ' failed: ' . $response->get_error_message());
            return null;
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[apti-engage] ' . $path . ' refused: ' . $code . ' ' . ($json['error']['msg'] ?? ''));
            return null;
        }
        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /**
     * Login OTP by real SMS through APTI (POST /sdk/v1/otp/send | /otp/verify):
     * APTI makes the 6-digit code, texts it and checks it; it also limits
     * resends (30 s apart, 5 an hour) and wrong tries. Used by the login
     * plugin (gram-digits-login). Returns [ok, message] — APTI's own message
     * for the customer's mistakes (400/429), a generic one otherwise.
     */
    public static function otp(string $step, string $phone, string $code = ''): array
    {
        $unavailable = "Couldn't reach the OTP service. Please try again.";
        $key = self::secret_key();
        if ($key === '') {
            return [false, "Login by OTP isn't available right now."];
        }
        $body = ['mobile' => $phone];
        if ($step === 'verify') {
            $body['otp'] = $code;
        }
        $response = wp_remote_post(self::api_base() . '/sdk/v1/otp/' . ($step === 'verify' ? 'verify' : 'send'), [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json', 'x-apti-key' => $key],
            'body'    => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            error_log('[apti-engage] otp/' . $step . ' failed: ' . $response->get_error_message());
            return [false, $unavailable];
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status >= 200 && $status < 300) {
            return [true, ''];
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        $msg  = $json['error']['msg'] ?? '';
        if (($status === 400 || $status === 429) && $msg !== '') {
            return [false, $msg];
        }
        error_log('[apti-engage] otp/' . $step . ' refused: ' . $status . ' ' . $msg);
        return [false, $unavailable];
    }

    /* ----------------------------------------------------------- helpers */

    /** The customer's phone with country code ("+919000000002"), or ''. */
    private static function user_phone(int $user_id): string
    {
        foreach (['digits_phone', 'billing_phone'] as $meta) {
            $phone = preg_replace('/[^\d+]/', '', (string) get_user_meta($user_id, $meta, true));
            if (strlen(preg_replace('/\D/', '', $phone)) >= 10) {
                return $phone;
            }
        }
        return '';
    }

    // Stable id for the logged-in customer, sent as APTI's visitor id so their
    // login and orders link to one record.
    private static function visitor_id(int $user_id): string
    {
        return 'wp-user-' . $user_id;
    }

    private static function load_cart(): void
    {
        if (function_exists('WC') && WC() && null === WC()->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    /* ------------------------------------------------------------- login */

    /** Phone or email login (and registration): APTI's customer record. */
    public static function on_login($user_login, $user): void
    {
        if (!$user instanceof WP_User) {
            return;
        }
        $body = ['event' => 'login_success', 'visitor_id' => self::visitor_id($user->ID)];
        $phone = self::user_phone($user->ID);
        if ($phone !== '') {
            $body['customer'] = ['phone' => $phone];
        }
        self::call('POST', '/sdk/v1/track', $body, false);
    }

    /* --------------------------------------------------- cart recovery */

    public static function checkout_script(): void
    {
        if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }
        if (!is_user_logged_in() || self::user_phone(get_current_user_id()) === '' || self::secret_key() === '') {
            return;
        }
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        wp_register_script('apti-engage', false, ['jquery'], null, true);
        wp_enqueue_script('apti-engage');
        $config = wp_json_encode([
            'url'     => admin_url('admin-ajax.php'),
            'action'  => self::AJAX_ACTION,
            'nonce'   => wp_create_nonce(self::AJAX_ACTION),
            'afterMs' => self::RECOVERY_AFTER_MS,
        ]);
        // 25 s on the checkout without placing the order, or leaving it
        // (tab closed or another page) → the cart recovery WhatsApp. Placing
        // the order isn't leaving; a failed attempt (checkout_error) is.
        wp_add_inline_script('apti-engage', <<<JS
(function () {
  var c = {$config};
  var sent = false;
  var placing = false;
  function send(beacon) {
    if (sent || placing) return;
    sent = true;
    var data = new FormData();
    data.append('action', c.action);
    data.append('_ajax_nonce', c.nonce);
    try {
      if (beacon && navigator.sendBeacon && navigator.sendBeacon(c.url, data)) return;
    } catch (e) {}
    fetch(c.url, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(function () {});
  }
  setTimeout(function () { send(false); }, c.afterMs);
  document.addEventListener('submit', function (e) {
    if (e.target && e.target.matches && e.target.matches('form.checkout')) placing = true;
  }, true);
  if (window.jQuery) {
    jQuery(document.body).on('checkout_error', function () { placing = false; });
  }
  window.addEventListener('pagehide', function () { send(true); });
})();
JS);
    }

    public static function ajax_cart_recovery(): void
    {
        check_ajax_referer(self::AJAX_ACTION);
        $user_id = get_current_user_id();
        $phone = $user_id ? self::user_phone($user_id) : '';
        if ($phone === '') {
            wp_send_json(['sent' => false, 'reason' => 'No phone on the account'], 400);
        }
        self::load_cart();
        if (!WC()->cart || WC()->cart->is_empty()) {
            wp_send_json(['sent' => false, 'reason' => 'Cart is empty']);
        }

        $user = get_userdata($user_id);
        $name = trim((string) $user->first_name) ?: $user->display_name;
        $data = self::call('POST', '/sdk/v1/engage/cart-recovery', [
            'template'      => self::TEMPLATE,
            'mobile'        => $phone,
            'booking_id'    => 'cart-' . $user_id . '-' . current_time('Ymd'),
            'customer_name' => $name !== '' ? $name : null,
        ]);
        if (!empty($data['sent']) && WC()->session) {
            // The discount is theirs now — look it up again on the next cart view.
            WC()->session->__unset(self::SESSION_KEY);
        }
        wp_send_json(['sent' => !empty($data['sent']), 'reason' => $data['reason'] ?? ($data === null ? 'APTI unreachable' : null)]);
    }

    /* ---------------------------------------------------------- discount */

    /** The customer's unused cart recovery discount from APTI (cached briefly), or null. */
    private static function open_discount(int $user_id): ?array
    {
        $phone = self::user_phone($user_id);
        if ($phone === '' || !WC()->session) {
            return null;
        }
        $cached = WC()->session->get(self::SESSION_KEY);
        if (is_array($cached) && ($cached['phone'] ?? '') === $phone && time() - (int) ($cached['at'] ?? 0) < self::DISCOUNT_CACHE_SECONDS) {
            return $cached['discount'];
        }
        $data = self::call('POST', '/sdk/v1/engage/cart-recovery/discount-for', ['mobile' => $phone]);
        $discount = is_array($data['discount'] ?? null) ? $data['discount'] : null;
        WC()->session->set(self::SESSION_KEY, ['phone' => $phone, 'at' => time(), 'discount' => $discount]);
        return $discount;
    }

    public static function apply_discount($cart): void
    {
        if ((is_admin() && !wp_doing_ajax()) || !is_user_logged_in() || !$cart || $cart->is_empty()) {
            return;
        }
        $discount = self::open_discount(get_current_user_id());
        if (!$discount || empty($discount['value'])) {
            return;
        }
        // Prices include GST, so take it off what the customer sees.
        $subtotal = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
        $off = $discount['type'] === 'percent'
            ? round($subtotal * (float) $discount['value'] / 100, 2)
            : min((float) $discount['value'], $subtotal);
        if ($off > 0) {
            $cart->add_fee(self::FEE_NAME . ' (' . $discount['label'] . ')', -$off, false);
        }
    }

    /** The order used the discount: tell APTI, so it's not offered again. */
    public static function redeem_on_order($order_or_id, $posted = null, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : ($order_or_id instanceof WC_Order ? $order_or_id : wc_get_order($order_or_id));
        if (!$order || !$order->get_customer_id()) {
            return;
        }
        $used = false;
        foreach ($order->get_fees() as $fee) {
            if (strpos($fee->get_name(), self::FEE_NAME) === 0) {
                $used = true;
            }
        }
        if (!$used) {
            return;
        }
        $phone = self::user_phone($order->get_customer_id());
        if ($phone === '') {
            return;
        }
        $data = self::call('POST', '/sdk/v1/engage/cart-recovery/redeem', ['mobile' => $phone, 'order_id' => (string) $order->get_id()]);
        $order->update_meta_data('_apti_recovery_redeemed', !empty($data['redeemed']) ? 'yes' : 'no');
        $order->save();
        if (WC()->session) {
            WC()->session->__unset(self::SESSION_KEY);
        }
    }

    /* ----------------------------------------------------------- payment */

    /** A paid order → APTI `payment_completed` (once per order). */
    public static function payment_completed($order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_apti_payment_tracked') === 'yes') {
            return;
        }
        $order->update_meta_data('_apti_payment_tracked', 'yes');
        $order->save();

        $body = [
            'event'       => 'payment_completed',
            'order_id'    => (string) $order->get_id(),
            'value'       => (float) $order->get_total(),
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $user_id = (int) $order->get_customer_id();
        if ($user_id) {
            $body['visitor_id'] = self::visitor_id($user_id);
        }
        $phone = $user_id ? self::user_phone($user_id) : preg_replace('/[^\d+]/', '', (string) $order->get_billing_phone());
        if (strlen(preg_replace('/\D/', '', $phone)) >= 10) {
            $body['customer'] = ['phone' => $phone];
        }
        self::call('POST', '/sdk/v1/track', $body);
    }
}

Apti_Engage::init();
