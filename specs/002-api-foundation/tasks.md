---
description: "F0-02 API Foundation approved specification and technical plan; tasks and consistency review, implementation gate pending"
---

# Tasks: F0-02 — API Foundation

**Status (2026-11-24):** Owner approved the complete spec/plan at `c9897aac60975b2e5aa90583480972c02152bb0d` in reply “تمام يلا” to the explicit approval-and-tasks proposal. **3/30 completed (documentation and authorization only)**; T003 approved 2026-11-24. T004 real runtime preflight pending. [Spec](spec.md) · [Plan](plan.md) · [Research](research.md) · [Consistency analysis](consistency-analysis.md) · [PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4).

**Scope:** Native Laravel 13/PHP 8.4 API v1 HTTP foundation only; no new dependency, business API, auth/CSRF runtime, tenant database, migration, Redis/Horizon, deployment or Codespaces. 27 outstanding tasks remain unchecked; no runtime evidence for F0-02 yet. T001/T002/T003 are documentary/approval checkpoints, not passing tests. Implementation authorized within scope only; merge requires separate T028 authorization.

**Format:** `Tnnn [P] [USn]` — [P] means separate files eligible to run in parallel once dependencies are complete; multiple story labels are shared technical dependencies. Sequential execution is safe. Every task names evidence or expected acceptance; mark complete only when verified. References in dependency and trace tables are exact.

## Phase 0 — Owner approvals, consistency and preflight (blocking)

- [x] **T001** Record owner approval of the full revised spec and plan at reviewed SHA `c9897aac60975b2e5aa90583480972c02152bb0d`, including DEC-01…04 and additional HTTP codes; link the dated approval handoff. Approval to prepare tasks/analysis only, not implementation or merge. **Depends:** none. **Trace:** FR-010.
- [x] **T002** Complete documented consistency analysis (`consistency-analysis.md`): all FR/AC/story/Constitution coverage, task dependency DAG, scope and current GitHub baseline; record outstanding execution gates. **Depends:** T001. **Trace:** FR-001, FR-007, FR-008, FR-009, FR-010, AC-10.
- [x] **T003** Owner explicitly authorized execution of this task ledger and reviewed scope on 2026-11-24 (“تمام يلا” in response to the T003 approval prompt). See [authorization and preflight handoff](../../docs/handoff/F0-02_IMPLEMENTATION_AUTHORIZED_2026-11-24.md). No merge, deployment, new package or broader scope authorized. **Depends:** T002. **Trace:** FR-008, FR-010.
- [ ] **T004** Preflight on GitHub: compare current feature HEAD and development; require no unreviewed runtime divergence, verify locked dependency versions and existing validation workflow, and run real isolated baseline PHP 8.4/Composer/Pest validation against the reviewed revision. Record run SHA and exact outcomes; no Codespaces, production data, new packages or automatic merge. **Depends:** T003. **Trace:** FR-001, FR-008, FR-009, FR-010, AC-10.

## Phase 1 — Tests first (RED)

