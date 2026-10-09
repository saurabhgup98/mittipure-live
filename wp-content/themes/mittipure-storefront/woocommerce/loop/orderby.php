<?php
/**
 * Shop ordering form as rendered on gramiyum.in: "<span>Sort by: </span>"
 * before the select (WooCommerce's loop/orderby.php otherwise).
 *
 * @package mittipure-storefront
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form class="woocommerce-ordering" method="get">
<span>Sort by: </span>
<select name="orderby" class="orderby" aria-label="<?php esc_attr_e('Shop order', 'woocommerce'); ?>"><?php foreach ($catalog_orderby_options as $id => $name) : ?><option value="<?php echo esc_attr($id); ?>" <?php selected($orderby, $id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?>
</select>
<input type="hidden" name="paged" value="1" /><?php wc_query_string_form_fields(null, ['orderby', 'submit', 'paged', 'product-page']); ?></form>
