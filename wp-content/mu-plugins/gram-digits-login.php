<?php
/**
 * Plugin Name: MittiPure Digits Look-alike Login
 * Description: Mobile-OTP login/registration that reproduces the Digits plugin
 *              (v9.2.4) as rendered on gramiyum.in — same URL
 *              (/?login=true&redirect_to&page=1), form markup, field names and
 *              admin-ajax request/response shapes. Fixed OTP: 123456. No SMS is sent.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/gram-digits-login/class-gram-digits.php';

Gram_Digits::init();
