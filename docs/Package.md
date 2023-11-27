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
- `Package::STATUS_MODULES_ADDED` - after all modules have been added.
- `Package::STATUS_READY` - after the "ready" action has been fired.
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

By providing the `Acme\plugin()` function, you’ll enable external code to hook into your application:

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

## Building the package

Sometimes, especially in unit tests, it might be desirable to obtain services as defined for the
production code, but without calling any `ExecutableModule::run()`, which usually contains
WP-dependant code, and therefore requires heavy mocking.

For example, assuming a common `plugin()` function like the following:

```php
function plugin(): Modularity\Package {
    static $package;
    if (!$package) {
        $properties = Modularity\Properties\PluginProperties::new(__FILE__);
        $package = Modularity\Package::new($properties)
            ->addModule(new ModuleOne())
            ->addModule(new ModuleTwo())
    }
    return $package;
}
```

In unit test it will be possible (as of v1.7+) to do something like the following:

```php
$myService = plugin()->build()->container()->get(MyService::class);
static::assertTrue($myService->isValid());
```

### Booting a built container

The `Package::boot()` method can be called on already built package.

For example, the following is a valid unit test code:

```php
$plugin = plugin()->build();
$myService = $plugin->container()->get(MyService::class);

static::assertTrue($myService->isValid());
static::assertFalse($myService->isBooted());

$plugin->boot();

static::assertTrue($myService->isBooted());
```

### Deprecated boot parameters

Before Modularity v1.7.0, it was an accepted practice to pass default modules to `Package::boot()`,
as in:

```php
add_action(
	'plugins_loaded',
	static function(): void {
		plugin()->boot(new ModuleOne(), new ModuleTwo());
	}
);
```

This is now deprecated to allow a better separation of the "building" and "booting" steps.

While it still works (and it will work up to version 2.0), it will emit a deprecation notice.

The replacement is using `Package::addModule()`:

```php
plugin()->addModule(new ModuleOne())->addModule(new ModuleTwo())->boot();
```

There's only one case in which calling `Package::boot()` with default modules will throw an 
exception (besides triggering a deprecated notice), that is when a passed modules was not added
before `Package::addModule()` and an instance of the container was already obtained from the package.

For example, this will throw an exception:

```php
$plugin = plugin()->build();

// Now that container is built, passing modules to `boot()` will raise an exception, because we
// can't add new modules to an already "compiled" container being that read-only.
$container = $plugin->container();

$plugin->boot(new ModuleOne());
```

To prevent the exception it would be necessary to add the module before calling `build()`, or  alternatively, to call `$plugin->boot(new ModuleOne())` _before_ calling `$plugin->container()`.
In this latter case the exception is not thrown, but the deprecation will still be emitted.


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
        $lib = Inpsyde\Modularity\Package::new($properties);
        $lib->addModule(new ModuleOne());
        $lib->addModule(new ModuleTwo());
        $lib->boot();
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
