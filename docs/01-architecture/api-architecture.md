# E-commerce SaaS V2 — API Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/api-architecture.md`  
**Version:** 1.0 — Approved API Architecture Baseline  
**Prepared:** 2026-11-17  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — API-08 (A)**  
**Decision Coverage:** **API-01 through API-08**  
**Implementation Status:** **Not implemented, installed, tested or verified**

> **Reading rule:** This document approves the MVP API conventions for a Laravel 13 / Next.js E-commerce SaaS. It does **not** prove package compatibility, functioning routes, implemented middleware, verified security, deployed OpenAPI specifications or readiness to serve live merchants. Feature-specific endpoints, fields, business rules, integration results and Pilot gates retain their own approval and validation requirements.

---

## 1. Document Overview, Authority and Boundaries

### 1.1 Purpose

Provide one lightweight and consistent standard for the four HTTP API surfaces, request and response shapes, filtering/pagination/search, validation, error localization, idempotency, authorization, OpenAPI contracts, versioning and API-focused security.

The system is a **Laravel 13 API-first Modular Monolith** with **Next.js** browser clients, **Central PostgreSQL plus one independent PostgreSQL database per Tenant**, Redis/Horizon and the conditionally selected `stancl/tenancy v3.10.1`. The MVP launches through a controlled Egypt-only Pilot; approval here does not relax existing business or release requirements.

### 1.2 Authoritative references

Read with:

- [Backend Architecture](./backend-architecture.md) — BA-11 REST API direction, BA-02/03 tenancy, BA-08 consistency, BA-10 rate limits, BA-12/13 authorization, BA-24 observability and BA-25 testing.
- [Module Architecture](./module-architecture.md) — explicit module registration, public contracts, four API surfaces and feature-level specifications.
- [Tenancy Architecture](./tenancy-architecture.md) — trusted resolution, PostgreSQL connections, session isolation, fail-closed behavior and Stancl integration gates.
- [Identity & Access Architecture](./identity-access-architecture.md) — IA-01…IA-09, Central workforce identity, Tenant-local customers/roles, Sanctum sessions, CSRF, authorization and revocation.
- [Data Architecture](./data-architecture.md) — PostgreSQL-first search, index/pagination conventions and safe schema evolution.
- [Project Overview](../00-overview/project-overview.md), [D-series Decision Register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) and [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).

**Authority rule:** approved D-series decisions control business behavior, OV decisions define MVP scope, BA/MA/TA/DA/IA baselines govern their technical domains, and API-01…API-08 govern the HTTP API contract within those boundaries. Surface any apparent conflict rather than silently overriding approved behavior.

### 1.3 Explicit non-goals

Do not introduce microservices, a custom API gateway, an HTTP-to-HTTP internal module architecture, JWT for browser authentication, MFA, a new validation/query framework, global shopper identity, a developer portal, mandatory generated SDKs, multiple preemptive API versions, or a universal dynamic filter language.

## 2. Approved Decisions — API-01 to API-08

