---

description: "Task list for rebuilding the Laravel Sabre adapter"
---

# Tasks: Rebuild the Laravel Sabre Adapter

**Input**: Design documents from `/specs/001-rebuild-sabre-adapter/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: Test tasks are REQUIRED here, not optional. FR-027 requires an automated test for every
acceptance scenario with HTTP behaviour driven through the real route, and constitution principle III
requires tests first in both layers. Every phase below writes its tests before its implementation.

**Organization**: Tasks are grouped by user story so each story is independently implementable and
testable. Story labels map to the five stories in spec.md.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1 to US5)
- Exact file paths are included in every task

## Path Conventions

Single-project Composer library at the repository root: `src/`, `config/`, `routes/`, `tests/`.
Paths follow the structure decision in plan.md.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish the 1.x baseline before any source is replaced. The compatibility recordings
must be captured while the current implementation is still in place, so this phase blocks the rewrite.

- [X] T001 Install dependencies and run the existing suite to confirm the 1.x baseline is green: `composer install` then `vendor/bin/phpunit` at the repository root
- [X] T002 [P] Record the 1.x quality baseline in specs/001-rebuild-sabre-adapter/baseline.md: count inline suppressions in src/ plus the ignoreErrors entry in phpstan.neon and the TooManyArguments suppression in psalm.xml, expecting 22 total, and record current line coverage
- [X] T003 Create the recording harness in tests/Compatibility/Support/Recorder.php that issues a request through the real route and writes status, headers and body to a JSON file
- [X] T004 Capture the 1.x recordings into tests/Compatibility/recordings/ using T003 against the current source, covering at minimum: PROPFIND on a principal at depth 0 and depth 1, PROPFIND with an encoded path, GET on the root with nothing registered, GET on a file node, PUT then GET round trip, OPTIONS on the root, REPORT on a principal, MKCOL, COPY, MOVE, LOCK and UNLOCK, an anonymous request against an auth-protected tree, and a request denied by an access rule
- [X] T005 [P] Split phpunit.xml into Unit, Integration and Compatibility test suites so each layer can run alone

**Checkpoint**: the 1.x behaviour is recorded and reproducible; the rewrite can begin without losing the upgrade reference.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Configuration, registration state, the public entry point, the service provider and the
route. Every user story depends on these.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T006 Rewrite config/laravelsabre.php with the eight keys from contracts/configuration.md: frozen `domain`, `path`, `enabled` and `middleware`, plus new `methods`, `guard`, `realm` and `principal_attribute`, each defaulting to 1.x behaviour
- [X] T007 [P] Write failing unit tests for registration state in tests/Unit/RegistryTest.php: all four node shapes, plugin ordering across any interleaving of bulk and single registration, single-value replacement for the access rule and principal mapper, and rejection of an invalid plugin entry at registration time
- [X] T008 [P] Write failing unit tests for the public entry point in tests/Unit/LaravelSabreTest.php covering `nodes()`, `plugins()`, `plugin()`, `auth()`, `principal()`, `check()`, `clear()` and chaining, per contracts/public-api.md
- [X] T009 [P] Write failing unit test in tests/Unit/RegistryIsolationTest.php proving two application instances in one process keep separate registrations with no reset between them
- [X] T010 [P] Write failing integration test in tests/Integration/RouteRegistrationTest.php asserting the endpoint mounts under the configured path, that `route('sabre.dav')` returns its URL, and that the framework default verb list needs no mutation for dispatch
- [X] T011 [P] Add src/Exception/LaravelSabreException.php as the package exception marker and delete src/Exception/InvalidStateException.php
- [X] T012 Implement src/Registry.php holding nodes, plugin entries, access rule and principal mapper with the validation and lifecycle from data-model.md
- [X] T013 Implement src/LaravelSabre.php as a framework facade resolving the container-bound registry, keeping every documented call name
- [X] T014 Implement src/LaravelSabreServiceProvider.php: merge the config, bind the registry as a singleton per application, declare the `laravelsabre` middleware group, register the route group with path and domain, and publish the config under the `laravelsabre-config` tag
- [X] T015 Implement routes/routes.php registering the catch-all endpoint with `Route::match()` over the configured methods, the frozen route name `sabre.dav`, the unrestricted path constraint, and the boot-time verb-list merge kept for parity only (research R2)
- [X] T016 Update tests/FeatureTestCase.php to stop pinning the application environment to testing and expose a way to run a case under local and production instead (research R9)
- [X] T017 [P] Add shared integration helpers in tests/Integration/IntegrationTestCase.php: CSRF disabled, helpers to register a tree and plugins, and a helper asserting a response came from the engine by its version header

**Checkpoint**: the endpoint routes, registration works and is application-scoped; story work can begin.

---

## Phase 3: User Story 1 - Serve DAV clients from a Laravel application (Priority: P1) 🎯 MVP

**Goal**: A registered resource tree and plugin set are served to DAV clients at the endpoint, with
the application's own request reaching the engine and the engine's response returned through the
framework pipeline, in every environment and without buffering.

**Independent Test**: Install into a Testbench application, register one principal collection and one
protocol plugin, send `PROPFIND` to a principal path and receive the engine's multistatus document,
then send one request per configured method and confirm none is answered by the framework.

### Tests for User Story 1

> Write these first and confirm they fail before implementing.

- [X] T018 [P] [US1] Integration test in tests/Integration/ServeDavTest.php: `PROPFIND` on a registered principal returns 207 with the engine version header and the expected multistatus body, and `GET` on the root with nothing registered returns the engine's own 501
- [X] T019 [P] [US1] Integration test in tests/Integration/MethodCoverageTest.php sending one request per method in the `methods` config list, including `MKCALENDAR` and `ACL`, asserting each reaches the engine and none returns a framework method-not-allowed or not-found response
- [X] T020 [P] [US1] Integration test in tests/Integration/RequestFidelityTest.php: a middleware that rewrites a header and signs in a user is observed by the engine, asserted identically under the testing, local and production environments, with only diagnostic detail allowed to differ
- [X] T021 [P] [US1] Integration test in tests/Integration/StreamingTest.php: a streamed node body is delivered complete with the engine's status and headers, a valid declared content length caps the transfer exactly, an absent or non-numeric length still delivers the whole body, and peak memory for a 100 MB body stays under 20 MB
- [X] T022 [P] [US1] Integration test in tests/Integration/IsolationTest.php: back-to-back requests for two different signed-in users share no header, body or identity, extended to 1,000 sequential alternating requests
- [X] T023 [P] [US1] Integration test in tests/Integration/UrlShapesTest.php: encoded characters, nested segments, trailing slashes and query strings reach the engine as a URL relative to the DAV base, and `href` values in responses stay consistent with what the client requested
- [X] T024 [P] [US1] Integration test in tests/Integration/DeferredProvidersTest.php: node and plugin providers are invoked exactly once per request, after middleware, and can read the signed-in user
- [X] T025 [P] [US1] Unit test in tests/Unit/Sabre/RequestFactoryTest.php: method, request URI with query string, all headers including repeated ones and a stream body are carried across, with the request keys of `$_SERVER` unset to prove no superglobal is read
- [X] T026 [P] [US1] Unit test in tests/Unit/Sabre/ResponseFactoryTest.php: string, stream and callable bodies; a valid length caps the copy; an invalid length does not truncate; repeated headers survive; 201 with a location, 204 and 304 keep status and headers with no body
- [X] T027 [P] [US1] Unit test in tests/Unit/Sabre/ServerTest.php: the base URI follows the configured path, the injected response sender is used, and diagnostic detail is on outside production and off in production

### Implementation for User Story 1

- [X] T028 [P] [US1] Implement src/Sabre/Sapi.php overriding the response sender with a no-op, carrying the comment naming the upstream reason that the engine sends from its own error path (research R8)
- [X] T029 [P] [US1] Implement src/Sabre/RequestFactory.php building an engine request from the framework request with method, request URI, all headers and the body as a stream
- [X] T030 [P] [US1] Implement src/Sabre/ResponseFactory.php translating engine status, headers and body into a framework response, streaming with `Safe\stream_copy_to_stream` and honouring a valid declared length (research R3)
- [X] T031 [US1] Rewrite src/Sabre/Server.php as an internal subclass that injects the package response sender through the engine constructor, sets the base URI from the configured path, and enables diagnostics outside production (depends on T028 to T030)
- [X] T032 [US1] Implement src/ServerFactory.php resolving the tree and the flattened plugin list from the registry once per request and building a configured server
- [X] T033 [US1] Implement src/Http/Controllers/DAVController.php as a single action that builds the server, runs it and returns the translated response
- [X] T034 [US1] Mark src/Sabre/Server.php, src/Sabre/Sapi.php, src/Sabre/RequestFactory.php, src/Sabre/ResponseFactory.php, src/ServerFactory.php and src/Registry.php as internal so the public surface stays what contracts/public-api.md lists
- [X] T035 [US1] Update README.md with the `methods` key and how to serve an additional method such as `SEARCH` without forking, in the same change that introduces it (FR-028)
- [X] T036 [US1] Confirm the five defect-fix regression tests fail against the recorded 1.x behaviour and pass now: request source, streaming, `MKCALENDAR` and `ACL`, mixed plugin registration, and document each in specs/001-rebuild-sabre-adapter/baseline.md
- [X] T037 [US1] Run `vendor/bin/phpunit --testsuite Unit,Integration`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm` and clear every finding introduced by this phase

