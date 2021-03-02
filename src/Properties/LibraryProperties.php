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
        $composerJsonData = json_decode($content, true);

        $properties = Properties::DEFAULT_PROPERTIES;
        $properties[self::PROP_DESCRIPTION] = $composerJsonData['description'] ?? '';
        $properties['tags'] = $composerJsonData['keywords'] ?? [];

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

        // requiresPhp in config.platform.php
        $requiresPhp = $composerJsonData['config']['platform']['php'] ?? null;
        if ($requiresPhp) {
            $properties[self::PROP_REQUIRES_PHP] = $requiresPhp;
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
        $packageNamePieces = explode('/', $packageName);

        return count($packageNamePieces) < 2
            ? $packageNamePieces[0]
            : $packageNamePieces[1];
    }
}
