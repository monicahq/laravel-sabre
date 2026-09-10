# Contract: Code-Facing Public API

**Feature**: [../spec.md](../spec.md) | **Plan**: [../plan.md](../plan.md)

This is the surface an application calls. Names listed as frozen are guaranteed by FR-030. Names
listed as redesigned may change shape in this release, and each change must appear in the migration
guide (FR-029, FR-031). Everything not listed here is internal and carries `@internal`.

## `LaravelSabre\LaravelSabre`

A facade over the application-scoped registry. Call sites keep the static style 1.x documented.

| Call | Argument | Returns | Status |
|---|---|---|---|
| `nodes($nodes)` | array of nodes, one node, a prebuilt tree, or a closure returning any of those | the registry, for chaining | Frozen name and accepted shapes |
| `plugins($plugins)` | array of plugins, or a closure returning an iterable of plugins | the registry | Frozen name; now appends instead of replacing |
| `plugin($plugin)` | one plugin instance | the registry | Frozen name; no longer raises after a closure registration |
| `auth(Closure $rule)` | closure receiving the request, returning a boolean | the registry | Frozen name |
| `principal(Closure $mapper)` | closure receiving the user, returning a non-empty string | the registry | New in this release |
| `check($request)` | the framework request | boolean | Frozen name; used by the `Authorize` middleware |
| `clear()` | none | void | Frozen name; resets registration for consumer test suites |

**Guarantees**

- Registration order never causes a failure. Any interleaving of `plugins()` and `plugin()` results in
  every registered plugin being active exactly once (FR-005).
- Closures passed to `nodes()`, `plugins()` and `principal()` are invoked at most once per request,
  after middleware (FR-006).
- Registration is scoped to the application instance. Two applications in one process never share it,
  and correctness does not depend on calling `clear()` (FR-034).
- `check()` returns true when no rule is registered (FR-013).

**Removed from the public surface**

| Removed | Replacement |
|---|---|
| `LaravelSabre::getNodes()` | Internal resolution; applications never needed it |
| `LaravelSabre::getPlugins()` | Internal resolution |
| `LaravelSabre\Exception\InvalidStateException` | No longer reachable; the state that raised it is gone (R5) |

## `LaravelSabre\LaravelSabreServiceProvider`

Frozen name, auto-discovered through the package's Composer extra. Registers the config merge, the
container bindings, the `laravelsabre` middleware group, the endpoint route, and the publishable
config file under the tag `laravelsabre-config`.

## `LaravelSabre\Http\Middleware\Authorize`

Frozen name and behaviour. Denies with 403 when the registered access rule returns false, admits
otherwise, and stays in the default `middleware` config list. It never swallows an exception raised by
the rule.

## `LaravelSabre\Http\Auth\AuthBackend`

Frozen name; implements the engine's auth backend interface.

| Member | Behaviour | Status |
|---|---|---|
| `check($request, $response)` | Resolves the user on the configured guard; returns the principal `principals/{mapped value}` | Frozen name; guard and mapping now configurable |
| `challenge($request, $response)` | Sends a Bearer challenge naming the configured realm | Frozen |
| `setRealm($realm)` | Overrides the configured realm for this instance | Frozen |

**Guarantees**: an anonymous request yields a failure result and a 401 challenge; a user whose mapped
principal value is empty raises `PrincipalResolutionException` rather than producing `principals/`
(FR-015); no credential or token is stored or logged (FR-017).

## Internal classes

`LaravelSabre\Registry`, `LaravelSabre\ServerFactory`, `LaravelSabre\Http\Controllers\DAVController`,
`LaravelSabre\Http\Middleware\EnsureEnabled`, `LaravelSabre\Http\Auth\PrincipalResolver`,
`LaravelSabre\Sabre\Server`, `LaravelSabre\Sabre\Sapi`, `LaravelSabre\Sabre\RequestFactory` and
`LaravelSabre\Sabre\ResponseFactory` are implementation detail. They are covered by unit tests but
carry no compatibility promise. In 1.x, `LaravelSabre\Sabre\Server` was effectively reachable; the
migration guide records that it is now internal.
