<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReadOnlyContainer implements ContainerInterface
{
    /**
     * @var array<string, callable(\Psr\Container\ContainerInterface $container):mixed>
     */
    private $services;

    /**
     * @var array<string, bool>
     */
    private $factoryIds;

    /**
     * @var ServiceExtensions
     */
    private $extensions;

    /**
     * Resolved factories.
     *
     * @var array<string, mixed>
     */
    private $resolvedServices = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers;

    /**
     * ReadOnlyContainer constructor.
     *
     * @param array<string, callable(ContainerInterface $container):mixed> $services
     * @param array<string, bool> $factoryIds
     * @param ServiceExtensions $extensions
     * @param ContainerInterface[] $containers
     */
    public function __construct(
        array $services,
        array $factoryIds,
        ServiceExtensions $extensions,
        array $containers
    ) {
        $this->services = $services;
        $this->factoryIds = $factoryIds;
        $this->extensions = $extensions;
        $this->containers = $containers;
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->resolvedServices)) {
            return $this->resolvedServices[$id];
        }

        if (array_key_exists($id, $this->services)) {
            $service = $this->services[$id]($this);
            $resolved = $this->extensions->resolve($service, $id, $this);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }

            return $resolved;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                $service = $container->get($id);

                return $this->extensions->resolve($service, $id, $this);
            }
        }

        throw new class ("Service with ID {$id} not found.")
            extends \Exception
            implements NotFoundExceptionInterface {
        };
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return true;
        }

        if (array_key_exists($id, $this->resolvedServices)) {
            return true;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }
}
