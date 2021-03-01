<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Properties;

/**
 * Class PluginProperties
 *
 * @package Inpsyde\Modularity\Properties
 *
 * @psalm-suppress PossiblyFalseArgument, InvalidArgument
 */
class PluginProperties extends BaseProperties
{
    /**
     * Custom properties for Plugins.
     */
    public const PROP_NETWORK = 'network';
    /**
     * Available methods of Properties::__call()
     * from plugin headers.
     *
     * @link https://developer.wordpress.org/reference/functions/get_plugin_data/
     */
    private const PLUGIN_METHODS = [
        self::PROP_AUTHOR => 'Author',
        self::PROP_AUTHOR_URI => 'AuthorURI',
        self::PROP_DESCRIPTION => 'Description',
        self::PROP_DOMAIN_PATH => 'DomainPath',
        self::PROP_NAME => 'Name',
        self::PROP_TEXTDOMAIN => 'TextDomain',
        self::PROP_URI => 'PluginURI',
        self::PROP_VERSION => 'Version',
        self::PROP_REQUIRES_WP => 'RequiresWP',
        self::PROP_REQUIRES_PHP => 'RequiresPHP',

        // additional headers
        self::PROP_NETWORK => 'Network',
    ];

    /**
     * @param string $pluginMainFile
     *
     * @return PluginProperties
     */
    public static function for(string $pluginMainFile): PluginProperties
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginData = get_plugin_data($pluginMainFile);
        $properties = Properties::DEFAULT_PROPERTIES;

        foreach (self::PLUGIN_METHODS as $key => $pluginDataKey) {
            $properties[$key] = $pluginData[$pluginDataKey] ?? '';
        }

        $baseName = plugin_basename($pluginMainFile);
        $basePath = plugin_dir_path($pluginMainFile);
        $baseUrl = plugins_url('/', $pluginMainFile);

        return new self(
            $baseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * @return bool
     *
     * @psalm-suppress PossiblyFalseArgument
     */
    public function network(): bool
    {
        return (bool) $this->get(self::PROP_NETWORK, false);
    }
}
