# 1.x Baseline and Rebuild Measurements

**Feature**: [spec.md](./spec.md) | **Captured**: 2026-09-09 | **Tasks**: T002, T004, T036, T047, T073, T079

Recorded from the 1.x source before the rebuild replaced it. This file is the reference for SC-003,
SC-006 and SC-009, and for the deviation list the migration guide carries.

## Suite

| Item | 1.x | Rebuild |
|---|---|---|
| Tests | 20 passing, 58 assertions | see final section |
| Suites | one suite over `tests/` | Unit, Integration, Compatibility |

## Static analysis suppressions

| Location | 1.x count |
|---|---|
| Inline annotations in `src/` | 22 |
| `phpstan.neon` ignoreErrors entries | 1 |
| `psalm.xml` suppress blocks | 1 |
| Total | 24 |

1.x inline breakdown: 6 `PossiblyUnusedMethod`, 4 `UnusedClass`, 4 `ClassMustBeFinal`,
2 `TooManyArguments`, and one each of `UndefinedMagicPropertyFetch`, `UndefinedClass`,
`InvalidReturnStatement`, `InvalidPropertyAssignmentValue`, `InvalidArgument`, plus one
`@phpstan-ignore`. SC-009 caps the rebuild at 11 inline, none broader than one symbol.

## Streaming memory

Measured with a 100 MB body, PHP 8.4.25:

| Mechanism | Peak memory increase |
|---|---|
| `stream_get_contents` as 1.x does | 98.00 MB |
| `stream_copy_to_stream` as the rebuild does | 0.00 MB |

## Compatibility recordings

24 recordings in `tests/Compatibility/recordings/`, captured through the real route against 1.x with
`LARAVELSABRE_RECORD=1`. Statuses as recorded:

| Status | Recordings |
|---|---|
| 200 | get-file, head-file, options-root, lock-file |
| 201 | put-new-file, mkcol-collection, copy-file, move-file |
| 204 | delete-file, put-existing-file |
| 207 | five PROPFIND variants, proppatch-file |
| 401 | anonymous-request-is-challenged |
| 403 | access-rule-denies |
| 405 | mkcalendar-is-routed, acl-is-routed (both deviations) |
| 409 | unlock-unknown-token |
| 415 | report-expand-property, report-unsupported |
| 501 | get-root-nothing-registered |

## Authorised deviations

The five defect fixes the spec authorises. Each is asserted by a regression test and listed in
`MIGRATION.md`.

| Deviation | 1.x behaviour | Rebuild behaviour | Evidence |
|---|---|---|---|
| `MKCALENDAR` routed | 405 from the router, engine never reached | Engine answers the method | recording `mkcalendar-is-routed`, `tests/Integration/MethodCoverageTest.php` |
| `ACL` routed | 405 from the router, engine never reached | Engine answers the method | recording `acl-is-routed`, `tests/Integration/MethodCoverageTest.php` |
| Request source | Raw process request outside the testing environment | Framework request after middleware in every environment | `tests/Integration/RequestFidelityTest.php` |
| Streaming | Whole body buffered into a string | Copied stream to stream | `tests/Integration/StreamingTest.php`, memory table above |
| Mixed plugin registration | `plugin()` after `plugins(Closure)` raised | Every ordering works | `tests/Unit/RegistryTest.php` |
| Missing principal value | Produced the principal `principals/` | Raises `PrincipalResolutionException` | `tests/Unit/PrincipalResolverTest.php` |

## Rebuild results

| Phase | Result |
|---|---|
| 1, setup | 1.x baseline green at 20 tests; 24 recordings captured |
| 2, foundational | Registry, entry point, provider and route in place; unit suite green |
| 3, User Story 1 | Engine bridge in place; 54 unit and 57 integration tests green |
| 4, User Story 2 | Access gate and identity bridge in place; both analysers clean |
| 5, User Story 3 | Placement, master switch, publishing and custom middleware in place |
| 6, User Story 4 | All 24 recordings replay; 2 authorised deviations confirmed |
| 7, User Story 5 | Quality gates added as tests; coverage at 100% |
| 8, polish | Quickstart validated command by command |

## Final measurements

| Metric | 1.x | Rebuilt | Target |
|---|---|---|---|
| Tests | 20 | 236, of which 24 are the recording fixtures, skipped unless re-recording | every scenario covered (FR-027) |
| Assertions | 58 | 2,629 | n/a |
| Line coverage of `src/` | not recorded | 100.00% (193/193) | at least 90% (SC-008) |
| Method coverage | not recorded | 100.00% (52/52) | n/a |
| Class coverage | not recorded | 100.00% (14/14) | n/a |
| Inline suppressions in `src/` | 22 | 0 | at most 11 (SC-009) |
| Config-level suppressions | 2 | 0 | none broader than a symbol |
| PHPStan level 5 | passing with 1 ignore rule | passing with none | clean (constitution IV) |
| Psalm | passing with 1 suppress block | passing with none | clean |
| Source files in `src/` | 10 | 15 | 14 to 16 per plan.md |
| Peak memory, 100 MB download | 98.00 MB | 0.00 MB measured on the copy, asserted under 20 MB end to end | under 20 MB (SC-006) |
| Methods reaching the engine | 15 of 17 | 17 of 17, plus any configured | 100% (SC-002) |
| Recordings reproduced | n/a | 22 of 22 non-deviating, 2 of 2 deviations confirmed | 100% (SC-003) |

Defect fixes confirmed in Phase 3 (T036):

| Fix | Evidence |
|---|---|
| `MKCALENDAR` and `ACL` reach the engine | `MethodCoverageTest` asserts the engine version header for every configured method; the recordings show 405 under 1.x |
| Framework request in every environment | `RequestFidelityTest` asserts method, path, headers, query and body under testing, local and production |
| Streaming without buffering | `ResponseFactoryTest::test_a_hundred_megabyte_body_is_not_buffered` measures under 20 MB; `StreamingTest` covers correctness and the length cap |
| Mixed plugin registration | `RegistryTest` covers single before bulk, single after a bulk closure, and single between two bulk registrations |
| No superglobal read | `RequestFactoryTest::test_it_reads_no_superglobal` and `ServerTest::test_no_request_is_read_from_the_process` run with the `$_SERVER` request keys unset |
