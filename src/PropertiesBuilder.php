<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

final class PropertiesBuilder
{
    /**
     * Available methods of Properties::__call()
     * from plugin headers.
     *
     * @link https://developer.wordpress.org/reference/functions/get_plugin_data/
     */
    private const PLUGIN_METHODS = [
        'author' => 'Author',
        'authorUri' => 'AuthorURI',
        'description' => 'Description',
        'domainPath' => 'DomainPath',
        'name' => 'Name',
        'textDomain' => 'TextDomain',
        'uri' => 'PluginURI',
        'version' => 'Version',
        'requiresWp' => 'RequiresWP',
        'requiresPhp' => 'RequiresPHP',

        // additional headers
        'network' => 'Network',
    ];
    /**
     * Available methods of Properties::__call()
     * from theme headers.
     *
     * @link https://developer.wordpress.org/reference/classes/wp_theme/
     */
    private const THEME_METHODS = [
        'author' => 'Author',
        'authorUri' => 'Author URI',
        'description' => 'Description',
        'domainPath' => 'Domain Path',
        'name' => 'Theme Name',
        'textDomain' => 'Text Domain',
        'uri' => 'Theme URI',
        'version' => 'Version',
        'requiresWp' => 'RequiresWP',
        'requiresPhp' => 'RequiresPHP',

        // additional headers
        'status' => 'Status',
        'tags' => 'Tags',
        'template' => 'Template',
    ];

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $properties;

    /**
     * @param string $composerJsonFile
     *
     * @return PropertiesBuilder
     * @throws \Exception
     *
     * @psalm-suppress MixedArrayAccess
     */
    public static function forLibrary(string $composerJsonFile): PropertiesBuilder
    {
        if (!\is_file($composerJsonFile) || !\is_readable($composerJsonFile)) {
            throw new \Exception(sprintf('File %1$s does not exist or is not readable!', $composerJsonFile));
        }

        $content = (string) file_get_contents($composerJsonFile);
        $composerJsonData = json_decode($content, true);

        $packageNamePieces = explode('/', (string) $composerJsonData['name']);
        $baseName = count($packageNamePieces) < 2
            ? $packageNamePieces[0]
            : $packageNamePieces[1];

        $properties = [
            'description' => $composerJsonData['description'] ?? '',
            'tags' => $composerJsonData['keywords'] ?? [],
            // default author and authorUri
            'author' => '',
            'authorUri' => '',
        ];

        $authors = $composerJsonData['authors'] ?? [];
        $names = [];
        foreach ((array) $authors as $author) {
            $name = $author['name'] ?? null;
            if ($name) {
                $names[] = $name;
            }
            $url = $author['homepage'] ?? null;
            if ($url && !$properties['authorUri']) {
                $properties['authorUri'] = $url;
            }
        }
        if (count($names) > 0) {
            $properties['author'] = implode(', ', $names);
        }

        // Custom settings which can be stored in composer.json "extra.modularity"
        $extra = $composerJsonData['extra']['modularity'] ?? [];
        $extraKeys = ['domainPath', 'name', 'textDomain', 'uri', 'version'];
        foreach ($extraKeys as $key) {
            $properties[$key] = $extra[$key] ?? '';
        }

        // platform specific settings
        $platform = $composerJsonData['config']['platform'] ?? [];
        $platformMapping = ['php' => 'requiresPhp', 'wordpress' => 'requiresWp'];
        foreach ($platformMapping as $search => $mappedTo) {
            $properties[$mappedTo] = $platform[$search] ?? '';
        }

        return self::new(
            $baseName,
            dirname($composerJsonFile),
            Properties::TYPE_LIBRARY,
            $properties
        );
    }

    /**
     * @param string $pluginMainFile
     *
     * @return PropertiesBuilder
     */
    public static function forPlugin(string $pluginMainFile): PropertiesBuilder
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginData = get_plugin_data($pluginMainFile);
        $properties = [];
        foreach (self::PLUGIN_METHODS as $key => $pluginDataKey) {
            $properties[$key] = $pluginData[$pluginDataKey] ?? '';
        }
        $properties['baseUrl'] = plugins_url('/', $pluginMainFile);

        $baseName = plugin_basename($pluginMainFile);
        $basePath = plugin_dir_path($pluginMainFile);

        return self::new(
            $baseName,
            $basePath,
            Properties::TYPE_PLUGIN,
            $properties
        );
    }

    /**
     * @param string $themeDirectory
     *
     * @return PropertiesBuilder
     */
    public static function forTheme(string $themeDirectory): PropertiesBuilder
    {
        if (!function_exists('wp_get_theme')) {
            require_once ABSPATH . 'wp-includes/theme.php';
        }

        /** @var \WP_Theme $theme */
        $theme = wp_get_theme($themeDirectory);
        $properties = [];
        foreach (self::THEME_METHODS as $key => $themeKey) {
            /** @psalm-suppress DocblockTypeContradiction */
            $properties[$key] = $theme->get($themeKey) ?? '';
        }
        $properties['baseUrl'] = (string) trailingslashit($theme->get_stylesheet_directory_uri());

        $baseName = $theme->get_stylesheet();
        $basePath = $theme->get_template_directory();

        return self::new(
            $baseName,
            $basePath,
            Properties::TYPE_THEME,
            $properties
        );
    }

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string $type
     * @param array $properties
     *
     * @return PropertiesBuilder
     */
    public static function new(
        string $baseName,
        string $basePath,
        string $type = Properties::TYPE_LIBRARY,
        array $properties = []
    ): PropertiesBuilder {
        $properties['baseName'] = $baseName;
        $properties['basePath'] = $basePath;

        return new static($type, $properties);
    }

    /**
     * @param string $type
     * @param array $properties
     *
     * @see PropertiesBuilder::new()
     *
     */
    private function __construct(
        string $type,
        array $properties
    ) {
        $this->properties = $properties;
        $this->type = $type;
    }

    /**
     * Assign a new value to Properties.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return PropertiesBuilder
     */
    public function set(string $key, $value): PropertiesBuilder
    {
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * Add multiple key/value values to Properties.
     *
     * @param array<string, mixed> $properties
     *
     * @return PropertiesBuilder
     */
    public function add(array $properties): PropertiesBuilder
    {
        foreach ($properties as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @return PropertiesInterface
     */
    public function build(): PropertiesInterface
    {
        return Properties::new(
            (string) $this->properties['baseName'],
            (string) $this->properties['basePath'],
            $this->type,
            $this->properties
        );
    }
}
