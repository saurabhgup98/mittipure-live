<?php
/**
 * MittiPure Storefront child theme.
 *
 * The header/nav markup mirrors gramiyum.in (GreenMart + Elementor), so the
 * ids/classes below are copied from that site on purpose — do not "clean them up".
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/inc/template-helpers.php';
require_once __DIR__ . '/inc/checkout.php';

/* ---------- assets ---------- */

add_action('wp_enqueue_scripts', function () {
    $ver = wp_get_theme()->get('Version');
    wp_enqueue_style('gram-fonts', 'https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap', [], null);
    wp_enqueue_style('gram-header', get_stylesheet_directory_uri() . '/assets/header.css', ['storefront-style'], $ver);
    wp_enqueue_style('gram-shop', get_stylesheet_directory_uri() . '/assets/shop.css', ['storefront-woocommerce-style'], $ver);
    wp_enqueue_script('gram-shop', get_stylesheet_directory_uri() . '/assets/shop.js', ['jquery'], $ver, true);
    // Classic mini-cart fragments, so the header cart count/total update after add-to-cart.
    wp_enqueue_script('wc-cart-fragments');
}, 20);

// Storefront's mobile footer bar and stock header pieces are replaced by our header.php.
add_action('init', function () {
    remove_action('storefront_footer', 'storefront_handheld_footer_bar', 999);
});

/* ---------- main nav (ids copied from gramiyum.in's "gramiyum-menu") ---------- */

/** GreenMart suffixes menu ids with 5 random chars on every render (e.g. menu-1-EJtLq). */
function gram_rand_id(string $prefix): string
{
    return $prefix . '-' . wp_generate_password(5, false);
}

/**
 * Mega-menu columns: [ul id, [[menu-item id, label, category slug], ...]].
 * Only the 10 categories we seed; ids and column placement as on gramiyum.in.
 */
function gram_mega_menu_columns(): array
{
    return [
        ['8aa5dc3', '24678e1', 'category-1', [
            [345208, 'Rice', 'traditional-rice-varieties'],
            [345206, 'Ghee', 'cow-ghee'],
            [345207, 'Pulses and Millets', 'millets'],
            [345209, 'Dried Nuts and Seeds', 'dried-nuts-and-seeds'],
        ]],
        ['a7c532d', '72666ea', 'category-2', [
            [345212, 'Podi Varities', 'podi-varities'],
            [345211, 'Masala and Spices', 'masala-and-spices'],
        ]],
        ['679d529', 'c4fcb50', 'category-3', [
            [345225, 'Cold Pressed Oil', 'cold-pressed-oil'],
            [445881, 'Diabetic-Friendly', 'diabetic-friendly'],
        ]],
        ['8e16a09', '5de9e45', 'category-4', [
            [431846, 'Crunchy Snacks', 'snacks'],
            [431848, 'Combo Boxes', 'buy-natural-combo-foods-online'],
        ]],
    ];
}

function gram_cat_link(string $slug): string
{
    $link = get_term_link($slug, 'product_cat');
    return is_wp_error($link) ? home_url('/') : $link;
}

function gram_is_current_cat(string $slug): bool
{
    return is_product_category($slug);
}

function gram_main_menu(): void
{
    $front_id  = (int) get_option('page_on_front');
    $is_home   = is_front_page();
    $home_cls  = 'menu-item menu-item-type-post_type menu-item-object-page menu-item-home'
        . ($is_home ? " current-menu-item page_item page-item-$front_id current_page_item active" : '')
        . ' menu-item-372850 level-0' . ($is_home ? ' active  active ' : '') . ' aligned-left';
    $shop_cur  = is_shop() ? ' current-menu-item current_page_item active' : '';
    $shop_par  = is_product() ? ' current_page_parent' : '';
    ?>
<ul id="<?php echo esc_attr(gram_rand_id('menu-1')); ?>" class="elementor-nav-menu menu nav navbar-nav megamenu flex-row" data-id="gramiyum-menu">
	<li id="menu-item-372850" class="<?php echo esc_attr($home_cls); ?>"><a class="elementor-item" href="<?php echo esc_url(home_url('/')); ?>">Home</a></li>
	<li id="menu-item-345197" class="menu-item menu-item-type-post_type menu-item-object-page<?php echo esc_attr($shop_par); ?> menu-item-345197 level-0 active-mega-menu aligned-fullwidth<?php echo esc_attr($shop_cur); ?>"><a class="elementor-item" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Categories <b class="caret"></b></a>
		<div class="dropdown-menu dropdown-load-ajax"><div class="dropdown-menu-inner"><div class="dropdown-html-placeholder" data-id="345202"></div></div></div>
	</li>
	<li id="menu-item-345200" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-345200 level-0 aligned-left"><a class="elementor-item" href="<?php echo esc_url(gram_cat_link('cold-pressed-oil')); ?>">Cold Pressed Oils</a></li>
	<li id="menu-item-457198" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-457198 level-0 aligned-left"><a class="elementor-item" href="<?php echo esc_url(home_url('/product/sweet-and-snack-combo-pack/')); ?>">🎉Ayudha Pooja Combo 🎊</a></li>
	<li id="menu-item-445919" class="menu-item menu-item-type-taxonomy menu-item-object-product_cat<?php echo gram_is_current_cat('diabetic-friendly') ? ' current-menu-item' : ''; ?> menu-item-445919 level-0 aligned-left"><a class="elementor-item" href="<?php echo esc_url(gram_cat_link('diabetic-friendly')); ?>">Smart Carb Choices</a></li>
</ul>
    <?php
}

