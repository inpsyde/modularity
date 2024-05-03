<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @psalm-import-type Service from \Inpsyde\Modularity\Module\ServiceModule
 * @psalm-import-type ExtendingService from \Inpsyde\Modularity\Module\ExtendingModule
 */
class ReadOnlyContainer implements ContainerInterface
{
    /**
     * @var array<string, Service>
     */
    private $services;

    /**
     * @var array<string, bool>
     */
    private $factoryIds;

    /**
     * @var array<string, array<ExtendingService>>
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
     * @param array<string, Service> $services
     * @param array<string, bool> $factoryIds
     * @param array<string, array<ExtendingService>> $extensions
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
     * @return mixed
     */
    public function get(string $id)
    {
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

    /**
     * @param string $id
     * @param mixed $service
     *
     * @return mixed
     */
    private function resolveExtensions(string $id, $service)
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
