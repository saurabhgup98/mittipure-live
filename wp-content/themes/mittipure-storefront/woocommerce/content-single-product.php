<?php
/**
 * Single product body in gramiyum.in's (GreenMart) structure: title above
 * the two columns, summary = price / short description / add-to-cart / meta,
 * description + additional information as full-width sections (no tab bar),
 * related products in a side column.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

global $product;

if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
    return;
}

gram_product_nav();
woocommerce_output_all_notices();
?>
<div class="single-product-before-wrapper"><h1 class="product_title entry-title"><?php the_title(); ?></h1><div class="single-rating-share"></div></div>
<div class="row row-active-full">
	<div class="product-inside-wrapper col-12 col-xl-8">
		<div id="product-<?php the_ID(); ?>" <?php wc_product_class('style-horizontal form-cart-popup', $product); ?>>
			<div class="row">
				<div class="image-mains col-md-6"><?php gram_product_gallery(); ?></div>
				<div class="information col-md-6">
					<div class="summary entry-summary ">
						<?php
						woocommerce_template_single_price();
						woocommerce_template_single_excerpt();
						woocommerce_template_single_add_to_cart();
						woocommerce_template_single_meta();
						?>
					</div>
				</div>
			</div>
			<div class="tbay-ywfbt-wrapper tbay-ywfbt-free"><div class="container"></div></div>
			<div class="woocommerce-tabs-full tabs-v1 woocommerce-tabs">
				<div class="tab-content">
					<?php if ($product->get_description()) : ?>
						<div id="tab-description" class="tab-full"><?php the_content(); ?></div>
					<?php endif; ?>
					<?php if ($product->has_attributes() || $product->has_weight() || $product->has_dimensions()) : ?>
						<div id="tab-additional_information" class="tab-full"><?php wc_display_product_attributes($product); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
	$related = array_filter(array_map('wc_get_product', wc_get_related_products($product->get_id(), 4)));
	if ($related) : ?>
		<div class="product-related-wrapper col-12 col-xl-4">
			<div class="related products widget">
				<h3 class="widget-title"><span>Related products</span></h3>
				<div class="owl-carousel products related">
					<?php foreach ($related as $related_product) : ?>
						<div class="item"><?php gram_product_card($related_product, 'products-grid'); ?></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
