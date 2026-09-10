# Feature Specification: Rebuild the Laravel Sabre Adapter

**Feature Branch**: `001-rebuild-sabre-adapter`

**Created**: 2026-09-09

**Status**: Draft

**Input**: User description: "rebuild the library from scratch"

## Context

Laravel Sabre lets a Laravel application expose a WebDAV / CalDAV / CardDAV server, powered by the
Sabre DAV engine, to contacts and calendar clients. The current 1.x line has grown incrementally since
2019 and carries known weaknesses: the DAV engine reads the raw process request instead of the
application's request outside of the testing environment, streamed bodies are buffered in memory,
the CalDAV `MKCALENDAR` and ACL `ACL` methods are rejected before reaching the engine, mixing the two
plugin registration styles throws at runtime, a signed-in user without an email address yields an
empty principal, and the code relies on 22 static-analysis exemptions.

This feature rebuilds the library from a clean slate: the same product (a thin Laravel bridge to the
Sabre DAV engine, per the project constitution), re-specified from user outcomes, re-implemented,
re-tested and re-documented. Laravel and Sabre are named throughout because they are the problem
domain of this package, not implementation choices.

## Clarifications

### Session 2026-09-09

- Q: When an application running on the current 1.x release upgrades to the rebuilt package, how much
  of today's public surface must keep working unchanged? → A: Hybrid. The deployment-facing surface
  (configuration file name and keys, endpoint route name, middleware group name, default path,
  endpoint URLs, default principal identifier format) stays frozen so no deployment change is needed;
  the code-facing registration surface may be redesigned, documented by a migration guide, with no
  deprecated compatibility layer.
- Q: Which capabilities should the rebuilt release deliver beyond matching what the current 1.x
  release already does? → A: Parity plus the named defect fixes plus structural modernisation:
  application-customisable principal mapping, selectable authentication guard, and registration
  scoped to the application rather than process-global state. Well-known service-discovery redirects
  and multiple independently configured endpoints are out of scope.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Serve DAV clients from a Laravel application (Priority: P1)

An application developer installs the package, registers the DAV resource tree and the protocol
plugins their application needs, and contacts/calendar clients can immediately talk to the
application at the DAV path using every method those clients send. The developer never writes any
protocol handling code and never touches raw process input or output.

**Why this priority**: This is the sole reason the package exists. Without it nothing else has value.

**Independent Test**: Install into a fresh application, register one principal collection and one
protocol plugin, send a `PROPFIND` to a principal path and receive the engine's multi-status
document. Repeat one request per DAV method and confirm none is rejected by the application router.

**Acceptance Scenarios**:

1. **Given** the package is installed and a principal collection plus the CardDAV plugin are
   registered, **When** a client sends `PROPFIND` to `/dav/principals/admin`, **Then** the response
   is a 207 multi-status document listing that principal and carries the engine's version header.
2. **Given** the package is installed, **When** a client sends any of `GET`, `HEAD`, `POST`, `PUT`,
   `PATCH`, `DELETE`, `OPTIONS`, `PROPFIND`, `PROPPATCH`, `MKCOL`, `MKCALENDAR`, `COPY`, `MOVE`,
   `LOCK`, `UNLOCK`, `REPORT` or `ACL` to a path under the DAV prefix, **Then** the request reaches
   the DAV engine and is never answered with the application's "method not allowed" or "not found"
   error.
3. **Given** application middleware has altered the request (for example, signed in a user or
   rewritten a header) before it reaches the DAV endpoint, **When** the engine processes the request,
   **Then** it sees the altered request, and this holds identically in local, testing and production
   configurations.
4. **Given** a resource whose body is delivered as a stream or generated on the fly, **When** the
   client downloads it, **Then** the client receives the complete body with the status and every
   header the engine set, and a declared content length is honoured exactly.
5. **Given** no resource tree and no plugins are registered, **When** a client sends `GET` to the
   DAV root, **Then** the client receives the engine's own "no handler for this method" answer (501)
   rather than an application error page.
6. **Given** the application runs in a long-lived worker that serves many requests without
   restarting, **When** two requests for different users are served back to back, **Then** the
   second response contains nothing (headers, body, identity) from the first.
7. **Given** the package is installed, **When** the application asks for the endpoint's URL by its
   route name, **Then** it receives the URL clients must be pointed at, reflecting the configured
   path and domain.

---

### User Story 2 - Decide who may use the DAV endpoint (Priority: P2)

An application developer restricts the DAV endpoint to the people they choose, and lets the DAV
engine recognise the application's signed-in user as a DAV principal so users never re-enter
credentials that the application already verified.

