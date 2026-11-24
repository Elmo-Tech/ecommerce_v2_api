# F0-02 — Quickstart and real CI evidence

**Status:** T001–T026 complete. PR #5 pointer merged; latest PR #4 independent review is rate limited, owner development-only merge approval recorded subject to gates. PR #4 unmerged; T027–T030 open.

## Actual GitHub Actions runs (2026-11-24)

| Stage | Source head | Run / job | Actual result |
| --- | --- | --- | --- |
| T004 baseline | e207b2fa2ffffa528fc9114be12564e62f99a810 | [45463661520](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45463661520) / 138692781749 | 31 passed / 134 assertions; independent Architecture 3/23; all CI steps passed. Actual checkout 7fd9a9096bf7eb64a0073e43f0ebdf0475ae0264. |
| T014 RED | 78e557e57c07260530a4179829598f3af4527ac0 | [45464194395](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464194395) / 138694580668 | 52 expected failed, 31 passed / 207 assertions; original group 31/134. Actual checkout 86adc5a17af15ca3b35e898c6e62aad17e0cb8f2. Later CI steps skipped, not passed. |
| Initial GREEN | a0cd1496978e15d83929f3ebb1fa7a9e48799007 | [45464656708](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464656708) / 138696216089 | 83 passed / 703 assertions; standalone Architecture 5/52; all CI steps successful. Checkout 5893376d8a70befbcd4742fa3e177040272f33ec. |
| Hardening RED | e9247e64eea4e30f1bb873754f7fa47385a7c6e0 | [45464973375](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464973375) / 138697336509 | 4 failed, 83 passed / 667 assertions; malformed path contract, invalid list descendants and log dedup reproduced. Later checks skipped. |
| Final GREEN | d0476e7d4fbcd096879995a0f48754b6c064beb1 | [45465135907](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45465135907) / 138697914602 | **87 passed / 726 assertions**, standalone Architecture **5/52**, old foundation group 31/134; all CI steps successful. Actual checkout **7d6af96d948cc1d81b23b68bae5df73ca2737bde**, development 43f309749c544040385977a530e78fd378f43b91. |
| Ready recheck | 85411378f739c413038f04e19ac227877275fc4d | [45465527841](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45465527841) / 138699339635 | **87/726**, standalone Architecture **5/52**, baseline 31/134; all steps green. Actual checkout 3d17dd9b1b47c0d22c06df6cc95b62cb782af9ae. |
| Independent review RED | 1b80cd9e35096764f0216dffd2861138097e0001 | [45493392229](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45493392229) / 138797386444 | 9 failed, 89 passed / 855 assertions; baseline 31/134; subsequent checks skipped due to RED. Checkout dce5af7. |
| Independent review GREEN | 1ad96e19ab8aceb6c33678720d2474d1c97417a4 | [45493622260](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45493622260) / 138798183749 | **98/911**, Architecture **5/52**, baseline **31/134**. All steps green. Actual checkout 1ab916fb29f84d5a9d262825f04879fd36a4ddd5. |

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
- Per-request UUID, locale and diagnostics; actual reused application tests including error paths; bounded ar/en negotiation and ar fallback; no mutable global API context. Native safe reporting scope; WeakMap dedup preserves independent exceptions. The native hook emits api.exception_reported with exception_status/exception_error_code; finalizer emits api.request_failed with actual sanitized rendered HTTP status/code, at info when already error-reported. Explicit native control-flow responses retained; unexpected custom success/redirect converted to safe failure. Nested restoration cleanup and exact escaping-exception WeakReference scope reporting after middleware unwinding; no global context retained. No guarantee for failures outside the HTTP app lifecycle.
- Field errors use generic safe localized messages, not raw Laravel validator text. Canonical field paths ≤256 ASCII bytes; nonempty field errors require VALIDATION_FAILED/422. Native Factory resolver selects ApiFieldValidator during an active API attempt only (native Validator outside). It uses Laravel's original wildcard provenance and real list membership to emit declared literals/numeric indices or trusted parent paths. Unprovable manual/custom-validator error keys yield generic 422, not caller-controlled paths. Future Features must declare safe fields/list rules, review any dynamic map or custom validator resolver, and explicitly own more specific safe messages. The adapter relies on protected framework provenance metadata; rerun regressions on Laravel upgrades. No raw input values, no domain catalog.
- Shared [OpenAPI JSON](contracts/openapi.json): empty paths, envelope/error/field/header components, canonical FBR-02 Money/ID strings with documented bounds/currency context, no fake business resource. Native JSON/local ref/representative HTTP checks, **not** full standards-conformance validation or generated client. Feature enforces actual BIGINT bounds/currency.
- Approved trigger filters `lang/**` and `specs/002-api-foundation/contracts/**` included on PR; source config inspected and actual integrated workflow passed. Trigger-only adjustment moved ahead for baseline without application changes.
- Scoped diff from pre-execution `e6ea55d3cfa86e2c7423184b114d42d73b26710f` through `d0476e7d4fbcd096879995a0f48754b6c064beb1`: 35 paths, only approved scope; 26 runtime/test/contract/workflow paths inspected for high-confidence credential patterns, zero matches; Composer files/explicit registry/provider/probe identical; no migrations, new dependencies or production routes. This is not full repository history or penetration audit.

