# F0-03 Testing Foundation — Quickstart and verified evidence

**Date:** 2026-11-25 (Africa/Cairo). **Feature:** [PR #7](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/7), Draft; development only. **Owner-approved design:** [spec](spec.md), [plan](plan.md), [tasks](tasks.md). This document records the tested implementation source; a subsequent docs-only evidence commit must still receive current candidate CI/review. No deployment or merge authorization.

## Local fast lane — isolated clean test checkout only

Use the committed PHP 8.4-compatible Composer lock. No new package or application provisioning is required. Do not run this in a production/shared checkout. The guard rejects nonempty/symlinked dotenv, unexpected dotenv/config cache and unsafe inherited service settings; do not overwrite a real .env to make a guard pass. Test defaults are SQLite memory, array cache/session/mail and sync queue; no actual business DB/Redis access. Supported commands (after locked dependencies already installed in the clean checkout):

```sh
php tests/Support/run-tests.php fast
php tests/Support/run-tests.php fast --group=foundation
php tests/Support/run-tests.php fast --suite=Architecture
php tests/Support/run-tests.php fast --group=testing-foundation
```

All required selections use fail-on-empty/warning/risky/skipped/incomplete. Direct vendor startup is not the pre-Composer boundary; use guarded launcher. Do not use arbitrary configuration or global database refresh traits. Native Integration is intentionally not a local/shared-service option under DEC-03 A.

## Hosted native lane — GitHub Actions only

Use the reviewed workflow [f0-01-laravel-bootstrap.yml](../../.github/workflows/f0-01-laravel-bootstrap.yml) on the feature PR against development. Do not forge hosted-job environment values, reuse descriptors or manually target services. The actual workflow verifies immutable image pulls and native driver versions, binds a fresh run identity, captures exact owned containers before start, validates ports/images/volumes, provisions synthetic restricted C/A/B PostgreSQL and A/B Redis fixtures, runs the complete native suite, always attempts owned cleanup, verifies repeat absence and publishes safe reports. The workflow invokes fixed commands in its owned context:

```sh
php -d zend.exception_ignore_args=1 tests/Support/ci/start-owned.php
php -d zend.exception_ignore_args=1 tests/Support/ci/setup-owned.php
php -d zend.exception_ignore_args=1 tests/Support/ci/run-report.php integration
php -d zend.exception_ignore_args=1 tests/Support/ci/cleanup-owned.php
php -d zend.exception_ignore_args=1 tests/Support/ci/cleanup-owned.php
```

Fast CI runs run-report.php foundation, fast-all and architecture with the same guarded launcher. All four fixed selections use private output and validated derived JUnit/summary artifacts. Reports retained seven days; not source/production backups. Missing/failed required services or invalid/unsafe reports fail closed. No fallback to SQLite for native cases.

## Exact current implementation evidence

Actual [CI #45609420261](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45609420261), attempt 1, both required jobs completed successfully on exact source `5b912b7e60ced8900049d361dc8fc50cba2e36aa`, base `97c5895bd5672261247b45f1b7eca5648afaf163`, integrated checkout `41f27e1cbe92b3c4c9e5b1f4bc55b400aac8309a`. Fast job 139188768774: 408 tests / 2499 assertions, original foundation 31/134, independent Architecture 9/101; native job 139189104128: 34 tests / 304 assertions (26 PostgreSQL, 8 Redis). All full selections PASSED, selection_verified=true, runner exit 0, no failures/errors/skips. The only skipped steps are fallback BLOCKED reporters (correctly not needed); no required test skipped. Actual wrapper durations: fast-all 4.625 s, foundation 1.550 s, Architecture 0.130 s, Integration 23.933 s. PHP 8.4.28 / Composer 2.11.3 / pdo_pgsql 8.4.28 / PhpRedis 6.3.0, locked Laravel 13.43.0/Pest 5.3.7/PHPUnit 13.4.7. Exact approved image IDs and digests verified, actual PostgreSQL 17.12/Redis 8.2.11 startup, grants and ACL passed. Locked Composer validate/install/platform/audit (no advisories reported), tracked PHP lint, clean tracked diff, actual owned cleanup and repeat, artifact upload and report cleanup all passed. Actual independently downloaded artifacts 15018893836 and 15018193701: exactly six fast and two integration allowlisted XML/JSON files, counts, durations (per-case rounded values), source/base/checkout/run matching, no raw output/property/error/skip tags. Compared actual prior 400 fast cases (including assertions) to final 408: zero removed/changed, eight added. All 34 prior Integration cases unchanged. Seven-day retention verified from artifact metadata. No new package, app, schema, real tenant, deployment or main change.

| Selection | Tests | Assertions | Wrapper elapsed (s) |
| --- | ---: | ---: | ---: |
| Fast full | 408 | 2499 | 4.625 |
| Native full | 34 | 304 | 23.933 |
| Original foundation (subset rerun) | 31 | 134 | 1.550 |
| Architecture (subset rerun) | 9 | 101 | 0.130 |

442 unique selected cases / 2803 assertions across disjoint fast and native suites. Do not add repeated subset totals. Actual reports: [fast](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45609420261/artifacts/15018893836), [native](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45609420261/artifacts/15018193701), [lock](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45609420261/artifacts/15019661671).

Approved test images:
- PostgreSQL 17.12-bookworm manifest sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03; actual image ID sha256:850a2f036953dbe09006295666ee46737e4a15f9ede370bf3bf5718ec0a9e087.
- Redis 8.2.11-bookworm manifest sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87; actual image ID sha256:443f407d5d5a3ab81be78ebb136791c5d90f4e469231bc922dd187f55660fac6.
- GitHub-hosted Ubuntu 24.04 linux/amd64; actual native driver versions as above. Test versions only, not production version decisions.

## Retained RED and diagnostic evidence

Baseline #45591182691 (98/911), actual preflight #45591605943, tests-first RED #45593286201; full fast first GREEN #45605270512 with native startup rejected; partial startup #45605653960, associative ordering mismatch #45606381139/#45606685160, Redis named-user DRYRUN correction #45607123679; first full GREEN #45607457879, repeated GREEN #45608290728. Actual controlled #45609081693: fast 408/2499, native 34/304, separate diagnostic intentionally FAILED; absent-owned prerequisite and invalid destination rejected, unknown cleanup file untouched, actual missing upload failed, three safe BLOCKED summaries uploaded (artifact 15019007413) and independently inspected; eight real Pest diagnostic cases incl. generated synthetic credential canary passed. Temporary diagnostic removed (exact original workflow restored), permanent eight tests retained. See [canonical ledger](tasks.md) and dated [T031 handoff](../../docs/handoff/F0-03_FAILURE_PATHS_VERIFIED_FINAL_CI_NEXT_2026-11-25.md). This is not an automatic retry-to-green; failures remain visible with explained corrections.

## Bounded scope and security review

Bounded T033 scope/security review of exact tested source `5b912b7e60ced8900049d361dc8fc50cba2e36aa` vs development `97c5895bd5672261247b45f1b7eca5648afaf163`: 60 changed files, no app/config/bootstrap/routes/database/public/lang/Composer file changes; old test sources remain untouched (new tests only; the two XML test configurations and scoped workflow changed). No production test registration; original F0-01/F0-02 tests retained; architecture suite and original group verified. Reviewed TestEnvironmentGuard.php lines 44–259 (safe env/defaults, checkout/dotenv/cache and report paths); IntegrationContext.php lines 16–216 (independent job inputs, protected records, live observation); LocalDocker.php lines 65–259, 395–568, 571–656 (local daemon, capture, pinned identities, loopback, exact resources and cleanup); IntegrationConnections.php lines 60–185 (actual role/DB identity, fresh native connections, closed denial matrix and Redis identity); OwnedServicePlan.php lines 81–173, PostgreSqlFixturePlan.php lines 20–107, RedisFixturePlan.php lines 12–97 (restricted runtime privileges and fixed dry-run command data, no global command execution); SafeTestReport.php lines 18–256 and run-report.php (bounded native JUnit, safe derived reports, exit preservation and hash scan); NativeReportFailureTest.php lines 54–126 (actual diagnostic coverage); workflow lines 46–47, 120–207, 213–361 (read-only contents, no persisted checkout credentials, separate lanes, exact allowlisted uploads and unconditional cleanup). No broad destructive migration/flush/volume removal, no shared endpoint or production credentials introduced. Actual real 42501/CONNECT and NOPERM proofs are from native selected suite, not simulated denial. No material blocker identified in this bounded scope review; this is not independent human approval. Known limits: accidental-target barrier, not a malicious-code sandbox; sequential isolated hosted jobs, not shared/local integration or capacity; abrupt loss before capture not guaranteed recoverable; report minimum-selection policy is not a general test-inventory guarantee (exact current case inventory independently compared); no image vulnerability scanner, Pint/PHPStan/Larastan/full OpenAPI validation, actual app Tenancy/Auth/worker/commerce/production readiness; Node action deprecation warning retained for F0-04.

## Rollback and next gates

Before merge, request changes on the feature branch; use reviewed source revert/new fix PR if needed, never rewrite development history or remove old evidence. There are no domain migrations or persistent merchant data to roll back. Re-run relevant full current-candidate tests on changes. T032–T034 evidence complete at the tested implementation source; T035 technical PR review and exact final candidate verification (including docs-only recheck) next. T036 actual owner/qualified independent human review for applicable Critical/High scope, then T037 separate development-only merge consent; T038–T040 verified integration and handoff publication. No CodeRabbit/Copilot mandatory gate, no self-approval or auto-merge. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED. F0-04/F0-05 separate.
