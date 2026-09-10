# Contract: HTTP Endpoint

**Feature**: [../spec.md](../spec.md) | **Plan**: [../plan.md](../plan.md)

One route, named `sabre.dav` (frozen), mounted at the configured path with an optional domain,
matching every path beneath it including nested segments, encoded characters, trailing slashes and
query strings. `route('sabre.dav')` returns the endpoint URL whether or not the endpoint is enabled
(FR-012).

## Accepted methods

`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `PROPFIND`, `PROPPATCH`, `MKCOL`,
`MKCALENDAR`, `COPY`, `MOVE`, `LOCK`, `UNLOCK`, `REPORT`, `ACL`, plus anything added to the `methods`
config key. Each one reaches the engine; none is answered by the framework's method-not-allowed or
not-found handler (FR-003, US1 scenario 2).

## Request handling guarantees

| Guarantee | Detail |
|---|---|
| Source of truth | The engine receives the framework request as it stands after middleware: method, request URI with query string, all headers, body as a stream (FR-007) |
| Environment independence | Identical in testing, local and production; no superglobal is read in any environment (FR-007, SC-004) |
| Base URI | Derived from the configured path, so DAV `href` values are relative to it (FR-011) |
| Provider timing | Node and plugin providers run once per request, after middleware, so they may depend on the signed-in user (FR-006) |

## Response handling guarantees

| Guarantee | Detail |
|---|---|
| Pipeline | Status, all headers including repeated ones, and body are returned through the framework response pipeline, so application middleware can observe and alter them (FR-008) |
| Streaming | Stream and callable bodies are sent without materialising the body; a valid declared content length caps the transfer exactly (FR-009, SC-006) |
| No side channel | Nothing is written to output or headers outside the pipeline, including from the engine's own error path (FR-010, research R8) |
| Body-less statuses | 201 with a location, 204 and 304 keep status and headers, with no body added |

## Status codes

| Status | When |
|---|---|
| 207 | Multistatus responses from the engine, for example `PROPFIND` on a principal |
| 401 | The engine's auth plugin challenges; header names the configured realm (FR-016) |
| 403 | The registered access rule denied; no DAV processing and no provider evaluation happened (FR-013) |
| 404 | The `enabled` switch is off, for every path under the endpoint (FR-019) |
| 501 | No plugin handles the method, for example `GET` on the root with nothing registered (US1 scenario 5) |
| 5xx | Engine error, rendered as the engine's own error document; stack traces only outside production (FR-018) |

Every response the engine produces carries its version header, which is how tests distinguish an
engine answer from a framework answer.

## Isolation

Two requests served by one long-lived worker share no headers, body, identity or configuration. One
thousand sequential requests alternating between two users each see only their own identity
(FR-024, FR-034, SC-005).
