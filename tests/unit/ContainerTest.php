<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\Container;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{

    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = new Container([], [], []);

        static::assertInstanceOf(ContainerInterface::class, $testee);
        static::assertFalse($testee->has('unknown'));
    }

    /**
     * @test
     */
    public function testGetUnknown(): void
    {
        static::expectException(\Exception::class);

        $testee = new Container([], [], []);
        $testee->get('unknown');
    }

    /**
     * @test
     */
    public function testHasGetService(): void
    {
        $expectedServiceKey = 'service';
        $expectedValue = new \stdClass();
        $testee = new Container(
            [
                'service' => static function () use ($expectedValue) {
                    return $expectedValue;
                },
            ],
            [],
            []
        );

        // check in Services
        static::assertTrue($testee->has($expectedServiceKey));
        // resolve Service
        static::assertSame($expectedValue, $testee->get($expectedServiceKey));
        // check in Factories
        static::assertTrue($testee->has($expectedServiceKey));
    }

    /**
     * @test
     */
    public function testHasGetServiceFromChildContainer(): void
    {
        $expectedServiceKey = 'service';
        $expectedValue = new \stdClass();

        $childContainer = new class($expectedServiceKey, $expectedValue) implements ContainerInterface {

            private $data = [];

            public function __construct(string $key, \stdClass $value)
            {
                $this->data[$key] = $value;
            }

            public function get($id)
            {
                return $this->data[$id];
            }

            public function has($id)
            {
                return isset($this->data[$id]);
            }
        };

        $testee = new Container(
            [],
            [],
            [$childContainer]
        );

        // check in child Container
        static::assertTrue($testee->has($expectedServiceKey));
        // resolve Service
        static::assertSame($expectedValue, $testee->get($expectedServiceKey));
        // check in resolved Services
        static::assertTrue($testee->has($expectedServiceKey));
    }

    /**
     * @test
     */
    public function testExtensions(): void
    {
        $expectedServiceKey = 'service';
        $expectedInitialService = new \stdClass();
        $extendedService = new \stdClass();

        $testee = new Container(
            [
                $expectedServiceKey => function () use ($expectedInitialService) {
                    return $expectedInitialService;
                },
            ],
            [
                $expectedServiceKey => [
                    function ($initialService) use ($expectedInitialService, $extendedService) {
                        static::assertSame($expectedInitialService, $initialService);

                        return $extendedService;
                    },
                ],
            ],
            []
        );

        static::assertSame($extendedService, $testee->get($expectedServiceKey));
    }
}
