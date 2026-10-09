<?php
/**
 * Digits look-alike: routing, page rendering and the digits_forms_ajax endpoint.
 *
 * Request contract (same field names as Digits' forms on gramiyum.in):
 *   POST /wp-admin/admin-ajax.php  action=digits_forms_ajax  type=login|register|forgot
 *   login step 1:  action_type=phone, login_digt_countrycode, digits_phone   (or action_type=email, digits_email)
 *   login step 2:  digits_step_1_type/value (+ digits_step_2_type=sms_otp, sms_otp)  or password
 *   register:      digt_countrycode, phone, email, digits_reg_name, digits_reg_password, then sms_otp
 *
 * Responses (wp_send_json_* shape Digits' script.min.js reads):
 *   next step:  {success:true,  data:{html, fill:{field:value}}}
 *   finished:   {success:true,  data:{process:true, process_type, redirect, message, login_reg_success_msg:1}}
 *   error:      {success:false, data:{message, notice?}}
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Gram_Digits
{
    const OTP          = '123456';
    const NONCE_ACTION = 'gram_digits_form';
    const VER          = '1.0.1';

    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'route'], 1);
        add_action('wp_ajax_nopriv_digits_forms_ajax', [self::class, 'ajax']);
        add_action('wp_ajax_digits_forms_ajax', [self::class, 'ajax']);
    }

    /** gramiyum.in's login URL: https://gramiyum.in?login=true&redirect_to&page=1 */
    public static function login_url(): string
    {
        return home_url() . '?login=true&redirect_to&page=1';
    }

    /** Where Digits sends the user after login (value of the digits_redirect_page field). */
    public static function redirect_page(): string
    {
        return preg_replace('#^https?:#', '', home_url('/')) . '?redirect_to&page=1';
    }

    public static function route(): void
    {
        $is_login_page = isset($_GET['login']) && $_GET['login'] === 'true';

        if (!$is_login_page) {
            // Logged-out /my-account/ goes to the Digits page (302), as on gramiyum.in.
            if (!is_user_logged_in() && function_exists('is_account_page') && is_account_page() && !is_wc_endpoint_url('lost-password')) {
                wp_redirect(self::login_url(), 302);
                exit;
            }
            return;
        }

        if (is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        add_action('wp_enqueue_scripts', function () {
            // The Digits page is a bare document: drop the theme's styles.
            foreach (['storefront-style', 'storefront-icons', 'storefront-gutenberg-blocks', 'storefront-woocommerce-style', 'storefront-child-style', 'gram-header', 'gram-shop', 'gram-fonts'] as $handle) {
                wp_dequeue_style($handle);
            }
            $base = content_url('mu-plugins/gram-digits-login/assets/');
            wp_enqueue_style('gram-digits', $base . 'digits.css', [], self::VER);
            wp_enqueue_script('gram-digits', $base . 'digits.js', ['jquery'], self::VER, true);
            wp_localize_script('gram-digits', 'dig_script', [
                'direction'                => 'ltr',
                'ajax_url'                 => admin_url('admin-ajax.php'),
                'ErrorPleasetryagainlater' => 'Error! Please try again later',
                'fillAllDetails'           => 'Please fill all the required details.',
                'InvalidMobileNumber'      => 'Invalid Mobile Number!',
                'InvalidOTP'               => 'Invalid OTP!',
                'home'                     => home_url(),
            ]);
            wp_localize_script('gram-digits', 'dig_log_obj', [
                'ajax_url'      => admin_url('admin-ajax.php'),
                'login_success' => 'Login Successful, Redirecting..',
                'ohsnap'        => 'Oh Snap!',
                'yay'           => 'Yay!',
                'notice'        => 'Notice!',
                'resendOtpTime' => '30',
                'home'          => home_url(),
            ]);
        }, 100);

        // WP reads page=1 on the home URL as a missing page; this is our page, not a 404.
        remove_action('wp_head', '_wp_render_title_tag', 1);
        status_header(200);
        nocache_headers();
        include __DIR__ . '/templates/login-page.php';
        exit;
    }

    /* ---------------------------------------------------------------- ajax */

    public static function ajax(): void
    {
        if (!isset($_POST['digits_form']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['digits_form'])), self::NONCE_ACTION)) {
            self::fail('Error! Please try again later');
        }
        $sub_action = self::post('sub_action');
        if ($sub_action === 'start_passwordless_login') {
            self::fail('No passkey is registered for this device. Please continue with your phone number.', true);
        }

        switch (self::post('type')) {
            case 'login':
            case 'forgot':
                self::handle_login();
                break;
            case 'register':
                self::handle_register();
                break;
            default:
                self::fail('Invalid details!');
        }
    }

    private static function handle_login(): void
    {
        // The forgot form carries its step in forgot_pass_method/forgot_password_value
        // (as on gramiyum.in); the login form uses digits_step_1_type/value.
        $forgot     = self::post('type') === 'forgot';
        $type_key   = $forgot ? 'forgot_pass_method' : 'digits_step_1_type';
        $value_key  = $forgot ? 'forgot_password_value' : 'digits_step_1_value';
        $step1_type = self::post($type_key);

        // ---- step 1: identify the account
        if ($step1_type === '') {
            if (self::post('action_type') === 'email') {
                $email = sanitize_email(self::post('digits_email'));
                if (!is_email($email)) {
                    self::fail('Invalid Email!');
                }
                if (!get_user_by('email', $email)) {
                    self::fail('Please signup before logging in.');
                }
                self::next_step($forgot ? self::otp_step_html(null, $email) : self::password_step_html(), [$type_key => 'email', $value_key => $email]);
            }

            $phone = self::normalize_phone(self::post('login_digt_countrycode'), self::post('digits_phone'));
            if (!$phone) {
                self::fail('Invalid Mobile Number!');
            }
            if (!self::user_by_phone($phone['full'])) {
                self::fail('Please signup before logging in.');
            }
            self::next_step(self::otp_step_html($phone), [$type_key => 'phone', $value_key => $phone['full']]);
        }

        // ---- step 2: verify OTP / password and log in
        $value = self::post($value_key);
        $user  = $step1_type === 'email' ? get_user_by('email', sanitize_email($value)) : self::user_by_phone($value);
        if (!$user) {
            self::fail('Invalid login credentials!');
        }
        if ($step1_type === 'email' && !$forgot) {
            if (!wp_check_password(self::post('password', false), $user->user_pass, $user->ID)) {
                self::fail('Invalid Password');
            }
        } elseif (self::post('sms_otp') !== self::OTP) {
            self::fail('Invalid OTP!');
        }

        self::log_in($user->ID, 'login');
    }

    private static function handle_register(): void
    {
        $phone = self::normalize_phone(self::post('digt_countrycode'), self::post('phone'));
        $email = sanitize_email(self::post('email'));
        $name  = sanitize_text_field(self::post('digits_reg_name'));

        if (!$phone) {
            self::fail('Invalid Mobile Number!');
        }
        if (self::user_by_phone($phone['full'])) {
            self::fail('Mobile Number already in use!');
        }
        if ($email !== '' && (!is_email($email) || email_exists($email))) {
            self::fail(email_exists($email) ? 'Email already in use!' : 'Invalid Email!');
        }
        if ($name === '') {
            self::fail('Please fill all the required details.');
        }

        // Step 1 (no OTP yet): send the OTP step.
        if (self::post('sms_otp') === '' && self::post('dig_otp') === '') {
            self::next_step(self::otp_step_html($phone), []);
        }
        $otp = self::post('sms_otp') !== '' ? self::post('sms_otp') : self::post('dig_otp');
        if ($otp !== self::OTP) {
            self::fail('Invalid OTP!');
        }

        $password = self::post('digits_reg_password', false);
        $user_id  = wp_insert_user([
            'user_login'   => self::unique_login($name, $phone['number']),
            'user_pass'    => $password !== '' ? $password : wp_generate_password(20),
            'user_email'   => $email,
            'first_name'   => $name,
            'display_name' => $name,
            'role'         => 'customer',
        ]);
        if (is_wp_error($user_id)) {
            self::fail($user_id->get_error_message());
        }
        // Digits' own user meta keys, plus WooCommerce billing details.
        update_user_meta($user_id, 'digits_phone', $phone['full']);
        update_user_meta($user_id, 'digits_phone_no', $phone['number']);
        update_user_meta($user_id, 'digt_countrycode', $phone['code']);
        update_user_meta($user_id, 'billing_phone', $phone['full']);
        update_user_meta($user_id, 'billing_first_name', $name);
        if ($email !== '') {
            update_user_meta($user_id, 'billing_email', $email);
        }

        self::log_in($user_id, 'register');
    }

    /* ------------------------------------------------------------- helpers */

    private static function log_in(int $user_id, string $process_type): void
    {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, self::post('rememberme') === '1');
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));

        $redirect = self::post('digits_redirect_page');
        wp_send_json_success([
            'process'               => true,
            'process_type'          => $process_type,
            'redirect'              => $redirect !== '' ? $redirect : self::redirect_page(),
            'message'               => $process_type === 'register' ? 'Registration Successful, Redirecting..' : 'Login Successful, Redirecting..',
            'login_reg_success_msg' => 1,
        ]);
    }

    private static function next_step(string $html, array $fill): void
    {
        wp_send_json_success(['html' => $html, 'fill' => $fill]);
    }

    private static function fail(string $message, bool $notice = false): void
    {
        wp_send_json_error(['message' => $message, 'notice' => $notice]);
    }

    private static function post(string $key, bool $sanitize = true): string
    {
        if (!isset($_POST[$key])) {
            return '';
        }
        $value = wp_unslash($_POST[$key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return is_string($value) ? ($sanitize ? trim(sanitize_text_field($value)) : $value) : '';
    }

    /** @return array{code:string,number:string,full:string}|null */
    private static function normalize_phone(string $code, string $number): ?array
    {
        $code   = '+' . preg_replace('/\D/', '', $code !== '' ? $code : '+91');
        $number = preg_replace('/\D/', '', $number);
        if ($code === '+91' && strlen($number) === 12 && strpos($number, '91') === 0) {
            $number = substr($number, 2);
        }
        if (strlen($number) < 6 || strlen($number) > 12) {
            return null;
        }
        return ['code' => $code, 'number' => $number, 'full' => $code . $number];
    }

    private static function user_by_phone(string $full): ?WP_User
    {
        $users = get_users(['meta_key' => 'digits_phone', 'meta_value' => $full, 'number' => 1]);
        return $users ? $users[0] : null;
    }

    private static function unique_login(string $name, string $number): string
    {
        $base  = sanitize_user(strtolower(preg_replace('/\s+/', '', $name)), true) ?: 'user' . $number;
        $login = $base;
        for ($i = 1; username_exists($login); $i++) {
            $login = $base . $i;
        }
        return $login;
    }

    /** OTP step, sent to a phone (or, for password reset by email, to an email address). */
    private static function otp_step_html(?array $phone, string $email = ''): string
    {
        $masked = $phone ? $phone['code'] . ' ' . $phone['number'] : $email;
        ob_start();
        include __DIR__ . '/templates/step-otp.php';
        return ob_get_clean();
    }

    private static function password_step_html(): string
    {
        ob_start();
        include __DIR__ . '/templates/step-password.php';
        return ob_get_clean();
    }
}
