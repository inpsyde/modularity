<?php
declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class ContainerConfigurator
{
    /**
     * @var array<string, callable(ContainerInterface $container):object>
     */
    private $services = [];

    /**
     * @var array<string, bool>
     */
    private $factoryIds = [];

    /**
     * @var array<string, array<callable(object, ContainerInterface $container):object>>
     */
    private $extensions = [];

    /**
     * @var ContainerInterface[]
     */
    private $containers = [];

    /**
     * @var null|ContainerInterface
     */
    private $compiledContainer;

    /**
     * ContainerConfigurator constructor.
     *
     * @param ContainerInterface[] $containers
     */
    public function __construct(array $containers = [])
    {
        array_map([$this, 'addContainer'], $containers);
    }

    /**
     * Allowing to add child containers.
     *
     * @param ContainerInterface $container
     */
    public function addContainer(ContainerInterface $container): void
    {
        $this->containers[] = $container;
    }

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):object $factory
     */
    public function addFactory(string $id, callable $factory): void
    {
        $this->addService($id, $factory);
        $this->factoryIds[$id] = true;
    }

    /**
     * @param string $id
     * @param callable(ContainerInterface $container):object $service
     *
     * @return void
     */
    public function addService(string $id, callable $service): void
    {
        if ($this->hasService($id)) {
            throw new class ("Service with ID {$id} is already registered.")
                extends \Exception
                implements ContainerExceptionInterface {
            };
        }

        $this->services[$id] = $service;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasService(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
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
     * @param callable(object $object, ContainerInterface $container):object $extender
     *
     * @return void
     */
    public function addExtension(string $id, callable $extender): void
    {
        if (!isset($this->extensions[$id])) {
            $this->extensions[$id] = [];
        }

        $this->extensions[$id][] = $extender;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasExtension(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /**
     * Returns a read only version of this Container.
     *
     * @return ContainerInterface
     */
    public function createReadOnlyContainer(): ContainerInterface
    {
        if (!$this->compiledContainer) {
            $this->compiledContainer = new ReadOnlyContainer(
                $this->services,
                $this->factoryIds,
                $this->extensions,
                $this->containers
            );
        }

        return $this->compiledContainer;
    }
}