<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Properties\PluginProperties;
use Inpsyde\Modularity\Properties\ThemeProperties;
use Inpsyde\Modularity\Tests\TestCase;
use \Brain\Monkey\Functions;

class ThemePropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
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
        $expectedStatus = 'publish';
        $expectedTags = ['foo', 'bar'];
        $expectedTemplate = 'template';

        $expectedBaseName = 'plugin-name';
        $expectedBasePath = '/path/to/plugin/';
        $expectedBaseUrl = 'https://localhost' . $expectedBasePath;

        $values = [
            'Author' => $expectedAuthor,
            'Author URI' => $expectedAuthorUri,
            'Description' => $expectedDescription,
            'Domain Path' => $expectedDomainPath,
            'Theme Name' => $expectedName,
            'Text Domain' => $expectedTextDomain,
            'Theme URI' => $expectedUri,
            'Version' => $expectedVersion,
            'Requires at least' => $expecteWpVersion,
            'Requires PHP' => $expectedPhpVersion,
            'Status' => $expectedStatus,
            'Tags' => $expectedTags,
            'Template' => $expectedTemplate,
        ];

        $themeStub = \Mockery::mock(\WP_Theme::class);
        foreach ($values as $key => $return) {
            $themeStub->expects('get')->with($key)->andReturn($return);
        }
        $themeStub->expects('get_stylesheet')->andReturn($expectedBaseName);
        $themeStub->expects('get_template_directory')->andReturn($expectedBasePath);
        $themeStub->expects('get_stylesheet_directory_uri')->andReturn($expectedBaseUrl);

        Functions\expect('wp_get_theme')->with($expectedBasePath)->andReturn($themeStub);

        $testee = ThemeProperties::for($expectedBasePath);

        static::assertInstanceOf(Properties::class, $testee);

        static::assertSame($expectedBaseName, $testee->baseName());
        static::assertSame($expectedBasePath, $testee->basePath());
        static::assertSame($expectedBaseUrl, $testee->baseUrl());

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

        // specific methods for Themes.
        static::assertSame($expectedTags, $testee->tags());
        static::assertSame($expectedTemplate, $testee->template());
        static::assertSame($expectedStatus, $testee->status());
    }
}