| Decision | Approved option | Direction |
| --- | --- | --- |
| **API-01** | **A — Single Versioned REST API** | One Laravel Modular Monolith, `/api/v1`, explicitly separated Platform, Merchant Dashboard, Public Storefront and Authenticated Customer route surfaces. Tenant resolution/guards and multi-tab-safe merchant context follow IA decisions. Final endpoint names are feature-level contracts. |
| **API-02** | **A — Standardized ApiResponse + camelCase** | Preserve `success`/`message`/`data` and `success`/`message`/`errors`, with `errorCode` for machine-readable failures and optional `meta` for paginated results. JSON and application query keys are camelCase. Put `X-Request-ID` in response headers, not JSON. |
| **API-03** | **A — Spatie Query Builder v7, conditional** | `filter[...]`, `sort`, safe allowed fields/aliases, native Laravel pagination and PostgreSQL-first search. Validate package/Laravel/PHP compatibility, including the PHP 8.4 requirement, before installation. |
| **API-04** | **A — Laravel-Native Validation** | Form Requests, centralized exception-to-`ApiResponse` mapping, localized Arabic/English messages, stable error codes, camelCase field errors and safe Spatie query errors. |
| **API-05** | **A — Targeted Idempotency** | Client-generated `Idempotency-Key` for selected risky writes; durable tenant-scoped operation tracking, PostgreSQL ACID/uniqueness/concurrency safeguards and retry-safe Horizon/Outbox behavior. Distinct from `X-Request-ID`. |
| **API-06** | **A — Lightweight OpenAPI Contract-First** | OpenAPI as contract reference, one initial `/api/v1`, backward-compatible evolution, focused contract tests and no mandatory SDK generation or portal. |
| **API-07** | **A — Laravel Native Security** | Sanctum/CSRF, trusted hosts/proxies, Tenant and Location checks, Redis-backed layered rate limits by IP/user/tenant/endpoint, HTTP 429/`Retry-After` and risk-based security tests. No MFA or custom security framework. |
| **API-08** | **A — Lightweight API Architecture Baseline** | Approve API-01…API-07 as the MVP architecture direction. Integration, testing, operational and Pilot readiness remain pending actual evidence. |

## 3. API Surfaces, Routing and Ownership — API-01

All routes belong to **one REST JSON API** under `/api/v1`. They are logical, authorization-enforced surfaces, **not** four Laravel deployments.

| Surface | Execution context | Who can access it |
| --- | --- | --- |
| **Platform API** | Central-only by default | Central workforce identity with explicit Platform permissions |
| **Merchant Dashboard API** | Authorized Tenant after Central membership validation | Active merchant staff with feature entitlement, Tenant-local permission, Location scope and resource/business authorization |
| **Public Storefront API** | Tenant resolved from trusted approved storefront subdomain | Public and eligible guest actions only |
| **Authenticated Customer API** | Same trusted Tenant; Tenant-local customer identity | Registered customer with matching Tenant guard and resource ownership |

Merchant requests **must explicitly identify the intended Tenant** in their trusted route/context. An illustrative selector is `/api/v1/tenants/{tenant}/orders`; the exact prefixes and resource names are **not yet approved endpoints**. The Tenant selector is never authorization by itself. Validate Central membership, Tenant state, entitlement, host agreement, local authorization and ownership for each applicable request. Storefront/Customer routing uses the approved subdomain-to-Tenant mapping. Reject unknown or mismatched hosts/contexts rather than falling back to a previous/default Tenant.

The approved IA-03 **multi-tab rule** applies: two browser tabs operating in two merchants must not share an implicit active-tenant switch. A "last selected merchant" preference is UI-only.

Business modules register their own route groups, application operations and policies through the lightweight explicit Module Registry. The same Catalog module can expose permitted public reads and protected merchant mutations; do not make cross-module HTTP requests inside the same monolith.

Use standard HTTP verbs according to resource behavior; individual verbs, endpoint paths, payloads and operation semantics are specified per Feature and reviewed in OpenAPI.

## 4. Naming and Serialization — API-02

**All application-owned request and response keys are `camelCase`**:

- JSON objects, including nested records: `customerName`, `shippingAddress.city`, `createdAt`.
- JSON validation error field paths: `customerName` and `items.0.productId`.
- Query parameters: `perPage`, `filter[locationId]` and `sort`.
- API metadata and error fields: `errorCode`, `currentPage`, `totalItems`.

Internal PHP/database names may use Laravel/PostgreSQL conventions such as `customer_name` and `created_at`. Serialize through explicit API Resources/DTOs or reviewed mappings; **never accidentally expose database names** in successful responses, errors, filters or sorting. Spatie API aliases must map allowed camelCase client names to their authorized underlying columns.