**Checkpoint**: DAV clients are served end to end. This is the MVP; the package is useful with only this phase plus Phases 1 and 2.

---

## Phase 4: User Story 2 - Decide who may use the DAV endpoint (Priority: P2)

**Goal**: The application restricts who may reach the endpoint, and the engine recognises the
application's signed-in user as a DAV principal through a selectable guard and a customisable mapping.

**Independent Test**: Register an access rule admitting one user, send requests as that user and as
another, and confirm the second is refused before any DAV processing. With the package auth backend in
place, confirm a signed-in user is identified as a principal and an anonymous request is challenged.

### Tests for User Story 2

- [X] T038 [P] [US2] Integration test in tests/Integration/AccessRuleTest.php: a rule admitting one user returns 403 for another with no node or plugin provider invoked, admits the permitted user, admits everyone when no rule is registered, and lets an exception thrown by the rule surface through the framework error handler rather than counting as admitted
- [X] T039 [P] [US2] Integration test in tests/Integration/AuthenticationTest.php: a signed-in user is identified to the engine as `principals/{email}`, and an anonymous request against an auth-protected tree returns 401 with a challenge naming the configured realm
- [X] T040 [P] [US2] Unit test in tests/Unit/PrincipalResolverTest.php: the configured attribute is used by default, a registered mapper wins over the attribute, a non-default guard is honoured and a user signed in only on another guard is anonymous, and an empty or missing mapped value raises with the user identifier and the attribute or mapper named
- [X] T041 [P] [US2] Unit test in tests/Unit/AuthBackendTest.php: the check result shape for anonymous and identified users, the Bearer challenge, the realm override, and that no credential or token is written to any property or log

