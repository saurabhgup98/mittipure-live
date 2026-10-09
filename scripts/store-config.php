<?php
/**
 * Shipping, tax and payment settings matching gramiyum.in's cart/checkout.
 * Run (re-runnable):  docker compose run --rm cli wp eval-file /scripts/store-config.php
 *
 * Razorpay keys are read from RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET env vars
 * (test-mode keys only); pass them with -e. Without them Razorpay stays listed
 * but cannot open its payment popup.
 */

global $wpdb;

/* ---------- India only (gives billing_country the "country_to_state--single" class) ---------- */

update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', ['IN']);
update_option('woocommerce_ship_to_countries', '');
update_option('woocommerce_ship_to_destination', 'billing');

/* ---------- checkout like gramiyum.in: guests may check out, no login prompt ---------- */

update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_checkout_login_reminder', 'no');
update_option('woocommerce_checkout_phone_field', 'required');
// gramiyum.in prints an empty .woocommerce-privacy-policy-text.
update_option('woocommerce_checkout_privacy_policy_text', '');

/* ---------- shipping: zone "India", flat rate ₹50 with instance id 4 (shipping_method_0_flat_rate4) ---------- */

$zone_id = (int) $wpdb->get_var("SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_name = 'India'");
if (!$zone_id) {
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name('India');
    $zone->add_location('IN', 'country');
    $zone->save();
    $zone_id = $zone->get_id();
}
$methods = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
$wpdb->delete($methods, ['zone_id' => $zone_id]);
$wpdb->query("DELETE FROM {$methods} WHERE instance_id = 4");
$wpdb->insert($methods, ['zone_id' => $zone_id, 'instance_id' => 4, 'method_id' => 'flat_rate', 'method_order' => 1, 'is_enabled' => 1]);
update_option('woocommerce_flat_rate_4_settings', ['title' => 'Flat rate', 'tax_status' => 'none', 'cost' => '50']);
WC_Cache_Helper::invalidate_cache_group('shipping_zones');
WC_Cache_Helper::get_transient_version('shipping', true);

/* ---------- tax: 5% GST included in prices, not on shipping ("includes ₹4.29 Tax" on ₹90) ---------- */

$rates = $wpdb->prefix . 'woocommerce_tax_rates';
if (!$wpdb->get_var($wpdb->prepare("SELECT tax_rate_id FROM {$rates} WHERE tax_rate_country = %s AND tax_rate_name = %s", 'IN', 'GST'))) {
    WC_Tax::_insert_tax_rate([
        'tax_rate_country'  => 'IN',
        'tax_rate_state'    => '',
        'tax_rate'          => '5.0000',
        'tax_rate_name'     => 'GST',
        'tax_rate_priority' => 1,
        'tax_rate_compound' => 0,
        'tax_rate_shipping' => 0,
        'tax_rate_order'    => 0,
        'tax_rate_class'    => '',
    ]);
}
update_option('woocommerce_tax_based_on', 'base');
update_option('woocommerce_tax_total_display', 'single');

/* ---------- payments: Razorpay, Easebuzz slot, Cash on delivery (gramiyum.in's order) ---------- */

$rzp = get_option('woocommerce_razorpay_settings', []);
$rzp = array_merge(is_array($rzp) ? $rzp : [], [
    'enabled'     => 'yes',
    'title'       => 'Credit Card/Debit Card/NetBanking/UPI',
    'description' => 'Pay securely by Credit or Debit card or Internet Banking or UPI through Razorpay.',
]);
$key_id     = getenv('RAZORPAY_KEY_ID') ?: '';
$key_secret = getenv('RAZORPAY_KEY_SECRET') ?: '';
if ($key_id !== '') {
    if (strpos($key_id, 'rzp_test_') !== 0) {
        WP_CLI::error('Only Razorpay test keys (rzp_test_…) may be used on this site.');
    }
    $rzp['key_id']     = $key_id;
    $rzp['key_secret'] = $key_secret;
}
update_option('woocommerce_razorpay_settings', $rzp);
update_option('woocommerce_payeasebuzz_settings', [
    'enabled'     => 'yes',
    'title'       => 'Easebuzz Payment Gateway',
    'description' => 'Pay securely by Credit or Debit card or internet banking or UPI or Wallets through Easebuzz.',
]);
update_option('woocommerce_cod_settings', [
    'enabled'            => 'yes',
    'title'              => 'Cash on delivery',
    'description'        => 'Pay with cash upon delivery.',
    'instructions'       => 'Pay with cash upon delivery.',
    'enable_for_methods' => [],
    'enable_for_virtual' => 'yes',
]);
update_option('woocommerce_gateway_order', ['razorpay' => 0, 'payeasebuzz' => 1, 'cod' => 2]);

WP_CLI::success('store config applied' . ($key_id !== '' ? ' (Razorpay test keys set)' : ' (no Razorpay keys yet)'));
