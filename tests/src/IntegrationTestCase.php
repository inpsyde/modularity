<?php

declare(strict_types=1);

namespace Syde\Modularity\Tests;

use Syde\Modularity\Properties\LibraryProperties;
use Syde\Modularity\Properties\PluginProperties;
use Syde\Modularity\Properties\ThemeProperties;

class IntegrationTestCase extends \PHPUnit\Framework\TestCase
{
    protected function phpLibraryProperties(): LibraryProperties
    {
        return LibraryProperties::new(__DIR__ . '/../fixtures/php-library/composer.json');
    }

    protected function themeProperties(): ThemeProperties
    {
        return ThemeProperties::new(__DIR__ . '/../fixtures/theme/');
    }

    protected function wpPluginProperties(): PluginProperties
    {
        return PluginProperties::new(__DIR__ . '/../fixtures/wp-plugin/index.php');
    }
}
