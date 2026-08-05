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

        static::assertFalse($package->hasFailed());
    }
}
