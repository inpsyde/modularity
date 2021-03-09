<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

use Inpsyde\Modularity\Container\ContainerConfigurator;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Properties\Properties;
use Psr\Container\ContainerInterface;

class Package
{
    /**
     * All the hooks fired in this class use this prefix.
     * @var string
     */
    private const HOOK_PREFIX = 'inpsyde.modularity.';
    /**
     * Identifier to access Properties in Container.
     *
     * @example
     * $container->has(Package::PROPERTIES);
     * $container->get(Package::PROPERTIES);
     *
     * @var string
     */
    public const PROPERTIES = 'properties';
    /**
     * Custom action to be used to add Modules to the package.
     * It might also be used to access package properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_INIT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_INIT = 'init';
    /**
     * Custom action which is triggered after the application
     * is booted to access container and properties.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_READY),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_READY = 'ready';
    /**
     * Custom action which is triggered when application failed to boot.
     *
     * @example
     * <code>
     * $package = Package::new();
     *
     * add_action(
     *      $package->hookName(Package::ACTION_FAILED_BOOT),
     *      $callback
     * );
     * </code>
     */
    public const ACTION_FAILED_BOOT = 'failed-boot';
    /**
     * Module states can be used to get information about your module.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // false
     * $package->boot(new SomeModule());
     * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // true
     * </code>
     */
    public const MODULE_ADDED = 'added';
    public const MODULE_REGISTERED = 'registered';
    public const MODULE_EXTENDED = 'extended';
    public const MODULE_EXECUTED = 'executed';
    public const MODULE_EXECUTION_FAILED = 'executed-failed';
    public const MODULES_ALL = '*';
    /**
     * Custom states for the class.
     *
     * @example
     * <code>
     * $package = Package::new();
     * $package->statusIs(Package::IDLE); // true
     * $package->boot();
     * $package->statusIs(Package::BOOTED); // true
     * </code>
     */
    public const STATUS_IDLE = 2;
    public const STATUS_INITIALIZED = 4;
    public const STATUS_BOOTED = 8;
    public const STATUS_FAILED = -8;

    /**
     * Current state of the application.
     *
     * @see Package::STATUS_*
     *
     * @var int
     */
    private $status = self::STATUS_IDLE;

    /**
     * Contains the progress of all modules.
     *
     * @see Package::moduleProgress()
     *
     * @var array<array<string>>
     */
    private $moduleStatus = [
        self::MODULES_ALL => [],
        self::MODULE_ADDED => [],
        self::MODULE_REGISTERED => [],
        self::MODULE_EXTENDED => [],
        self::MODULE_EXECUTION_FAILED => [],
    ];

    /**
     * @var ExecutableModule[]
     */
    private $executables = [];

    /**
     * @var Properties
     */
    private $properties;

    /**
     * @var ContainerConfigurator
     */
    private $containerConfigurator;

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     *
     * @return Package
     */
    public static function new(Properties $properties, ContainerInterface  ...$containers): Package
    {
        return new self($properties, ...$containers);
    }

    /**
     * @param Properties $properties
     * @param ContainerInterface[] $containers
     */
    private function __construct(Properties $properties, ContainerInterface ...$containers)
    {
        $this->properties = $properties;

        $this->containerConfigurator = new ContainerConfigurator($containers);
        $this->containerConfigurator->addService(
            self::PROPERTIES,
            static function () use ($properties) {
                return $properties;
            }
        );
    }

    /**
     * @param Module $module
     *
     * @return $this
     * @throws \Exception
     */
    public function addModule(Module $module): self
    {
        $this->assertStatus(self::STATUS_IDLE, 'access Container');

        $added = false;
        if ($module instanceof ServiceModule) {
            foreach ($module->services() as $serviceName => $callable) {
                $this->containerConfigurator->addService($serviceName, $callable);
            }
            $added = true;
            $this->moduleProgress($module->id(), self::MODULE_REGISTERED);
        }

        if ($module instanceof FactoryModule) {
            foreach ($module->factories() as $serviceName => $callable) {
                $this->containerConfigurator->addFactory($serviceName, $callable);
            }
            $added = true;
            $this->moduleProgress($module->id(), self::MODULE_REGISTERED);
        }

        // ExecutableModules are collected and executed on Package::boot()
        // when the Container is being compiled.
        if ($module instanceof ExecutableModule) {
            $this->executables[] = $module;
            $added = true;
        }

        if ($module instanceof ExtendingModule) {
            foreach ($module->extensions() as $serviceName => $extender) {
                $this->containerConfigurator->addExtension($serviceName, $extender);
            }
            $added = true;
            $this->moduleProgress($module->id(), self::MODULE_EXTENDED);
        }

        if ($added) {
            $this->moduleProgress($module->id(), self::MODULE_ADDED);
        }

        return $this;
    }

