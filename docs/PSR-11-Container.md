# Default Container based on PSR-11
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
