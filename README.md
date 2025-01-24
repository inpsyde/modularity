# Inpsyde Modularity

[![Version](https://img.shields.io/packagist/v/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)
[![Status](https://img.shields.io/badge/status-active-brightgreen.svg)](https://github.com/inpsyde/modularity)
[![codecov](https://codecov.io/gh/inpsyde/modularity/branch/master/graph/badge.svg)](https://codecov.io/gh/inpsyde/modularity)
[![Downloads](https://img.shields.io/packagist/dt/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)
[![License](https://img.shields.io/packagist/l/inpsyde/modularity.svg)](https://packagist.org/packages/inpsyde/modularity)

## Introduction

inpsyde/modularity is a modular [PSR-11](https://github.com/php-fig/container) implementation for WordPress Plugins,
Themes or Libraries.

## Installation

```shell
composer require inpsyde/modularity
```

## Minimum Requirements and Dependencies

* PHP 7.4+

When installed for development via Composer, the package also requires:

* inpsyde/php-coding-standards
* roots/wordpress
* phpstan/phpstan
* phpunit/phpunit
* brain/monkey
* mikey179/vfsstream

## Documentation

1. [Package](docs/Package.md)
2. [PSR-11 Container](docs/PSR-11-Container.md)
3. [Modules](docs/Modules.md)
4. [Properties](docs/Properties.md)
5. [Application Flow](docs/Application-flow.md)

## License

This repository is a free software, and is released under the terms of the GNU General Public License version 2 or (at your option) any later version. See [LICENSE](./LICENSE) for complete license.
