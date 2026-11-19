# E-commerce SaaS V2 — API Standards

**Version:** 1.0 — Approved Engineering Baseline  
**Date:** 2026-11-19  
**Repository:** Elmo-Tech/ecommerce_v2_api  
**Branch:** development  
**Target path:** docs/02-standards/api-standards.md  
**Approval status:** AS-01…AS-09 approved individually; AS-10 is the final documentation/review step authorized by the owner's prior instruction to publish after discussion.  
**Implementation status:** DOCUMENTATION ONLY — NOT IMPLEMENTED, INSTALLED, TESTED OR DEPLOYED.

> **Read before coding:** These are requirements for future Laravel features, not evidence of running routes, configured security or production readiness. This repository currently has approved planning documentation, not a verified running Laravel backend. D-201 remains OPEN; BA-16 Backup/Restore remains DEFERRED; BA-30 G3 remains BLOCKED for a live Pilot.

---

## 1. Purpose, Scope, Authority and References

### 1.1 Purpose

Standardize the HTTP boundary of E-commerce SaaS V2: **Laravel 13 API-first Modular Monolith**, **Next.js**, a Central PostgreSQL database and isolated PostgreSQL databases per Tenant, Redis/Horizon, authoritative module writes and transactional outbox.

This document defines how the four API surfaces expose resources, validate input, serialize responses, identify their Tenant, paginate and search data, protect idempotency, authorize access, maintain OpenAPI contracts and prove changes safe before merge.

**Four logical API surfaces within one Laravel deployment:**

| Surface | Data and identity boundary |
| --- | --- |
| Platform | Central-only by default; Central workforce identity and Platform permissions |
| Merchant Dashboard | Explicit Tenant route plus Central workforce Session and current Tenant membership, entitlements, role, Location and resource/business checks |
| Public Storefront | Tenant resolved from trusted storefront subdomain; explicitly allowed public/guest operations |
| Authenticated Customer | Same verified storefront Tenant and independently scoped Tenant-local customer Session/guard |

The MVP begins with **one REST JSON API** under **/api/v1**. There are no internal module-to-module HTTP calls; business modules use **Public Contracts** and module-owned state.

### 1.2 Sources of authority

