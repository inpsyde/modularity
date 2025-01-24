<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Inpsyde\Modularity\Container\ReadOnlyContainer as Container;
use Inpsyde\Modularity\Container\ServiceExtensions;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @phpstan-import-type Service from \Inpsyde\Modularity\Module\ServiceModule
 * @phpstan-import-type ExtendingService from \Inpsyde\Modularity\Module\ExtendingModule
 */
class ReadOnlyContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = $this->factoryContainer();

        static::assertInstanceOf(ContainerInterface::class, $testee);
        static::assertFalse($testee->has('unknown'));
    }

    /**
     * @test
     */
    public function testGetUnknown(): void
    {
        static::expectException(\Exception::class);

        $testee = $this->factoryContainer();
        $testee->get('unknown');
    }

    /**
     * @test
     * @dataProvider provideServices
     *
     * @param mixed $expected
     * @param callable $service
     */
    public function testHasGetService($expected, callable $service): void
    {
        $expectedId = 'service';
        $services = [$expectedId => $service];
        $testee = $this->factoryContainer($services);

        // check in Services
        static::assertTrue($testee->has($expectedId));
        // resolve Service
        static::assertSame($expected, $testee->get($expectedId));
        // check in Factories
        static::assertTrue($testee->has($expectedId));
    }

    /**
     * @return \Generator
     */
    public static function provideServices(): \Generator
    {
        $service = new \stdClass();
        yield 'object service' => [
            $service,
            static function () use ($service): object {
                return $service;
            },
        ];

        $service = 'foo';
        yield 'string service' => [
            $service,
            static function () use ($service): string {
                return $service;
            },
        ];

        $service = ['foo', 'bar'];
        yield 'array service' => [
            $service,
            static function () use ($service): array {
                return $service;
            },
        ];
    }

    /**
     * @test
     */
    public function testHasGetServiceFromChildContainer(): void
    {
        $expectedKey = 'service';
        $expectedValue = new \stdClass();

        $childContainer = new class ($expectedKey, $expectedValue) implements ContainerInterface {
            /** @var array<string, \stdClass> */
            private array $data = [];

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

        $testee = $this->factoryContainer([], [], [$childContainer]);

        // check in child Container
        static::assertTrue($testee->has($expectedKey));
        // resolve Service
        static::assertSame($expectedValue, $testee->get($expectedKey));
        // check in resolved Services
        static::assertTrue($testee->has($expectedKey));
    }

    /**
     * @test
     *
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    public function testFactoriesAndServices(): void
    {
        // phpcs:enable Inpsyde.CodeQuality.NestingLevel
        $expectedServiceKey = 'service';
        $expectedFactoryKey = 'factory';
        $services = [
            $expectedServiceKey => function (): object {
                return new class {
                    protected int $serviceCounter = 0;

                    public function count(): int
                    {
                        $this->serviceCounter++;

                        return $this->serviceCounter;
                    }
                };
            },
            $expectedFactoryKey => function (): object {
                return new class {
                    protected int $factoryCounter = 0;

                    public function count(): int
                    {
                        $this->factoryCounter++;

                        return $this->factoryCounter;
                    }
                };
            },
        ];
        $factoryIds = [$expectedFactoryKey => true];

        $testee = $this->factoryContainer($services, $factoryIds);

        // Services are cached and same instance is returned.
        static::assertSame(1, $testee->get($expectedServiceKey)->count());
        static::assertSame(2, $testee->get($expectedServiceKey)->count());

        // Factories always a new instance is created.
        static::assertSame(1, $testee->get($expectedFactoryKey)->count());
        static::assertSame(1, $testee->get($expectedFactoryKey)->count());
    }

    /**
     * @test
     */
    public function testServiceExtensionsBackwardCompatibility(): void
    {
        $service = static function (): object {
            return (object) ['count' => 0];
        };

        $extension = static function (object $thing): object {
            /** @var object{count:integer}&\stdClass $thing */
            $thing->count++;

            return $thing;
        };

        $container = new Container(['thing' => $service], [], ['thing' => $extension], []);

        $resolved = $container->get('thing');

        static::assertInstanceOf(\stdClass::class, $resolved);
        static::assertSame(1, $resolved->count);
    }

    /**
     * @test
     */
    public function testServiceExtensionsBackwardCompatibilityBreaksOnWrongType(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line  */
        new Container([], [], ServiceExtensions::class, []);
    }

    /**
     * @param array<string, Service> $services
     * @param array<string, bool> $factoryIds
     * @param ContainerInterface[] $containers
     *
     * @return Container
     */
    private function factoryContainer(
        array $services = [],
        array $factoryIds = [],
        array $containers = []
    ): Container
    {
        return new Container($services, $factoryIds, new ServiceExtensions(), $containers);
    }
}
