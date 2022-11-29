# Container

Modularity's primary container is a [PSR-11](https://www.php-fig.org/psr/psr-11/) implementation
that is "compiled" from the various _services_, _factories_, and _extensions_ configured by modules.

## Child Containers

Besides definitions in modules, the Modularity container is capable of delegating services'
resolution to other PSR-11 "child containers", that can be added via the `Package` class.

In fact, if the container can not find a service in the primary "parent" container, it will attempt
to resolve it delegating to "child containers".

Registering an external PSR-11 container via the `Package` class looks like this:

```php
<?php
// a couple of examples of custom PSR-11 containers
$leagueContainer = new League\Container\Container();
$diContainer = new Di\Container();

$module = new MyModule();

Inpsyde\Modularity\Package::new($properties, $leagueContainer, $diContainer)
    ->boot($module);
```

In the example above, `MyModule::run()` method will receive a PSR-11 container capable of resolving
services from both the external containers passed to `Package::new()`.

In the case of name collision, the resolution is _FIFO_: the container added first will take
precedence.

Please note that if `MyModule` is an `ExtendingModule`, it will be capable of extending services
registered by the two "external" containers.


## Container Compiler

Another way Modularity is open to extensions via PSR-11 is the "container compiler".

A compiler is an object that takes _services_, _factories_, and _extensions_ added by modules, plus
"child" containers added to it and then "compile" a PSR-11 container.

This is what the Modularity's `ReadOnlyContainerCompiler` class does, and it is instantiated
when we call `Package::new()`.

However, the `Package` class provides a different static constructor that allow us to use a
custom compiler.

An example:

```php
<?php
use Inpsyde\Modularity;
use Psr\Container\ContainerInterface;

class FilteredContainer implements ContainerInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
            
    public function get(string $id): mixed
    {
        return apply_filters("my-container-service-{$id}", $this->container->get($id));
    }
}

class FilteredContainerCompiler extends Modularity\Container\ReadOnlyContainerCompiler
{
    public function compile(): ContainerInterface
    {
        return new FilteredContainer(parent::compile());
    }
}

Modularity\Package::newWithCompiler($properties, new FilteredContainerCompiler())
    ->boot(new MyModule());
```


### Custom compiler for services definition

The main reason to exist for the custom compiler is to allow "decoration" of the default read-only
container.

That could be useful for debugging, profiling, etc.

However, the custom compiler could be used for anything, including returning a container that
has new services, or services that override what is defined in modules.

In that case, **it is important the compiled container takes into account defined services, factories
and extensions** to don't break the "contract" the package has with any module registering
services.

Moreover, please keep in mind services defined in "child" containers can be extended by modules, 
whereas any service returned by the custom compiled container's `get()` method can not be extended 
anymore.

### About `Package::new()` VS `Package::newWithCompiler()`

It is worth nothing that `Package::new()` is little less than a wrapper around 
`Package::newWithCompiler()` where the container builder is constructed from given containers.

That is:

```php
Inpsyde\Modularity\Package::new($properties, $container1, $container2);
```

is equivalent to:

```php
Inpsyde\Modularity\Package::newWithCompiler(
    $properties,
    new Inpsyde\Modularity\Container\ReadOnlyContainerCompiler($container1, $container2)
);
```