HTTP headers retain their conventional names, e.g. `Accept-Language`, `X-Request-ID`, `Retry-After` and `Idempotency-Key`; the JSON camelCase rule does not rename headers. Identifiers, enum wire values and exact domain properties are determined by each Feature contract.

## 5. Standard Responses, HTTP Status and Correlation — API-02

Preserve the user-approved `App\Helpers\ApiResponse` pattern with the `App\Enums\HttpStatusCode` style rather than replacing it with a new envelope. The implementation may extend the helper to support optional `meta`/`errorCode` and correct HTTP special cases.

**Illustrative successful resource response:**

```json
{
  "success": true,
  "message": "تم جلب الطلب بنجاح",
  "data": {
    "orderId": 1045,
    "customerName": "Ahmed",
    "totalAmount": 1250
  }
}
```

**Illustrative validation failure (HTTP 422):**

```json
{
  "success": false,
  "message": "يرجى مراجعة البيانات المدخلة",
  "errorCode": "VALIDATION_FAILED",
  "errors": {
    "productName": ["اسم المنتج مطلوب"],
    "items.0.productId": ["المنتج المحدد غير صالح"]
  }
}
```

Successful ordinary JSON responses use `success: true`, `message` and `data`; add `meta` only where needed (primarily pagination). Error JSON uses `success: false`, localized `message`, stable `errorCode` and `errors` (empty array when no field details apply; field-keyed object for validation). Keep the response shape consistent for controller output and mapped exceptions.

**HTTP status is authoritative**: do not send HTTP 200 for a failed operation or claim success before a pending operation finishes. Use the approved `HttpStatusCode` enum direction (including 200, 201, 204, 400, 401, 403, 404, 409, 422, 429 and 500); extend it for genuine 202/503 cases when relevant. `204 No Content` returns **no body**. File/download responses are not wrapped in JSON. Raw Laravel 419 CSRF behavior needs a compatible documented client response path; 419 is not a standard HTTP status.

**Request correlation:** server-side middleware creates a trusted unique request identifier for each HTTP request, associates it with safe log context and emits `X-Request-ID` on responses (including errors, and responses without JSON bodies where feasible). Do **not** repeat `requestId` in the JSON payload. A client-provided value is not trusted without explicit validation. Pass trace context into jobs only when relevant, without transferring authentication authority.

```http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json
X-Request-ID: 01KAB7M82FQ6
```

### FBR-02-05/06/07 — API Error and Serialization Refinements (2026-11-22)

The final protected API contract maps **identified CSRF-token mismatches to HTTP 403 with `CSRF_TOKEN_MISMATCH`**, distinguishing unauthenticated 401, ordinary forbidden 403 and true throttling 429. Laravel's internal 419 must be mapped and proven with the selected Sanctum/Next.js integration; no unsafe automatic retry of uncertain commits.

JSON Money inputs/outputs use **exact decimal strings of integer currency minor units** plus authoritative currency context; BigInt DB identifiers and relevant references use **canonical decimal strings**, not unsafe JSON Numbers. PostgreSQL BIGINT, cross-DB UUIDv7 exceptions, exact calculations and Feature-specific OpenAPI continue unchanged. Existing numeric example payloads are nonbinding illustrations and must not be copied into final contracts.

## 6. Request Validation, Error Codes and Localization — API-04

Use **Laravel Form Requests** and the framework's native validation, authentication and authorization exceptions. Centralize exception-to-response mapping; business modules define only their necessary business errors. Do not build a custom validation rules engine or send internal exception traces to browser clients.

