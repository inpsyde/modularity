<?php

// phpcs:disable

declare(strict_types=1);

$vendor = dirname(__DIR__).'/vendor';

if (! realpath($vendor)) {
    die('Please install via Composer before running tests.');
}

putenv('TESTS_DIR='.__DIR__);
putenv('LIB_DIR='.dirname(__DIR__));

// Define WordPress ABSPATH for usage in IntegrationTests
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__.'/../vendor/wordpress/wordpress/');
}

require_once "{$vendor}/autoload.php";
unset($vendor);
