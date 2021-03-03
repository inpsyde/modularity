<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

/**
 * A FactoryModule allows you to register an array of factories
 * to your application container. Accessing a factory via Container::get
 * will always return a new instance.
 *
 * @package Inpsyde\Modularity\Module
 */
interface FactoryModule extends Module
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface $container):object>
     */
    public function factories(): array;
}