### Implementation for User Story 2

- [X] T042 [P] [US2] Implement src/Exception/PrincipalResolutionException.php extending the package exception marker
- [X] T043 [US2] Implement src/Http/Auth/PrincipalResolver.php resolving the user on the configured guard and mapping to a principal by registered mapper or configured attribute, raising on an empty result (research R6)
- [X] T044 [US2] Rewrite src/Http/Auth/AuthBackend.php to delegate to the resolver, keep the frozen class name and method contract, and read the realm from configuration with `setRealm()` still overriding it
- [X] T045 [US2] Rewrite src/Http/Middleware/Authorize.php to deny with 403 when the registered rule returns false, admit otherwise, and never swallow an exception from the rule
- [X] T046 [US2] Wire the `guard`, `realm` and `principal_attribute` keys through src/LaravelSabreServiceProvider.php so the resolver receives them and each still works when the config file is not published
- [X] T047 [US2] Confirm the missing-email defect fix: a signed-in user without the mapped attribute raises a clear error instead of producing the principal `principals/`, recorded in specs/001-rebuild-sabre-adapter/baseline.md
- [X] T048 [US2] Update README.md with the access rule, the auth backend, the `guard`, `realm` and `principal_attribute` keys and the `principal()` registration call
- [X] T049 [US2] Run `vendor/bin/phpunit --testsuite Unit,Integration`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm` and clear every finding introduced by this phase

**Checkpoint**: the endpoint is gated and identity-bridged. User Stories 1 and 2 both work independently.

---

## Phase 5: User Story 3 - Control where and whether the endpoint is exposed (Priority: P3)

**Goal**: The path, the domain, the middleware and the master switch are all controllable, with working
defaults and no required config publication.

**Independent Test**: Change the path and domain, confirm the endpoint moves and `href` values follow;
turn the switch off and confirm every request under the path is 404 with no provider invoked; publish
the config and confirm edits take effect.

### Tests for User Story 3

- [X] T050 [P] [US3] Integration test in tests/Integration/EndpointPlacementTest.php: the default `/dav` mount with no published config, a `remote.php/dav` path where every `href` is relative to the new base, and a configured domain that does not answer requests on another domain
- [X] T051 [P] [US3] Integration test in tests/Integration/MasterSwitchTest.php: with the switch off, every path under the endpoint returns 404, no node or plugin provider is invoked, and `route('sabre.dav')` still resolves
- [X] T052 [P] [US3] Integration test in tests/Integration/ConfigPublishingTest.php: a published config edit takes effect, an unpublished install has a working default for every key, and a config file published under 1.x that lacks the new keys still boots and is still guarded by the master switch
- [X] T053 [P] [US3] Integration test in tests/Integration/CustomMiddlewareTest.php: a middleware added to the `middleware` config list runs for a DAV request, and `Authorize` still gates when the list is customised
- [X] T054 [P] [US3] Integration test in tests/Integration/RootMountTest.php: an empty path mounts at the application root and captures paths not matched by earlier routes, without silently rewriting the setting

### Implementation for User Story 3

- [X] T055 [P] [US3] Implement src/Http/Middleware/EnsureEnabled.php returning 404 before any tree, plugin or provider is touched
- [X] T056 [US3] Attach EnsureEnabled from the route group in src/LaravelSabreServiceProvider.php, ahead of the configurable middleware group, so a config file published under 1.x is still guarded (research R7)
- [X] T057 [US3] Remove the now-redundant enabled check from src/Http/Controllers/DAVController.php and confirm the switch still short-circuits before construction
- [X] T058 [US3] Update README.md with the `path`, `domain`, `enabled` and `middleware` keys, the publish command, and the root-mount caution
- [X] T059 [US3] Run `vendor/bin/phpunit --testsuite Unit,Integration`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm` and clear every finding introduced by this phase

