<?php
/**
 * Test-mode card/UPI payment, standing in for Razorpay on this test site
 * (no real keys, no money moves). Same id and title as Razorpay so the
 * checkout looks the same. Any card number or UPI ID pays the order,
 * except TEST_FAIL_ID, which fails it. See docs/TEST-PAYMENTS.md.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

class Gram_Gateway_Test_Pay extends WC_Payment_Gateway
{
    public const TEST_FAIL_ID = 'failure@razorpay';

    protected string $default_title = 'Credit Card/Debit Card/NetBanking/UPI';
    protected string $default_description = 'Pay securely by Credit or Debit card or Internet Banking or UPI through Razorpay.';

    public function __construct()
    {
        $this->id                 = $this->id ?: 'razorpay';
        $this->method_title       = 'Razorpay (test mode)';
        $this->method_description = 'Test payments only: any card number or UPI ID pays the order, ' . self::TEST_FAIL_ID . ' fails it.';
        $this->has_fields         = true;
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
            'title'       => ['title' => 'Title', 'type' => 'text', 'default' => $this->default_title],
            'description' => ['title' => 'Description', 'type' => 'textarea', 'default' => $this->default_description],
        ];
    }

    private function field_name(): string
    {
        return 'gram_test_pay_' . $this->id;
    }

    public function payment_fields(): void
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        $name = $this->field_name();
        ?>
<p class="form-row form-row-wide gram-test-pay">
	<label for="<?php echo esc_attr($name); ?>">Card number or UPI ID <span class="required">*</span></label>
	<input type="text" class="input-text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" autocomplete="off" placeholder="4111 1111 1111 1111 or success@razorpay" />
	<small>Test mode: no money is taken. Any card number or UPI ID pays the order. Use <code><?php echo esc_html(self::TEST_FAIL_ID); ?></code> to see a failed payment.</small>
</p>
        <?php
    }

    private function entered_id(): string
    {
        // WooCommerce has already checked the checkout nonce before calling the gateway.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return trim(sanitize_text_field(wp_unslash($_POST[$this->field_name()] ?? '')));
    }

    public function validate_fields(): bool
    {
        if ($this->entered_id() === '') {
            wc_add_notice('Enter a card number or UPI ID to pay.', 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $id    = $this->entered_id();

        if (strtolower($id) === self::TEST_FAIL_ID) {
            $order->update_status('failed', sprintf('Test payment failed (%s).', self::TEST_FAIL_ID));
            wc_add_notice('Payment failed. Please try again with another card or UPI ID, or choose Cash on delivery.', 'error');
            return ['result' => 'failure'];
        }

        // Keep only the last 4 characters of what was typed.
        $order->add_order_note(sprintf('Test payment received (%s, ending %s).', $this->method_title, substr($id, -4)));
        $order->payment_complete('pay_test_' . wp_generate_password(14, false));
        WC()->cart->empty_cart();

        return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
    }
}
