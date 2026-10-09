<?php
/**
 * Markup helpers that reproduce gramiyum.in's (GreenMart) page structure:
 * breadcrumb, product card, product prev/next nav, price display rules.
 *
 * @package mittipure-storefront
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- breadcrumb (#tbay-breadscrumb, three variants as on gramiyum.in) ---------- */

function gram_breadcrumb_section(): void
{
    if (is_front_page()) {
        return;
    }
    $home  = '<li><a href="' . esc_url(home_url()) . '">Home</a></li>';
    $crumb = fn ($term) => '<li><a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a></li>';
    $chain = function ($term) use ($crumb) {
        $out = '';
        foreach (array_reverse(get_ancestors($term->term_id, 'product_cat')) as $ancestor_id) {
            $out .= $crumb(get_term($ancestor_id, 'product_cat'));
        }
        return $out;
    };

    if (is_product()) {
        $terms = wc_get_product_terms(get_queried_object_id(), 'product_cat', ['orderby' => 'parent', 'order' => 'DESC']);
        $items = $home . ($terms ? $chain($terms[0]) . $crumb($terms[0]) : '');
        echo '<section id="tbay-breadscrumb" class="tbay-breadscrumb  "><div class="container"><div class="breadscrumb-inner">'
            . '<ol class="tbay-woocommerce-breadcrumb breadcrumb" itemprop="breadcrumb">' . $items . '</ol>'
            . '</div></div></section>';
        return;
    }

    if (is_shop() || is_product_taxonomy()) {
        $term  = is_product_taxonomy() ? get_queried_object() : null;
        $items = $home . ($term ? $chain($term) . '<li>' . esc_html($term->name) . '</li>' : '<li>Shop</li>');
        echo '<section id="tbay-breadscrumb" class="tbay-breadscrumb  active-nav-right "><div class="container"><div class="breadscrumb-inner">'
            . '<ol class="tbay-woocommerce-breadcrumb breadcrumb" >' . $items . '</ol>'
            . '<a href="javascript:history.back()" class="greenmart-back-btn"><i class="tb-icon tb-icon-zt-chevron-left"></i><span class="text">Previous page</span></a>'
            . '</div></div></section>';
        return;
    }

    if (is_search()) {
        $title = sprintf('Search results for &ldquo;%s&rdquo;', esc_html(get_search_query()));
    } elseif (is_singular()) {
        $title = esc_html(get_the_title());
    } else {
        $title = esc_html(wp_strip_all_tags(get_the_archive_title()));
    }
    echo '<section id="tbay-breadscrumb" class="tbay-breadscrumb "><div class="container"><div class="p-relative breadscrumb-inner">'
        . '<ol class="breadcrumb">' . $home . '<li>' . $title . '</li></ol>'
        . '</div></div></section>';
}

/* ---------- prices: "<br> Incl. of all taxes", and no price for variable parents ---------- */

add_filter('woocommerce_get_price_html', function (string $html, WC_Product $product) {
    if ($product->is_type('variable')) {
        return ''; // gramiyum.in shows the price only once a variation is chosen
    }
    return $html === '' ? $html : $html . '<br> Incl. of all taxes';
}, 10, 2);

// Same sort options (and default) as gramiyum.in's shop.
add_filter('woocommerce_catalog_orderby', fn () => [
    'popularity' => 'Sort by popularity',
    'date'       => 'Sort by latest',
    'price'      => 'Sort by price: low to high',
    'price-desc' => 'Sort by price: high to low',
]);
add_filter('woocommerce_default_catalog_orderby', fn () => 'popularity');

/* ---------- product card (GreenMart .product-block.grid markup) ---------- */

