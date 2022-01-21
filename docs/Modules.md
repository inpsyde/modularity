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

