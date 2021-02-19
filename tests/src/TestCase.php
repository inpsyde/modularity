<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\PropertiesInterface;
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
     *
     * @return PropertiesInterface|MockInterface
     */
    protected function mockProperties(string $basename = 'basename')
    {
        $stub = \Mockery::mock(PropertiesInterface::class);
        $stub
            ->shouldReceive('basename')
            ->andReturn($basename);

        return $stub;
    }

    /**
     * @param string $id
     *
     * @return Module|MockInterface
     */
    protected function mockModule(string $id = 'module')
    {
        $stub = \Mockery::mock(Module::class);
        $stub
            ->shouldReceive('id')
            ->andReturn($id);

        return $stub;
    }

    /**
     * @param string $id
     *
     * @return ServiceModule|MockInterface
     */
    protected function mockServiceModule(string $id = 'service-module')
    {
        $stub = \Mockery::mock(ServiceModule::class);
        $stub
            ->shouldReceive('id')
            ->andReturn($id);

        return $stub;
    }

    /**
     * @param string $id
     *
     * @return ExtendingModule|MockInterface
     */
    protected function mockExtendingModule(string $id = 'extending-module')
    {
        $stub = \Mockery::mock(ExtendingModule::class);
        $stub
            ->shouldReceive('id')
            ->andReturn($id);

        return $stub;
    }

    /**
     * @param string $id
     *
     * @return ExecutableModule|MockInterface
     */
    protected function mockExecutableModule(string $id = 'executable-module')
    {
        $stub = \Mockery::mock(ExecutableModule::class);
        $stub
            ->shouldReceive('id')
            ->andReturn($id);

        return $stub;
    }
}
