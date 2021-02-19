<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

use Inpsyde\Modularity\Tests\TestCase;

class ModuleClassNameIdTraitTest extends TestCase
{

    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = new class implements Module {

            use ModuleClassNameIdTrait;
        };

        static::assertSame(get_class($testee), $testee->id());
    }
}
