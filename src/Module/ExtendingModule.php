<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Module;

/**
 * An ExtendingModule allows you to return an array of extensions mapped by service key
 * to wrap those into a new object.
 *
 * @package Inpsyde\Modularity\Module
 */
interface ExtendingModule extends Module
{

    /**
     * @return array<string, callable(object $object, \Psr\Container\ContainerInterface $container):object>
     */
    public function extensions(): array;
}
