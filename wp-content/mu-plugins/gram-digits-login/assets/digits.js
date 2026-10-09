/*
 * Front-end for the Digits look-alike page. Mirrors the behaviour of Digits'
 * script.min.js on gramiyum.in: the whole form is POSTed (jQuery serializeArray)
 * to admin-ajax.php, the server answers with the next step's HTML or a
 * "process" result, and messages use Digits' .dig_popmessage markup.
 */
jQuery(function ($) {
	'use strict';

	var busy = false;

	/* Digits (dig_mobile_no_placeholder=1) shows an example national number for the country. */
	$('.mobile_placeholder').attr('placeholder', '81234 56789');
	var $overlay = $('.dig_load_overlay');

	/* ---------- Digits toast (same markup as showDigMessage) ---------- */

	function showDigMessage(message, level) {
		if (!message) return;
		$('.dig_error_message').remove();
		$('body').append(
			"<div class='dig_popmessage dig_popmessage_right dig_error_message'><div class='dig_popmessage_contents'>" +
			"<div class='dig_firele'><div class='dig_pop_bg'></div><div class='dig_pop_bg_over'></div></div>" +
			"<div class='dig_lasele'><div class='dig_lase_snap'></div><div class='dig_lase_message'>" + $('<div>').text(message).html() + "</div></div>" +
			"<div class='dig_popdismiss'></div></div></div>"
		);
		var cls = level === 1 ? 'dig_success_msg' : level === 2 ? 'dig_notice_msg' : 'dig_critical_msg';
		var title = level === 1 ? dig_log_obj.yay : level === 2 ? dig_log_obj.notice : dig_log_obj.ohsnap;
		$('.dig_popmessage').show().addClass(cls + ' dig_popBounceInRight  digits_page_visible').find('.dig_lase_snap').text(title);
	}
	window.showDigMessage = showDigMessage;
	window.showDigErrorMessage = function (m) { showDigMessage(m, 3); };
	window.showDigSuccessMessage = function (m) { showDigMessage(m, 1); };
	window.showDigNoticeMessage = function (m) { showDigMessage(m, 2); };
	window.hideDigMessage = function () { $('.dig_popmessage').fadeOut('fast', function () { $(this).remove(); }); };

	$(document).on('click', '.dig_popdismiss', window.hideDigMessage);

	/* ---------- redirect (Digits strips its own query params first) ---------- */

	function digitsRedirect(url) {
		if (!/^https?:\/\//i.test(url)) url = window.location.protocol + url;
		var u = new URL(url);
		['method', 'auth_key', 'auth_token', 'login', 'type', 'wait'].forEach(function (k) { u.searchParams.delete(k); });
		var qs = u.searchParams.toString();
		window.location.href = u.origin + u.pathname + (qs ? '?' + qs : '');
	}

	/* ---------- tabs: phone / email ---------- */

	$(document).on('click', '.digits-form_tab-item', function (e) {
		e.preventDefault();
		var $tab = $(this);
		var $container = $tab.closest('.digits-form_tab_container');
		var index = $tab.index();
		$tab.addClass('digits-tab_active').siblings().removeClass('digits-tab_active');
		$container.find('.digits-form_tab_body').removeClass('digits-tab_active').eq(index).addClass('digits-tab_active');
		var field = $tab.data('change');
		if (field) $tab.closest('form').find('[name="' + field + '"]').val($tab.data('value'));
		hideDigMessage();
	});

	/* ---------- login <-> register ---------- */

	$(document).on('click', '.digits-form_toggle_login_register', function (e) {
		e.preventDefault();
		var $wrapper = $(this).closest('.digits-form_wrapper');
		var $login = $wrapper.find('.digloginpage'), $register = $wrapper.find('.register'), $forgot = $wrapper.find('.forgot');
		$forgot.hide();
		if ($(this).hasClass('show_register')) { $login.hide(); $register.show(); } else { $register.hide(); $login.show(); }
		hideDigMessage();
	});

	$(document).on('click', '.digits-form_show_forgot_password', function (e) {
		e.preventDefault();
		var $wrapper = $(this).closest('.digits-form_wrapper');
		$wrapper.find('.digloginpage, .register').hide();
		$wrapper.find('.forgot').show();
	});

	$(document).on('click', '.dig_login_cancel', function () {
		digitsRedirect($(this).data('back'));
	});

	/* ---------- password eye ---------- */

	$(document).on('click', '.digits_password_eye', function () {
		var $input = $(this).closest('.digits_password_inp_row').find('input');
		var show = $input.attr('type') === 'password';
		$input.attr('type', show ? 'text' : 'password');
		$(this).toggleClass('eye-closed', !show);
	});

	/* OTP field -> step type, like Digits' .digits_otp_input-field change handler. */
	$(document).on('change input', '.digits_otp_input-field', function () {
		var field = $(this).closest('.digits-form_tab_body').data('change');
		if (field) $(this).closest('form').find('[name="' + field + '"]').val($(this).attr('name'));
	});

	/* ---------- resend timer ---------- */

	function startResendTimer($step) {
		var $resend = $step.find('.digits-form_resend_otp');
		var left = parseInt(dig_log_obj.resendOtpTime, 10) || 30;
		var $t = $resend.find('.resend_timer');
		$resend.addClass('digits_resend_disabled');
		var fmt = function (s) { return '(00:' + (s < 10 ? '0' + s : s) + ')'; };
		$t.show().text(fmt(left));
		var timer = setInterval(function () {
			left -= 1;
			if (left <= 0) {
				clearInterval(timer);
				$t.hide();
				$resend.removeClass('digits_resend_disabled');
				return;
			}
			$t.text(fmt(left));
		}, 1000);
	}

	$(document).on('click', '.digits-form_resend_otp', function (e) {
		e.preventDefault();
		if ($(this).hasClass('digits_resend_disabled')) return;
		startResendTimer($(this).closest('.digits_secure_login_auth_wrapper'));
		showDigMessage('OTP sent again (test site: use 123456)', 1);
	});

	/* ---------- back from a step ---------- */

	function resetSteps($form) {
		$form.find('.digits_secure_login_auth_wrapper').remove();
		$form.find('.digits-form_tab_wrapper > .digits-form_tab_container, .digits-form_tab_wrapper > .digits-form_tab_container ~ input').show();
		$form.find('.digits-form_tab_wrapper').children().show();
		$form.find('.digits_form-init_step_data, .digits-form_login_separator, .digits-form_passwordless_login, .dig_login_signup_bar').show();
		$form.find('.digits_form-init_step_data .digits-form_rememberme').hide();
		$form.find('[name^="digits_step_"], [name="forgot_pass_method"], [name="forgot_password_value"], [name="dig_otp"]').val('');
		$form.find('.digits_form_back').addClass('digits_hide_back');
		$form.addClass('digits_form_index_section');
	}

	$(document).on('click', '.digits_form_back', function () {
		resetSteps($(this).closest('form'));
	});

	/* ---------- submit ---------- */

	function requiredMissing($form) {
		var missing = false;
		$form.find('input,select,textarea').each(function () {
			var $i = $(this);
			if ($i.is(':hidden') || !$i.attr('required')) return;
			if ($.trim($i.val()) === '') {
				missing = true;
				$i.closest('.digits-form_input_row').addClass('input-error');
			} else {
				$i.closest('.digits-form_input_row').removeClass('input-error');
			}
		});
		return missing;
	}

	$(document).on('click', '.digits-form_submit', function (e) {
		e.preventDefault();
		if (busy) return;
		var $btn = $(this);
		var $form = $btn.closest('form');
		var data = $form.serializeArray();

		if ($btn.hasClass('digits-form_passwordless_login')) {
			data.push({ name: 'sub_action', value: 'start_passwordless_login' });
		} else if (requiredMissing($form)) {
			showDigMessage(dig_script.fillAllDetails, 3);
			return;
		}
		data.push({ name: 'container', value: 'digits_protected' });

		busy = true;
		$overlay.fadeIn('fast');
		$.ajax({ type: 'post', url: dig_script.ajax_url, data: data })
			.done(function (res) {
				var r = res && res.data ? res.data : {};
				if (res && res.success && r.html) {
					showStep($form, r);
				} else if (res && res.success && r.process) {
					if (r.login_reg_success_msg == 1 && r.message) showDigMessage(r.message, 1);
					setTimeout(function () { digitsRedirect(r.redirect); }, 500);
					return; // keep overlay while redirecting
				} else {
					showDigMessage(r.message || dig_script.ErrorPleasetryagainlater, r.notice ? 2 : 3);
				}
				$overlay.fadeOut('fast');
			})
			.fail(function () {
				$overlay.fadeOut('fast');
				showDigMessage(dig_script.ErrorPleasetryagainlater, 3);
			})
			.always(function () { busy = false; });
	});

	function showStep($form, r) {
		$.each(r.fill || {}, function (name, value) { $form.find('[name="' + name + '"]').val(value); });
		var $wrapper = $form.find('.digits-form_tab_wrapper').first();
		$form.find('.digits_secure_login_auth_wrapper').remove();
		$wrapper.children().hide();
		var $step = $(r.html);
		$wrapper.append($step);
		$form.find('.digits_form-init_step_data, .digits-form_login_separator, .digits-form_passwordless_login, .dig_login_signup_bar').hide();
		$form.find('.digits_form_back').removeClass('digits_hide_back');
		$form.removeClass('digits_form_index_section');
		if ($step.find('.digits-form_resend_otp').length) startResendTimer($step);
		$step.find('input:visible').first().trigger('focus');
	}
});
