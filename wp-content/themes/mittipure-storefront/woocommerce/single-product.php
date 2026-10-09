<?php
/**
 * Single product page wrapper in gramiyum.in's (GreenMart) structure.
 *
 * @package mittipure-storefront
 */

defined('ABSPATH') || exit;

get_header(); ?>

<section id="main-container" class="main-content container">
	<h1 class="page-title title-woocommerce">Shop</h1>
	<div class="row  shop-page">
		<div id="main-content" class="singular-shop col-xs-12 col-12 col-xs-12 col-md-12 col-12">
			<div id="primary" class="content-area">
				<main id="main" class="site-main" role="main">
					<div id="content" class="site-content" role="main">
						<?php
						while (have_posts()) {
							the_post();
							wc_get_template_part('content', 'single-product');
						}
						?>
					</div>
				</main>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
