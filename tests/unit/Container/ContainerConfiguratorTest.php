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
            function () use ($expectedValue) {
                return $expectedValue;
            }
        );

        static::assertTrue($testee->hasService($expectedKey));
    }

    /**
     * @test
     */
    public function testServiceOverride()
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addService(
            $expectedKey,
            function () {
                return new \DateTime();
            }
        );
        $testee->addService(
            $expectedKey,
            function () {
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
    public function testFactoryOverride()
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addFactory(
            $expectedKey,
            function () {
                return new \DateTime();
            }
        );
        $testee->addFactory(
            $expectedKey,
            function () {
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
    public function testFactoryOverridesService()
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addService(
            $expectedKey,
            function () {
                return new \DateTime();
            }
        );
        $testee->addFactory(
            $expectedKey,
            function () {
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
    public function testServiceOverridesFactory()
    {
        $expectedKey = 'key';

        $testee = new ContainerConfigurator();
        $testee->addFactory(
            $expectedKey,
            function () {
                return new \DateTime();
            }
        );
        $testee->addService(
            $expectedKey,
            function () {
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
        $expectedValue = new \stdClass();

        $childContainer = new class($expectedKey, $expectedValue) implements ContainerInterface {
            private $data = [];

            public function __construct(string $key, object $value)
            {
                $this->data[$key] = function () use ($value) {
                    return $value;
                };
            }

            public function get(string $id)
            {
                if (!$this->has($id)) {
                    return null;
                }

                return $this->data[$id]();
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->data);
            }
        };

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
        $expectedOriginalValue = new class {
            public function __toString()
            {
                return 'original';
            }
        };
        $expectedExtendedValue = new class($expected) {
            private $expected;

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
            function () use ($expectedOriginalValue) {
                return $expectedOriginalValue;
            }
        );

        $testee->addExtension(
            $expectedKey,
            function ($previous) use ($expectedOriginalValue, $expectedExtendedValue) {
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
        $object = (object)$array;
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
            function (\ArrayIterator $object): array {
                $array = $object->getArrayCopy();
                $array['works'] = 'Works!';

                return $array;
            }
        );

        $configurator->addExtension(
            '@instanceof<string>',
            function (): string {
                throw new \Error('Failed!');
            }
        );
        // Invalid code does not break resolution
        $configurator->addExtension(
            '@instanceof<This-Is-Not-Valid-Code!>',
            function (): array {
                throw new \Error('Failed!');
            }
        );
        // Undefined classes are ignored
        $configurator->addExtension(
            '@instanceof<ThisCouldBeValidClassNameButItDoesNotExists>',
            function (): array {
                throw new \Error('Failed!');
            }
        );
        // This is fine, but we don't expect it running because there are no stdClass in services
        $configurator->addExtension(
            '@instanceof<stdClass>',
            function (\stdClass $object): \stdClass {
                $array = get_object_vars($object);
                $array['works'] = 'Works!';
                return (object)$array;
            }
        );
        $configurator->addExtension(
            '@instanceof<bool>',
            function (): bool {
                throw new \Error('Failed!');
            }
        );
        $configurator->addExtension(
            '@instanceof<int>',
            function (): int {
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
            (array)$container->get('object')
        );

        static::assertSame('Test', $container->get('string'));
        static::assertSame(['test' => 'Test'], $container->get('array'));
        static::assertSame(0, $container->get('int'));
    }

    /**
     * @test
     * @runInSeparateProcess
     *
     * @noinspection PhpUndefinedClassInspection
     */
    public function testExtensionByTypeNoInfiniteRecursion(): void
    {
        // We can't declare classes inside a class, but we can eval it.
        $php = <<<'PHP'
        class A {}
        class B extends A {}
        PHP;
        eval($php);

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
     *
     * @noinspection PhpUndefinedClassInspection
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public function testExtensionByTypeNested(): void
    {
        $logs = [];
        $log = static function (object $object, int ...$nums) use (&$logs): object {
            foreach ($nums as $num) {
                if (!in_array($num, $logs, true)) {
                    $logs[] = $num;
                    break;
                }
            }
            return $object;
        };

        $configurator = new ContainerConfigurator();
        $configurator->addService('test', function () {
            return new \ArrayObject();
        });

        // We can't declare classes inside a class, but we can eval it.
        $php = <<<'PHP'
        class A {}
        class B extends A {}
        class C {}
        class D {};
        class E extends D {};
        PHP;
        eval($php);

        $configurator->addExtension(
            '@instanceof<D>', static function (\D $o) use (&$log): \E {
                return $log(new \E(), 6, 9);
            }
        );
        $configurator->addExtension(
            '@instanceof<A>',
            static function (\A $o) use (&$log): \A {
                return $log($o, -1); // we never expect this to run
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayAccess>',
            static function (\ArrayAccess $o) use (&$log): \ArrayAccess  {
                return $log($o, 2);
            }
        );
        $configurator->addExtension(
            "@instanceof<B>",
            static function (\B $o) use (&$log): \C {
                return $log(new \C(), 4);
            }
        );
        $configurator->addExtension(
            'test',
            static function (object $o) use ($log): object {
                return $log($o, 0);
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayObject>',
            static function (\ArrayObject $o) use (&$log): \ArrayObject {
                return $log($o, 1);
            }
        );
        $configurator->addExtension(
            '@instanceof<C>',
            static function (\C $o) use (&$log): \D {
                return $log(new \D(), 5);
            }
        );
        $configurator->addExtension(
            '@instanceof<ArrayAccess>',
            static function (\ArrayAccess $o) use (&$log): \B {
                return $log(new \B(), 3);
            }
        );
        $configurator->addExtension(
            "@instanceof<E>",
            static function (\E $o) use (&$log): \E {
                return $log($o, 8);
            }
        );
        $configurator->addExtension(
            "@instanceof<D>",
            static function (\D $o) use (&$log): \D {
                return $log($o, 7, 10);
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

        $childContainer = new class($expectedId, $expectedValue) implements ContainerInterface {
            private $values;

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