/* ---------- header mini-cart (GreenMart markup) ---------- */

function gram_mini_cart_count(): void
{
    $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    echo '<span class="mini-cart-items">' . (int) $count . '</span>';
}

function gram_mini_cart_subtotal(): void
{
    echo '<span class="mini-cart-subtotal">' . (WC()->cart ? WC()->cart->get_cart_subtotal() : wc_price(0)) . '</span>';
}

add_filter('woocommerce_add_to_cart_fragments', function (array $fragments) {
    ob_start();
    gram_mini_cart_count();
    $fragments['.tbay-topcart .mini-cart-items'] = ob_get_clean();
    ob_start();
    gram_mini_cart_subtotal();
    $fragments['.tbay-topcart .mini-cart-subtotal'] = ob_get_clean();
    return $fragments;
});

/* ---------- Recent Viewed Product (GreenMart header widget) ---------- */

// WooCommerce only records views when its own widget is active; record them
// always, in the same cookie WooCommerce uses.
add_action('template_redirect', function () {
    if (!is_singular('product')) {
        return;
    }
    $id     = get_queried_object_id();
    $viewed = gram_recently_viewed_ids();
    $viewed = array_values(array_diff($viewed, [$id]));
    $viewed[] = $id;
    $viewed = array_slice($viewed, -15);
    wc_setcookie('woocommerce_recently_viewed', implode('|', $viewed));
}, 25);

function gram_recently_viewed_ids(): array
{
    $raw = isset($_COOKIE['woocommerce_recently_viewed']) ? wp_unslash($_COOKIE['woocommerce_recently_viewed']) : '';
    return array_values(array_filter(array_map('absint', explode('|', $raw))));
}

function gram_recently_viewed(): void
{
    $ids = array_reverse(gram_recently_viewed_ids());
    if (is_singular('product')) {
        $ids = array_values(array_diff($ids, [get_queried_object_id()]));
    }
    $ids = array_slice($ids, 0, 8);
    ?>
<div class="tbay-element tbay-element-product-recently-viewed product-recently-viewed-header" data-column="8">
	<h3 class="header-title">Recent Viewed Product</h3>
	<div class="content-view<?php echo $ids ? '' : ' empty'; ?>">
		<div class="list-recent">
			<?php if (!$ids) : ?>
				You have no recently viewed item.
			<?php else :
				foreach ($ids as $pid) :
					$p = wc_get_product($pid);
					if (!$p || !$p->is_visible()) {
						continue;
					} ?>
				<div class="item-recent">
					<a class="img-recent" href="<?php echo esc_url($p->get_permalink()); ?>" title="<?php echo esc_attr($p->get_name()); ?>"><?php echo $p->get_image('woocommerce_gallery_thumbnail'); ?></a>
				</div>
			<?php endforeach;
			endif; ?>
		</div>
	</div>
</div>
    <?php
}

/**
 * Mega-menu body. gramiyum.in ships only a placeholder and loads this by
 * AJAX on first hover; we print it as a template and swap it in on hover
 * (assets/shop.js), so the DOM before/after hover matches.
 */
function gram_mega_menu_body(): void
{
    ?>
<div data-elementor-type="wp-post" data-elementor-id="345202" class="elementor elementor-345202">
	<section class="elementor-section elementor-top-section elementor-element elementor-element-4142b57 elementor-section-boxed elementor-section-height-default elementor-section-height-default wpr-particle-no wpr-jarallax-no wpr-parallax-no wpr-sticky-section-no" data-id="4142b57" data-element_type="section">
		<div class="elementor-container elementor-column-gap-default">
			<?php foreach (gram_mega_menu_columns() as [$col_id, $widget_id, $ul_id, $items]) : ?>
			<div class="elementor-column elementor-col-25 elementor-top-column elementor-element elementor-element-<?php echo esc_attr($col_id); ?>" data-id="<?php echo esc_attr($col_id); ?>" data-element_type="column">
				<div class="elementor-widget-wrap elementor-element-populated">
					<div class="elementor-element elementor-element-<?php echo esc_attr($widget_id); ?> elementor-widget elementor-widget-tbay-menu-vertical" data-id="<?php echo esc_attr($widget_id); ?>" data-element_type="widget" data-widget_type="tbay-menu-vertical.default">
						<div class="elementor-widget-container">
							<div class="tbay-element tbay-element-menu-vertical">
								<div class="menu-vertical-container">
									<ul id="<?php echo esc_attr(gram_rand_id($ul_id)); ?>" class="menu-vertical nav">
										<?php foreach ($items as [$item_id, $label, $slug]) :
											$cur = gram_is_current_cat($slug) ? ' current-menu-item' : ''; ?>
										<li id="menu-item-<?php echo (int) $item_id; ?>" class="menu-item menu-item-type-taxonomy menu-item-object-product_cat<?php echo esc_attr($cur); ?> menu-item-<?php echo (int) $item_id; ?>"><a href="<?php echo esc_url(gram_cat_link($slug)); ?>"><?php echo esc_html($label); ?></a></li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
</div>
    <?php
}

add_action('wp_footer', function () {
    echo '<script type="text/template" id="gram-megamenu-345202">';
    gram_mega_menu_body();
    echo '</script>';
});
