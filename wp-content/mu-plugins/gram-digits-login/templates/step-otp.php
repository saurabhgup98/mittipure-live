<?php
/**
 * OTP step returned in data.html. Digits renders this server-side too; its
 * exact markup isn't observable without receiving a real SMS, so it is built
 * from the selectors Digits' script.min.js uses: .digits-form_tab_body[data-change],
 * .otp_input / .digits_otp_input-field (name = step type), .digits-form_resend_otp,
 * .resend_timer, .digits-form_input_info.
 *
 * @var string $masked phone/email the code was "sent" to
 * @package gram-digits-login
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="digits_secure_login_auth_wrapper digits-form_tab_container" data-change="digits_step_2_type">
<div class="main-section-title digits-hide">Verify OTP</div>
<div class="digits-form_tabs">
<div class="digits-form_tab-bar">
<div data-change="digits_step_2_type" data-value="sms_otp" class="digits-form_tab-item digits-tab_active">SMS</div>
</div>
</div>
<div class="digits-form_body">
<div class="digits-form_body_wrapper">
<div class="digits-form_tab_body digits-tab_active" data-change="digits_step_2_type">
<div class="digits-form_input_row">
<div class="digits-form_input">
<input type="text" name="sms_otp" class="otp_input digits_otp_input-field" autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="Enter OTP" required/>
</div>
</div>
<div class="digits-form_input_info">Enter the 6 digit code sent to <?php echo esc_html($masked); ?></div>
<div class="digits-form_resend_otp digits_resend_disabled" data-id="sms_otp" data-type="sms_otp">Resend OTP <span class="resend_timer">(00:30)</span></div>
</div>
</div>
</div>
</div>
