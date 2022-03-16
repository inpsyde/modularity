# Package
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



## Access from external

The recommended way to set up your Application is to provide a function in your Application namespace which returns an instance of Package. Here’s a short example of an “Acme”-Plugin:

```php
<?php

declare(strict_types=1);

/*
 * Plugin Name:       Acme
 * Author:            Inpsyde GmbH
 * Author URI:        https://inpsyde.com/
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



## Connecting packages

Every `Package` has a separate container, however sometimes it might be desirable access another package's services. For example from a plugin access one library services, or from a theme access a plugin's services.

That can be done using the `Package::connect()` method.

For example:

```php
// a theme functions.php

$properties = Properties\ThemeProperties::new('/path/to/theme/dir/');
$theme = Inpsyde\Modularity\Package::new($properties);

$theme->connect(\Acme\plugin());
$theme->boot();
```

To note:

- `Package::connect()` must be called **before** boot. If called later, no connections happen and it returns `false`
- The package to be connected might be already booted or not. In the second case the connection will happen, but before accessing its services it has to be booted, or an exception will happen.

Package connection is a great way to create reusable libraries and services that can be used by many plugins. For example, it might be possible to have a *library* that has something like this:

```php
namespace Acme;

function myLibrary(): Package {
    static $lib;
    if (!$lib) {
        $properties = Properties\LibraryProperties::new('path/to/composer.json');
        $lib = Inpsyde\Modularity\: Package::new($properties);
        $lib->boot(new ModuleOne(), new ModuleTwo());
    }
    return $lib;
}
```

This would be autoloaded by Composer, but not being a plugin will not be called by WordPress.

However, *many* plugins in the same installation could do:

```php
/** @var Package $plugin */
$plugin->connect(\Acme\myLibrary());
```

Thanks to that, all plugins will be able to access the library's services in the same way they access own modules' services.



### Accessing connected packages' properties 

In modules, we can access package properties calling `$container->get(Package::PROPERTIES)`. If we'd like to access any connected package properties, we could do that using a key whose format is: `sprintf('%s.%s', $connectedPackage->name(), Package::PROPERTIES)`.



## What happens on Package::boot()?

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
