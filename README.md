# Sabre adapter for Laravel

Laravel-Sabre is an adapter to use Sabre.io DAV Server on Laravel.

[![Latest Version](https://img.shields.io/packagist/v/monicahq/laravel-sabre?style=flat-square&label=Latest%20Version)](https://github.com/monicahq/laravel-sabre/releases)
[![Downloads](https://img.shields.io/packagist/dt/monicahq/laravel-sabre?style=flat-square&label=Downloads)](https://packagist.org/packages/monicahq/laravel-sabre)
[![Workflow Status](https://img.shields.io/github/workflow/status/monicahq/laravel-sabre/Unit%20tests?style=flat-square&label=Workflow%20Status)](https://github.com/monicahq/laravel-sabre/actions?query=branch%3Amain)
[![Quality Gate](https://img.shields.io/sonar/quality_gate/monicahq_laravel-sabre?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Quality%20Gate)](https://sonarcloud.io/dashboard?id=monicahq_laravel-sabre)
[![Coverage Status](https://img.shields.io/sonar/coverage/monicahq_laravel-sabre?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Coverage%20Status)](https://sonarcloud.io/dashboard?id=monicahq_laravel-sabre)

# Installation

You may use Composer to install this package into your Laravel project:

``` bash
composer require monicahq/laravel-sabre
```

You don't need to add this package to your service providers.


## Configuration

If you want, you can publish the package config file to `config/laravelsabre.php`:

```sh
php artisan vendor:publish --provider="LaravelSabre\LaravelSabreServiceProvider"
```

If desired, you may disable LaravelSabre entirely using the `enabled` configuration option:
``` php
'enabled' => env('LARAVELSABRE_ENABLED', true),
```

Change the `path` configuration to set the url path where the Sabre server will answer to.


# Usage

Use `LaravelSabre\LaravelSabre` class to add node collection and plugins to the Sabre server.

In the example above, `DAVServiceProvider` is a service provider that has been added to the list of providers in `config/app.php` file.


## Nodes
`LaravelSabre::nodes()` is used to add nodes collection to the Sabre server.

It may be an array, or a callback function, like in this example here:

Example:
``` php
use LaravelSabre\LaravelSabre;
use Sabre\DAVACL\PrincipalCollection;
use Sabre\DAVACL\PrincipalBackend\PDO as PrincipalBackend;

class DAVServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        LaravelSabre::nodes(function () {
            return $this->nodes();
        });
    }

    /**
     * List of nodes for DAV Collection.
     */
    private function nodes() : array
    {
        $principalBackend = new PrincipalBackend();

        return [
            new PrincipalCollection($principalBackend),
        ];
    }
}
```


## Plugins

You can use either:
- `LaravelSbre::plugins()` to define a new array of plugins to add to the Sabre server. It may be a callback function.
- or `LaravelSbre::plugin()` to add 1 plugin to the list of plugins.

Example:
``` php
use LaravelSabre\LaravelSabre;
use LaravelSabre\Http\Auth\AuthBackend;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\CardDAV\Plugin as CardDAVPlugin;

class DAVServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        LaravelSabre::plugins(function () {
            return $this->plugins();
        });
    }

    /**
     * List of Sabre plugins.
     */
    private function plugins()
    {
        // Authentication backend
        $authBackend = new AuthBackend();
        yield new AuthPlugin($authBackend);

        // CardDAV plugin
        yield new CardDAVPlugin();
    }
}
```


## Auth

Use the `LaravelSabre::auth()` method with the `Authorize::class` middleware gate, to allow access to some people, based on some criteria.

Example:
``` php
LaravelSabre::auth(function () {
    return auth()->user()->email == 'admin@admin.com';
})
```


# License

Author: [Alexis Saettler](https://github.com/monicahq)

This project is part of [MonicaHQ](https://github.com/monicahq/).

Copyright © 2019–2022.

Licensed under the MIT License. [View license](/LICENSE.md).
