<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Brain\Monkey\Functions;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Properties\ThemeProperties;
use Inpsyde\Modularity\Tests\TestCase;

class ThemePropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedDescription = 'the description';
        $expectedAuthor = 'Inpsyde GmbH';
        $expectedAuthorUri = 'https://inpsyde.com/';
        $expectedDomainPath = 'languages/';
        $expectedName = "Properties Test";
        $expectedTextDomain = 'properties-test';
        $expectedUri = 'https://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expectedWpVersion = "5.3";
        $expectedStatus = 'publish';
        $expectedTags = ['foo', 'bar'];

        $expectedBaseName = 'plugin-name';
        $expectedBasePath = '/path/to/plugin/';
        $expectedBaseUrl = 'https://localhost' . $expectedBasePath;

        $values = [
            'Author' => $expectedAuthor,
            'AuthorURI' => $expectedAuthorUri,
            'Description' => $expectedDescription,
            'DomainPath' => $expectedDomainPath,
            'Name' => $expectedName,
            'TextDomain' => $expectedTextDomain,
            'ThemeURI' => $expectedUri,
            'Version' => $expectedVersion,
            'RequiresWP' => $expectedWpVersion,
            'RequiresPHP' => $expectedPhpVersion,
            'Status' => $expectedStatus,
            'Tags' => $expectedTags,
            // No child-Theme.
            'Template' => '',
        ];

        $themeStub = \Mockery::mock(\WP_Theme::class);
        foreach ($values as $key => $return) {
            $themeStub->expects('get')->with($key)->andReturn($return);
        }
        $themeStub->expects('get_stylesheet')->andReturn($expectedBaseName);
        $themeStub->expects('get_stylesheet_directory')->andReturn($expectedBasePath);
        $themeStub->expects('get_stylesheet_directory_uri')->andReturn($expectedBaseUrl);

        Functions\expect('wp_get_theme')->with($expectedBasePath)->andReturn($themeStub);
        Functions\expect('get_stylesheet')->andReturn($expectedBaseName);

        $properties = ThemeProperties::new($expectedBasePath);

        static::assertInstanceOf(Properties::class, $properties);

        static::assertSame($expectedBaseName, $properties->baseName());
        static::assertSame($expectedBasePath, $properties->basePath());
        static::assertSame($expectedBaseUrl, $properties->baseUrl());

        static::assertSame($expectedDescription, $properties->description());
        static::assertSame($expectedAuthor, $properties->author());
        static::assertSame($expectedAuthorUri, $properties->authorUri());
        static::assertSame($expectedDomainPath, $properties->domainPath());
        static::assertSame($expectedName, $properties->name());
        static::assertSame($expectedTextDomain, $properties->textDomain());
        static::assertSame($expectedUri, $properties->uri());
        static::assertSame($expectedVersion, $properties->version());
        static::assertSame($expectedWpVersion, $properties->requiresWp());
        static::assertSame($expectedPhpVersion, $properties->requiresPhp());

        // specific methods for Themes.
        static::assertSame($expectedTags, $properties->tags());
        static::assertSame('', $properties->template());
        static::assertSame($expectedStatus, $properties->status());

        // API for Themes
        static::assertFalse($properties->isChildTheme());
        static::assertTrue($properties->isCurrentTheme());
        static::assertNull($properties->parentThemeProperties());
    }

    /**
     * @test
     */
    public function testChildTheme(): void
    {
        $expectedBaseName = 'plugin-name';
        $expectedBasePath = '/path/to/plugin/';
        $expectedBaseUrl = 'https://localhost' . $expectedBasePath;

        $expectedTemplate = 'parent-theme';

        $themeStub = \Mockery::mock(\WP_Theme::class);

        $themeStub->allows('get')->andReturnArg(0)->byDefault();
        $themeStub->expects('get')->with('Template')->andReturn($expectedTemplate);

        $themeStub->expects('get_stylesheet')->andReturn($expectedBaseName);
        $themeStub->expects('get_stylesheet_directory')->andReturn($expectedBasePath);
        $themeStub->expects('get_stylesheet_directory_uri')->andReturn($expectedBaseUrl);

        Functions\expect('wp_get_theme')->with($expectedBasePath)->andReturn($themeStub);

        $properties = ThemeProperties::new($expectedBasePath);

        static::assertSame($expectedTemplate, $properties->template());
        static::assertTrue($properties->isChildTheme());
    }
}
