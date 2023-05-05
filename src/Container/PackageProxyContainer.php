<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Exception\ContainerException;
use Inpsyde\Modularity\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

class PackageProxyContainer implements ContainerInterface
{
    /**
     * @var Package
     */
    private $package;

    /**
     * @var ContainerInterface|null
     */
    private $container;

    /**
     * @param Package $package
     */
    public function __construct(Package $package)
    {
        $this->package = $package;
    }

    /**
     * @param string $id
     * @return mixed
     *
     * @throws ContainerException | NotFoundException
     */
    public function get(string $id)
    {
        assert(is_string($id));
        $this->assertPackageBooted($id);

        return $this->container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     *
     * @throws ContainerException
     */
    public function has(string $id): bool
    {
        assert(is_string($id));

        return $this->tryContainer() && $this->container->has($id);
    }

    /**
     * @return bool
     *
     * @throws ContainerException
     * @psalm-assert-if-true ContainerInterface $this->container
     */
    private function tryContainer(): bool
    {
        if ($this->container) {
            return true;
        }

        if ($this->package->statusIs(Package::STATUS_BOOTED)) {
            $this->container = $this->package->container();
        }

        return (bool)$this->container;
    }

    /**
     * @param string $id
     * @return void
     *
     * @throws ContainerException
     *
     * @psalm-assert ContainerInterface $this->container
     */
    private function assertPackageBooted(string $id): void
    {
        if ($this->tryContainer()) {
            return;
        }

        $name = $this->package->name();
        $status = $this->package->statusIs(Package::STATUS_FAILED)
            ? 'failed booting'
            : 'is not booted yet';

        throw new ContainerException("Error retrieving service {$id} because package {$name} {$status}.");
    }
}
