<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReadOnlyContainer implements ContainerInterface
{
    /**
     * @var array<string, callable(\Psr\Container\ContainerInterface $container):object>
     */
    private $services;

    /**
     * @var array<string, bool>
     */
    private $factoryIds = [];

    /**
     * @var array<string, array<callable(object, ContainerInterface $container):object>>
     */
    private $extensions;

    /**
     * Resolved factories.
     *
     * @var array<string, object>
     */
    private $resolvedServices = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers;

    /**
     * ReadOnlyContainer constructor.
     *
     * @param array<string, callable(ContainerInterface $container):object> $services
     * @param array<string, bool> $factoryIds
     * @param array<string, array<callable(object, ContainerInterface $container):object>> $extensions
     * @param ContainerInterface[] $containers
     */
    public function __construct(
        array $services,
        array $factoryIds,
        array $extensions,
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
     * @return object
     *
     * @psalm-suppress MissingReturnType
     * @psalm-suppress MissingParamType
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedInferredReturnType
     */
    public function get($id)
    {
        assert(is_string($id));

        if (array_key_exists($id, $this->resolvedServices)) {
            return $this->resolvedServices[$id];
        }

        if (array_key_exists($id, $this->services)) {
            $service = $this->services[$id]($this);
            $resolved = $this->resolveExtensions($id, $service);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $resolved;
                unset($this->services[$id]);
            }

            return $resolved;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                /** @var object $service */
                $service = $container->get($id);

                return $this->resolveExtensions($id, $service);
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
    public function has($id)
    {
        assert(is_string($id));

        if (array_key_exists($id, $this->services)) {
            return true;
        }

        if (array_key_exists($id, $this->resolvedServices)) {
            return true;
        };

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $id
     * @param object $service
     *
     * @return object
     */
    private function resolveExtensions(string $id, object $service): object
    {
        if (!isset($this->extensions[$id])) {
            return $service;
        }

        foreach ($this->extensions[$id] as $extender) {
            $service = $extender($service, $this);
        }

        return $service;
    }
}