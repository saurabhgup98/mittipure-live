<?php
/**
 * Header: Storefront's document shell, but the <header> itself reproduces
 * gramiyum.in's GreenMart/Elementor markup (same element ids, classes and
 * data-ids) so SDK selectors written against gramiyum.in match here.
 * Source snapshot: gramiyum.in homepage, 2026-10-07.
 *
 * @package mittipure-storefront
 */

$gram_cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="hfeed site">

<header id="tbay-header" class="tbay_header-template site-header">
	<div data-elementor-type="wp-post" data-elementor-id="2958" class="elementor elementor-2958">

		<?php /* ---------- Top bar ---------- */ ?>
		<section class="elementor-section elementor-top-section elementor-element elementor-element-6721b9c9 elementor-section-content-middle elementor-section-stretched elementor-section-height-min-height elementor-section-boxed elementor-section-height-default elementor-section-items-middle wpr-particle-no wpr-jarallax-no wpr-parallax-no wpr-sticky-section-no" data-id="6721b9c9" data-element_type="section">
			<div class="elementor-container elementor-column-gap-default">
				<div class="elementor-column elementor-col-50 elementor-top-column elementor-element elementor-element-5a1bf856" data-id="5a1bf856" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-32df86b2 elementor-widget elementor-widget-button" data-id="32df86b2" data-element_type="widget" data-widget_type="button.default">
							<div class="elementor-widget-container">
								<div class="elementor-button-wrapper">
									<a class="elementor-button elementor-size-sm" role="button">
										<span class="elementor-button-content-wrapper">
											<span class="elementor-button-text">Free shipping on all orders over Rs.499</span>
										</span>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="elementor-column elementor-col-50 elementor-top-column elementor-element elementor-element-440517dd" data-id="440517dd" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<?php // Hidden on every breakpoint on gramiyum.in too; kept for selector parity. ?>
						<div class="elementor-element elementor-element-7e1ae591 w-auto elementor-hidden-desktop elementor-hidden-tablet elementor-hidden-mobile elementor-widget elementor-widget-button" data-id="7e1ae591" data-element_type="widget" data-widget_type="button.default">
							<div class="elementor-widget-container"><div class="elementor-button-wrapper"><a href="<?php echo esc_url(home_url('/become-a-vendor/')); ?>" class="elementor-button-link elementor-button elementor-size-sm" role="button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">Become A Vendor</span></span></a></div></div>
						</div>
						<div class="elementor-element elementor-element-5ec6dacd w-auto elementor-hidden-desktop elementor-hidden-tablet elementor-hidden-mobile elementor-widget elementor-widget-button" data-id="5ec6dacd" data-element_type="widget" data-widget_type="button.default">
							<div class="elementor-widget-container"><div class="elementor-button-wrapper"><a href="<?php echo esc_url(home_url('/tracking-order/')); ?>" class="elementor-button-link elementor-button elementor-size-sm" role="button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">For Bulk Orders</span></span></a></div></div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<?php /* ---------- Logo / search / customer care / account / cart ---------- */ ?>
		<section class="element-sticky-header elementor-section elementor-top-section elementor-element elementor-element-7b9afd15 elementor-section-content-middle elementor-section-boxed elementor-section-height-default elementor-section-height-default wpr-particle-no wpr-jarallax-no wpr-parallax-no wpr-sticky-section-no" data-id="7b9afd15" data-element_type="section">
			<div class="elementor-container elementor-column-gap-default">

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-54143db3" data-id="54143db3" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-4608815c w-auto elementor-widget elementor-widget-greenmart-site-logo elementor-widget-tbay-base" data-id="4608815c" data-element_type="widget" data-widget_type="greenmart-site-logo.default">
							<div class="elementor-widget-container">
								<div class="tbay-element tbay-element-site-logo">
									<div class="header-logo">
										<a href="<?php echo esc_url(home_url()); ?>">
											<img width="750" height="146" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/horizontal-logo.svg'); ?>" class="header-logo-img" alt="horizontal logo" decoding="async" fetchpriority="high" />
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-245d1ab" data-id="245d1ab" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-a387821 elementor-widget elementor-widget-shortcode" data-id="a387821" data-element_type="widget" data-widget_type="shortcode.default">
							<div class="elementor-widget-container">
								<div class="elementor-shortcode">
									<div class="dgwt-wcas-search-wrapp dgwt-wcas-has-submit woocommerce dgwt-wcas-style-pirx js-dgwt-wcas-layout-classic dgwt-wcas-layout-classic js-dgwt-wcas-mobile-overlay-enabled dgwt-wcas-search-darkoverl-mounted js-dgwt-wcas-search-darkoverl-mounted">
										<form class="dgwt-wcas-search-form" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
											<div class="dgwt-wcas-sf-wrapp">
												<label class="screen-reader-text" for="dgwt-wcas-search-input-2">Products search</label>
												<input id="dgwt-wcas-search-input-2" type="search" class="dgwt-wcas-search-input" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search for products..." autocomplete="off" />
												<div class="dgwt-wcas-preloader"></div>
												<div class="dgwt-wcas-voice-search"></div>
												<button type="submit" aria-label="Search" class="dgwt-wcas-search-submit">
													<svg class="dgwt-wcas-ico-magnifier" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
												</button>
												<input type="hidden" name="post_type" value="product"/>
												<input type="hidden" name="dgwt_wcas" value="1"/>
											</div>
										</form>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-288adc74" data-id="288adc74" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-14cf01ce elementor-vertical-align-bottom elementor-view-default elementor-widget elementor-widget-icon-box" data-id="14cf01ce" data-element_type="widget" data-widget_type="icon-box.default">
							<div class="elementor-widget-container">
								<div class="elementor-icon-box-wrapper">
									<div class="elementor-icon-box-content">
										<h3 class="elementor-icon-box-title"><span>Cutomer Care</span></h3>
										<p class="elementor-icon-box-description"><a href="tel:+919500129121">+91 95001 29121</a></p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-21ecf23c" data-id="21ecf23c" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-6bf5facf layout-account-column elementor-widget w-auto elementor-widget-tbay-account" data-id="6bf5facf" data-element_type="widget" data-widget_type="tbay-account.default">
							<div class="elementor-widget-container">
								<div class="tbay-element tbay-element-account header-icon">
									<div class="tbay-login">
										<a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="account-button">
											<span class="title-account"><i aria-hidden="true" class="tb-icon tb-icon-zz-za-user"></i> Account</span>
											<?php if (is_user_logged_in()) : ?>
												<span class="text-account"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
											<?php else : ?>
												<span class="text-account">Login/Register</span>
											<?php endif; ?>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-2c514e97" data-id="2c514e97" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-356eb63e layout-wrapper-title-price-column position-total-absolute elementor-widget w-auto elementor-widget-tbay-mini-cart" data-id="356eb63e" data-element_type="widget" data-widget_type="tbay-mini-cart.default">
							<div class="elementor-widget-container">
								<div class="tbay-element tbay-element-mini-cart">
									<div class="tbay-topcart popup">
										<div id="cart" class="cart-dropdown cart-popup dropdown">
											<a class="dropdown-toggle mini-cart" data-toggle="dropdown" aria-expanded="true" role="button" aria-haspopup="true" data-delay="0" href="javascript:void(0);" title="View your shopping cart">
												<span class="cart-icon"><i class="tb-icon tb-icon-zt-cart"></i></span>
												<span class="wrapper-title-cart">
													<span class="text-cart">Cart</span>
													<?php gram_mini_cart_count(); ?>
													<?php gram_mini_cart_subtotal(); ?>
												</span>
											</a>
											<div class="dropdown-menu">
												<div class="widget_shopping_cart_content">
													<?php woocommerce_mini_cart(); ?>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="elementor-column elementor-col-16 elementor-top-column elementor-element elementor-element-8665ded" data-id="8665ded" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-bcf7d30 elementor-widget elementor-widget-shortcode" data-id="bcf7d30" data-element_type="widget" data-widget_type="shortcode.default">
							<div class="elementor-widget-container"><div class="elementor-shortcode">[mini-wallet]</div></div>
						</div>
					</div>
				</div>

			</div>
		</section>

		<?php /* ---------- Main nav + Recent Viewed Product ---------- */ ?>
		<section class="element-sticky-header elementor-section elementor-top-section elementor-element elementor-element-4b7e20b5 elementor-section-stretched elementor-section-boxed elementor-section-height-default elementor-section-height-default wpr-particle-no wpr-jarallax-no wpr-parallax-no wpr-sticky-section-no" data-id="4b7e20b5" data-element_type="section">
			<div class="elementor-container elementor-column-gap-default">
				<div class="elementor-column elementor-col-50 elementor-top-column elementor-element elementor-element-36f4f25a" data-id="36f4f25a" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-7c89fdbe elementor-nav-menu__align-flex-start elementor-widget elementor-widget-tbay-nav-menu" data-id="7c89fdbe" data-element_type="widget" data-widget_type="tbay-nav-menu.default">
							<div class="elementor-widget-container">
								<div class="tbay-element tbay-element-nav-menu">
									<nav class="elementor-nav-menu--main elementor-nav-menu__container elementor-nav-menu--layout-horizontal tbay-horizontal" data-id="gramiyum-menu">
										<?php gram_main_menu(); ?>
									</nav>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="elementor-column elementor-col-50 elementor-top-column elementor-element elementor-element-6fcb3678" data-id="6fcb3678" data-element_type="column">
					<div class="elementor-widget-wrap elementor-element-populated">
						<div class="elementor-element elementor-element-20240616 w-auto elementor-widget elementor-widget-tbay-product-recently-viewed" data-id="20240616" data-element_type="widget" data-widget_type="tbay-product-recently-viewed.default">
							<div class="elementor-widget-container">
								<?php gram_recently_viewed(); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="elementor-section elementor-top-section elementor-element elementor-element-6268f780 elementor-section-full_width elementor-section-stretched elementor-section-height-default elementor-section-height-default wpr-particle-no wpr-jarallax-no wpr-parallax-no wpr-sticky-section-no" data-id="6268f780" data-element_type="section">
			<div class="elementor-container elementor-column-gap-no">
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-169e4d4a" data-id="169e4d4a" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-28b5442a elementor-widget elementor-widget-spacer" data-id="28b5442a" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-3c090e71" data-id="3c090e71" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-7fd09a4c elementor-widget elementor-widget-spacer" data-id="7fd09a4c" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-666f51f4" data-id="666f51f4" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-253901ef elementor-widget elementor-widget-spacer" data-id="253901ef" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-4adf4ebe" data-id="4adf4ebe" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-1e102f29 elementor-widget elementor-widget-spacer" data-id="1e102f29" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-2a950eb5" data-id="2a950eb5" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-2383ce0d elementor-widget elementor-widget-spacer" data-id="2383ce0d" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-13dd4bf3" data-id="13dd4bf3" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-37779ed0 elementor-widget elementor-widget-spacer" data-id="37779ed0" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-2e668f9" data-id="2e668f9" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-3db75bff elementor-widget elementor-widget-spacer" data-id="3db75bff" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
				<div class="elementor-column elementor-col-12 elementor-top-column elementor-element elementor-element-5142547a" data-id="5142547a" data-element_type="column"><div class="elementor-widget-wrap elementor-element-populated"><div class="elementor-element elementor-element-767fddee elementor-widget elementor-widget-spacer" data-id="767fddee" data-element_type="widget" data-widget_type="spacer.default"><div class="elementor-widget-container"><div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div></div></div></div></div>
			</div>
		</section>

	</div>
	<div id="nav-cover"></div>
</header>

<div id="tbay-main-content">
<?php gram_breadcrumb_section();