- [ ] **T005** [US1] [US2] [US3] Create testing-only fixture support in `tests/Fixtures/` for HTTP scenarios, with route registration scoped to the test application. Include deterministic nested validation rules, genuine native exception types/status/header fixtures and safe control responses. Do not register any new production route or alter the existing FoundationDemo probe contract. **Depends:** T004. **Trace:** FR-001, FR-003, FR-004, FR-008, FR-009, AC-03, AC-04, AC-05, AC-09.
- [ ] **T006** [P] [US1] Write `tests/Unit/Core/ApiResponseTest.php` for success 200/201 data and optional truthful meta, strict errorCode/error arrays vs field object, 204 body length zero, genuine failure status; verify int-backed HTTP enum and stable constants. Contract assertions must reject contradictory success/status combinations or malformed envelopes. **Depends:** T005. **Trace:** FR-002, FR-003, FR-004, FR-009, AC-01, AC-02, AC-03, AC-05.
- [ ] **T007** [P] [US1] Write `tests/Feature/Foundation/ApiExceptionContractTest.php`: actual framework rendering for 400, native AuthenticationException, AuthorizationException, unmatched v1 route, native method mismatch 405, 409, 422 non-field, 429, 500, 503 and other genuine HTTP 4xx/5xx; require stable mapped code, preserved applicable Allow/Retry-After/WWW-Authenticate, safe response even APP_DEBUG=true, no raw SQL/path/secret/trace or forced login redirect. Use synthetic exceptions, not installed auth/limiter. Test native redirects/files and unrelated HTTP behavior remain intact. **Depends:** T005. **Trace:** FR-003, FR-005, FR-008, FR-009, AC-04, AC-05, AC-06.
- [ ] **T008** [P] [US1] Write `tests/Feature/Foundation/ApiValidationContractTest.php`: use actual Laravel validation/FormRequest on a test-only route for camelCase `customerName` and `items.0.quantity`, stable 422 and field-keyed object, no sensitive values echoed; generic failures have `errors: []`. Check stable keys/code across ar/en without indiscriminately transforming client keys. **Depends:** T005. **Trace:** FR-003, FR-004, FR-006, FR-009, AC-03, AC-07.
- [ ] **T009** [P] [US2] Write `tests/Unit/Core/ApiLocaleNegotiationTest.php` for allowlist, region/case, descending q, equal q first appearance, wildcard/exclusion and duplicate/malformed policy, byte/range bounds, absent/unsupported fallback, no q=0 preference selection. Write `tests/Feature/Foundation/ApiLocalizationTest.php` for real translated generic/validation messages, missing English then missing both fallback, safe diagnostics, unchanged status/code/path. Cover documented negotiation cases below. **Depends:** T005. **Trace:** FR-006, FR-009, AC-07.
- [ ] **T010** [P] [US2] Write `tests/Feature/Foundation/ApiRequestLifecycleTest.php`: one generated UUID per attempt, exact same ID on response and safe diagnostic, ignore supplied/spoofed header, no ID JSON field, 200/201/204 and native safe failures including unmatched 404, 405, 500 and validation. Exercise multiple requests against a reused application, including ar→en→failure→ar, verify previous/default translator and logging state restored, no sensitive raw exception values in captured technical diagnostics, no mutation of unrelated requests. **Depends:** T005. **Trace:** FR-003, FR-005, FR-006, FR-009, AC-02, AC-04, AC-06, AC-07, AC-08.
- [ ] **T011** [P] [US3] Extend `tests/Feature/Foundation/FoundationProbeIsolationTest.php` and isolated `tests/Fixtures/isolated-module-bootstrap.php` only as necessary: actual testing vs independently booted local/production route list, zero production routes, exact `/api/v1` boundary (not `/api/v10`), no new public debug route. Empty route collection legitimately produces no route-cache artifact. Scratch fixture must reuse current production bootstrap/config and add only isolated harmless control route; generate actual cache and load it in fresh production boot; prove control present and all test-only routes absent. Preserve original F0-01 tests. **Depends:** T005. **Trace:** FR-001, FR-005, FR-008, FR-009, AC-04, AC-09, AC-10.
- [ ] **T012** [P] [US3] Write `tests/Unit/Core/ApiOpenApiContractTest.php` and representative HTTP contract assertions in `tests/Feature/Foundation/ApiOpenApiContractTest.php`: OpenAPI 3.1 JSON parse, empty paths object, complete local refs, shared success/error/validation/header components, exact status/code and array/object distinctions, optional honest meta, 204 no body, Money/ID decimal strings per FBR-02 without fake resource schemas. Explicitly limit to structural and behavioral checks, not full OpenAPI conformance. **Depends:** T005. **Trace:** FR-002, FR-003, FR-004, FR-005, FR-007, FR-009, AC-01, AC-02, AC-03, AC-04, AC-05, AC-08.
- [ ] **T013** [P] [US3] Write `tests/Architecture/ApiFoundationBoundariesTest.php`: explicit registry remains, no domain writers, auth packages, migration, DB/Redis startup, public test routes, string-backed project enum or unauthorized dependency changes. Ensure new tests are discovered by existing PHPUnit Unit/Feature/Architecture suites and standalone architecture runner. **Depends:** T005. **Trace:** FR-001, FR-008, FR-009, AC-09, AC-10.
- [ ] **T014** Commit tests before production code and run targeted red suites on isolated GitHub Actions with actual locked PHP/Laravel/Pest. Record expected failing new assertions/missing planned functionality, exact run SHA, no syntax/fixture/bootstrap errors mistaken for RED. Existing regression-only assertions should remain green. Do not weaken tests to obtain red/green. Stop and diagnose unintended failures. **Depends:** T006, T007, T008, T009, T010, T011, T012, T013. **Trace:** FR-009, FR-010, AC-01, AC-02, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10.

