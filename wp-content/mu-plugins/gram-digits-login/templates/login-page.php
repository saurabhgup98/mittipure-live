<?php
/**
 * Full-page Digits login (gramiyum.in: /?login=true&redirect_to&page=1).
 * Markup reproduced from Digits 9.2.4's output on gramiyum.in, 2026-10-07:
 * login / forgot / register forms, loader overlay, cancel button.
 *
 * @package gram-digits-login
 */

if (!defined('ABSPATH')) {
    exit;
}

$redirect = Gram_Digits::redirect_page();
$nonce    = wp_create_nonce(Gram_Digits::NONCE_ACTION);
$referer  = '/?login=true&#038;redirect_to&#038;page=1';
$iid      = fn () => md5(wp_generate_password(20, false));
$remember = 'digits_login_remember_me' . wp_rand(1000000000, 2147483647);

/** Country code + mobile row, identical in all three forms (name differs). */
$mobile_row = function (string $cc_name, string $phone_name, bool $required, bool $register = false) {
    ?>
<div class="digits-form_input digits-form_countrycode countrycodecontainer digits_countrycodecontainer">
<span class="digits-field-country_flag untdovr_flag_container_flag"></span>
<input type="text" name="<?php echo esc_attr($cc_name); ?>"
class="input-text countrycode digits_countrycode country_code_flag"
value="+91"
country="india"
maxlength="6" size="3"
placeholder="+91"
inputmode="numeric"
autocomplete="tel-country-code"
/>
</div>
<div class="digits-form_input">
<input type="tel"
class="mobile_field mobile_format dig-mobmail dig-mobile_field mobile_placeholder"
name="<?php echo esc_attr($phone_name); ?>"
<?php echo $register ? '' : 'inputmode="numeric"'; ?>
autocomplete="tel-national"
placeholder="Phone Number"
data-placeholder="Phone Number"
style="padding-left: 123px"
<?php echo $register ? 'inputmode="numeric"' : ''; ?>
value="" data-type="2"<?php echo $required ? ' required' : ''; ?>/>
</div>
    <?php
};

