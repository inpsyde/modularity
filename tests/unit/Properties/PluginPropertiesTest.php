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
        $expectedAuthorUri = 'https://www.inpsyde.com';
        $expectedDomainPath = 'languages/';
        $expectedName = "Properties Test";
        $expectedTextDomain = 'properties-test';
        $expectedUri = 'http://github.com/inpsyde/modularity';
        $expectedVersion = '1.0';
        $expectedPhpVersion = "7.4";
        $expecteWpVersion = "5.3";
        $expectedNetwork = true;

        $expectedBaseName = 'plugin-name';
        $expectedBasePath = '/path/to/plugin/';

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
                    'RequiresWP' => $expecteWpVersion,
                    'RequiresPHP' => $expectedPhpVersion,
                    'Network' => $expectedNetwork,
                ]
            );

        Functions\when('plugins_url')->returnArg(1);

        Functions\expect('plugin_basename')
            ->andReturn($expectedBaseName);

        Functions\expect('plugin_dir_path')
            ->andReturn($expectedBasePath);

        $testee = PluginProperties::new($expectedBasePath);

        static::assertInstanceOf(Properties::class, $testee);
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
        // Custom to Plugins
        static::assertSame($expectedNetwork, $testee->network());
    }
}
