# Upgrading from 1.x

## Laravel 11 is no longer supported

This release supports **PHP 8.2, 8.3 and 8.4 with Laravel 12 or 13**. Laravel 11 is dropped.

The reason is a security advisory: `roave/security-advisories` now conflicts with
`illuminate/mail >=9,<12.60`, and `laravel/framework` replaces `illuminate/mail`, so every Laravel 11
release is covered by it. A Laravel 11 application therefore cannot be installed or tested alongside
the advisory database, which means the package can no longer prove it works there.

If you are on Laravel 11, upgrade the framework first, then this package. Version 1.x continues to
work on Laravel 11, but it is affected by the same advisory.

## Everything else

The deployment-facing surface is unchanged. **No change to your configuration files, environment
variables or client-facing URLs is required.** Contacts and calendar clients keep syncing against the
same URLs, with the same principals and the same authentication behaviour.

What may need a change is application code that registers the DAV resource tree, plugins or access
rule. Everything that changed there is listed below. Nothing removed is kept as a deprecated
pass-through: a removed element fails immediately and visibly.

## Unchanged, guaranteed

| Element | Value |
|---|---|
| Config file | `config/laravelsabre.php`, publish tag `laravelsabre-config` |
| Config keys | `domain`, `path`, `enabled`, `middleware` |
| Environment variable | `LARAVELSABRE_ENABLED` |
| Route name | `sabre.dav` |
| Middleware group | `laravelsabre` |
| Default path | `dav` |
| Endpoint URLs | Unchanged for a given path and domain |
| Default principal | `principals/{user email}` |
| Classes | `LaravelSabre`, `LaravelSabreServiceProvider`, `Http\Middleware\Authorize`, `Http\Auth\AuthBackend` |
| Registration calls | `LaravelSabre::nodes()`, `plugins()`, `plugin()`, `auth()`, `check()`, `clear()` |

## Removed, with replacements

| Removed | Replacement |
|---|---|
| `LaravelSabre::getNodes()` | None needed. Resolving the tree is internal to the package. |
| `LaravelSabre::getPlugins()` | None needed. Resolving plugins is internal to the package. |
| `LaravelSabre\Exception\InvalidStateException` | Unreachable and removed: mixing `plugins()` and `plugin()` now works in any order. |
| `LaravelSabre\Sabre\Server` as a reachable class | Now internal. Applications never constructed it; `Server::setRequest()` and `Server::getResponse()` are replaced by an internal `handle()`. |
| `LaravelSabre\Sabre\Sapi` as a reachable class | Now internal. |

## Changed behaviour you may notice

| Change | Before | After |
|---|---|---|
| `plugins()` with an array or closure | Replaced whatever was registered | Appends, so bulk and single registration can be interleaved in any order |
| Return value of the registration calls | A new `LaravelSabre` instance | The registry instance. Chaining still works. |
| Registration storage | Static properties shared by the whole process | Bound to the application instance, so two applications in one process keep separate registrations and no reset is needed between them |
| Invalid plugin argument | Accepted, then failed inside the DAV server | Rejected at registration time with `LaravelSabre\Exception\LaravelSabreException` naming the type |
| Framework verb list | Extended so `Route::any()` covered DAV methods | Still extended for compatibility, but the endpoint no longer depends on it. Configure `methods` instead of relying on the extension. |

## Fixed defects

These are deliberate behaviour changes. Each one is a fix the previous release could not make without
breaking its own tests.

| Area | Before | After |
|---|---|---|
| `MKCALENDAR` | Rejected with 405 by the router, never reached the DAV server | Routed, so the server answers |
| `ACL` | Rejected with 405 by the router, never reached the DAV server | Routed, so the server answers |
| Request source | Outside the testing environment the DAV server read the raw process request, so method, body and headers could differ from what your middleware produced | The framework request after middleware, in every environment |
| Large downloads | The whole body was buffered into memory before sending | Copied stream to stream. A 100 MB download now costs under 20 MB of peak memory instead of about 98 MB |
| Mixed plugin registration | `plugin()` after `plugins(Closure)` threw `InvalidStateException` | Every ordering works |
| User without an email address | Produced the principal `principals/`, which clients failed on in confusing ways | Raises `LaravelSabre\Exception\PrincipalResolutionException` naming the user and the source |

## New, all defaulting to the previous behaviour

| Addition | Default | Purpose |
|---|---|---|
| `methods` config key | the 17 DAV methods | Accept a further method, such as `SEARCH`, without forking |
| `guard` config key | `null`, the application default guard | Resolve the DAV user on a specific guard |
| `realm` config key | `'sabre/dav'` | Name the realm in the authentication challenge |
| `principal_attribute` config key | `'email'` | Map a different user attribute into the principal |
| `LaravelSabre::principal()` | not registered | Map the user to a principal with your own logic |
| `LaravelSabre\Exception\LaravelSabreException` | n/a | Base class for every exception this package raises |

Ignoring all of these leaves behaviour exactly as it was.

## Checklist

1. Upgrade the package. Change no configuration.
2. Search your codebase for `getNodes(`, `getPlugins(` and `InvalidStateException`. Remove them; none has a replacement you need.
3. If you registered plugins twice with `plugins()` and relied on the second call replacing the first, call `clear()` first or merge the lists yourself.
4. Run your test suite. If it called `LaravelSabre::clear()` in teardown, you can keep that; it is no longer required for correctness.
5. Sync one contacts client and one calendar client against a staging deployment before releasing.
