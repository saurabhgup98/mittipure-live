/* Minus/plus buttons around WooCommerce quantity inputs (GreenMart behaviour). */
jQuery(function ($) {
	$(document.body).on('click', '.box-quantity .minus, .box-quantity .plus', function () {
		var $qty = $(this).siblings('input.qty');
		var val = parseFloat($qty.val()) || 0;
		var step = parseFloat($qty.attr('step')) || 1;
		var min = parseFloat($qty.attr('min')) || 0;
		var max = parseFloat($qty.attr('max'));
		val = $(this).hasClass('plus') ? val + step : val - step;
		if (val < min) val = min;
		if (!isNaN(max) && max > 0 && val > max) val = max;
		$qty.val(val).trigger('change');
		// Loop cards: keep the ajax add-to-cart button's quantity in sync.
		$(this).closest('.groups-button').find('.add_to_cart_button').attr('data-quantity', val);
	});
	$(document.body).on('change', '.products-grid .groups-button input.qty', function () {
		$(this).closest('.groups-button').find('.add_to_cart_button').attr('data-quantity', $(this).val());
	});
});

/* Mega-menu: fill the placeholder on first hover, as GreenMart's AJAX loader does. */
jQuery(function ($) {
	$(document).on('mouseenter focusin', '#menu-item-345197', function () {
		var $ph = $(this).find('.dropdown-html-placeholder');
		var tpl = document.getElementById('gram-megamenu-' + $ph.data('id'));
		if ($ph.length && tpl) {
			$ph.replaceWith(tpl.innerHTML);
		}
	});
});

/*
 * Digits' Place-order hook (#place_order onclick="verifyOTPbilling(10)").
 * With gramiyum.in's settings (no checkout OTP) Digits just submits the
 * checkout form, so WooCommerce's normal ajax checkout runs.
 */
function verifyOTPbilling(step) {
	if (step === 10) jQuery('form.checkout').trigger('submit');
}

/* Phone/tablet menu: "Menu" button, and an arrow next to Categories that opens its list. */
jQuery(function ($) {
	var $header = $('#tbay-header');
	var $toggle = $header.find('.gram-nav-toggle');
	var $cats = $('#menu-item-345197');

	function fillMega() {
		var $ph = $cats.find('.dropdown-html-placeholder');
		var tpl = document.getElementById('gram-megamenu-' + $ph.data('id'));
		if ($ph.length && tpl) $ph.replaceWith(tpl.innerHTML);
	}

	$cats.children('a').after('<button type="button" class="gram-sub-toggle" aria-expanded="false" aria-label="Show categories"></button>');

	$toggle.on('click', function () {
		var open = !$header.hasClass('gram-nav-open');
		$header.toggleClass('gram-nav-open', open);
		$toggle.attr('aria-expanded', open ? 'true' : 'false');
	});

	$cats.on('click', '.gram-sub-toggle', function () {
		fillMega();
		var open = !$cats.hasClass('gram-sub-open');
		$cats.toggleClass('gram-sub-open', open);
		$(this).attr('aria-expanded', open ? 'true' : 'false');
	});

	// Close the menu once a link is tapped (the page is navigating away) and on Escape.
	$header.on('click', '#gram-main-nav a', function () {
		$header.removeClass('gram-nav-open');
		$toggle.attr('aria-expanded', 'false');
	});
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $header.hasClass('gram-nav-open')) {
			$header.removeClass('gram-nav-open');
			$toggle.attr('aria-expanded', 'false').trigger('focus');
		}
	});
});
