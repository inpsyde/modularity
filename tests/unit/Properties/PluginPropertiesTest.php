<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit\Properties;

use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Properties\PluginProperties;
use Inpsyde\Modularity\Tests\TestCase;
use \Brain\Monkey\Functions;

class PluginPropertiesTest extends TestCase
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
        $expectedUri = 'http://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expectedWpVersion = "5.3";
        $expectedNetwork = true;

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

        $testee = PluginProperties::new($expectedPluginMainFile);

        static::assertInstanceOf(Properties::class, $testee);
        static::assertSame($expectedDescription, $testee->description());
        static::assertSame($expectedAuthor, $testee->author());
        static::assertSame($expectedAuthorUri, $testee->authorUri());
        static::assertSame($expectedDomainPath, $testee->domainPath());
        static::assertSame($expectedName, $testee->name());
        static::assertSame($expectedTextDomain, $testee->textDomain());
        static::assertSame($expectedUri, $testee->uri());
        static::assertSame($expectedVersion, $testee->version());
        static::assertSame($expectedWpVersion, $testee->requiresWp());
        static::assertSame($expectedPhpVersion, $testee->requiresPhp());
        static::assertSame($expectedSanitizedBaseName, $testee->baseName());
        // Custom to Plugins
        static::assertSame($expectedNetwork, $testee->network());
        static::assertSame($expectedPluginMainFile, $testee->pluginMainFile());
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

        $testee = PluginProperties::new($pluginMainFile);

        Functions\expect('is_plugin_active')->andReturn(true);

        static::assertTrue($testee->isActive());
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

        Functions\expect('is_plugin_active_for_network')->andReturn(true);

        $testee = PluginProperties::new($pluginMainFile);
        static::assertTrue($testee->isNetworkActive());
    }

    /**
     * @test
     * @dataProvider provideCustomHeaders
     *
     * @param array $customHeaders
     */
    public function testCustomPluginHeaders(array $customHeaders): void
    {
        $pluginMainFile = '/app/wp-content/plugins/plugin-dir/plugin-name.php';
        $expectedBaseName = 'plugin-dir/plugin-name.php';
        $expectedBasePath = '/app/wp-content/plugins/plugin-dir/';
        $expectedSanitizedBaseName = 'plugin-dir';

        $expectedAuthor = 'Inpsyde GmbH';
        $expectedAuthorUri = 'https://inpsyde.com/';

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

        $testee = PluginProperties::new($pluginMainFile);

        // Check if PluginProperties do behave as normal
        static::assertSame($expectedSanitizedBaseName, $testee->baseName());
        static::assertSame($expectedBasePath, $testee->basePath());

        // Test default Headers
        static::assertSame($expectedAuthor, $testee->author());
        static::assertSame($expectedAuthor, $testee->get(Properties::PROP_AUTHOR));
        static::assertSame($expectedAuthorUri, $testee->authorUri());
        static::assertSame($expectedAuthorUri, $testee->get(Properties::PROP_AUTHOR_URI));

        // Test headers from get_plugin_data() are removed from properties
        // "Author" will be mapped to Properties::PROP_AUTHOR
        static::assertFalse($testee->has('Author'));
        static::assertFalse($testee->has('AuthorURI'));

        // Test custom Headers
        foreach ($customHeaders as $key => $value) {
            static::assertTrue($testee->has($key));
            static::assertSame($value, $testee->get($key));
        }
    }

    /**
     * Provides custom Plugin Headers which will
     * be returned by get_plugin_data()
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
     *
     * @param string $pluginPath
     * @param string $muPluginDir
     * @param bool $expected
     *
     * @test
     *
     * @runInSeparateProcess
     *
     * @dataProvider provideIsMuPluginData
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

        $testee = PluginProperties::new($pluginMainFile);
        static::assertSame($expected, $testee->isMuPlugin());
    }

    /**
     * @return array[]
     */
    public function provideIsMuPluginData(): array
    {
        return [
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
