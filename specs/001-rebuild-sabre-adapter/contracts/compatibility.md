# Contract: Compatibility With 1.x

**Feature**: [../spec.md](../spec.md) | **Plan**: [../plan.md](../plan.md)

The clarified compatibility contract: the deployment-facing surface is frozen, the code-facing surface
may be redesigned, and there is no deprecated compatibility layer (spec Clarifications, FR-030,
FR-031). This file is the source for the migration guide FR-029 requires.

## Frozen: no deployment change at upgrade

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
| Public class names | `LaravelSabre`, `LaravelSabreServiceProvider`, `Http\Middleware\Authorize`, `Http\Auth\AuthBackend` |
| Facade calls | `nodes()`, `plugins()`, `plugin()`, `auth()`, `check()`, `clear()` |

## Removed or changed, with replacements

| Element | Change | Replacement |
|---|---|---|
| `LaravelSabre::getNodes()` | Removed from the public surface | None needed; resolution is internal |
| `LaravelSabre::getPlugins()` | Removed from the public surface | None needed |
| `Exception\InvalidStateException` | Removed | Unreachable: mixed plugin registration now works |
| `LaravelSabre\Sabre\Server` | Now internal | None; applications never constructed it |
| `LaravelSabre\Sabre\Sapi` | Now internal | None |
| Return value of the facade setters | The registry instead of a new facade instance | Chaining still works |
| Framework verb list | Still merged for parity, but dispatch no longer depends on it | Configure `methods` instead of relying on the merge |

No removed element is retained as a deprecated pass-through. Each fails visibly (FR-031).

## Deliberate behaviour changes

These are the five fixes the spec authorises. Each needs a compatibility-suite entry marked as a
deviation with before and after behaviour (SC-003).

| Area | 1.x behaviour | Rebuilt behaviour |
|---|---|---|
| Request source | Outside the testing environment the engine read the raw process request | The framework request after middleware, in every environment |
| Streaming | Bodies were buffered whole with a string read | Copied stream to stream, honouring a valid declared length |
| Methods | `MKCALENDAR` and `ACL` were rejected before reaching the engine | Both accepted, and the list is configurable |
| Plugin registration | `plugin()` after `plugins(Closure)` raised | Every ordering works |
| Missing email | Produced the principal `principals/` | Raises a clear exception naming the user and the attribute |

## Additive, defaults unchanged

`methods`, `guard`, `realm` and `principal_attribute` config keys, and the `principal()` registration
call. All default to 1.x behaviour, so an application that ignores them sees no change (FR-022).

## Upgrade acceptance

An upgrade passes when the compatibility suite reproduces 1.x status, headers and body for every
recorded pair except the deviations above, the reference consumer's clients keep syncing, and the only
changes the guide prescribes are in application code that registers the tree, plugins or access rule
(SC-003, SC-010, SC-011).