- Validate allowed input fields, types, array depth, required values, query pagination/sorting and request size before applying business effects.
- Use explicit authorized assignments to prevent mass assignment of Tenant IDs, Location grants, approval/status/price attributes or other privileged server-owned fields.
- Keep **format validation** separate from **business eligibility and resource authorization**: e.g. `quantity` > 0 does not prove stock availability.
- Map invalid/disallowed Spatie filters and sorts to stable client-facing errors (proposed `INVALID_FILTER` / `INVALID_SORT` with HTTP 422), rather than raw package exceptions. **Do not silently ignore unknown filters** if doing so could widen results.
- Use Laravel translations and supported `Accept-Language: ar` / `en` for client-facing messages; code/field names do not change with locale. Default/fallback locale is finalized during localization configuration (Arabic is the suggested Egypt-Pilot default).
- Avoid exposing whether an account, customer, order or Tenant exists when that would break isolation; an authorization-sensitive 404 can be appropriate.
- CSRF/session failures must be handled without bypassing the approved cookie/CSRF model.

**Proposed compact shared error identifiers:** `VALIDATION_FAILED`, `UNAUTHENTICATED`, `ACCESS_DENIED`, `RESOURCE_NOT_FOUND`, `RESOURCE_CONFLICT`, `FEATURE_UNAVAILABLE`, `INVALID_FILTER`, `INVALID_SORT`, `RATE_LIMIT_EXCEEDED` and `INTERNAL_ERROR`. These names illustrate the adopted stable-code convention; finalize exact registry/mapping in API Security Standards and feature-specific OpenAPI contracts. Do not build a speculative global catalog of hundreds of errors.

## 7. Filtering, Sorting and Pagination — API-03

Adopt **Spatie Laravel Query Builder v7 conditionally**, integrated with Eloquent and the owning module's scoped query. **Installation and runtime adoption are gated on verifying the actual Composer constraints and compatibility of Spatie v7, Laravel 13, PHP 8.4 and the project environment.** No package compatibility test or installation is represented by this document.

**Illustrative GET with query parameters:**

```http
GET /api/v1/tenants/store-a/orders?filter[status]=pending&filter[locationId]=5&sort=-createdAt&page=1&perPage=20
```

The example route is nonbinding; the **query syntax** is the intended standard:

- `filter[fieldName]` for explicitly allowed filters, with `camelCase` client field names.
- `sort=createdAt` for ascending or `sort=-createdAt` for descending on explicitly allowed fields. Map aliases safely to internal column names.
- `page` / `perPage` for Laravel page-based pagination.
- Use Spatie `allowedFilters`/`allowedSorts`, trusted server-side query predicates and optionally allowlisted includes. No arbitrary client-selected SQL fragments or unlimited relationship expansion.
- Use **bounded pagination** and deterministic/default order where needed. `perPage=20` and a maximum of 100 were discussed as **proposals**, **not final approved numeric limits**; decide limits per API Standard/Feature with measured data.
- Paginated results retain the agreed `ApiResponse` envelope with optional `meta`. Suggested field names: `currentPage`, `perPage`, `lastPage` and `totalItems`; verify cost/availability of total counts for each endpoint rather than promising expensive totals universally.

**Illustrative list response:**

```json
{
  "success": true,
  "message": "",
  "data": [
    {"id": 1045, "status": "pending", "createdAt": "2026-11-17T10:30:00Z"}
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "lastPage": 1,
    "totalItems": 1
  }
}
```

Apply Tenant/Location/entitlement/ownership restrictions to the base query and each sensitive relation **independently of Spatie's filter allowlists**. A supplied `filter[locationId]` is not a Location permission. Invalid query inputs must not expose broader results. Heavy CSV/Excel exports use separate authorized/limited workflows instead of unbounded pagination values.

## 8. Search Standards — API-03 / BA-26

**PostgreSQL-first** search is the MVP strategy. A conventional Spatie callback/custom filter can expose an allowed search criterion such as `filter[search]=Samsung`; feature owners select exact searchable fields and semantics (SKU, product name, permitted customer fields, order reference, etc.).