## Phase 2 — Minimal native implementation

- [ ] **T015** [US1] Implement strict typed `app/Enums/HttpStatusCode.php` (int-backed) and `app/Helpers/ApiResponse.php`: native response objects, 200/201, true empty 204, classified errors and optional real meta. Reject or prevent invalid combinations; do not wrap arbitrary downstream data, downloads or every native response. Run T006 tests. **Depends:** T014. **Trace:** FR-002, FR-003, FR-004, AC-01, AC-02, AC-03.
- [ ] **T016** [US1] Implement `app/Core/Http/ApiErrorCodes.php` and `app/Core/Http/ApiExceptionMapper.php` for approved plan §3.2 mapping, explicit Laravel exception classification, stable safe generic 500 and HTTP_ERROR fallback, actual field validation, safe protocol headers with correct JSON content type. Domain-specific mappings and real CSRF/limiter integration remain deferred. No raw exception message in response. **Depends:** T015. **Trace:** FR-003, FR-004, FR-008, AC-03, AC-04, AC-05, AC-06.
- [ ] **T017** [US2] Implement native ar/en `lang/ar/api.php`, `lang/en/api.php` and narrow locale resolver (planned technical path `app/Core/Http/ApiLocaleResolver.php`); enforce bounded negotiation and missing-key fallback in test matrix. Ensure native validation default messages used by fixture are translated or explicitly safe; no untrusted translation path or raw key output. No preference persistence. **Depends:** T014. **Trace:** FR-004, FR-006, AC-03, AC-07.
- [ ] **T018** [US2] Implement `app/Http/Middleware/AssignRequestId.php` and minimal request-scoped locale support as justified; exact v1 path check, server UUID, request attributes, cleanup of mutable application/translator/diagnostic context in success/failure. Use safe correlated reporting without passing confidential exception message/body/headers to logs; avoid double logging and preserve meaningful internal diagnostics. Do not globally clear unrelated application context. **Depends:** T016, T017. **Trace:** FR-003, FR-005, FR-006, AC-04, AC-06, AC-07, AC-08.
- [ ] **T019** [US1] [US2] Integrate actual `bootstrap/app.php` native middleware, exception classification/reporting/render/response hooks to cover unmatched path and early failures where feasible. Preserve non-v1/non-API negotiation, request ID and selected locale from current request even during unwinding; verify actual Laravel order. No catch-all route or global JSON success wrapping. **Depends:** T018. **Trace:** FR-001, FR-003, FR-004, FR-005, FR-006, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08.
- [ ] **T020** [US3] Register empty native `routes/api.php` at `/api/v1` in `bootstrap/app.php`; retain explicit provider ownership, no additional production health/debug/business route. Keep F0-01 FoundationDemo probe isolated and unchanged. Confirm actual routing prefix and zero production routes; test scratch cache with actual new bootstrap. **Depends:** T019. **Trace:** FR-001, FR-008, AC-09, AC-10.
- [ ] **T021** [US3] Author canonical `specs/002-api-foundation/contracts/openapi.json` (OpenAPI 3.1, empty paths, shared components, local references, 204/header and FBR-02 contract notes). No package or generated SDK. Keep status/code and locale-neutral schema consistent with approved plan and observed helper behavior. **Depends:** T015, T016, T018. **Trace:** FR-002, FR-003, FR-004, FR-005, FR-007, AC-01, AC-02, AC-03, AC-04, AC-05, AC-08.
- [ ] **T022** Minimally update `.github/workflows/f0-01-laravel-bootstrap.yml` trigger paths to include `lang/**` and `specs/002-api-foundation/contracts/**` on development-target PRs (and relevant feature push if used). Preserve read-only job permissions, isolated env, Composer audit/platform/full Pest/Architecture/PHP syntax; report suite labels accurately. No deployment job, dependency/lock edits or new CI framework. Document path filter coverage. **Depends:** T014 for final verification. **Sequencing note:** approved trigger-only portion moved ahead to T004 to obtain a real baseline PR run with no runtime changes; retain T022 open until the final checks cover the new path filters. **Trace:** FR-009, FR-010, AC-10.

## Phase 3 — Verification and evidence