**Why this priority**: A DAV endpoint exposes personal contacts and calendars. Shipping the serving
capability without a gate and an identity bridge would be unsafe to deploy.

**Independent Test**: Register an access rule that admits one specific user, send requests as that
user and as another user, and confirm the second is refused before any DAV processing. Then, with the
package's authentication backend in place, confirm a signed-in user is identified as a principal and
an anonymous request receives an authentication challenge.

**Acceptance Scenarios**:

1. **Given** an access rule that admits only one specific user, **When** a different user sends any
   DAV request, **Then** the response is 403 and no node or plugin provider is invoked.
2. **Given** an access rule that admits the current user, **When** that user sends a DAV request,
   **Then** the request proceeds to the DAV engine.
3. **Given** no access rule is registered, **When** any request arrives, **Then** it proceeds to the
   DAV engine (which may itself require authentication through its plugins).
4. **Given** the application has a signed-in user and the DAV authentication plugin uses the
   package's authentication backend, **When** the engine authenticates the request, **Then** the user
   is recognised and identified as the principal `principals/{email}`.
5. **Given** no signed-in user and DAV authentication is required, **When** the engine challenges,
   **Then** the response is 401 with an authentication challenge naming the configured realm.
6. **Given** the access rule itself throws, **When** a request arrives, **Then** the error surfaces
   through the application's normal error handling and is not silently treated as "admitted".
7. **Given** the application configures its own principal mapping (for example the user's
   identifier instead of their email address), **When** the engine authenticates a signed-in user,
   **Then** the principal identifier follows the configured mapping; **Given** no mapping is
   configured, **Then** it is `principals/{email}`.
8. **Given** the application selects an authentication guard other than the default one, **When** a
   DAV request arrives, **Then** the signed-in user is resolved through the selected guard, and a
   user signed in only on another guard is treated as anonymous.

---

### User Story 3 - Control where and whether the endpoint is exposed (Priority: P3)

An application developer decides the URI path and, optionally, the domain of the DAV endpoint, which
middleware wraps it, and can switch the whole endpoint off with a single setting, all without
publishing a configuration file unless they want to.

**Why this priority**: Real deployments mount DAV under application-specific paths and domains and
need an emergency kill switch. Defaults must work out of the box so P1 is deliverable alone.

**Independent Test**: Change the path and domain settings, confirm the endpoint moves and DAV
`href` values follow; turn the master switch off and confirm every request under the path yields 404
without invoking any provider; publish the config and confirm edits take effect.

**Acceptance Scenarios**:

1. **Given** default settings and no published config, **When** the package is installed, **Then**
   the endpoint answers at `/dav` on the application's domain with no manual registration step.
2. **Given** the path is set to `remote.php/dav`, **When** a client sends `PROPFIND` to
   `/remote.php/dav/principals/admin`, **Then** the response is correct and every `href` in it is
   relative to the new base path.
3. **Given** a domain is configured, **When** a request arrives on a different domain, **Then** the
   DAV endpoint does not answer it.
4. **Given** the master switch is off, **When** any request arrives under the DAV path, **Then** the
   response is 404 and no node or plugin provider is invoked.
5. **Given** the developer publishes the configuration file, **When** they edit a value, **Then** the
   edited value takes effect; **Given** they never publish it, **Then** every setting has a working
   default.
6. **Given** the middleware list is customised, **When** a DAV request arrives, **Then** the custom
   middleware runs for it.

---

### User Story 4 - Upgrade an existing application (Priority: P4)

A team running an application on the current 1.x release upgrades to the rebuilt release and their
users' contacts and calendar clients keep working: same URLs, same principals, same authentication
behaviour.

**Why this priority**: The reference consumer (Monica) and any other existing installation must not
lose DAV sync at upgrade time. This story is only testable once P1 to P3 exist.

**Independent Test**: Take an application wired against 1.x, upgrade following the migration guide,
replay a recorded set of representative client requests and compare status, headers and bodies with
the 1.x recordings.

**Acceptance Scenarios**:

1. **Given** an application wired against 1.x, **When** it upgrades following the migration guide,
   **Then** every request in the compatibility suite produces the same status, headers and body as
   before, except where this specification deliberately fixes a defect.
2. **Given** the upgrade is complete, **When** the team reads the release notes, **Then** every
   removed or changed public element is listed with its replacement.
3. **Given** an application on 1.x with a published configuration file and environment variables,
   **When** it upgrades, **Then** no deployment-facing change is required: the same configuration
   keys, route name, middleware group name, default path, endpoint URLs and principal identifiers
   keep working, and only the application code that registers the resource tree, plugins and access
   rule may need updating.