| Reference | Governs |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | OV-approved MVP boundaries and live Pilot gates |
| [D-152 Onward Decision Register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) | Recent business decisions, including D-201 OPEN |
| [Consolidated Handoff v5.48](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) | Earlier approved detailed D-series behavior |
| [Open Decisions and Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | Explicit unresolved decisions and Pilot readiness |
| [API Architecture](../01-architecture/api-architecture.md) | API-01…API-08 approved architectural direction |
| [Code Standards](./code-standards.md) | CS-01…CS-12: Actions, public DTOs, int-backed Enums, exceptions, typing and AI discipline |
| [Backend Architecture](../01-architecture/backend-architecture.md) | Laravel backend, security, runtime, tenancy, test and release baseline |
| [Module Architecture](../01-architecture/module-architecture.md) | One Writer, module-first feature contracts, explicit registry |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Trusted Tenant resolution, Central/tenant database separation and conditional Stancl |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | Sanctum Sessions, Central staff, Tenant-local customers, RBAC, Location and revocation |
| [Data Architecture](../01-architecture/data-architecture.md) | Money, timestamps, indexing, query measurements and safe migrations |
| [Transaction Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | ACID, concurrency, business idempotency and outbox |
| [Queues and Jobs](../01-architecture/queue-jobs-event-processing-standards.md) | Horizon, trusted Tenant context, retries, cleanup, delivery |
| [Commerce Workflow Architecture](../01-architecture/commerce-workflow-architecture.md) | Orders, Inventory, Collections, Fulfillment, Returns and business invariants |
| [Repository Workflow](../WORKFLOW.md) | Proposed branch/PR flow; not proof CI/branch protection is configured |

**Precedence:** approved D-series business rules govern behavior; OV governs MVP scope; architecture baselines govern boundaries; Code and API Standards govern programming discipline. Individual Feature Specs and OpenAPI define exact endpoints, identifiers, enum wire values, permission keys and business error codes. Do not silently invent business details, change module ownership or choose D-201.

### 1.3 Explicit non-goals

No new JWT browser authentication, MFA, custom API Gateway, custom validation/query DSL, independent microservice HTTP boundary, public developer portal, mandatory SDK generation, speculative /api/v2, global shopper directory, generic Saga/transaction engine, payment-gateway integration or external search engine is authorized here.

**Publishing this document does not authorize writing application code, installing Composer packages, editing migrations, configuring CI, merging to main or deploying.**

---

## 2. Approved API Standards Decision Register

| Decision | Approved option | Enforceable direction |
| --- | --- | --- |
| **AS-01** | **A — RESTful Routing + Explicit Tenant in Merchant URL** | Plural resources, explicit business actions; Merchant route selects Tenant, Sanctum Session authenticates user; verify Membership on every request |
| **AS-02** | **A — Centralized ApiResponse + API Resources** | Standard JSON success/failure, real HTTP statuses, optional pagination meta, X-Request-ID header |
| **AS-03** | **A — Laravel Form Requests + Central Error Codes** | Safe approved fields/DTO mapping, initial input validation, domain authorization, localized ar/en errors |
| **AS-04** | **A — Conditional Spatie Query Builder + Controlled Pagination** | Allowlisted camelCase filters/sorts; default perPage **20**, ordinary maximum **100**; appropriate paginate/simple/cursor choices |
| **AS-05** | **A — PostgreSQL-First Search + Measured Optimization** | Exact indexed lookup and controlled ILIKE, targeted pg_trgm/FTS after testing, Arabic/English validation |
| **AS-06** | **A — HTTP + Business Idempotency + PostgreSQL ACID** | Idempotency-Key for appropriate high-risk writes; real domain once-only effect, retries and reconciliation |
| **AS-07** | **A — Sanctum + Tenant Middleware + Layered Authorization + Redis Rate Limiting** | Separate guards, current permission, fail-closed Tenant scope and safe 429 |
| **AS-08** | **A — Lightweight OpenAPI Contract-First + One /api/v1** | Versioned wire contract, reviewed compatibility and focused consumer tests |
| **AS-09** | **A — Automated API + Contract Testing + Risk-Based Release Gates** | Actual isolation, authorization, PostgreSQL concurrency/idempotency, scope-aware PR evidence |
| **AS-10** | **Final review and publication under existing authorization** | Consolidate AS-01…09 and publish comprehensive documentation to development |

**Important refinement:** API Architecture previously described page size 20 and max 100 as proposals. They were **explicitly adopted in AS-04** and are therefore now the default ordinary list API standard. This approval is not proof the framework is configured.

---

## 3. REST Routes, API Surfaces and Tenant Context — AS-01

### 3.1 Route design

Use conventional HTTP verbs for resources, explicit **POST business-action endpoints** when a meaningful state transition has its own permissions, and **plural noun** route segments. Use lower-case **kebab-case** for multiword path segments; application-owned JSON and query keys remain **camelCase**.

**Conceptual Merchant route examples only, not finalized endpoint specifications:**

~~~http
GET    /api/v1/tenants/{tenant}/orders
GET    /api/v1/tenants/{tenant}/orders/{order}
POST   /api/v1/tenants/{tenant}/orders
PATCH  /api/v1/tenants/{tenant}/orders/{order}
POST   /api/v1/tenants/{tenant}/orders/{order}/confirm
POST   /api/v1/tenants/{tenant}/orders/{order}/cancel
~~~

Use GET for reads without business mutation, POST for creation/actions, PATCH for approved partial updates, PUT only when replacement semantics actually apply. A resource DELETE endpoint must **not** silently delete immutable order, stock, collection, refund or audit evidence. Follow approved cancellation/reversal/correction workflows. Avoid procedural create-order/update-order naming as the default and avoid excessive nesting.

### 3.2 Why Merchant Tenant belongs in the route

Merchant Dashboard requests explicitly identify the target Tenant in the URI and authenticate the Central user via Sanctum Session. **The URI is a selector, not a security credential.**

~~~http
GET /api/v1/tenants/store-a/orders
Cookie: <browser-authenticated-session-cookie>
~~~

A user can operate Store A in one tab and Store B in another while using the appropriate authorized Central identity context. **Do not store the authoritative active Tenant in one shared mutable Session, Token claim or static property** that changes when another tab changes stores. A UI last-selected merchant preference is not authorization.

~~~text
Tab A: GET /api/v1/tenants/store-a/orders
  -> current membership Store A -> Tenant A DB -> allowed orders A

Tab B: GET /api/v1/tenants/store-b/orders
  -> current membership Store B -> Tenant B DB -> allowed orders B

Switching Tab B never changes the Tenant addressed by Tab A.
~~~

**Required Merchant request checks, fail closed:**

1. Authenticate Central workforce identity using the appropriate Sanctum first-party Session.
2. Resolve requested Tenant URI selector through trusted Central Tenant Directory.
3. Validate any trusted Host/context agreement; reject mismatches and unavailable Tenants.
4. Verify **current active Central membership** of the user in that Tenant.
5. Verify Tenant state and applicable subscription/feature entitlements.
6. Initialize the **verified Tenant PostgreSQL connection** through the approved integration, after authorization.
7. Check Tenant-local role, permission, Location scope, resource ownership and relevant business approval.
8. Run module-owned Action and **always reset scoped Tenant/connection state** after request, including failure.

A URI Tenant ID is not hidden authentication material. Changing it must **never** grant unauthorized access. Use public slug/opaque route identifier defined by Feature; never expose credentials or raw DB connection settings. A hostile caller can guess URI paths: membership, role and object authorization are mandatory regardless of guessability.

### 3.3 Other surfaces

- **Platform** remains Central-only by default. Platform Admin cannot automatically bypass Tenant business authorization. Explicitly authorized support/Cross-Context workflows require their own scope.
- **Storefront** resolves Tenant from approved trusted subdomain mapped in Central directory; guest sees only eligible public data.
- **Customer** uses the storefront's verified Tenant plus a matching tenant-local customer guard and per-resource ownership; no cross-store shopper identity or session authority.
- **Future integrations:** a proposed server-to-server tenant-bound credential may suit an approved future integration but does **not** replace the current Sanctum browser Session model or authorize a new feature.

### 3.4 Module route registration

Each module owns its HTTP routes and features registered through approved explicit Module Registry/Service Providers. Modules do not call each other's own HTTP endpoints inside the same Monolith; use published in-process Public Contracts. Controllers map HTTP to validated Action DTOs and API Resources, not into direct cross-module business writes.

---

## 4. Standard API Responses and HTTP Status Codes — AS-02

### 4.1 One JSON envelope

Preserve the approved **App\Helpers\ApiResponse** pattern and **App\Enums\HttpStatusCode** direction, with centralized exception mapping, Laravel API Resources and stable JSON serialization.

**Successful completed resource response — illustrative only:**

~~~json
{
  "success": true,
  "message": "تم جلب الطلب بنجاح",
  "data": {
    "id": 105,
    "orderNumber": "ORD-105",
    "totalAmount": 150000,
    "status": 1
  }
}
~~~

The total is an **integer monetary minor-unit amount**, not 150000 EGP. The status number is **not** an approved actual Order Enum value; each Feature sets real state meanings and its public wire contract.

**Validation failure:**

~~~http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json
X-Request-ID: req-example-123
~~~

~~~json
{
  "success": false,
  "message": "يرجى مراجعة البيانات المدخلة",
  "errorCode": "VALIDATION_FAILED",
  "errors": {
    "customerName": ["اسم العميل مطلوب"],
    "items.0.quantity": ["الكمية يجب أن تكون أكبر من صفر"]
  }
}
~~~

**Business conflict:**

~~~json
{
  "success": false,
  "message": "الكمية المطلوبة غير متوفرة",
  "errorCode": "INSUFFICIENT_STOCK",
  "errors": []
}
~~~

That business code is illustrative; exact Feature status/code mapping is to be specified. Use stable **machine-readable errorCode**, localized message and an empty **errors** array when no field errors. Use a field-keyed object for Validation with **camelCase** field names even in nested paths.

### 4.2 Paginated success

~~~json
{
  "success": true,
  "message": "تم جلب الطلبات بنجاح",
  "data": [
    {"id": 105, "orderNumber": "ORD-105"},
    {"id": 106, "orderNumber": "ORD-106"}
  ],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "lastPage": 8,
    "totalItems": 160
  }
}
~~~

Numbers are example data. **meta** is optional and reports only information **actually computed**; do not invent totalItems/lastPage when using simple/cursor pagination without count. A Feature's OpenAPI contract defines its honest alternate cursor/hasMore metadata.

### 4.3 HTTP status table

| HTTP | Correct use |
| --- | --- |
| **200** | Completed successful read/operation with representation |
| **201** | Resource actually created |
| **202** | Operation accepted but genuinely pending asynchronous execution; not final business success |
| **204** | Success with **no body**, not wrapped JSON |
| **400** | Invalid general HTTP request not better classified |
| **401** | Missing/invalid authentication session |
| **403** | Authenticated but unauthorized |
| **404** | Not found or deliberately hidden existence when isolation requires |
| **409** | Resource/business conflict, including same-key different payload |
| **422** | Form/query/filter/sort validation failure |
| **429** | Rate limit exceeded with meaningful Retry-After where supported |
| **500** | Unexpected internal fault; safe message without SQL/secrets |
| **503** | Genuine temporary service unavailable if applicable |

Do not send HTTP 200 and success=false for a failure. Do not claim a money/stock change completed before actual authority/commit. **204 has no JSON body**; downloads/files use normal file responses and correct headers. Laravel's common CSRF HTTP 419 behavior is **nonstandard**, so finalize the compatible Next.js handling in Auth implementation without weakening CSRF.

### 4.4 Correlation headers

Middleware creates trusted unique **X-Request-ID** for each HTTP attempt; emit the header on successful/error responses where feasible, and attach safe ID to logs. It does **not** go into JSON body by default. A client-supplied request trace needs explicit validation; do not treat it as trusted authorization.

**X-Request-ID**, **Idempotency-Key**, **Business Operation ID**, **Job/Event ID** and **Tenant selection** are separate concepts.

### 4.5 Serialization details

All application-owned request/response JSON, nested keys, errors, query aliases and metadata are **camelCase**. Internal PHP/database may use snake_case; only approved API Resources/DTOs/mappers expose the wire representation.

Approved **CS-05**: all project-defined PHP Enums are **int-backed**, with PostgreSQL **SMALLINT** for multi-state Enum storage and **BOOLEAN** for genuine true/false flags. **This does not automatically force an int wire value** for any endpoint whose explicit Feature/OpenAPI contract specifies something else; map safely and review compatibility. Final money uses currency-aware **BIGINT integer minor units**; UTC timestamps are represented by reviewed time/locale conventions.

---

## 5. Request Validation, Error Codes and Localization — AS-03

### 5.1 Responsibility map

| Layer | Role |
| --- | --- |
| Middleware | Auth/CSRF, trusted Tenant and early request limits |
| Laravel Form Request | Input format, schema, types, required/allowed fields and initial authorization |
| Application DTO/Action | Deliberate input mapping, current resource authorization, orchestration |
| Module Domain Rules | Actual stock, price, financial state, eligibility and transition invariants |
| Central Exception Handler | Stable HTTP status, ApiResponse and translated error messages |

Validating quantity >0 does **not** prove available stock. A valid Tenant selector, role name, discount or isPaid boolean supplied by a client does **not** grant authority.

### 5.2 Form Request illustration

~~~php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;

final class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:productId,quantity'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
~~~

This snippet is **illustrative, not a final approved order schema**; exact identifiers/UOM quantities, permission and mutation path are Feature-owned. The backend must handle payload size, nested depth, limits and authorized resource references before business effects.

### 5.3 Mass-assignment safeguards

Never pass unrestricted request()->all() to Eloquent or treat client-supplied **tenantId, isPaid, finalPrice, discount, approval, role, location permission or status** as authoritative.

~~~json
{
  "customerId": 15,
  "discount": 200,
  "isPaid": true,
  "tenantId": 8
}
~~~

Only **allowed validated** fields map to typed Action DTOs. For sensitive/contract-strict writes, **reject unexpected extra fields** with reviewed validation behavior; other Feature contracts may deliberately define handling of unknown fields. Do not blindly ignore fields where that could cause privileges or unintended defaults.

The owning Action rechecks **current** Tenant, Location, business state and authorization at the point of any critical effect.

### 5.4 Compact shared error-code vocabulary

| Identifier | Purpose | Typical HTTP |
| --- | --- | --- |
| **VALIDATION_FAILED** | Invalid submitted fields | 422 |
| **UNAUTHENTICATED** | No authenticated session | 401 |
| **ACCESS_DENIED** | Insufficient authorization | 403 |
| **RESOURCE_NOT_FOUND** | Missing or protected resource | 404 |
| **RESOURCE_CONFLICT** | Conflicting state/business effect | 409 |
| **FEATURE_UNAVAILABLE** | Disabled/untitled feature | Feature-defined safe code |
| **INVALID_FILTER** | Unknown/disallowed filter | 422 |
| **INVALID_SORT** | Unknown/disallowed sort | 422 |
| **RATE_LIMIT_EXCEEDED** | Rate limit exceeded | 429 |
| **INTERNAL_ERROR** | Unexpected failure | 500 |

These names derive from the API Architecture's **proposed small shared registry**; this file adopts the convention but does **not** claim a registry has been implemented or every mapping finalized. Domain-specific codes stay in the provider Module/Feature, not a global list of hundreds of exceptions.

### 5.5 Arabic/English messages

Use Laravel translations and validated **Accept-Language ar/en**. Error codes/field names stay stable across languages; messages change. The exact fallback locale is **TO SPECIFY** in Localization/Foundation; Arabic was suggested for Egypt Pilot but is not silently configured by this file.

No raw SQL, stack traces, other Tenant's resource existence, payment credentials or private contact data in validation errors.

---

## 6. Filtering, Sorting, Pagination and Limits — AS-04

### 6.1 Conditional dependency

Use **Spatie Laravel Query Builder v7** if **actual PHP/Laravel/Composer compatibility** is verified. The API Architecture explicitly cautions about Laravel 13 and PHP 8.4 compatibility. Do **not** assert compatibility without running it, silently force versions or introduce a competing generic Query DSL. If incompatible, document the finding and obtain approval for the smallest supported alternative.

### 6.2 Agreed query syntax

~~~http
GET /api/v1/tenants/store-a/orders?filter[status]=1&filter[locationId]=5&sort=-createdAt&page=1&perPage=20
~~~

This is an example only. Values for status, Location and real paths must follow approved Feature Enum/wire types and permissions.

- **filter[fieldName]** with allowed **camelCase** client aliases.
- **sort=createdAt** ascending, **sort=-createdAt** descending, only allowlisted.
- **page** one-based for page navigation, **perPage** a validated positive page size.
- **Default perPage=20**, **ordinary maximum perPage=100** (AS-04 approved values). Feature-specific lower maximum allowed with explicit contract.
- Unknown/invalid filters and sorts: safe **HTTP 422** (INVALID_FILTER/INVALID_SORT), **never silently ignore** when that might widen results.
- Never permit client-controlled raw SQL clauses, arbitrary columns or unlimited nested includes.
- Translate allowed aliases safely to actual internal PostgreSQL columns.
- Scope the underlying Eloquent query by trusted Tenant/Location/customer ownership **before applying user filters**, including relation/include/aggregate scope.
- Supplying filter[locationId] never grants Location permission.

### 6.3 Pagination selection

| Method | Good fit | Limit |
| --- | --- | --- |
| **paginate()** | Dashboard pages with total record/page counts and random navigation | COUNT/OFFSET expensive at scale |
| **simplePaginate()** | Sequential pages without total-count requirement | Cannot promise totalItems/lastPage |
| **cursorPaginate()** | Very large lists with stable deterministic unique/tie-broken order | No arbitrary page jump; indexed order needed |
| **Bounded Horizon export job** | Heavy CSV/Excel or large authorized reports | Separate flow, not huge perPage |

Validate nonpositive/malformed/greater-than-100 perPage values and **reject** rather than running unlimited data queries. A cursor needs stable unique-enough ordering (e.g. reviewed unique tie-breaker), indexes and compatible OpenAPI cursor metadata; no invented total count.

Always use authorized selected columns, eager loading when useful, reasonable query limits and query-plan measurement. Do not index every field speculatively or run expensive COUNT universally.

### 6.4 Performance and consistency

Pagination is not a data-isolation primitive. Filters must never override authorization. Large/changing result sets can shift between independent pages; define where a stable snapshot is needed, rather than suggesting cursor navigation guarantees one.

---

## 7. PostgreSQL-First Search — AS-05

### 7.1 Search patterns

| Search type | Suggested method |
| --- | --- |
| SKU, barcode, exact order reference | Exact indexed match |
| Reference prefix | Controlled prefix match and fitting index/operator strategy |
| Names and basic text | Bounded ILIKE with measured query plan |
| Expensive substring matching | Targeted pg_trgm with GIN/GiST after justified tests |
| Approved long-text search | PostgreSQL Full-Text Search if feature and corpus warrant |
| Customer phone/identifier | Exact scope-authorized match with no PII leakage |

Expose only approved fields, for example **filter[search]**, not arbitrary SQL columns. Apply verified Tenant, Location, customer ownership and field visibility restrictions, including search results and related records.

### 7.2 Arabic and English

Test representative real Arabic/English product names, spelling variations and ranking; do **not** assume default FTS automatically gives Arabic morphological equivalence. Optional character normalization, separate search columns, token rules and trigram indexes require a measured use case.

### 7.3 Optimization sequence

1. Start with correct scoped and bounded PostgreSQL queries.
2. Measure representative Tenant dataset using **EXPLAIN ANALYZE** or suitable query timings.
3. Add targeted B-tree/composite index when access patterns justify it.
4. Consider pg_trgm/FTS only with test evidence and acceptable write/index overhead.
5. Propose a specialized Search service later **only if measured need** exceeds PostgreSQL-first baseline.

No Elasticsearch/Meilisearch is part of the first Pilot baseline. A future alternative must still honor Tenant permissions and deal safely with index synchronization lag.

---

## 8. HTTP Idempotency, Domain Idempotency and Safe Retries — AS-06

### 8.1 Distinct safety layers

**HTTP Idempotency-Key** protects a selected logical write from being performed twice if an HTTP response was lost. **Business Idempotency** protects one physical/financial effect even if two different keys or Horizon retries target it. **PostgreSQL** provides the authoritative transactional locks, conditional updates and unique constraints.

These are not interchangeable with disabling the button in Next.js or using a Redis TTL lock.

### 8.2 Example

~~~http
POST /api/v1/tenants/store-a/orders
Content-Type: application/json
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
~~~

Frontend creates a new key for a new logical operation, keeps it for retries of that exact operation, and never reuses it for a different sale. Feature decides which endpoints require it (not every GET/update), with high-risk order creation, Direct Sale, real Collection and Refund as prime examples.

Durable operation identity must include **validated Tenant/Central scope**, operation type, relevant actor/contract boundaries, canonical request meaning, protected state and business outcome reference. Do not trust a client-supplied Tenant as the binding scope. A small operation table or an existing authoritative business reference may meet the requirement; no universal Idempotency Framework is approved.

### 8.3 Retry matrix

| Scenario | Expected safe behavior |
| --- | --- |
| Same key + same canonical input + completed | Revalidate current permissions; safely replay authorized outcome, no new effect |
| Same key + different canonical input | **409 Conflict** |
| Same key while in progress | Explicit pending/conflict behavior; **never parallel second effect** |
| Different keys target one physical event | Domain state/unique reference prevents duplicate |
| Different genuine approved partial Collections/Refunds on same Order | Permit if amount/state/approval constraints satisfied; use **real event reference**, not just order ID |
| Independent new purchases with identical basket | Do not suppress merely because payloads look alike |
| Proven rolled-back transient DB failure | Bounded retry after safety classification |
| Network timeout after possible commit | Inspect durable operation state before retry |
| Unknown external payment/refund result | **Reconcile real evidence first**, never blindly repeat transaction |

Key retention/TTL, canonical fingerprint, inflight HTTP code, replay details and schema are **feature/Transaction Standards implementation decisions**. Expiring an HTTP key must never enable duplicate physical dispatch, stock movement or real money effect.

### 8.4 Transactions and Outbox

For critical writes in one Tenant Database, owning Use-Case Action coordinates **one short local ACID transaction on correct validated connection**. Each Module still owns its own writes: e.g. Orders calls Inventory's published contract to reserve stock, not direct StockModel mutation. Insert Outbox intent **in same local transaction**, then publish after commit.

Do not hold a DB transaction across network/provider call or inter-Tenant/Central connections. Cross-context workflows follow durable staged coordination, not fictitious distributed ACID.

**X-Request-ID** is a per-attempt trace; **Idempotency-Key** is logical retry identity; **Business Operation Reference** identifies the actual effect. They must not be treated as interchangeable.

---

## 9. Authentication, Authorization and API Security — AS-07

### 9.1 Browser authentication topology

Use **Laravel Sanctum first-party Sessions + CSRF**, with Next.js calls through approved **same-origin API reverse-proxy topology**, even if Next.js and Laravel deploy separately.

- HTTPS and **Secure**, **HttpOnly** host-only **session** cookies; suitable SameSite starting with Lax.
- CSRF token/cookie accessible as required by Sanctum client flow, but never expose session identifier to JS.
- Explicitly trusted proxies/hosts/stateful origins; reject spoofed forwarded headers and unapproved Host/Tenant mapping.
- Regenerate session on login and invalidate on logout; test expiry and frontend recovery.
- No JWT browser auth, global parent-domain auth cookie, implicit cross-host SSO or custom IAM framework introduced.

### 9.2 Different guards and contexts

| Surface | Correct identity |
| --- | --- |
| Platform | Central workforce identity with explicit Platform authorization |
| Merchant | Central workforce identity + verified Tenant route membership + tenant-local RBAC/Location/resource checks |
| Public Storefront | Guest scope under trusted Tenant subdomain |
| Authenticated Customer | Tenant-local customer account/session and tenant-specific resource ownership |

Customer Session cannot be used as workforce authentication, nor replayed across Tenants. Host-only cookies alone don't prove cross-tenant replay safety; validate Stancl **ScopeSessions** behavior and proper guard/connection isolation with actual tests.

### 9.3 Layered authorization

Every protected endpoint checks relevant **active Central Membership**, Tenant lifecycle, subscription entitlement, local role, Location, object ownership and operation/approval thresholds. A general orders.view permission does not authorize every branch or private file. Platform Super Admin is not unconditional Tenant business authority.

Permissions apply to lists, reads, writes, exports, private files, background jobs, Search and financial approvals, not just Next.js buttons.

**Revocation must deny new requests** even when an existing Central Session remains valid or permission caches are stale. Resource operations must revalidate at sensitive action boundary.

### 9.4 Rate limiting

Use Laravel native **Named Rate Limiters backed by Redis**, keyed to trusted scope.

| API class | Suggested limiter scope |
| --- | --- |
| Login / password recovery | Verified IP and safe account identifier |
| Platform | Central actor and endpoint class |
| Merchant | Authorized actor + Tenant + endpoint |
| Public storefront | Verified IP + Tenant + endpoint |
| Customer | Tenant + customer ID + endpoint |
| High-risk checkout/upload/export/refund | Additional per-operation budgets and security rules |

Numeric limits/lockout periods are **not approved yet**; define them with real traffic/tests in Security/Feature Standards. Respect shared IPs and prevent one noisy Tenant consuming unrelated Tenant budget. Never trust arbitrary X-Forwarded-For headers outside configured proxies.

On throttle: **HTTP 429**, standard errorCode **RATE_LIMIT_EXCEEDED**, meaningful **Retry-After** when available, and **X-Request-ID**. Next.js must not retry high-risk writes without the established idempotency contract.

### 9.5 Additional safeguards

Validate upload/request sizes, private file access, content types, allowed fields, bounded query work and CSRF/Origin controls. Use least-privilege PostgreSQL roles and safe SQL binding. Never expose session secrets, passwords, private customer/payment data or raw stack traces in logs/API responses. No mandatory WAF, CAPTCHA, SIEM or paid gateway from day one.

---

## 10. OpenAPI, Versioning and Backward Compatibility — AS-08

### 10.1 Lightweight contract-first

Each Feature defines its reviewed OpenAPI **before consumers rely on the endpoint**, covering:

1. Method, path, route surface, intended business operation.
2. Authentication guard, Tenant resolution, allowed role/Location and resource ownership.
3. Request JSON/query/body fields in camelCase, limits and optional/required semantics.
4. Response schemas, status codes, pagination metadata and localized errors.
5. Stable errorCode and CSRF/rate-limit behavior where applicable.
6. Allowed filters/sorts/search aliases and specified currency/enum wire values.
7. X-Request-ID header and Idempotency-Key semantics when required.
8. Business conflicts and relevant current state/approval conditions.
9. Negative/authorization cases, compatibility and consumer tests.

Keep Feature source contracts close to specs with one coherent reviewed OpenAPI artifact. Tool, generator/manual workflow and canonical OpenAPI file paths remain **Foundation choices**; no installed generator or generated TypeScript SDK is implied.

### 10.2 One version

The initial public interface is **/api/v1**. Back-end release numbers and Git branches are independent of API version. Compatible additions do not automatically create /api/v2; incompatible changes require review of graceful migration or genuine versioning.

### 10.3 Compatibility matrix

| Change | Expected review |
| --- | --- |
| New independent route | Usually additive |
| New optional response field | Usually additive but check strict clients |
| New optional request field | Usually additive if defaults safe |
| Remove/rename a used field | **Breaking** |
| Change field type, Money scale or public identifier semantics | **Breaking** |
| Make optional field required | **Breaking** |
| Change errorCode/HTTP status contract | Potentially breaking |
| Add/change public Enum case/value | Review strict client behavior and older workers |
| Authorization/Location/visibility change | Security and consumer review; may change behavior |
| Change pagination/filter/sort semantics | Compatibility/UX review |

Where possible deploy additive backend support first, update Next.js consumers, verify supported clients and complete reviewed deprecation. Coordinate PostgreSQL Tenant migrations/Blue-Green and old/new worker compatibility. No mandatory public developer portal or SDK generation in MVP.

### 10.4 Example additive response

~~~json
{
  "success": true,
  "message": "تم جلب المنتج",
  "data": {
    "id": 10,
    "name": "Samsung A55",
    "price": 1500000,
    "sku": "SAM-A55"
  }
}
~~~

Optional sku is typically additive; removing name or changing price from integer minor units to string would be breaking. Never force an internal int-backed Enum into a different public wire shape without reviewing the existing contract.

---

## 11. API Testing and Risk-Based Release Gates — AS-09

### 11.1 Expected tests

Use actual Laravel API Feature/Integration tests and contract checks against reviewed OpenAPI, with **PHPUnit or Pest** chosen by Foundation. The test runner, CI, Composer packages and Branch Protection are **NOT VERIFIED as configured**.

| Group | Required evidence |
| --- | --- |
| Functional | Happy path and expected business failure with correct status/result |
| Validation | Invalid schema, nested paths, extra/forbidden fields and untrusted inputs |
| Response contract | ApiResponse success/errors, 201/204/file exceptions, camelCase and X-Request-ID |
| Localization | ar/en messages and stable errorCode/field names |
| Authentication/CSRF | 401, expired/invalid Session, CSRF handling, logout, guard separation |
| Authorization | Tenant membership, role, Location, resource and sensitive approvals |
| Tenant isolation | Tenant A cannot read/write B routes, search, files, exports or caches |
| Multi-tab | One authorized user addresses two Tenants from separate tabs safely |
| Revocation | Revoked current membership/permission denied despite valid Session |
| Query | Allowed/rejected filters/sorts, 422, default 20/max 100, pagination and truthful meta |
| Search | Trusted Tenant/Location scope and Arabic/English representative data |
| Idempotency | Same key/same payload, different payload, inflight duplicate, lost response |
| Business duplicates | Same physical stock/financial effect through different keys/jobs |
| PostgreSQL concurrency | Last unit competing requests with independent DB sessions and correct locks |
| Horizon/Outbox | Duplicate/reordered delivery, retry, Tenant A -> B -> Central cleanup |
| Rate limiting | 429/Retry-After, correct IP/user/Tenant budgets and NAT shared IP case |
| Contract compatibility | Next.js supported consumer does not break due to changed schema |
| Security/operational | Privilege boundaries, secrets, recoverability and measured release evidence |

### 11.2 Actual database fixtures

Critical tenancy integration requires **at least one Central PostgreSQL database and two isolated Tenant PostgreSQL databases** with actual guards/context/permissions. One SQLite/in-memory shared DB with tenant_id labels **does not prove** physical isolation or PostgreSQL locking correctness.

Test sequential and overlapping Tenant A/B requests plus invalid contexts, then **Tenant A -> Tenant B -> Central** through reused Horizon Worker with normal and failing jobs.

### 11.3 High-risk blocking business scenarios

- Two independent transactions compete for the last stock unit: cannot oversell.
- Confirmed order cannot persist if its required reservation fails.
- Duplicate dispatch, physical movement, Collection or Refund cannot reapply actual effect.
- Legitimate distinct partial Collections/Refunds must remain possible within approved caps and permissions.
- Unknown external payment/refund must be reconciled before retry.
- Cross-Tenant/Location data exposure and unauthorized customer records are prohibited.
- Duplicate/retried Outbox/Jobs must not cause money/stock inconsistencies.
- Schema compatibility during rolling or Blue-Green workers must be tested for relevant releases.

**Mocks alone do not prove critical DB concurrency.** Use real PostgreSQL, locks and unique constraints. Heavy load/failure/recovery suites are proportionate and may run at integration or pre-Pilot gates rather than every small read-only PR.

### 11.4 PR requirements and blockers

Each changed API PR needs scope and Feature Spec, altered routes/DTOs/error schema, permission/tenant impact, OpenAPI compatibility evidence, actual test commands/results, open limitations and deployment/migration implications. Report unrun tests as unrun, not Passed.

**Block merge/release** when change permits Cross-Tenant leakage, authorization/revocation failure, overselling, duplicate critical financial/stock effects, invalid order-reservation commit, lost required Outbox durability or silent rewriting of historical finance.

CI/Branch Protection, actual package versions, test levels, performance thresholds and real Pilot readiness are implementation decisions/evidence; no success is implied by publishing these docs.

---

## 12. Practical Laravel Patterns and Anti-Patterns

All following paths, types, examples and flows are **illustrative**, not live implementation or final Feature approval.

### 12.1 Correct HTTP to business flow

~~~text
GET /api/v1/tenants/{tenant}/orders
  -> Central Sanctum auth
  -> Tenant resolve from Central Directory
  -> Current membership + Entitlement
  -> Initialize authorized Tenant DB
  -> Tenant local RBAC/Location/resource checks
  -> Orders-owned scoped query
  -> OrderResource + ApiResponse
  -> Clear Tenant context
~~~

### 12.2 Correct risk-aware write

~~~text
POST /api/v1/tenants/{tenant}/orders
  -> Same trusted auth/Tenant/Location context
  -> Form Request + approved CreateOrderData mapping
  -> Stable Idempotency-Key claim / reject conflicting attempt
  -> Orders Application Action
  -> validated Tenant ACID transaction
     -> Pricing Contract
     -> Inventory Contract when required by approved business path
     -> Orders-owned state
     -> Tenant Outbox intent for required downstream events
  -> COMMIT
  -> Correct HTTP response (safe replay if previous response lost)
  -> Horizon processes post-commit work idempotently
~~~

No transaction crosses an external provider call or user action. Online/Manual/Direct Sale paths follow their **distinct approved business rules**; the example is not a universal reservation flow.

### 12.3 Anti-patterns

~~~text
BAD: Tenant chosen only from mutable active_tenant in shared Session.
BAD: User changes URI Tenant and backend trusts it without Membership.
BAD: Browser JWT claim treated as permanent role after revocation.
BAD: Orders Controller directly decrements Inventory Eloquent table.
BAD: request()->all() sets isPaid=true or client supplied finalPrice.
BAD: Unknown query filter silently ignored, widening results.
BAD: perPage=100000 used to export private customer records.
BAD: Money response reports 150000 but client guesses currency exponent.
BAD: CSRF disabled to make Next.js Session work.
BAD: Refund retried after unknown provider timeout.
BAD: Controller always returns HTTP 200 even when stock fails.
BAD: Different Tenant user data leaked in error/CSV/private file.
BAD: AI claims tests/CI passed without execution.
~~~

---

## 13. API Threats and Operational Risk Controls

| Risk | Failure | Mandatory safeguard |
| --- | --- | --- |
| Cross-Tenant IDOR | Guessing route Tenant/resource | Verified Membership, scoped DB + resource policy |
| Multi-tab drift | Tab B switching shared active Tenant changes A | Explicit Tenant route on every Merchant request |
| Wrong DB after Worker reuse | Stale Tenant context or connection | Reinitialize verified context, cleanup/fail closed |
| Privilege revocation ignored | Session still active | Current Membership/Permission checks per request |
| Over-posting | isPaid, tenantId, final totals in body | Validation, allowlisted typed DTO and server-owned state |
| Search/filter leakage | Unscoped query or dropped bad filter | Trusted base query and allowlisted fail-closed filters |
| Query overload | Unbounded COUNT/ILIKE/pagination | 20/100 list bounds, measured indexes, async exports |
| Duplicate charge/stock | Retries after uncertain completion | HTTP+business idempotency, DB constraints and reconciliation |
| Session/CSRF misuse | Browser cross-site action | Sanctum CSRF, host-only cookie, trusted proxy/origin |
| API contract breaking | Next.js expects old fields/status | Reviewed OpenAPI and contract tests |
| Sensitive log exposure | Credentials/PII in traces | Safe structured logging and separate audited business truth |
| Premature Pilot | No validated off-VPS restore | BA-16 still DEFERRED and BA-30 G3 BLOCKED |

No speculative third-party service, unmeasured target or hidden “fix” is approved by this table.

---

## 14. AS-10 — Final Architecture Consistency Review

### 14.1 Compatibility with existing decisions

| Baseline | Review |
| --- | --- |
| API-01 and IA-03 | **Compatible:** AS-01 explicit Merchant route Tenant, current Membership, no shared active Tenant state |
| API-02 and CS-05/06 | **Compatible:** ApiResponse, camelCase and internal int-backed Enums independent of wire contract |
| API-03 and DA-05 / BA-26 | **Compatible:** conditional Spatie, AS-04 20/100, PostgreSQL-first measured search |
| API-04 and CS-03/04/06 | **Compatible:** Form Requests, DTOs, focused Actions and central exceptions |
| API-05 and TC-03/04 | **Compatible:** idempotency and PostgreSQL ACID/Outbox plus permanent business guards |
| API-06 and BA-11 | **Compatible:** OpenAPI reviewed contract with single /api/v1 |
| API-07 and IA/TA | **Compatible:** Sanctum/CSRF, Tenant/customer guard isolation and scoped Redis rate limits |
| BA-25 / MA-08 / CS-09 | **Compatible:** risk-based automated tests and code PR evidence |
| CW-01…CW-08 and D-series | **Compatible:** no invented online/Manual/Direct Sale or actual Collection/Refund rules |
| D-201 / BA-16 / BA-30 | **Unchanged:** OPEN / DEFERRED / G3 BLOCKED |

**Clarification:** earlier API Architecture treated page default 20 / max 100 as nonbinding examples. The user explicitly **approved those numbers in AS-04**; this document refines that one detail. Other previously OPEN implementation decisions remain open.

### 14.2 Pending decisions and validations

| Pending item | Status / owner |
| --- | --- |
| Actual route paths, resource identifier formats, guards and permission keys | Feature Spec/OpenAPI |
| Exact DTO and API Resource fields and public Enum status representation | Feature Contract |
| Exact shared error registry, feature codes and fallback locale | API/Localization Standards |
| Conditional Spatie v7 + Laravel 13 + PHP version compatibility | Runtime Composer verification |
| Stancl, Sanctum, CSRF, sessions, proxy, Redis/Horizon integration | Foundation real integration tests |
| Exact middleware alias/order and CSRF 419 frontend behavior | Foundation/Auth Standards |
| Detailed search indexes, allowed aliases, Arabic normalization and cursor meta | Feature/Database Standards |
| Idempotency record design, canonical hashes, TTL and replay status | Transaction/Idempotency Standard + Feature |
| Numeric rate limits, burst/lockout policy | Security Standards + measured load |
| Test runner, OpenAPI tooling, CI and branch protection | Foundation/Testing |
| Operational telemetry, SLO targets, real capacity/recovery results | Infrastructure/Observability |
| D-201 fulfillment alternative-location behavior | **OPEN**, requires separate business approval |
| BA-16 offsite backup/recovery strategy | **DEFERRED**, mandatory proven recovery before Pilot |
| BA-30 G3 live Pilot recovery readiness | **BLOCKED**, documentation does not satisfy gate |

### 14.3 Permission to publish

The owner approved **AS-01 through AS-09** individually and previously explicitly instructed to **write the document to GitHub after the discussion ends**. AS-10 is the final consolidation, source cross-check and authorized documentation upload on **development**. There is **no additional permission** to implement backend code, run migrations, install dependencies, deploy, modify production or promote to main.

---

## 15. Developer and AI Agent Pre-PR Checklist

- [ ] Feature Spec and current approved D/OV/API/Code/Module/Transaction references read.
- [ ] Correct REST route, API surface, business Action and module-owned Controller/Action.
- [ ] Merchant Tenant always explicit in URI; no authoritative active-Tenant shared Session state.
- [ ] Tenant is resolved through Central Directory, and current Membership/Entitlement verified.
- [ ] Local RBAC, Location, resource, customer and sensitive business permissions enforced.
- [ ] Separate Platform/Staff/Customer/Public guards and trusted host/subdomain resolution.
- [ ] Sanctum first-party session, CSRF and secure cookies/proxies configured and actually tested.
- [ ] Form Request validates allowed input and maps only safe fields into Action DTO.
- [ ] ApiResponse and API Resources serialize camelCase with correct status.
- [ ] Errors have stable code, ar/en messages, safe field errors and no sensitive disclosure.
- [ ] X-Request-ID in headers, distinct from Idempotency-Key and Business Operation reference.
- [ ] Filtering/sorting allowlisted with 422 for invalid; underlying query fully authorized.
- [ ] Ordinary default perPage=20, max=100; no fabricated pagination totals.
- [ ] Search bounded, properly scoped, Arabic/English behavior tested where required.
- [ ] Critical writes protected by durable HTTP retry identity plus real domain uniqueness.
- [ ] Actual PostgreSQL tests for concurrent stock/collection/refund effects where relevant.
- [ ] Outbox recorded with authoritative local ACID writes; asynchronous jobs are idempotent.
- [ ] Revocation, Tenant A/B multi-tab, Tenant A->B->Central Worker cleanup tested.
- [ ] OpenAPI updated and consumer compatibility checked for wire changes.
- [ ] Rate limit responses and trusted identity keys tested where applicable.
- [ ] Actual test/static/format commands and results disclosed; unrun means unrun.
- [ ] Open decisions still marked open and Pilot blockers not silently waived.
- [ ] No unauthorized merge, package install, migration, main promotion or deployment.

---

## 16. Final Engineering Position

The API standard for E-commerce SaaS V2 is **Laravel-native, RESTful, single /api/v1, OpenAPI contract-first and Tenant-safe**. Merchant users authenticate through **Sanctum first-party Sessions** and identify their intended Tenant **explicitly in the URL**; backend authorization checks every relevant Tenant, Location, resource and business permission. Public storefronts use **trusted Tenant subdomains**, and customers have separate tenant-local identity scopes.

Responses use **ApiResponse**, mapped **camelCase** Resources, actual HTTP statuses, **X-Request-ID** headers, localized errors and stable public error codes. Queries use bounded allowed filters/sorts, **default 20 / maximum 100** for ordinary lists, and **PostgreSQL-first search** with measured indexing. Spatie integration remains conditional on real compatibility.

High-risk writes protect against duplicate API attempts **and** duplicate real-world money/stock effects through **Idempotency-Key, authoritative domain constraints, PostgreSQL ACID and Transactional Outbox**, with reconciliation for unknown external outcomes. Every significant API change must be validated against OpenAPI, current authorization and actual relevant DB/worker behavior.

**Approved documentation baseline only — no installed or running API is proven here. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.**

**End of API Standards baseline.**
