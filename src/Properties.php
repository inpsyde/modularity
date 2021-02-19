<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

/**
 * @method string author()
 * @method string authorUri()
 * @method string description()
 * @method string domainPath()
 * @method string name()
 * @method string textDomain()
 * @method string uri()
 * @method string version()
 * @method string requiresWp()
 * @method string requiresPhp()
 * @method string|null baseUrl()
 */
class Properties implements PropertiesInterface, \IteratorAggregate, \Countable
{

    /**
     * @var null|bool
     */
    private $isDebug = null;

    /**
     * @var string
     */
    private $baseName;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $properties;

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string $type
     * @param array $properties
     *
     * @return Properties
     */
    public static function new(
        string $baseName,
        string $basePath,
        string $type,
        array $properties
    ): Properties {
        $baseName = self::sanitizeBaseName($baseName);
        $basePath = (string) trailingslashit($basePath);

        return new self(
            $baseName,
            $basePath,
            $type,
            $properties
        );
    }

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string $type
     * @param array $properties
     *
     * @see Properties::new()
     *
     */
    private function __construct(
        string $baseName,
        string $basePath,
        string $type,
        array $properties
    ) {
        $this->baseName = $baseName;
        $this->basePath = $basePath;
        $this->properties = $properties;
        $this->type = $type;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private static function sanitizeBaseName(string $name): string
    {
        substr_count($name, '/') and $name = dirname($name);

        $name = strtolower(
            pathinfo(
                $name,
                PATHINFO_FILENAME
            )
        );

        return $name;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->get($name);
    }

    /**
     * @return string
     */
    public function baseName(): string
    {
        return $this->baseName;
    }

    /**
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param string $type
     *
     * @return bool
     * @see Properties::TYPE_*
     *
     */
    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * @return bool
     */
    public function isLibrary(): bool
    {
        return $this->isType(self::TYPE_LIBRARY);
    }

    /**
     * @return bool
     */
    public function isPlugin(): bool
    {
        return $this->isType(self::TYPE_PLUGIN);
    }

    /**
     * @return bool
     */
    public function isTheme(): bool
    {
        return $this->isType(self::TYPE_THEME);
    }

    /**
     * @return bool
     * @see Properties::isDebug()
     */
    public function isDebug(): bool
    {
        if ($this->isDebug === null) {
            $this->isDebug = defined('WP_DEBUG') && WP_DEBUG;
        }

        return $this->isDebug;
    }

    /**
     * Returns an iterator for properties.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * Returns the number of parameters.
     *
     * @return int The number of properties
     */
    public function count()
    {
        return \count($this->properties);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return isset($this->properties[$key]);
    }
}
