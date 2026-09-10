# Pull request

Opened as [#164](https://github.com/monicahq/laravel-sabre/pull/164) from branch
`001-rebuild-sabre-adapter` into `main`, carrying 109 changed files. The text below is what was used.

## Title

```
feat!: rebuild the Sabre adapter on a framework-native request pipeline
```

The `!` marks the breaking change, which semantic-release turns into a MAJOR release, as constitution
principle V requires for a change to the named public surface.

## Description

```markdown
Rebuilds the package from the specification in `specs/001-rebuild-sabre-adapter/`, keeping the
deployment-facing surface frozen so existing installations upgrade without touching configuration,
environment variables or URLs.

## Why

The 1.x line read the raw process request outside the testing environment, buffered whole response
bodies in memory, rejected `MKCALENDAR` and `ACL` before they reached the DAV server, threw when the
two plugin registration styles were mixed, produced the principal `principals/` for a user without an
email address, and carried 22 static-analysis suppressions.

## What changed

- The DAV server now receives the framework request as it stands after middleware, in every
  environment. The package injects its own SAPI so the server never reads `$_SERVER` or `php://input`.
- Response bodies are copied stream to stream. A 100 MB download costs under 20 MB of peak memory
  instead of about 98 MB.
- The endpoint routes an explicit, configurable method list, so `MKCALENDAR`, `ACL` and any further
  method such as `SEARCH` are a config entry rather than a patch to a framework global.
- Registration lives in a container-bound registry, so two applications in one process keep separate
  registrations and no reset is needed between them.
- Plugin registration appends, so bulk and single registration mix in any order.
- Identity resolution takes a configurable guard and a configurable attribute, with an optional
  mapping closure, and raises a named exception rather than producing an empty principal.

## Migration

No deployment change is required. See `MIGRATION.md` for the removed code-facing elements, each with
its replacement, and for the six deliberate behaviour changes.

## Verification

- 236 tests, of which 24 are recording fixtures skipped unless re-recording
- 100% line, method and class coverage of `src/`
- PHPStan level 5 and Psalm both clean, with zero suppressions, down from 24
- All 24 recordings captured from 1.x replay identically, except the two authorised deviations
- Quality gates are themselves tests: spec coverage, suppression budget, workaround comments,
  documentation completeness and matrix agreement
```

Remember the attribution lines the repository requires on commits and pull request descriptions.
