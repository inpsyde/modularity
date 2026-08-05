<?php

declare(strict_types=1);

namespace Syde\Modularity\Module;

/**
 * @package Syde\Modularity\Module
 */
interface Module
{
    /**
     * Unique identifier for your Module.
     *
     * @return string
     */
    public function id(): string;
}
