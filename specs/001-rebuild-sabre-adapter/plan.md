# Implementation Plan: Rebuild the Laravel Sabre Adapter

**Branch**: `001-rebuild-sabre-adapter` | **Date**: 2026-09-09 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-rebuild-sabre-adapter/spec.md`

## Summary

Rebuild `monicahq/laravel-sabre` as a thin, fully tested bridge between a Laravel application and the
sabre/dav engine, keeping the deployment-facing surface frozen so existing installations upgrade
without touching configuration or URLs, while replacing the internals that cause today's defects.

The technical approach, grounded in the Phase 0 probes recorded in [research.md](./research.md), has
four moving parts. First, the package builds the engine's request explicitly from the Laravel request
and injects its own SAPI through the engine's constructor, which removes the superglobal read that
forced 1.x to branch on the environment. Second, the endpoint is registered with an explicit method
list from configuration instead of a mutated framework verb list, which makes `MKCALENDAR`, `ACL` and
any application extension a configuration entry. Third, engine responses are translated into streamed
framework responses with a stream-to-stream copy, which removes whole-body buffering. Fourth,
registration state moves from private static properties into a container-bound registry behind a
facade, so two applications in one process cannot see each other's registration and the documented
call sites stay identical.

## Technical Context

**Language/Version**: PHP 8.2, 8.3 and 8.4 (matrix unchanged; local verification ran on 8.4.25)

**Primary Dependencies**: `illuminate/support` ^11 || ^12 || ^13, `sabre/dav` ^4.0 (resolves 4.7.1,
pulling `sabre/http` 5.1.13), `thecodingmachine/safe` ^3.0. Development only:
`orchestra/testbench` ^9 || ^10 || ^11, `phpunit/phpunit` ^11 || ^12, `larastan/larastan`,
`vimeo/psalm`, `mockery/mockery`, `roave/security-advisories`. No new runtime dependency is proposed.

**Storage**: N/A. The package ships no storage backend; the host application supplies the resource
tree, and the only persistence the package touches is the host's authentication guard.

**Testing**: PHPUnit with Orchestra Testbench. Unit tests under `tests/Unit/` for the registry,
identity and translation units; integration tests under `tests/Integration/` driving the real route
with real DAV methods, parameterised over the testing, local and production environments.

**Target Platform**: Any Laravel 11 to 13 application on a supported PHP version, served by PHP-FPM,
the CLI server, or a long-lived worker such as Octane. No reliance on superglobals or process-global
mutable state is permitted, which is what makes the worker case correct.

**Project Type**: Single-project Composer library, auto-discovered service provider, no frontend and
no CLI surface.

**Performance Goals**: A 100 MB download raises peak memory by under 20 MB (SC-006; the measured
stream-copy cost is 0.00 MB, leaving the budget to framework overhead). One thousand sequential
requests alternating between two users leak nothing across requests (SC-005).

**Constraints**: Thin adapter, no protocol reimplementation (constitution I). Deployment-facing
surface frozen: config file name and keys, route name `sabre.dav`, middleware group `laravelsabre`,
default path `dav`, endpoint URLs, default principal format (FR-030). New config keys default to 1.x
behaviour (FR-022, constitution V). PHPStan level 5 and Psalm clean, `thecodingmachine/safe` wrappers
for error-prone calls, at most 11 suppressions in `src/`, each no broader than one symbol (SC-009,
constitution IV). Line coverage at least 90% (SC-008).

**Scale/Scope**: 34 functional requirements, 11 success criteria, 5 prioritised user stories.
Estimated 14 to 16 source files, roughly 1,200 lines of source, replacing 10 files and about 1,000
lines today. The supported matrix is 8 cells and every cell must pass (constitution II).

## Constitution Check

*GATE: evaluated before Phase 0 research and re-evaluated after Phase 1 design.*

| Principle | Gate | Verdict | Basis |
|---|---|---|---|
| I. Adapter Fidelity | No protocol reimplementation; subclassing only to bridge framework concerns; every upstream workaround carries a comment naming its reason | PASS | Protocol handling stays in sabre/dav. The package's engine subclass, SAPI, request factory and response factory exist only to translate framework request, response, configuration and environment. R8 keeps the no-op response sender with its upstream reason. FR-023 forbids shipping domain nodes or storage. |
| II. Supported Version Matrix | `composer.json` and `tests.yml` agree; every cell green; drops are MAJOR | PASS | The matrix is unchanged and out of scope (spec Assumptions). No version guard is needed for the chosen mechanisms: the engine constructor's SAPI argument, `Route::match()` and `stream_copy_to_stream` are stable across Laravel 11 to 13 and sabre/dav 4.x. |
| III. Test-First, Both Layers | Regression test per fix; unit tests for public API; HTTP behaviour through Testbench and real verbs; no cross-test global state; SonarCloud gate | PASS | FR-027 requires a test per acceptance scenario, HTTP ones through the real route. Each of the five defect fixes gets a regression test that fails against 1.x behaviour. R9 replaces the pinned testing environment with a parameterised one. R4 removes the need for teardown resets while `LaravelSabre::clear()` stays available for consumer suites. |
| IV. Static Analysis And Style Are Gates | PHPStan level 5 and Psalm clean; safe wrappers; narrow suppressions; StyleCI laravel preset | PASS | Explicit request construction and typed factories remove the mixed-type properties behind most of the 22 current suppressions. SC-009 caps the result at 11, each scoped to one symbol. Streaming uses `Safe\stream_copy_to_stream`. |
| V. Stable, Documented Public Surface | Named surface stable or MAJOR with migration instructions; new methods and keys documented in the same change; keys work when absent | PASS with obligation | The named deployment surface is frozen by FR-030. The redesign touches the code-facing part of `LaravelSabre` and makes the engine subclass internal, which the clarified spec allows and the constitution permits only as a MAJOR release with migration instructions, so the release must be MAJOR and ship the migration guide required by FR-029. New keys `methods`, `guard`, `realm` and `principal_attribute` are additive, default to 1.x behaviour, and are documented in README in the same change (FR-028). |
| Security & Dependency Constraints | `Authorize` stays in the default middleware list; `enabled` short-circuits with 404 before construction; no verbose traces in production; auth delegates to the guard; no credential storage or logging; no new runtime dependency | PASS | `Authorize` keeps its name and its place in the default list. R7 puts the master switch in a package-applied middleware ahead of the configurable group, so a config file published under 1.x still gets it. Diagnostics stay off in production (FR-018). Identity resolves through `Auth::guard()` (R6). FR-017 forbids storing or logging credentials. No dependency is added. |
| Development Workflow & Quality Gates | Pull requests, Conventional Commit titles, green matrix, Sonar gate, semantic-release, README in the same change | PASS | CI, release tooling and governance are reused unchanged (spec Assumptions). The release is MAJOR, so the pull request title carries a breaking marker and the description carries migration instructions. |

**Complexity Tracking**: no violations to justify. See the section below.

## Project Structure

### Documentation (this feature)

```text
specs/001-rebuild-sabre-adapter/
├── plan.md                    # This file
├── research.md                # Phase 0 output: nine verified decisions
├── data-model.md              # Phase 1 output: entities, fields, validation, lifecycle
├── quickstart.md              # Phase 1 output: runnable validation guide
├── contracts/                 # Phase 1 output
│   ├── public-api.md          # Code-facing surface the package exposes to applications
│   ├── configuration.md       # Config keys, types, defaults, frozen or new
│   ├── http-endpoint.md       # Methods, statuses, headers, bodies the endpoint guarantees
│   └── compatibility.md       # Frozen surface, removed elements, observable changes
├── checklists/
│   └── requirements.md        # Spec quality checklist, 16/16 passing
└── tasks.md                   # Phase 2 output, created by /speckit-tasks
```

### Source Code (repository root)

```text
src/
├── LaravelSabre.php                        # Facade: nodes, plugins, plugin, auth, principal, check, clear
├── LaravelSabreServiceProvider.php         # Bindings, config merge, middleware group, routes, publishing
├── Registry.php                            # Application-scoped registration state
├── ServerFactory.php                       # Builds a configured engine instance per request
├── Http/
│   ├── Controllers/DAVController.php       # Single action: resolve, run, translate
│   ├── Middleware/Authorize.php            # Access-rule gate (frozen name, default middleware list)
│   ├── Middleware/EnsureEnabled.php        # Master switch, attached by the route group
│   └── Auth/
│       ├── AuthBackend.php                 # Engine auth backend (frozen name)
│       └── PrincipalResolver.php           # Guard selection and principal mapping
├── Sabre/
│   ├── Server.php                          # Internal: engine subclass, base URI and wiring
│   ├── Sapi.php                            # Internal: no-op response sender, upstream reason documented
│   ├── RequestFactory.php                  # Internal: Laravel request to engine request
│   └── ResponseFactory.php                 # Internal: engine response to framework response, streaming
└── Exception/
    ├── LaravelSabreException.php           # Package exception marker
    └── PrincipalResolutionException.php    # Missing or empty principal value

