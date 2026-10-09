<?php
/**
 * Mini-cart, wrapped in GreenMart's markup (as rendered on gramiyum.in).
 * The empty state is copied verbatim; the non-empty list is WooCommerce's
 * own template inside the same wrappers.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;
?>
<div class="mini_cart_content">
	<div class="mini_cart_inner">
		<div class="mcart-border">
			<?php if (WC()->cart && !WC()->cart->is_empty()) : ?>
				<?php include WC()->plugin_path() . '/templates/cart/mini-cart.php'; ?>
			<?php else : ?>
				<ul class="cart_empty ">
					<li><span>Your cart is empty</span></li>
					<li class="total"><a class="button wc-continue" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Continue shopping<i class="tb-icon tb-icon-angle-right"></i></a></li>
				</ul>
			<?php endif; ?>
			<div class="clearfix"></div>
		</div>
	</div>
</div>
