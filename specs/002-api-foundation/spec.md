# Feature Specification — F0-02: API Foundation

**Status:** **DRAFT FOR OWNER REVIEW — NOT APPROVED, NOT IMPLEMENTED**  
**Prepared:** 2026-11-24 | **Working branch:** `feature/f0-02-api-foundation`  
**Feature owner:** Backend Engineering Foundation / Core (not a business-domain writer)  
**Dependency:** [F0-01 complete on `development`](../../docs/handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md) with [task ledger 26/26](../001-laravel-bootstrap-module-registry/tasks.md)  
**Authority:** [Constitution v1.0.0](../../.specify/memory/constitution.md), [API Standards AS-01/02/03/08/09](../../docs/02-standards/api-standards.md), FBR-02-05/06/07, [Backend Architecture BA-11](../../docs/01-architecture/backend-architecture.md), [Module Architecture §§8/13/16](../../docs/01-architecture/module-architecture.md), [Code Standards CS-05/06/07/08/09](../../docs/02-standards/code-standards.md), [Testing Standards TEST-05](../../docs/02-standards/testing-standards.md).

> This is a **review proposal** for the next bounded F0 engineering Feature. It does not silently turn the complete API Standards into implemented behavior. An explicit owner approval of this spec and the technical plan is required **before writing API runtime code, installing packages, running migrations or merging the Feature**.

## 1. Problem and intended outcome

F0-01 produced a verified Laravel 13 PHP ^8.4 application and a native **explicit Module Registry**, deliberately without public production APIs, Auth, Tenancy or database state. Later modules need one consistent, small API HTTP layer so they can expose scoped routes and safe success/error contracts without each redefining exception shapes, request correlation or versioning.

**F0-02 proposes the minimal shared HTTP/API contract plumbing**, not a business resource, not Tenant resolution and not a claim that all AS-01–AS-09 are now executable. A future approved module will supply its actual protected endpoints, DTOs, OpenAPI and authorization.

## 2. User and integration stories

### US1 — Reusable API response contract (P1)

A later Feature author can invoke one technical response helper for completed successes and classified failures, while a Next.js consumer receives a stable shape and genuine HTTP status.

**Acceptance:**

- Success uses `{"success":true,"message":"...","data":...}` and *optional only if meaningful* `meta` for real pagination; never invented totals or a forced envelope for downloads.
- Failure uses `{"success":false,"message":"...","errorCode":"...","errors":[]}` with **stable machine-readable** `errorCode`; validation uses a field-keyed object with camelCase names, including nested paths.
- HTTP status reflects the outcome (at minimum correctly handle representative 200/201/204 and 400/401/403/404/409/422/429/500/503); `204` has **no body**, not `{"success":true}`.
- An unclassified internal exception must yield a safe 500 without SQL, source paths, secrets or stack traces in the client response.
- `App\Helpers\ApiResponse` and int-backed `App\Enums\HttpStatusCode` are the approved API Standards direction; F0-02 must not introduce business status Enums or invent monetary/identifier wire types.

### US2 — Trusted request correlation and localized generic errors (P1)

For each independent `/api/v1` HTTP attempt, a safe generated `X-Request-ID` is available on success/error where technically possible, distinct from Idempotency-Key, Tenant selection and domain operation identity.

**Acceptance:**

- Generate a trusted request ID per HTTP attempt; set `X-Request-ID` response header. Do not put it into the default JSON envelope or trust an arbitrary caller-supplied header as auth/identity.
- Central handling of API exceptions selects stable HTTP status/errorCode while `message` is safe and localized (initial supported message languages **ar** and **en**). The same errorCode and field path remain stable across languages.
- No raw Laravel exception trace, SQL, credentials, other Tenant record existence or sensitive body logged/returned. No globally retained request/Tenant identity.
- **Localization fallback remains an explicit F0-02 review decision**; this draft does **not** select it silently.

### US3 — Versioned, module-friendly HTTP foundation (P2)

A later registered module can add its own routes under reviewed `/api/v1` conventions without automatic discovery or a new publicly accessible debug endpoint.

**Acceptance:**

- Laravel-native version routing/integration matches `/api/v1`; paths use lower-case kebab-case for multiword resources and only future module-owned routes create real resources.
- The four approved logical surfaces (Platform, Merchant Dashboard, Public Storefront, Authenticated Customer) are **documented contracts**, not four open bypass routes.
- F0-02 adds **no public business endpoint** and does not expose F0-01's technical probe outside `testing`. Production route collection and cached production routes must not accidentally include a test-only API.
- One minimal, reviewed **OpenAPI shared response/error/correlation schema** is provided for future Feature-specific OpenAPI definitions; no generated SDK or new generator package required by this draft.

## 3. Functional requirements (proposed)

