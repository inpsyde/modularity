<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Inpsyde\Modularity\Container\ContainerConfigurator;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ContainerConfiguratorTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = new ContainerConfigurator();

        static::assertInstanceOf(ContainerConfigurator::class, $testee);
        static::assertFalse($testee->hasService('something'));
        static::assertFalse($testee->hasExtension('something'));
        static::assertInstanceOf(ContainerInterface::class, $testee->createReadOnlyContainer());
    }

    /**
     * @test
     */
    public function testAddHasService(): void
    {
        $expectedKey = 'key';
        $expectedValue = new class {
        };

        $testee = new ContainerConfigurator();

        static::assertFalse($testee->hasService($expectedKey));

        $testee->addService(
            $expectedKey,
            /** @return mixed */
            static function () use ($expectedValue) {
                return $expectedValue;
            }
        );

        static::assertTrue($testee->hasService($expectedKey));
    }

    /**
     * @test
     */
    public function testAddHasFactory(): void
    {
        $expectedKey = 'key';
        $expectedValue = new class {
        };

        $testee = new ContainerConfigurator();

        static::assertFalse($testee->hasService($expectedKey));

        $testee->addFactory(
            $expectedKey,
            /** @return mixed */
            static function () use ($expectedValue) {
                return $expectedValue;
            }
        );

        static::assertTrue($testee->hasService($expectedKey));
    }

    /**
     * @test
     */
    public function testServiceOverride(): void
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addService(
            $expectedKey,
            static function (): \DateTime {
                return new \DateTime();
            }
        );
        $testee->addService(
            $expectedKey,
            static function (): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
        );
        $container = $testee->createReadOnlyContainer();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    /**
     * @test
     */
    public function testFactoryOverride(): void
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addFactory(
            $expectedKey,
            static function (): \DateTime {
                return new \DateTime();
            }
        );
        $testee->addFactory(
            $expectedKey,
            static function (): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
        );
        $container = $testee->createReadOnlyContainer();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    /**
     * @test
     */
    public function testFactoryOverridesService(): void
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addService(
            $expectedKey,
            static function (): \DateTime {
                return new \DateTime();
            }
        );
        $testee->addFactory(
            $expectedKey,
            static function (): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
        );
        $container = $testee->createReadOnlyContainer();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);

        $secondResult = $container->get($expectedKey);

        self::assertNotSame(
            $result,
            $secondResult,
            'Container should return new instances after overriding the initial service'
        );
    }

    /**
     * @test
     */
    public function testServiceOverridesFactory(): void
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addFactory(
            $expectedKey,
            static function (): \DateTime {
                return new \DateTime();
            }
        );
        $testee->addService(
            $expectedKey,
            static function (): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
        );
        $container = $testee->createReadOnlyContainer();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);

        $secondResult = $container->get($expectedKey);

        self::assertSame(
            $result,
            $secondResult,
            'Container entry should remain the same instance after overriding the initial factory'
        );
    }

    /**
     * @test
     */
    public function testHasServiceNotFound(): void
    {
        $testee = new ContainerConfigurator();
        static::assertFalse($testee->hasService('unknown-service'));
    }

    /**
     * @test
     */
    public function testHasServiceInChildContainer(): void
    {
        $expectedKey = 'key';
        $childContainer = $this->stubContainer($expectedKey);

        $testee = new ContainerConfigurator();
        $testee->addContainer($childContainer);

        static::assertTrue($testee->hasService($expectedKey));
    }

    /**
     * @test
     */
    public function testExtensionById(): void
    {
        $testee = new ContainerConfigurator();

        $expectedKey = 'key';
        $expected = 'expectedValue';

        $expectedOriginalValue = new class
        {
            public function __toString()
            {
                return 'original';
            }
        };

        $expectedExtendedValue = new class ($expected)
        {
            private string $expected;

            public function __construct(string $expected)
            {
                $this->expected = $expected;
            }

            public function __toString()
            {
                return $this->expected;
            }
        };

        $testee->addService(
            $expectedKey,
            /**
             * @param mixed $previous
             * @return mixed
             */
            static function () use ($expectedOriginalValue) {
                return $expectedOriginalValue;
            }
        );

        $testee->addExtension(
            $expectedKey,
            /**
             * @param mixed $previous
             * @return mixed
             */
            static function ($previous) use ($expectedOriginalValue, $expectedExtendedValue) {
                static::assertSame($expectedOriginalValue, $previous);

                return $expectedExtendedValue;
            }
        );

        static::assertTrue($testee->hasService($expectedKey));
        static::assertTrue($testee->hasExtension($expectedKey));
        static::assertSame($expectedExtendedValue, $testee->createReadOnlyContainer()->get($expectedKey));
    }

    /**
     * @test
     */
    public function testExtensionByType(): void
    {
        $string = 'Test';
        $array = ['test' => 'Test'];
        $iterator = new \ArrayIterator($array);
        $object = (object) $array;
        $int = 0;

        $configurator = new ContainerConfigurator();

        $services = compact('string', 'array', 'iterator', 'object', 'int');
        $container = new class ($services) extends \ArrayObject implements ContainerInterface
        {
            public function get(string $id)
            {
                return $this[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return $this->offsetExists($id);
            }
        };

        $configurator->addContainer($container);

        $configurator->addExtension(
            '@instanceof<ArrayIterator>',
            static function (\ArrayIterator $object): array {
                $array = $object->getArrayCopy();
                $array['works'] = 'Works!';

                return $array;
            }
        );

        $configurator->addExtension(
            '@instanceof<string>',
            static function (): string {
                throw new \Error('Failed!');
            }
        );
        // Invalid code does not break resolution
        $configurator->addExtension(
            '@instanceof<This-Is-Not-Valid-Code!>',
            static function (): array {
                throw new \Error('Failed!');
            }
        );
        // Undefined classes are ignored
        $configurator->addExtension(
            '@instanceof<ThisCouldBeValidClassNameButItDoesNotExists>',
            static function (): array {
                throw new \Error('Failed!');
            }
        );
        // This is fine, but we don't expect it running because there are no stdClass in services
        $configurator->addExtension(
            '@instanceof<stdClass>',
            static function (\stdClass $object): \stdClass {
                $array = get_object_vars($object);
                $array['works'] = 'Works!';
                return (object) $array;
            }
        );
        $configurator->addExtension(
            '@instanceof<bool>',
            static function (): bool {
                throw new \Error('Failed!');
            }
        );
        $configurator->addExtension(
            '@instanceof<int>',
            static function (): int {
                throw new \Error('Failed!');
            }
        );

        $container = $configurator->createReadOnlyContainer();

        static::assertSame(
            ['test' => 'Test', 'works' => 'Works!'],
            $container->get('iterator')
        );

        static::assertSame(
            ['test' => 'Test', 'works' => 'Works!'],
            (array) $container->get('object')
        );

        static::assertSame('Test', $container->get('string'));
        static::assertSame(['test' => 'Test'], $container->get('array'));
        static::assertSame(0, $container->get('int'));
    }

    private function loadStubs(): void
    {
        require_once __DIR__ . '/../stubs.php';
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testExtensionByTypeNoInfiniteRecursion(): void
    {
        $this->loadStubs();

        $called = [];

        $configurator = new ContainerConfigurator();
        $configurator->addService('test', static function (): \A {
            return new \A();
        });
        $configurator->addExtension(
            '@instanceof<B>',
            static function (\B $object) use (&$called): \B {
                $called[] = 'instanceof<B>';
                return $object;
            }
        );
        $configurator->addExtension(
            '@instanceof<A>',
            static function () use (&$called): \B {
                $called[] = 'instanceof<A>';
                return new \B();
            }
        );

        $object = $configurator->createReadOnlyContainer()->get('test');
        static::assertTrue($object instanceof \B);
        static::assertSame(['instanceof<A>', 'instanceof<B>', 'instanceof<A>'], $called);
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testExtensionByTypeNested(): void
    {
        $logs = [];
        /**
         * @template T
         *
         * @param object&T $object
         * @param int ...$nums
         *
         * @return object&T
         */
        $log = static function ($object, int ...$nums) use (&$logs) {
            foreach ($nums as $num) {
                if (!in_array($num, $logs, true)) {
                    $logs[] = $num;
                    break;
                }
            }
            return $object;
        };

        $configurator = new ContainerConfigurator();
        /**
         * @return \ArrayAccess<string,string>
         */
        $service = static function (): \ArrayAccess {
            return new \ArrayObject();
        };
        $configurator->addService('test', $service);

        $this->loadStubs();

        $configurator->addExtension(
            '@instanceof<D>',
            static function (\D $object) use (&$log): \E {
                return $log(new \E(), 6, 9);
            }
        );
        $configurator->addExtension(
            '@instanceof<A>',
            static function (\A $object) use (&$log): \A {
                return $log($object, -1); // we never expect this to run
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayAccess>',
            static function (\ArrayAccess $object) use (&$log): object {
                /** @var \ArrayAccess<string, string> */
                return $log($object, 2);
            }
        );
        $configurator->addExtension(
            "@instanceof<B>",
            static function (\B $object) use (&$log): \C {
                return $log(new \C(), 4);
            }
        );
        $configurator->addExtension(
            'test',
            static function (object $object) use ($log): object {
                return $log($object, 0);
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayObject>',
            static function (\ArrayObject $object) use (&$log): \ArrayObject {
                /** @var \ArrayObject<string, string> */
                return $log($object, 1);
            }
        );
        $configurator->addExtension(
            '@instanceof<C>',
            static function (\C $object) use (&$log): \D {
                return $log(new \D(), 5);
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayAccess>',
            static function (\ArrayAccess $object) use (&$log): \B {
                return $log(new \B(), 3);
            }
        );
        $configurator->addExtension(
            "@instanceof<E>",
            static function (\E $object) use (&$log): \E {
                return $log($object, 8);
            }
        );
        $configurator->addExtension(
            "@instanceof<D>",
            static function (\D $object) use (&$log): \D {
                return $log($object, 7, 10);
            }
        );

        $service = $configurator->createReadOnlyContainer()->get('test');

        static::assertTrue($service instanceof \E);
        // test the order of callbacks was the one expected
        static::assertSame(range(0, 10), $logs);
    }

    /**
     * @test
     */
    public function testCustomContainer(): void
    {
        $expectedId = 'expected-id';
        $expectedValue = new \stdClass();

        $childContainer = new class ($expectedId, $expectedValue) implements ContainerInterface
        {
            /** @var array<string, object> */
            private array $values = [];

            public function __construct(string $expectedId, object $expectedValue)
            {
                $this->values[$expectedId] = $expectedValue;
            }

            public function get(string $id)
            {
                return $this->values[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->values[$id]);
            }
        };

        $testee = new ContainerConfigurator([$childContainer]);

        static::assertTrue($testee->hasService($expectedId));

        $readOnlyContainer = $testee->createReadOnlyContainer();
        static::assertSame($expectedValue, $readOnlyContainer->get($expectedId));
    }
}
