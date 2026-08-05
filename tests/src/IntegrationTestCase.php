<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests;

use Inpsyde\Modularity\Properties\LibraryProperties;
use Inpsyde\Modularity\Properties\PluginProperties;
use Inpsyde\Modularity\Properties\ThemeProperties;

class IntegrationTestCase extends TestCase
{
    protected function phpLibraryProperties(): LibraryProperties
    {
        return LibraryProperties::new(__DIR__ . '../fixtures/php-library/composer.json');
    }

    protected function themeProperties(): ThemeProperties
    {
        return ThemeProperties::new(__DIR__ . '../fixtures/theme/');
    }

    protected function wpPluginProperties(): PluginProperties
    {
        return PluginProperties::new(__DIR__ . '../fixtures/wp-plugin/index.php');
    }
}
