<!--
Sync Impact Report
==================
Version change: (unversioned template) → 1.0.0
Bump rationale: initial ratification; every placeholder replaced with concrete, enforceable rules.

Modified principles (placeholder → concrete):
- PRINCIPLE_1_NAME → I. Adapter Fidelity (NON-NEGOTIABLE)
- PRINCIPLE_2_NAME → II. Supported Version Matrix (NON-NEGOTIABLE)
- PRINCIPLE_3_NAME → III. Test-First, Both Layers
- PRINCIPLE_4_NAME → IV. Static Analysis And Style Are Gates
- PRINCIPLE_5_NAME → V. Stable, Documented Public Surface

Added sections:
- Security & Dependency Constraints (was placeholder SECTION_2)
- Development Workflow & Quality Gates (was placeholder SECTION_3)
- Governance (populated from placeholder GOVERNANCE_RULES)

Removed sections: none

Templates status (read at runtime, not modified by this command):
- .specify/templates/plan-template.md — Constitution Check gate is generic; compatible as-is
- .specify/templates/spec-template.md — no constitution coupling; compatible
- .specify/templates/tasks-template.md — no constitution coupling; compatible

Deferred items / follow-ups:
- RATIFICATION_DATE recorded as the adoption date of this document (2026-09-09), not the
  project's 2019 inception. Amend if the maintainer intends to backdate governance.
-->
# Laravel Sabre Constitution

## Core Principles

### I. Adapter Fidelity (NON-NEGOTIABLE)

This package MUST remain a thin adapter between Laravel and `sabre/dav`. It MUST NOT reimplement
WebDAV, CalDAV or CardDAV semantics.

- Protocol behaviour MUST be delegated to `sabre/dav`. Subclassing (`LaravelSabre\Sabre\Server`,
  `LaravelSabre\Sabre\Sapi`) is permitted ONLY to bridge Laravel routing, request/response,
  configuration and environment concerns.
- Nodes, trees and plugins MUST be supplied by the consuming application through
  `LaravelSabre::nodes()`, `LaravelSabre::plugins()` and `LaravelSabre::plugin()`. The package MUST NOT
  ship opinionated domain nodes or storage backends beyond the Laravel authentication bridge.
- Every workaround for upstream Sabre behaviour MUST carry a comment naming the upstream reason
  (as `Sapi::sendResponse()` does for suppressed `header()` calls) so it can be removed once upstream
  fixes it.

Rationale: consumers adopt this package for Laravel wiring, not for a second DAV implementation.
Divergence from upstream turns every `sabre/dav` upgrade into a rewrite.

### II. Supported Version Matrix (NON-NEGOTIABLE)

- The supported PHP and Laravel matrix MUST be declared in both `composer.json` and
  `.github/workflows/tests.yml`, and the two declarations MUST agree.
- Every pull request MUST pass the full matrix. A change that is green on only the default cell
  (currently PHP 8.4 / Laravel 13) is not mergeable.
- Adding a PHP or Laravel version is a MINOR release. Dropping one is a breaking change and MUST be
  released as MAJOR with the drop stated in the PR description.
- Cross-version differences MUST be handled by explicit version guards or shims in `src/`. Narrowing
  the matrix to make a change compile is NOT an acceptable substitute.

Rationale: the package's value is that it works across the framework versions its users are actually
on; silent matrix erosion breaks downstream applications at upgrade time.

### III. Test-First, Both Layers

- Every bug fix MUST add a regression test that fails before the fix and passes after it.
- Every change to the public API MUST add or update unit tests under `tests/Unit/`.
- Behaviour that reaches HTTP MUST be covered by `tests/Integration/` through Orchestra Testbench,
  exercising the real route and the real DAV verbs (`PROPFIND`, `PROPPATCH`, `REPORT`, `MKCOL`,
  `COPY`, `MOVE`, `LOCK`, `UNLOCK`).
- Tests MUST run under `APP_ENV=testing` and MUST NOT depend on global state left by another test.
  Any test touching `LaravelSabre` static state MUST call `LaravelSabre::clear()` in teardown.
- Coverage MUST be reported to SonarCloud, and the SonarCloud quality gate MUST pass before merge.

Rationale: the adapter's failure modes are protocol-level and version-level; only an end-to-end
request through Laravel's router proves the verb registration, middleware and response translation
still hold.

### IV. Static Analysis And Style Are Gates

- PHPStan (level 5, with larastan, strict rules, deprecation rules, phpunit and safe-rule extensions)
  and Psalm MUST report no new findings on `src/`.
- Error-prone standard library calls MUST use `thecodingmachine/safe` wrappers (`Safe\ob_start`,
  `Safe\stream_get_contents`, and equivalents) rather than raw functions with return-value checks.
