<?php
/**
 * Simple product add to cart, with gramiyum.in's (GreenMart) mobile wrappers
 * inside/after the form. Based on WooCommerce's single-product/add-to-cart/simple.php.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

global $product;

if (!$product->is_purchasable()) {
    return;
}

echo wc_get_stock_html($product); // phpcs:ignore WordPress.Security.EscapeOutput

if ($product->is_in_stock()) : ?>

	<?php do_action('woocommerce_before_add_to_cart_form'); ?>

	<form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'><div id="mobile-close-infor"><i class="icon-close icons"></i></div><div class="mobile-infor-wrapper"><div class="media"><div class="mr-3 media-left">
<?php echo $product->get_image('woocommerce_gallery_thumbnail'); ?></div><div class="media-body"><div class="infor-body"><p class="price"><?php echo $product->get_price_html(); ?></p><?php echo wc_get_stock_html($product); ?></div></div></div></div><?php
		do_action('woocommerce_before_add_to_cart_button');
		do_action('woocommerce_before_add_to_cart_quantity');

		woocommerce_quantity_input([
			'min_value'   => $product->get_min_purchase_quantity(),
			'max_value'   => $product->get_max_purchase_quantity(),
			'input_value' => isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : $product->get_min_purchase_quantity(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		]);

		do_action('woocommerce_after_add_to_cart_quantity');
		?>
<button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html($product->single_add_to_cart_text()); ?></button><?php do_action('woocommerce_after_add_to_cart_button'); ?></form><div id="mobile-close-infor-wrapper"></div><div class="mobile-btn-cart-click "><div id="tbay-click-addtocart"><?php echo esc_html($product->single_add_to_cart_text()); ?></div></div>

	<?php do_action('woocommerce_after_add_to_cart_form'); ?>

<?php endif;
