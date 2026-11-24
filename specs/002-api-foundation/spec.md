# Feature Specification — F0-02: API Foundation

**Status:** **OWNER-APPROVED SPEC — IMPLEMENTED, TECHNICALLY VERIFIED AND MERGED TO DEVELOPMENT 2026-11-25; CLOSEOUT PUBLICATION PENDING**  
**Prepared/reviewed:** 2026-11-24 | **Working branch:** `feature/f0-02-api-foundation`  
**Feature owner:** Backend Engineering Foundation / Core (not a business-domain writer)  
**Dependency:** [F0-01 complete on `development`](../../docs/handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md) with [task ledger 26/26](../001-laravel-bootstrap-module-registry/tasks.md)  
**Authority:** [Constitution v1.0.0](../../.specify/memory/constitution.md), [API Standards AS-01/02/03/08/09](../../docs/02-standards/api-standards.md), FBR-02-05/06/07, [Backend Architecture BA-11](../../docs/01-architecture/backend-architecture.md), [Module Architecture §§8/13/16](../../docs/01-architecture/module-architecture.md), [Code Standards CS-05/06/07/08/09](../../docs/02-standards/code-standards.md), [Testing Standards TEST-05](../../docs/02-standards/testing-standards.md), [Localization Standards LOC-ST-02](../../docs/02-standards/localization-standards.md#4-loc-st-02--supported-locales-resolution-preferences-and-fallback).

> This is the **owner-approved bounded F0 engineering Feature specification** (reviewed baseline `c9897aac60975b2e5aa90583480972c02152bb0d`). Approval covers the revised spec and plan; the owner separately authorized scoped implementation on 2026-11-24 (T003). This does not turn all API Standards into implemented behavior; actual evidence and separate merge approval remain required. See [tasks](tasks.md), [consistency analysis](consistency-analysis.md) and [approval handoff](../../docs/handoff/F0-02_SPEC_PLAN_APPROVED_TASKS_ANALYZED_2026-11-24.md).

## 1. Problem and intended outcome

F0-01 produced a verified Laravel 13 PHP ^8.4 application and a native **explicit Module Registry**, deliberately without public production APIs, Auth, Tenancy or database state. Later modules need one consistent, small API HTTP layer so they can expose scoped routes and safe success/error contracts without each redefining exception shapes, request correlation or versioning.

**F0-02 defines the approved minimal shared HTTP/API contract plumbing**, not a business resource, not Tenant resolution and not a claim that all AS-01–AS-09 are now executable. A future approved module will supply its actual protected endpoints, DTOs, OpenAPI and authorization.

## 2. User and integration stories

### US1 — Reusable API response contract (P1)

A later Feature author can invoke one technical response helper for completed successes and classified failures, while a Next.js consumer receives a stable shape and genuine HTTP status.

**Acceptance:**

- Success uses `{"success":true,"message":"...","data":...}` and *optional only if meaningful* `meta` for real pagination; never invented totals or a forced envelope for downloads.
- Failure uses `{"success":false,"message":"...","errorCode":"...","errors":[]}` with **stable machine-readable** `errorCode`; validation uses a field-keyed object with camelCase names, including nested paths.
- HTTP status reflects the outcome (at minimum correctly handle representative 200/201/204 and 400/401/403/404/405/409/422/429/500/503); `204` has **no body**, not `{"success":true}`.
- An unclassified internal exception must yield a safe 500 without SQL, source paths, secrets or stack traces in the client response.
- `App\Helpers\ApiResponse` and int-backed `App\Enums\HttpStatusCode` are the approved API Standards direction; F0-02 must not introduce business status Enums or invent monetary/identifier wire types.

### US2 — Trusted request correlation and localized generic errors (P1)

For each independent `/api/v1` HTTP attempt, a safe generated `X-Request-ID` is available on success/error where technically possible, distinct from Idempotency-Key, Tenant selection and domain operation identity.

**Acceptance:**

- Generate a trusted request ID per HTTP attempt; set `X-Request-ID` response header. Do not put it into the default JSON envelope or trust an arbitrary caller-supplied header as auth/identity.
- Central handling of API exceptions selects stable HTTP status/errorCode while `message` is safe and localized (initial supported message languages **ar** and **en**). The same errorCode and field path remain stable across languages.
- No raw Laravel exception trace, SQL, credentials, other Tenant record existence or sensitive body logged/returned. No globally retained request/Tenant identity.
- **DEC-01 approved:** supported `ar`/`en`, regional forms mapped to supported parent language, bounded quality-weighted `Accept-Language` negotiation excluding `q=0` preferences; absent/unsupported/malformed input safely falls back to `ar` (also previously approved by LOC-ST-02). Request locale must not leak across attempts. Missing translations follow LOC-ST-02.

### US3 — Versioned, module-friendly HTTP foundation (P2)

A later registered module can add its own routes under reviewed `/api/v1` conventions without automatic discovery or a new publicly accessible debug endpoint.

**Acceptance:**

- Laravel-native version routing/integration matches `/api/v1`; DEC-04 approves an empty native `routes/api.php` seam. Paths use lower-case kebab-case for multiword resources and only future module-owned routes create real resources.
- The four approved logical surfaces (Platform, Merchant Dashboard, Public Storefront, Authenticated Customer) are **documented contracts**, not four open bypass routes.
- F0-02 adds **no public business endpoint** and does not expose F0-01's technical probe outside `testing`. Production route collection and cached production routes must not accidentally include a test-only API.
- One minimal, reviewed **OpenAPI shared response/error/correlation schema** is provided for future Feature-specific OpenAPI definitions; no generated SDK or new generator package required by this draft.

## 3. Approved functional requirements

- **FR-001 — Native integration:** extend the actual Laravel 13 bootstrap and Core technical layer; preserve explicitly registered Module Providers and F0-01 green behavior. No directory scan or HTTP calls between modules.
- **FR-002 — Success API:** central helper for 200/201 successes with data/message, optional honest meta, and correct 204 **empty** response.
- **FR-003 — Error API:** central helper and exception mapping for safe 400/401/403/404/405/409/422/429/500/503 outcomes. Use approved shared error codes, including 400/BAD_REQUEST, 405/METHOD_NOT_ALLOWED, 503/SERVICE_UNAVAILABLE and HTTP_ERROR for other genuine unclassified HTTP errors as approved in plan §3.2. Preserve genuine HTTP status and protocol headers (`Allow`, `Retry-After`, and `WWW-Authenticate` when applicable/provided); unexpected non-HTTP failures are safe 500. Never hide a failure behind 200/success=false.
- **FR-004 — Validation:** Laravel-native Validation/Form Request error capture, camelCase field keys in the API contract and correct 422/VALIDATION_FAILED; exact domain Form Requests remain future Feature-owned. Preserve declared wire field paths, not arbitrary recasing of caller-supplied keys; ensure field messages do not disclose sensitive input or technical details.
- **FR-005 — Correlation:** one server-generated trusted `X-Request-ID` per attempt; test header availability for handled success/error, including unmatched API routes and method mismatch, ignored client header, and absence of accidental request-global state. Attach a safe request ID to relevant technical diagnostics; no cross-request context retention or worker-level tracing implementation.
- **FR-006 — Localization:** ar/en generic message dictionaries and bounded request negotiation; approved fallback `ar` (DEC-01/LOC-ST-02). `errorCode` and fields remain language-neutral. Verify locale and request-local logging cleanup, including exception paths and successive requests.
- **FR-007 — Contract:** small reviewable OpenAPI 3.1 JSON component definitions for envelope, generic errors, validation and request-ID header at the DEC-03 approved path `contracts/openapi.json` relative to this spec. No public paths are invented. Native syntax/structure/ref and representative HTTP contract checks are not a full standards-conformance validator. Explicitly respect FBR-02-06/07: finalized Money minor units are decimal **strings** with currency; database BIGINT identifiers are decimal **strings**. Do not copy obsolete illustrative numeric wire examples as normative schema.
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
| AC-04 | Unknown API route / method mismatch / resource not found fixture | Real 404 or 405, safe error envelope and server request ID; preserve `Allow` for 405; no stack trace |
| AC-05 | Bad request, unauthenticated, forbidden, conflict, throttled and service-unavailable synthetic exceptions | Corresponding real HTTP status and stable errorCode per final reviewed mapping; preserve applicable protocol headers; no auth/rate-limit engine assumed |
| AC-06 | Unhandled PHP/Laravel exception | Safe 500/INTERNAL_ERROR; exception details not exposed |
| AC-07 | ar/en, regional/weighted/unsupported/malformed headers and missing-key fallback | Correct request-local message and stable errorCode/path names; no previous request locale leak |
| AC-08 | Untrusted client `X-Request-ID` input / successive normal and failing requests | Safe generated unique response header and scoped safe diagnostics; no identity/locale leakage; not an authorization or business operation key |
| AC-09 | Production boot + generated route-cache fixture | No test-only probe route exposed; no new production business route |
| AC-10 | Laravel Module Registry & previous Feature suite | Existing F0-01 behavior/26-task evidence unaffected; actual new suite and source/code SHA captured |

## 6. Owner decisions — approved 2026-11-24 (four of four)

| ID | Owner-approved choice | Boundary |
| --- | --- | --- |
| **F0-02-DEC-01** | **A — Arabic (ar) fallback**, validated ar/en and regional/weighted negotiation | Also ratifies existing LOC-ST-02; no actor preference DB, email policy, or Tenant/Identity switch. |
| **F0-02-DEC-02** | Compact shared string constants in `ApiErrorCodes`; `HttpStatusCode` int-backed Enum; centralized mapper | No string-backed PHP Enum or global domain catalog. Additional generic mappings approved in plan §3.2 on 2026-11-24. |
| **F0-02-DEC-03** | OpenAPI 3.1 JSON at `specs/002-api-foundation/contracts/openapi.json`, no new packages, empty paths and shared components | Lightweight structural/contract tests only; do not claim full OpenAPI conformance or generated clients. |
| **F0-02-DEC-04** | Register the empty native `routes/api.php` under `/api/v1` now | Explicit module-owned routes remain authoritative; no public debug, probe, business or health route added. |

**Authority:** owner response “1- A; 2- تمام موافق على ترشيحك; 3- موافق; 4- مظبوط موافق على كلامك” in the F0-02 review on 2026-11-24. See [dated decision handoff](../../docs/handoff/F0-02_OWNER_DECISIONS_AND_REVIEW_2026-11-24.md). AS-03's historical unspecified locale is resolved by the later approved LOC-ST-02 and this explicit confirmation.

## 7. Review/approval checklist

- [x] Owner approved DEC-01…04.
- [x] Owner approves the complete revised specification, scope, exclusions and detailed HTTP/locale/contract behavior (2026-11-24, reviewed baseline SHA above).
- [x] Owner approves the revised [implementation plan](plan.md) in the same explicit approval response, including additional error mappings, CI coverage and safe request lifecycle.
- [x] Generate [tasks.md](tasks.md) and complete [documentation consistency analysis](consistency-analysis.md).
- [x] Obtain separate explicit implementation authorization (T003, 2026-11-24).
- [x] Implement tests first and minimal runtime code; verified actual CI, including post-review hardening, per [quickstart](quickstart.md).
- [x] ChatGPT technical review, owner sign-off in response to review request and final integrated candidate recheck (T027).
- [ ] No merge to `development`, promotion to `main` or deployment without separate authorization and applicable gates.

**T001–T029 verified; T030 closeout documentation complete in the proposed final state. **30/30 applies only when this closeout PR #6 is merged into development and the publication is verified**. Before that, T030 remains pending. Not production-ready.**
