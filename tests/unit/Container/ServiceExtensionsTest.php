<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Inpsyde\Modularity\Container\ServiceExtensions;
use Inpsyde\Modularity\Tests\TestCase;

class ServiceExtensionsTest extends TestCase
{
    /**
     * @test
     */
    public function testBasicFunctionality(): void
    {
        $serviceExtensions = new ServiceExtensions();
        $expected = 0;

        $serviceExtensions->add(
            'thing',
            static function (object $thing) use (&$expected): object {
                $thing->count++;
                $expected++;

                return $thing;
            }
        );

        $serviceExtensions->add(
            'nothing',
            static function (object $thing): object {
                $thing->count++;

                return $thing;
            }
        );

        $serviceExtensions->add(
            'thing',
            static function (object $thing) use (&$expected): object {
                $thing->count++;
                $expected++;

                return $thing;
            }
        );

        $serviceExtensions->add(
            ServiceExtensions::typeId(\stdClass::class),
            static function (object $thing) use (&$expected): object {
                $thing->count++;
                $expected++;

                return $thing;
            }
        );

        $serviceExtensions->add(
            ServiceExtensions::typeId(\ArrayObject::class),
            static function (object $thing): object {
                $thing->count++;

                return $thing;
            }
        );

        $container = $this->stubContainer();
        $thing = $serviceExtensions->resolve((object) ['count' => 0], 'thing', $container);

        static::assertTrue($serviceExtensions->has('thing'));
        static::assertTrue($serviceExtensions->has(ServiceExtensions::typeId(\stdClass::class)));
        static::assertSame($expected, $thing->count);
    }
}
