# Phase 0 Research: Rebuild the Laravel Sabre Adapter

**Feature**: [spec.md](./spec.md) | **Date**: 2026-09-09 | **Plan**: [plan.md](./plan.md)

All Technical Context items were resolvable from the repository, the constitution and the clarified
spec; no `NEEDS CLARIFICATION` remained at entry. Research therefore focused on verifying the four
mechanisms the rebuild depends on, because each one is the direct cause of a defect the spec asks to
fix. Every decision below was checked against the versions Composer actually resolves today:
laravel/framework 13.31.0, sabre/dav 4.7.1, sabre/http 5.1.13, orchestra/testbench 11.2.0,
phpunit 12.5.35, thecodingmachine/safe 3.4.0, PHP 8.4.25.

Verification used a throwaway probe test run through Orchestra Testbench and a standalone memory
probe. Both were deleted after the run; their results are recorded here as evidence and become real
tests during implementation (FR-027).

## R1. Delivering the application's request to the engine in every environment (FR-007)

**Decision**: Build a `Sabre\HTTP\Request` explicitly from the Laravel request and assign it to the
server, and inject a package-owned SAPI through the `Sabre\DAV\Server` constructor's second
argument. Never call the upstream SAPI's request reader, and never branch on the environment.

**Rationale**: `Sabre\DAV\Server::__construct($treeOrNode, ?HTTP\Sapi $sapi)` ends with
`$this->httpRequest = $this->sapi->getRequest()`, and `Sabre\HTTP\Sapi::getRequest()` reads
`$_SERVER`, `php://input` and `$_POST`. That single line is why 1.x sees the raw process request:
construction itself imports the superglobals, and the 1.x `setRequest()` only overrides method, body
and headers when the environment is `testing`. Injecting a SAPI whose reader returns an empty request
removes the superglobal dependency at the source, so one code path serves testing, local and
production, and long-lived workers stay correct.

**Verified**: with `$_SERVER['REQUEST_URI']` and `$_SERVER['REQUEST_METHOD']` unset, a server built
with an injected SAPI plus a request assembled from `Illuminate\Http\Request` returned 207 with an
`X-Sabre-Version` header and a multistatus body, and the injected reader was consulted exactly once
(by the constructor). Request construction used the Laravel method, request URI, all headers, and
`getContent(true)`, which yields a stream resource.

**Alternatives considered**: overriding the static reader to translate the Laravel request, rejected
because `Sapi::getRequest()` is static and cannot reach instance or container state; priming
`$_SERVER` from the Laravel request before construction, rejected because it keeps a process-global
dependency and cannot represent a streamed body; keeping the environment branch, rejected because it
leaves the production path untested, which is the defect.

## R2. Accepting DAV methods without mutating framework globals (FR-003, FR-034)

**Decision**: Register the endpoint with `Route::match($methods, '{path?}', ...)` where `$methods`
comes from a new `methods` config key, and keep merging those methods into the router's verb list for
parity only, never depending on it.

**Rationale**: The router's static verb list is read in exactly two places in the framework: the
`any()` helper and the 405 alternate-verb probe used when no route matches. Nothing in dispatch
consults it, because `addRoute()` indexes routes by whatever method strings it is given. So
`match()` accepts `PROPFIND`, `MKCALENDAR`, `ACL` and any application-supplied extension directly.
Keeping the merge preserves two 1.x side effects that applications may rely on: a host application's
own `Route::any()` still covers DAV verbs, and the framework's 405 diagnostics still recognise them.
The merge is an idempotent boot-time operation, not per-request state, so it does not conflict with
application-scoped registration.

**Verified**: with the verb list reset to the framework default of GET, HEAD, POST, PUT, PATCH,
DELETE and OPTIONS, a route registered through `match()` answered `PROPFIND`, `MKCALENDAR`, `ACL` and
`REPORT` on a deep nested path with status 200, and the verb list was still free of those methods
afterwards. This is what makes `MKCALENDAR` and `ACL` support a configuration entry rather than a
patch to a framework global.

**Alternatives considered**: keeping `Route::any()` plus the verb mutation, rejected because the
method list then cannot be extended per application without touching framework internals; a catch-all
`Route::addRoute()` call built by hand, rejected as equivalent to `match()` with less clarity.

## R3. Streaming response bodies without buffering (FR-009, SC-006)

**Decision**: Translate the engine response into a streamed Laravel response whose callback copies
the engine's stream straight to output with `Safe\stream_copy_to_stream`, passing the declared content
length as the copy limit when it is a valid non-negative integer, and no limit otherwise. Strings
keep using an ordinary response; callable bodies are invoked inside the streaming callback and write
directly to output.

**Rationale**: 1.x calls `stream_get_contents`, which materialises the whole body as a PHP string
before the response is sent. A stream-to-stream copy holds only one chunk at a time and is the same
mechanism the engine expects a SAPI to use.

**Measured**: copying a 100 MB body to output raised peak memory by 0.00 MB, while
`stream_get_contents` on the same body raised it by 98.00 MB. This is the mechanism behind SC-006's
budget of under 20 MB for a 100 MB download, with the whole budget left for framework overhead.

**Alternatives considered**: `fpassthru`, rejected because it cannot honour a declared length; manual
`fread` loops, rejected as a hand-rolled copy with no advantage; buffering with output-buffer helpers
as 1.x does for callable bodies, kept only for the callable case where the engine writes with `echo`,
and even there the buffer is flushed per callback rather than accumulated.

