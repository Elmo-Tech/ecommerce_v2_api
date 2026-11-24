# F0-02 — Quickstart and real CI evidence

**Status:** Implementation and technical verification T001–T025 complete. T026 feature documentation complete, permanent development handoff update blocked. T027 independent PR review requested; Ready CI passed, substantive review result and separate merge authority pending. No Codespaces, deployment, migrations, new packages or production access.

## Actual GitHub Actions runs (2026-11-24)

| Stage | Source head | Run / job | Actual result |
| --- | --- | --- | --- |
| T004 baseline | e207b2fa2ffffa528fc9114be12564e62f99a810 | [45463661520](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45463661520) / 138692781749 | 31 passed / 134 assertions; independent Architecture 3/23; all CI steps passed. Actual checkout 7fd9a9096bf7eb64a0073e43f0ebdf0475ae0264. |
| T014 RED | 78e557e57c07260530a4179829598f3af4527ac0 | [45464194395](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464194395) / 138694580668 | 52 expected failed, 31 passed / 207 assertions; original group 31/134. Actual checkout 86adc5a17af15ca3b35e898c6e62aad17e0cb8f2. Later CI steps skipped, not passed. |
| Initial GREEN | a0cd1496978e15d83929f3ebb1fa7a9e48799007 | [45464656708](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464656708) / 138696216089 | 83 passed / 703 assertions; standalone Architecture 5/52; all CI steps successful. Checkout 5893376d8a70befbcd4742fa3e177040272f33ec. |
| Hardening RED | e9247e64eea4e30f1bb873754f7fa47385a7c6e0 | [45464973375](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464973375) / 138697336509 | 4 failed, 83 passed / 667 assertions; malformed path contract, invalid list descendants and log dedup reproduced. Later checks skipped. |
| Final GREEN | d0476e7d4fbcd096879995a0f48754b6c064beb1 | [45465135907](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45465135907) / 138697914602 | **87 passed / 726 assertions**, standalone Architecture **5/52**, old foundation group 31/134; all CI steps successful. Actual checkout **7d6af96d948cc1d81b23b68bae5df73ca2737bde**, development 43f309749c544040385977a530e78fd378f43b91. |
| Ready recheck | 85411378f739c413038f04e19ac227877275fc4d | [45465527841](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45465527841) / 138699339635 | **87/726**, standalone Architecture **5/52**, baseline 31/134; all steps green. Actual checkout 3d17dd9b1b47c0d22c06df6cc95b62cb782af9ae. |

Real Ubuntu 24.04 runner, PHP **8.4.28**, Composer **2.11.3**, Laravel **13.43.0**, Pest **5.3.7**, Laravel plugin 5.0.1, PHPUnit 13.4.7; unchanged committed lock. Initial concurrent run 45463631362 cancelled; not success evidence. Logs inspected for checkout SHA, versions, counts, audit and lint. Final integrated run checked actual `refs/pull/4/merge` not simply feature source.

## Commands actually executed in workflow

```bash
php -v
composer --version
composer validate --strict --no-check-publish
composer check-platform-reqs --lock
composer install --no-scripts --no-plugins --no-interaction --prefer-dist --no-progress
composer audit --locked --no-interaction
composer check-platform-reqs
php -l bootstrap/app.php
php -l routes/console.php
php artisan about --no-ansi
php artisan route:list --json
# Runner-local empty ignored .env, isolated testing environment.
COLUMNS=180 php artisan test --group=foundation --fail-on-empty-test-suite --display-warnings --fail-on-warning --no-ansi
COLUMNS=180 php artisan test --fail-on-empty-test-suite --display-warnings --fail-on-warning --no-ansi
vendor/bin/pest --testsuite Architecture
git ls-files -z -- '*.php' | xargs -0 -r -n 1 php -l
git diff --exit-code -- .
```

No DB/migrations/production credentials. Isolated test config: array cache/session, sync queue, in-memory SQLite configuration without opening business DB connections. Real production/local boot and actual empty collection route cache behavior tested; scratch copies of actual new bootstrap/config register only a harmless control and generate/load real route cache. Original F0-01 probe remains testing only.

## Implementation contract and safety review

- Native `/api/v1` boundary, `ApiResponse` 200/201 and zero-body 204; true 4xx/5xx, approved codes; preserve native redirects/downloads and applicable reviewed protocol headers. No new production routes or catch-all. Future genuinely pending async 202 belongs to its Feature.
- Per-request UUID, locale and diagnostics; actual reused application tests including error paths; bounded ar/en negotiation and ar fallback; no mutable global API context. Native safe reporting scope; WeakMap dedup preserves independent exceptions. Report-hook diagnostics classify exceptions before any custom render; finalizer enforces actual response status/code. No guarantee for failures outside the HTTP app lifecycle.
- Field errors use generic safe localized messages, not raw Laravel validator text. Canonical field paths ≤256 ASCII bytes; omit untrusted descendants when a declared Laravel list rule fails. Future Feature FormRequests must define approved fields and list rules, review dynamic-map paths and specific safe translations; generic mapper cannot infer arbitrary domain schema from expanded rules. No raw input values, no domain catalog.
- Shared [OpenAPI JSON](contracts/openapi.json): empty paths, envelope/error/field/header components, canonical FBR-02 Money/ID strings with documented bounds/currency context, no fake business resource. Native JSON/local ref/representative HTTP checks, **not** full standards-conformance validation or generated client. Feature enforces actual BIGINT bounds/currency.
- Approved trigger filters `lang/**` and `specs/002-api-foundation/contracts/**` included on PR; source config inspected and actual integrated workflow passed. Trigger-only adjustment moved ahead for baseline without application changes.
- Scoped diff from pre-execution `e6ea55d3cfa86e2c7423184b114d42d73b26710f` through `d0476e7d4fbcd096879995a0f48754b6c064beb1`: 35 paths, only approved scope; 26 runtime/test/contract/workflow paths inspected for high-confidence credential patterns, zero matches; Composer files/explicit registry/provider/probe identical; no migrations, new dependencies or production routes. This is not full repository history or penetration audit.

## Limits and next gate

Pint and PHPStan/Larastan absent; workflow emitted notices, no pass claimed. Existing Node action version deprecation warning recorded; modernization belongs to F0-04 separately. Real Auth/CSRF, sensitive rate-limiter fallback, Tenant DB/PostgreSQL, Redis/Horizon, Octane workers, performance and production security are later applicable features, not proved here. Milestone tests for zero production routes/no new packages/no migrations must be evolved under explicitly approved subsequent Features, not silently disabled. D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED.

T026: feature docs and dated handoff reconciled; direct permanent `development` handoff update was blocked, so remain open. T027: PR Ready; CodeRabbit manual review requested and acknowledged, no substantive review returned as of latest check. Ready recheck passed, but final current candidate CI required after any further changes. T028 explicit separate owner merge authority. T029–T030 verify integration into development and reconcile handoff. No main promotion, deploy or automatic merge. Rollback: reviewed revert of feature commits in a later PR; no database rollback/destructive command needed for this scope.
