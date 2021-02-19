<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

/**
 * A ServiceModule allows you to register an array of services
 * to your application container.
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
