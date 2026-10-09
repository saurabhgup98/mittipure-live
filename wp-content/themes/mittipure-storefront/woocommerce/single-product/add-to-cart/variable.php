<?php
/**
 * Variable product add to cart — radio buttons (WC Variations Radio Buttons
 * plugin's template) reshaped to gramiyum.in's rendered markup:
 * td.label (not th), "Clear" link inside td.value, and GreenMart's mobile
 * wrappers (#mobile-close-infor, .mobile-infor-wrapper, .mobile-attribute-list).
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

global $product;

$attribute_keys  = array_keys($attributes);
$variations_json = wp_json_encode($available_variations);
$variations_attr = function_exists('wc_esc_json') ? wc_esc_json($variations_json) : _wp_specialchars($variations_json, ENT_QUOTES, 'UTF-8', true);

do_action('woocommerce_before_add_to_cart_form'); ?>

<div class="mobile-attribute-list">
	<?php foreach ($attributes as $name => $options) :
		$default = $selected_attributes[sanitize_title($name)] ?? '';
		$term    = $default && taxonomy_exists($name) ? get_term_by('slug', $default, $name) : null; ?>
		<div class="list-wrapper">
			<div class="name"><?php echo esc_html(wc_attribute_label($name)); ?></div>
			<div class="value"><?php echo esc_html($term ? $term->name : $default); ?></div>
		</div>
	<?php endforeach; ?>
	<div id="attribute-open"><i class="tb-icon tb-icon-zt-chevron-right"></i></div>
</div>

<form class="variations_form cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint($product->get_id()); ?>" data-product_variations="<?php echo $variations_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
	<div id="mobile-close-infor"><i class="icon-close icons"></i></div>
	<div class="mobile-infor-wrapper">
		<div class="media">
			<div class="mr-3 media-left"><?php echo $product->get_image('woocommerce_gallery_thumbnail'); ?></div>
			<div class="media-body"><div class="infor-body"></div></div>
		</div>
	</div>
	<?php do_action('woocommerce_before_variations_form'); ?>

	<?php if (empty($available_variations) && false !== $available_variations) : ?>
		<p class="stock out-of-stock"><?php echo esc_html(apply_filters('woocommerce_out_of_stock_message', __('This product is currently out of stock and unavailable.', 'woocommerce'))); ?></p>
	<?php else : ?>
		<table class="variations" cellspacing="0">
			<tbody>
				<?php foreach ($attributes as $name => $options) :
					$sanitized_name = sanitize_title($name);
					if (isset($_REQUEST['attribute_' . $sanitized_name])) {
						$checked_value = wc_clean(wp_unslash($_REQUEST['attribute_' . $sanitized_name]));
					} else {
						$checked_value = $selected_attributes[$sanitized_name] ?? '';
					} ?>
					<tr class="attribute-<?php echo esc_attr($sanitized_name); ?>">
						<td class="label"><label for="<?php echo esc_attr($sanitized_name); ?>"><?php echo wc_attribute_label($name); // phpcs:ignore ?></label></td>
						<td class="value"><?php
							if (!empty($options)) {
								if (taxonomy_exists($name)) {
									foreach (wc_get_product_terms($product->get_id(), $name, ['fields' => 'all']) as $term) {
										if (in_array($term->slug, $options, true)) {
											print_attribute_radio($checked_value, $term->slug, $term->name, $sanitized_name);
										}
									}
								} else {
									foreach ($options as $option) {
										print_attribute_radio($checked_value, $option, $option, $sanitized_name);
									}
								}
							}
							echo end($attribute_keys) === $name ? '<a class="reset_variations" href="#">' . esc_html__('Clear', 'woocommerce') . '</a>' : '';
						?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php do_action('woocommerce_after_variations_table'); ?>

		<div class="single_variation_wrap">
			<?php
			do_action('woocommerce_before_single_variation');
			do_action('woocommerce_single_variation');
			do_action('woocommerce_after_single_variation');
			?>
		</div>
	<?php endif; ?>

	<?php do_action('woocommerce_after_variations_form'); ?>
</form>
<div id="mobile-close-infor-wrapper"></div>
<div class="mobile-btn-cart-click "><div id="tbay-click-addtocart"><?php echo esc_html($product->single_add_to_cart_text()); ?></div></div>

<?php
do_action('woocommerce_after_add_to_cart_form');