- Suppressions (`@psalm-suppress`, `@phpstan-ignore`, `ignoreErrors`, `errorLevel="suppress"`) MUST be
  scoped to the narrowest possible symbol and MUST NOT be widened to silence an unrelated finding.
  Raising the suppression surface requires the same review as a source change.
- Code style MUST follow the StyleCI `laravel` preset with `fully_qualified_strict_types`, and all
  files MUST respect `.editorconfig` (LF, UTF-8, 4-space indent, final newline, no trailing
  whitespace).

Rationale: this is a library with no application-level test harness downstream; static analysis is the
only mechanism that catches type and deprecation regressions across the whole supported matrix.

### V. Stable, Documented Public Surface

The public surface is: the `LaravelSabre` static API, `LaravelSabreServiceProvider`, the config keys in
`config/laravelsabre.php`, the `Authorize` middleware, `AuthBackend`, the route name `sabre.dav`, and
the `laravelsabre` middleware group.

- Renaming, removing or changing the contract of any of the above is a breaking change. It MUST be
  released as MAJOR and the PR description MUST include migration instructions.
- New public methods and new config keys MUST be documented in `README.md` in the same pull request
  that introduces them.
- Config keys MUST keep working when absent from a published config file, via `mergeConfigFrom`. New
  keys MUST be additive and MUST default to the pre-change behaviour.

Rationale: applications wire this package into service providers and published config files; an
undocumented or silently changed surface breaks them at deploy time, not at compile time.

## Security & Dependency Constraints

- The DAV endpoint MUST stay authorization-gated. `Authorize` MUST remain in the default `middleware`
  config list, and a `LaravelSabre::check()` result of `false` MUST deny the request with `abort(403)`.
- The `enabled` master switch MUST short-circuit with `404` before any node, plugin or tree is
  constructed.
- `debugExceptions` MUST remain disabled in the `production` environment. Verbose Sabre stack traces
  MUST NOT reach production clients.
- Authentication MUST delegate to Laravel's `Auth` guard. The package MUST NOT store, log or echo
  credentials, bearer tokens or challenge material.
- `roave/security-advisories` MUST stay in `require-dev`, and Dependabot MUST remain enabled weekly for
  both Composer and GitHub Actions with `lockfile-only` versioning.
- Any addition to `require` is a supply-chain and matrix cost. New runtime dependencies MUST be
  justified in the PR description and MUST carry an explicit upper version bound.

## Development Workflow & Quality Gates

- All work lands through pull requests. The release branches (`main`, `next`, `next-major`, `beta`,
  `alpha`) MUST NOT receive unreviewed source changes.
- Pull request titles MUST follow Conventional Commits; the `Lint PR` workflow enforces this. The type
  determines the release: `feat` → MINOR, `fix` and `refactor` → PATCH, `!` or `BREAKING CHANGE` →
  MAJOR, scope `no-release` → no release.
- Required green checks before merge: the tests matrix workflow, the static analysis workflow, the
  SonarCloud quality gate, and the PR title lint.
- Releases MUST be produced by semantic-release from a release branch. `CHANGELOG.md` and git tags are
  generated artifacts and MUST NOT be hand-edited.
- `README.md` MUST be updated in the same pull request as any change to installation, configuration or
  public API.
- Agent guidance files (for example `CLAUDE.md` or `.github/copilot-instructions.md`) MUST NOT
  contradict this constitution. On conflict, this document wins.

## Governance

- This constitution supersedes ad-hoc practice and any conflicting repository guidance.
- Amendments MUST be proposed as a pull request that modifies `.specify/memory/constitution.md` and
  nothing else. The PR MUST state the rationale, apply the version bump, update the Last Amended date,
  and refresh the Sync Impact Report. Merging requires approval from a repository maintainer.
- This document is versioned with semantic versioning: MAJOR for a principle removed or redefined
  incompatibly, MINOR for a principle or section added or materially expanded, PATCH for
  clarifications, wording and typo fixes that do not change obligations.
- Compliance is verified at review time: every pull request review MUST check the change against the
  Core Principles. The `/speckit-plan` Constitution Check gate MUST be evaluated before design work
  begins.
- A violation that cannot be avoided MUST be recorded in the plan's Complexity Tracking section, naming
  the simpler alternative that was rejected and why. Undocumented violations MUST block merge.
- This constitution MUST be re-reviewed at every MAJOR upstream bump (Laravel or `sabre/dav`), or
  annually, whichever comes first.

**Version**: 1.0.0 | **Ratified**: 2026-09-09 | **Last Amended**: 2026-09-09