function gram_add_to_cart_link(WC_Product $product): string
{
    $type  = $product->get_type();
    $ajax  = $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock();
    $class = " button product_type_$type add_to_cart_button" . ($ajax ? ' ajax_add_to_cart' : '') . " product_type_$type";
    $id    = $product->get_id();
    $sku   = esc_attr($product->get_sku());
    $extra = $ajax
        ? sprintf(' rel="nofollow" data-success_message="%s" role="button"', esc_attr(sprintf('“%s” has been added to your cart', $product->get_name())))
        : ' rel=""';

    return sprintf(
        '<a href="%1$s" rel="nofollow" data-product_id="%2$d" data-product_sku="%3$s" data-quantity="1" class="%4$s" data-product_id="%2$d" data-product_sku="%3$s" aria-label="%5$s"%6$s><i class="tb-icon tb-icon-zt-cart"></i>' . "\n" . '<span class="title-cart">%7$s</span></a>',
        esc_url($product->add_to_cart_url()),
        $id,
        $sku,
        esc_attr($class),
        esc_attr($product->add_to_cart_description()),
        $extra,
        esc_html($product->add_to_cart_text())
    );
}

function gram_product_card(WC_Product $product, string $extra_class = ''): void
{
    $simple = $product->is_type('simple');
    $link   = $product->get_permalink();
    ?>
<div <?php wc_product_class($extra_class, $product); ?>><div class="product-block grid " data-product-id="<?php echo (int) $product->get_id(); ?>"><div class="product-content"><div class="block-inner"><figure class="image">
<?php gram_sale_flash($product); ?><a title="<?php echo esc_attr($product->get_name()); ?>" href="<?php echo esc_url($link); ?>" class="product-image">
<?php echo $product->get_image('woocommerce_thumbnail', ['class' => 'attachment-shop_catalog image-effect']); ?><?php
$hover = $product->get_gallery_image_ids();
if ($hover) {
    echo wp_get_attachment_image($hover[0], 'woocommerce_thumbnail', false, ['class' => 'image-hover']);
} ?>
</a><div class="group-actions-product"><div class="button-wishlist"></div></div></figure></div><div class="caption"><div class="meta"><div class="infor"><div class="name-subtitle"><h3 class="name"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($product->get_name()); ?></a></h3></div>
<?php if ($simple) : ?><span class="price"><?php echo $product->get_price_html(); ?></span><?php endif; ?></div></div><div class="groups-button clearfix"><div class="quantity-group-btn <?php echo $simple ? 'active' : ''; ?>"><?php
    if ($simple && $product->is_purchasable() && $product->is_in_stock()) {
        woocommerce_quantity_input(['min_value' => 1, 'input_value' => 1], $product);
    }
    ?><div class="add-cart"><?php echo gram_add_to_cart_link($product); ?></div></div></div></div></div></div></div>
    <?php
}

/** GreenMart sale badge: <span class="onsale"><span class="saled">-5%</span></span> */
function gram_sale_flash(WC_Product $product): void
{
    if (!$product->is_on_sale() || !$product->is_type('simple')) {
        return;
    }
    $regular = (float) $product->get_regular_price();
    $sale    = (float) $product->get_sale_price();
    if ($regular > 0) {
        printf("<span class=\"onsale\"><span class=\"saled\">-%d%%</span></span>\n", (int) round(($regular - $sale) / $regular * 100));
    }
}

/* ---------- single product: prev/next nav (.product-nav) ---------- */

function gram_product_nav(): void
{
    $side = function ($post, string $pos) {
        if (!$post) {
            return;
        }
        $url   = esc_url(get_permalink($post));
        $img   = get_the_post_thumbnail($post, 'woocommerce_gallery_thumbnail');
        $inner = "<div class='product_single_nav_inner single_nav'>\n<a href=\"$url\">\n<span class='name-pr'>" . esc_html(get_the_title($post)) . "</span>\n</a></div>";
        $links = $pos === 'left'
            ? "<a class='img-link' href=\"$url\">$img</a>$inner<a class='img-link' href=\"$url\"></a>"
            : "<a class='img-link' href=\"$url\"></a>$inner<a class='img-link' href=\"$url\">$img</a>";
        echo "<div class='$pos psnav'>$links</div>";
    };
    echo '<div class="product-nav pull-right"><div class="link-images visible-lg">';
    $side(get_previous_post(false, '', 'product_cat'), 'left');
    $side(get_next_post(false, '', 'product_cat'), 'right');
    echo '</div></div>';
}

