# Modules
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

## ServiceModule
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

## FactoryModule
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

## ExtendingModule
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

### Extending by type

Sometimes it is desirable to extend a service by its type. Extending modules can do that as well:

```php
use Inpsyde\Modularity\Module\ExtendingModule;
use Psr\Log\{LoggerInterface, LoggerAwareInterface};

class LoggerAwareExtensionModule implements ExtendingModule
{
    public function extensions() : array 
    {
        return [
            '@instanceof<Psr\Log\LoggerAwareInterface>' => static function(
                LoggerAwareInterface $service,
                ContainerInterface $c
            ): ExtendedService {

                if ($c->has(LoggerInterface::class)) {
                    $service->setLogger($c->get(LoggerInterface::class));
                }
                return $service;
            }
        ];
    }
}
```

#### Types and subtypes

The `@instanceof<T>` syntax works with class and interface names, targeting the given type and any 
of its subtypes.

For example, assuming the following objects:

```php
interface Animal {}
class Dog implements Animal {}
class BullDog extends Dog {}
```

and the following module: 

```php
class AnimalsExtensionModule implements ExtendingModule
{
    public function extensions() : array 
    {
        return [
            '@instanceof<Animal>' => fn(Animal $animal) => $animal,
            '@instanceof<Dog>' => fn(Dog $dog) => $dog,
            '@instanceof<BullDog>' => fn(BullDog $bullDog) => $bullDog,
        ];
    }
}
```

A service of type `BullDog` would go through all the 3 extensions.

Note how extending callbacks can always safely declare the parameter type using in the signature
the type they have in `@instanceof<T>`.

#### Precedence

The precedence of extensions-by-type resolution goes as follows:

1. Extensions added to exact class
2. Extensions added to any parent class
3. Extensions added to any implemented interface

Inside each of the three "groups", extensions are processed in _FIFO_ mode: the first added are the
first processed.

#### Name helper

The syntax `"@instanceof<T>"` is an hardcoded string that might be error prone to type.

The method `use Inpsyde\Modularity\Container\ServiceExtensions::typeId()` might be used to avoid 
using hardcode strings. For example:

```php
use npsyde\Modularity\Container\ServiceExtensions;

class AnimalsExtensionModule implements ExtendingModule
{
    public function extensions() : array 
    {
        return [
            ServiceExtensions::typeId(Animal::class) => fn(Animal $animal) => $animal,
            ServiceExtensions::typeId(Dog::class) => fn(Dog $dog) => $dog,
            ServiceExtensions::typeId(BullDog::class) => fn(BullDog $bullDog) => $bullDog,
        ];
    }
}
```

#### Only for objects

Extensions-by-type only work for objects. Any usage of `@instanceof<T>` syntax with a string that is
a class/interface name will be ignored.
That means it is not possible to extend by type scalar/array services nor pseudo-types like 
`iterable` or `callable`.

#### Possibly recursive

Extensions by type might be recursive. For example, an extension for type `A` that returns an 
instance of `B` will prevent further extensions to type `A` to execute (unless `B` is a child of `A`)
and will trigger execution of extensions for type `B`.
**Infinite recursion is prevented**. So if extensions for `A` return `B` and extensions for `B`
return `A` that's where it stops, returning an `A` instance.

#### Use carefully

**Please note**: extensions-by-type have a performance impact especially when type extensions are 
used to return a different type, because of possible recursions.
As a reference, it was measured that resolving 10000 objects in the container, each having 9 
extensions-by-type callbacks, on a very fast server, on PHP 8, for one concurrent user, takes 
between 80 and 90 milliseconds.

## ExecutableModule
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

### Context-based execution of Services
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

### Service/Factory overrides
When the same Service id is registered more than once by multiple modules, the latter will override the former.

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

class ModuleWhichOverridesServices implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services() : array
    {
        return [
            ServiceOne::class => static function(ContainerInterface $container): ServiceOne {
                return new class extends ServiceOne{
                    /*  */
                };
            } 
        ];
    }
}
```

*For module developers* this opens up some possibilities, like the ability to inject Mocks in the container, or work around 
scenarios where the use of extensions would result in an unneeded and/or wasteful constructor call of the now-obsolete
original.

*For package maintainers* this is something to watch out for when consuming Modules from multiple sources. 
However unlikely it may be, there is a risk of _unintentional_ overrides resulting in unexpected behaviour.
