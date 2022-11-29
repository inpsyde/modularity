<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Container;

use Inpsyde\Modularity\Container\ReadOnlyContainerCompiler;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ReadOnlyContainerCompilerTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $testee = new ReadOnlyContainerCompiler();

        static::assertFalse($testee->hasService('something'));
        static::assertFalse($testee->hasExtension('something'));
        static::assertInstanceOf(ContainerInterface::class, $testee->compile());
    }

    /**
     * @test
     */
    public function testAddHasService(): void
    {
        $expectedKey = 'key';
        $expectedValue = new class {
        };

        $testee = new ReadOnlyContainerCompiler();

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

        $testee = new ReadOnlyContainerCompiler();

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

        $testee = new ReadOnlyContainerCompiler();
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
        $container = $testee->compile();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    /**
     * @test
     */
    public function testFactoryOverride()
    {
        $expectedKey = 'key';

        $testee = new ReadOnlyContainerCompiler();
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
        $container = $testee->compile();
        $result = $container->get($expectedKey);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    /**
     * @test
     */
    public function testFactoryOverridesService()
    {
        $expectedKey = 'key';

        $testee = new ReadOnlyContainerCompiler();
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
        $container = $testee->compile();
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

        $testee = new ReadOnlyContainerCompiler();
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
        $container = $testee->compile();
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
        $testee = new ReadOnlyContainerCompiler();
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

            public function get($id)
            {
                if (!$this->has($id)) {
                    return null;
                }

                return $this->data[$id]();
            }

            public function has($id)
            {
                return array_key_exists($id, $this->data);
            }
        };

        $testee = new ReadOnlyContainerCompiler($childContainer);

        static::assertTrue($testee->hasService($expectedKey));
    }

    /**
     * @test
     */
    public function testAddExtension(): void
    {
        $testee = new ReadOnlyContainerCompiler();

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
        static::assertSame($expectedExtendedValue, $testee->compile()->get($expectedKey));
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

            public function get($id)
            {
                return $this->values[$id];
            }

            public function has($id)
            {
                return isset($this->values[$id]);
            }
        };

        $testee = new ReadOnlyContainerCompiler($childContainer);

        static::assertTrue($testee->hasService($expectedId));

        $readOnlyContainer = $testee->compile();
        static::assertSame($expectedValue, $readOnlyContainer->get($expectedId));
    }
}
