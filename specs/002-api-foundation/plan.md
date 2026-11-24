# Implementation Plan — F0-02 API Foundation (REVIEW DRAFT)

**Date:** 2026-11-24 | **Branch:** `feature/f0-02-api-foundation`  
**Approval:** **PENDING OWNER SPEC + PLAN SIGN-OFF. NO IMPLEMENTATION AUTHORIZATION.**  
**Canonical requirements:** [F0-02 spec](spec.md) | **Approved source:** [API Standards AS-01–09](../../docs/02-standards/api-standards.md), [Constitution](../../.specify/memory/constitution.md), [Module Architecture](../../docs/01-architecture/module-architecture.md), [Code Standards](../../docs/02-standards/code-standards.md), [Testing Standards](../../docs/02-standards/testing-standards.md).

## 1. Verified starting point and boundaries

- `development` contains **F0-01 T001–T026 COMPLETE**: Laravel 13.43.0, PHP `^8.4`, authentic `composer.lock`, native explicit `config/modules.php` registry and FoundationDemo testing-only probe. [Merged handoff](../../docs/handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md).
- Existing `bootstrap/app.php` only loads `routes/console.php` and registers API JSON rendering for `api/*` or `expectsJson()`. It exposes **no public production API routes**; `FoundationDemoServiceProvider` registers an internal probe only under `testing`.
- No Sanctum, Tenant DB, Redis/Horizon, migrations, users or other business modules exist in the current runtime. This Feature **cannot** claim actual authorization, Tenant resolution, payment/stock safety or public API readiness.
- The current minimal locked dependencies are **`laravel/framework ^13.0` and Pest 5** on PHP ^8.4. No package-install approval for F0-02; verify any proposed package with real Composer dry-run and separate owner approval before altering the lock.
- Proposed integration is **native Laravel HTTP boundary**, not a new API Gateway, SDK generator, custom authentication framework or auto-registered business modules.

## 2. Architecture and ownership proposal

| Technical boundary | Proposed owner/path | Contract and review guard |
| --- | --- | --- |
| JSON response formatting | `app/Helpers/ApiResponse.php` | Approved AS-02 `success/message/data`, `success/message/errorCode/errors`, optional honest `meta`; Laravel JsonResponse; correct 204 without a body |
| HTTP status values | `app/Enums/HttpStatusCode.php` | Small **int-backed enum** (CS-05), not business state Enum; strict HTTP status semantics |
| Shared error-code mapping | `app/Core/Http/ApiErrorCodes.php` (proposed string constants, **not** string-backed PHP Enum) | Stable external strings including `VALIDATION_FAILED`, `UNAUTHENTICATED`, `ACCESS_DENIED`, `RESOURCE_NOT_FOUND`, `RESOURCE_CONFLICT`, `RATE_LIMIT_EXCEEDED`, `INTERNAL_ERROR` where applicable; no domain error-code explosion |
| Central exception classification | `app/Core/Http/ApiExceptionMapper.php` + actual `bootstrap/app.php` Laravel `withExceptions` | API-only output; 400/401/403/404/409/422/429/500/503; unknown exceptions safe 500; no global conversion of HTML/site responses unrelated to API. Only use real HTTP exceptions where appropriate; no invented domain success |
| Trusted request correlation | `app/Http/Middleware/AssignRequestId.php` + native middleware registration | Generate server-owned request ID for each API HTTP attempt, return **`X-Request-ID` header**, never as default JSON field; do not trust caller header or retain as global Tenant state |
| Generic translation | `lang/ar/api.php`, `lang/en/api.php`; negotiated locale helper/middleware as justified | Same `errorCode`/field names across ar/en; **fallback language remains open DEC-01** until owner approves |
| API route seam | `bootstrap/app.php` and **only if approved** `routes/api.php` (may be empty) | Version prefix `/api/v1`; **zero public business endpoints** until separately reviewed modules, no test probe in prod or route cache; no implicit module discovery |
| Contract file | **Proposed** `specs/002-api-foundation/contracts/openapi.json` with OpenAPI 3.1 components and empty `paths` | JSON can be schema/structure checked by native PHP without installing a YAML parser. Review canonical path/format under **DEC-03** before committing executable contract |
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

**Representative generic status/error pairings to validate:** 401/UNAUTHENTICATED; 403/ACCESS_DENIED; 404/RESOURCE_NOT_FOUND; 409/RESOURCE_CONFLICT; 422/VALIDATION_FAILED; 429/RATE_LIMIT_EXCEEDED; 500/INTERNAL_ERROR. The 400 and 503 shared codes, 405 handling and domain-specific error mappings require careful review rather than guessing from an illustrative example. An unknown exception yields safe 500, not HTTP 200. `Retry-After` on a true 429 only when known/meaningful.

### 3.3 FBR-02 wire truth

- **FBR-02-06:** finalized application JSON Money uses **decimal-string integer minor units** plus currency; older AS-02 numeric Money example is illustrative and **superseded**.
- **FBR-02-07:** PostgreSQL BIGINT-backed resource IDs use **canonical decimal strings**, never unsafe JavaScript Numbers; feature-specific resource mappers will own those fields later.
- **FBR-02-05:** browser CSRF mismatch contract 403/`CSRF_TOKEN_MISMATCH` will be integrated/tested when real Sanctum/CSRF exists; F0-02 must **not** install authentication or disable CSRF to produce an artificial pass.

