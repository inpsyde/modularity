<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Properties\LibraryProperties;
use Inpsyde\Modularity\Exception\FileNotFoundException;
use Inpsyde\Modularity\Tests\TestCase;
use org\bovigo\vfs\vfsStream;

class LibraryPropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testForLibraryInvalidFile(): void
    {
        static::expectException(FileNotFoundException::class);
        LibraryProperties::new('non-existing.file');
    }

    /**
     * @test
     */
    public function testForLibrary(): void
    {
        $inputName = 'vendor/test';
        $expectedBaseName = "vendor-test";
        $expectedName = "Vendor Test";
        $composerJsonData = [
            "name" => $inputName,
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::new($root->url() . '/json/composer.json');

        static::assertSame($expectedBaseName, $testee->baseName());
        static::assertSame($expectedName, $testee->name());
    }

    /**
     * @test
     */
    public function testVersionInRoot(): void
    {
        $expectedVersion = '1.0';
        $composerJsonData = [
            "name" => 'test',
            "version" => $expectedVersion,
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::new($root->url() . '/json/composer.json');

        static::assertSame($expectedVersion, $testee->version());
    }

    /**
     * @test
     */
    public function testForLibraryWithoutVendor(): void
    {
        $expectedBaseName = "properties-test";
        $expectedName = "Properties Test";
        $composerJsonData = [
            "name" => $expectedBaseName,
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::new($root->url() . '/json/composer.json');

        static::assertSame($expectedBaseName, $testee->baseName());
        static::assertSame($expectedName, $testee->name());
    }

    /**
     * @test
     */
    public function testForLibraryAllProperties(): void
    {
        $expectedBaseName = "properties-test";
        $expectedDescription = 'the description';
        $expectedAuthor = 'Inpsyde GmbH';
        $expectedAuthorUri = 'https://inpsyde.com/';
        $expectedDomainPath = 'languages/';
        $expectedName = "Properties Test";
        $expectedTextDomain = 'properties-test';
        $expectedUri = 'http://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expecteWpVersion = "5.3";
        $expectedKeywords = ["expected", "keywords"];

        $composerJsonData = [
            "name" => $expectedBaseName,
            "description" => $expectedDescription,
            "authors" => [
                [
                    "name" => $expectedAuthor,
                    "homepage" => $expectedAuthorUri,
                ],
            ],
            "keywords" => $expectedKeywords,
            "require" => [
                "php" => $expectedPhpVersion,
            ],
            "extra" => [
                "modularity" => [
                    "domainPath" => $expectedDomainPath,
                    "name" => $expectedName,
                    "textDomain" => $expectedTextDomain,
                    "uri" => $expectedUri,
                    "version" => $expectedVersion,
                    "requiresWp" => $expecteWpVersion,
                ],
            ],
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::new($root->url() . '/json/composer.json');

        static::assertInstanceOf(Properties::class, $testee);
        static::assertSame($expectedKeywords, $testee->tags());
        static::assertSame($expectedBaseName, $testee->baseName());
        static::assertSame($expectedDescription, $testee->description());
        static::assertSame($expectedAuthor, $testee->author());
        static::assertSame($expectedAuthorUri, $testee->authorUri());
        static::assertSame($expectedDomainPath, $testee->domainPath());
        static::assertSame($expectedName, $testee->name());
        static::assertSame($expectedTextDomain, $testee->textDomain());
        static::assertSame($expectedUri, $testee->uri());
        static::assertSame($expectedVersion, $testee->version());
        static::assertSame($expecteWpVersion, $testee->requiresWp());
        static::assertSame($expectedPhpVersion, $testee->requiresPhp());
    }

    /**
     * @test
     * @dataProvider providePhpRequirements
     */
    public function testPhpDevRequireParsing(string $requirement, ?string $expected): void
    {
        $composerJsonData = [
            'name' => 'inpsyde/some-package_name',
            'require-dev' => [
                'php' => $requirement,
            ],
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::new($root->url() . '/json/composer.json');
        $php = $testee->requiresPhp();

        static::assertSame($expected, $php, "For requirement: '{$requirement}'");
    }

    /**
     * @test
     */
    public function testBaseUrlCanBeSet(): void
    {
        $root = vfsStream::setup('root', null, ['composer.json' => '{"name": "vendor/test"}']);

        $properties = LibraryProperties::new($root->url() . '/composer.json');

        static::assertNull($properties->baseUrl());

        $properties->withBaseUrl('https://example.com');
        static::assertSame('https://example.com/', $properties->baseUrl());
    }

    /**
     * @test
     */
    public function testBaseUrlCanNotBeOverridden(): void
    {
        $root = vfsStream::setup('root', null, ['composer.json' => '{"name": "vendor/test"}']);

        $properties = LibraryProperties::new(
            $root->url() . '/composer.json',
            'https://example.com'
        );

        static::assertSame('https://example.com/', $properties->baseUrl());

        $this->expectExceptionMessageMatches('/not overridable/i');

        $properties->withBaseUrl('https://example.com/something');
    }

    /**
     * @return array
     */
    public function providePhpRequirements(): array
    {
        // [requirement, expected]

        return [
            // simple requirements
            ['7.1', '7.1'],
            ['7.1.3-dev', '7.1.3'],

            // bigger than or equal
            ['>=7.1', '7.1'],
            ['>= 7.4', '7.4'],
            ['> 7.2', '7.2'],
            ['>7.1.3', '7.1.3'],

            // semantic operators
            ['^7.3.0', '7.3.0'],
            ['~7.4.0', '7.4.0'],
            ['^ 7.3.0', '7.3.0'],
            ['~ 7.4.0', '7.4.0'],
            ['^7', '7'],
            ['~7.1', '7.1'],

            // ranges
            ['>= 7.2.4 < 8', '7.2.4'],
            ['>=7.2.4 < 8', '7.2.4'],
            ['>= 7.2.4 <8', '7.2.4'],
            ['>=7.2.4 <8', '7.2.4'],
            ['>=7.2.4<8', '7.2.4'],

            // inline alias
            ['dev-src#abcde as 7.4', '7.4'],

            // alternatives
            ['5.6 || >=7', '5.6'],
            ['5.6 || >= 7', '5.6'],
            ['5.6||>= 7', '5.6'],
            ['5.6||>=7', '5.6'],
            ['5.6||>= 7.2.4 < 8', '5.6'],

            // composite alternatives
            ['>= 7.2.4 < 8 || >= 7.1-dev < 7.2.3', '7.1'],
            ['~7.0.1 || >= 7.1 < 7.2.3', '7.0.1'],
            ['dev-src#abcde as 7.0.5-dev || >= 7.1 < 7.2.3', '7.0.5'],

            // things we don't accept
            ['<= 8', null],
            ['<8', null],
            ['dev-master', null],
            ['dev-foo#abcde', null],
        ];
    }
}
