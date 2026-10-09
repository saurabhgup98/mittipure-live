<?php
/**
 * Cart / checkout pieces that gramiyum.in gets from its plugins, reproduced
 * as markup only:
 *  - "View Available Coupons" link (WPC Coupon List, wpccl-*)
 *  - "Empty cart" button (ProWC Empty Cart, #prowc_empty_cart)
 *  - Digits' checkout hidden fields and Place-order hook (verifyOTPbilling)
 *  - Easebuzz gateway slot (payeasebuzz) — present, but not a real gateway
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

/* ---------- coupons ---------- */

function gram_wpccl_button(): string
{
    return '<div class="wpccl-btn-wrapper"><a href="#" class="wpccl-btn" data-featherlight="#wpccl-popup" data-featherlight-before-open="wpccl_load_coupons()" data-featherlight-variant="wpccl-featherlight">View Available Coupons</a></div>';
}

add_action('woocommerce_cart_coupon', function () {
    echo gram_wpccl_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
});

// Same (unbalanced <p>) wrapper the coupon-list plugin prints on gramiyum.in.
add_filter('woocommerce_checkout_coupon_message', function (string $message) {
    return '<div class="wpccl-input-wrapper">' . $message . '<p></div>' . gram_wpccl_button() . '</p>';
});

/* ---------- empty cart ---------- */

add_action('woocommerce_after_cart', function () {
    printf('<div style="float:right;"><a href="%s" style="" class="button" id="prowc_empty_cart">Empty cart</a></div>', esc_url(add_query_arg('prowc_empty_cart', '', wc_get_cart_url())));
});

add_action('wp_loaded', function () {
    if (isset($_GET['prowc_empty_cart']) && function_exists('WC') && WC()->cart) { // phpcs:ignore WordPress.Security.NonceVerification
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});

/* ---------- shipping row label ("Shipment") ---------- */

add_filter('woocommerce_shipping_package_name', function () {
    return 'Shipment';
});

/* ---------- checkout layout: payment sits in .order-payment (form-checkout.php) ---------- */

add_action('init', function () {
    remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
});

/* ---------- address fields ---------- */

// Shipping phone is required on gramiyum.in too (billing phone: woocommerce_checkout_phone_field option).
add_filter('woocommerce_shipping_fields', function (array $fields) {
    if (isset($fields['shipping_phone'])) {
        $fields['shipping_phone']['required'] = true;
    }
    return $fields;
});

// Empty hidden inputs printed by gramiyum.in's multiple-addresses plugin (feature not reproduced).
add_filter('woocommerce_form_field', function (string $field, string $key) {
    if ($key === 'billing_email') {
        $field .= '<input type="hidden" name="thmaf_hidden_field_billing" id="thmaf_hidden_field_billing" value="" /><input type="hidden" name="thmaf_checkbox_shipping" id="thmaf_checkbox_shipping" value="" />';
    } elseif ($key === 'shipping_phone') {
        $field .= '<input type="hidden" name="thmaf_hidden_field_shipping" id="thmaf_hidden_field_shipping" value="" />';
    }
    return $field;
}, 10, 2);

/* ---------- Digits checkout fields ---------- */

// "none" everywhere = Digits doesn't ask for a phone OTP at checkout (gramiyum.in's setting),
// so verifyOTPbilling(10) just submits the form (see shop.js).
add_action('woocommerce_after_checkout_billing_form', function () {
    ?>
	        <input type="hidden" id="digits_vcustomer_phone" value="none" />
        <input type="hidden" id="digits_vbill_phone" value="none" />
        <input type="hidden" id="digits_guest_vbill_phone" value="none" />
            <input type="hidden" name="isPassEnab" id="dig_wc_check_page">
    <input type="hidden" name="dig_nounce" class="dig_nounce" value="<?php echo esc_attr(wp_create_nonce('dig_form')); ?>">
    <input type="hidden" name="code" id="dig_wc_bill_code">
    <input type="hidden" name="csrf" id="dig_wc_bill_csrf">
    <p id="mobile_email_field" style="display: none !important;"></p>
    <?php
}, 5);

add_filter('woocommerce_order_button_html', function () {
    $text = esc_attr__('Place order', 'woocommerce');
    return sprintf(
        '<button onclick="verifyOTPbilling(10);return false;"  data-digits_verify="%s" type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="%s" data-value="%s">%s</button>',
        esc_attr(wp_create_nonce('digits_wc_checkout')),
        $text,
        $text,
        esc_html__('Place order', 'woocommerce')
    );
});

/* ---------- Easebuzz slot ---------- */

add_filter('woocommerce_payment_gateways', function (array $gateways) {
    if (!class_exists('Gram_Gateway_Easebuzz') && class_exists('WC_Payment_Gateway')) {
        require_once __DIR__ . '/class-gram-gateway-easebuzz.php';
    }
    $gateways[] = 'Gram_Gateway_Easebuzz';
    return $gateways;
});
