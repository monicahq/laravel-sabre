# Quickstart and Validation Guide

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-09-09

Two audiences. The first half is what a developer does to get a working DAV endpoint, which is what
SC-001 times at under 15 minutes. The second half is how a maintainer proves the rebuilt package meets
the specification. No implementation code appears here; see [contracts/](./contracts/) for the surface
and `tasks.md` for the work items.

## Prerequisites

- PHP 8.2, 8.3 or 8.4 with a Laravel 12 or 13 application (Laravel 13 needs PHP 8.3 or newer)
- Composer
- A DAV client for the end-to-end check, for example `curl`, or a contacts or calendar client for SC-007

## Use the package in an application

1. Install: `composer require monicahq/laravel-sabre`. The provider is auto-discovered; nothing is
   added to the application's provider list.
2. Optionally publish the config: `php artisan vendor:publish --tag=laravelsabre-config`. Every key has
   a working default without this step.
3. In a service provider's `boot()`, register a resource tree with `LaravelSabre::nodes(...)` and the
   protocol plugins with `LaravelSabre::plugins(...)` or `LaravelSabre::plugin(...)`. Pass closures when
   the registration depends on the signed-in user, because closures run per request after middleware.
4. Optionally restrict access with `LaravelSabre::auth(...)`, and customise identity with
   `LaravelSabre::principal(...)` or the `guard` and `principal_attribute` config keys.
5. Point clients at `route('sabre.dav')`, which is `/dav` by default.

First request to confirm the endpoint answers:

```sh
curl -i -X PROPFIND -H 'Depth: 0' http://localhost:8000/dav/principals/admin
```

Expect 207 with a multistatus body and an `X-Sabre-Version` header. With nothing registered, a `GET`
on `/dav` returns 501 from the engine, which also confirms the wiring.

## Validate the rebuilt package

Setup once:

```sh
composer install
```

### Automated suites

```sh
vendor/bin/phpunit                       # unit and integration suites
vendor/bin/phpunit --testsuite Unit      # registry, identity, translation units
vendor/bin/phpstan analyse               # level 5, larastan, strict, safe rules
vendor/bin/psalm                         # second analyser, must be clean
```

Coverage for SC-008 needs a coverage driver:

```sh
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

### Scenario checks mapped to success criteria

| Check | How to run it | Pass condition |
|---|---|---|
| SC-002, every method reaches the engine | Integration test issuing one request per method in the `methods` config list | No response comes from the framework's method-not-allowed or not-found handler; each carries the engine version header |
| SC-004, environment parity | Same integration cases parameterised over the testing, local and production environments | Identical status, headers and body; only diagnostic detail differs |
| SC-003, upgrade parity | Replay `tests/Compatibility/` recorded pairs | Every pair matches 1.x except entries explicitly marked as deviations |
| SC-005, no cross-request leaks | Integration test issuing 1,000 sequential requests alternating two signed-in users | Every response carries only its own request's identity |
| SC-006, streaming memory | Integration test downloading a 100 MB resource while sampling peak memory | Peak increase under 20 MB. Measured mechanism cost is 0.00 MB, against 98.00 MB for the 1.x buffering call |
| SC-009, suppression budget | Count inline suppressions in `src/` plus config-level entries in `phpstan.neon` and `psalm.xml` | At most 11 against the 1.x baseline of 22, none broader than one symbol |
| SC-007, real clients | Point one contacts client and one calendar client at a reference application | Both discover, list and sync |
| SC-008, matrix | Push a pull request and let the shared workflow run all 8 cells | Every cell green, coverage at least 90% |
| SC-001, onboarding | Give a developer only the README | First successful `PROPFIND` within 15 minutes |

### Mechanism probes already verified

These were run during Phase 0 against laravel/framework 13.31.0, sabre/dav 4.7.1, sabre/http 5.1.13
and PHP 8.4.25, and become permanent tests during implementation:

- A route registered with an explicit method list answers `PROPFIND`, `MKCALENDAR`, `ACL` and `REPORT`
  while the framework's default verb list stays untouched.
- An engine server built with an injected SAPI and a request assembled from the framework request
  returns 207 with the request keys of `$_SERVER` unset, so no superglobal is consulted.
- Copying a 100 MB body stream to output costs 0.00 MB of peak memory; the current buffering call costs
  98.00 MB.

See [research.md](./research.md) for the reasoning, the alternatives and the exact findings.
