<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{

    /**
     * @var array<string, callable(\Psr\Container\ContainerInterface $container):object>
     */
    private $factories;

    /**
     * @var array<string, array<callable(object, ContainerInterface $container):object>>
     */
    private $extensions;

    /**
     * Resolved factories.
     *
     * @var array<string, object>
     */
    private $services = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers;

    /**
     * ReadOnlyContainer constructor.
     *
     * @param array<string, callable(ContainerInterface $container):object> $factories
     * @param array<string, array<callable(object, ContainerInterface $container):object>> $extensions
     * @param ContainerInterface[] $containers
     */
    public function __construct(
        array $factories,
        array $extensions,
        array $containers
    ) {
        $this->factories = $factories;
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

        if (array_key_exists($id, $this->factories)) {
            $service = $this->factories[$id]($this);
            $this->services[$id] = $this->resolveExtensions($id, $service);
            unset($this->factories[$id]);
        }

        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                /** @var object $service */
                $service = $container->get($id);
                $this->services[$id] = $this->resolveExtensions($id, $service);

                return $this->services[$id];
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

        if (array_key_exists($id, $this->factories)) {
            return true;
        }

        if (array_key_exists($id, $this->services)) {
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
        if (! isset($this->extensions[$id])) {
            return $service;
        }

        foreach ($this->extensions[$id] as $extender) {
            $service = $extender($service, $this);
        }

        return $service;
    }
}