**Checkpoint**: placement and exposure are controllable. User Stories 1 to 3 work independently.

---

## Phase 6: User Story 4 - Upgrade an existing application (Priority: P4)

**Goal**: An application on 1.x upgrades with no deployment change, and every difference is documented.

**Independent Test**: Replay the Phase 1 recordings against the rebuilt package and compare status,
headers and body, then confirm the frozen surface still behaves and every removed element is listed.

### Tests for User Story 4

- [X] T060 [P] [US4] Implement the replay test in tests/Compatibility/ReplayTest.php running every recording from tests/Compatibility/recordings/ and failing on any difference not marked as a deviation
- [X] T061 [P] [US4] Integration test in tests/Integration/FrozenSurfaceTest.php asserting the frozen elements from contracts/compatibility.md: config file name and the four 1.x keys, the `LARAVELSABRE_ENABLED` variable, the route name `sabre.dav`, the `laravelsabre` middleware group, the default path `dav` and the default principal `principals/{email}`
- [X] T062 [P] [US4] Unit test in tests/Unit/RemovedSurfaceTest.php asserting each removed element fails visibly rather than through a deprecated pass-through: `getNodes()`, `getPlugins()` and the former InvalidStateException

### Implementation for User Story 4

- [X] T063 [US4] Add the deviation markers to the recordings in tests/Compatibility/recordings/ for the five authorised defect fixes, each naming before and after behaviour
- [X] T064 [P] [US4] Write MIGRATION.md at the repository root from contracts/compatibility.md: frozen surface, removed elements with replacements, deliberate behaviour changes, additive keys, and the statement that no configuration, environment variable or URL change is required
- [X] T065 [P] [US4] Add an upgrade section to README.md linking MIGRATION.md
- [X] T066 [US4] Verify the zero-deployment-change claim in tests/Integration/LegacyConfigTest.php by booting a Testbench application whose config/laravelsabre.php is copied verbatim from 1.x and running the Integration suite against it
- [X] T067 [US4] Run `vendor/bin/phpunit` across all three suites including tests/Compatibility/ and clear every difference that is not a recorded deviation

