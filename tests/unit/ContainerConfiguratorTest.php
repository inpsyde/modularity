<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\ContainerConfigurator;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ContainerConfiguratorTest extends TestCase
{

    /**
     * @test
     */
    public function testBasic()
    {
        $testee = new ContainerConfigurator();

        static::assertInstanceOf(ContainerConfigurator::class, $testee);
        static::assertFalse($testee->hasService('something'));
        static::assertFalse($testee->hasExtension('something'));
        static::assertInstanceOf(ContainerInterface::class, $testee->compile());
    }

    /**
     * @test
     */
    public function testAddHasService()
    {
        $expectedKey = 'key';
        $expectedValue = new class {

        };

        $testee = new ContainerConfigurator();

        static::assertFalse($testee->hasService($expectedKey));

        $testee->addService(
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
    public function testAddServiceTwice()
    {
        static::expectException(\Exception::class);

        $expectedKey = 'key';
        $expectedValue = new class {

        };

        $testee = new ContainerConfigurator();
        $testee->addService(
            $expectedKey,
            function () use ($expectedValue) {
                return $expectedValue;
            }
        );
        $testee->addService(
            $expectedKey,
            function () use ($expectedValue) {
                return $expectedValue;
            }
        );
    }

    /**
     * @test
     */
    public function testHasServiceNotFound()
    {
        $testee = new ContainerConfigurator();
        static::assertFalse($testee->hasService('unknown-service'));
    }

    /**
     * @test
     */
    public function testHasServiceInChildContainer()
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
                if (! $this->has($id)) {
                    return null;
                }

                return $this->data[$id]();
            }

            public function has($id)
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
    public function testAddExtension()
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
        static::assertSame($expectedExtendedValue, $testee->compile()->get($expectedKey));
    }
}
