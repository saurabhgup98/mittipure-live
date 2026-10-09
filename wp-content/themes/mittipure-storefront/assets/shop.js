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
