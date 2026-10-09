<?php
/**
 * Mounted into the WordPress container and required from wp-config.php
 * (via WORDPRESS_CONFIG_EXTRA in docker-compose.yml).
 *
 * Edit WP_HOME / WP_SITEURL here when exposing the site through ngrok,
 * then `docker compose restart wordpress`.
 */

define('WP_HOME', 'http://localhost:8081');
define('WP_SITEURL', 'http://localhost:8081');

// Full <script> src for the APTI tracking SDK. Leave empty to load nothing.
define('APTI_SDK_SRC', '');