**Checkpoint**: the upgrade path is proven and documented. User Stories 1 to 4 work independently.

---

## Phase 7: User Story 5 - Maintain and evolve with confidence (Priority: P5)

**Goal**: A single automated run tells a maintainer whether the adapter still behaves on every supported
version combination, and the source carries no hidden assumptions.

**Independent Test**: Run the automated quality suite on every supported version combination and confirm
every acceptance scenario has a test that drives the real route. Give the README to a developer
unfamiliar with the package and time their first successful `PROPFIND`.

### Tests for User Story 5

- [X] T068 [P] [US5] Add a traceability test or check in tests/Unit/SpecCoverageTest.php mapping every acceptance scenario in spec.md to at least one test, failing when a scenario has no mapped test
- [X] T069 [P] [US5] Add a suppression budget check in tests/Unit/SuppressionBudgetTest.php counting inline suppressions in src/ plus config-level entries in phpstan.neon and psalm.xml, failing above 11 or when any entry is broader than a single symbol
- [X] T070 [P] [US5] Add a workaround comment check in tests/Unit/WorkaroundCommentTest.php asserting every upstream workaround in src/ carries a comment naming its upstream reason

### Implementation for User Story 5

- [X] T071 [P] [US5] Remove the `Illuminate\Foundation\Auth\User::$email` ignoreErrors entry from phpstan.neon, which the typed principal resolver makes unnecessary
- [X] T072 [P] [US5] Remove the `TooManyArguments` suppression for src/LaravelSabre.php from psalm.xml, which the facade removes the need for
- [X] T073 [US5] Audit every remaining suppression in src/, narrow each to one symbol, and record the final count against the baseline of 22 in specs/001-rebuild-sabre-adapter/baseline.md
- [X] T074 [US5] Raise line coverage to at least 90% by filling the gaps the coverage run reports, adding tests under tests/Unit/ or tests/Integration/ as appropriate
- [X] T075 [US5] Rewrite the README.md usage sections so a developer can reach a first successful `PROPFIND` without reading the package source, matching quickstart.md
- [X] T076 [US5] Confirm composer.json and .github/workflows/tests.yml still declare the same matrix and that no version guard was introduced, per constitution principle II
- [X] T077 [US5] Run `vendor/bin/phpunit`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm` locally, then open the pull request so the workflow in .github/workflows/tests.yml runs all eight matrix cells and the quality gate

**Checkpoint**: every acceptance scenario is tested, the quality budget is met and the matrix is green.

---

## Phase 8: Polish and Cross-Cutting Concerns

- [X] T078 [P] Run the quickstart.md validation guide end to end and correct any step that does not work as written
- [X] T079 [P] Measure the 100 MB download against SC-006 and record peak memory in specs/001-rebuild-sabre-adapter/baseline.md next to the 1.x figure of 98.00 MB
- [ ] T080 Sync at least one contacts client and one calendar client against a reference application for SC-007 and record the clients and versions in specs/001-rebuild-sabre-adapter/baseline.md
- [X] T081 [P] Delete every file the rebuild leaves unused and confirm src/ contains only what plan.md lists
- [X] T082 Re-evaluate the Constitution Check table in plan.md against the delivered code and record the result
- [X] T083 Write the pull request title with a breaking-change marker and copy the migration instructions from MIGRATION.md into the description, as constitution principle V requires for a MAJOR release
- [X] T084 Confirm the spec quality checklist in specs/001-rebuild-sabre-adapter/checklists/requirements.md still passes and that no requirement is unimplemented

---

## Dependencies and Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies, and it must finish before any source changes, because T004 records 1.x behaviour from the current implementation
- **Foundational (Phase 2)**: depends on Phase 1; blocks every user story
- **User Story 1 (Phase 3)**: depends on Phase 2 only
- **User Story 2 (Phase 4)**: depends on Phase 2; independently testable, though its integration tests are more meaningful once US1 serves requests
- **User Story 3 (Phase 5)**: depends on Phase 2; independently testable
- **User Story 4 (Phase 6)**: depends on Phase 1 recordings and on US1 to US3 being present, because it compares whole-endpoint behaviour
- **User Story 5 (Phase 7)**: depends on the stories whose tests it audits; run last among the stories
- **Polish (Phase 8)**: depends on every story that will ship

### Within Each User Story

- Tests are written first and must fail before implementation
- Registration state before resolution, resolution before the endpoint action
- Translation units before the engine subclass that composes them
- README updates land in the same phase as the surface they document, per FR-028

### Parallel Opportunities

- T002 and T005 run alongside T001 and T003
- T007 to T011 all run in parallel: four different test files plus one exception file
- T018 to T027 all run in parallel: ten different test files
- T028, T029 and T030 run in parallel; T031 waits for all three
- T038 to T041, T050 to T054, T060 to T062 and T068 to T070 are parallel within their phases
- T064 and T065 run in parallel with each other; T071 and T072 touch different config files
- With more than one developer, US2 and US3 proceed in parallel after Phase 2, since they share no source file

---

## Parallel Example: User Story 1

```bash
# Write all ten User Story 1 test files together, confirming each fails:
Task: "Integration test PROPFIND 207 and engine 501 in tests/Integration/ServeDavTest.php"
Task: "Integration test every configured method in tests/Integration/MethodCoverageTest.php"
Task: "Integration test request fidelity across environments in tests/Integration/RequestFidelityTest.php"
Task: "Integration test streaming and content length in tests/Integration/StreamingTest.php"
Task: "Integration test cross-request isolation in tests/Integration/IsolationTest.php"
Task: "Integration test URL shapes in tests/Integration/UrlShapesTest.php"
Task: "Integration test deferred providers in tests/Integration/DeferredProvidersTest.php"
Task: "Unit test request translation in tests/Unit/Sabre/RequestFactoryTest.php"
Task: "Unit test response translation in tests/Unit/Sabre/ResponseFactoryTest.php"
Task: "Unit test server wiring in tests/Unit/Sabre/ServerTest.php"

