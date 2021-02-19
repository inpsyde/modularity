<?php # -*- coding: utf-8 -*-

namespace Inpsyde\Modularity;

interface PropertiesInterface
{
    /**
     * Type of Properties
     */
    public const TYPE_PLUGIN = 'plugin';
    public const TYPE_THEME = 'theme';
    public const TYPE_LIBRARY = 'library';

    /**
     * @param string $key
     * @param null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    public function isType(string $type): bool;
    public function isLibrary(): bool;
    public function isPlugin(): bool;
    public function isTheme(): bool;
    public function isDebug(): bool;
    public function baseName(): string;
    public function basePath(): string;

}