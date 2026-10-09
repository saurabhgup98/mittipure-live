<?php
/**
 * Password step for "Use Email Address" login, returned in data.html.
 * Built from Digits' selectors (.digits_password_inp_row, .digits_password_eye).
 *
 * @package gram-digits-login
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="digits_secure_login_auth_wrapper digits-form_tab_container" data-change="digits_step_2_type">
<div class="main-section-title digits-hide">Enter Password</div>
<div class="digits-form_body">
<div class="digits-form_body_wrapper">
<div class="digits-form_tab_body digits-tab_active" data-change="digits_step_2_type">
<div class="digits-form_input_row digits_password_inp_row">
<div class="digits-form_input">
<input type="password" name="password" class="digits_otp_input-field" autocomplete="current-password" placeholder="Password" required/>
</div>
<div class="digits_password_eye-cont digits_password_eye">
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<div class="digits_password_eye-open digits_password_eye-line digits_password_eye-default-line"></div>
</div>
</div>
</div>
</div>
</div>
</div>
