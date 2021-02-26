<?php

declare(strict_types=1);

namespace Inpsyde\Modularity;

use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;

class Bootstrap
{
    private const FILTER_MODULES_PREFIX = 'inpsyde.modularity.';
    /**
     * Identifier to access Properties in Container.
     *
     * @example
     * $container->has(Boostrap::PROPERTIES);
     * $container->get(Boostrap::PROPERTIES);
     *
     * @var string
     */
    public const PROPERTIES = 'properties';
    /**
     * Custom action which is triggered before application
     * is booted to extend modules and access properties.
     *
     * @example
     *
     * $app = Bootstrap::new();
     *
     * add_action(
     *      $app->hookName(Bootstrap::ACTION_INIT),
     *      $callback
     * );
     */
    public const ACTION_INIT = 'init';
    /**
     * Custom action which is triggered after the application
     * is booted to access container and properties.
     *
     * @example
     *
     * $app = Bootstrap::new();
     *
     * add_action(
     *      $app->hookName(Bootstrap::ACTION_READY),
     *      $callback
     * );
     */
    public const ACTION_READY = 'ready';
    /**
     * Custom action which is triggered when application failed to boot.
     *
     * @example
     *
     * $app = Bootstrap::new();
     *
     * add_action(
     *      $app->hookName(Bootstrap::ACTION_FAILED_BOOT),
     *      $callback
     * );
     */
    public const ACTION_FAILED_BOOT = 'failed-boot';
    /**
     * Module states can be used to get information about your module.
     *
     * @example
     *
     * $app = Bootstrap::new();
     * $app->moduleIs(SomeModule::class, Bootstrap::STATE_EXECUTED); // false
     * $app->boot(new SomeModule());
     * $app->moduleIs(SomeModule::class, Bootstrap::STATE_EXECUTED); // true
     */
    public const MODULE_ADDED = 'added';
    public const MODULE_REGISTERED = 'registered';
    public const MODULE_EXTENDED = 'extended';
    public const MODULE_EXECUTED = 'executed';
    public const MODULE_EXECUTION_FAILED = 'executed-failed';
    private const MODULES_ALL = '_all';

    /**
     * Contains the progress of all modules.
     *
     * @see Bootstrap::progress()
     *
     * @var array<array<string>>
     */
    private $progress = [
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
     * @var PropertiesInterface
     */
    private $properties;

    /**
     * @var ContainerConfigurator
     */
    private $containerConfigurator;

    /**
     * Defines if Bootstrap::boot was executed and all modules are added.
     *
     * @var bool
     */
    private $booted = false;

    /**
     * @param PropertiesInterface $properties
     *
     * @return Bootstrap
     */
    public static function new(PropertiesInterface $properties): Bootstrap
    {
        return new self($properties);
    }

    /**
     * @param PropertiesInterface $properties
     */
    private function __construct(PropertiesInterface $properties)
    {
        $this->properties = $properties;

        $containerConfigurator = new ContainerConfigurator();
        $containerConfigurator->addService(self::PROPERTIES, $properties);
        $this->containerConfigurator = $containerConfigurator;
    }

    /**
     * @param Module $module
     *
     * @return $this
     * @throws \Exception
     */
    public function addModule(Module $module): self
    {
        $this->assertNotBooted('addModule');

        $added = false;
        if ($module instanceof ServiceModule) {
            foreach ($module->services() as $serviceName => $callable) {
                $this->containerConfigurator->addFactory($serviceName, $callable);
            }
            $added = true;
            $this->progress($module->id(), self::MODULE_REGISTERED);
        }

        // ExecutableModules are collected and executed on Bootstrap::boot()
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
            $this->progress($module->id(), self::MODULE_EXTENDED);
        }

        if ($added) {
            $this->progress($module->id(), self::MODULE_ADDED);
        }

        return $this;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return $this
     * @throws \Exception
     */
    public function addContainer(ContainerInterface $container): self
    {
        $this->assertNotBooted('addContainer');
        $this->containerConfigurator->addContainer($container);

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
            if ($this->booted) {
                return false;
            }
            // Add default Modules to the Application.
            array_map([$this, 'addModule'], $defaultModules);

            do_action(
                $this->hookName(self::ACTION_INIT),
                $this
            );
            // we want to lock adding new Modules and Containers now
            // to process everything and be able to compile the container.
            $this->booted = true;

            if (count($this->executables) > 0) {
                $this->doExecute();
            }

            do_action(
                $this->hookName(self::ACTION_READY),
                $this
            );
        } catch (\Throwable $throwable) {
            do_action($this->hookName(self::ACTION_FAILED_BOOT), $throwable);

            if ($this->properties->isDebug()) {
                throw $throwable;
            }

            return false;
        }

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
            $this->progress(
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
    private function progress(string $moduleId, string $type)
    {
        $this->progress[$type][] = $moduleId;
        $this->progress[self::MODULES_ALL][] = sprintf('%1$s %2$s.', $moduleId, $type);
    }

    /**
     * @return array<array<string>>
     */
    public function modulesStatus(): array
    {
        return $this->progress;
    }

    /**
     * @param string $moduleId
     * @param string $status
     *
     * @return bool
     */
    public function moduleIs(string $moduleId, string $status): bool
    {
        return in_array($moduleId, $this->progress[$status], true);
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
     * @see Bootstrap::name()
     *
     */
    public function hookName(string $suffix = ''): string
    {
        $filter = self::FILTER_MODULES_PREFIX . $this->properties->baseName();

        if ($suffix) {
            $filter .= '.' . $suffix;
        }

        return $filter;
    }

    /**
     * @return PropertiesInterface
     */
    public function properties(): PropertiesInterface
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
        if (!$this->booted) {
            throw new \Exception("Can't access Container before application has booted.");
        }

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
     * @param string $action
     *
     * @throws \Exception
     */
    private function assertNotBooted(string $action): void
    {
        if ($this->booted) {
            throw new \Exception(sprintf("Can't %s at this point of application.", $action));
        }
    }
}
