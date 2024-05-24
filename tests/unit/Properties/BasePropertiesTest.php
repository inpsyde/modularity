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

        $testee = $this->factoryBaseProperties(
            $expectedName,
            $expectedPath
        );

        static::assertInstanceOf(Properties::class, $testee);
        static::assertSame($expectedName, $testee->baseName());
        static::assertSame($expectedPath, $testee->basePath());

        // Defaults
        static::assertFalse($testee->isDebug());
        static::assertEmpty($testee->tags());
        static::assertFalse($testee->has('unknown-key'));
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
     * @dataProvider provideBaseNameData
     */
    public function testBaseNameSanitization(string $baseName, string $sanitizedBaseName): void
    {
        $testee = $this->factoryBaseProperties(
            $baseName,
            ''
        );

        static::assertSame($sanitizedBaseName, $testee->baseName());
    }

    /**
     * @return \Generator
     */
    public static function provideBaseNameData(): \Generator
    {
        yield 'empty' => ['', ''];
        yield 'word' => ['foo', 'foo'];
        yield 'words' => ['foo bar', 'foo bar'];
        yield 'relative path to package' => ['path/package-dir/package.php', 'package-dir'];
        yield 'absolute path' => ['/abs/path/package-dir/package.php', 'package-dir'];
        yield 'single file' => ['package.php', 'package'];
    }

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string|null $baseUrl
     * @param array $properties
     * @return BaseProperties
     */
    private function factoryBaseProperties(
        string $baseName,
        string $basePath,
        string $baseUrl = null,
        array $properties = []
    ): BaseProperties {

        return new class ($baseName, $basePath, $baseUrl, $properties) extends BaseProperties
        {
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
