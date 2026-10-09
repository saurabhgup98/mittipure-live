<?php
/**
 * Plugin Name: APTI SDK Tracking
 * Description: Loads the APTI website-tracking SDK on every front-end page.
 *              The script URL comes from APTI_SDK_SRC in wp-config-extra.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', function () {
    if (!defined('APTI_SDK_SRC') || APTI_SDK_SRC === '') {
        return;
    }
    printf('<script async src="%s"></script>' . "\n", esc_url(APTI_SDK_SRC));
});
