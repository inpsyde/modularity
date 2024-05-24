<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Brain\Monkey;
use Inpsyde\Modularity\Container\PackageProxyContainer;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Tests\TestCase;

class PackageProxyContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testAccessingContainerEarlyThrows(): void
    {
        $package = Package::new($this->stubProperties());

        $container = new PackageProxyContainer($package);
        static::assertFalse($container->has('test'));

        $this->expectExceptionMessageMatches('/is not ready yet/i');
        $container->get('test');
    }

    /**
     * @test
     */
    public function testAccessingFailedPackageEarlyThrows(): void
    {
        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow(new \Error());

        $container = new PackageProxyContainer($package->build());
        static::assertFalse($container->has('test'));

        $this->expectExceptionMessageMatches('/is errored/i');
        $container->get('test');
    }
}