- **FR-001 — Native integration:** extend the actual Laravel 13 bootstrap and Core technical layer; preserve explicitly registered Module Providers and F0-01 green behavior. No directory scan or HTTP calls between modules.
- **FR-002 — Success API:** central helper for 200/201 successes with data/message, optional honest meta, and correct 204 **empty** response.
- **FR-003 — Error API:** central helper and exception mapping for safe 400/401/403/404/409/422/429/500/503 outcomes and the compact shared error codes approved by API Standards. Never hide a failure behind 200/success=false.
- **FR-004 — Validation:** Laravel-native Validation/Form Request error capture, camelCase field keys in the API contract and correct 422/VALIDATION_FAILED; exact domain Form Requests remain future Feature-owned.
- **FR-005 — Correlation:** one server-generated trusted `X-Request-ID` per attempt; test header availability for handled success/error, header injection and absence of accidental request-global state.
- **FR-006 — Localization:** ar/en generic message dictionaries and reviewed request negotiation. `errorCode` and fields remain language-neutral; fallback choice is **OPEN** for owner review.
- **FR-007 — Contract:** small reviewable OpenAPI component definitions for envelope, generic errors, validation and request-ID header. Explicitly respect FBR-02-06/07: finalized Money minor units are decimal **strings** with currency; database BIGINT identifiers are decimal **strings**. Do not copy obsolete illustrative numeric wire examples as normative schema.
- **FR-008 — Secure scope:** technical only; **no** Sanctum/session/CSRF installation, Central/Tenant DB, migrations, provisioning, Redis/Horizon, entitlement, stock, orders, payments, search/filter/Spatie Query Builder, idempotency persistence, business APIs or production demo route.
- **FR-009 — Tests:** real Laravel/Pest unit+HTTP/negative/exception contract/locale/request-ID/architecture tests; include production/non-testing and production route-cache boundary checks; keep Composer pinned, avoid destructive test data.
- **FR-010 — Evidence:** actual command, environment, full git SHA, test results and source diff recorded in feature review. Unrun Pint/PHPStan must remain explicit, not described as green.

## 4. Non-goals and deferred integrations

- **F1 Tenancy:** Central directory, trusted Tenant resolution, Tenant connection management and DB migrations.
- **F2 Identity & Governance:** Sanctum browser authentication, user/role/Location authorization, CSRF enforcement. The approved `403/CSRF_TOKEN_MISMATCH` contract is reserved, **but real TokenMismatch mapping is validated only when its auth integration exists**, never by turning off CSRF.
- **F3 Operations:** Redis-backed rate limits, transactional Outbox, idempotency durability, jobs, correlation propagation into worker/metrics and private file downloads. F0-02 may define a safe error contract without pretending these dependencies are live.
- **Domain Features:** no Orders/Inventory/Customer/Payment concrete resources; no business error semantics beyond shared generic mapping.
- **F0-03/F0-04:** no unapproved dedicated test framework, Pint/PHPStan or OpenAPI generator dependency installation.

**Still open elsewhere:** D-201; BA-16 and BA-30 G3 remain deferred/blocked.

## 5. Acceptance and negative scenarios to prove after authorization

| ID | Scenario | Expected outcome |
| --- | --- | --- |
| AC-01 | Technical success with data, optional meta | 200/201, correct success shape; no fabricated counts |
| AC-02 | No-content success | 204 and **zero response body** |
| AC-03 | Validation failure with nested fields | 422, `VALIDATION_FAILED`, camelCase path keys and field errors |
| AC-04 | Unknown API route / protected resource not found | 404, safe error envelope, no stack trace |
| AC-05 | Unauthenticated, forbidden, conflict, throttled and service-unavailable synthetic exceptions | Corresponding real HTTP status and stable errorCode where approved; no auth/rate-limit engine assumed |
| AC-06 | Unhandled PHP/Laravel exception | Safe 500/INTERNAL_ERROR; exception details not exposed |
| AC-07 | ar vs en request | Localized generic message, stable errorCode/path names |
| AC-08 | Untrusted client `X-Request-ID` input / two successive attempts | Safe generated unique response header, not an authorization or business operation key |
| AC-09 | Production boot + generated route-cache fixture | No test-only probe route exposed; no new production business route |
| AC-10 | Laravel Module Registry & previous Feature suite | Existing F0-01 behavior/26-task evidence unaffected; actual new suite and source/code SHA captured |

## 6. Material open decisions — resolve in F0-02 review

| ID | Decision for approval | Constraints |
| --- | --- | --- |
| **F0-02-DEC-01** | Which fallback response-message locale when `Accept-Language` is absent or unsupported? **ar** (Egypt-first candidate) vs **en**, or another explicitly approved fallback | API Standards AS-03 explicitly says fallback **TO SPECIFY**. Do not infer it from market alone. |
| **F0-02-DEC-02** | Which minimal Core error-code transport in PHP? Compact constants/value objects versus dedicated int-backed enum/wire mapper | Preserve stable external strings; CS-05 requires **int-backed** project-defined Enums, so avoid a string-backed enum. |
| **F0-02-DEC-03** | Concrete canonical shared OpenAPI path and lightweight schema validation approach, without introducing new dependencies without review | Feature-owned OpenAPI stays close to specs; one schema baseline, no duplicated independently editable definitions. |
| **F0-02-DEC-04** | Whether to register an empty `routes/api.php` native `/api/v1` group now, or provide only configuration and test-scoped fixture for first integration | No public production routes, preserve tested F0-01 route-cache isolation. |

## 7. Review/approval checklist

- [ ] Owner approves or amends this exact bounded F0-02 scope, exclusions and DEC-01…04.
- [ ] Engineering review verifies Constitution, AS-01/02/03/08/09, CS-05/06/07/08 and MA-08/13 boundaries; approved no-migration/no-auth plan.
- [ ] [F0-02 plan](plan.md) independently reviewed and approved with source/config/test paths and error mapping table.
- [ ] Only after both approvals, generate final `tasks.md`, implement on this feature branch with real test/CI evidence and PR review.
- [ ] Do not merge to `development` or promote `main` merely because this draft exists.
