<?php
/**
 * Quantity input in gramiyum.in's (GreenMart) markup: .box-quantity wrapper
 * with minus/plus buttons. Behaviour for the buttons is in assets/shop.js.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

if ($max_value && $min_value === $max_value) : ?>
	<div class="quantity hidden">
		<input type="hidden" id="<?php echo esc_attr($input_id); ?>" class="qty" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($min_value); ?>" />
	</div>
<?php else : ?>
<div class="box-quantity"><div class="quantity">
<label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php esc_html_e('Quantity', 'woocommerce'); ?></label>
<button class="minus" type="button" value="-" tabindex="0"><i class="tb-icon tb-icon-zz-minus"></i></button>
<input
type="number"
id="<?php echo esc_attr($input_id); ?>"
class="<?php echo esc_attr(join(' ', (array) $classes)); ?>"
step="<?php echo esc_attr($step); ?>"
min="<?php echo esc_attr($min_value); ?>"
max="<?php echo esc_attr(0 < $max_value ? $max_value : ''); ?>"
name="<?php echo esc_attr($input_name); ?>"
value="<?php echo esc_attr($input_value); ?>"
title="Qty"
size="4"
placeholder="<?php echo esc_attr($placeholder); ?>"
inputmode="<?php echo esc_attr($inputmode); ?>" />
<button class="plus" type="button" value="+" tabindex="0"><i class="tb-icon tb-icon-zz-plus"></i></button></div></div>
<?php endif;
