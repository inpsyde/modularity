# Properties
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
| Properties::authorUri() | Author URI | Author URI | authors[0].homepage |
| Properties::description() | Description | Description | description |
| Properties::domainPath() | Domain Path | Domain Path | extra.modularity.domainPath |
| Properties::name() | Theme Name | Plugin Name | extra.modularity.name |
| Properties::textDomain() | Text Domain | Text Domain | extra.modularity.textDomain |
| Properties::uri() | Theme URI | Plugin URI | extra.modularity.uri |
| Properties::version() | Version | Version | version<br>extra.modularity.version |
| Properties::requiresWp() | Requires at least | Requires at least | extra.modularity.requiresWp |
| Properties::requiresPhp() | Requires PHP | Requires PHP | require.php<br>require-dev.php |
| Properties::baseUrl() | WP_Theme::get_stylesheet_directory_uri() | plugins_url() |  |
| Properties::network() |  | Network |  |
| Properties::status() | Status |  |  |
| Properties::tags() | Tags |  | keywords |
| Properties::template() | Template |  |  |



### Accessing connected packages' properties 

When we have packages connected via `Package::connect()`,  to access connected packages' properties, we could do that using a container key whose format is: `sprintf('%s.%s', $connectedPackage->name(), Package::PROPERTIES)`.



## PluginProperties

Inside your Plugin you can use the following code to automatically generate Properties based on the [Plugins Header](https://developer.wordpress.org/reference/functions/get_plugin_data/):

```php
<?php
use Inpsyde\Modularity\Properties;

$properties = Properties\PluginProperties::new('/path/to/plugin-main-file.php');
```

Additionally, PluginProperties will have the following public API:

- `PluginProperties::pluginMainFile(): string` - returns the Plugin main file.
- `PluginProperties::network(): bool` - returns if the Plugin is only network-wide usable.
- `PluginProperties::isActive(): bool` - returns if the current Plugin is active.
- `PluginProperties::isNetworkActive(): bool` - returns if the current Plugin is network-wide active.
- `PluginProperties::isMuPlugin(): bool` - returns if the current Plugin is a must-use Plugin.

Please note that our usage of `get_plugin_data` opts out of translations and HTML-safe text processing (via `wptexturize`) offered by default.
These functions should not be used before the 'init' hook which may be too late for some applications.

## ThemeProperties

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



## LibraryProperties

For libraries, you can use the LibraryProperties which give you context based on your composer.json. You can bootstrap your standalone-library like following:

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
