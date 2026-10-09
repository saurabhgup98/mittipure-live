<?php
/**
 * Home page. gramiyum.in's home is an Elementor layout (sliders, banners);
 * only its outer wrapper is mirrored — the sections below are look-alikes.
 *
 * @package mittipure-storefront
 */

get_header();

$products = wc_get_products(['status' => 'publish', 'limit' => 8, 'orderby' => 'date', 'order' => 'DESC']);
?>

<section id="main-container" class="container inner">
	<div class="row">
		<div id="main-content" class="main-page col-12">
			<div id="main" class="site-main">

				<div class="gram-hero">
					<div class="gram-hero__text">
						<p class="gram-hero__eyebrow">Nothing Added. Nothing Extracted.</p>
						<h2>Cold pressed oils, ghee &amp; natural foods</h2>
						<a class="button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Shop now</a>
					</div>
				</div>

				<h2 class="gram-section-title">Shop by category</h2>
				<ul class="gram-category-tiles">
					<?php foreach (gram_mega_menu_columns() as [, , , $items]) :
						foreach ($items as [, $label, $slug]) : ?>
						<li><a href="<?php echo esc_url(gram_cat_link($slug)); ?>"><?php echo esc_html($label); ?></a></li>
					<?php endforeach;
					endforeach; ?>
				</ul>

				<h2 class="gram-section-title">New arrivals</h2>
				<div class="products products-grid">
					<div class="row" data-xlgdesktop=4 data-desktop=4 data-desktopsmall=4 data-tablet=3 data-landscape=2 data-mobile=2>
						<?php foreach ($products as $p) {
							gram_product_card($p);
						} ?>
					</div>
				</div>

			</div>
		</div>
	</div>
</section>

<?php
get_footer();
