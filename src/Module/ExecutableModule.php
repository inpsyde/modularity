<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

use Psr\Container\ContainerInterface;

/**
 * An ExecutableModule is meant to executed services after they
 * are registered to the application container. This is the best chance to add some
 * hooks to WordPress or listen to them.
 *
 * @package Inpsyde\Modularity\Module
 */
interface ExecutableModule extends Module
{

    /**
     * @param ContainerInterface $container
     *
     * @return bool     true when successfully booted, otherwhise false.
     */
    public function run(ContainerInterface $container): bool;
}
