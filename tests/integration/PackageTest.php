<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Integration;

use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Tests\IntegrationTestCase;

class PackageTest extends IntegrationTestCase
{
    public function testBootPhpLibrary(): void
    {
        $package = Package::new($this->phpLibraryProperties());
        $package->boot();

        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }

    public function testBootTheme(): void
    {
        $package = Package::new($this->themeProperties());
        $package->boot();
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }

    public function testBootPlugins(): void
    {
        $package = Package::new($this->wpPluginProperties());
        $package->boot();

        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }

    public function testBootOnPluginsLoaded(): void
    {
        $package = Package::new($this->wpPluginProperties());

        add_action('plugins_loaded', static function () use ($package): void {
            $package->boot();
        });

        do_action('plugins_loaded');

        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }
}