- Run search **within the trusted Tenant and authorized Location/customer scope**, including permitted relationships.
- Prefer indexed exact/reference lookups; add appropriate PostgreSQL indexes and only adopt full-text or `pg_trgm` where real requirements and query measurements justify them.
- Validate Arabic/English search, normalization and ranking on representative content; PostgreSQL extensions do not automatically guarantee linguistic correctness.
- Avoid a custom query DSL, Elasticsearch/Meilisearch or another dedicated search service for the first Pilot.
- A module-facing abstraction may allow a future search implementation without changing business contracts, but does not itself authorize deploying a new engine.

## 9. Authentication, Authorization and Tenant Isolation — API-01 / API-07

Browser authentication uses **Laravel Sanctum first-party session cookies + CSRF**, with Next.js using the approved **same-origin API reverse-proxy** topology. Use host-only secure authentication cookies (HTTPS `Secure`; session `HttpOnly`), appropriate SameSite, explicitly trusted hosts/proxies and trusted stateful origins. Do not use wildcard parent-domain authentication cookies, JWT browser auth, implicit cross-host SSO or MFA in MVP.

Separate the **Central workforce guard** (Platform Admin and merchant staff) from **Tenant-local customer guards**. Guest storefront requests only access explicitly permitted public/guest operations. Customer sessions must not resolve as workforce sessions or across Tenants; verify session isolation including Stancl `ScopeSessions` behavior.

For each applicable Merchant Dashboard request, enforce **all necessary layers**: correct authenticated Central identity, validated explicit Tenant membership/state, entitlement, Tenant-local role and permission registry, allowed Location scope, resource ownership, and business-specific discount/refund/approval rules. Do not trust Tenant IDs, arbitrary host headers, query filters or client claims as proof of authorization. Unavailable or mismatched Tenant contexts fail closed; context is cleared between requests and Horizon jobs. Platform Admin status is not a blanket bypass of Tenant business policies.

Enforce permissions on reads, writes, private files, exports, reporting, background work and sensitive business actions, not just frontend UI components. Revocation must block new access to the affected Tenant even with a still-valid Central session and stale cache.

## 10. Targeted Idempotency and Safe Retries — API-05

Require/support **client-generated `Idempotency-Key`** for the high-risk write endpoints selected by their owning features, especially appropriate order creation, Direct Sales, collections, refunds and other effects where duplicate execution is harmful. A new network attempt for **the same logical operation** reuses the original key; an unrelated operation gets another key.

**Rules:**

1. Scope durable operation identity to the authoritative context (Tenant for merchant business effects; Central for Central-only operations), business operation type and appropriate actor/contract boundaries; never share an unqualified global key.
2. Store a trustworthy association of key, canonical request meaning, operation state and recoverable outcome. Concurrent duplicates must be serialized/claimed safely through PostgreSQL uniqueness/locks/transactions, not Redis TTL alone.
3. Once complete, repeat requests with the same key and same meaning may return the prior authorized outcome **without reapplying effects**. Revalidate current access before disclosing any stored response.
4. Reusing a key for different request content yields a conflict (illustratively HTTP 409). Concurrent in-progress duplicate attempts produce a clear retryable state (e.g. a documented 409 code), not a second sale.
5. Key retention/TTL, safe response replay, timeout handling and terminal failure semantics are **feature-specific pending details**, especially for payments and refunds. Expiring an HTTP key does not waive permanent business-level duplicate protection.
6. Enforce domain-level invariants independently: different keys must never bypass one-time reservation, dispatch, collection, refund or actual stock movement constraints.

**`X-Request-ID` is per HTTP attempt for debugging; `Idempotency-Key` identifies a logical retried operation**. They are never interchangeable.

Use **tenant-local ACID transactions**, unique operation references and concurrency control for critical writes, with durable Outbox records committed alongside the applicable business effect and idempotent consumers. Redis/Horizon can redeliver jobs; do not claim exactly-once processing or financial transfer. Central-to-Tenant operations require explicit durable staged coordination, reconciliation and compensating actions as defined by the owning workflow, **not** distributed ACID.

## 11. API Security, Rate Limiting and Abuse Protection — API-07

