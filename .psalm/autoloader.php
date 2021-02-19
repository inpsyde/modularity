<?php // phpcs:disable
if (defined('ABSPATH')) {
    return;
}

define('ABSPATH', dirname(__DIR__) . '/vendor/wordpress/wordpress/');
define('WPINC', 'wp-includes');

require_once ABSPATH . WPINC . '/plugin.php';
require_once ABSPATH . WPINC . '/theme.php';
require_once ABSPATH . WPINC . '/class-wp-theme.php';
require_once ABSPATH . WPINC . '/link-template.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