    /**
     * @param Module ...$defaultModules
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function boot(Module ...$defaultModules): bool
    {
        try {
            // don't allow to boot the application multiple times.
            $this->assertStatus(self::STATUS_IDLE, 'execute boot');

            // Add default Modules to the Application.
            array_map([$this, 'addModule'], $defaultModules);

            do_action(
                $this->hookName(self::ACTION_INIT),
                $this
            );
            // we want to lock adding new Modules and Containers now
            // to process everything and be able to compile the container.
            $this->progress(self::STATUS_INITIALIZED);

            if (count($this->executables) > 0) {
                $this->doExecute();
            }

            do_action(
                $this->hookName(self::ACTION_READY),
                $this
            );
        } catch (\Throwable $throwable) {
            $this->progress(self::STATUS_FAILED);
            do_action($this->hookName(self::ACTION_FAILED_BOOT), $throwable);

            if ($this->properties->isDebug()) {
                throw $throwable;
            }

            return false;
        }

        $this->progress(self::STATUS_BOOTED);

        return true;
    }

    /**
     * @return void
     *
     * @throws \Throwable
     */
    private function doExecute(): void
    {
        foreach ($this->executables as $executable) {
            $success = $executable->run($this->container());
            $this->moduleProgress(
                $executable->id(),
                $success
                    ? self::MODULE_EXECUTED
                    : self::MODULE_EXECUTION_FAILED
            );
        }
    }

    /**
     * @param string $moduleId
     * @param string $type
     *
     * @return  void
     */
    private function moduleProgress(string $moduleId, string $type)
    {
        $this->moduleStatus[$type][] = $moduleId;
        $this->moduleStatus[self::MODULES_ALL][] = sprintf('%1$s %2$s.', $moduleId, $type);
    }

    /**
     * @return array<array<string>>
     */
    public function modulesStatus(): array
    {
        return $this->moduleStatus;
    }

    /**
     * @param string $moduleId
     * @param string $status
     *
     * @return bool
     */
    public function moduleIs(string $moduleId, string $status): bool
    {
        return in_array($moduleId, $this->moduleStatus[$status], true);
    }

    /**
     * Return the filter name to be used to extend modules of the plugin.
     *
     * If the plugin is single file `my-plugin.php` in plugins folder the filter name will be:
     * `inpsyde.modularity.my-plugin`.
     *
     * If the plugin is in a sub-folder e.g. `my-plugin/index.php` the filter name will be:
     * `inpsyde.modularity.my-plugin` anyway, so the file name is not relevant.
     *
     * @param string $suffix
     *
     * @return string
     * @see Package::name()
     *
     */
    public function hookName(string $suffix = ''): string
    {
        $filter = self::HOOK_PREFIX . $this->properties->baseName();

        if ($suffix) {
            $filter .= '.' . $suffix;
        }

        return $filter;
    }

    /**
     * @return Properties
     */
    public function properties(): Properties
    {
        return $this->properties;
    }

    /**
     * @return ContainerInterface
     *
     * @throws \Exception
     */
    public function container(): ContainerInterface
    {
        $this->assertStatus(self::STATUS_INITIALIZED, 'access Container', '>=');

        return $this->containerConfigurator->createReadOnlyContainer();
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->properties->baseName();
    }

    /**
     * @param int $status
     */
    private function progress(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @param int $status
     *
     * @return bool
     */
    public function statusIs(int $status): bool
    {
        return $this->status === $status;
    }

    /**
     * @param int $status
     * @param string $action
     * @param string $operator
     *
     * @throws \Exception
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function assertStatus(int $status, string $action, string $operator = '=='): void
    {
        if (!version_compare((string) $this->status, (string) $status, $operator)) {
            throw new \Exception(sprintf("Can't %s at this point of application.", $action));
        }
    }
}