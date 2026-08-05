<?php

declare(strict_types=1);

use Syde\Modularity\Container\ContainerConfigurator;
use Syde\Modularity\Container\PackageProxyContainer;
use Syde\Modularity\Container\ReadOnlyContainer;
use Syde\Modularity\Container\ServiceExtensions;
use Syde\Modularity\Module\ExecutableModule;
use Syde\Modularity\Module\ExtendingModule;
use Syde\Modularity\Module\FactoryModule;
use Syde\Modularity\Module\Module;
use Syde\Modularity\Module\ModuleClassNameIdTrait;
use Syde\Modularity\Module\ServiceModule;
use Syde\Modularity\Package;
use Syde\Modularity\Properties\BaseProperties;
use Syde\Modularity\Properties\LibraryProperties;
use Syde\Modularity\Properties\PluginProperties;
use Syde\Modularity\Properties\Properties;
use Syde\Modularity\Properties\ThemeProperties;

class_alias(Package::class, 'Inpsyde\Modularity\Package');

class_alias(ContainerConfigurator::class, 'Inpsyde\Modularity\Container\ContainerConfigurator');
class_alias(PackageProxyContainer::class, 'Inpsyde\Modularity\Container\PackageProxyContainer');
class_alias(ReadOnlyContainer::class, 'Inpsyde\Modularity\Container\ReadOnlyContainer');
class_alias(ServiceExtensions::class, 'Inpsyde\Modularity\Container\ServiceExtensions');

class_alias(ExecutableModule::class, 'Inpsyde\Modularity\Module\ExecutableModule');
class_alias(ExtendingModule::class, 'Inpsyde\Modularity\Module\ExtendingModule');
class_alias(FactoryModule::class, 'Inpsyde\Modularity\Module\FactoryModule');
class_alias(Module::class, 'Inpsyde\Modularity\Module\Module');
class_alias(ModuleClassNameIdTrait::class, 'Inpsyde\Modularity\Module\ModuleClassNameIdTrait');
class_alias(ServiceModule::class, 'Inpsyde\Modularity\Module\ServiceModule');

class_alias(BaseProperties::class, 'Inpsyde\Modularity\Properties\BaseProperties');
class_alias(LibraryProperties::class, 'Inpsyde\Modularity\Properties\LibraryProperties');
class_alias(PluginProperties::class, 'Inpsyde\Modularity\Properties\PluginProperties');
class_alias(Properties::class, 'Inpsyde\Modularity\Properties\Properties');
class_alias(ThemeProperties::class, 'Inpsyde\Modularity\Properties\ThemeProperties');