config/laravelsabre.php                     # domain, path, enabled, middleware + methods, guard, realm, principal_attribute
routes/routes.php                           # Route::match(configured methods) to the controller, name sabre.dav

tests/
├── FeatureTestCase.php                     # Testbench base, environment parameterisable
├── Authenticated.php                       # Test user
├── Unit/                                   # Registry, PrincipalResolver, factories, facade
├── Integration/                            # Real route, real DAV methods, per environment
├── Compatibility/                          # Recorded 1.x request and response pairs (US4)
└── Sabre/                                  # Existing mock principal and auth backends
```

**Structure Decision**: the existing single-project library layout is kept, because the package has one
deliverable and the constitution names paths inside it. Three additions structure the rebuild: a
`Registry` plus `ServerFactory` pair that separates registration from per-request resolution (R4), a
`Sabre/` translation layer split into request and response factories so each side is unit-testable
without HTTP, and a `tests/Compatibility/` suite that holds the recorded 1.x pairs the upgrade story
depends on. `src/Exception/InvalidStateException.php` disappears with the state machine that raised it
(R5).

## Phase 0 and Phase 1 Outputs

**Phase 0** produced [research.md](./research.md): nine decisions, each with rationale, alternatives
and verification. Three were verified by running code against the resolved dependency versions rather
than by reading documentation. The load-bearing measurements are that a route registered with an
explicit method list answers `PROPFIND`, `MKCALENDAR`, `ACL` and `REPORT` with the framework's default
verb list untouched; that an engine server built with an injected SAPI and a request assembled from the
Laravel request returns 207 with `$_SERVER` request keys unset; and that copying a 100 MB body
stream-to-stream costs 0.00 MB of peak memory against 98.00 MB for the current buffering call.

**Phase 1** produced [data-model.md](./data-model.md), the four contracts under
[contracts/](./contracts/), and [quickstart.md](./quickstart.md). The data model turns the spec's seven
entities into concrete state with validation rules and lifecycle. The contracts pin the code-facing
API, the configuration keys, the HTTP guarantees and the compatibility diff, which together are what
FR-027 tests and FR-029 documents.

**Post-design constitution re-check**: unchanged from the table above. The design adds no runtime
dependency, no protocol logic, no matrix change and no new public deployment surface. The one
obligation carried forward is that the release must be MAJOR and must ship the migration guide, because
the code-facing surface changes.

**Post-implementation constitution re-check (2026-09-10)**: all seven gates still pass, now against
delivered code rather than a design.

| Principle | Evidence |
|---|---|
| I. Adapter Fidelity | `src/` holds 15 files and no protocol logic; the engine subclass only sets the base URI, injects the SAPI and translates request and response. `tests/Unit/WorkaroundCommentTest.php` enforces that every workaround names its upstream reason. |
| II. Supported Version Matrix | Unchanged, and now enforced by `tests/Unit/SupportedMatrixTest.php`, which fails if `composer.json` and `.github/workflows/tests.yml` disagree or if a runtime dependency is added. |
| III. Test-First, Both Layers | 236 tests. `tests/Unit/SpecCoverageTest.php` maps all 30 acceptance scenarios to a named test and fails if one is unmapped; it also asserts that scenarios reaching HTTP are driven through the real route. |
| IV. Static Analysis And Style Are Gates | PHPStan level 5 and Psalm both report zero findings with zero suppressions, down from 22 inline plus 2 config-level. `tests/Unit/SuppressionBudgetTest.php` keeps it that way. |
| V. Stable, Documented Public Surface | `tests/Integration/FrozenSurfaceTest.php` asserts the frozen elements; `tests/Unit/RemovedSurfaceTest.php` asserts removed elements fail visibly and appear in `MIGRATION.md`; `tests/Unit/DocumentationTest.php` asserts the README documents every config key and public call. |
| Security & Dependency Constraints | `Authorize` is still in the default list and gated by `AccessRuleTest`; the master switch is enforced by a package-applied middleware and covered by `MasterSwitchTest` and `LegacyConfigTest`; diagnostics are asserted off in production; no credential material is stored or logged, asserted in both layers. |
| Development Workflow & Quality Gates | Suite, both analysers and coverage all run clean locally. Opening the pull request, which is where the matrix and the SonarCloud gate run, is left to the maintainer; the title and description are prepared in `pull-request.md`. |

## Complexity Tracking

No constitution violation requires justification. Two deliberate deviations from the simplest possible
design are recorded here because they cost surface area and a reviewer should see the reasoning.

| Deviation | Why needed | Simpler alternative rejected because |
|---|---|---|
| Registration split into a container-bound `Registry` plus a per-request `ServerFactory`, rather than one class | FR-034 and US5 scenario 5 require two applications in one process to keep separate registrations, while deferred providers must resolve per request after middleware (FR-006) | A single static holder is what 1.x has; it makes correctness depend on consumer teardown discipline and cannot separate boot-time registration from per-request resolution |
| Framework verb list still merged at boot even though dispatch no longer needs it | Preserves two 1.x side effects applications may depend on: a host application's own catch-all route covering DAV verbs, and the framework's 405 alternate-verb diagnostics recognising them | Dropping the merge is cleaner but silently changes behaviour outside the frozen surface for any application that relied on it, which the upgrade story forbids |
