<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;

/**
 * @deprecated Use ReadOnlyContainerCompiler instead.
 */
class ContainerConfigurator extends ReadOnlyContainerCompiler
{
    /**
     * @param list<ContainerInterface> $containers
     */
    public function __construct(array $containers = [])
    {
        parent::__construct(...$containers);
    }

    /**
     * @return ContainerInterface
     *
     * @deprecated Use ReadOnlyContainerCompiler::compile() instead.
     */
    public function createReadOnlyContainer(): ContainerInterface
    {
        return $this->compile();
    }
}
