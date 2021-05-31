<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class PackageTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $propertiesStub = $this->mockProperties($expectedName);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->statusIs(Package::STATUS_IDLE));
        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
        static::assertSame($expectedName, $package->name());
        static::assertInstanceOf(Properties::class, $package->properties());
        static::assertInstanceOf(ContainerInterface::class, $package->container());
        static::assertEmpty($package->modulesStatus()[Package::MODULES_ALL]);
    }

    /**
     * @param string $suffix
     * @param string $baseName
     * @param string $expectedHookName
     *
     * @test
     * @dataProvider provideHookNameSuffix
     */
    public function testHookName(string $suffix, string $baseName, string $expectedHookName): void
    {
        $propertiesStub = $this->mockProperties($baseName);
        $package = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $package->hookName($suffix));
    }

    /**
     * @return \Generator
     */
    public function provideHookNameSuffix(): \Generator
    {
        $expectedName = 'baseName';
        $baseHookName = 'inpsyde.modularity.' . $expectedName;
        yield 'no suffix' => [
            '',
            $expectedName,
            $baseHookName,
        ];

        yield 'failed boot' => [
            Package::ACTION_FAILED_BOOT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_FAILED_BOOT,
        ];

        yield 'init' => [
            Package::ACTION_INIT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_INIT,
        ];

        yield 'ready' => [
            Package::ACTION_READY,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_READY,
        ];
    }

    /**
     * @test
     */
    public function testBootWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->mockModule($expectedId);
        $propertiesStub = $this->mockProperties('name', false);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->boot($moduleStub));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_EXTENDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // booting again will do nothing.
        static::assertFalse($package->boot());
    }

    /**
     * @test
     */
    public function testBootWithServiceModule(): void
    {
        $moduleId = 'my-service-module';
        $serviceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class);
        $module->shouldReceive('services')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithFactoryModule(): void
    {
        $moduleId = 'my-factory-module';
        $factoryId = 'factory-id';

        $module = $this->mockModule($moduleId, FactoryModule::class);
        $module->shouldReceive('factories')->andReturn($this->stubServices($factoryId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($factoryId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithNonExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $extensionId = 'extension-id';

        $module = $this->mockModule($moduleId, ExtendingModule::class);
        $module->shouldReceive('extensions')->andReturn($this->stubServices($extensionId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        // false because extending a service not in container
        static::assertFalse($package->container()->has($extensionId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $serviceId = 'service-id';

        $module = $this->mockModule($moduleId, ServiceModule::class, ExtendingModule::class);
        $module->shouldReceive('services')->andReturn($this->stubServices($serviceId));
        $module->shouldReceive('extensions')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * Test if on Properties::isDebug() === false no Exception is thrown
     * and Boostrap::boot() returns false.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugFalse(): void
    {
        $exception = new \Exception("Catch me if you can!");

        $module = $this->mockModule('id', ExecutableModule::class);
        $module->shouldReceive('run')->andThrow($exception);

        $package = Package::new($this->mockProperties('basename', false));

        $failedHook = $package->hookName(Package::ACTION_FAILED_BOOT);
        Monkey\Actions\expectDone($failedHook)->once()->with($exception);

        static::assertFalse($package->boot($module));
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * Test if on Properties::isDebug() === true an Exception is thrown.
     *
     * @test
     */
    public function testBootWithThrowingModuleAndDebugTrue(): void
    {
        $exception = new \Exception("Catch me if you can!");

        $module = $this->mockModule('id', ExecutableModule::class);
        $module->shouldReceive('run')->andThrow($exception);

        $package = Package::new($this->mockProperties('basename', true));

        $failedHook = $package->hookName(Package::ACTION_FAILED_BOOT);
        Monkey\Actions\expectDone($failedHook)->once()->with($exception);

        $this->expectExceptionObject($exception);
        $package->boot($module);
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->mockModule($moduleId, ExecutableModule::class);
        $module->shouldReceive('run')->andReturn(true);

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $moduleId = 'executable-module';
        $module = $this->mockModule($moduleId, ExecutableModule::class);
        $module->shouldReceive('run')->andReturn(false);

        $package = Package::new($this->mockProperties());

        static::assertTrue($package->boot($module));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when multiple modules are added, and debug is true, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenDebug(): void
    {
        $emptyModule = $this->mockModule('empty');
        $emptyServicesModule = $this->mockModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->mockModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->mockModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->mockModule('service', ServiceModule::class);
        $servicesModule->shouldReceive('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->mockModule('factory', FactoryModule::class);
        $factoriesModule->shouldReceive('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->mockModule('extension', ExtendingModule::class);
        $extendingModule->shouldReceive('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->mockModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->shouldReceive('services')->andReturn($this->stubServices('MS1'));
        $multiModule->shouldReceive('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->shouldReceive('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->mockProperties('name', true))
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->boot($emptyModule, $extendingModule));

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'empty_factories ' . Package::MODULE_NOT_ADDED,
                'empty_extensions ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED . ' (S1, S2)',
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED . ' (MS1)',
                'multi ' . Package::MODULE_REGISTERED_FACTORIES . ' (MF1, MF2)',
                'multi ' . Package::MODULE_EXTENDED . ' (ME1, ME2)',
                'multi ' . Package::MODULE_ADDED,
                'factory ' . Package::MODULE_REGISTERED_FACTORIES . ' (F)',
                'factory ' . Package::MODULE_ADDED,
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED . ' (E)',
                'extension ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty_services',
                'empty_factories',
                'empty_extensions',
                'empty',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'multi',
                'extension',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
                'factory',
                'extension',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test, when multiple modules are added, and debug is false, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenNotDebug(): void
    {
        $emptyModule = $this->mockModule('empty');
        $emptyServicesModule = $this->mockModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->mockModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->mockModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->mockModule('service', ServiceModule::class);
        $servicesModule->shouldReceive('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->mockModule('factory', FactoryModule::class);
        $factoriesModule->shouldReceive('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->mockModule('extension', ExtendingModule::class);
        $extendingModule->shouldReceive('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->mockModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->shouldReceive('services')->andReturn($this->stubServices('MS1'));
        $multiModule->shouldReceive('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->shouldReceive('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->mockProperties('name', false))
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->boot($emptyModule, $extendingModule));

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'empty_factories ' . Package::MODULE_NOT_ADDED,
                'empty_extensions ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED,
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED,
                'multi ' . Package::MODULE_REGISTERED_FACTORIES,
                'multi ' . Package::MODULE_EXTENDED,
                'multi ' . Package::MODULE_ADDED,
                'factory ' . Package::MODULE_REGISTERED_FACTORIES,
                'factory ' . Package::MODULE_ADDED,
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED,
                'extension ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty_services',
                'empty_factories',
                'empty_extensions',
                'empty',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'multi',
                'extension',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
                'factory',
                'extension',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }
}
