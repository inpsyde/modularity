# Inpsyde Modularity

[![Version](https://img.shields.io/packagist/v/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)
[![Status](https://img.shields.io/badge/status-active-brightgreen.svg)](https://github.com/inpsyde/modularity)
[![codecov](https://codecov.io/gh/inpsyde/modularity/branch/master/graph/badge.svg)](https://codecov.io/gh/inpsyde/modularity)
[![Downloads](https://img.shields.io/packagist/dt/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)
[![License](https://img.shields.io/packagist/l/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)

## Introduction

inpsyde/modularity is a modular [PSR-11](https://github.com/php-fig/container) implementation for WordPress Plugins, Themes or Libraries.

## Installation

```
$ composer require inpsyde/modularity
```

## Minimum Requirements and Dependencies

* PHP 7.2+

When installed for development, via Composer, the package also requires:

* inpsyde/php-coding-standards
* roots/wordpress
* vimeo/psalm
* phpunit/phpunit
* brain/monkey
* mikey179/vfsstream

## Package
This is the central class, which will allow you to add multiple Containers, register Modules and use Properties to get more information about your Application.

Aside from that, the `Package`-class will boot your Application on a specific point (like plugins_loaded) and grants access for other Applications via hook to register and extend Services via Modules.

```php
<?php
Inpsyde\Modularity\Package::new($properties)->boot();
```

The `Package`-class contains the following public API:

**Package::moduleStatus(): array**

Returns an array of all Modules and the current status.

**Package::moduleIs(string $moduleId, string $status): bool**

Allows to check the status for a given `Module::id()`.

Following `Module` statuses are available:

| Status                                 | Description                                                  |
| -------------------------------------- | ------------------------------------------------------------ |
| `Package::MODULE_REGISTERED`           | A `ServiceModule` was added and returned a non-zero number of services. |
| `Package::MODULE_REGISTERED_FACTORIES` | A `FactoryModule` was added and returned a non-zero number of factories. |
| `Package::MODULE_EXTENDED`             | An `ExtendingModule` was added and returned a non-zero number of extension. |
| `Package::MODULE_ADDED`                | _Any_ of the three statuses above applied, or a module implements `ExecutableModule` |
| `Package::MODULE_NOT_ADDED`            | _None_ of the first three statuses applied for a modules that is non-executable. That might happen in two scenarios: a module only implemented base `Module` interface, or did not return any service/factory/extension. |
| `Package::MODULE_EXECUTED`             | An `ExecutableModule::run()` method was called and returned `true`. |
| `Package::MODULE_EXECUTION_FAILED`     | An `ExecutableModule::run()` method was called and returned `false`. |

**Package::hookName(string $suffix = ''): string**

Allows to generate the hookName for Package-class (see below)

**Package::properties(): PropertiesInterface**

Access to Properties.

**Package::container(): ContainerInterface**

Access to the compiled Container after the booting process is finished.

**Package::name():string**

A shortcut to `Properties::baseName()` which contains the name of your Application

**Package::addModule(Module $module): self**

Allows adding Modules from outside via custom Hooks triggered.

**Package::statusIs(int $status): bool**

Retrieve the current status of the Application. Following are available:

- `Package::STATUS_IDLE` - before Application is booted.
- `Package::STATUS_INITIALIZED` - after first init action is triggered.
- `Package::STATUS_BOOTED` - Application has successfully booted.
- `Package::STATUS_FAILED_BOOT` - when Application did not boot properly.

### Access from external
The recommended way to set up your Application is to provide a function in your Application namespace which returns an instance of Package. Here’s a short example of an “Acme”-Plugin:

```php
<?php

declare(strict_types=1);

/*
 * Plugin Name:       Acme
 * Author:            Inpsyde GmbH
 * Author URI:        https://inpsyde.com
 * Version:           1.0.0
 * Text Domain:       acme
 */

namespace Acme;

use Inpsyde\Modularity;

function plugin(): Modularity\Package {
    static $package;
    if (!$package) {
        $properties = Modularity\Properties\PluginProperties::new(__FILE__);
        $package = Modularity\Package::new($properties);
    }

   return $package;
}

add_action(
	'plugins_loaded',
	static function(): void {
		plugin()->boot();
	}
);
```

By providing the `Acme\plugin()`-function, you’ll enable externals to hook into your Application:

```php
<?php

declare(strict_types=1);

namespace FooBarInc;

use Inpsyde\Modularity\Package;

if (! function_exists('Acme\plugin')) {
   return;
}

add_action(
    Acme\plugin()->hookName(Package::ACTION_INIT),
    static function (Package $plugin): void {
       $plugin->addModule(new MyModule());
    }
);
```

### What happens on Package::boot()?

When booting your Application, following will happen inside:

**0. Package::statusIs(Package::STATUS_IDLE);**

Application is idle and ready to start.

**1. Register default Modules**

Default Modules which are injected before `Package::boot()` will be registered first by iterating over all Modules and calling `Package::addModule()`.

**2. Package::ACTION_INIT**

A custom WordPress action will be triggered first to allow registration of additional Modules via `Package::addModule()` by accessing the `Package`-class. Application will change into `Package::STATUS_INITIALIZED` afterwards.

Newly registered Modules via that hook will be executed after the default Modules which are injected before the `Package::boot()`-method.

**3. Compile read-only Container**

The default primary PSR-Container is generated by the ContainerConfigurator by injecting all Factories, Extension and child PSR-Containers into it.

**4. Execute all ExecutableModules**

After collecting all ExecutableModules, the Package-class will now iterate over all ExecutableModules and execute them by injecting the default primary PSR-Container.

**5. Package::ACTION_READY**

Last but not least, `Package::boot()` will trigger custom WordPress Action which allows you to access the Package-class again for the purpose of debugging all Modules.

**6. Done**

The package was either successfully booted and state changed to `Package::STATUS_BOOTED` or failed booting due some exceptions and state was changed to `Package::STATUS_FAILED_BOOT`.


## Default Container based on PSR-11
The used Container in your Application is based on PSR-11 and allows you via Modules to set, extend and get Services. The consuming Services from your Modules has read-only access to the Container. Adding new Factories to the Container will only be possible via an own Container or a Module.

The default (primary) Container is a delegating Container and receives aside from Factories, Extensions also PSR-based child Containers, which are registered via Package-class. If a Service cannot be resolved via the primary Container, it will resort to the has and get methods of the delegates to resolve the requested Service. Additionally, the primary Container will allow you not only to access Services, it will also allow you to extend Services from child Containers.

You can simply register an own PSR-Container via Package like following:

```php
<?php
$leagueContainer = new League\Container\Container();
$diContainer = new Di\Container();

Inpsyde\Modularity\Package::new($properties, $leagueContainer, $diContainer)
    ->boot();
```

## Modules
Services can be _registered_, _extended_ and _booted_ via a so-called Module in your Application. 

Those Modules can be registered to your Application via the provided `ServiceModule`-, `FactoryModule`-, `ExtendingModule`- and `ExecutableModule`-interfaces.

**Default Modules** are registered before `Package::boot()`:

```php
<?php
Inpsyde\Modularity\Package::new($properties)
    ->addModule(new ModuleWhichProvidesServices())
    ->addModule(new ModuleWhichProvidesFactories())
    ->addModule(new ModuleWhichProviedsExtensions())
    ->addModule(new ModuleWhichIsExecuted())
	->boot();
```

Each Module implementation will extend the basic `Module`-interface which is required to define a `Module::id(): string`. This identifier will be re-used in Package-class to keep track of the current state of your Module and will allow easier debugging of your Application. To avoid defining this by hand, it is possible to use the following Trait: `Inpsyde\Modularity\Module\ModuleClassNameIdTrait`

### ServiceModule
A ServiceModule will allow you to register new Services to the Container, to access them later on a specific point. The `ServiceModule::services(): array` will return an array of Services. Each array-key is an identifier for your Service, while the array-value will contain a callable which receives the primary Container (read-only) to set up your Service.

Services registered via `ServiceModule::services()` will only be resolved and extended once and on continues access the same instance will be returned.

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichProvidesServices implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services() : array
    {
        return [
            ServiceOne::class => static function(ContainerInterface $container): ServiceOne {
                return new ServiceOne();
            } 
        ];
    }
}
```

### FactoryModule
The `FactoryModule::factories(): array` will allow you to register new Services as factories. This means, that every time you’re accessing the Service via Container::get() you’ll get a new instance of the Service.

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichProvidesFactories implements FactoryModule
{
    use ModuleClassNameIdTrait;

    public function factories() : array
    {
        return [
            FactoryServiceOne::class => static function(ContainerInterface $container): FactoryServiceOne {
                return new FactoryServiceOne();
            } 
        ];
    }
}
```

### ExtendingModule
The `ExtendingModule::extensions(): array` will allow you to return an array of Extensions for your Services. Those Extensions will be added to your Services after registration. Each Extension will return a callable function which will receive the original Service and the primary Container (read-only).

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichProvidesExtensions implements ExtendingModule
{
    use ModuleClassNameIdTrait;

    public function extensions() : array 
    {
        return [
            ServiceOne::class => static function(ServiceOne $serviceOne, ContainerInterface $container): ExtendedServiceOne
            {
                return ExtendedServiceOne($serviceOne);
            }
        ];
    }
}
```

### ExecutableModule
If there is functionality that needs to be executed, you can make the Module executable like following:

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichIsExecuted implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        $serviceOne = $container->get(ServiceOne::class);
        add_action('init', $serviceOne);

        return true;
    }
}
```

The return value true/false will determine if the Module has successfully been executed or not.

#### Context-based execution of Services
To execute Services based on a Context like “Rest Request” or “FrontOffice” we recommend the usage of [inpsyde/wp-context](https://github.com/inpsyde/wp-context). This package allows you to access the current request context and based on that you can execute your Services:

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;
use Inpsyde\WpContext;

class ModuleFour implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        $context = WpContext::determine();
        if (!$context->is(WpContext::AJAX, WpContext::CRON)) {
          return false;
        }

        // stuff for requests that are either AJAX or WP cron
        $serviceOne = $container->get(ServiceOne::class);
        add_action('init', $serviceOne);

        return true;
    }
}
```

## Properties
Properties containing additional information about your Application and can be built based on Themes, Plugins or Libraries. The Properties itself are immutable and only grant access to values after they were injected into the Package-class.

Properties are added to the Package-class and automatically added as a Service to the primary Container.

To access Properties in your application via Container you can use the class constant `Package::PROPERTIES`:

```php
<?php

declare(strict_types=1);

use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleThree implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        /** @var Properties $properties */
        $properties = $container->get(Package::PROPERTIES);
        
        return true;
    }
}
```

A specific instance of your Properties will use the following data:

| Properties method | Theme - style.css | Plugin - file header | Library - composer.json |
| --- | --- | --- | --- |
| Properties::author() | Author | Author | authors[0].name |
| Properties::authorUri() | Author URI | AuthorURI | authors[0].homepage |
| Properties::description() | Description | Description | description |
| Properties::domainPath() | Domain Path | DomainPath | extra.modularity.domainPath |
| Properties::name() | Theme Name | Name | extra.modularity.name |
| Properties::textDomain() | Text Domain | TextDomain | extra.modularity.textDomain |
| Properties::uri() | Theme URI | PluginURI | extra.modularity.uri |
| Properties::version() | Version | Version | version<br>extra.modularity.version |
| Properties::requiresWp() | RequiresWP | RequiresWP | extra.modularity.requiresWp |
| Properties::requiresPhp() | RequiresPHP | RequiresPHP | require.php<br>require-dev.php |
| Properties::baseUrl() | WP_Theme::get_stylesheet_directory_uri() | plugins_url() |  |
| Properties::network() |  | Network |  |
| Properties::status() | Status |  |  |
| Properties::tags() | Tags |  | keywords |
| Properties::template() | Template |  |  |

### PluginProperties
Inside your Plugin you can use the following code to automatically generate Properties based on the [Plugins Header](https://developer.wordpress.org/reference/functions/get_plugin_data/):

```php
<?php
use Inpsyde\Modularity\Properties;

$properties = Properties\PluginProperties::new('/path/to/plugin-main-file.php');
```

Additionally, PluginProperties will have the following public API:

- `PluginProperties::network(): bool` - returns if the Plugin is only network-wide usable.
- `PluginProperties::isActive(): bool` - returns if the current Plugin is active.
- `PluginProperties::isNetworkActive(): bool` - returns if the current Plugin is network-wide active.
- `PluginProperties::isMuPlugin(): bool` - returns if the current Plugin is a must-use Plugin.

### ThemeProperties
To generate Properties for your Theme you need to provide the Theme directory or Theme name. Properties will be built based on the headers in style.css of your Theme:

```php
<?php
use Inpsyde\Modularity\Properties;

$properties = Properties\ThemeProperties::new('/path/to/theme/directory/');
```

Additionally, ThemeProperties will have the following public API:

- `ThemeProperties::status(): string` - If the current Theme is “published”.
- `ThemeProperties::tags(): array` - Tags defined in style.css.
- `ThemeProperties::template(): string`
- `ThemeProperties::isChildTheme(): bool` - True, when the Theme is a Child Theme and using a template.
- `ThemeProperties::isCurrentTheme(): bool` - returns true when this Theme is activated.
- `ThemeProperties::parentThemeProperties(): ?ThemeProperties` - returns Properties of the parent theme if it is a child-Theme.

### LibraryProperties
For libraries, you can use the LibraryProperties which give you context based on your composer.json. You can boostrap your standalone-library like following:

```php
use Inpsyde\Modularity\Properties;

$properties = Properties\LibraryProperties::new('path/to/composer.json');
```

Often when creating a library we don't know the base URL of library, because we don't know where it
gets installed and WP does not natively support libraries. That is why by default 
`LibraryProperties::baseUrl()` returns null.

In the case a `LibraryProperties` instance is created in a context where the base URL is known, it
is possible to include it when creating the instance:

```php
$url = 'https://example.com/wp-content/vendor/my/library';
$properties = Inpsyde\Modularity\Properties\LibraryProperties::new('path/to/composer.json', $url);
```

Alternatively, is the URL is known at a later time, when an instance of `LibraryProperties` is
already present, it is possible to use the `withBaseUrl()` method:

```php
$url = 'https://example.com/wp-content/vendor/my/library';
/** @var Inpsyde\Modularity\Properties\LibraryProperties $properties */
$properties->withBaseUrl($url);
```

Please note that `withBaseUrl()` will only work if a base URL is not set already, otherwise it will
throw an exception.

Additionally, `LibraryProperties` will have the following public API:

- `LibraryProperties::tags(): array` - returns a list of keywords defined in composer.json.

## License

Copyright (c) Inpsyde GmbH

This code is licensed under the [GPLv2+ License](LICENSE).
