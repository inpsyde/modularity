<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Inpsyde\Modularity\Package;
use Psr\Container\ContainerExceptionInterface;
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
     * @throws \Exception
     */
    public function get(string $id)
    {
        $this->assertPackageBooted($id);

        return $this->container->get($id);
    }

    /**
     * @param string $id
     * @return bool
     *
     * @throws \Exception
     */
    public function has(string $id): bool
    {
        return $this->tryContainer() && $this->container->has($id);
    }

    /**
     * @return bool
     *
     * @throws \Exception
     * @psalm-assert-if-true ContainerInterface $this->container
     */
    private function tryContainer(): bool
    {
        if ($this->container) {
            return true;
        }

        /** TODO: We need a better way to deal with status checking besides equality */
        if (
            $this->package->statusIs(Package::STATUS_READY)
            || $this->package->statusIs(Package::STATUS_BOOTED)
        ) {
            $this->container = $this->package->container();
        }

        return (bool)$this->container;
    }

    /**
     * @param string $id
     * @return void
     *
     * @throws \Exception
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
            ? 'is errored'
            : 'is not ready yet';

        throw new class ("Error retrieving service {$id} because package {$name} {$status}.")
            extends \Exception
            implements ContainerExceptionInterface {
        };
    }
}
