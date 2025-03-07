<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
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
        $propertiesStub = $this->stubProperties($expectedName);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->statusIs(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_DONE));

        $package->build();

        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_DONE));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(static function (Package $package): void {
                static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
            });

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_DONE));
        static::assertFalse($package->hasReachedStatus(6));
        // @phpstan-ignore classConstant.deprecated (check backward compatibility with deprecated constant)
        static::assertTrue($package->hasReachedStatus(Package::STATUS_MODULES_ADDED));

        static::assertSame($expectedName, $package->name());
        static::assertInstanceOf(Properties::class, $package->properties());
        static::assertInstanceOf(ContainerInterface::class, $package->container());
        static::assertEmpty($package->modulesStatus()[Package::MODULES_ALL]);
    }

    /**
     * @test
     * @dataProvider provideHookNameSuffix
     *
     * @param string $suffix
     * @param string $baseName
     * @param string $expectedHookName
     */
    public function testHookName(string $suffix, string $baseName, string $expectedHookName): void
    {
        $propertiesStub = $this->stubProperties($baseName);
        $package = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $package->hookName($suffix));
    }

    /**
     * @return \Generator
     */
    public static function provideHookNameSuffix(): \Generator
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

        yield 'booted' => [
            Package::ACTION_BOOTED,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_BOOTED,
        ];
    }

    /**
     * @test
     */
    public function testBootWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->stubModule($expectedId);
        $propertiesStub = $this->stubProperties('name', true);

        $package = Package::new($propertiesStub)->addModule($moduleStub);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_EXTENDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // booting again return false, but we expect no breakage
        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }

    /**
     * @test
     */
    public function testBuildWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->stubModule($expectedId);
        $propertiesStub = $this->stubProperties('name', true);

        $package = Package::new($propertiesStub)->addModule($moduleStub);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_EXTENDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // building again we expect no breakage
        $package->build()->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
    }

    /**
     * @test
     */
    public function testBootWithServiceModule(): void
    {
        $moduleId = 'module_test';
        $serviceId = 'service_test';

        $package = $this->stubSimplePackage('test');

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
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
    public function testBuildWithServiceModule(): void
    {
        $moduleId = 'module_test';
        $serviceId = 'service_test';

        $package = $this->stubSimplePackage('test');

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
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

        $module = $this->stubModule($moduleId, FactoryModule::class);
        $module->expects('factories')->andReturn($this->stubServices($factoryId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
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
    public function testBuildWithFactoryModule(): void
    {
        $moduleId = 'my-factory-module';
        $factoryId = 'factory-id';

        $module = $this->stubModule($moduleId, FactoryModule::class);
        $module->expects('factories')->andReturn($this->stubServices($factoryId));

        $package = Package::new($this->stubProperties())->addModule($module);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
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

        $module = $this->stubModule($moduleId, ExtendingModule::class);
        $module->expects('extensions')->andReturn($this->stubServices($extensionId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
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
    public function testBuildWithExtendingModuleWithNonExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $extensionId = 'extension-id';

        $module = $this->stubModule($moduleId, ExtendingModule::class);
        $module->expects('extensions')->andReturn($this->stubServices($extensionId));

        $package = Package::new($this->stubProperties())->addModule($module);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
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

        $module = $this->stubModule($moduleId, ServiceModule::class, ExtendingModule::class);
        $module->expects('services')->andReturn($this->stubServices($serviceId));
        $module->expects('extensions')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBuildWithExtendingModuleWithExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $serviceId = 'service-id';

        $module = $this->stubModule($moduleId, ServiceModule::class, ExtendingModule::class);
        $module->expects('services')->andReturn($this->stubServices($serviceId));
        $module->expects('extensions')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->stubProperties())->addModule($module);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(true);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * @test
     */
    public function testBuildWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->never();

        $package = Package::new($this->stubProperties())->addModule($module);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
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
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(false);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testBootPassingModulesEmitDeprecation(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->allows('services')->andReturn($this->stubServices('service_1'));

        $package = Package::new($this->stubProperties('test', true));

        $this->convertDeprecationsToExceptions();
        try {
            $count = 0;
            $package->boot($module1);
        } catch (\Throwable $throwable) {
            $count++;
            $this->assertThrowableMessageMatches($throwable, 'boot().+?deprecated.+?1\.7');
        } finally {
            static::assertSame(1, $count);
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testBootPassingModulesAddModules(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->allows('services')->andReturn($this->stubServices('service_1'));

        $package = Package::new($this->stubProperties('test', true));

        $this->ignoreDeprecations();
        $package->boot($module1);

        static::assertSame('service_1', $package->container()->get('service_1')['id']);
    }

    /**
     * @test
     */
    public function testAddModuleFailsAfterBuild(): void
    {
        $package = Package::new($this->stubProperties('test', true))->build();

        $this->expectExceptionMessageMatches("/add module/i");

        $package->addModule($this->stubModule());
    }

    /**
     * @test
     */
    public function testBuildResolveServices(): void
    {
        $module = new class () implements ServiceModule, ExtendingModule, ExecutableModule
        {
            public function id(): string
            {
                return 'test-module';
            }

            public function services(): array
            {
                return [
                    'dependency' => static function (): object {
                        return (object) ['x' => 'Works!'];
                    },
                    'service' => static function (ContainerInterface $container): object {
                        $works = $container->get('dependency')->x;

                        return new class (['works?' => $works]) extends \ArrayObject
                        {
                        };
                    },
                ];
            }

            public function extensions(): array
            {
                return [
                    'service' => function (\ArrayObject $current): object {
                        return new class ($current)
                        {
                            /**
                             * @var \ArrayObject<string, string>
                             */
                            private \ArrayObject $object;

                            /**
                             * @param \ArrayObject<string, string> $object
                             */
                            public function __construct(\ArrayObject $object)
                            {
                                $this->object = $object;
                            }

                            public function works(): string
                            {
                                return (string) $this->object->offsetGet('works?');
                            }
                        };
                    },
                ];
            }

            public function run(ContainerInterface $container): bool
            {
                throw new \Error('This should not run!');
            }
        };

        $actual = Package::new($this->stubProperties())
            ->addModule($module)
            ->build()
            ->container()
            ->get('service')
            ->works();

        static::assertSame('Works!', $actual);
    }

    /**
     * @test
     */
    public function testBuildPassingModulesToBoot(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));

        $module3 = $this->stubModule('module_3', ServiceModule::class);
        $module3->expects('services')->andReturn($this->stubServices('service_3'));

        $package = Package::new($this->stubProperties('test', true))
            ->addModule($module1)
            ->addModule($module2)
            ->build();

        $this->ignoreDeprecations();
        $package->boot($module2, $module3);

        $container = $package->container();

        static::assertSame('service_1', $container->get('service_1')['id']);
        static::assertSame('service_2', $container->get('service_2')['id']);
        static::assertSame('service_3', $container->get('service_3')['id']);
    }

    /**
     * @test
     */
    public function testBootFailsIfPassingNotAddedModulesAfterContainer(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));

        $module3 = $this->stubModule('module_3', ServiceModule::class);
        $module3->allows('services')->andReturn($this->stubServices('service_3'));

        $package = Package::new($this->stubProperties('test', true))
            ->addModule($module1)
            ->addModule($module2)
            ->build();

        $container = $package->container();

        static::assertSame('service_1', $container->get('service_1')['id']);
        static::assertSame('service_2', $container->get('service_2')['id']);

        $this->expectExceptionMessageMatches("/can't add module module_3/i");
        $this->ignoreDeprecations();
        $package->boot($module2, $module3);
    }

    /**
     * @test
     */
    public function testBootFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->whenHappen(
                static function (string $packageName, int $status) use (&$log): void {
                    static::assertSame($status, Package::STATUS_IDLE);
                    $log[] = 0;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
                    $log[] = 4;
                }
            );

        $package->connect(Package::new($this->stubProperties('connected', true)));
        $package->boot();

        static::assertSame(range(0, 4), $log);
        static::assertCount(1, $package->connectedPackages());
    }

    /**
     * This is identical to the above where we do only `boot()`, we do here `build()->boot()` but
     * we expect identical result.
     *
     * @test
     */
    public function testBuildAndBootFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->whenHappen(
                static function (string $packageName, int $status) use (&$log): void {
                    static::assertSame($status, Package::STATUS_IDLE);
                    $log[] = 0;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
                    $log[] = 4;
                }
            );

        $package->connect(Package::new($this->stubProperties('connected', true)));
        $package->build()->boot();

        static::assertSame(range(0, 4), $log);
        static::assertCount(1, $package->connectedPackages());
    }

    /**
     * This is mostly identical to the above where we do `build()->boot()` but here we do
     * we do just `build()` and we expect very similar result, but ACTION_READY never fired.
     *
     * @test
     */
    public function testBuildFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->whenHappen(
                static function (string $packageName, int $status) use (&$log): void {
                    static::assertSame($status, Package::STATUS_IDLE);
                    $log[] = 0;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->never();

        $package->connect(Package::new($this->stubProperties('connected', true)));
        $package->build();

        static::assertSame(range(0, 3), $log);
        static::assertCount(1, $package->connectedPackages());
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitHookDebugOff(): void
    {
        $package = Package::new($this->stubProperties('test', false));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitHookDebugOn(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitializedHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromReadyHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->once();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->boot();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromInitHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/build/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromInitializedHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/build/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromReadyHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_MODULARITY_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->once();

        $this->expectExceptionMessageMatches('/build/i');
        $package->boot();
    }

    /**
     * @test
     */
    public function testPropertiesCanBeRetrievedFromContainer(): void
    {
        $expected = $this->stubProperties();
        $actual = Package::new($expected)->build()->container()->get(Package::PROPERTIES);

        static::assertSame($expected, $actual);
    }

    /**
     * Test, when multiple modules are added, and debug is true, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenDebug(): void
    {
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->stubModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->stubModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->stubModule('factory', FactoryModule::class);
        $factoriesModule->expects('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->stubModule('extension', ExtendingModule::class);
        $extendingModule->expects('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));
        $multiModule->expects('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->expects('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->stubProperties('name', true))
            ->addModule($emptyModule)
            ->addModule($extendingModule)
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty not-added',
                'extension extended (E)',
                'extension added',
                'empty_services not-added',
                'empty_factories not-added',
                'empty_extensions not-added',
                'service registered (S1, S2)',
                'service added',
                'multi registered (MS1)',
                'multi registered-factories (MF1, MF2)',
                'multi extended (ME1, ME2)',
                'multi added',
                'factory registered-factories (F)',
                'factory added',
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
                'empty_factories',
                'empty_extensions',
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
                'extension',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'extension',
                'service',
                'multi',
                'factory',
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
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->stubModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->stubModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->stubModule('factory', FactoryModule::class);
        $factoriesModule->expects('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->stubModule('extension', ExtendingModule::class);
        $extendingModule->expects('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));
        $multiModule->expects('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->expects('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->stubProperties('name', false))
            ->addModule($emptyModule)
            ->addModule($extendingModule)
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED,
                'extension ' . Package::MODULE_ADDED,
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
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
                'empty_factories',
                'empty_extensions',
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
                'extension',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'extension',
                'service',
                'multi',
                'factory',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test we can connect services across packages.
     *
     * @test
     */
    public function testConnectIdlePackageFromIdlePackage(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->with($package1->name(), Package::STATUS_IDLE, true);

        $connected = $package2->connect($package1);

        static::assertTrue($connected);
        static::assertTrue($package2->isPackageConnected($package1->name()));

        $package1->build();
        $package2->build();

        // Retrieve a Package 1's service from Package 2's container.
        static::assertInstanceOf(\ArrayObject::class, $package2->container()->get('service_1'));
    }

    /**
     * Test we can connect services across packages.
     *
     * @test
     */
    public function testConnectBuiltPackageFromIdlePackage(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->with($package1->name(), Package::STATUS_IDLE, false);

        $package1->build();

        $connected = $package2->connect($package1);

        static::assertTrue($connected);
        static::assertTrue($package2->isPackageConnected($package1->name()));

        $package2->build();

        // Retrieve a Package 1's service from Package 2's container.
        static::assertInstanceOf(\ArrayObject::class, $package2->container()->get('service_1'));
    }

    /**
     * @test
     */
    public function testConnectBootedPackageFromIdlePackage(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->with($package1->name(), Package::STATUS_IDLE, false);

        $package1->boot();

        $connected = $package2->connect($package1);

        static::assertTrue($connected);
        static::assertTrue($package2->isPackageConnected($package1->name()));

        $package2->build();

        // Retrieve a Package 1's service from Package 2's container.
        static::assertInstanceOf(\ArrayObject::class, $package2->container()->get('service_1'));
    }

    /**
     * @test
     */
    public function testConnectBuiltPackageFromBuildPackageFailsDebugOff(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        \Mockery::mock('alias:' . \WP_Error::class);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECT))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        $package1->build();
        $package2->build();

        static::assertFalse($package2->connect($package1));
    }

    /**
     * @test
     */
    public function testConnectBuiltPackageFromBuildPackageFailsDebugOn(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2', true);

        \Mockery::mock('alias:' . \WP_Error::class);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECT))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        $package1->build();
        $package2->build();

        $this->expectExceptionMessageMatches('/built container/i');
        $package2->connect($package1);
    }

    /**
     * @test
     */
    public function testConnectBuiltPackageFromBootedPackageFailsDebugOff(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        \Mockery::mock('alias:' . \WP_Error::class);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECT))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        $package1->build();
        $package2->boot();

        static::assertFalse($package2->connect($package1));
    }

    /**
     * @test
     */
    public function testConnectBuiltPackageFromBootedPackageFailsDebugOn(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2', true);

        \Mockery::mock('alias:' . \WP_Error::class);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECT))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        $package1->build();
        $package2->boot();

        $this->expectExceptionMessageMatches('/built container/i');
        $package2->connect($package1);
    }

    /**
     * @test
     */
    public function testAccessingServicesFromIdleConnectedPackageFails(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');

        $connected = $package2->connect($package1);

        $package2->build();

        static::assertTrue($connected);
        static::assertTrue($package2->isPackageConnected($package1->name()));

        // We got a "not found" exception because `PackageProxyContainer::has()` return false,
        // because $package1 is not built
        $this->expectExceptionMessageMatches('/service_1.+not found/i');
        $package2->container()->get('service_1');
    }

    /**
     * @test
     */
    public function testPackageCanOnlyBeConnectedOnce(): void
    {
        $package1 = $this->stubSimplePackage('1', false);
        $package2 = $this->stubSimplePackage('2', true);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once();

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECT))
            ->twice()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_BUILD))
            ->never();

        static::assertTrue($package2->connect($package1));
        static::assertTrue($package2->isPackageConnected($package1->name()));

        static::assertFalse($package2->connect($package1));
        static::assertTrue($package2->isPackageConnected($package1->name()));

        static::assertFalse($package2->connect($package1));
        static::assertTrue($package2->isPackageConnected($package1->name()));

        $package1->build();
        $package2->build();
        static::assertSame('service_1', $package2->container()->get('service_1')['id']);
    }

    /**
     * @test
     */
    public function testPackageCanNotBeConnectedWithThemselves(): void
    {
        $package1 = $this->stubSimplePackage('1');

        $action = $package1->hookName(Package::ACTION_FAILED_CONNECT);
        Monkey\Actions\expectDone($action)->never();

        static::assertFalse($package1->connect($package1));
    }

    /**
     * @test
     */
    public function testGettingServicesFromBuiltConnectedPackage(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');
        $package3 = $this->stubSimplePackage('3');

        $connected2 = $package1->connect($package2);
        $connected3 = $package1->connect($package3);

        // Note only P2 is "booted", while P1 and P3 are "built".
        $package1->build();
        $package2->boot();
        $package3->build();

        // Test connection was successful
        static::assertTrue($connected2);
        static::assertTrue($connected3);
        static::assertTrue($package1->isPackageConnected($package2->name()));
        static::assertTrue($package1->isPackageConnected($package3->name()));

        // We can get containers of all three packages
        $container1 = $package1->container();
        $container2 = $package2->container();
        $container3 = $package3->container();

        // And we can get services from all three containers if called directly
        static::assertSame('service_1', $container1->get('service_1')['id']);
        static::assertSame('service_2', $container2->get('service_2')['id']);
        static::assertSame('service_3', $container3->get('service_3')['id']);

        // And we can use Package 1 to get a service from the two connected packages
        static::assertSame('service_2', $package1->container()->get('service_2')['id']);
        static::assertSame('service_3', $package1->container()->get('service_3')['id']);
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is off, we expect the exception
     * to be caught, an "boot failed" action to be failed, and the Package to be in errored status.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties())->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
        static::assertFalse($package->hasReachedStatus(Package::STATUS_IDLE));
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is of, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties('basename', true))->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        $this->expectExceptionObject($exception);
        $package->boot();
    }

    /**
     * When multiple calls to `Package::addPackage()` throw an exception, and debug is off, we
     * expect none of them to bubble up, and the first to cause the "build failed" action.
     * We also expect the Package to be in errored status.
     * We expect all other `Package::addPackage()` exceptions to do not fire action hook.
     * We expect Package::build()` to fail without doing anything. Finally, when `Package::boot()`
     * is called, we expect the action "boot failed" to be called, and the passed exception to have
     * an exception hierarchy with all the thrown exceptions.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_ADD_MODULE))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($package): void {
                    $this->assertThrowableMessageMatches($throwable, 'build package');
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'build package');
                    $previous = $previous->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'add module');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->addModule($module1)->addModule($module2)->build()->boot());
        static::assertTrue($package->hasFailed());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * The same as the test above, but this time we call `Package::boot()` directly, instead of
     * `$package->build()->boot()`, but the expectations are identical.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleWithoutBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        $connected = Package::new($this->stubProperties());
        $connected->boot();

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_ADD_MODULE))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'two');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        $package = $package->addModule($module1)->addModule($module2);

        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
    }

    /**
     * When `Package::build()` throws an exception, and debug is off, we expect it to be caught, the
     * "build failed" action to be fired, and the Package to be in errored status. When after that
     * `Package::boot()` is called we expect the action "boot failed" to be called passing an
     * exception whose "previous" is the exception thrown by `Package::build()`.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    static::assertSame($exception, $throwable->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->build()->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
    }

    /**
     * When `Package::build()` throws an exception, and debug is on, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties('basename', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                    static::assertTrue($package->hasFailed());
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionObject($exception);
        $package->build()->boot();
    }
}