- [ ] **T023** Run targeted real HTTP/unit/contract/architecture GREEN checks covering all written tests against feature revision. Fix findings within approved scope, record run IDs, failing/passing counts and revisions. Confirm no test was skipped or silently weakened. No code/contract change outside approval. **Depends:** T019, T020, T021, T022. **Trace:** FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009, FR-010, AC-01, AC-02, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10.
- [ ] **T024** Run full `php artisan test --fail-on-empty-test-suite --display-warnings --fail-on-warning --no-ansi` (no group filter), `vendor/bin/pest --testsuite Architecture`, real production/local route list, empty-route-cache behavior and genuine isolated scratch cache, locked Composer validate/install/platform/audit, tracked PHP syntax and clean runtime git diff. Use reviewed integrated PR merge candidate; exact environment/SHA/logs must be captured. Distinguish unavailable Pint/PHPStan/Larastan from pass, resolve applicable required check gaps before merge. **Depends:** T023. **Trace:** FR-001, FR-008, FR-009, FR-010, AC-09, AC-10.
- [ ] **T025** Review changed files and actual safety evidence: no secret/SQL/trace leakage, no new production routes, no auth bypass/CSRF shortcut, no unreviewed DB/Composer changes, no state retention, no fake schema or claim of implemented tenancy/worker isolation. Compare approved baseline to HEAD; record bounded review and risk exceptions only with authority. Critical/High failures block merge. **Depends:** T024. **Trace:** FR-003, FR-005, FR-006, FR-007, FR-008, FR-009, FR-010, AC-04, AC-06, AC-08, AC-09, AC-10.
- [ ] **T026** Reconcile spec/plan/tasks/checklist, contract and Foundation module inventory; create `specs/002-api-foundation/quickstart.md` with actual commands, versions, full revision, run URLs/counts, known limitations, review and rollback notes (revert feature changes via reviewed PR, never destructive DB rollback). Record dated milestone and permanent handoff. Do not mark unexecuted tasks complete. **Depends:** T025. **Trace:** FR-007, FR-009, FR-010, AC-10.

## Phase 4 — Independent review and separately authorized integration

- [ ] **T027** Obtain substantive independent PR review (CodeRabbit/qualified reviewer) when eligible; move Draft to Ready only under appropriate authorization, inspect all findings, resolve actionable review comments/threads with actual fixes and rerun applicable full integrated CI after final source changes. Require evidence on final candidate SHA; no reliance on Draft skipped-review status or stale green checks. **Depends:** T026. **Trace:** FR-008, FR-009, FR-010, AC-10.
- [ ] **T028** Obtain separate explicit owner authorization for development-only merge, confirm current base/head, conflict-free verified candidate, applicable Critical/High checks and review completed, no unresolved gate. Do not merge if authorization or evidence missing. **Depends:** T027. **Trace:** FR-008, FR-009, FR-010, AC-10.
- [ ] **T029** Only with T028 satisfied, merge reviewed PR #4 into `development` via approved repository workflow, verify GitHub merged=true and merge SHA, preserve F0-01 evidence. No main promotion, production release, migration or deployment. **Depends:** T028. **Trace:** FR-008, FR-010, AC-10.
- [ ] **T030** Verify merged feature and final evidence on development, reconcile task ledger and permanent/dated handoff, document remaining F0-03/F0-04/F1 dependencies and D-201 OPEN/BA-16 DEFERRED/BA-30 G3 BLOCKED. Do not start next feature or mark production-ready without separate approval. **Depends:** T029. **Trace:** FR-001, FR-008, FR-009, FR-010, AC-10.

## Story checkpoints and execution order

| Story | Independent acceptance checkpoint |
| --- | --- |
| US1 (P1) shared response/exception contract | T006–T008 and T015–T016/T019; T023 proves 200/201/204, true failures, validation and safe exception handling. |
| US2 (P1) trusted request ID/localization | T009–T010, T017–T019; T023 proves ar/en, stable codes, request IDs, logging safety and repeated-request cleanup. |
| US3 (P2) versioned module-friendly seam | T011–T013, T020–T021; T023/T024 prove exact prefix, contract and real production/cache isolation. |

All risk-selected test sources (T005–T013) precede code and T014 records actual RED. Core shared components precede integrated middleware/handler; do not parallelize writes to `bootstrap/app.php` or shared fixtures. T022 may run independently after T014, but must finish before T023. T024–T030 are sequential gates, not automatic actions. Complete all three stories; US1 alone is not release-ready.