/* ---------- archive sidebar (.sidebar-mobile-wrapper) ---------- */

/** Sidebar "Product Categories" menu: [menu-item id, label, slug], as on gramiyum.in. */
function gram_sidebar_categories(): array
{
    return [
        [345316, 'Cold Pressed Oil', 'cold-pressed-oil'],
        [345317, 'Combo Boxes', 'buy-natural-combo-foods-online'],
        [345318, 'Crunchy Snacks', 'snacks'],
        [345320, 'Dried Nuts and Seeds', 'dried-nuts-and-seeds'],
        [345321, 'Ghee', 'cow-ghee'],
        [345324, 'Masala and Spices', 'masala-and-spices'],
        [345325, 'Podi Varities', 'podi-varities'],
        [345330, 'Pulses and Millets', 'millets'],
        [345331, 'Rice', 'traditional-rice-varieties'],
    ];
}

function gram_archive_sidebar(): void
{
    ?>
<div class="sidebar-mobile-wrapper col-12 col-xl-4"><aside class="sidebar sidebar-left" itemscope="itemscope" itemtype="http://schema.org/WPSideBar"><div class="widget-mobile-heading">
<a href="javascript:void(0);" class="close-side-widget"><i class="icon-close icons"></i></a></div><aside id="tbay_custom_menu-2" class="widget widget_tbay_custom_menu"><div class="tbay_custom_menu wpb_content_element treeview-menu"><div class="widget"><h2 class="widgettitle">Product Categories</h2><nav class="tbay_custom_menu-nav"><div class="menu-category-menu-container"><ul id="<?php echo esc_attr(gram_rand_id('mobile-category')); ?>" class="menu" data-id="mobile-category" data-format="no-builder"><?php
    foreach (gram_sidebar_categories() as [$item_id, $label, $slug]) {
        $cur = is_product_category($slug) ? ' current-menu-item active' : '';
        printf(
            '<li class="menu-item menu-item-type-taxonomy menu-item-object-product_cat%s menu-item-%d%s aligned-left"><a href="%s">%s</a></li>',
            esc_attr($cur),
            $item_id,
            $cur ? ' active active active' : '',
            esc_url(gram_cat_link($slug)),
            esc_html($label)
        );
    }
    ?></ul></div></nav></div></div></aside><?php
    the_widget('WC_Widget_Price_Filter', ['title' => 'Filter by price'], [
        'before_widget' => '<aside id="woocommerce_price_filter-3" class="widget woocommerce widget_price_filter">',
        'after_widget'  => '</aside>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ]);
    ?></aside></div>
    <?php
}

/**
 * WooCommerce's gallery, but with the wrapper as <figure> (as in the
 * WooCommerce version gramiyum.in runs) instead of today's <div>.
 */
function gram_product_gallery(): void
{
    ob_start();
    woocommerce_show_product_images();
    $html = ob_get_clean();

    $open = '<div class="woocommerce-product-gallery__wrapper">';
    $pos  = strpos($html, $open);
    if ($pos !== false) {
        $html = substr_replace($html, '<figure class="woocommerce-product-gallery__wrapper">', $pos, strlen($open));
        // Wrapper's closing tag is the second-to-last </div> (the last closes the gallery).
        $last   = strrpos($html, '</div>');
        $closer = strrpos(substr($html, 0, $last), '</div>');
        $html   = substr_replace($html, '</figure>', $closer, strlen('</div>'));
    }
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
}

/* ---------- stock: "<span class="label">Availability:</span>In stock" ---------- */

add_filter('woocommerce_get_stock_html', function (string $html) {
    return preg_replace('#^(<p class="stock [^"]*">)#', '$1<span class="label">Availability:</span>', trim($html));
});