/** Phone / email tab block used by the login and forgot forms. */
$phone_email_tabs = function () use ($mobile_row) {
    ?>
<div class="digits-form_tab_wrapper">
<div class="digits-form_tab_container">
<div class="digits-form_tabs">
<div class="digits-form_tab-bar">
<div data-change="action_type" data-value="phone" class="digits-form_tab-item digits_login_use_phone digits-tab_active">Use Phone Number</div>
<div data-change="action_type" data-value="email" class="digits-form_tab-item digits_login_use_email">Use Email Address</div>
</div>
</div>
<div class="digits-form_body">
<div class="digits-form_body_wrapper">
<div class="digits-form_tab_body digits-tab_active">
<div class="digits-form_input_row digits-mobile_wrapper digits-form_border">
<?php $mobile_row('login_digt_countrycode', 'digits_phone', true); ?>
</div>
</div>
<div class="digits-form_tab_body ">
<div class="digits-form_input_row">
<div class="digits-form_input">
<input
name="digits_email"
type="email"
autocomplete="username"
placeholder="Email Address"
required
/>
</div>
</div>
</div>
</div>
<input type="hidden" name="action_type" value="phone"
autocomplete="off"/>
</div>
</div>
</div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html(get_bloginfo('name')); ?> &lsaquo; Log In</title>
<?php wp_head(); ?>
</head>
<body>
<div class="digits_ui " id="digits_protected">
<div class="digits-form_page digits-auto-theme">
<div class="digits-form_container digits">
<div class="digits-form_wrapper digits_modal_box digits2_box">

<form class="digits_form_index_section digloginpage digits_original" method="post" enctype="multipart/form-data"
>
<div class="digits-form_login">
<div class="digits-form_heading">
<span class="digits_back_icon digits_hide_back digits_form_back"></span>
<span class="digits-form_heading_text" data-text="Login">Login</span>
</div>
<?php $phone_email_tabs(); ?>
<div class="digits_form-init_step_data">
<div class="digits-form_input_row digits-form_rememberme" style="display:none;">
<div class="digits-form_input digits-field-type_tac">
<div class="dig_opt_mult_con">
<div class="dig_opt_mult">
<label for="<?php echo esc_attr($remember); ?>" class="checked">
<div class="dig_input_wrapper">
<input data-all="digits_login_remember_me" name="rememberme"
class="not-empty digits_login_remember_me" id="<?php echo esc_attr($remember); ?>" type="checkbox"
value="1" checked>
<div>Remember Me</div>
</div>
</label>
</div>
</div>
</div>
</div>
</div>
<button class="digits-form_button digits-form_submit digits-form_submit-btn" type="submit">
<span class="digits-form_button-text">
Continue                </span>
<span class="digits-form_button_ic"></span>
</button>
<div class="digits-form_login_separator">
<span>or</span>
</div>
<button class="digits-form_button digits-form_submit digits-form_passwordless_login" type="submit">
<span class="digits-form_button-text">
Passwordless login                    </span>
<span class="digits-form_button_ic"></span>
</button>
<div class="digits-form_footer">
</div>
<input type="hidden" name="digits" value="1"/>
<input type="hidden" name="instance_id" value="<?php echo esc_attr($iid()); ?>"
autocomplete="off"/>
<input type="hidden" name="action" value="digits_forms_ajax" class="digits_action_type" autocomplete="off"/>
<input type="hidden" name="type" value="login" class="digits_action_type" autocomplete="off"/>
<input type="hidden" name="digits_step_1_type" value=""
autocomplete="off"/>
<input type="hidden" name="digits_step_1_value" value=""
autocomplete="off"/>
<input type="hidden" name="digits_step_2_type" value=""
autocomplete="off"/>
<input type="hidden" name="digits_step_2_value" value=""
autocomplete="off"/>
<input type="hidden" name="digits_step_3_type" value=""
autocomplete="off"/>
<input type="hidden" name="digits_step_3_value" value=""
autocomplete="off"/>
<input type="hidden" name="digits_login_email_token" value="" class="reset_on_back"/>
<input type="hidden" name="digits_redirect_page"
value="<?php echo esc_attr($redirect); ?>"/>
<input type="hidden" name="digits_form" value="<?php echo esc_attr($nonce); ?>" />
<input type="hidden" name="_wp_http_referer" value="<?php echo $referer; // phpcs:ignore ?>" />
<div class="dig_login_signup_bar digits-title_color digits_show_on_index">
<span>Not a member yet?</span>
<a href="#" class="digits-form_toggle_login_register show_register">
Register Now                    </a>
</div>
<div class="digits-hide">
<div class="digits-form_show_forgot_password digits_reset_pass"></div>
<div class="digits-form_toggle_login_register show_login"></div>
</div>
</div>
</form>

<form class="digits_form_index_section forgot digits_original" method="post" enctype="multipart/form-data"
style="display: none;"    >
<div class="digits-form_forgot_password">
<div class="digits-form_heading">
<span class="digits_back_icon digits_hide_back digits_form_back"></span>
<span class="digits-form_heading_text" data-text="Reset Password">Reset Password</span>
</div>
<?php $phone_email_tabs(); ?>
<button class="digits-form_button digits-form_submit digits-form_submit-btn" type="submit">
<span class="digits-form_button-text">
Continue                </span>
<span class="digits-form_button_ic"></span>
</button>
<div class="digits-form_footer">
</div>
<input type="hidden" name="instance_id" value="<?php echo esc_attr($iid()); ?>"
autocomplete="off"/>
<input type="hidden" name="action" value="digits_forms_ajax" autocomplete="off"/>
<input type="hidden" name="type" value="forgot" autocomplete="off"/>
<input type="hidden" name="forgot_pass_method" autocomplete="off"/>
<input type="hidden" name="forgot_password_value" autocomplete="off"/>
<input type="hidden" name="digits" value="1"/>
<input type="hidden" name="digits_redirect_page"
value="<?php echo esc_attr($redirect); ?>"/>
</div>
<input type="hidden" name="digits_form" value="<?php echo esc_attr($nonce); ?>" />
<input type="hidden" name="_wp_http_referer" value="<?php echo $referer; // phpcs:ignore ?>" />    </form>

<form class="digits_form_index_section register digits_register digits_original digits_hide_label"
method="post"
enctype="multipart/form-data"
style="display: none;"    >
<div class="digits-form_register">
<div class="digits-form_heading">
<span class="digits_back_icon digits_hide_back digits_form_back"></span>
<span class="digits-form_heading_text" data-text="Register">Register</span>
</div>
<div class="digits-form_tab_wrapper">
<div class="digits-form_tab_container digits-form_body-no_tabs">
<div class="digits-form_tabs">
</div>
<div class="digits-form_body">
<div class="digits_signup_form_step digits_signup_active_step">
<div class="digits_phone_holder">
<div class="digits-form_input_row">
<div
id="dig_cs_mobilenumber"
class="digits-mobile_wrapper digits-form_border">
<?php $mobile_row('digt_countrycode', 'phone', false, true); ?>
</div>
</div>
</div>
<div class="digits_email_holder">
<div id="dig_cs_email" class="digits-form_input_row">
<div class="digits-form_input">
<input
name="email"
type="email"
autocomplete="email"
placeholder="Email Address"/>
</div>
</div>
</div>
<div id="dig_cs_name" class="digits-form_input_row digits-user_inp_row">
<div class="digits-form_input">
<label class="field_label">
First Name                                </label>
<input type="text" name="digits_reg_name" id="digits_reg_name"
value=""                                        placeholder="First Name"
autocomplete="name"/>
</div>
</div>
<div id="dig_cs_password"
class="digits-form_input_row digits-user_inp_row digits_password_inp_row">
<div class="digits-form_input">
<label class="field_label">
Password                                </label>
<input type="password"
name="digits_reg_password"
class="new_password"
autocomplete="new-password"
placeholder="Password"
/>
</div>
<div class="digits_password_eye-cont digits_password_eye">
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
<div class="digits_password_eye-open digits_password_eye-line digits_password_eye-default-line"></div>
</div>
</div>
<input type="hidden" name="digits_process_register" value="1" />        </div>
</div>
</div>
</div>
<button class="digits-form_button digits-form_submit digits-form_submit-btn"
data-subaction="signup"
type="submit">
<span class="digits-form_button-text">
Continue                </span>
<span class="digits-form_button_ic"></span>
</button>
<div class="digits-form_footer">
</div>
<input type="hidden" name="instance_id" value="<?php echo esc_attr($iid()); ?>"
autocomplete="off"/>
<input type="hidden" name="optional_data" value="optional_data" autocomplete="off"/>
<input type="hidden" name="action" value="digits_forms_ajax" autocomplete="off"/>
<input type="hidden" name="type" value="register" autocomplete="off"/>
<input type="hidden" name="dig_otp" value=""/>
<input type="hidden" name="digits" value="1"/>
<input type="hidden" name="digits_redirect_page"
value="<?php echo esc_attr($redirect); ?>"/>
<div class="dig_login_signup_bar digits-title_color digits_show_on_index">
<span>Already a member?</span>
<a href="#" class="digits-form_toggle_login_register show_login">
Login Now                    </a>
</div>
<div>
</div>
</div>
<input type="hidden" name="digits_form" value="<?php echo esc_attr($nonce); ?>" />
<input type="hidden" name="_wp_http_referer" value="<?php echo $referer; // phpcs:ignore ?>" />    </form>
</div>
<div class="dig_load_overlay">
<div class="dig_load_content">
<div class="dig_spinner">
<div class="dig_double-bounce1"></div>
<div class="dig_double-bounce2"></div>
</div>
</div>
</div>
<div class="digits_site_footer_box">
</div>
</div>
<div class="digits-cancel dig_login_cancel"
title="Go Back"
data-back="<?php echo esc_attr(preg_replace('#^https?:#', '', home_url('/')) . '?page=1'); ?>"        ></div>
</div>
</div>
<div id="digits_country_list_wrapper"></div>
<?php wp_footer(); ?>
</body>
</html>