Use **Laravel native security and named rate limiters with Redis-backed state**. Define practical layered policies for relevant endpoint classes: login and password recovery (IP plus safe account identifier), Platform (Central actor/endpoint), Merchant (authorized actor/Tenant/endpoint), Public Storefront (IP/Tenant/endpoint), authenticated customer (Tenant/customer/endpoint), and high-risk checkout, upload or export endpoints.

- Trust client IP only through explicitly configured trusted proxy boundaries; do not accept spoofed forwarded headers or unvalidated Tenant information as limiter identities.
- Account for shared/NAT IPs, prevent easy unauthenticated bypass and avoid a noisy Tenant unintentionally starving unrelated Tenants.
- **Numeric rates, burst allowances, exceptions and lockout periods are pending Security Standards, tests and measured traffic**; no values are fixed by API-07 approval.
- On throttling, return **HTTP 429**, the standard error envelope with `RATE_LIMIT_EXCEEDED` and a meaningful `Retry-After` header where available. Emit `X-Request-ID` for correlation.
- Next.js must not perform unlimited retries, particularly for non-idempotent or money/stock-impacting requests.
- Validate request/upload sizes, restrict private file downloads, preserve CSRF/origin protection, prevent over-posting, use safe DB bindings/least-privilege roles and keep secrets, passwords, session IDs and sensitive customer/payment data out of logs.
- High-risk monitoring is proportionate and privacy-limited. A paid WAF, CAPTCHA service, dedicated gateway, SIEM or bespoke abuse framework is **not mandatory** for this MVP; reassess against observed abuse and infrastructure risk.

### FBR-02-10 — Lost Rate-Limiter Protection

A genuine exceeded quota is 429 / RATE_LIMIT_EXCEEDED. When the Redis limiter required for a security-sensitive route cannot protect it, **fail secure with 503** and a stable service-unavailable contract (unless independently proven equivalent protection is available). Bounded public read degradation may be separately permitted. Do not treat a Redis outage as proof the account exceeded its quota.

## 12. OpenAPI, Versioning and Backward Compatibility — API-06

Use **OpenAPI as the reviewed contract reference** for relevant routes. Each Feature describes HTTP method/path, request/query fields in camelCase, allowed filtering/sorting, auth surface, required permissions/tenant context, response schema, relevant HTTP statuses, localized error codes, `X-Request-ID`, and `Idempotency-Key` when applicable.

**Single API version:** `/api/v1`. Release numbers and Git branches are independent of API version; adding compatible endpoints or optional fields does not automatically require `/api/v2`.

**Compatibility discipline:**

- Treat removal/renaming of used response fields, changing types or required inputs, changing error/enum semantics and other client-breaking behavior as changes requiring explicit review.
- Additive fields are normally compatible, but newly added enum variants may still break restrictive clients; verify Next.js consumer behavior.
- Where practical, deploy additive backend support before changing Next.js consumers; remove compatibility behavior only after verification and a reviewed deprecation window.
- Apply focused **contract tests** for approved schemas, successful and forbidden cases, representative locales, pagination, error headers, and shared auth/tenant boundaries.
- Document Feature-specific contracts close to their spec and present a coherent reviewed OpenAPI artifact; the canonical generated/manual tooling and paths are determined during Foundation/Spec Kit setup.
- Code generation of TypeScript SDKs, a public developer portal and a large independent API lifecycle platform are **optional future tools**, not MVP prerequisites. Internal Platform API documentation is not publicly exposed by default.

## 13. Validation Matrix and Release Gates — API-08

The decisions are a baseline, **not** test results. Minimum meaningful integration exercises Central PostgreSQL and **at least two Tenant PostgreSQL databases** with actual middleware, guards, sessions and database permissions.