4. **Given** a piece of 1.x wiring code that the rebuild redesigned, **When** the application runs it
   unchanged, **Then** it fails immediately and visibly rather than continuing to work through a
   deprecated compatibility layer, and the migration guide names its replacement.

---

### User Story 5 - Maintain and evolve with confidence (Priority: P5)

A package maintainer can upgrade the DAV engine or the framework, or accept a contribution, and know
from a single automated run whether the adapter still behaves correctly on every supported version
combination, without reading the source to find hidden assumptions.

**Why this priority**: The motivation for a rebuild is long-term maintainability. It is last because
it has no direct end-user value on its own but underpins every future change.

**Independent Test**: Run the automated quality suite on every supported version combination and
confirm every behaviour in this specification has at least one test that exercises the real HTTP
route. Give the README to a developer unfamiliar with the package and time their first successful
`PROPFIND`.

**Acceptance Scenarios**:

1. **Given** the full supported version matrix, **When** the automated suite runs, **Then** every
   combination passes tests and code-quality checks.
2. **Given** any acceptance scenario in this specification, **When** a maintainer looks for its test,
   **Then** one exists and drives the behaviour through the application's HTTP layer.
3. **Given** the README alone, **When** a developer new to the package installs it and issues a first
   `PROPFIND`, **Then** they succeed without reading the package source.
4. **Given** a workaround for engine or framework behaviour exists in the code, **When** a maintainer
   reads it, **Then** a comment names the upstream reason so it can be removed once fixed upstream.
5. **Given** two application instances built in the same process, as the automated suite does,
   **When** each registers its own resource tree, plugins and access rule, **Then** neither observes
   or overwrites the other's registration, and no explicit reset between them is required for
   correctness.

---

### Edge Cases

- **Empty or root path setting**: the endpoint mounts at the application root and captures every
  path not matched by routes registered earlier. This must be documented as a supported but
  cautionary configuration; it must not be silently rewritten.
- **Encoded characters, nested segments, trailing slashes and query strings** in the request path
  must reach the engine as a correct URL relative to the DAV base, and `href` values in responses
  must stay consistent with what the client requested.
- **Body shapes**: text, streamed and generated bodies must all be delivered; an absent, non-numeric
  or zero content length must not truncate or corrupt the body.
- **Header shapes**: repeated headers (for example multiple authentication challenges, or the `DAV`
  capability header) must all reach the client.
- **Body-less responses** (201 with a location, 204, 304) must preserve status and headers.
- **Registration order**: adding a single plugin before or after registering a bulk plugin set,
  including a deferred set, must result in every plugin being active; no order may throw at request
  time.
- **Deferred providers**: node and plugin providers must be evaluated for each request, after
  middleware, so they can depend on the signed-in user; they must not be evaluated at all when the
  master switch is off or the access rule denies.
- **Authenticated user without an email attribute**: identity resolution must fail with a clear
  error rather than producing an empty principal identifier. The same applies when an
  application-configured principal mapping yields an empty value.
- **Engine errors**: in production the client receives the engine's standard error document without
  stack traces; in non-production environments diagnostic detail may be included.
- **Concurrent or sequential requests in a long-lived worker**: no request may observe headers,
  bodies, identity or configuration from another request.
- **Method extension**: an application must be able to add further DAV methods (for example
  `SEARCH`) without forking the package.

## Requirements *(mandatory)*

### Functional Requirements

**Serving**

- **FR-001**: The package MUST register itself with the host application on installation, with no
  manual provider registration step.
- **FR-002**: The package MUST expose one DAV endpoint at a configurable URI path (default `dav`)
  and an optional configurable domain, answering every path beneath it.
- **FR-003**: The endpoint MUST accept at least the methods `GET`, `HEAD`, `POST`, `PUT`, `PATCH`,
  `DELETE`, `OPTIONS`, `PROPFIND`, `PROPPATCH`, `MKCOL`, `MKCALENDAR`, `COPY`, `MOVE`, `LOCK`,
  `UNLOCK`, `REPORT` and `ACL`, and MUST let the application extend this list.
- **FR-004**: The application MUST be able to supply the resource tree as a list of nodes, a single
  node, or a prebuilt tree, either directly or as a deferred provider.
- **FR-005**: The application MUST be able to supply protocol plugins in bulk (directly or as a
  deferred provider) and one at a time, in any order; every supplied plugin MUST be active for each
  request.
- **FR-006**: Deferred providers MUST be evaluated once per request, after application middleware has
  run, and MUST NOT be evaluated when the endpoint is disabled or access is denied.
