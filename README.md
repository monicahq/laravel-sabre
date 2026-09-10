# Sabre adapter for Laravel

Laravel-Sabre lets a Laravel application serve WebDAV, CalDAV and CardDAV clients through the
[sabre/dav](https://sabre.io) server. You supply the resource tree and the plugins; this package does
the Laravel wiring: routing, request and response translation, configuration, access control and the
bridge from your application's authentication to DAV principals.

[![Latest Version](https://img.shields.io/packagist/v/monicahq/laravel-sabre?style=flat-square&label=Latest%20Version)](https://github.com/monicahq/laravel-sabre/releases)
[![Downloads](https://img.shields.io/packagist/dt/monicahq/laravel-sabre?style=flat-square&label=Downloads)](https://packagist.org/packages/monicahq/laravel-sabre)
[![Workflow Status](https://img.shields.io/github/actions/workflow/status/monicahq/laravel-sabre/tests.yml?branch=main&style=flat-square&label=Workflow%20Status)](https://github.com/monicahq/laravel-sabre/actions?query=branch%3Amain)
[![Quality Gate](https://img.shields.io/sonar/quality_gate/monicahq_laravel-sabre?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Quality%20Gate)](https://sonarcloud.io/dashboard?id=monicahq_laravel-sabre)
[![Coverage Status](https://img.shields.io/sonar/coverage/monicahq_laravel-sabre?server=https%3A%2F%2Fsonarcloud.io&style=flat-square&label=Coverage%20Status)](https://sonarcloud.io/dashboard?id=monicahq_laravel-sabre)


# Requirements

| Requirement | Supported |
|---|---|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12, 13 (Laravel 13 requires PHP 8.3 or newer) |
| sabre/dav | 4.x |

Laravel 11 is not supported. See [MIGRATION.md](MIGRATION.md) for why and for what to do if you are
still on it.


# Installation

Install with Composer:

``` bash
composer require monicahq/laravel-sabre
```

The service provider is discovered automatically. You do not add anything to your provider list.

By default the DAV server answers at `/dav`. With nothing registered yet, a `GET` there returns the
DAV server's own "no plugin handled this method" answer, which confirms the wiring works:

``` bash
curl -i http://localhost:8000/dav
```


# Quick start

Register your resource tree and your plugins from any service provider's `boot()` method:

``` php
use Illuminate\Support\ServiceProvider;
use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\LaravelSabre;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAVACL\PrincipalBackend\PDO as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

class DAVServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        LaravelSabre::nodes(function () {
            return [new PrincipalCollection(new PrincipalBackend(/* ... */))];
        });

        LaravelSabre::plugins(function () {
            yield new AuthPlugin(new AuthBackend());
            yield new CardDAVPlugin();
        });
    }
}
```

Then ask the server for a principal:

``` bash
curl -i -X PROPFIND -H 'Depth: 0' http://localhost:8000/dav/principals/admin
```

You should receive `207 Multi-Status` with a DAV multistatus document.


# Configuration

Publishing the config file is optional. Every setting has a working default:

``` sh
php artisan vendor:publish --tag=laravelsabre-config
```

The `laravelsabre-config` tag and the provider name both work:

``` sh
php artisan vendor:publish --provider="LaravelSabre\LaravelSabreServiceProvider"
```

| Option | Default | Purpose |
|---|---|---|
| `domain` | `null` | Restricts the endpoint to one domain. Null serves it on the application's own domain. |
| `path` | `'dav'` | URI path of the endpoint, and the base of DAV `href` values in responses. |
| `enabled` | `env('LARAVELSABRE_ENABLED', true)` | Master switch. When false, every request under the path is answered with 404 before any node, plugin or provider is touched. |
| `middleware` | `['web', Authorize::class]` | Middleware applied to the endpoint. |
| `methods` | the 17 DAV methods | HTTP methods the endpoint accepts. |
| `guard` | `null` | Guard used to resolve the signed in user. Null uses the application default. |
| `realm` | `'sabre/dav'` | Realm named in the authentication challenge. |
| `principal_attribute` | `'email'` | User attribute mapped into the DAV principal. |

A config file published under an earlier version keeps working: settings added later are merged
underneath it and default to the previous behaviour.

## Path and URL

`path` sets where the endpoint answers, and DAV `href` values in every response follow it, so clients
see a consistent namespace. Generate the URL from the route name rather than hardcoding it:

``` php
$url = route('sabre.dav');                                  // http://example.com/dav
$url = route('sabre.dav', ['path' => 'principals/admin']);  // http://example.com/dav/principals/admin
```

Setting `path` to an empty string mounts the endpoint at the application root. This is supported, but
be careful: the endpoint then answers every path that no earlier route matched.

## HTTP methods

The `methods` option lists the HTTP methods the endpoint accepts. The default covers WebDAV, CalDAV
and CardDAV: `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `PROPFIND`, `PROPPATCH`,
`MKCOL`, `MKCALENDAR`, `COPY`, `MOVE`, `LOCK`, `UNLOCK`, `REPORT` and `ACL`.

Add an entry to serve a further method without forking the package. Routing it is all this package
does; handling it is up to the DAV server and its plugins:

``` php
'methods' => ['GET', 'HEAD', 'OPTIONS', 'PROPFIND', 'REPORT', 'SEARCH'],
```


# Usage

## Nodes

`LaravelSabre::nodes()` registers the resource tree. It accepts an array of nodes, a single node, a
prebuilt `Sabre\DAV\Tree`, or a closure returning any of those:

``` php
LaravelSabre::nodes([new PrincipalCollection($principalBackend)]);   // array of nodes
LaravelSabre::nodes(new PrincipalCollection($principalBackend));     // one node
LaravelSabre::nodes(new Tree(new Directory('/srv/dav')));            // prebuilt tree
LaravelSabre::nodes(fn () => [new PrincipalCollection($backend)]);   // resolved per request
```

Pass a closure when the tree depends on the signed in user. Closures run once per request, after
middleware, so `auth()->user()` is available inside them. Registering nodes again replaces the
previous registration.

## Plugins

`LaravelSabre::plugins()` registers plugins in bulk, from an array, a generator or a closure.
`LaravelSabre::plugin()` registers one. Both append, so you can mix them in any order and every
registered plugin is active:

``` php
LaravelSabre::plugins([new CardDAVPlugin(), new CalDAVPlugin()]);
LaravelSabre::plugin(new SyncPlugin());

LaravelSabre::plugins(function () {
    yield new AuthPlugin(new AuthBackend());
});
```

A closure runs once per request, after middleware. Anything that is not a `Sabre\DAV\ServerPlugin` is
rejected at registration time with `LaravelSabre\Exception\LaravelSabreException`.

## Access control

`LaravelSabre::auth()` decides who may use the endpoint. The closure receives the request and returns
a boolean. When it returns false the request is refused with 403, before any node, plugin or provider
is touched. When no rule is registered every request is admitted, and authentication is left to your
middleware stack and to the DAV auth plugin.

``` php
LaravelSabre::auth(function ($request) {
    return $request->user()?->email === 'admin@admin.com';
});
```

The gate is the `LaravelSabre\Http\Middleware\Authorize` middleware, part of the default `middleware`
list. An exception thrown by your rule is never treated as an admission: it surfaces through your
application's error handling.

## Authentication and principals

`LaravelSabre\Http\Auth\AuthBackend` bridges your application's authentication to the DAV server, so a
user who is already signed in never re-enters credentials:

``` php
LaravelSabre::plugin(new AuthPlugin(new AuthBackend()));
```

The signed in user is resolved on the guard named by `guard`, which defaults to your application's own
default guard. An anonymous request is answered with 401 and a Bearer challenge naming `realm`.

The principal identifier is `principals/{value}`, where the value comes from the user attribute named
by `principal_attribute`, which defaults to `email`. For anything more involved, register a mapping:

``` php
LaravelSabre::principal(function ($user) {
    return 'uid/'.$user->getKey();
});
```

A registered mapping takes precedence over the configured attribute. If the mapping or the attribute
yields nothing usable, the package raises
`LaravelSabre\Exception\PrincipalResolutionException` naming the user and the source, rather than
producing the empty principal `principals/`.

No credential, token or challenge material is ever stored or logged by this package.

## Testing your integration

`LaravelSabre::clear()` forgets every registration. Registration is scoped to the application
instance, so tests that build a fresh application do not need it, but it is available for suites that
register different trees within one application.


# Upgrading from 1.x

No change to your configuration, environment variables or URLs is required. Application code that
registers the resource tree, plugins or access rule may need small changes, and every one of them is
listed in [MIGRATION.md](MIGRATION.md), together with the defects this release fixes.


# License

Author: [Alexis Saettler](https://github.com/monicahq)

This project is part of [MonicaHQ](https://github.com/monicahq/).

Copyright © 2019–2026.

Licensed under the MIT License. [View license](/LICENSE.md).
