<?php
/**
 * Stand-in for gramiyum.in's Easebuzz gateway: same id, title and
 * description, so the payment list matches. Choosing it on this test site
 * fails with a notice — use Razorpay (test mode) or Cash on delivery.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

class Gram_Gateway_Easebuzz extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'payeasebuzz';
        $this->method_title       = 'Easebuzz (test-site placeholder)';
        $this->method_description = 'Markup-only stand-in for the Easebuzz gateway on gramiyum.in. Orders cannot be paid with it.';
        $this->has_fields         = false;
        $this->init_form_fields();
        $this->init_settings();
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled'     => ['title' => 'Enable', 'type' => 'checkbox', 'default' => 'yes'],
            'title'       => ['title' => 'Title', 'type' => 'text', 'default' => 'Easebuzz Payment Gateway'],
            'description' => ['title' => 'Description', 'type' => 'textarea', 'default' => 'Pay securely by Credit or Debit card or internet banking or UPI or Wallets through Easebuzz.'],
        ];
    }

    public function process_payment($order_id)
    {
        wc_add_notice('Easebuzz is not available on this test site. Please choose another payment method.', 'error');
        return ['result' => 'failure'];
    }
}