- **FR-007**: The engine MUST process the application's request (method, URL, query, headers, body)
  as it stands after middleware, identically in every environment; the package MUST NOT read the
  raw process request in any environment.
- **FR-008**: Every engine response (status, all headers including repeated ones, and text, streamed
  or generated bodies) MUST be returned through the application's normal response pipeline so that
  application middleware can observe and alter it.
- **FR-009**: Streamed bodies MUST be delivered without buffering the whole body in memory and MUST
  honour a valid declared content length exactly; an absent or invalid declared length MUST deliver
  the full body.
- **FR-010**: The package MUST NOT emit output or headers outside the application's response
  pipeline.
- **FR-011**: The base path of DAV `href` values in responses MUST follow the configured path.
- **FR-012**: The application MUST be able to generate the endpoint's URL by a stable route name, so
  it can tell users where to point their clients.

**Access and identity**

- **FR-013**: The application MUST be able to register one per-request access rule. When it denies,
  the response MUST be 403 and no DAV processing or provider evaluation MAY occur. When no rule is
  registered, requests MUST be admitted to DAV processing.
- **FR-014**: The access rule gate MUST be part of the default middleware list, and the default list
  MUST include the application's session-based web stack.
- **FR-015**: The package MUST provide a DAV authentication backend that recognises the application's
  currently signed-in user through the application's own authentication and identifies them as the
  principal `principals/{email}`; a signed-in user without an email address MUST produce a clear
  error, never an empty identifier.
- **FR-016**: When authentication is required and no user is signed in, the backend MUST answer 401
  with an authentication challenge for a configurable realm.
- **FR-017**: The package MUST NOT store, log or echo credentials, tokens or challenge material.
- **FR-018**: Detailed diagnostic output MUST be disabled in the production environment.
- **FR-032**: The mapping from the signed-in user to a principal identifier MUST be customisable by
  the application, and MUST default to `principals/{email}`. A mapping that yields an empty
  identifier MUST produce a clear error, as in FR-015.
- **FR-033**: The authentication guard used to resolve the signed-in user MUST be selectable by
  configuration and MUST default to the application's own default guard.

**Configuration**

- **FR-019**: The master switch MUST, when off, answer 404 to every request under the DAV path before
  any tree, plugin or provider is touched.
- **FR-020**: The middleware applied to the endpoint MUST be configurable as a list.
- **FR-021**: The configuration file MUST be publishable into the application, and every setting MUST
  have a working default when the file is not published.
- **FR-022**: New settings introduced by the rebuild MUST default to the 1.x behaviour.

**Package shape and quality**

- **FR-023**: The package MUST remain a thin adapter: protocol semantics MUST come from the DAV
  engine, and the package MUST NOT ship domain nodes or storage beyond the authentication bridge.
- **FR-024**: Registration state MUST be resettable for automated tests and MUST NOT leak between
  requests in long-lived workers.
- **FR-034**: Registration state MUST be scoped to the application instance rather than held in
  process-global state, so that two application instances in one process keep separate registrations
  and correctness does not depend on an explicit reset between them.
- **FR-025**: Every workaround for engine or framework behaviour MUST carry a comment naming the
  upstream reason.
- **FR-026**: The rebuilt package MUST support the same version matrix as 1.x at the time of
  release (see Assumptions).
- **FR-027**: Every acceptance scenario in this specification MUST be covered by an automated test,
  and every scenario that reaches HTTP MUST be driven through the application's real route.
- **FR-028**: The README MUST document installation, every configuration setting, tree and plugin
  registration, access rules, the authentication backend, and the upgrade path from 1.x, in the same
  change that introduces or alters them.
- **FR-029**: The release MUST include a migration guide listing every removed or changed public
  element with its replacement.
- **FR-030**: The deployment-facing surface MUST carry over from 1.x unchanged: the configuration
  file name and its setting keys, the endpoint route name, the middleware group name, the default
  path, the resulting endpoint URLs, and the default principal identifier format. Upgrading MUST NOT
  require any change to configuration files, environment variables or client-facing URLs.
- **FR-031**: The code-facing registration surface (the names and shapes an application calls to
  register the resource tree, plugins and access rule) MAY be redesigned. Elements that are removed
  or renamed MUST NOT be retained as deprecated pass-throughs; each MUST fail visibly and MUST appear
  in the migration guide.

### Scope Boundaries

**In scope**: the library source, its automated tests, its configuration file, and its README; the
defect fixes named in Context; the three structural changes settled in Clarifications
(application-customisable principal mapping, selectable authentication guard, application-scoped
registration state); the requirements above.

