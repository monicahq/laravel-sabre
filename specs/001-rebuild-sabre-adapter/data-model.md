# Phase 1 Data Model: Rebuild the Laravel Sabre Adapter

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-09-09

This package stores nothing persistently. Its "data" is registration and request state held for the
life of an application instance or a single request. Each entity below comes from the spec's Key
Entities section and is pinned here to concrete state, validation rules and lifecycle so that
Phase 2 can write tests against it.

## Registration State

Holder of everything an application registers. Bound as one instance per application instance, so two
applications in one process never share it (FR-034).

| Field | Type | Default | Notes |
|---|---|---|---|
| `nodes` | array of nodes, single node, prebuilt tree, or provider closure | empty array | Accepts all four shapes (FR-004) |
| `plugins` | ordered list of entries; each entry is a plugin or a provider closure | empty list | Order of registration is preserved (FR-005) |
| `accessRule` | closure taking the request and returning a boolean, or null | null | Null means admit (FR-013) |
| `principalMapper` | closure taking the user and returning a string, or null | null | Null means use the configured attribute (FR-032) |

**Validation rules**

- A node registration that is not a node, tree, or provider closure is coerced to an array of nodes,
  preserving the current behaviour for iterables.
- A plugin entry must be a plugin instance or a callable; anything else is rejected at registration
  time with a package exception naming the offending type, rather than at request time.
- Registering nodes twice replaces the previous registration. Registering plugins in bulk appends to
  the list rather than replacing it, which is what makes every ordering work (R5).
- The access rule and the principal mapper are single-valued; a second registration replaces the first.

**Lifecycle**

1. **Empty** at application boot.
2. **Registered** as consumer service providers call the facade during their own boot.
3. **Resolved** per request, after middleware, when the endpoint action asks for nodes and plugins;
   provider closures are called exactly once per request (FR-006).
4. **Discarded** with the application instance. `clear()` returns it to Empty for consumer test suites
   (FR-024).

Providers are never called in states 1 or 2, and never at all when the endpoint is disabled or access
is denied (FR-006, FR-019).

## DAV Endpoint

The single mount point. Derived entirely from configuration; it holds no mutable state.

| Field | Type | Default | Source |
|---|---|---|---|
| `path` | string | `dav` | `laravelsabre.path` |
| `domain` | string or null | null | `laravelsabre.domain` |
| `enabled` | boolean | true | `laravelsabre.enabled`, env `LARAVELSABRE_ENABLED` |
| `middleware` | list of middleware references | `['web', Authorize::class]` | `laravelsabre.middleware` |
| `methods` | list of uppercase method names | the 17 methods in FR-003 | `laravelsabre.methods` |
| `routeName` | string, fixed | `sabre.dav` | Frozen by FR-030 |
| `middlewareGroup` | string, fixed | `laravelsabre` | Frozen by FR-030 |

**Validation rules**

- `path` is normalised to a leading and trailing slash when used as the engine's base URI, and used
  verbatim as the route prefix. An empty path mounts at the application root and is supported but
  documented as cautionary (spec Edge Cases).
- `methods` entries are upper-cased. Unknown entries are permitted, because that is the extension
  mechanism (FR-003).
- Every field must have a working value when no config file is published (FR-021).

## Resource Tree

What the engine serves. Owned by the application, resolved from Registration State per request.

- **Shapes accepted**: list of nodes, one node, a prebuilt tree, or a closure returning any of those.
- **Resolution**: a closure is invoked once per request; a list becomes the engine's root collection;
  a single node or prebuilt tree is passed through unchanged.
- **Empty case**: no registration yields an empty root collection, so the engine answers its own
  "no handler" response rather than a framework error (US1 scenario 5).

## Plugin Set

The protocol extensions active for a request.

- **Entries**: plugin instances and provider closures, in registration order.
- **Resolution**: each closure is invoked once per request; results are flattened, preserving order;
  the flattened list is added to the engine one plugin at a time.
- **Invariant**: for any interleaving of bulk and single registration, every registered plugin appears
  exactly once in the resolved list, and no ordering raises (FR-005, R5).

## Access Rule

The gate that runs before any DAV work.

- **Shape**: closure receiving the framework request, returning a boolean.
- **Absent**: the request is admitted (FR-013).
- **Denied**: the response is 403 and nothing else is touched, including provider closures.
- **Throwing**: the exception propagates to the framework error handler; it is never treated as
  admitted (US2 scenario 6).
- **Evaluated by**: the `Authorize` middleware, which stays in the default middleware list.

## Principal Identity

The bridge from the application's signed-in user to a DAV principal.

| Field | Type | Default | Source |
|---|---|---|---|
| `guard` | string or null | null, meaning the application default | `laravelsabre.guard` |
| `attribute` | string | `email` | `laravelsabre.principal_attribute` |
| `mapper` | closure or null | null | Registered through the facade |
| `realm` | string | `sabre/dav` | `laravelsabre.realm` |

**Resolution order**: registered mapper, otherwise the configured attribute read from the user.

**Validation rules**

- The resolved value must be a non-empty string. Empty, null or missing raises a package exception
  naming the user identifier and the attribute or mapper used (FR-015, FR-032).
- The principal is the resolved value prefixed with `principals/`. The prefix and the email default
  are frozen by FR-030.
- No credential, token or challenge material is ever stored on this entity or logged (FR-017).

**States**: anonymous, meaning no user on the selected guard, which produces a 401 challenge naming
the realm; identified, which produces the principal string; misconfigured, which raises.

## Request Translation

Per-request state, created and discarded inside one endpoint invocation.

- **Inputs**: the framework request after middleware: method, request URI including query string,
  all headers including repeated ones, and the body as a stream.
- **Output**: an engine request whose base URI is derived from the endpoint path.
- **Invariants**: no superglobal is read (FR-007); the body is never materialised as a string during
  translation; the same inputs produce the same engine request in every environment (SC-004).

## Response Translation

- **Inputs**: engine status, headers including repeated ones, and body as string, stream or callable.
- **Output**: a framework response. A string body becomes an ordinary response; a stream or callable
  body becomes a streamed response.
- **Invariants**: a valid non-negative declared content length caps the copy exactly; an absent or
  invalid one sends the whole body; no output or header is emitted outside the framework pipeline
  (FR-008, FR-009, FR-010); body-less statuses keep their status and headers (spec Edge Cases).

## Compatibility Suite

Test fixtures, not runtime state. Recorded pairs used to prove the upgrade path (US4, SC-003).

| Field | Type | Notes |
|---|---|---|
| `name` | string | Identifies the scenario, for example `propfind-principal-depth-0` |
| `request` | method, path, headers, body | Replayed against the rebuilt package |
| `expected` | status, headers, body | Recorded from 1.x |
| `deviation` | null or a reason | Non-null only for the five deliberate defect fixes, each naming before and after behaviour |

**Validation rule**: a pair whose result differs from `expected` while `deviation` is null fails the
suite. Deviations must be listed in the migration guide (FR-029, SC-003).
