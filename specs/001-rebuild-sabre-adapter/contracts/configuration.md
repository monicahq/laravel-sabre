# Contract: Configuration

**Feature**: [../spec.md](../spec.md) | **Plan**: [../plan.md](../plan.md)

File: `config/laravelsabre.php`, published with `php artisan vendor:publish --tag=laravelsabre-config`
or by provider name. Every key works when the file is not published, through the provider's config
merge (FR-021, constitution V). The file name and all 1.x key names are frozen (FR-030).

| Key | Type | Default | Status | Behaviour |
|---|---|---|---|---|
| `domain` | string or null | `null` | Frozen | Restricts the endpoint to one domain; null means the application's own domain (FR-002) |
| `path` | string | `'dav'` | Frozen | URI prefix of the endpoint; also sets the base of DAV `href` values (FR-002, FR-011) |
| `enabled` | boolean | `env('LARAVELSABRE_ENABLED', true)` | Frozen | When false, every request under the path is 404 before any tree, plugin or provider is touched (FR-019) |
| `middleware` | list | `['web', Authorize::class]` | Frozen | Middleware for the endpoint; `Authorize` must remain present (FR-014, FR-020) |
| `methods` | list of strings | the 17 methods of FR-003 | New | Methods the endpoint accepts; add entries to support further methods such as `SEARCH` (FR-003) |
| `guard` | string or null | `null` | New | Authentication guard used to resolve the user; null means the application default (FR-033) |
| `realm` | string | `'sabre/dav'` | New | Realm named in the 401 challenge (FR-016) |
| `principal_attribute` | string | `'email'` | New | User attribute mapped into the principal when no mapper closure is registered (FR-032) |

**Rules**

- Every new key defaults to 1.x behaviour, so a config file published under 1.x keeps working
  unchanged and no deployment edit is required at upgrade (FR-022, FR-030, SC-011).
- The master switch is enforced by a middleware the package attaches to the route group itself, not by
  an entry in the `middleware` list, precisely so that a config file published under 1.x is still
  guarded (research R7).
- `methods` values are upper-cased before route registration. Adding a method is enough to route it;
  handling it is the engine's or a plugin's job.
- Changing `path` moves both the endpoint and the base of every `href` in responses, so clients see a
  consistent namespace (FR-011, US3 scenario 2).
- Environment variables: only `LARAVELSABRE_ENABLED` is read, and only through this file.