## Limits and next gate

Pint and PHPStan/Larastan absent; workflow emitted notices, no pass claimed. Existing Node action version deprecation warning recorded; modernization belongs to F0-04 separately. Real Auth/CSRF, sensitive rate-limiter fallback, Tenant DB/PostgreSQL, Redis/Horizon, Octane workers, performance and production security are later applicable features, not proved here. Milestone tests for zero production routes/no new packages/no migrations must be evolved under explicitly approved subsequent Features, not silently disabled. D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED.

T026 complete via verified PR #5 merge. T027 latest-head review blocked by CodeRabbit rate limit; owner consent recorded but T028 remains open until review and final current integrated CI. T029–T030 require verified merge and final handoff. No main promotion, deploy or automatic merge. Rollback remains a reviewed revert of feature changes in a later PR.


## Independent review remediation limits

Owner-authorized IR-01…06 remediation: independent [RED #45493392229](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45493392229), source `1b80cd9e35096764f0216dffd2861138097e0001`, job 138797386444, checkout dce5af7, **9 failed, 89 passed / 855 assertions**. Subsequent [GREEN #45493622260](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45493622260), source `1ad96e19ab8aceb6c33678720d2474d1c97417a4`, job 138798183749, integrated checkout `1ab916fb29f84d5a9d262825f04879fd36a4ddd5` against development `43f309749c544040385977a530e78fd378f43b91`: **98 passed / 911 assertions**, standalone Architecture **5/52**, original group **31/134**, PHP 8.4.28, Composer 2.11.3, Laravel 13.43.0. All workflow steps succeeded, including Composer audit/platform, PHP syntax, route/cache checks and clean source diff. Diff from prior reviewed head contains only 14 PHP test/runtime files and 2 dated docs, bounded high-confidence credential scan of the 14 PHP files returned zero matches. No dependencies, registry membership, routes or migrations added.
Critical shared translation catalog parity is now tested; known missing code diagnostics are allowlisted, unknown keys not logged. Manual report() events are not treated as failed HTTP responses. Logging outcome describes the exception response rendered by the native handler, not a universal access log or proof of failure after response delivery. Exact escaping-exception identity is tested for locale event failures; framework bootstrap failures before the boundary or unavailable handler infrastructure remain outside the universal guarantee. No actual Octane/worker/load testing or new quality tooling in this feature.


## Owner merge authorization and verified handoff integration

Owner explicitly authorized approval then integration into development only (reply “تمام اعتمد ثم ادمج” on 2026-11-24). Authorization is conditional on the previously agreed review and actual integrated CI gates; it is not a waiver. [PR #5](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/5) merged=true, merge SHA `f0febe3acdd20f84230476629aa9cb759df5ca7e`, after CodeRabbit completed with zero actionable comments on `d24639c56cd383f48c7fa7b97dd5fafa68486736`. Post-merge development ref and pointer blob verified; only `docs/handoff/NEXT_CHAT_START_HERE.md` changed. T026 complete. PR #4 remains unmerged. [CI #45494073701](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45494073701), job 138799812430, source `9d4ce9fe045b730b15c631f437c82ca81b65cb3c`, actual integrated checkout `e6630408b94a057caf2f7a28d95d4f13493c3aac` against prior development `43f309749c544040385977a530e78fd378f43b91`: 98/911, standalone Architecture 5/52, original group 31/134, audit/lint/platform/cache and clean diff green. Subsequent development change is pointer-only; recheck current integrated candidate after this docs update. Latest CodeRabbit review of PR #4 was explicitly rate limited, not completed ([bot response](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4#issuecomment-7145328884)); zero actionable comments pertains to the older reviewed head `4bb5964e3cbb63290e400f23158cbafe0c1f66e3`. No formal approval or new substantive review claimed. Do not bypass rate limit or independent review gate. T027 remains open, T028 owner consent recorded but gate incomplete until T027 and final base/head verification, T029/T030 not executed. No deployment/main promotion.
