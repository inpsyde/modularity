<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Properties;

class BaseProperties implements Properties
{
    /**
     * @var null|bool
     */
    protected $isDebug = null;

    /**
     * @var string
     */
    protected $baseName;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string|null
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @param string $baseName
     * @param string $basePath
     * @param string|null $baseUrl
     * @param array $properties
     */
    protected function __construct(
        string $baseName,
        string $basePath,
        string $baseUrl = null,
        array $properties = []
    ) {
        $baseName = self::sanitizeBaseName($baseName);
        $basePath = (string) trailingslashit($basePath);
        if ($baseUrl) {
            $baseUrl = (string) trailingslashit($baseUrl);
        }

        $this->baseName = $baseName;
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
        $this->properties = array_replace(Properties::DEFAULT_PROPERTIES, $properties);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected static function sanitizeBaseName(string $name): string
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
     * {@inheritDoc}
     */
    public function baseName(): string
    {
        return $this->baseName;
    }

    /**
     * {@inheritDoc}
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * {@inheritDoc}
     */
    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * {@inheritDoc}
     */
    public function author(): string
    {
        return (string) $this->get(self::PROP_AUTHOR);
    }

    /**
     * {@inheritDoc}
     */
    public function authorUri(): string
    {
        return (string) $this->get(self::PROP_AUTHOR_URI);
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return (string) $this->get(self::PROP_DESCRIPTION);
    }

    /**
     * {@inheritDoc}
     */
    public function textDomain(): string
    {
        return (string) $this->get(self::PROP_TEXTDOMAIN);
    }

    /**
     * {@inheritDoc}
     */
    public function domainPath(): string
    {
        return (string) $this->get(self::PROP_DOMAIN_PATH);
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return (string) $this->get(self::PROP_NAME);
    }

    /**
     * {@inheritDoc}
     */
    public function uri(): string
    {
        return (string) $this->get(self::PROP_URI);
    }

    /**
     * {@inheritDoc}
     */
    public function version(): string
    {
        return (string) $this->get(self::PROP_VERSION);
    }

    /**
     * {@inheritDoc}
     * @psalm-suppress MixedInferredReturnType, MixedReturnStatement
     */
    public function requiresWp(): ?string
    {
        return $this->get(self::PROP_REQUIRES_WP);
    }

    /**
     * {@inheritDoc}
     * @psalm-suppress MixedInferredReturnType, MixedReturnStatement
     */
    public function requiresPhp(): ?string
    {
        return $this->get(self::PROP_REQUIRES_PHP);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value)
    {
        if (isset(self::DEFAULT_PROPERTIES[$key])) {
            throw new class("The ${key} is a protected property and not allowed to change.") extends \InvalidArgumentException {
            };
        }

        $this->properties[$key] = $value;
    }

    /**
     * {@inheritdoc}
     * @psalm-suppress InvalidArgument
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
}