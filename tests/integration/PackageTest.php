<?php

declare(strict_types=1);

namespace Syde\Modularity\Tests\Integration;

use Syde\Modularity\Package;
use Syde\Modularity\Tests\IntegrationTestCase;

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

    public function testBuildAndBootActionsTriggered(): void
    {
        $package = Package::new($this->wpPluginProperties());

        $package->build();
        static::assertTrue(did_action($package->hookName(Package::ACTION_INIT)));
        static::assertTrue(did_action($package->hookName(Package::ACTION_MODULARITY_INIT)));
        static::assertTrue(did_action($package->hookName(Package::ACTION_INITIALIZED)));

        $package->boot();
        static::assertTrue(did_action($package->hookName(Package::ACTION_BOOTED)));
    }
}