### 3.4 Versioning and headers

- Initial prefix only **`/api/v1`**. Contract-first with additive compatibility; no `/api/v2` or generated SDK just for this Feature.
- Per-attempt server-owned `X-Request-ID`; **distinct** from `Idempotency-Key` (future retry identity), Tenant selector and business operation ID.
- API errors are safe and localized, not raw stack traces or SQL. No logging of full sensitive payload.

## 4. Implementation sequencing — only AFTER approvals

**Phase A — Contract and review gate (BLOCKING):**
1. Confirm/approve spec and plan; explicitly settle F0-02-DEC-01…04 with owner; review Constitution, AS, CS, MA fit and cross-Feature dependencies.
2. Record actual code revision, locked Laravel and Pest versions. Confirm any new dependency request separately; prefer no new package.
3. Define a minimal test-only HTTP fixture and shared OpenAPI envelope/error schema; do not introduce real business routes.

**Phase B — Tests FIRST:**
4. Write negative/positive unit tests for success/error JSON, 204 empty response and status mapping.
5. Write HTTP tests for safe exception handler (including 404 and non-API request unchanged), validation 422 nested keys, ar/en text fallback, `X-Request-ID` on success/error and inbound spoofed header ignored.
6. Verify testing-only FoundationDemo probe remains absent outside testing/production cache, no DB/migrations and zero unapproved public endpoints.

**Phase C — Minimal native implementation:**
7. Implement tiny helper + int-backed status Enum; integrate native Laravel response/exception mapping; avoid broad middleware side effects.
8. Implement trusted per-attempt correlation and reviewed locale negotiation. Add only required translations with stable wire keys.
9. Integrate versioned routing seam and document actual shared OpenAPI components; do not install API/auth/tenancy packages by implication.

**Phase D — Real verification and PR:**
10. Run existing F0-01 suites + new F0-02 unit/feature/architecture tests under actual PHP 8.4/Laravel 13, platform checks, `composer audit --locked`, `php -l`, route listing and true production route-cache isolation; record SHA/run/job/logs. Include `php artisan test` without `--group`.
11. Review diff for production route exposure, auth bypass/CSRF overreach, data/secret handling and unintended dependencies/migrations; document any skipped Pint/PHPStan honestly.
12. Only with all approvals/tests, ask for independent PR review and planned integration into `development`. No automatic `main` promotion or deployment.

**Do not mark any of these implementation phases COMPLETE on the strength of this draft.**

## 5. Quality and security gates

- **Positive HTTP** 200/201 proper schema and no body on 204.
- **Negative HTTP** 400/401/403/404/409/422/429/500/503 and 404 unknown routes as applicable, real status not 200 with `success=false`.
- **Safe failures** no SQL, stack, raw PHP source paths or session/secret leakage; general 500 message only.
- **Validation** deterministic nested camelCase error keys and safe error array/object distinction.
- **Correlation** per attempt, trusted server ID on safe errors and successes, not client spoof/authorization; cleanup across reused Laravel test requests.
- **Localization** ar/en generic messages and stable errorCode, with the **owner-approved fallback** in both test and docs.
- **Boundary** Core only, no business route/API, no Tenant or central DB activation, no production probe, no provider scans, F0-01 suites remain green.
- **Compatibility** OpenAPI components syntactically and logically valid, correct FBR numeric wire strings and no incompatible public semantics.
- **Real evidence** exact runner/versions/commit/run and all test counts; critical failures stop PR integration. If static/format tools unavailable, record as unavailable.

## 6. Decisions and risks before code

| Item | Current proposal | Decision needed |
| --- | --- | --- |
| DEC-01 Localization fallback | **Candidate ar** for Egypt-first, but NOT approved by API Standards | Owner selects approved fallback and header negotiation behavior |
| DEC-02 Error-code representation | Simple typed/constants map of stable wire strings, not a new string-backed PHP Enum | Owner/architecture confirms and identifies how future domain errors extend without a global mega-enum |
| DEC-03 OpenAPI canonical file/tool | Native JSON OpenAPI components file, no extra generator/parser package | Confirm file location, format, review/contract-check expectation |
| DEC-04 Native route seam | Enable `/api/v1` group only as required, no production route; internal HTTP fixture only under testing | Confirm whether empty `routes/api.php` should be committed or deferred |
| Risk: Laravel exception lifecycle | Middleware/header may not run on some exceptions/404 without suitable early registration | Test at HTTP boundary, not mock-only; revise native registration if needed |
| Risk: accidental standard drift | Older AS-02 example has numeric ID/Money; FBR-02 overrides it | Contract tests assert canonical decimal strings for any shared schema, no false assertions of resource data |
| Risk: blast radius | Exception/render callbacks may change F0-01 technical probe or future non-API responses | Scope strictly to API requests; run full regression and prod route exposure tests |

## 7. Explicit owner approval gate

- [ ] Approve or revise **[spec.md](spec.md)**, including non-goals.
- [ ] Approve or revise this **plan**, including paths and the four decisions.
- [ ] Only then generate final task breakdown and commence implementation/tests on `feature/f0-02-api-foundation`.
- [ ] Keep documented open decisions D-201/BA-16/BA-30 unchanged; no release or production access.

**This plan is not a tested result and does not grant implementation permission.**
