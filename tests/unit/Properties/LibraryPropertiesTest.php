<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Properties\LibraryProperties;
use Inpsyde\Modularity\Tests\TestCase;
use org\bovigo\vfs\vfsStream;

class LibraryPropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testForLibraryInvalidFile(): void
    {
        static::expectException(\Exception::class);
        LibraryProperties::for('non-existing.file');
    }

    /**
     * @test
     */
    public function testForLibrary(): void
    {
        $expectedName = "properties-test";
        $composerJsonData = [
            "name" => $expectedName,
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::for($root->url() . '/json/composer.json');

        static::assertSame($expectedName, $testee->baseName());
    }

    /**
     * @test
     */
    public function testForLibraryWithoutVendor(): void
    {
        $expectedName = "properties-test";
        $composerJsonData = [
            "name" => $expectedName,
        ];

        $structure = [
            'json' => [
                'composer.json' => json_encode($composerJsonData),
            ],
        ];
        $root = vfsStream::setup('root', null, $structure);

        $testee = LibraryProperties::for($root->url() . '/json/composer.json');

        static::assertSame($expectedName, $testee->baseName());
    }

    /**
     * @test
     */
    public function testForLibraryAllProperties(): void
    {
        $expectedBaseName = "properties-test";
        $expectedDescription = 'the description';
        $expectedAuthor = 'Inpsyde GmbH';
        $expectedAuthorUri = 'https://www.inpsyde.com';
        $expectedDomainPath = 'languages/';
        $expectedName = "Properties Test";
        $expectedTextDomain = 'properties-test';
        $expectedUri = 'http://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expecteWpVersion = "5.3";

        $composerJsonData = [
            "name" => $expectedBaseName,
            "description" => $expectedDescription,
            "authors" => [
                [
                    "name" => $expectedAuthor,
                    "homepage" => $expectedAuthorUri,
                ],
            ],
            "config" => [
                "platform" => [
                    "php" => $expectedPhpVersion,
                ],
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

        $testee = LibraryProperties::for($root->url() . '/json/composer.json');

        static::assertInstanceOf(Properties::class, $testee);
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
}