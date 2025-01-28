<?php

declare(strict_types=1);

$testsDir = str_replace('\\', '/', __DIR__);
$libDir = dirname($testsDir);
$vendorDir = "{$libDir}/vendor";
$autoload = "{$vendorDir}/autoload.php";

if (!is_file($autoload)) {
    die('Please install via Composer before running tests.');
}

putenv('TESTS_DIR=' . $testsDir);
putenv('LIB_DIR=' . $libDir);
putenv('VENDOR_DIR=' . $vendorDir);

require_once "{$libDir}/vendor/antecedent/patchwork/Patchwork.php";

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    require_once $autoload;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', "{$vendorDir}/roots/wordpress-no-content/");
}

require_once "{$vendorDir}/roots/wordpress-no-content/wp-includes/class-wp-error.php";

unset($testsDir, $libDir, $vendorDir, $autoload);
