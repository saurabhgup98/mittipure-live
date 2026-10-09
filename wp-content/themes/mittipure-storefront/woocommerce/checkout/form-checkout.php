<?php
/**
 * Checkout form in gramiyum.in's (GreenMart) layout: customer details and
 * order review side by side, payment methods in their own .order-payment
 * block under the review table (moved off woocommerce_checkout_order_review
 * in functions.php; WooCommerce's ajax refresh still finds .woocommerce-checkout-payment).
 *
 * @package mittipure-storefront
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
<div class="clearfix row">
	<div class="details-check col-lg-6 col-sm-12">
	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>
			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>
	</div>
	<div class="details-review col-lg-6 col-sm-12">
		<div class="order-review">
			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
			<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>
			<div class="order-payment">
				<h3 id="order_payment_heading"><?php esc_html_e( 'Payment method', 'woocommerce' ); ?></h3><?php woocommerce_checkout_payment(); ?>
			</div>
			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
		</div>
	</div>
</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
