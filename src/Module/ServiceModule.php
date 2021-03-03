<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

/**
 * A ServiceModule allows you to register an array of services
 * to your application container. Services accessed via Container::get()
 * will only be resolved once.
 *
 * @package Inpsyde\Modularity\Module
 */
interface ServiceModule extends Module
{

    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface $container):object>
     */
    public function services(): array;
}
