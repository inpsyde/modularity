<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\Properties;
use Inpsyde\Modularity\PropertiesInterface;
use Inpsyde\Modularity\Tests\TestCase;

class PropertiesTest extends TestCase
{

    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedBaseName = 'name';
        $expectedBasePath = __DIR__.'/';
        $testee = Properties::new(
            $expectedBaseName,
            $expectedBasePath,
            PropertiesInterface::TYPE_LIBRARY,
            []
        );

        static::assertSame($expectedBasePath, $testee->basePath());
        static::assertSame($expectedBaseName, $testee->baseName());
        static::assertTrue($testee->isLibrary());
        static::assertTrue($testee->isType(PropertiesInterface::TYPE_LIBRARY));
        static::assertFalse($testee->isPlugin());
        static::assertFalse($testee->isTheme());
        static::assertFalse($testee->isType('unknown'));
        static::assertFalse($testee->isDebug());
        static::assertInstanceOf(\ArrayIterator::class, $testee->getIterator());
    }

    /**
     * @test
     */
    public function testProperties(): void
    {
        $expectedKey = 'key';
        $expectedValue = 'value';

        $testee = Properties::new(
            'foo',
            __DIR__,
            PropertiesInterface::TYPE_LIBRARY,
            [$expectedKey => $expectedValue]
        );

        static::assertTrue($testee->has($expectedKey));
        static::assertSame($expectedValue, $testee->get($expectedKey));

        static::assertSame($expectedValue, $testee->{$expectedKey}());
        static::assertCount(1, $testee);
    }

    /**
     * @test
     */
    public function testTypePlugin(): void
    {
        $testee = Properties::new(
            'foo',
            __DIR__,
            PropertiesInterface::TYPE_PLUGIN,
            []
        );

        static::assertTrue($testee->isType(PropertiesInterface::TYPE_PLUGIN));
        static::assertFalse($testee->isLibrary());
        static::assertTrue($testee->isPlugin());
        static::assertFalse($testee->isTheme());
    }

    /**
     * @test
     */
    public function testTypeTheme(): void
    {
        $testee = Properties::new(
            'foo',
            __DIR__,
            PropertiesInterface::TYPE_THEME,
            []
        );

        static::assertTrue($testee->isType(PropertiesInterface::TYPE_THEME));
        static::assertFalse($testee->isLibrary());
        static::assertFalse($testee->isPlugin());
        static::assertTrue($testee->isTheme());
    }
}