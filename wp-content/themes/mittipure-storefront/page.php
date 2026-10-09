<?php
/**
 * Regular pages (cart, checkout, my-account, …) in gramiyum.in's page wrapper.
 *
 * @package mittipure-storefront
 */

get_header(); ?>

<section id="main-container" class="container inner">
	<div class="row">
		<div id="main-content" class="main-page col-12">
			<div id="main" class="site-main">
				<?php while (have_posts()) :
					the_post(); ?>
					<header class="page-header">
						<h1 class="page-title"><?php the_title(); ?></h1>
					</header>
					<?php the_content(); ?>
				<?php endwhile; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
