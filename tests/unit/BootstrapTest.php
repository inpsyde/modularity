<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Inpsyde\Modularity\Bootstrap;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\PropertiesInterface;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class BootstrapTest extends TestCase
{

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBasic()
    {
        $expectedName = 'foo';
        $propertiesStub = $this->mockProperties($expectedName);

        $testee = Bootstrap::new($propertiesStub);

        static::assertTrue($testee->statusIs(Bootstrap::STATUS_IDLE));
        static::assertTrue($testee->boot());
        static::assertTrue($testee->statusIs(Bootstrap::STATUS_BOOTED));
        static::assertSame($expectedName, $testee->name());
        static::assertInstanceOf(PropertiesInterface::class, $testee->properties());
        static::assertInstanceOf(ContainerInterface::class, $testee->container());
        static::assertEmpty($testee->modulesStatus()['_all']);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBootWithModule()
    {
        $expectedModuleId = 'my-module';

        $moduleStub = $this->mockModule($expectedModuleId);
        $propertiesStub = $this->mockProperties();
        $propertiesStub->expects('isDebug')->andReturn(false);

        $testee = Bootstrap::new($propertiesStub);

        static::assertTrue($testee->boot($moduleStub));
        static::assertFalse($testee->moduleIs($expectedModuleId, Bootstrap::MODULE_ADDED));

        // booting again will do nothing.
        static::assertFalse($testee->boot());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBootWithServiceModule()
    {
        $serviceModuleId = 'my-service-module';
        $serviceId = 'service-id';

        $moduleStub = $this->mockServiceModule($serviceModuleId);
        $moduleStub
            ->shouldReceive('services')
            ->andReturn(
                [
                    $serviceId => function () {
                        return new class() {

                            public function __toString()
                            {
                                return 'bar';
                            }
                        };
                    },
                ]
            );

        $propertiesStub = $this->mockProperties();

        $testee = Bootstrap::new($propertiesStub);

        static::assertTrue($testee->boot($moduleStub));
        static::assertTrue($testee->moduleIs($serviceModuleId, Bootstrap::MODULE_ADDED));
        static::assertTrue($testee->moduleIs($serviceModuleId, Bootstrap::MODULE_REGISTERED));
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function testBootWithExtendingModule()
    {
        $extendingModuleId = 'my-extending-module';
        $serviceId = 'service-1';

        $extendingModuleStub = $this->mockExtendingModule($extendingModuleId);
        $extendingModuleStub
            ->shouldReceive('extensions')
            ->andReturn(
                [
                    $serviceId => function () {
                        return 'foo';
                    },
                ]
            );

        $propertiesStub = $this->mockProperties();

        $testee = Bootstrap::new($propertiesStub);

        static::assertTrue($testee->boot($extendingModuleStub));
        static::assertTrue($testee->moduleIs($extendingModuleId, Bootstrap::MODULE_ADDED));
        static::assertTrue($testee->moduleIs($extendingModuleId, Bootstrap::MODULE_EXTENDED));
    }

    /**
     * @test
     */
    public function testAddContainer(): void
    {
        $childContainer = new class implements ContainerInterface {

            public function get($id)
            {
                return $id;
            }

            public function has($id)
            {
                return $id;
            }
        };

        $properties = $this->mockProperties();
        $testee = Bootstrap::new($properties);
        $testee->addContainer($childContainer);
    }

    /**
     * @test
     */
    public function testAddContainerAfterBooted(): void
    {
        static::expectException(\Exception::class);

        $childContainer = new class implements ContainerInterface {

            public function get($id)
            {
                return $id;
            }

            public function has($id)
            {
                return $id;
            }
        };
        $properties = $this->mockProperties();
        $testee = Bootstrap::new($properties);
        $testee->boot();
        $testee->addContainer($childContainer);
    }

    /**
     * @test
     */
    public function testContainerAccessTooEarly(): void
    {
        static::expectException(\Exception::class);

        $properties = $this->mockProperties();
        $testee = Bootstrap::new($properties);
        $testee->container();
    }

    /**
     * Test if on Properties::isDebug() === false no Exception is thrown
     * and Boostrap::boot() returns false.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugFalse(): void
    {
        $throwingModule = new class implements ExecutableModule {

            use ModuleClassNameIdTrait;

            public function run(ContainerInterface $container): bool
            {
                throw new \Exception("Catch me if you can!");
            }
        };

        $properties = $this->mockProperties();
        $properties->expects('isDebug')->andReturn(false);
        $testee = Bootstrap::new($properties);

        static::assertFalse($testee->boot($throwingModule));
        static::assertTrue($testee->statusIs(Bootstrap::STATUS_FAILED_BOOT));
    }

    /**
     * Test if on Properties::isDebug() === true an Exception is thrown.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugTrue(): void
    {
        static::expectException(\Exception::class);

        $throwingModule = new class implements ExecutableModule {

            use ModuleClassNameIdTrait;

            public function run(ContainerInterface $container): bool
            {
                throw new \Exception("Catch me if you can!");
            }
        };

        $properties = $this->mockProperties();
        $properties->expects('isDebug')->andReturn(true);
        Bootstrap::new($properties)->boot($throwingModule);
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $serviceId = 'executable-module';
        $executableModule = $this->mockExecutableModule($serviceId);
        $executableModule->shouldReceive('run')
            ->andReturn(true);

        $properties = $this->mockProperties();

        $testee = Bootstrap::new($properties);

        static::assertTrue($testee->boot($executableModule));
        static::assertTrue($testee->moduleIs($serviceId, Bootstrap::MODULE_EXECUTED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $serviceId = 'executable-module';
        $executableModule = $this->mockExecutableModule($serviceId);
        $executableModule->shouldReceive('run')
            ->andReturn(false);

        $properties = $this->mockProperties();
        $testee = Bootstrap::new($properties);

        static::assertTrue($testee->boot($executableModule));
        static::assertTrue($testee->moduleIs($serviceId, Bootstrap::MODULE_EXECUTION_FAILED));
    }
}