| Test area | Required behavior/evidence |
| --- | --- |
| Response and serialization | The shared success/error envelope, camelCase nested fields, 201/204 behavior, file responses, stable `errorCode` and `X-Request-ID` across meaningful failure cases |
| Validation and locale | Laravel Form Requests, Arabic/English messages, nested field errors, safe exception rendering, non-disclosing 404/403 cases |
| Spatie/query contracts | Runtime compatibility verified; allowed and rejected filters/sorts, alias mapping, bounded pagination, no unknown-filter widening and safe Search |
| Authentication and CSRF | First-party Next.js/Sanctum cookie flow, trusted proxy/host settings, guard separation, logout/reset invalidation, cross-store customer session rejection |
| Tenant/Location/ownership | Unauthorized cross-tenant IDs, routes, files, searches, exports, cache and Location access all fail closed; multi-tab explicit scoping works |
| Revocation | Removed/suspended memberships and permissions stop new Tenant access despite existing browser sessions or cached grants |
| Idempotency and concurrency | Concurrent identical keys, changed payload, request timeout/retry, different keys targeting one business effect, repeated refunds/collections/stock updates |
| Jobs and Outbox | Retry, duplicate event, correct Tenant context and worker cleanup without duplicated financial/stock effects |
| Rate limiting and abuse | Layered policy keys, HTTP 429/`Retry-After`, shared IP cases, login/recovery and guest endpoint abuse tests |
| OpenAPI/consumer compatibility | Actual endpoint shapes and error codes match approved contracts; backward compatibility holds through deployments |
| Operational and Pilot gates | Security audit, secrets, off-VPS verified backup/restore, approved legal/data obligations, measured performance, safe deployment and sign-off |

**Release blockers** include cross-tenant leakage, meaningful authorization bypass, revocation failures and duplicate critical money/stock effects. The existing BA-16 backup/recovery architectural selection remains **DEFERRED**, while verified off-VPS backups and restoration remain **mandatory before a live merchant Pilot**. Stancl's Laravel 13/PostgreSQL/session/Horizon integration is still **PENDING VALIDATION**. No implementation, CI, performance or Pilot readiness is implied by publishing this Markdown file.

## 14. MVP Boundaries, Deferred Details and Next Steps

**Fixed architectural direction:** one Laravel REST JSON API, four isolated route surfaces, camelCase wire contracts, the `ApiResponse` pattern, server `X-Request-ID` header, Laravel Form Request validation, Arabic/English messages and stable error identifiers, Spatie query conventions conditionally, PostgreSQL-first search, targeted idempotency, OpenAPI-first contracts, native Sanctum security and layered Redis rate limits.

**Pending implementation-level choices:** final route paths/guards/permission keys and policies; request/response DTOs; accepted `HttpStatusCode` enum additions; final field-level error-code registry; fallback locale; paginator default/max and total-count tradeoffs; exact Spatie/PHP/Laravel Composer compatibility; actual filtering aliases/search semantics; CSRF 419 client handling; session-proxy configuration; `X-Request-ID` middleware/log propagation; durable idempotency record design and TTL; rate-limit values; OpenAPI generation/storage tooling; test fixture coverage; operations and incident response.

These are assigned to API/Security Standards, Foundation integration spikes and **Feature Specs**, without silently modifying business decisions.

**Next architecture area:** Transactions & Consistency Architecture (proposed `docs/01-architecture/transaction-consistency-architecture.md`), with the scope of tenant-local transaction ownership, concurrency, durable idempotency, Outbox, cross-database coordination and failure reconciliation. Naming and future creation remain subject to explicit user approval.

---

## 15. Final Approval and Implementation Boundary — API-08 (A)

**APPROVED: Lightweight API Architecture Baseline, API-01 through API-08.**

This approval authorizes documenting the agreed architectural direction only. It is **not** a claim that `Spatie` or `Stancl` have been installed, tested or accepted by Composer, that OpenAPI files have been generated, or that endpoints, middleware, authorization checks, security gates or Pilot readiness are implemented.

**No application implementation, deployment, production promotion, new package installation or changes to other repository files are authorized by this document.**
