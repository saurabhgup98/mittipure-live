<?php
/**
 * Stand-in for gramiyum.in's Easebuzz gateway: same id, title and
 * description, so the payment list matches. On this test site it pays the
 * same way as the Razorpay stand-in (test mode, see class-gram-gateway-test-pay.php).
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

class Gram_Gateway_Easebuzz extends Gram_Gateway_Test_Pay
{
    protected string $default_title = 'Easebuzz Payment Gateway';
    protected string $default_description = 'Pay securely by Credit or Debit card or internet banking or UPI or Wallets through Easebuzz.';

    public function __construct()
    {
        $this->id = 'payeasebuzz';
        parent::__construct();
        $this->method_title = 'Easebuzz (test mode)';
    }
}
