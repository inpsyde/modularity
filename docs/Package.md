# Package

`Package` is the library's main class that manages different modules, containers, and embeds a "properties" object that provides information about the application.



## "Build" and "Boot" procedures

The `Package` class is responsible for "bootstrapping" the application and, by emitting hooks, enable external code to register and extend services, as well as "connecting" other `Package` instances sharing the containers.

That happens in two separate phases, the "build" and "boot" phase.

In the **"build" phase**, initialized by calling **`Package::build()`**, the class emits an hook that allow external code to add modules or connect other packages. After that, the package container is "locked" and no more services can be added.

In the **"boot" phase**, initialized by calling **`Package::boot()`**, any "executable" module that was added in the "build" phase is now executed.

More info about the two phases can be found in the ["Application flow" chapter](./Application-flow.md)



## Action hooks

It has been mentioned how during both the "build" and "boot" phases the `Package` instance emits hooks that allow external code to interact with it, e. g. by extending or connecting it.

There are three package-specific hooks:

- `Package::ACTION_INITIALIZING`, fired at the beginning of the "build" phase, enables adding modules or connecting packages to the passed `Package` instance.
- `Package::ACTION_INITIALIZED`, fired at the end of the "build" phase, enables external code to access `Package`'s container, resolving services. No modification to the `Package`'s services are possible at this time or later.
- `Package::ACTION_BOOTED`, fired at the end of the "boot" phase, enables external code to access `Package`'s instance at a stage where it did all its job by registering services and adding hook to WordPress.

All the hooks above enable access to `Package` properties and to retrieve information about specific modules.



### Hooking package-specific hooks

The three package-specific hooks are so called because their name is dynamic, and can be obtained via a `Package` instance, by calling `Package::hookName()` passing any of the hook name constant mentioned above. For example:

```php
add_action(
    $package->hookName(Package::ACTION_INIT),
    fn (Package $package) => $package->addModule(new SomeModule())
);
```



### Generic "init" hook

Besides the three package-specific hooks, the `Package` instance emits a single hook whose name is not dynamic, but is fired for every `Package` instance. 

The hook name is stored in the `Package::ACTION_MODULARITY_INIT` constant, it is executed right after the package-specific `Package::ACTION_INIT` hook, and unlike the three package-specific hooks, it passes the package name as first argument and the `Package` instance as second.  

```php
add_action(
    Package::ACTION_MODULARITY_INIT,
    function (string $packageName, Package $package): void {
        if (str_starts_with($packageName, 'acme-')) {
            $package->connect(\Acme\someGlobalLibrary())
        }
    }
);
```

Among other things, this enables to easily apply the same operations to multiple packages without calling `function_exists()` and even without knowing in advance what packages will be there.



## Usage example

The following code shows how to use this class for a plugin. A theme or library usage would not differ much. 

```php
/* Plugin Name: Acme */

namespace Acme;

use Inpsyde\Modularity\{Package, Properties};

function plugin(): Package {
    static $package;
    if (!$package) {
        $properties = Properties\PluginProperties::new(__FILE__);
        $package = Package::new($properties)
            ->addModule(new ModuleOne())
            ->addModule(new ModuleTwo());
    }
   return $package;
}

// An early hook. Not _too_ early to allow external code to extend the instance before
// the call to `plugin()->build()` "locks" it. A late priority is used so that hooking
// 'plugins_loaded' is still ok to call `plugin()` and extend the obtained `Package`.
add_action('plugins_loaded', fn () => plugin()->build(), PHP_INT_MAX);

// The latest hook the plugin can use to do its job.
add_action('template_redirect', fn () => plugin()->boot());
```

The `Acme\plugin()` function above enables external code to use an action hook to extend the package, for example adding more modules:

```php
namespace FooBarInc;

use Inpsyde\Modularity\Package;

if (function_exists('Acme\plugin')) {
   add_action(
        Acme\plugin()->hookName(Package::ACTION_INIT),
        fn (Package $plugin) => $plugin->addModule(new MyModule())
    );
}
```



### Alternative usage using a plugin-specific hook

An alternative to the previous example makes use of a plugin-specific hook to allow for extension. This hook is fired inside the `plugin()` function, right before calling `build()`:

```php
use Inpsyde\Modularity\{Package, Properties};

function plugin(): Package {
    static $package;
    if (!$package) {
        $properties = Properties\PluginProperties::new(__FILE__);
        $package = Package::new($properties);
        // Add default modules here...
        do_action('acme-plugin.extend', $package);
        $package->build();
    }
    return $package;
}

// The latest hook the plugin can use to do its job.
add_action('template_redirect', fn () => plugin()->boot());
```

Thanks to that, any code that needs to extend this plugin, does not need to call `function_exists()`, and the bootstrap process is easier without a separate `build()`, still keeping `boot()` as late as possible. Extending code can look like the following:

```php
use Inpsyde\Modularity\Package;

add_action(
    'acme-plugin.extend',
    function (Package $plugin): void {
        $plugin->addModule(new MyModule());
    }
);
```

This approach makes sense when we expect multiple external plugins/libraries/themes to extend our plugin, e. g. when we are writing a plugin we design to be extended via extensions.



## Connecting packages

Every `Package` has a separate container, however it might be desirable access another package's services. For example, from a plugin access a library's services, or from a theme access a plugin's services.

That can be done using the `Package::connect()` method. Here's an example:

```php
// Theme functions.php
use Inpsyde\Modularity\{Package, Properties};

$theme = Package::new(Properties\ThemeProperties::new(__DIR__));
$theme->connect(\Acme\plugin());
$theme->boot();
```

To note:

- `Package::connect()` must be called **before** the package enters the "initialized" status, that is, before calling `Package::boot()` or `Package::build()`. If called later, no connections happen and it returns `false`
- The package to be connected might be already booted or not. In the second case the connection will happen, but before accessing its services it has to be at least built, or an exception will happen.

Package connection enables the creation of reusable libraries to be consumed by multiple plugins. For example, it might be possible to have a *library* that has something like this:

```php
namespace Acme;

use Inpsyde\Modularity\{Package, Properties};

function myLibrary(): Package {
    static $lib;
    if (!$lib) {
        $properties = Properties\LibraryProperties::new('path/to/composer.json');
        Package::new($properties)
            ->addModule(new ModuleOne())
            ->addModule(new ModuleTwo())
            ->boot();
    }
    return $lib;
}
```

This function might be autoloaded via Composer, autoload, but not being a plugin, it will not be executed by WordPress.

However, multiple plugins in the same installation could do:

```php
$plugin->connect(\Acme\myLibrary());
```

Thanks to that, all plugins will be able to access the library's services in the same way they access own modules' services.

Please note that by calling `Package::boot()` in the `myLibrary()` function immediately after having instantiated the `Package` instance will prevent any external code to extend the library, adding more modules or connecting other packages.



### Accessing connected packages' properties

In modules, we can access package properties calling `$container->get(Package::PROPERTIES)`. If we'd like to access any connected package properties, we could do that using a key whose format is: `sprintf('%s.%s', $connectedPackage->name(), Package::PROPERTIES)`.



## `Package` public API




### `Package::boot(): bool`

Executes the "boot" phase, and the "build" phase, if it has not be executed separately via `Package::build()`.



### `Package::build(): static`

Executes the "build" phase. The inner container is safely accessible after that, and no more services can be added to it.



### `Package::connect(Package $package): bool`

Connect the given package sharing their services with the calling `Package` instance.




### `Package::connectedPackages(): array`

Returns an array of names of packages connected via `Package::connect()`.




### `Package::container(): ContainerInterface`

Access to the compiled PSR-11 container. Throws an exception if called before the "build" phase is completed.



### `Package::hasContainer(): bool`

Returns true if a container has already be generated for the Package, regardless current status. Note: this might be true even in case of failures.



### `Package::hasFailed(): bool`

Returns true if the current status is failed.



### `Package::hasReachedStatus(int $status): bool`

Returns true if the current given status is either the current Package status, or a status the package has previously been. Please note that it will always return false when in a "failed" status (`Package::hasFailed()` returns true).

For the list of available statuses see `Package::statusIs()` below.




### `Package::hookName(string $suffix = ''): string`

Generates the hook name for package-specific hooks.




### `Package::isPackageConnected(string $packageName): bool`

Returns `true` when give a name of a package previously connected via `Package::connect()`.




### `Package::moduleIs(string $moduleId, string $status): bool`

Used to check the status for a given `Module::id()`.  The following statuses are available:

| Status                                 | Description                                                                                                                                                                                                              |
|----------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Package::MODULE_REGISTERED`           | A `ServiceModule` was added and returned a non-zero number of services.                                                                                                                                                  |
| `Package::MODULE_REGISTERED_FACTORIES` | A `FactoryModule` was added and returned a non-zero number of factories.                                                                                                                                                 |
| `Package::MODULE_EXTENDED`             | An `ExtendingModule` was added and returned a non-zero number of extension.                                                                                                                                              |
| `Package::MODULE_ADDED`                | _Any_ of the three statuses above applied, or a module implements `ExecutableModule`                                                                                                                                     |
| `Package::MODULE_NOT_ADDED`            | _None_ of the first three statuses applied for a modules that is non-executable. That might happen in two scenarios: a module only implemented base `Module` interface, or did not return any service/factory/extension. |
| `Package::MODULE_EXECUTED`             | An `ExecutableModule::run()` method was called and returned `true`.                                                                                                                                                      |
| `Package::MODULE_EXECUTION_FAILED`     | An `ExecutableModule::run()` method was called and returned `false`.                                                                                                                                                     |



### `Package::moduleStatus(): array`

Returns an associative array that maps module names to their current status.




### `Package::name(): string`

A shortcut to `Properties::baseName()`.




### `Package::properties(): PropertiesInterface`

Access to the wrapped [properties instance](./Properties.md).




### `Package::statusIs(int $status): bool`

Retrieve the current status of the application. The following statuses are available:

| Status                         | Description                                                                       |
|--------------------------------|-----------------------------------------------------------------------------------|
| `Package::STATUS_IDLE`         | Before application is built or booted (`Package` instance just instantiated).     |
| `Package::STATUS_INITIALIZING` | Before `Package::build()` started processing modules.                             |
| `Package::STATUS_INITIALIZED`  | After `Package::build()` end processing modules.                                  |
| `Package::STATUS_BOOTING`      | Before `Package::boot()` started processing executable modules' "run procedures". |
| `Package::STATUS_BOOTED`       | After `Package::boot()` ended processing executable modules' "run procedures".    |
| `Package::STATUS_DONE`         | The application has successfully completed both processes.                        |
| `Package::STATUS_FAILED`       | The application did not build/boot properly.                                      |