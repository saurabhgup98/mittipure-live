<?php
/**
 * Shop + product category archive in gramiyum.in's (GreenMart) structure.
 * WooCommerce functions are called directly (not via hooks) so Storefront's
 * own loop wrappers don't leak into the markup.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

get_header(); ?>

<section id="main-container" class="main-content container">
	<h1 class="page-title title-woocommerce"><?php woocommerce_page_title(); ?></h1>
	<div class="row flex-row-reverse shop-page">
		<div id="main-content" class="archive-shop col-xs-12 col-12 col-12 col-xl-8 pull-right">
			<div id="primary" class="content-area">
				<main id="main" class="site-main" role="main">
					<div id="content" class="site-content" role="main">
						<?php
						if (is_shop()) {
							echo '<ul class="all-subcategories row"></ul>';
						}
						woocommerce_taxonomy_archive_description();
						?>
						<div class="tbay-filter">
							<div class="tbay-sidebar-mobile-btn"><i class="tb-icon tb-icon-zz-filter"></i>Filter</div>
							<div class="display-mode-warpper display-mode">
								<a href="javascript:void(0);" id="display-mode-grid" class="change-view display-mode-btn active" title="'Grid'"><i class="tb-icon tb-icon-zt-view-module"></i></a>
								<a href="javascript:void(0);" id="display-mode-list" class="change-view display-mode-btn list " title="'List'"><i class="tb-icon tb-icon-zt-shape"></i></a>
							</div>
							<?php
							woocommerce_output_all_notices();
							if (woocommerce_product_loop()) {
								woocommerce_result_count();
								woocommerce_catalog_ordering();
							}
							?>
						</div>

						<?php if (woocommerce_product_loop()) : ?>
							<div class="products products-grid">
								<div class="row" data-xlgdesktop=4 data-desktop=4 data-desktopsmall=4 data-tablet=3 data-landscape=2 data-mobile=2>
									<?php
									wc_set_loop_prop('columns', 4);
									while (have_posts()) {
										the_post();
										gram_product_card(wc_get_product(get_the_ID()));
									}
									?>
								</div>
							</div>
							<?php woocommerce_pagination(); ?>
						<?php else : ?>
							<?php wc_no_products_found(); ?>
						<?php endif; ?>
					</div>
				</main>
			</div>
		</div>
		<?php gram_archive_sidebar(); ?>
	</div>
</section>

<?php
get_footer();
