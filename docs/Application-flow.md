# The application flow

Modularity implements its application flow in two phases:

- First, the application's dependencies tree is "composed" by collecting services declared in modules, adding sub-containers, and connecting other applications.
- After that, the application dependency tree is locked, and the services are "consumed" to execute their behavior.

The `Package` class implements the two phases above, respectively, in the two methods:

- **`Package::build()`**
- **`Package::boot()`**



### Single-phase VS two-phases bootstrapping

It must be noted that **`Package::boot()`**, before proceeding with the "boot" phase, will execute the "build" phase if it hasn't been executed yet. In other words, it is not always necessary to explicitly call `Package::build()`, and many times calling `Package::boot()` will suffice.

The following two code snippets are equivalent:

```php
Package::new($properties)->build()->boot();
```

```php
Package::new($properties)->boot();
```



### Use cases for two-phased bootstrapping

There are at least two use cases for explicitly calling `Package::build()`:

- When a plugin needs to "execute" pretty late during the WordPress loading, let's say, at `"template_redirect"`, we might to call `Package::boot()` at the latest possible time, but call `Package::build()` earlier to enable other packages to connect to it.
- In unit tests, it might be desirable to access services from the container without any need to add hook via `Package::boot()`. In this specific case, the production code might only call `Package::boot()` while test might just use `Package::build()`.

Both stages are implemented through a series of *steps*, and the application status progresses as the steps are complete. In the process, a few action hooks are fired to allow external code to interact with the flow.

At any point of the flow, by holding an instance of the `Package`, it is possible to inspect the current status via `Package::statusIs()`, passing as an argument one of the `Package::STATUS_*` constants.



## The "build" phase

1. Upon instantiation, the `Package` status is at **`Package::STATUS_IDLE`**
2. Modules can be added by directly calling **`Package::addModule()`** on the instance, and other packages can be added by calling **`Package::connect()`**.
3. **`Package::build()`** is called.
4. The `Package` status moves to **`Package::STATUS_INITIALIZING`**.
5. The **`Package::ACTION_INIT`** action hook is fired, passing the package instance as an argument. That allows external code to add modules and connect other packages.
6. The `Package` status moves to **`Package::STATUS_INITIALIZED`**. No more modules can be added.
7. The **`Package::ACTION_INITIALIZED`** action hook is fired, passing the package instance as an argument. That allows external code to get services from the container.



## The "boot" phase

1. **`Package::boot()`** is called.
2. `Package` status moves to **`Package::STATUS_BOOTING`**.
3. **All executables modules run**. That is when all the application behavior happens.
4. The `Package` status moves to **`Package::STATUS_BOOTED`**.
5. The **`Package::ACTION_BOOTED`** action hook is fired, passing the package instance as an argument.
6. The `Package` status moves to **`Package::STATUS_DONE`**. The booting stage is completed. `Package::boot()` returns true.



## The "failure flow"

The steps listed above for the two stages represent the "happy paths". If any exception is thrown at any of the steps above, the flows are halted and the "failure flow" starts.



### When the failure starts during the "build" phase

1. The `Package` status moves to **`Package::STATUS_FAILED`**.
2. The **`Package::ACTION_FAILED_BUILD`** action hook is fired, passing the raised `Throwable` as an argument.
3. If the `Package`'s `Properties` instance is in "debug mode" (`Properties::isDebug()` returns `true`), the exception bubbles up, and the flow stops here.
4. If the `Properties` instance is _not_ in "debug mode", the **`Package::ACTION_FAILED_BOOT`** action hook is fired, passing a `Throwable` whose `previous` property is the `Throwable` thrown during the building stage. The "previous hierarchy" could be several levels if during the building stage many failures happened. 
5. `Package::boot()` returns false.



### When the failure starts during the "boot" phase

1. The `Package` status moves to **`Package::STATUS_FAILED`**.
2. The **`Package::ACTION_FAILED_BOOT`** action hook is fired, passing the raised `Throwable` as an argument.
3. If the `Package`'s `Properties` instance is in "debug mode" (`Properties::isDebug()` returns `true`), the exception bubbles up, and the flow stops here.
4. `Package::boot()` returns false.



## About modules passed to `Package::boot()`

Passing modules to add to `Package::boot()` has been deprecated since Modularity `v1.7.0`.

For backward compatibility, when that happens, a deprecation notice is triggered (similarly to WordPress' `_deprecated_argument`) but modules are still added.

It must be noted, that when first calling `Package::build()` and after that `Package::boot()` passing modules as argument, we will add those modules _after_ the status is already at `Package::STATUS_INITIALIZED` (because of the `Package::build()` call) and, as mentioned above, that should not be possible.

The `Package` class still deals with this scenario aiming for 100% backward compatibility, but there's an edge case. If anything that listens to the `Package::ACTION_INITIALIZED` hook accesses the container (which is an accepted and documented possibility) the compiled container will be created, which means we can't add modules to it anymore. In this specific case, calling something like `$package->build()->boot($someModule)` will end-up in an exception.

While this is a breakage of the backward compatibility promise, it is also true that `Package::build()` was introduced in `v1.7.0` when passing modules to `Package::boot()` was deprecated. Developers who have introduced `Package::build()` should also have removed any module passed to `Package::boot()`.
