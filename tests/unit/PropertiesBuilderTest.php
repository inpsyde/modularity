<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\PropertiesBuilder;
use Inpsyde\Modularity\PropertiesInterface;
use Inpsyde\Modularity\Tests\TestCase;
use org\bovigo\vfs\vfsStream;
use \Brain\Monkey\Functions;

class PropertiesBuilderTest extends TestCase
{

    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $expectedPath = __DIR__.'/';
        $testee = PropertiesBuilder::new($expectedName, $expectedPath);

        $properties = $testee->build();
        static::assertInstanceOf(PropertiesInterface::class, $properties);
        static::assertSame($expectedName, $properties->baseName());
        static::assertSame($expectedPath, $properties->basePath());
    }

    /**
     * @test
     */
    public function testSet(): void
    {
        $expectedKey = 'foo';
        $expectedValue = 'bar';

        $testee = PropertiesBuilder::new('foo', __DIR__);
        $testee->set($expectedKey, $expectedValue);

        $properties = $testee->build();
        static::assertTrue($properties->has($expectedKey));
        static::assertSame($expectedValue, $properties->get($expectedKey));
    }

    /**
     * @test
     */
    public function testAdd(): void
    {
        $expectedKey = 'foo';
        $expectedValue = 'bar';

        $testee = PropertiesBuilder::new('foo', __DIR__);
        $testee->add([$expectedKey => $expectedValue]);

        $properties = $testee->build();
        static::assertTrue($properties->has($expectedKey));
        static::assertSame($expectedValue, $properties->get($expectedKey));
    }

    /**
     * @test
     */
    public function testForLibraryInvalidFile(): void
    {
        static::expectException(\Exception::class);
        PropertiesBuilder::forLibrary('non-existing.file');
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

        $testee = PropertiesBuilder::forLibrary($root->url().'/json/composer.json');
        $properties = $testee->build();

        static::assertSame($expectedName, $properties->baseName());
        static::assertTrue($properties->isLibrary());
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

        $testee = PropertiesBuilder::forLibrary($root->url().'/json/composer.json');
        $properties = $testee->build();

        static::assertSame($expectedName, $properties->baseName());
        static::assertTrue($properties->isLibrary());
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

        $testee = PropertiesBuilder::forLibrary($root->url().'/json/composer.json');
        $properties = $testee->build();

        static::assertSame($expectedBaseName, $properties->baseName());
        static::assertTrue($properties->isLibrary());

        static::assertSame($expectedDescription, $properties->get('description'));
        static::assertSame($expectedAuthor, $properties->get('author'));
        static::assertSame($expectedAuthorUri, $properties->get('authorUri'));
        static::assertSame($expectedDomainPath, $properties->get('domainPath'));
        static::assertSame($expectedName, $properties->get('name'));
        static::assertSame($expectedTextDomain, $properties->get('textDomain'));
        static::assertSame($expectedUri, $properties->get('uri'));
        static::assertSame($expectedVersion, $properties->get('version'));
        static::assertSame($expecteWpVersion, $properties->get('requiresWp'));
        static::assertSame($expectedPhpVersion, $properties->get('requiresPhp'));
    }

    /**
     * @test
     */
    public function testForPlugin(): void
    {
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

        $expectedBaseName = 'plugin-name';
        $expectedBasePath = '/path/to/plugin/';

        \Brain\Monkey\Functions\expect('get_plugin_data')
            ->andReturn(
                [
                    'Name' => $expectedName,
                    'Author'=> $expectedAuthor,
                    'AuthorURI' => $expectedAuthorUri,
                    'Description' => $expectedDescription,
                    'DomainPath' => $expectedDomainPath,
                    'TextDomain' => $expectedTextDomain,
                    'PluginURI' => $expectedUri,
                    'Version' => $expectedVersion,
                    'RequiresWP' => $expecteWpVersion,
                    'RequiresPHP' => $expectedPhpVersion,
                ]
            );

        Functions\when('plugins_url')->returnArg(1);

        Functions\expect('plugin_basename')
            ->andReturn($expectedBaseName);

        Functions\expect('plugin_dir_path')
            ->andReturn($expectedBasePath);

        $testee = PropertiesBuilder::forPlugin($expectedBasePath);
        $properties = $testee->build();

        static::assertTrue($properties->isPlugin());
        static::assertSame($expectedDescription, $properties->get('description'));
        static::assertSame($expectedAuthor, $properties->get('author'));
        static::assertSame($expectedAuthorUri, $properties->get('authorUri'));
        static::assertSame($expectedDomainPath, $properties->get('domainPath'));
        static::assertSame($expectedName, $properties->get('name'));
        static::assertSame($expectedTextDomain, $properties->get('textDomain'));
        static::assertSame($expectedUri, $properties->get('uri'));
        static::assertSame($expectedVersion, $properties->get('version'));
        static::assertSame($expecteWpVersion, $properties->get('requiresWp'));
        static::assertSame($expectedPhpVersion, $properties->get('requiresPhp'));
    }
}
