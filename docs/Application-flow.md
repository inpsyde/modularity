# The application flow

Modularity implements its application flow in two stages:

- First, the application's dependencies tree is "composed" by collecting services declared in modules, adding sub-containers, and connecting other applications.
- After that, the application dependency tree is locked, and the services are "consumed" to execute their behavior.

The `Package` class implements the two stages above, respectively, in the two methods:

- **`Package::build()`**
- **`Package::boot()`**

For convenience, `Package::boot()` is "smart enough" to call `build()` if it was not called before, so the following code (that makes the two stages evident):

```php
Package::new($properties)->build()->boot();
```

is entirely equivalent to the following:

```php
Package::new($properties)->boot();
```

Both stages are implemented through a series of *steps*, and the application status progresses as the steps are complete. In the process, a few action hooks are fired to allow external code to interact with the flow.

At any point of the flow, by holding an instance of the `Package` is possible to inspect the current status via `Package::statusIs()`, passing as an argument one of the `Package::STATUS_*` constants.


## Building stage

1. Upon instantiation, the `Package` status is at **`Package::STATUS_IDLE`**
2. Default modules can be added by calling **`Package::addModule()`** on the instance.
3. The **`Package::ACTION_INIT`** action hook is fired, passing the package instance as an argument. That allows external code to add modules.
4. The `Package` status moves to **`Package::STATUS_INITIALIZED`**. The "building" stage is completed, and no more modules can be added.


## Booting stage

1. When the booting stage begins, the `Package` status moves to **`Package::STATUS_MODULES_ADDED`**.
2. A read-only PSR-11 container is created. It can lazily resolve the dependency tree defined in the previous stage.
3. **All executables modules run**. That is when all the application behavior happens. Note: Because the container is "lazy", only the consumed services are resolved. The `Package` never executes factory callbacks for services "registered" in the previous stage but not used in this stage.
4. The `Package` status moves to **`Package::STATUS_READY`**.
5. The **`Package::ACTION_READY`** action hook is fired, passing the package instance as an argument. External code hooking that action can access the read-only container instance, resolve services, and perform additional actions but not register modules.
6. The `Package` status moves to **`Package::STATUS_BOOTED`**. The booting stage is completed. `Package::boot()` returns true.


## The "failure flow"

The steps listed above for the two stages represent the "happy paths". If any exception is thrown at any of the steps above, the flows are halted and the "failure flow" starts.

### When the failure starts during the "building" stage

1. The `Package` status moves to **`Package::STATUS_FAILED`**.
2. The **`Package::ACTION_FAILED_BUILD`** action hook is fired, passing the raised `Throwable` as an argument.
3. If the `Package`'s `Properties` instance is in "debug mode" (`Properties::isDebug()` returns `true`), the exception bubbles up, and the flow stops here.
4. If the `Properties` instance is _not_ in "debug mode", the **`Package::ACTION_FAILED_BOOT`** action hook is fired, passing a `Throwable` whose `previous` property is the `Throwable` thrown during the building stage. The "previous hierarchy" could be several levels if during the building stage many failures happened. 
5. `Package::boot()` returns false.

### When the failure starts during the "booting" stage

1. The `Package` status moves to **`Package::STATUS_FAILED`**.
2. The **`Package::ACTION_FAILED_BOOT`** action hook is fired, passing the raised `Throwable` as an argument.
3. If the `Package`'s `Properties` instance is in "debug mode" (`Properties::isDebug()` returns `true`), the exception bubbles up, and the flow stops here.
4. `Package::boot()` returns false.


## A note about default modules passed to boot()

The `Package::boot()` method accepts a list of modules. That has been deprecated since Modularity v1.7.

Considering that `Package::boot()` represents the "booting" stage that is supposed to happen *after* the "building" stage, it might be hard to figure out where the addition of those modules fits in the flows described above.

When `Package::boot()` is called without calling `Package::build()` first, as in:

```php
Package::new($properties)->boot(new ModuleOne(), new ModuleTwo());
```

The code is equivalent to the following:

```php
Package::new($properties)->addModule(new ModuleOne())->addModule(new ModuleTwo())->boot();
```

So the "building" flow is respected.

However, when `Package::boot()` is called after `Package::build()`, as in:

```php
Package::new($properties)->build()->boot(new ModuleOne(), new ModuleTwo());
```

The `Package` is at the end of the "building" flow after `Package::build()` is called, but it must "jump" back in the middle of "building" flow to add the modules.

In fact, after `Package::build()` is called the application status is at `Package::STATUS_INITIALIZED`, and no more modules can be added.

However, for backward compatibility reasons, in that case, the `Package` temporarily "hacks" the status back to `Package::STATUS_IDLE` so modules can be added, and then resets it to `Package::STATUS_INITIALIZED` so that the "booting" stage can start as usual.

This "hack" is why passing modules to `Package::boot()` has been deprecated and will be removed in the next major version when backward compatibility breaks are allowed.