## R4. Application-scoped registration state (FR-034, FR-024, US5 scenario 5)

**Decision**: Hold nodes, plugins, the access rule and the principal mapper in a `Registry` object
bound as a singleton in the service container. Keep `LaravelSabre` as the public entry point,
implemented as a framework facade that resolves that singleton, so documented call sites such as
`LaravelSabre::nodes(...)` and `LaravelSabre::plugin(...)` keep working unchanged.

**Rationale**: 1.x keeps registration in private static properties, so two applications built in one
process share and overwrite each other's registration, and tests must call `clear()` in teardown for
correctness. Container-bound state is discarded with the application instance, which is what both the
test suite and long-lived workers need. A facade keeps the call style that the README, Monica and
every existing service provider already use, so this structural change costs nothing at the call site.

**Alternatives considered**: static properties plus a mandatory reset, rejected because correctness
then depends on discipline in consumer test suites; injecting the registry into consumer service
providers, rejected because it breaks every documented call site for no behavioural gain; a scoped
binding refreshed per request, rejected because registration happens once at boot while resolution
must happen per request, which the registry already separates.

## R5. Mixed plugin registration (FR-005)

**Decision**: Store plugin registrations as an ordered list of entries, where each entry is either a
plugin instance or a deferred provider, and flatten the list at resolution time. Single and bulk
registration append to the same list in any order.

**Rationale**: 1.x stores either an array or a single closure in one property, so a bulk closure
registration followed by `plugin()` throws, and a `plugin()` call before a bulk registration is
silently discarded. A list of entries removes the state machine that produces both faults, so the
package no longer needs its `InvalidStateException`.

**Alternatives considered**: throwing a clearer error, rejected because the spec requires every
ordering to work; merging closures eagerly at registration time, rejected because deferred providers
must run per request after middleware.

## R6. Identity, guard and realm (FR-015, FR-016, FR-032, FR-033)

**Decision**: Resolve the user through `Auth::guard(config('laravelsabre.guard'))`, where a null
setting means the application's default guard. Map the user to a principal with an application-supplied
mapper when one is registered, otherwise by reading the configured user attribute, which defaults to
`email`. An empty or missing mapped value raises a package exception that names the user and the
attribute. Read the challenge realm from configuration, defaulting to `sabre/dav`, and keep the Bearer
challenge.

**Rationale**: 1.x hardcodes the default guard and the email attribute and concatenates whatever it
finds, so a user without an email address yields the principal `principals/` and the client sees
confusing authorization failures rather than a configuration error. Making the guard and the mapping
configurable is the clarified capability scope, and defaulting both to today's behaviour satisfies the
frozen deployment surface and the constitution's rule that new keys default to prior behaviour.

**Alternatives considered**: mapper only, rejected because the common case of a different attribute
should not require writing a closure; attribute only, rejected because principals derived from a
tenant or a slug need application logic; failing over to the user identifier when the attribute is
missing, rejected because a silently different principal namespace corrupts client-side state.

## R7. Master switch placement (FR-019)

**Decision**: Keep the route registered whether or not the endpoint is enabled, and short-circuit with
404 in a package-applied middleware attached by the route group itself, ahead of the configurable
middleware group.

**Rationale**: The switch must answer 404 before any tree, plugin or provider is touched. Placing the
check in a middleware the package attaches, rather than in the configurable `middleware` list, means an
application that published its config file under 1.x still gets the guard, because its published list
does not mention the new class. Keeping the route registered preserves URL generation by route name
while the endpoint is switched off, which FR-012 needs and 1.x provides.

**Alternatives considered**: skipping route registration when disabled, rejected because
`route('sabre.dav')` would then throw for an application that only wants to display the endpoint URL;
leaving the check in the controller as 1.x does, acceptable for status parity but rejected because the
session and web middleware would still run for a disabled endpoint.

## R8. Suppressing upstream output (constitution I, FR-010)

**Decision**: Keep a package-owned SAPI that overrides response sending with a documented no-op, and
keep the comment naming the upstream reason.

**Rationale**: The engine's `start()` sends the response through the SAPI from its own exception
handler, so an engine-level error would emit headers and a body outside the framework's response
pipeline. The no-op is the reason the package can return the engine's error document as a normal
framework response. This is a bridge workaround, not a protocol reimplementation, and the constitution
requires the upstream reason to stay in a comment so it can be removed if upstream changes.

**Alternatives considered**: calling `invokeMethod()` with response sending disabled instead of
`start()`, rejected because the package would then have to reimplement the engine's error document,
which principle I forbids.

## R9. Test strategy for environment parity (FR-007, SC-004)

**Decision**: Drop the forced `testing` environment from the integration test base class and
parameterise the environment instead, so the same request is asserted under testing, local and
production configuration, with only diagnostic detail expected to differ.

**Rationale**: The current base class pins the environment to `testing`, which is exactly the branch
1.x takes; the production path is therefore unexercised. Since R1 removes the branch, the suite can and
must prove SC-004 rather than assume it.

**Alternatives considered**: a separate production-only test class, acceptable but rejected in favour of
a data-provided environment on the shared cases, which keeps the assertion set identical by construction.
