<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Inpsyde\Modularity\Container\ReadOnlyContainer as Container;
use Inpsyde\Modularity\Container\ServiceExtensions;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ReadOnlyContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = $this->createContainer();

        static::assertInstanceOf(ContainerInterface::class, $testee);
        static::assertFalse($testee->has('unknown'));
    }

    /**
     * @test
     */
    public function testGetUnknown(): void
    {
        static::expectException(\Exception::class);

        $testee = $this->createContainer();
        $testee->get('unknown');
    }

    /**
     * @test
     *
     * @dataProvider provideServices
     */
    public function testHasGetService($expected, callable $service): void
    {
        $expectedId = 'service';
        $services = [$expectedId => $service];
        $testee = $this->createContainer($services);

        // check in Services
        static::assertTrue($testee->has($expectedId));
        // resolve Service
        static::assertSame($expected, $testee->get($expectedId));
        // check in Factories
        static::assertTrue($testee->has($expectedId));
    }

    public function provideServices(): \Generator
    {
        $service = new \stdClass();
        yield 'object service' => [
            $service,
            function () use ($service) {
                return $service;
            },
        ];

        $service = 'foo';
        yield 'string service' => [
            $service,
            function () use ($service) {
                return $service;
            },
        ];

        $service = ['foo', 'bar'];
        yield 'array service' => [
            $service,
            function () use ($service) {
                return $service;
            },
        ];
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

            public function get(string $id)
            {
                return $this->data[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->data[$id]);
            }
        };

        $testee = $this->createContainer([], [], [$childContainer]);

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
    public function testFactoriesAndServices(): void
    {
        $expectedServiceKey = 'service';
        $expectedFactoryKey = 'factory';

        $services = [
            $expectedServiceKey => function () {
                return new class {
                    protected $counter = 0;

                    public function count(): int
                    {
                        $this->counter++;

                        return $this->counter;
                    }
                };
            },
            $expectedFactoryKey => function () {
                return new class {
                    protected $counter = 0;

                    public function count(): int
                    {
                        $this->counter++;

                        return $this->counter;
                    }
                };
            },
        ];
        $factoryIds = [$expectedFactoryKey => true];

        $testee = $this->createContainer($services, $factoryIds);

        // Services are cached and same instance is returned.
        static::assertSame(1, $testee->get($expectedServiceKey)->count());
        static::assertSame(2, $testee->get($expectedServiceKey)->count());

        // Factories always a new instance is created.
        static::assertSame(1, $testee->get($expectedFactoryKey)->count());
        static::assertSame(1, $testee->get($expectedFactoryKey)->count());
    }

    /**
     * @param array $services
     * @param array $factoryIds
     * @param array $containers
     *
     * @return Container
     */
    private function createContainer(
        array $services = [],
        array $factoryIds = [],
        array $containers = []
    ): Container {

        return new Container($services, $factoryIds, new ServiceExtensions(), $containers);
    }
}
