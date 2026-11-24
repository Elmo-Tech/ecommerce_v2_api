# Implementation Plan — F0-02 API Foundation (OWNER-APPROVED; IMPLEMENTATION AUTHORIZED)

**Date:** 2026-11-24 | **Branch:** `feature/f0-02-api-foundation`  
**Approval:** **DEC-01…04 AND COMPLETE REVISED SPEC + PLAN APPROVED 2026-11-24 (reviewed baseline `c9897aac60975b2e5aa90583480972c02152bb0d`). SCOPED IMPLEMENTATION AUTHORIZED 2026-11-24 (T003); SEPARATE MERGE AUTHORIZATION PENDING.**  
**Canonical requirements:** [F0-02 spec](spec.md) | **Approved source:** [API Standards AS-01–09](../../docs/02-standards/api-standards.md), [Constitution](../../.specify/memory/constitution.md), [Module Architecture](../../docs/01-architecture/module-architecture.md), [Code Standards](../../docs/02-standards/code-standards.md), [Testing Standards](../../docs/02-standards/testing-standards.md), [Localization Standards LOC-ST-02](../../docs/02-standards/localization-standards.md#4-loc-st-02--supported-locales-resolution-preferences-and-fallback), [FBR-02](../../docs/reviews/FBR-02-architecture-gap-review.md).

## 1. Verified starting point and boundaries

- `development` contains **F0-01 T001–T026 COMPLETE**: Laravel 13.43.0, PHP `^8.4`, authentic `composer.lock`, native explicit `config/modules.php` registry and FoundationDemo testing-only probe. [Merged handoff](../../docs/handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md).
- Existing `bootstrap/app.php` only loads `routes/console.php` and registers API JSON rendering for `api/*` or `expectsJson()`. It exposes **no public production API routes**; `FoundationDemoServiceProvider` registers an internal probe only under `testing`.
- No Sanctum, Tenant DB, Redis/Horizon, migrations, users or other business modules exist in the current runtime. This Feature **cannot** claim actual authorization, Tenant resolution, payment/stock safety or public API readiness.
- The current minimal locked dependencies are **`laravel/framework ^13.0` and Pest 5** on PHP ^8.4. No package-install approval for F0-02; verify any proposed package with real Composer dry-run and separate owner approval before altering the lock.
- Proposed integration is **native Laravel HTTP boundary**, not a new API Gateway, SDK generator, custom authentication framework or auto-registered business modules.

## 2. Approved architecture and ownership

| Technical boundary | Owner/path | Contract and review guard |
| --- | --- | --- |
| JSON response formatting | `app/Helpers/ApiResponse.php` | Approved AS-02 `success/message/data`, `success/message/errorCode/errors`, optional honest `meta`; Laravel JsonResponse; correct 204 without a body |
| HTTP status values | `app/Enums/HttpStatusCode.php` | Small **int-backed enum** (CS-05), not business state Enum; strict HTTP status semantics |
| Shared error-code mapping | `app/Core/Http/ApiErrorCodes.php` (DEC-02 approved string constants, **not** string-backed PHP Enum) | Stable external strings including `VALIDATION_FAILED`, `UNAUTHENTICATED`, `ACCESS_DENIED`, `RESOURCE_NOT_FOUND`, `RESOURCE_CONFLICT`, `RATE_LIMIT_EXCEEDED`, `INTERNAL_ERROR` where applicable; no domain error-code explosion |
| Central exception classification | `app/Core/Http/ApiExceptionMapper.php` + actual `bootstrap/app.php` Laravel `withExceptions` | API v1-only envelope; 400/401/403/404/405/409/422/429/500/503, preserve other genuine HTTP error statuses and required headers per approved mapping; unexpected non-HTTP exceptions safe 500; no global conversion of unrelated responses |
| Trusted request correlation | `app/Http/Middleware/AssignRequestId.php` + native middleware registration | Early request-path-scoped middleware, not route-group-only; generate server-owned ID once per attempt and attach response header on handled success/failure, including unmatched API path. Store in request-local attributes; safe diagnostics, no client spoofing or mutable global context |
| Generic translation | `lang/ar/api.php`, `lang/en/api.php`; negotiated locale helper/middleware as justified | DEC-01/LOC-ST-02 approved `ar` fallback; bounded weighted ar/en negotiation and missing-key fallback; preserve request-local locale and restore previous/default state on all paths |
| API route seam | `bootstrap/app.php` and empty `routes/api.php` (DEC-04 approved) | Version prefix `/api/v1`; **zero public business endpoints** until separately reviewed modules, no test probe in prod or route cache; no implicit module discovery |
| Contract file | **DEC-03 approved** `specs/002-api-foundation/contracts/openapi.json` with OpenAPI 3.1 components and empty `paths` | Native PHP syntax/structural/local reference checks plus actual representative HTTP contract assertions; no generator, parser or full OpenAPI conformance claim |
| Tests | `tests/Unit/Core/`, `tests/Feature/Foundation/`, `tests/Architecture/` and **testing-only** route fixture | Test failure statuses/validation/correlation, locale/negative paths, no production route/cache or DB, preserve F0-01 existing contract. No live public debug endpoint |

**No new module-specific public API or database author.** This is cross-cutting technical Core/Foundation. Keep current explicit Module Registry. Do **not** register fake Platform, Merchant, Storefront or Customer public endpoints to claim the four surfaces are implemented.

## 3. Contract design choices derived from approved standards

### 3.1 Success

```json
{"success":true,"message":"...","data":{"example":"only test fixture content"}}
```

A legitimate 201 uses the same success structure with HTTP 201; a 204 returns **no body**. The optional `meta` key is only included when actually computed from pagination, never invented from a proposed sample. No business Resource schema is finalized here.

### 3.2 Failure

```json
{"success":false,"message":"...","errorCode":"VALIDATION_FAILED","errors":{"customerName":["..."]}}
```

For non-field-specific failures `errors: []`, not an invented object. Field names must follow reviewed camelCase request contracts, including nested `items.0.quantity`; do not silently recase client fields or assert backend validation proves business authority.

**Approved generic HTTP mapping (2026-11-24)** (AS-02/03 and FBR-02):

| Status | Error code | Classification |
| --- | --- | --- |
| 400 | `BAD_REQUEST` | **Approved new generic code** for invalid general HTTP request, not field validation. |
| 401 | `UNAUTHENTICATED` | AS-03; native authentication failure, no login redirect for API. Real guard integration deferred. |
| 403 | `ACCESS_DENIED` | AS-03; ordinary authorization failure. Real CSRF mismatch `CSRF_TOKEN_MISMATCH` is reserved for Auth integration, not conflated with generic 403. |
| 404 | `RESOURCE_NOT_FOUND` | AS-03; unmatched route or legitimately hidden/missing resource; no existence disclosure. |
| 405 | `METHOD_NOT_ALLOWED` | **Approved new generic code**; preserve `Allow` header. |
| 409 | `RESOURCE_CONFLICT` | AS-03; genuine classified conflict only. |
| 422 | `VALIDATION_FAILED` | AS-03; field-keyed object for actual validation; safe generic non-field errors have `errors: []`. |
| 429 | `RATE_LIMIT_EXCEEDED` | AS-03; genuine throttle; retain meaningful `Retry-After` if provided. |
| 500 | `INTERNAL_ERROR` | AS-03; unexpected non-HTTP errors, no internal exception details exposed. |
| 503 | `SERVICE_UNAVAILABLE` | **Approved new generic code**; genuine temporary unavailability. FBR-02-10 limiter behavior is deferred, not simulated as implemented. |
| Other genuine HTTP 4xx/5xx | `HTTP_ERROR` | **Approved fallback for unclassified HTTP exceptions**: preserve actual HTTP error status and applicable protocol headers, safe generic localized text, no fabricated domain code. |

Preserve applicable protocol headers on mapped HTTP exceptions (`Allow`, `Retry-After`, `WWW-Authenticate` when present) rather than replacing them with a bare JSON response. Unexpected non-HTTP exceptions yield safe 500, not HTTP 200. Keep redirects, files/downloads and other non-envelope responses native where applicable. AS-02 defines 202 for genuinely pending async work; no fake 202/business async route is introduced here. Domain codes remain Feature-owned. The new generic codes and fallback in this table were included in the owner's approval of the full revised spec/plan on 2026-11-24; not yet implemented or tested.

### 3.3 FBR-02 wire truth

- **FBR-02-06:** finalized application JSON Money uses **decimal-string integer minor units** plus currency; older AS-02 numeric Money example is illustrative and **superseded**.
- **FBR-02-07:** PostgreSQL BIGINT-backed resource IDs use **canonical decimal strings**, never unsafe JavaScript Numbers; feature-specific resource mappers will own those fields later.
- **FBR-02-05:** browser CSRF mismatch contract 403/`CSRF_TOKEN_MISMATCH` will be integrated/tested when real Sanctum/CSRF exists; F0-02 must **not** install authentication or disable CSRF to produce an artificial pass.

### 3.4 Versioning and headers

- Initial prefix only **`/api/v1`**. Contract-first with additive compatibility; no `/api/v2` or generated SDK just for this Feature.
- Per-attempt server-owned `X-Request-ID`; **distinct** from `Idempotency-Key` (future retry identity), Tenant selector and business operation ID.
- API errors are safe and localized, not raw stack traces or SQL. No logging of full sensitive payload.

### 3.5 Approved request lifecycle and compatibility design

- Use an early, narrowly path-scoped native middleware for the exact `/api/v1` path and descendants, not a matcher for lookalikes such as `/api/v10`. Retain existing Laravel JSON negotiation behavior for requests outside this new v1 envelope boundary. Do not add a catch-all route or alter non-API HTML responses.
- Initialize request ID and locale before route matching, retaining the ID/selected locale in request attributes so the centralized error-render/response hooks can preserve them for unmatched 404, 405 and other handled errors. Restore mutable translator/application locale and diagnostic context after every attempt, including exceptions; mapper must use the selected request context rather than a stale global locale. Test the actual Laravel lifecycle and handler order; this plan does not assume middleware alone catches all exceptions.
- Proposed ID implementation: a server-generated Laravel-native UUID per request, ignore any caller-supplied `X-Request-ID` for trusted correlation, no new dependency, no JSON field. Attach only safe request ID/status/code diagnostics and avoid duplicate logging or raw sensitive input; no full tracing/worker propagation in F0-02. Where the application cannot technically produce a response, no universal header guarantee is claimed.
- `Accept-Language`: supported ar/en only, compatible regional parents, respect quality weights and exclude q=0, safely bounded parsing. Absent/unsupported/malformed/oversized header resolves to `ar`; missing selected translation falls back to ar then safe human-readable message, with a safe Development/CI diagnostic. Critical missing translations block release per LOC-ST-02. Exact tie behavior, wildcard handling and length/range bounds are recorded in [tasks.md](tasks.md) as private technical choices, covered by tests; no persistent preference or translation engine. Review cache variation when a future cacheable localized representation exists; no new shared cache here.
- Shared OpenAPI component tests distinguish `errors: []` from nonempty validation field-object, optional truthful `meta`, 204 zero-body, stable code/status/header and decimal-string Money/BIGINT convention without inventing resource fields. JSON parsing alone is not complete OpenAPI validation. Keep one canonical shared artifact and reference it from later Feature contracts.

## 4. Implementation sequencing — only AFTER approvals

**Phase A — Contract and review gate (BLOCKING):**
1. Confirm/approve the complete revised spec and plan; DEC-01…04 already approved 2026-11-24. Review remaining proposed HTTP mapping, locale/lifecycle details, Constitution, AS, LOC, CS, MA fit and cross-Feature dependencies.
2. Record actual code revision, locked Laravel and Pest versions. Confirm any new dependency request separately; prefer no new package.
3. Define a minimal test-only HTTP fixture and shared OpenAPI envelope/error schema; do not introduce real business routes.

**Phase B — Tests FIRST:**
4. Write negative/positive unit tests for success/error JSON, 204 empty response and status mapping.
5. Write HTTP tests for safe exception handler (including unmatched API 404, 405 + Allow, other genuine HTTP errors, non-v1 and non-API behavior unchanged), validation 422 nested keys, ar/en negotiation/missing-key fallback, `X-Request-ID` on success/error and inbound spoofed header ignored. Verify request-local locale, ID and diagnostic cleanup across repeated success/failure in the same application.
6. Verify testing-only FoundationDemo probe remains absent outside testing/production cache, no DB/migrations and zero unapproved public endpoints.

**Phase C — Minimal native implementation:**
7. Implement tiny helper + int-backed status Enum; integrate native Laravel response/exception mapping; avoid broad middleware side effects.
8. Implement trusted per-attempt correlation and reviewed locale negotiation. Add only required translations with stable wire keys.
9. Integrate the approved empty native versioned routing seam and document actual shared OpenAPI components; do not install API/auth/tenancy packages by implication. The existing real application has zero production routes and Laravel declines to generate an empty route cache; retain a genuine scratch route-cache fixture with a harmless control route and exercise actual relevant bootstrap/config, without adding a route to the repository's production application.

**Phase D — Real verification and PR:**
10. Run existing F0-01 suites + new F0-02 unit/feature/architecture/contract tests under actual PHP 8.4/Laravel 13, platform checks, `composer audit --locked`, `php -l`, route listing and true production route-cache isolation; record SHA/run/job/logs. Include `php artisan test` without `--group`. Review and minimally extend the existing validation workflow's PR path filters for `lang/**` and shared OpenAPI contract artifacts, or explicitly select equivalent checks, so later translation/contract-only changes are not silently skipped. This minimal CI scope is approved; no deployment job or F0-03/F0-04 package install.
11. Review diff for production route exposure, auth bypass/CSRF overreach, data/secret handling and unintended dependencies/migrations; document any skipped Pint/PHPStan honestly.
12. Only with all approvals/tests, ask for independent PR review and planned integration into `development`. No automatic `main` promotion or deployment.

**T001–T025 complete with actual baseline, RED, GREEN, review regression RED and final GREEN; T026 feature docs complete, permanent pointer staged in [docs-only PR #5](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/5); IR-01…06 independent review remediation verified in 98/911 GREEN. The approved T022 trigger-only path extension was performed early to trigger baseline CI; all application/test source changes followed successful T004. See [quickstart](quickstart.md). Independent review and final merge gates remain open.**

## 5. Quality and security gates

- **Positive HTTP** 200/201 proper schema and no body on 204.
- **Negative HTTP** 400/401/403/404/405/409/422/429/500/503, unclassified genuine HTTP errors and unknown v1 paths as applicable; real status not 200 with `success=false`; protocol headers preserved.
- **Safe failures** no SQL, stack, raw PHP source paths or session/secret leakage; general 500 message only.
- **Validation** deterministic nested camelCase error keys and safe error array/object distinction.
- **Correlation** per attempt, trusted server ID on safe errors and successes, not client spoof/authorization; cleanup across reused Laravel test requests.
- **Localization** ar/en generic messages and stable errorCode, approved `ar` fallback, regional/weighted and unsupported/malformed preferences; missing-key behavior and no request-to-request state leak.
- **Boundary** Core only, no business route/API, no Tenant or central DB activation, no production probe, no provider scans, F0-01 suites remain green.
- **Compatibility** OpenAPI components syntactically and logically valid, correct FBR numeric wire strings and no incompatible public semantics.
- **Real evidence** exact runner/versions/commit/run and all test counts; critical failures stop PR integration. If static/format tools unavailable, record as unavailable.

## 6. Approved decisions and execution risks

| Item | Status / approach |
| --- | --- |
| DEC-01 Localization fallback | **APPROVED ar** — owner choice A; existing LOC-ST-02 is the governing source. |
| DEC-02 Error-code representation | **APPROVED compact shared string constants + int-backed HTTP enum + central mapper**; additional wire codes in §3.2 approved as part of complete spec/plan. |
| DEC-03 OpenAPI | **APPROVED** canonical OpenAPI 3.1 JSON components artifact, no new package, bounded structural/HTTP checks. |
| DEC-04 Native route seam | **APPROVED** empty `routes/api.php` registered under `/api/v1`, no production endpoints. |
| Risk: Laravel exception lifecycle | Early API-path detection, centralized response hook and request-local state must cover unmatched routes and restore state; prove with HTTP tests. |
| Risk: protocol compatibility | Preserve meaningful headers and genuine error statuses; test 405/429 and native non-v1/non-API behavior. |
| Risk: accidental standard drift | FBR-02 overrides illustrative numeric ID/Money examples; LOC-ST-02 resolves old AS-03 fallback wording. |
| Risk: CI/static-analysis gaps | Current F0-01 workflow lacks explicit lang/contract path triggers; approved minimal coverage fix. Pint/PHPStan absent; no pass claimed or unapproved tooling added. Resolve applicable required checks/any permitted noncritical exception before merge. |
| Risk: production exposure | Real production has zero routes, use genuine isolated cache fixture and retain test probe isolation. |

## 7. Explicit owner approval gate

Owner response “تمام يلا” on 2026-11-24 to the explicit proposal to approve the complete current spec/plan and proceed with tasks and consistency analysis before code; see [approval handoff](../../docs/handoff/F0-02_SPEC_PLAN_APPROVED_TASKS_ANALYZED_2026-11-24.md).

- [x] Owner confirmed DEC-01…04 on 2026-11-24.
- [x] Owner approves the complete revised [spec.md](spec.md) including non-goals and new generic HTTP wire mappings.
- [x] Owner approves this complete revised plan including lifecycle design, CI coverage and risk-selected test strategy in the same response.
- [x] [Task ledger](tasks.md) generated and [consistency analysis](consistency-analysis.md) completed.
- [x] T003 separate owner implementation authorization, 2026-11-24.
- [x] T004 real preflight, tests-first RED, implementation, GREEN and scope review T005–T025 on `feature/f0-02-api-foundation`.
- [ ] T026 feature docs/dated handoff complete; canonical pointer proposed in [docs-only PR #5](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/5), await separately authorized integration.
- [ ] T027 independent review and final integrated candidate recheck.
- [ ] Keep D-201/BA-16/BA-30 unchanged; no release or production access. No merge or deployment without separate authorization.

**Approved and implemented plan; technical evidence at [quickstart](quickstart.md), independent review and separate development-only merge authority still pending.**


## Independent review remediation (owner-authorized, within F0-02)

The six technical findings in [independent review](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4#issuecomment-7144653211) were reproduced and fixed tests-first. See [actual evidence](quickstart.md) and [dated remediation handoff](../../docs/handoff/F0-02_INDEPENDENT_REVIEW_FIXES_2026-11-24.md). Native Validator factory adapter exposes field provenance only; rule engine and module/authorization ownership remain native and unchanged. Factory callback checks the current active request and returns the normal Validator elsewhere. Explicit native control-flow responses are retained, unexpected custom non-errors become safe failures. No new wire codes, dependencies or domain behavior. IR-07 pointer follows separately reviewed docs-only PR #5 to avoid conflicts. Final review/merge remain gated.