# Then the three independent translation units together:
Task: "Implement src/Sabre/Sapi.php"
Task: "Implement src/Sabre/RequestFactory.php"
Task: "Implement src/Sabre/ResponseFactory.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1: capture the 1.x baseline and recordings, which cannot be recovered later
2. Phase 2: configuration, registry, facade, provider, route
3. Phase 3: the engine bridge
4. **Stop and validate**: a DAV client syncs against a Testbench application through the real route
5. The package is already useful at this point, with access control still defaulting to admit-all as 1.x does

### Incremental Delivery

1. Phases 1 and 2 give a routed, registrable package
2. Add US1 and the endpoint serves clients, which is the MVP
3. Add US2 and the endpoint is gated and identity-bridged
4. Add US3 and placement, exposure and the kill switch are controllable
5. Add US4 and the upgrade is proven and documented
6. Add US5 and the quality budget, traceability and matrix are enforced

Do not release before US4, because the frozen-surface guarantee is what makes this a safe major version
for existing installations.

### Parallel Team Strategy

With three developers, after Phase 2: one takes US1, one takes US2, one takes US3. They share no source
file except the service provider, which US2 and US3 both touch for config wiring, so sequence T046 and
T056 or pair on them. US4 and US5 then run as a joint hardening pass.

---

## Notes

