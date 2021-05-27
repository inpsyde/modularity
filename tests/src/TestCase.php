<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Properties\Properties;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @param string $basename
     * @param bool $isDebug
     *
     * @return Properties|MockInterface
     */
    protected function mockProperties(
        string $basename = 'basename',
        bool $isDebug = false
    ): Properties {

        $stub = \Mockery::mock(Properties::class);
        $stub->shouldReceive('basename')->andReturn($basename);
        $stub->shouldReceive('isDebug')->andReturn($isDebug);

        return $stub;
    }

    /**
     * @param string $id
     * @param class-string ...$interfaces
     * @return Module|MockInterface
     */
    protected function mockModule(string $id = 'module', string ...$interfaces): Module
    {
        in_array(Module::class, $interfaces, true) or $interfaces[] = Module::class;

        $stub = \Mockery::mock(...$interfaces);
        $stub->shouldReceive('id')->andReturn($id);

        if (in_array(ServiceModule::class, $interfaces, true) ) {
            $stub->shouldReceive('services')->byDefault()->andReturn([]);
        }

        if (in_array(FactoryModule::class, $interfaces, true) ) {
            $stub->shouldReceive('factories')->byDefault()->andReturn([]);
        }

        if (in_array(ExtendingModule::class, $interfaces, true) ) {
            $stub->shouldReceive('extensions')->byDefault()->andReturn([]);
        }

        if (in_array(ExecutableModule::class, $interfaces, true) ) {
            $stub->shouldReceive('run')->byDefault()->andReturn(false);
        }

        return $stub;
    }

    /**
     * @param string ...$ids
     * @return array<string, callable>
     */
    protected function stubServices(string ...$ids): array
    {
        $services = [];
        foreach ($ids as $id) {
            $services[$id] = static function () use ($id) {
                return new \ArrayObject(['id' => $id]);
            };
        }

        return $services;
    }
}
