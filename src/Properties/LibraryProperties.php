<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Properties;

/**
 * Class LibraryProperties
 *
 * @package Inpsyde\Modularity\Properties
 */
class LibraryProperties extends BaseProperties
{
    /**
     * Additional properties specific for libraries.
     */
    public const PROP_TAGS = 'tags';
    /**
     * Allowed configuration in composer.json "extra.modularity".
     *
     * @var array
     */
    public const EXTRA_KEYS = [
        self::PROP_DOMAIN_PATH,
        self::PROP_NAME,
        self::PROP_TEXTDOMAIN,
        self::PROP_URI,
        self::PROP_VERSION,
        self::PROP_REQUIRES_WP,
    ];

    /**
     * @param string $composerJsonFile
     *
     * @return LibraryProperties
     *
     * @throws \Exception
     * @psalm-suppress MixedArrayAccess
     */
    public static function new(string $composerJsonFile): LibraryProperties
    {
        if (!\is_file($composerJsonFile) || !\is_readable($composerJsonFile)) {
            throw new \Exception(sprintf('File %1$s does not exist or is not readable!', $composerJsonFile));
        }

        $content = (string) file_get_contents($composerJsonFile);
        /** @var array $composerJsonData */
        $composerJsonData = json_decode($content, true);

        $properties = Properties::DEFAULT_PROPERTIES;
        $properties[self::PROP_DESCRIPTION] = $composerJsonData['description'] ?? '';
        $properties[self::PROP_TAGS] = $composerJsonData['keywords'] ?? [];

        $authors = $composerJsonData['authors'] ?? [];
        $names = [];
        foreach ((array) $authors as $author) {
            $name = $author['name'] ?? null;
            if ($name) {
                $names[] = $name;
            }
            $url = $author['homepage'] ?? null;
            if ($url && !$properties['authorUri']) {
                $properties[self::PROP_AUTHOR_URI] = $url;
            }
        }
        if (count($names) > 0) {
            $properties[self::PROP_AUTHOR] = implode(', ', $names);
        }

        // Custom settings which can be stored in composer.json "extra.modularity"
        $extra = $composerJsonData['extra']['modularity'] ?? [];
        foreach (self::EXTRA_KEYS as $key) {
            $properties[$key] = $extra[$key] ?? '';
        }

        // requiresPhp in "require.php" or "require-dev.php"
        $properties[self::PROP_REQUIRES_PHP] = self::extractPhpVersion($composerJsonData);

        // composer.json might has "version" in root
        $version = $composerJsonData['version'] ?? null;
        if ($version) {
            $properties[self::PROP_VERSION] = $version;
        }

        $baseName = self::buildBaseName((string) $composerJsonData['name']);
        $basePath = dirname($composerJsonFile);
        $baseUrl = null;

        return new self(
            $baseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * @param string $packageName
     *
     * @return string
     */
    private static function buildBaseName(string $packageName): string
    {
        $packageNamePieces = explode('/', $packageName, 2);

        return implode('-', $packageNamePieces);
    }

    /**
     * Check PHP version in require, require-dev.
     *
     * Attempt to parse requirements to find the _minimum_ accepted version (consistent with WP).
     * Composer requirements are parsed in a way that, for example:
     * `>=7.2`        returns `7.2`
     * `^7.3`         returns `7.3`
     * `5.6 || >= 7.1` returns `5.6`
     * `>= 7.1 < 8`   returns `7.1`
     *
     * @param array $composerData
     * @param string $key
     *
     * @return string|null
     */
    private static function extractPhpVersion(array $composerData, string $key = 'require'): ?string
    {
        $nextKey = ($key === 'require')
            ? 'require-dev'
            : null;
        $base = (array) ($composerData[$key] ?? []);
        $requirement = $base['php'] ?? null;
        $version = ($requirement && is_string($requirement))
            ? trim($requirement)
            : null;
        if (!$version) {
            return $nextKey
                ? self::extractPhpVersion($composerData, $nextKey)
                : null;
        }

        static $matcher;
        $matcher or $matcher = static function (string $version): ?string {
            $version = trim($version);
            if (!$version) {
                return null;
            }

            // versions range like `>= 7.2.4 < 8`
            if (preg_match('{>=?([\s0-9\.]+)<}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            // aliases like `dev-src#abcde as 7.4`
            if (preg_match('{as\s*([\s0-9\.]+)}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            // Basic requirements like 7.2, >=7.2, ^7.2, ~7.2
            if (preg_match('{^(?:[>=\s~\^]+)?([0-9\.]+)}', $version, $matches)) {
                return trim($matches[1], " \t\n\r\0\x0B.");
            }

            return null;
        };

        // support for simpler requirements like `7.3`, `>=7.4` or alternative like `5.6 || >=7`

        $alternatives = explode('||', $version);
        $found = null;
        foreach ($alternatives as $alternative) {
            /** @var callable(string):?string $matcher */
            $itemFound = $matcher($alternative);
            if ($itemFound && (!$found || version_compare($itemFound, $found, '<'))) {
                $found = $itemFound;
            }
        }

        if ($found) {
            return $found;
        }

        return $nextKey
            ? self::extractPhpVersion($composerData, $nextKey)
            : null;
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return (array) $this->get(self::PROP_TAGS);
    }
}