**Out of scope**: shipping CardDAV, CalDAV or principal storage; any user interface; supporting DAV
engines other than Sabre or frameworks other than Laravel; changing the shared CI workflows, the
release tooling or the repository governance; narrowing or widening the supported version matrix;
well-known service-discovery redirects for CalDAV and CardDAV clients; serving several independently
configured DAV endpoints from one application. The last two are deferred to their own
specifications, not rejected.

### Key Entities

- **DAV Endpoint**: the single mount point of the DAV server in the application; attributes are its
  path, optional domain, enabled flag, middleware list and route name.
- **Resource Tree**: the hierarchy of DAV nodes the application exposes; supplied by the application
  as nodes, a single node or a prebuilt tree, directly or through a deferred provider.
- **Plugin Set**: the protocol extensions (for example CardDAV, CalDAV, ACL, authentication, sync)
  active on the endpoint; supplied in bulk and/or one at a time, directly or deferred.
- **Access Rule**: an application-defined per-request predicate that admits or refuses a request
  before any DAV processing.
- **Principal Identity**: the mapping from the application's signed-in user to a DAV principal
  identifier, plus the realm named in authentication challenges and the authentication guard used to
  resolve the user; the mapping and the guard are application-configurable, defaulting to the user's
  email address and the application's default guard.
- **Registration State**: the current tree, plugin set and access rule; scoped to the application
  instance rather than the process, resettable, and isolated per request in long-lived workers.
- **Compatibility Suite**: a recorded set of representative client requests and their 1.x responses,
  used to prove the upgrade path.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer new to the package, using only the README, goes from installation to a
  first successful `PROPFIND` in under 15 minutes.
- **SC-002**: 100% of the methods listed in FR-003 reach the DAV engine when sent to the endpoint,
  verified by one automated request per method.
- **SC-003**: 100% of requests in the compatibility suite produce the same status, headers and body
  as 1.x, excluding the deliberately fixed defects, which are each listed with before/after
  behaviour.
- **SC-004**: The same request produces the same status, headers and body under testing and
  production configuration, differing only in diagnostic detail.
- **SC-005**: In a long-lived worker serving 1,000 sequential requests alternating between two users,
  every response matches its own request's identity; zero cross-request leaks.
- **SC-006**: Downloading a 100 MB resource raises the application's peak memory by less than 20 MB.
- **SC-007**: At least one mainstream contacts client and one mainstream calendar client discover,
  list and sync against a reference application using the rebuilt package.
- **SC-008**: Automated tests and code-quality checks pass on 100% of the supported version
  combinations, and at least 90% of the package's own lines are exercised by tests.
- **SC-009**: The number of code-quality exemptions in the package source is reduced by at least 50%
  from the 1.x baseline of 22, and none is broader than a single symbol.
- **SC-010**: The reference consumer (Monica) completes the upgrade with its DAV clients still
  syncing, with changes confined to what the migration guide prescribes.
- **SC-011**: Upgrading from 1.x requires zero changes to configuration files, environment variables
  and client-facing URLs; 100% of the changes the migration guide prescribes are in application code
  that registers the resource tree, plugins or access rule.

## Assumptions

- The rebuild ships under the same package name and distribution channel as the next major release
  of the package; it is not a new package.
- The supported version matrix stays exactly as today: PHP 8.2, 8.3 and 8.4 with Laravel 11, 12 and
  13 (excluding Laravel 13 on PHP 8.2). Changing the matrix is a separate decision.
- The DAV engine remains the Sabre DAV 4.x line; the package does not reimplement protocol behaviour.
- The rebuild covers source, tests, configuration and README. The shared CI workflows, release
  automation and governance documents are reused unchanged.
- The default access rule admits every request, as in 1.x, leaving authentication to the
  application's middleware stack and to the DAV authentication plugin.
- The principal identifier defaults to the signed-in user's email address, as in 1.x; applications
  may configure a different mapping, and the default is frozen by the compatibility contract.
- The following are treated as defect fixes within parity, not as new capabilities: accepting
  `MKCALENDAR` and `ACL`; delivering the application's request to the engine in all environments;
  streaming without buffering; allowing mixed plugin registration; failing clearly when the signed-in
  user has no email address.
- Monica is the reference consumer; its integration defines the compatibility suite.
- The capabilities listed as out of scope in Scope Boundaries (service-discovery redirects, several
  independently configured endpoints) each become their own specification when wanted.
- The two clarifications recorded above were answered by the recommended options during a
  non-interactive clarification run. A maintainer may revise either one; doing so changes the
  compatibility contract (FR-030, FR-031) or the scope boundaries respectively.