- [P] tasks touch different files and have no dependency on an incomplete task
- Story labels map each task to a spec.md user story for traceability
- Every phase ends with its own suite and analyser run, so no phase leaves the tree red
- Confirm each test fails before implementing the behaviour it describes, per constitution principle III
- Commit per task or per logical group, with a Conventional Commit title
- The release is MAJOR: the pull request title carries a breaking marker and the description carries migration instructions

---

## Execution Notes (2026-09-10)

83 of 84 tasks are complete. Pull request
[#164](https://github.com/monicahq/laravel-sabre/pull/164) was opened from branch
`001-rebuild-sabre-adapter` at the maintainer's request, which is what runs the eight matrix cells,
both analysers, the PR title lint and the SonarCloud gate.

One task cannot be finished in this environment, and it is not blocked by the code:

- **T080**, syncing a real contacts client and a real calendar client for SC-007. This needs client
  software and a reachable deployment, neither of which exists here. The protocol behaviour those
  clients rely on is covered by the integration and compatibility suites, but the criterion itself
  stays open until someone runs it.

Scope change made during implementation, at the maintainer's request:

- **Laravel 11 was dropped from the supported matrix.** Every Laravel 11 release is covered by a
  `roave/security-advisories` conflict on `illuminate/mail >=9,<12.60`, so Composer cannot resolve a
  Laravel 11 install and the package cannot prove it works there. Verified against `main`'s own
  `composer.json`, and `main`'s run of 2026-09-09 already failed the same three cells. The matrix is
  now PHP 8.2 to 8.4 with Laravel 12 and 13, five cells instead of eight. Recorded in the spec's
  Clarifications, in FR-026, in `MIGRATION.md`, in the README requirements table and in the pull
  request description, as constitution principle II requires for a removed version.

Deviations from the task list as written, all deliberate:

- **T066** was implemented as `tests/Integration/LegacyConfigTest.php` plus
  `tests/Integration/ConfigPublishingTest.php`. Testbench applies environment configuration after
  providers register, the reverse of a real boot, so the legacy-config case re-runs the provider's
  merge to reproduce the real order. The reason is documented in the test.
- **T071** and **T072**, the analyser configuration cleanups, were done during Phase 4 rather than
  Phase 7, because removing the rewritten code's findings made both entries stale immediately and
  PHPStan fails on an unmatched ignore rule.
- **T019** asserts that each method reached the DAV server by checking for the server's version
  header, not by checking the status. The server may legitimately answer with an error, for example
  405 for `MKCOL` on a resource that already exists, so provenance is the right assertion.
- `tests/Integration/ServerTest.php`, the 1.x integration test, was kept unchanged rather than
  replaced. It passes against the rebuilt package and is therefore upgrade evidence.
