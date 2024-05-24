<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Module;

use Inpsyde\Modularity;
use Inpsyde\Modularity\Tests\TestCase;

class ModuleClassNameIdTraitTest extends TestCase
{
    /**
     * @test
     */
    public function testIdMatchesClassName(): void
    {
        $module = new class implements Modularity\Module\Module
        {
            use Modularity\Module\ModuleClassNameIdTrait;
        };

        static::assertSame(get_class($module), $module->id());
    }
}
