<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Inpsyde\Modularity\Properties\BaseProperties;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;

class BasePropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $expectedPath = __DIR__ . '/';

        $testee = $this->createBaseProperties(
            $expectedName,
            $expectedPath
        );

        static::assertInstanceOf(Properties::class, $testee);
        static::assertSame($expectedName, $testee->baseName());
        static::assertSame($expectedPath, $testee->basePath());

        // Defaults
        static::assertFalse($testee->isDebug());
        static::assertSame(null, $testee->baseUrl());
        static::assertSame('', $testee->author());
        static::assertSame('', $testee->authorUri());
        static::assertSame('', $testee->description());
        static::assertSame('', $testee->domainPath());
        static::assertSame('', $testee->name());
        static::assertSame('', $testee->uri());
        static::assertSame('', $testee->version());
        static::assertSame(null, $testee->requiresPhp());
        static::assertSame(null, $testee->requiresWp());
    }

    /**
     * @test
     */
    public function testSetHasGet(): void
    {
        $expectedValue = 'bar';

        $testee = $this->createBaseProperties('foo', __DIR__);

        static::assertFalse($testee->has('foo'));
        $testee->set('foo', $expectedValue);
        static::assertTrue($testee->has('foo'));
        static::assertSame($expectedValue, $testee->get('foo'));
    }

    private function createBaseProperties(
        string $baseName,
        string $basePath,
        string $baseUrl = null,
        array $properties = []
    ): BaseProperties {
        return new class($baseName, $basePath, $baseUrl, $properties) extends BaseProperties {
            public function __construct(
                string $baseName,
                string $basePath,
                string $baseUrl = null,
                array $properties = []
            ) {
                parent::__construct($baseName, $basePath, $baseUrl, $properties);
            }
        };
    }
}