## Private locale parsing parameters (task-level design permitted by plan §3.5)

- Bound aggregate Accept-Language header input to 4096 bytes and at most 32 comma-separated ranges before expensive parsing; invalid syntax/weight or exceeded limit yields ar. Parse supported ar/en and compatible regional forms case-insensitively. Descending quality, source order on ties; preserve explicit exclusions. Exact duplicate normalized ranges with contradictory qualities are treated as malformed input and use ar fallback; repeat identical ranges harmlessly. Use native parsing where correct, with bounded validation as needed; do not build a general localization service.
- Explicit supported language ranges take precedence over wildcard for that language; wildcard may select remaining unexcluded supported language(s), preferring ar on equal wildcard choice. q=0 never a positive negotiation preference. If no positive supported language can be negotiated, apply the separately approved system fallback ar even if no range is acceptable (not a claim the client positively selected ar). No 406 solely for locale negotiation. These are disclosed implementation parameters, not new business policy.

| Header case | Expected language |
| --- | --- |
| ar, en, ar-EG, EN-us | ar, en, ar, en respectively |
| en;q=0.9,ar;q=0.8 | en |
| en;q=0,ar;q=0.5 | ar |
| en;q=0.7,ar;q=0.7 | en (first) |
| ar;q=0.7,en;q=0.7 | ar (first) |
| ar;q=0,*;q=1 | en (wildcard eligible language) |
| en;q=0,*;q=1 | ar |
| ar;q=0,en;q=0 | ar (system fallback, not q=0 selection) |
| absent, empty, fr, invalid q, over byte/range bound | ar |

These choices refine technical handling only within approved plan. If real Laravel/Symfony behavior conflicts with approved LOC-ST-02 or requires material external contract change, stop and obtain clarification rather than silently changing semantics. A missing English translation uses Arabic, then a reviewed safe general message if both absent; diagnostics in Development/CI must not expose confidential input.

## Requirement traceability

| Requirement / acceptance ID | Covering tasks |
| --- | --- |
| FR-001 | T002, T004, T005, T011, T013, T019, T020, T023, T024, T030 |
| FR-002 | T006, T012, T015, T021, T023 |
| FR-003 | T005, T006, T007, T008, T010, T012, T015, T016, T018, T019, T021, T023, T025 |
| FR-004 | T005, T006, T008, T012, T015, T016, T017, T019, T021, T023 |
| FR-005 | T007, T010, T011, T012, T018, T019, T021, T023, T025 |
| FR-006 | T008, T009, T010, T017, T018, T019, T023, T025 |
| FR-007 | T002, T012, T021, T023, T025, T026 |
| FR-008 | T002, T003, T004, T005, T007, T011, T013, T016, T020, T023, T024, T025, T027, T028, T029, T030 |
| FR-009 | T002, T004, T005, T006, T007, T008, T009, T010, T011, T012, T013, T014, T022, T023, T024, T025, T026, T027, T028, T030 |
| FR-010 | T001, T002, T003, T004, T014, T022, T023, T024, T025, T026, T027, T028, T029, T030 |
| AC-01 | T006, T012, T014, T015, T021, T023 |
| AC-02 | T006, T010, T012, T014, T015, T021, T023 |
| AC-03 | T005, T006, T008, T012, T014, T015, T016, T017, T019, T021, T023 |
| AC-04 | T005, T007, T010, T011, T012, T014, T016, T018, T019, T021, T023, T025 |
| AC-05 | T005, T006, T007, T012, T014, T016, T019, T021, T023 |
| AC-06 | T007, T010, T014, T016, T018, T019, T023, T025 |
| AC-07 | T008, T009, T010, T014, T017, T018, T019, T023 |
| AC-08 | T010, T012, T014, T018, T019, T021, T023, T025 |
| AC-09 | T005, T011, T013, T014, T020, T023, T024, T025 |
| AC-10 | T002, T004, T011, T013, T014, T020, T022, T023, T024, T025, T026, T027, T028, T029, T030 |

**Evidence policy:** actual GitHub runs and final candidate SHA only; no fabricated pass, no test package install without separate approval; independent review and explicit merge permission required. Not actual Octane, multi-Tenant PostgreSQL, Sanctum, Redis/Horizon, load or production test evidence.
