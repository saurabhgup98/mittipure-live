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
 *                - a placed order (COD too) → `payment_completed` (APTI adds
 *                  the referral cashback rule set in the admin) and the
 *                  order-confirmed WhatsApp (mittipure_order_confirm, once
 *                  per order: Buy Again + Refer & Earn buttons)
 *                - referral cashback, as on Sera Stay: a shared link
 *                  (?ref_id=…) is remembered in a cookie and sent with the
 *                  next login, so APTI records who referred a new customer
 *                  and credits them when that customer orders; the
 *                  customer's whole available balance comes off at checkout
 *                  ("Referral cashback", ≥ ₹1 left to pay — held on the
 *                  order, used once it's placed/paid, given back if it's
 *                  cancelled or fails); /refer/<referral id> opens WhatsApp
 *                  share with their link; My Account shows the balance
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
    // Referral cashback. STYXKEY_ cookies reach PHP through Pantheon's edge.
    const REF_COOKIE         = 'STYXKEY_apti_ref';
    const REF_COOKIE_DAYS    = 30;
    const CASHBACK_FEE_NAME  = 'Referral cashback';
    const CASHBACK_SESSION   = 'apti_cashback';
    // Order-confirmed WhatsApp: "Expected Delivery" this many days out.
    const DELIVERY_DAYS      = 5;
    const ITEMS_MAX_CHARS    = 120;
    const BRAND              = 'MittiPure';

    public static function init(): void
    {
        add_action('wp_login', [self::class, 'on_login'], 10, 2);
        add_action('wp_head', [self::class, 'ref_script'], 1);
        add_action('template_redirect', [self::class, 'refer_page'], 5);
        add_action('woocommerce_account_dashboard', [self::class, 'account_cashback']);
        add_action('woocommerce_cart_calculate_fees', [self::class, 'apply_cashback'], 20);
        add_action('woocommerce_checkout_order_processed', [self::class, 'reserve_cashback'], 20, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'reserve_cashback'], 20);
        foreach (['woocommerce_order_status_cancelled', 'woocommerce_order_status_failed'] as $hook) {
            add_action($hook, [self::class, 'release_cashback']);
        }
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

    /**
     * An Indian mobile as "+91XXXXXXXXXX", or '' — only 10 digits starting
     * 6–9 (APTI reads anything else as a different number).
     */
    private static function india_mobile(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
            $digits = substr($digits, 2);
        }
        return preg_match('/^[6-9]\d{9}$/', $digits) ? '+91' . $digits : '';
    }

    /** The referral id from a shared link this browser came in on, or ''. */
    private static function ref_id(): string
    {
        $ref = isset($_COOKIE[self::REF_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::REF_COOKIE])) : '';
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ref) ? strtolower($ref) : '';
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
        if ($user instanceof WP_User) {
            self::track_login($user->ID, false);
        }
    }

    /**
     * login_success with the phone and any referral link's id: APTI finds or
     * creates the customer, and a new one is recorded as referred.
     */
    private static function track_login(int $user_id, bool $blocking): void
    {
        $body = ['event' => 'login_success', 'visitor_id' => self::visitor_id($user_id)];
        $phone = self::user_phone($user_id);
        if ($phone !== '') {
            $body['customer'] = ['phone' => $phone];
        }
        $ref = self::ref_id();
        if ($ref !== '') {
            $body['ref_id'] = $ref;
        }
        self::call('POST', '/sdk/v1/track', $body, $blocking);
    }

    /**
     * ?ref_id=<referral id> on any page → kept in a cookie for the next login
     * (set in the browser, so cached pages work too).
     */
    public static function ref_script(): void
    {
        $cookie = self::REF_COOKIE;
        $max_age = self::REF_COOKIE_DAYS * DAY_IN_SECONDS;
        echo "<script>(function(){try{var r=new URLSearchParams(location.search).get('ref_id');"
            . "if(r&&/^[0-9a-f-]{36}$/i.test(r)){document.cookie='{$cookie}='+r+';path=/;max-age={$max_age};SameSite=Lax'+(location.protocol==='https:'?';Secure':'');}"
            . "}catch(e){}})();</script>\n";
    }

    /* -------------------------------------------------- referral cashback */

    /** The customer's referral id and cashback from APTI (cached briefly in the cart session), or null. */
    private static function cashback_balance(int $user_id, bool $fresh = false): ?array
    {
        $phone = self::user_phone($user_id);
        if ($phone === '') {
            return null;
        }
        $session = function_exists('WC') && WC() ? WC()->session : null;
        if (!$fresh && $session) {
            $cached = $session->get(self::CASHBACK_SESSION);
            if (is_array($cached) && ($cached['phone'] ?? '') === $phone && time() - (int) ($cached['at'] ?? 0) < self::DISCOUNT_CACHE_SECONDS) {
                return $cached['balance'];
            }
        }
        $data = self::call('POST', '/sdk/v1/cashback/balance', ['mobile' => $phone]);
        if ($session && $data !== null) {
            $session->set(self::CASHBACK_SESSION, ['phone' => $phone, 'at' => time(), 'balance' => $data]);
        }
        return $data;
    }

    private static function forget_cashback(): void
    {
        if (function_exists('WC') && WC() && WC()->session) {
            WC()->session->__unset(self::CASHBACK_SESSION);
        }
    }

    /**
     * The whole available balance off the order (whole rupees, at least ₹1
     * left to pay), after the WhatsApp offer.
     */
    public static function apply_cashback($cart): void
    {
        if ((is_admin() && !wp_doing_ajax()) || !is_user_logged_in() || !$cart || $cart->is_empty()) {
            return;
        }
        $balance = self::cashback_balance(get_current_user_id());
        $available = floor((float) ($balance['available'] ?? 0));
        if ($available <= 0) {
            return;
        }
        $payable = (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax()
            + (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
        foreach ($cart->get_fees() as $fee) {
            $payable += (float) $fee->amount;
        }
        $off = min($available, floor($payable - 1));
        if ($off > 0) {
            $cart->add_fee(self::CASHBACK_FEE_NAME, -$off, false);
        }
    }

    /** The cashback fee on an order, or null. */
    private static function cashback_fee(WC_Order $order): ?WC_Order_Item_Fee
    {
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_name() === self::CASHBACK_FEE_NAME) {
                return $fee;
            }
        }
        return null;
    }

    /**
     * The order took cashback: hold it on APTI for this order. If APTI holds
     * less (the balance changed), the order gets that much; none → removed.
     */
    public static function reserve_cashback($order_or_id, $posted = null, $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : ($order_or_id instanceof WC_Order ? $order_or_id : wc_get_order($order_or_id));
        if (!$order || !$order->get_customer_id() || $order->get_meta('_apti_cashback_status') !== '') {
            return;
        }
        $fee = self::cashback_fee($order);
        if (!$fee) {
            return;
        }
        self::forget_cashback();
        $wanted = abs((float) $fee->get_total());
        $phone = self::user_phone($order->get_customer_id());
        $data = $phone === '' ? null : self::call('POST', '/sdk/v1/cashback/reserve', [
            'mobile'     => $phone,
            'booking_id' => (string) $order->get_id(),
            'max_amount' => $wanted,
        ]);
        $held = round((float) ($data['amount'] ?? 0), 2);
        if ($held <= 0) {
            $order->remove_item($fee->get_id());
        } elseif ($held < $wanted) {
            $fee->set_amount((string) -$held);
            $fee->set_total((string) -$held);
            $fee->save();
        }
        if ($held < $wanted) {
            $order->calculate_totals(false);
        }
        $order->update_meta_data('_apti_cashback_status', $held > 0 ? 'reserved' : 'none');
        $order->update_meta_data('_apti_cashback_amount', $held);
        $order->save();
    }

    /** Placed / paid: the held cashback is used. */
    private static function confirm_cashback(WC_Order $order): void
    {
        if ($order->get_meta('_apti_cashback_status') !== 'reserved') {
            return;
        }
        $data = self::call('POST', '/sdk/v1/cashback/confirm', ['booking_id' => (string) $order->get_id()]);
        if ($data !== null) {
            $order->update_meta_data('_apti_cashback_status', 'used');
            $order->save();
        }
    }

    /** Cancelled or failed before it was used: the hold is given back. */
    public static function release_cashback($order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_apti_cashback_status') !== 'reserved') {
            return;
        }
        $data = self::call('POST', '/sdk/v1/cashback/release', ['booking_id' => (string) $order->get_id()]);
        if ($data !== null) {
            $order->update_meta_data('_apti_cashback_status', 'released');
            $order->save();
        }
    }

    /** My Account: cashback balance, referral id and the Refer & Earn link. */
    public static function account_cashback(): void
    {
        $balance = self::cashback_balance(get_current_user_id(), true);
        if (!$balance || empty($balance['referral_id'])) {
            return;
        }
        $rupees = static function ($v): string {
            return '₹' . number_format_i18n((float) $v, floor((float) $v) == (float) $v ? 0 : 2);
        };
        ?>
        <div id="apti-cashback" style="margin:1.5em 0;padding:1em 1.25em;border:1px solid #e3e3e3;border-radius:8px">
            <h3 style="margin-top:0">Referral cashback</h3>
            <p style="margin:.25em 0">Available: <strong id="apti-cashback-available"><?php echo esc_html($rupees($balance['available'] ?? 0)); ?></strong>
                <?php if ((float) ($balance['pending'] ?? 0) > 0) : ?> · Pending: <span id="apti-cashback-pending"><?php echo esc_html($rupees($balance['pending'])); ?></span><?php endif; ?></p>
            <p style="margin:.25em 0">Your referral ID: <code id="apti-referral-id"><?php echo esc_html($balance['referral_id']); ?></code></p>
            <p style="margin:.75em 0 0">Your available cashback comes off your next order automatically.
                <a id="apti-refer-link" href="<?php echo esc_url(home_url('/refer/' . $balance['referral_id'])); ?>">Refer &amp; Earn</a></p>
        </div>
        <?php
    }

    /**
     * /refer/<referral id> — where the order WhatsApp's "Refer & Earn" lands:
     * opens WhatsApp's share screen with the customer's link. "me" = APTI
     * hadn't seen them log in when it sent the message; their id is looked
     * up once they're logged in.
     */
    public static function refer_page(): void
    {
        $path = trim((string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH), '/');
        if (!preg_match('#^refer/([^/]+)$#', $path, $m)) {
            return;
        }
        $id = strtolower(sanitize_text_field($m[1]));
        $link = '';
        $note = '';
        if ($id === 'me') {
            if (!is_user_logged_in()) {
                $note = 'login';
            } else {
                $balance = self::cashback_balance(get_current_user_id(), true);
                $id = (string) ($balance['referral_id'] ?? '');
                if ($id === '') {
                    $note = 'pending';
                }
            }
        } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            return; // not ours: the normal 404
        }
        if ($note === '') {
            $link = add_query_arg(['utm_source' => 'whatsapp', 'utm_medium' => 'apti_referral', 'ref_id' => $id], home_url('/'));
        }
        $message = 'Hey! Shop pure, natural products at ' . self::BRAND . ' using my link: ' . $link;
        $share = 'https://wa.me/?text=' . rawurlencode($message);

        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        nocache_headers();
        get_header();
        ?>
        <div id="refer-page" style="max-width:480px;margin:3em auto;padding:0 16px;text-align:center">
            <?php if ($note === 'login') : ?>
                <h1>Refer a friend</h1>
                <p>Log in to get your referral link.</p>
                <p><a class="button" href="<?php echo esc_url(home_url() . '?login=true&redirect_to&page=1'); ?>">Log in</a></p>
            <?php elseif ($note === 'pending') : ?>
                <h1>Your referral link is being set up</h1>
                <p>Please try again in a minute.</p>
            <?php else : ?>
                <h1>Refer a friend</h1>
                <p>Opening WhatsApp so you can pick who to send it to…</p>
                <p id="refer-message" style="text-align:left;border:1px solid #e3e3e3;border-radius:8px;padding:1em"><?php echo esc_html($message); ?></p>
                <p><a id="refer-whatsapp-btn" class="button" href="<?php echo esc_url($share); ?>">Share on WhatsApp</a></p>
                <p><button id="refer-copy-btn" type="button" class="button alt" data-link="<?php echo esc_attr($link); ?>">Copy my link</button></p>
                <script>
                (function () {
                  var share = <?php echo wp_json_encode($share); ?>;
                  var btn = document.getElementById('refer-copy-btn');
                  btn.addEventListener('click', function () {
                    if (navigator.clipboard) navigator.clipboard.writeText(btn.getAttribute('data-link')).then(function () { btn.textContent = 'Link copied'; });
                  });
                  window.location.href = share;
                })();
                </script>
            <?php endif; ?>
        </div>
        <?php
        get_footer();
        exit;
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
        // For testing, a notice (once per tab) and a status line say to wait
        // for that message and reply Yes/OK to it before ordering: both are
        // Marketing templates, and the reply is what lets the order
        // confirmation through.
        wp_add_inline_script('apti-engage', <<<JS
(function () {
  var c = {$config};
  var sent = false;
  var placing = false;
  var status = document.createElement('div');
  status.id = 'apti-whatsapp-status';
  status.setAttribute('role', 'status');
  status.style.cssText = 'margin:0 0 1.25em;padding:.85em 1.1em;border-radius:8px;background:#eef6ea;border:1px solid #cfe3c4;color:#24351f;font-size:.95em;line-height:1.5';
  function say(html) { status.innerHTML = html; }
  var host = document.querySelector('form.checkout') || document.querySelector('.woocommerce');
  if (host) host.parentNode.insertBefore(status, host);
  var left = Math.round(c.afterMs / 1000);
  function tick() {
    if (sent || placing) return;
    say('<strong>Testing WhatsApp:</strong> stay on this page — the cart message reaches your WhatsApp in <strong>' + left + ' s</strong>.');
    if (left-- > 0) setTimeout(tick, 1000);
  }
  tick();
  function result(r) {
    if (r && r.sent) {
      say('<strong>Cart message sent on WhatsApp.</strong> Reply <strong>Yes</strong> or <strong>OK</strong> to it, wait about 1 minute, then place your order to get the order confirmation there.');
    } else if (r && r.reason === 'already_sent_today') {
      say("<strong>Today's cart message was already sent.</strong> If you haven't yet, reply <strong>Yes</strong> or <strong>OK</strong> to it on WhatsApp, wait about 1 minute, then place your order.");
    } else {
      say('<strong>No cart message this time</strong>' + (r && r.reason ? ' (' + String(r.reason).replace(/[<>&]/g, '') + ')' : '') + '. You can still place your order.');
    }
  }
  function send(beacon) {
    if (sent || placing) return;
    sent = true;
    var data = new FormData();
    data.append('action', c.action);
    data.append('_ajax_nonce', c.nonce);
    try {
      if (beacon && navigator.sendBeacon && navigator.sendBeacon(c.url, data)) return;
    } catch (e) {}
    say('Sending the cart message on WhatsApp…');
    fetch(c.url, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true })
      .then(function (res) { return res.json(); })
      .then(result)
      .catch(function () { result(null); });
  }
  setTimeout(function () { send(false); }, c.afterMs);

  var seenKey = 'apti_whatsapp_notice_seen';
  var seen = false;
  try { seen = !!sessionStorage.getItem(seenKey); } catch (e) {}
  if (!seen) {
    var box = document.createElement('div');
    box.id = 'apti-whatsapp-notice';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);padding:16px';
    box.innerHTML = '<div style="width:100%;max-width:440px;background:#fff;border-radius:16px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.25);color:#24351f;font-size:15px;line-height:1.55">'
      + '<p style="margin:0;font-size:12px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:#7a8b3a">Before you order</p>'
      + '<h2 style="margin:4px 0 14px;font-size:20px">One quick step to complete the test</h2>'
      + '<ol style="margin:0 0 14px 1.2em;padding:0">'
      + '<li style="margin-bottom:8px">Stay on this page for about <strong>25 seconds</strong>. You\'ll get the cart message on WhatsApp.</li>'
      + '<li style="margin-bottom:8px">Reply <strong>Yes</strong> or <strong>OK</strong> to that message. This lets us send your order confirmation there.</li>'
      + '<li>After replying, wait <strong>about 1 minute</strong>, then place your order.</li>'
      + '</ol>'
      + '<p style="margin:0;padding:10px 12px;border-radius:10px;background:#f6f1e7">You\'ll then receive the order confirmation on WhatsApp, with your referral link.</p>'
      + '<button type="button" id="apti-whatsapp-notice-ok" class="button" style="margin-top:18px;width:100%;height:46px;border:0;border-radius:10px;background:#0b7a3e;color:#fff;font-weight:600">OK</button>'
      + '</div>';
    document.body.appendChild(box);
    var ok = document.getElementById('apti-whatsapp-notice-ok');
    ok.focus();
    ok.addEventListener('click', function () {
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
      box.parentNode.removeChild(box);
    });
  }
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
        $body = [
            'template'   => self::TEMPLATE,
            'mobile'     => $phone,
            'booking_id' => 'cart-' . $user_id . '-' . current_time('Ymd'),
        ];
        if ($name !== '') {
            $body['customer_name'] = $name; // optional: APTI says "User" without it
        }
        $data = self::call('POST', '/sdk/v1/engage/cart-recovery', $body);
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

    /**
     * A placed order (COD: when it's placed; online: when paid) → once per
     * order: its held cashback is used, APTI `payment_completed` (APTI adds
     * the referral cashback rule, so the referrer is credited), the payment
     * for APTI's Referral payments, and the order-confirmed WhatsApp.
     */
    public static function payment_completed($order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_apti_payment_tracked') === 'yes') {
            return;
        }
        $order->update_meta_data('_apti_payment_tracked', 'yes');
        $order->save();

        self::confirm_cashback($order);

        $user_id = (int) $order->get_customer_id();
        if ($user_id) {
            // The customer record must exist before the payment is counted
            // (a long-lived session may never have logged in since): same
            // visitor id, so APTI keeps the two in order.
            self::track_login($user_id, true);
        }
        $body = [
            'event'       => 'payment_completed',
            'order_id'    => (string) $order->get_id(),
            'value'       => (float) $order->get_total(),
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        if ($user_id) {
            $body['visitor_id'] = self::visitor_id($user_id);
        }
        $phone = $user_id ? self::user_phone($user_id) : preg_replace('/[^\d+]/', '', (string) $order->get_billing_phone());
        if (strlen(preg_replace('/\D/', '', $phone)) >= 10) {
            $body['customer'] = ['phone' => $phone];
        }
        self::call('POST', '/sdk/v1/track', $body);

        if ($user_id && $phone !== '') {
            self::call('POST', '/sdk/v1/cashback/payment', [
                'mobile'      => $phone,
                'booking_id'  => (string) $order->get_id(),
                'amount_paid' => (float) $order->get_total(),
            ], false);
        }
        self::order_confirmed($order, $phone);
        self::forget_cashback();
    }

    /** mittipure_order_confirm to the customer's mobile (APTI sends it once per order). */
    private static function order_confirmed(WC_Order $order, string $phone): void
    {
        $mobile = self::india_mobile($phone !== '' ? $phone : (string) $order->get_billing_phone());
        if ($mobile === '') {
            return;
        }
        $names = [];
        foreach ($order->get_items() as $item) {
            $names[] = $item->get_name();
        }
        if (!$names) {
            return;
        }
        $items = $names[0] . (count($names) > 1 ? ' and ' . (count($names) - 1) . ' more' : '');
        if (function_exists('mb_strimwidth')) {
            $items = mb_strimwidth($items, 0, self::ITEMS_MAX_CHARS, '…');
        }
        $total = (float) $order->get_total();
        $name = trim((string) $order->get_billing_first_name());
        $body = [
            'mobile'   => $mobile,
            'order_id' => (string) $order->get_order_number(),
            'items'    => $items,
            'total'    => number_format($total, floor($total) == $total ? 0 : 2),
            'delivery' => wp_date('j M Y', time() + self::DELIVERY_DAYS * DAY_IN_SECONDS),
        ];
        if ($name !== '') {
            $body['customer_name'] = $name;
        }
        $data = self::call('POST', '/sdk/v1/engage/order-confirmed', $body);
        $order->update_meta_data('_apti_order_whatsapp', !empty($data['sent']) ? 'sent' : ($data['reason'] ?? 'failed'));
        $order->save();
    }
}

Apti_Engage::init();
