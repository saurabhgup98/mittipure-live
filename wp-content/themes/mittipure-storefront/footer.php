<?php
/**
 * Footer: closes #tbay-main-content (opened in header.php). gramiyum.in's
 * footer is a large Elementor layout; only its outer element is mirrored.
 *
 * @package mittipure-storefront
 */
?>
</div><!-- #tbay-main-content -->

<footer id="tbay-footer" class="tbay-footer">
	<div class="container">
		<div class="gram-footer-cols">
			<div>
				<h3 class="heading-tbay-title"><span class="title">MittiPure</span></h3>
				<p>Online Store for Cold Pressed Oil and Natural Food Products – Nothing Added. Nothing Extracted.</p>
			</div>
			<div>
				<h3 class="heading-tbay-title"><span class="title">Categories</span></h3>
				<ul class="menu">
					<?php foreach (gram_mega_menu_columns() as [, , , $items]) :
						foreach ($items as [, $label, $slug]) : ?>
						<li><a href="<?php echo esc_url(gram_cat_link($slug)); ?>"><?php echo esc_html($label); ?></a></li>
					<?php endforeach;
					endforeach; ?>
				</ul>
			</div>
			<div>
				<h3 class="heading-tbay-title"><span class="title">Customer Care</span></h3>
				<p><a href="tel:+919500129121">+91 95001 29121</a></p>
				<p class="gram-test-note">MittiPure — SDK test site, not a real store.</p>
			</div>
		</div>
	</div>
</footer>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
