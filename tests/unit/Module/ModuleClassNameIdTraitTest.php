<?php

declare(strict_types=1);

namespace Syde\Modularity\Tests\Unit\Module;

use Syde\Modularity;
use Syde\Modularity\Tests\TestCase;

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
