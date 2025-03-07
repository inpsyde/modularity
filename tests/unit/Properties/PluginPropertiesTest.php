<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Brain\Monkey\Functions;
use Inpsyde\Modularity\Properties\PluginProperties;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;

class PluginPropertiesTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedDescription = 'the description';
        $expectedAuthor = 'Syde GmbH';
        $expectedAuthorUri = 'https://syde.com/';
        $expectedDomainPath = 'languages/';
        $expectedName = "Properties Test";
        $expectedTextDomain = 'properties-test';
        $expectedUri = 'https://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expectedWpVersion = "5.3";
        $expectedNetwork = random_int(1, 1000) > 500;

        $expectedPluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';
        $expectedBasePath = '/app/wp-content/plugins/plugin-dir/';
        $expectedSanitizedBaseName = 'plugin-dir';

        Functions\expect('get_plugin_data')
            ->andReturn(
                [
                    'Name' => $expectedName,
                    'Author' => $expectedAuthor,
                    'AuthorURI' => $expectedAuthorUri,
                    'Description' => $expectedDescription,
                    'DomainPath' => $expectedDomainPath,
                    'TextDomain' => $expectedTextDomain,
                    'PluginURI' => $expectedUri,
                    'Version' => $expectedVersion,
                    'RequiresWP' => $expectedWpVersion,
                    'RequiresPHP' => $expectedPhpVersion,
                    'Network' => $expectedNetwork,
                ]
            );

        Functions\when('plugins_url')->returnArg(1);
        Functions\when('wp_normalize_path')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\expect('plugin_dir_path')->andReturn($expectedBasePath);

        $properties = PluginProperties::new($expectedPluginMainFile);

        static::assertInstanceOf(Properties::class, $properties);
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
        static::assertSame($expectedSanitizedBaseName, $properties->baseName());
        // Custom to Plugins
        static::assertSame($expectedNetwork, $properties->network());
        static::assertSame($expectedPluginMainFile, $properties->pluginMainFile());
    }

    /**
     * @test
     * @runInSeparateProcess
     * @dataProvider provideRequiresPluginsData
     *
     * @param string $requiresPlugins
     * @param string[] $expected
     */
    public function testRequiresPlugins(string $requiresPlugins, array $expected): void
    {
        $pluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';

        Functions\expect('get_plugin_data')->andReturn([
            'RequiresPlugins' => $requiresPlugins,
        ]);
        Functions\when('plugins_url')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\when('plugin_dir_path')->returnArg(1);

        Functions\expect('wp_normalize_path')->andReturnFirstArg();

        $properties = PluginProperties::new($pluginMainFile);
        static::assertEquals($expected, $properties->requiresPlugins());
    }

    /**
     * @return \Generator
     */
    public static function provideRequiresPluginsData(): \Generator
    {
        yield from [
            'no dependencies' => [
                '',
                [],
            ],
            'one dependency' => [
                'dependency',
                [
                    'dependency',
                ],
            ],
            'multiple dependencies' => [
                'dependency1,dependency2,dependency3',
                [
                    'dependency1',
                    'dependency2',
                    'dependency3',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function testIsActive(): void
    {
        $pluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';
        $expectedBasePath = '/app/wp-content/plugins/plugin-dir/';

        Functions\when('get_plugin_data')->justReturn([]);
        Functions\when('plugins_url')->returnArg(1);
        Functions\when('wp_normalize_path')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\expect('plugin_dir_path')->andReturn($expectedBasePath);

        $properties = PluginProperties::new($pluginMainFile);

        Functions\expect('is_plugin_active')
            ->andReturnUsing(static function (string $baseName) use ($expectedBaseName): bool {
                return $baseName === $expectedBaseName;
            });

        static::assertTrue($properties->isActive());
    }

    /**
     * @test
     */
    public function testIsNetworkActive(): void
    {
        $pluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';
        $expectedBasePath = '/app/wp-content/plugins/plugin-dir/';

        Functions\expect('get_plugin_data')->andReturn([]);
        Functions\when('plugins_url')->returnArg(1);
        Functions\when('wp_normalize_path')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\expect('plugin_dir_path')->andReturn($expectedBasePath);

        Functions\expect('is_plugin_active_for_network')
            ->andReturnUsing(static function (string $baseName) use ($expectedBaseName): bool {
                return $baseName === $expectedBaseName;
            });

        $properties = PluginProperties::new($pluginMainFile);
        static::assertTrue($properties->isNetworkActive());
    }

    /**
     * @test
     * @dataProvider provideCustomHeaders
     *
     * @param array<string, string> $customHeaders
     */
    public function testCustomPluginHeaders(array $customHeaders): void
    {
        $pluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';
        $expectedBasePath = '/app/wp-content/plugins/plugin-dir/';
        $expectedSanitizedBaseName = 'plugin-dir';

        $expectedAuthor = 'Syde GmbH';
        $expectedAuthorUri = 'https://syde.com/';

        $pluginData = array_merge(
            [
                'Author' => $expectedAuthor,
                'AuthorURI' => $expectedAuthorUri,
            ],
            $customHeaders
        );

        Functions\expect('get_plugin_data')->andReturn($pluginData);
        Functions\when('plugins_url')->returnArg(1);
        Functions\when('wp_normalize_path')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\expect('plugin_dir_path')->andReturn($expectedBasePath);

        $properties = PluginProperties::new($pluginMainFile);

        // Check if PluginProperties do behave as normal
        static::assertSame($expectedSanitizedBaseName, $properties->baseName());
        static::assertSame($expectedBasePath, $properties->basePath());

        // Test default Headers
        static::assertSame($expectedAuthor, $properties->author());
        static::assertSame($expectedAuthor, $properties->get(Properties::PROP_AUTHOR));
        static::assertSame($expectedAuthorUri, $properties->authorUri());
        static::assertSame($expectedAuthorUri, $properties->get(Properties::PROP_AUTHOR_URI));

        // Test headers from get_plugin_data() are removed from properties
        // "Author" will be mapped to Properties::PROP_AUTHOR
        static::assertFalse($properties->has('Author'));
        static::assertFalse($properties->has('AuthorURI'));

        // Test custom Headers
        foreach ($customHeaders as $key => $value) {
            static::assertTrue($properties->has($key));
            static::assertSame($value, $properties->get($key));
        }
    }

    /**
     * @return \Generator
     */
    public function provideCustomHeaders(): \Generator
    {
        yield 'WooCommerce Plugin Headers' => [
            [
                'WC requires at least' => '2.2',
                'WC tested up to:' => '2.3',
            ],
        ];

        yield 'Custom Plugin Headers' => [
            [
                'Foo' => 'bar',
                'Baz' => 'bam',
            ],
        ];
    }

    /**
     * @test
     * @runInSeparateProcess
     * @dataProvider provideIsMuPluginData
     *
     * @param string $pluginMainFile
     * @param string $muPluginDir
     * @param bool $expected
     */
    public function testIsMuPlugin(string $pluginMainFile, string $muPluginDir, bool $expected): void
    {
        $expectedBaseName = 'the-plugin/index.php';

        Functions\expect('get_plugin_data')->andReturn([]);
        Functions\when('plugins_url')->returnArg(1);
        Functions\expect('plugin_basename')->andReturn($expectedBaseName);
        Functions\when('plugin_dir_path')->returnArg(1);

        Functions\expect('wp_normalize_path')->andReturnFirstArg();

        define('WPMU_PLUGIN_DIR', $muPluginDir);

        $properties = PluginProperties::new($pluginMainFile);
        static::assertSame($expected, $properties->isMuPlugin());
    }

    /**
     * @return \Generator
     */
    public static function provideIsMuPluginData(): \Generator
    {
        yield from [
            'is not mu-plugin' => [
                '/wp-content/plugins/the-plugin/index.php',
                '/wp-content/mu-plugins/',
                false,
            ],
            'is mu-plugin' => [
                '/wp-content/mu-plugins/the-plugin/index.php',
                '/wp-content/mu-plugins/',
                true,
            ],
        ];
    }
}
