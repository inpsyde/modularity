<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;

interface ContainerCompiler
{
    /**
     * @param ContainerInterface $container
     * @return void
     */
    public function addContainer(ContainerInterface $container): void;

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):mixed $factory
     * @return void
     */
    public function addFactory(string $id, callable $factory): void;

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):mixed $service
     * @return void
     */
    public function addService(string $id, callable $service): void;

    /**
     * @param string $id
     * @return bool
     */
    public function hasService(string $id): bool;

    /**
     * @param string $id
     * @param callable(mixed $service, ContainerInterface $container):mixed $extender
     * @return void
     */
    public function addExtension(string $id, callable $extender): void;

    /**
     * @param string $id
     * @return bool
     */
    public function hasExtension(string $id): bool;

    /**
     * @return ContainerInterface
     */
    public function compile(): ContainerInterface;
}
