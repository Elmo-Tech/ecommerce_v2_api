# E-commerce SaaS V2 — Backend Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target path:** `docs/01-architecture/backend-architecture.md`  
**Version:** 1.0 — Architecture Baseline / First Documentation Draft  
**Prepared:** 2026-11-16  
**Branch:** `development`  
**Architecture decision status:** **CONDITIONALLY APPROVED — BA-30 (B)**  
**Document wording status:** **DRAFT FOR CONTENT REVIEW**; publication is authorized, but the published text has not been separately signed off.  
**Decision coverage:** BA-01…BA-30, with **29 approved** and **BA-16 deferred**.  
**First deployment target:** Egypt-only Closed Pilot; initially one production VPS.

> **Critical reading rule:** Architecture approval does **not** mean implementation completion, successful CI/CD configuration, legal compliance, or permission to activate merchants with live data. Backup and verified recovery, security/tenant-isolation evidence, performance acceptance, and legally required workflows are mandatory Pilot readiness gates. BA-16 is **DEFERRED, NOT APPROVED**.

---

## 1. Document Overview, Authority and Boundaries

### 1.1 Purpose

Define the approved technical architecture for a Multi-Tenant E-commerce SaaS API supporting independent merchant storefronts and unified commerce operations. This is the **system-wide backend architecture** reference, not a detailed database schema, individual feature specification, deployment runbook, or a new approval of business rules.

The platform combines an independent storefront for each merchant with operational administration for online orders, staff-entered manual orders, simplified internet-connected direct sales, location-specific inventory, controlled fulfillment, collections and post-sale workflows.

### 1.2 Source and decision hierarchy

Read together with these repository references:

1. [Project Overview](../00-overview/project-overview.md) — approved OV-01…OV-25 product/MVP choices; its **document text is currently a review draft**.
2. [Latest authoritative D-152…D-200 decision register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) — approved detailed business decisions, with **D-201 OPEN**.
3. [Comprehensive Business Discovery Handoff](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) — earlier D-series history and current business context.
4. [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) — open product decisions, exceptions and eligibility gates.
5. [Repository workflow](../WORKFLOW.md) — branch conventions; not proof of configured automation.
6. Explicit architecture decisions **BA-01…BA-30** agreed in architecture discovery, recorded in section 18 here. The full individual architectural ADRs are not yet separate repository files.

Authority is domain-specific: approved D-series decisions control business behavior; OV decisions control MVP scope; BA decisions control technical architecture. If they appear to conflict, **surface the conflict and request a decision**; do not silently rewrite history. Earlier handoff proposals do not supersede later approvals.

### 1.3 Scope and non-goals

**Initial MVP/Pilot:** selected Egyptian small/mid-size merchants; platform-admin-led onboarding and manually managed SaaS subscriptions; independent subdomain storefronts; Arabic and English; EGP; 2–3 prebuilt themes; responsive Next.js surfaces; Catalog, Locations and inventory; eligible online and manual orders; basic online-connected direct sales; manual delivery/pickup; permitted verified collection records; transactional email and in-app notifications; essential reporting, CSV/Excel exports and support requests.

**Not included in first MVP:** merchant self-service onboarding, integrated online payment gateway, shipping carrier API, automated WhatsApp/SMS, PWA/native apps, custom domains, full offline POS, split tender for Direct Sale, split fulfillment, full ERP, advanced AI, automated marketing, full report builder, S3 hosting as the initial storage backend, and other expressly deferred functions. A future-capable adapter is not permission to implement a deferred product integration.

The complete approved MVP can be enabled in **controlled Pilot phases** only where each participating merchant has a safe, complete, legally eligible workflow. No agreed MVP capability is silently removed by phasing.

### 1.4 Status vocabulary

| Status | Meaning |
| --- | --- |
| **APPROVED** | Explicitly selected architectural design direction; implementation detail may still require specification. |
| **DEFERRED** | No architecture option selected; does not waive a mandatory operational gate. |
| **PENDING VALIDATION** | Requires measurement, testing, security review, legal review or an implementation-specific decision. |
| **FUTURE-READY** | Preserve a migration boundary; do not deploy the future technology by implication. |

---

## 2. Architecture Principles and Non-Negotiable Invariants

1. **API-first, modular monolith:** Laravel 13 is one backend application with disciplined business module boundaries; not a set of microservices.
2. **Database-per-tenant separation:** one Central PostgreSQL database plus an independent PostgreSQL database per merchant. Separate databases need not mean separate physical servers.
3. **Fail-closed tenant resolution:** never infer trusted tenant authorization from a user-controlled identifier alone. Every request, job, file, cache key and export uses validated tenant context.
4. **Least privilege:** separate platform identity, merchant memberships, tenant staff roles, plan entitlements, location permissions, resource ownership and special business approval rights.
5. **Business invariants over convenience:** no overselling, no implicit Pending Stock confirmation, no double dispatch/collection/refund, no historical financial mutation, and no unauthorized cross-store access.
6. **One eligible fulfillment location per order:** do not merge partial inventory from multiple Locations into apparent availability.
7. **Strong local consistency:** business-critical decisions use ACID transactions on a single tenant database with concurrency protection and idempotency; post-commit effects may use outbox/events.
8. **Events do not replace critical synchronous checks:** an asynchronous consumer cannot be the final safety mechanism for reservation, payment verification or inventory decrement.
9. **Source-of-truth discipline:** PostgreSQL owns operational facts. Redis, search indexes, aggregates, email status and generated files are derivatives or workflow aids unless specifically modeled as authoritative records.
10. **Resilience by measurement:** prioritize security, tenant safety and coherent production releases before advanced topology. No invented throughput/uptime guarantees.
11. **Egypt Pilot and regulatory readiness:** applicable statutory tax/sales documents, e-invoicing or e-receipt rules, customer rights and privacy obligations are verified for actual enabled merchants, not assumed satisfied by generic PDFs.
12. **Traceability:** every production-relevant feature is derived from approved D/OV/BA decisions, explicit feature acceptance tests, an auditable implementation and release evidence.

All completed monetary amounts use integer currency minor units according to the currency exponent, with approved intermediate calculation precision. EGP is the initial in-store Pilot currency; do not hardcode the assumption that all future currencies have two decimal places.

---

## 3. System Context and Runtime Topology

### 3.1 Logical view

~~~mermaid
flowchart TB
    Shopper["Shopper — Merchant Storefront / Next.js"]
    Staff["Merchant Dashboard / Next.js"]
    Admin["Platform Admin Surface"]
    Proxy["Reverse Proxy / HTTPS / Blue-Green Traffic Routing"]
    API["Laravel 13 REST API — PHP-FPM initially"]
    Core["Tenant Resolution + AuthZ + Business Modules"]
    Central[("Central PostgreSQL DB")]
    Tenants[("Isolated PostgreSQL Database per Tenant")]
    Redis[("Redis — Queues / Cache / Rate Limits")]
    Horizon["Laravel Horizon / Outbox Workers"]
    Storage[("Persistent Local File Storage — S3 Ready")]
    Email["Transactional Email Provider"]

    Shopper --> Proxy
    Staff --> Proxy
    Admin --> Proxy
    Proxy --> API
    API --> Core
    Core --> Central
    Core --> Tenants
    Core --> Redis
    Core --> Storage
    Redis --> Horizon
    Horizon --> Central
    Horizon --> Tenants
    Horizon --> Storage
    Horizon --> Email
~~~

This represents **logical relationships**, not the number of physical servers or permanent open database connections. The initial production layout is one VPS with separate containers for Laravel API, Horizon, PostgreSQL, Redis and the reverse proxy; Next.js hosting may be deployed independently. The frontend implementation is outside this API repository.

### 3.2 Runtime components

| Component | Initial role | Boundary |
| --- | --- | --- |
| Laravel 13 API | Contract-first backend on PHP-FPM | Stateless request handling where possible; no business state in process memory. |
| PostgreSQL | Central DB + per-tenant DBs | Runtime database roles are constrained; independent tenant databases. |
| Redis | Cached data, queue transport and throttling | Not the final truth for financial or inventory values. |
| Laravel Horizon | Tenant-aware background processing | Separate worker process/container; no retained tenant context. |
| Reverse proxy | TLS termination and deployment traffic routing | Readiness checks and controlled switch between API versions. |
| Local file volumes | Product media, private documents, export files | Persistent and segregated from disposable container filesystems. |
| Next.js | Storefront and management UI | Browser auth/session contracts; separately deployed frontend. |
| Email provider | Transactional notifications | Provider-agnostic adapter; no synchronous dependency for completing orders. |

### 3.3 Business request boundary

For a tenant-scoped request: establish trusted tenant identity, authenticate actor if required, validate membership and scope, activate the correct tenant connection, enforce entitlements/policies, execute the module operation, commit its transaction, and clean request-specific context. Errors must not fall back to a different tenant or an unscoped default connection.

---

## 4. High-Level Application Architecture

The Laravel application is a **Modular Monolith**, with business modules sharing one deployment unit while enforcing explicit public contracts. Modules should own their aggregates and write paths; controllers are delivery adapters rather than containers for business rules. Shared infrastructure handles tenancy, persistence connections, observability and transports, not unrelated business logic.

**Interaction model (BA-07 B):**

- **Synchronous contracts** for authorization, pricing verification, inventory reservation, operational state transitions and other immediate business-critical operations.
- **Domain Events + Transactional Outbox** for post-commit notifications, analytics updates and other recoverable side effects.
- **Explicit coordination / compensation** for business workflows spanning Central and Tenant databases. Never assume a single ACID transaction covers both.
- **No unrestricted cross-module table mutations**; an integration contract is required for changing another module's owned state.

The runtime is initially PHP-FPM with OPcache and measured worker resource limits. All code and dependencies must respect **Octane-Ready** rules: no leaked request/tenant data in static mutable state or long-lived service singletons, no retained obsolete connections, and explicit lifecycle cleanup. Octane is **not approved for first-Pilot production**; selection of its server and activation require isolated performance/security tests.

---

## 5. Business Modules, Ownership and Dependency Boundaries

The following **16 logical business domains** are an approved architectural baseline for responsibility planning, **not an immutable requirement for 16 Laravel package directories**. Concrete code grouping and per-feature boundaries are to be finalized in Module Architecture and Spec Kit documents.

| Domain | Primary ownership | Principal dependencies |
| --- | --- | --- |
| **Platform & Tenants** | Central Tenant Directory, status, provisioning lifecycle, merchant onboarding by Platform Super Admin | Central database, security, provisioning infrastructure |
| **SaaS Subscriptions** | Central plans, subscription administration, entitlement definitions and enforced limits | Platform & Tenants, identity |
| **Identity & Access** | Central platform/staff login identities and memberships; tenant-local roles, permissions and location scopes | Tenant Context, Central/Tenant persistence, policy engine |
| **Merchant Settings & Content** | Tenant shop identity, bilingual content, supported themes, public pages and settings | Tenant AuthZ, file storage, entitlements |
| **Catalog** | Products, variants, basic services, categories, media, catalog imports and publishable states | Settings, storage, entitlements |
| **Pricing & Promotions** | Pricing contracts, authorized discounts, coupons, tax calculation rules and price breakdowns | Catalog, entitlement and policy contracts |
| **Customers** | Independent per-storefront shopper accounts, guest details and addresses | Tenant-specific authentication/authorization |
| **Inventory & Locations** | Locations, stock ledger, on-hand/reserved/available, allocations, adjustments and physical transfers | Catalog, tenant and location permissions |
| **Orders** | Online/Manual order records, pending-stock review, confirmed order orchestration and preserved snapshots | Catalog, pricing, customers, inventory, collection contracts |
| **Direct Sales** | Online-connected immediate sale and evidenced late entry/correction of real external sales | Catalog, pricing, inventory, collections |
| **Fulfillment** | Preparing/Ready/Hold, actual dispatch, manual delivery and pickup confirmation | Orders, inventory, authorized actors |
| **Payments & Collections** | Payment/collection facts, manual verification, COD reconciliation and external evidence | Orders/direct sales, permissions, approved financial rules |
| **Returns & Refunds** | Return/exchange process, inspection outcomes, authorized and evidenced refund state | Orders/sales, inventory, collections |
| **Notifications** | Tenant-scoped in-app alerts and transactional email intentions/status | Domain events, outbox, identity, mail provider |
| **Reporting & Exports** | Read projections, dashboards, scoped exports, report generation | Authorized read contracts across domains |
| **Support** | Merchant help content/support requests and Platform Admin responses | Platform/tenant identity, access, storage |

**Audit & Activity** crosses the system but retains data ownership: tenant business audit in the Tenant DB and platform administrative audit in the Central DB, with limited centralized security telemetry (BA-14). This need not become a seventeenth business domain.

### 5.1 Required boundary clarifications

- **Catalog vs Pricing:** Catalog owns eligible items and their applicable base price definitions; Pricing evaluates authorized discounts, taxes and final calculations. Orders preserve confirmed financial snapshots.
- **Orders vs Inventory:** Orders coordinate confirmation; Inventory owns reservation and stock movements. No Orders-side direct stock table mutation.
- **Orders vs Fulfillment:** Fulfillment verifies physical events; Inventory executes the authorized idempotent stock effects. Delivery and COD collection remain separate states.
- **Direct Sales vs Orders:** Direct Sale has immediate paid/handover constraints distinct from ordinary order fulfillment. Reuse contracts, not necessarily a single state machine.
- **Collections vs Returns:** approving a refund is not proof that money was returned. Physical receipt and inspection are distinct from restocking.
- **Reporting:** read-only domain contracts, authorized queries and aggregates; no alternate business ledger of truth.
- **Support:** exact Central/Tenant data ownership and support visibility need a module-specific decision before implementation; do not assume Platform Admin can browse merchant secrets or data unrestrictedly.

### 5.2 Recommended dependency-aware delivery sequence

**Foundation** → Central Tenant Directory and Provisioning → Tenant Connection/Isolation → Identity, Membership and Authorization → Entitlements/Audit/Outbox/Storage → Merchant Settings/Catalog/Customers/Pricing/Locations → Inventory Ledger/Reservations/Transfers → Orders/Collections → Direct Sales and Fulfillment → Returns/Refunds → Reports, Support and remaining MVP features.

This is a **technical dependency direction**, not an approved feature list, final Pilot wave allocation or permission to omit later approved MVP capabilities. Catalog, settings, customers and notification templates can progress in parallel when their contracts are ready.

---

## 6. Multi-Tenancy Architecture and Provisioning

### 6.1 Data partition model — BA-02 C

The **Central DB** holds Tenant Directory and lifecycle status, platform administrators, shared staff login identities, explicit tenant memberships, SaaS plan/subscription/entitlement ownership and platform audit. Each **Tenant DB** holds local roles, policies, location scopes, independent storefront customer accounts and commercial operations (catalog, orders, inventory, collections, fulfillment, returns, local audit).

A merchant's shopper account is not a global platform shopper identity. The same email may independently exist in different tenant storefronts, and it does not imply cross-tenant access.

### 6.2 Trusted tenant resolution — BA-03 B

- **Storefront:** resolve the tenant from an approved platform subdomain and Tenant Directory mapping.
- **Authenticated dashboard/API:** derive tenant scope from an authenticated and authorized membership, with active-tenant selection where relevant.
- **When both host and explicit context exist:** they must agree; disagreement is rejected.
- **Background processing:** serialize an immutable, validated tenant reference for the job and resolve it again under worker authorization/availability rules.
- **All cases:** verify tenant state/entitlement, reject unknown/unauthorized tenant, and clear context in all completion and exception paths.

Never trust a request header or tenant ID supplied by an untrusted client as authorization. Avoid global model scopes, connection defaults, cache keys or jobs that silently recover by falling back to a prior/default tenant.

### 6.3 Provisioning — BA-05 B

Platform Super Admin starts tenant onboarding; an asynchronous, retry-safe workflow progresses through **Pending → Provisioning → Ready / Failed**. The orchestrator creates tenant DB and restricted roles, applies controlled migrations/bootstrap, installs required tenancy settings, validates readiness, and activates only on success. Retries must not duplicate databases or partially activate merchants. An expired subscription does **not** automatically delete the tenant DB.

Provisioning credentials are not available to ordinary storefront requests. Failed provision attempts are auditable and recoverable by authorized operators.

### 6.4 Migrations across tenants — BA-06 B

Use migration versions and inventory of per-tenant schema state, preflight validation, controlled batches, failure isolation, and expand/contract compatibility across the live application versions. A migration failing for one tenant blocks its own progression and is surfaced; it does not silently make every tenant unusable or claim successful rollout. Version drift and backward/forward compatibility must be visible to deployment gates.

---

## 7. PostgreSQL, Connections, Transactions and Data Integrity

### 7.1 Tenant-aware connection manager — BA-20 B

A shared **connection-management capability** activates the Central or a specific Tenant connection only when required (**lazy connection**), under a validated Tenant Context. Each request/job ends by disposing of tenant-specific mutable state and stale connection bindings; this is mandatory for Horizon and any future Octane runtime. Never swap a tenant connection during an active transaction.

Connection concurrency and timeouts are bounded against the **shared PostgreSQL instance** and total PHP/Horizon concurrency. Five hundred tenant databases would not imply five hundred permanent open connections. A pooling proxy such as PgBouncer is **not selected for first Pilot** and can be evaluated only with measurements and careful transaction/session-state compatibility tests.

### 7.2 Database identities and secrets — BA-21 B

- A dedicated, least-privilege PostgreSQL **runtime role per tenant** can access its tenant DB only.
- Central runtime role, tenant migration role, provisioning role and infrastructure administrator are separate privilege tiers.
- Runtime APIs are not database superusers and cannot create databases.
- Explicitly review PostgreSQL CONNECT and default PUBLIC privileges, schema/table/sequence privileges, role inheritance and future object grants to enforce actual separation.
- Secrets are retrieved from a secured configuration/secret mechanism (provider not yet chosen). Central DB stores opaque references, not plaintext passwords; logs never include secrets.
- Credential rotation/revocation must be planned with connection teardown, availability checks and audit; failed secret resolution is **fail closed**.

### 7.3 Transaction semantics — BA-08 B

All reservation, sale, dispatch, correction, collection and refund effects must be performed with the proper tenant-local ACID transaction, concurrency-safe writes or locks, stable unique operation keys and idempotency guards. Where Business Rules require one Location to fulfill a complete order, verify the **whole set of lines** atomically before confirming/reserving.

The Transactional Outbox records intended downstream events **within the same relevant Tenant DB transaction**. Outbox relays and consumers are retryable and idempotent; delivery may occur more than once. Redis transport does not give exactly-once external effects.

Operations spanning Central/Tenant DBs require explicit staged coordination with durable state, retries and compensation/reconciliation, **not** a presumed distributed ACID commit.

### 7.4 Historical evidence and domain constraints

- Preserve actual stock states **On-hand, Reserved, Available** per Location, plus in-transit and unsellable states where applicable.
- A Transfer reduces the source at actual authorized dispatch, records in-transit stock and increases destination only after actual receipt, according to approved rules; order reassignment is **not** an inventory transfer.
- Manual Pending Stock is unconfirmed and unreserved; FIFO is follow-up priority, not an implied inventory guarantee.
- Physical dispatch or pickup reduces stock/consumes reservation once; Delivery confirmation does not repeat inventory subtraction or imply COD settlement.
- Direct Sale requires full verified collection and immediate handover; one payment method; no offline checkout. Authorized late recording of a real external sale preserves actual-event and recorded-at timestamps plus correction lineage.
- Keep transaction/order, tax, discount, consent and payment snapshots historically traceable. Use reversal/correction records rather than deleting original legitimate financial history.

Specific table schemas, PostgreSQL partition/index layout, ORM repository patterns and data-retention durations belong to later data standards and per-feature designs, subject to these invariants.

---

## 8. Authentication, Authorization, Audit and Security

### 8.1 Authentication — BA-04 B / BA-12 B

- Platform Admin and merchant staff authentication use the central identity and membership model.
- Storefront shoppers authenticate within their individual tenant's customer identity space; guest checkout remains supported where approved.
- Browser authentication defaults to **Laravel Sanctum session cookies + CSRF**; privileged API tokens are not a default substitute for browser sessions and require separate scoped approval when needed.
- Define cookie scopes, SameSite/Secure/HttpOnly, trusted origins, subdomain boundaries, session invalidation and CSRF behavior for Next.js and store subdomains. **Do not create wildcard cross-tenant auth cookies** that inadvertently merge shopper or merchant sessions.
- Avoid relying on user-supplied tenant hostnames, Referer or untrusted claims to switch identities.

### 8.2 Layered authorization — BA-13 B

Effective access requires **all applicable layers**: authenticated actor/guest eligibility, active tenant membership, subscription entitlement, role/permission registry, approved Location scope, resource ownership and business-specific approvals (including refunds, discounts and corrections). Default is deny; enforce on reads, writes, exports, file downloads, jobs and platform administrative operations—not merely UI visibility.

A subscription enabling a feature does not authorize every employee to perform it. Special refund/correction powers are separate from ordinary role labels; do **not** invent a universal mandatory two-person approval when not required by approved D-series rules.

### 8.3 Audit and telemetry — BA-14 B

Record critical tenant business effects with actor, timestamp, tenant/location, before/after references or delta, reason and correlation ID where appropriate; platform tenant/subscription/admin changes are logged centrally. Security monitoring records bounded high-value security events across the platform. Apply retention/access controls, integrity safeguards and privacy minimization. Do not claim an ordinary audit table is intrinsically tamper-proof against privileged database administrators.

Security practices include input validation, rate limiting by identity/IP/tenant/endpoint, upload checks, private files, environment-scoped secrets, dependency checks, encryption in transit and action-level alerts. Exact algorithms, limits and retention periods belong to security standards and measured operations.

---

## 9. REST API Contracts and Versioning

**BA-11 B:** contract-first **REST JSON** APIs with the initial version prefix **`/api/v1`**, OpenAPI as the schema reference, documented validation, authorization, error classifications, pagination, sorting and filtering. Preserve consistent stable machine-readable error identifiers independent of Arabic/English presentation text.

A predictable client-facing standard must define response and error envelope, filtering grammar, pagination strategy, idempotency headers/keys, error codes, request correlation, feature entitlement failures, authorization failure semantics and localization. **The exact wire schema has not yet been approved**; specify it in API Standards, not by guessing field names here.

Backward compatibility and explicit deprecation windows protect Next.js consumers; breaking behavior requires a reviewed versioning decision and contract tests. Endpoint names, resources, fields and model validation details are per-feature specifications.

Tenant-specific data access is scoped on the server irrespective of client-provided filters or IDs. OpenAPI contracts and test cases include both successful and forbidden cross-tenant/cross-location access.

---

## 10. Redis, Horizon, Events and Background Work

### 10.1 Async policy — BA-09 B

Redis queues + Laravel Horizon process background and delayed work. Logical queue classes can include **critical, notifications, default, bulk and platform** (names/splits are proposed configurations, not a pre-existing implementation). Configure worker counts, retry budgets, timeouts, backoff, alerting and fairness by measured workloads. Reserve capacity for core operations; bulk imports/exports may not starve critical work.

Tenant-aware jobs carry a trusted tenant reference and execution identity; the worker re-resolves tenant state and permission where applicable, activates the correct DB connection, executes idempotently and clears context in a guaranteed cleanup path. Failed jobs and orphaned outbox events require auditable reprocessing.

### 10.2 Outbox and delivery semantics

A business write and its outbox event are committed together in the **same tenant transaction**. A publisher forwards the event, and each consumer maintains an idempotency boundary. Retries, duplicate deliveries, process crashes and reordered events must not duplicate collection, stock movements, corrections or refunds. Across Central/Tenant services, consistency is reconciled explicitly.

### 10.3 Notifications — BA-28 B

Order confirmation and other approved essential customer events create **transactional email intentions** without blocking order persistence on provider availability. The provider adapter is replaceable; track queued/attempted/accepted/failed states and only record **Delivered** when supported by trusted provider evidence. Provider webhooks are authenticated, replay-protected and tenant-attributed **if/when enabled**.

Tenant-local in-app alerts remain functional independently of email delivery and do not trigger unauthorized automatic confirmation, cancellation, hold release or refund. Email delivery does not constitute required customer consent or financial settlement proof. No automated WhatsApp/SMS, shipping API or payment gateway is authorized by this architectural adapter.

---

## 11. Cache, Search and File Storage

### 11.1 Caching and rate limits — BA-10 B

Redis keys are environment- and tenant-scoped (and actor/location scoped where necessary), with explicit TTL/invalidation behavior for permission, entitlements and domain read models. Sensitive or stale cached prices/availability must be revalidated against PostgreSQL at commit/checkout. Separate cache and queue capacity where needed; cache eviction must not break transactional correctness.

Rate limiting can be layered by IP, user, tenant and endpoint to reduce abuse and preserve fairness; limits remain **PENDING VALIDATION**.

### 11.2 Search and indexed access — BA-26 B

Use **PostgreSQL-first** search: appropriate indexes, exact-match product/SKU/order lookups, controlled text search, optional full-text and `pg_trgm` techniques where useful. Search queries are tenant-local and policy-filtered. Arabic/English correctness, normalization and ranking require representative test datasets and measured query performance; database features alone do not guarantee language quality.

Expose a module-facing search abstraction so a future dedicated search engine can be introduced without replacing business contracts. That engine is **not** approved for first-Pilot deployment.

### 11.3 File storage — BA-15 **AMENDED**

**Current approved strategy:** **Local Persistent Storage First + S3-Ready Architecture**. The earlier BA-15 shared S3-compatible initial backend is **superseded**.

- Product/media files, tenant documents and asynchronous report exports initially reside on **persistent VPS-mounted storage**, **outside ephemeral Docker container layers**.
- Production Blue and Green versions must access the required production files safely during a traffic switch; file operations and retention must be compatible across deployed versions.
- **Production and development** use separately scoped volumes, paths, permissions and secrets.
- Separate public product media from private order documents, customer uploads, support attachments and reports. A directory naming convention alone is insufficient authorization; check tenant, user, ownership and Location policy before serving private files.
- Apply validated content types, size/quantity limits, file-name normalization, safe content delivery, upload scanning/risk assessment and retention/deletion policies. Exact limits remain for file standards and business specs.
- Use Laravel's storage abstraction and disk-independent domain references, not hard-coded absolute local filesystem paths. A later move to S3-compatible object storage must include data migration, integrity checks and URL/permission compatibility.
- Local file storage remains a **single-server failure domain** and is **not a backup strategy**. Off-server recoverability is a mandatory pre-live gate under section 17.

No assumption of S3 presigned URLs exists while files are stored locally; secure authorization-backed or time-bounded application download mechanisms must be designed for that backend.

---

## 12. Reporting, Exports and External Integrations

### 12.1 Hybrid reporting — BA-27 B

Light operational reports use optimized tenant-local PostgreSQL reads and indexes; heavy reports and Excel/CSV exports use bounded Horizon jobs with streaming/chunking, controlled concurrency and memory/query timeouts. Files are stored privately on the approved local persistent backend, and access is re-authorized when fetched. Exports are never a way to bypass Location permissions or tenant boundaries.

Analytical aggregates/read models can be updated asynchronously where useful; mark freshness explicitly. Operational truth about active reservations, current order state or actual collection must not be inferred from stale dashboards. Platform-level metrics may use minimized authorized central aggregates instead of fan-out reads across all tenant databases.

Metric definitions (gross vs net sale vs collected revenue; return effects and the original business date of evidenced late-recorded external sales) are **module specification matters**. Historical corrections must remain explainable in reissued reports.

### 12.2 Provider-agnostic integrations — BA-28 B

Define narrow adapter interfaces for **actual MVP needs** (transactional email and already-approved storage mechanisms), with standardized provider error handling, backoff, request IDs, delivery-status mapping, secret isolation, circuit/bulkhead considerations if needed and observability.

A generic integration interface does **not** approve integrations to payment gateways, couriers, WhatsApp, social networks or automated subscription billing. Provider choice, DNS mail authentication (SPF/DKIM/DMARC), sender identities, lawful data processing and webhook detail are specified before live email activation.

---

## 13. Infrastructure, Environments and Release Strategy

### 13.1 Production first phase — BA-17 A

Begin with **one production VPS** and container separation of Laravel API, Horizon, PostgreSQL, Redis and reverse proxy. Use controlled persistent volumes for PostgreSQL and file storage; no claim of multi-node High Availability. Container networking and secrets must restrict unnecessary administrative access. Host sizing and resource reservations remain **PENDING VALIDATION**.

Design connections/configuration so Laravel API, workers, database and Redis can move to separate hosts later without rewriting core business logic (BA-17 future B). **Single VPS remains a single point of failure** for application, database and local files.

### 13.2 Two environments only — BA-22 **AMENDED**

| Environment | Repository branch | Purpose | Mandatory segregation |
| --- | --- | --- | --- |
| **Development** | `development` | Integration and pre-release testing | Independent databases, Redis/queues, file volumes, hostnames, secrets, and no live merchant-data writes. |
| **Production / Main** | `main` | Stable merchant-serving release | Production-only credentials and data, controlled deploy and audit. |

No third always-on Staging environment is required now. If both run on one physical host, logical/environment separation is mandatory and the lack of failure/resource isolation must be acknowledged. Prefer separate infrastructure when needed by resource tests. This decision sets environment **roles**, not proof that deployments are currently running.

### 13.3 Blue-Green release — BA-18 B

1. Build and test immutable release artifacts **without stopping the running production version**.
2. Preflight production resources, database versions and required data/storage compatibility.
3. Run compatible **expand-first** tenant migrations in controlled batches, with version tracking; never assume an old release tolerates a destructive schema change.
4. Launch the new application version alongside the old on the VPS if capacity allows.
5. Verify readiness, tenant connectivity, application health and essential smoke tests.
6. Switch reverse-proxy traffic to the new healthy version; drain in-flight work, coordinate Horizon worker stop/start, observe error/latency/queue metrics.
7. Roll back **application traffic** when compatible and safe; database rollback is **not automatic**, especially after per-tenant migrations or irreversible data writes.

This targets **zero/near-zero disruption for compatible application releases**, not 100% uninterrupted service under VPS failure, unsafe migrations or insufficient temporary resource capacity. Use a justified, controlled maintenance window rather than pretend otherwise.

### 13.4 CI/CD — BA-23 B

- Feature/fix PRs target `development` with automated static checks, unit/API/tenant isolation tests and review.
- Successful integration tests permit **automated deployment to Development**.
- Promotion is a reviewed PR from `development` to `main` with required quality gates and explicit production release approval.
- Production release uses Blue-Green, post-deploy smoke/observability checks and an auditable rollback decision.
- Merge policies, exact GitHub Actions workflows, secrets wiring and protected branch settings **still require implementation and verification**. The repository `docs/WORKFLOW.md` is guidance, not evidence that these controls exist.

---

## 14. Testing, Observability, Security and Quality Gates

### 14.1 Risk-based testing — BA-25 B

| Test family | Minimum architecture concern |
| --- | --- |
| Unit / policy | Isolated money, authorization, discount, stock and state rules. |
| Integration / PostgreSQL | Transaction boundaries, locks, reservations, ledger/collection consistency. |
| API contract | OpenAPI shapes, errors, pagination, backward compatibility, locales. |
| Tenant isolation | Cross-tenant reads/writes/IDs, staff/customer identity, cache, files, exports, workers. |
| Concurrency / idempotency | Competing last-unit orders, repeated requests, worker retries and duplicate events. |
| Queue / outbox | Crash/retry/reordering, event publication, tenant context cleanup. |
| Batched migrations | Multiple tenant schema versions, partial failure, safe continuation and version tracking. |
| Deployment | Blue-Green readiness, compatibility, drain, rollback and persistent files. |
| End-to-end | Eligible Online/Manual/Direct Sales, fulfillment, collections and post-sale paths. |
| Security / load / recovery | Privileges, secrets, rate limits, representative throughput, restore rehearsals. |

Each Spec Kit feature will define acceptance criteria, negative/authorization cases, regression tests and PR evidence proportional to risk. Tests that demonstrate cross-tenant access or duplicate financial/stock effects are **release blockers**. Heavy load/chaos suites need not run on every commit; run them at risk-appropriate gates and before Pilot.

### 14.2 Observability — BA-24 B

Implement structured logs, correlation/request IDs, actionable errors, tenant-aware and privacy-limited metrics, plus tracing where needed. Observe API P95/P99, error rates and availability, PostgreSQL connections/slow queries/locks/IO, Redis usage, Horizon lag/failures, outbox backlog, blue-green release outcomes and business-critical failures.

Separate Production and Development metrics. Tenant identifiers may be used where needed for diagnostics under privacy controls; never log passwords, unrestricted personal data, card credentials or complete financial records.

Numerical SLOs, alert thresholds and capacity limits are **PENDING VALIDATION** after representative load/availability tests. Do not invent 99.99% availability or a fixed P95 value here. Resource exhaustion during concurrent blue-green deployment must be measurable before approval.

---

## 15. Capacity Planning and Scaling

**BA-29 B — Measured Capacity Planning + Phased Scaling**:

1. **Pilot baseline:** define representative merchant/store, branch, catalog, order, concurrent shopper, inventory transaction and export workloads; measure real resource behavior, not an assumed merchant count.
2. **Profile critical paths:** checkout confirmation, manual order confirmation, full immediate Direct Sale, competing reservations, physical dispatch, returns, report exports and tenant provisioning/migrations.
3. **Measure shared bottlenecks:** CPU, RAM, disk/IOPS, PostgreSQL connections and lock time, Redis pressure, Horizon lag, HTTP error/P95 and temp resources for Blue-Green.
4. **Vertical scaling first:** adjust VPS CPU/RAM/disk/IO capacity and worker/connection limits based on demonstrated constraints.
5. **Service separation second:** move PostgreSQL, workers, Redis and/or API independently when measured limits, resilience needs or isolation concerns justify them.
6. **Storage migration when justified:** move persistent file data to S3-compatible storage with verified migration and access controls **before** relying on multiple application hosts that require shared durable files.
7. **Horizontal/multi-node scaling only after redesigning remaining shared failure domains**, validating readiness, connection concurrency, tenant routing and service recovery.

**Unapproved until measured:** VPS sizing, peak request rates, database count and tenancy distribution per PostgreSQL host, storage quotas, alert thresholds, queue worker counts, session capacity, service availability objectives and RPO/RTO.

An implementation-specific capacity report and infrastructure configuration belong to later artifacts; this section defines the approved measurement strategy.

---

## 16. Architecture Risks, Open Decisions and Deferred Choices

| Risk / open item | Impact and required response | Status |
| --- | --- | --- |
| **BA-16 Backup & Restore** | Architecture choice remains deferred; design tested off-VPS backups for Central/Tenant DBs and local files, recovery ownership and RPO/RTO before live data. | **DEFERRED decision / BLOCKING Pilot gate** |
| **Single VPS failure** | Full production outage possible even with blue-green; monitor, document recovery and phase migration when justified. | Accepted initial topology risk |
| **Local file/data co-location** | Same host failure can affect files and DB simultaneously; persistent volumes alone are not protection. | Accepted initial design / recovery gate |
| **Blue-Green memory headroom** | Two releases running together may exhaust host resources. | Pre-deploy load and resource acceptance |
| **Multi-tenant credential or context leak** | Cross-store data exposure from workers/connections/auth/cookies. | Critical security gate |
| **Batch migration incompatibility** | Mixed database versions or destructive changes can prevent safe rollback. | Release-blocking compatibility tests |
| **D-201** | Choice of special follow-up marker vs existing communication notes for alternative-location proposal awaiting customer response. | **OPEN — Orders/Fulfillment feature decision** |
| **Egypt compliance** | Legally required receipts/e-invoicing/sales documents and data/customer rights vary by merchant type and activity. | Independent legal/go-live gate |
| **Numerical SLO, capacity, RPO/RTO** | Cannot claim safe load/recovery without evidence. | Pending measurement and BA-16 |
| **Module-level rules** | Plan grace/expiry, exact permission registry, pricing edge cases, report definitions, upload limits and support ownership. | Later approved feature/standards decisions |
| **Octane / PgBouncer / external search** | Would introduce long-lived/concurrency or synchronization complexity. | Future option, not enabled |
| **S3-compatible storage** | Local volume limits distributed hosting and recovery patterns. | Future migration, not initial backend |
| **Project Overview document status** | Existing approved OV scope is represented in a review-draft file; final wording needs its own sign-off. | Documentation review pending |

**No automatic scope expansion:** future capabilities require separate business/scope approval. A readiness gate is not a promise that a currently deferred integration will be built.

---

## 17. Mandatory Pilot Readiness Gates — BA-30 B

**Architecture acceptance** and **permission to activate live merchant data/workflows** are separate decisions. A Closed Pilot can only start for merchants whose enabled commercial journeys are fully supported and legally eligible. The following gates must have owned, recorded evidence:

| Gate | Evidence required before merchant live activation | Current disposition |
| --- | --- | --- |
| **G1 — Tenant safety** | Multi-tenant penetration/regression tests across HTTP, admin, workers, files, cache, searches and exports; credential privilege checks. | NOT VERIFIED |
| **G2 — Commerce consistency** | Concurrent checkout/Direct Sale/Manual Order tests: no overselling, duplicate stock/collection/refund or implicit Pending Stock confirmation. | NOT VERIFIED |
| **G3 — Backup and restore** | **Approve BA-16**; complete recoverable off-VPS backups of Central DB, Tenant DBs and local files; perform full/tenant restore drills; approve measured **RPO/RTO**. | **BLOCKED — BA-16 DEFERRED** |
| **G4 — Releases** | Tested CI/CD, backward-compatible batch migrations, blue-green resource headroom, health checks, worker drain and compatible application rollback. | NOT VERIFIED |
| **G5 — Capacity and NFRs** | Approved numeric SLOs and realistic measured load profiles; queue/database/storage capacity and alerts. | PENDING VALIDATION |
| **G6 — Security and privacy** | Authorization and secrets audit, TLS/cookies/CSRF, upload defenses, audit retention and incident response expectations. | NOT VERIFIED |
| **G7 — Legal readiness** | Verified Egyptian tax, e-invoicing/e-receipt, sales document, data/privacy and customer-rights obligations for the actual participating merchants/channels. | LEGAL REVIEW REQUIRED |
| **G8 — Operational completeness** | E2E verification for each enabled Pilot wave, including settlement/returns/support obligations, bilingual UI/notifications and merchant staff controls. | PENDING FEATURE ACCEPTANCE |
| **G9 — Deployment/environment separation** | `main` and `development` isolated; production is not exposed to test data, secrets or uncontrolled resource contention. | NOT VERIFIED |
| **G10 — Explicit release sign-off** | Named release owner, acceptance record, scoped merchant/feature enablement and documented stop/rollback criteria. | APPROVAL REQUIRED |

These are **gates to verify**, not assertions that the repository already satisfies them. Do not set a go-live date or approve live onboarding while G3 remains unfulfilled. Recovery design specifics stay open until BA-16 is explicitly resolved; urgency does not silently select PITR, snapshots or a backup vendor.

### 17.1 Delivery vs business launch

Documentation, API scaffolding, specs, automated tests and other non-production engineering can progress while BA-16 is deferred. However, production-grade readiness evidence must exist before actual merchant/customer records are entrusted to the system. The controlled phased Pilot (OV-24 B) is a **feature activation strategy**, not a waiver of security, integrity, backups or law.

---

## 18. Architecture Decision Traceability

The following preserves the architecture decisions made in discovery. Choice labels are specific to each BA decision; **BA-15 was later explicitly amended**, and **BA-22 was adapted to the requested two-environment setup**.

| Decision | Status / selection | Binding architecture baseline |
| --- | --- | --- |
| BA-01 | APPROVED — B | Laravel 13 Modular Monolith + API-First. |
| BA-02 | APPROVED — C | Separate PostgreSQL database per Tenant + Central Database. |
| BA-03 | APPROVED — B | Hybrid tenant resolution via trusted subdomain and authorized membership/context; fail closed. |
| BA-04 | APPROVED — B | Central platform/staff identity and memberships; tenant-local merchant roles and independent shopper accounts. |
| BA-05 | APPROVED — B | Automated asynchronous, retry-safe tenant provisioning with readiness gates. |
| BA-06 | APPROVED — B | Batched tenant migrations, version tracking, expand/contract compatibility and failure isolation. |
| BA-07 | APPROVED — B | Strict module boundaries and hybrid synchronous contracts/domain events. |
| BA-08 | APPROVED — B | Tenant-local ACID, idempotency, transactional outbox and cross-DB compensation. |
| BA-09 | APPROVED — B | Redis Queues + Laravel Horizon; tenant-aware/background workload isolation. |
| BA-10 | APPROVED — B | Tenant-aware Redis caching, invalidation and layered rate limits. |
| BA-11 | APPROVED — B | Contract-first REST JSON `/api/v1` + OpenAPI and compatibility discipline. |
| BA-12 | APPROVED — B | Hybrid auth: Sanctum browser sessions/CSRF, central staff membership and tenant-local shoppers. |
| BA-13 | APPROVED — B | Layered authorization: membership, entitlement, role, location, ownership, business approval. |
| BA-14 | APPROVED — B | Tenant-local business audit + Central Platform Audit + centralized limited security monitoring. |
| BA-15 | **APPROVED — AMENDED** | **Local Persistent Storage First + S3-Ready.** Previous initial S3 backend superseded. |
| BA-16 | **DEFERRED — NO OPTION APPROVED** | Backup/Restore/PITR/DR selection open; tested off-VPS recovery mandatory before Pilot. |
| BA-17 | APPROVED — A | Single production VPS, containerized separation, migration-ready for later separated services. |
| BA-18 | APPROVED — B | Resource-conditional blue-green, readiness, drain, compatible rollback and coordinated migrations/workers. |
| BA-19 | APPROVED — C | PHP-FPM Pilot + Octane-Ready design; Octane only after measured safety/performance gates. |
| BA-20 | APPROVED — B | Central/Tenant connection separation, lazy tenant connections, cleanup, concurrency limits; PgBouncer later if justified. |
| BA-21 | APPROVED — B | Dedicated tenant runtime roles and least privilege, isolated migration/provisioning privileges and protected rotatable secrets. |
| BA-22 | **APPROVED — AMENDED** | Only `main` and `development` environments, separately isolated; no third Staging now. |
| BA-23 | APPROVED — B | Automated Development CI/CD; reviewed PR promotion to Main with quality gates and blue-green. |
| BA-24 | APPROVED — B | Structured observability, correlation IDs, tenant-aware metrics and measurable SLO gates. |
| BA-25 | APPROVED — B | Risk-based automated tests including tenancy, concurrency, outbox, migrations, deployment and security. |
| BA-26 | APPROVED — B | PostgreSQL-first search with abstraction, indexes, optional `pg_trgm`; dedicated engine later only if justified. |
| BA-27 | APPROVED — B | Hybrid PostgreSQL reporting + bounded Horizon async Excel/CSV and optional read aggregates. |
| BA-28 | APPROVED — B | Provider-agnostic integration adapters and reliable async transactional email/in-app delivery. |
| BA-29 | APPROVED — B | Measured capacity planning, vertical scaling first, service/storage separation when justified. |
| BA-30 | **APPROVED — B (CONDITIONAL)** | Architecture baseline approved subject to mandatory Pilot readiness, risk register and deferred decisions. |

### 18.1 What happens after this document

1. Review and explicitly approve the **wording and completeness** of this first architecture document; do not conflate publication with document sign-off.
2. Derive focused **Tenancy, Data, Identity, Module, Integration and Infrastructure** architecture/standards files where needed, referencing this baseline rather than duplicating it.
3. Define Module Boundaries and **Spec Kit Feature Breakdown** in separate artifacts; each feature gets its own specification, design plan, tasks and tests after approvals.
4. Implement Foundation first, then merchant/catalog/pricing/location and safe inventory, then commerce and post-sale workflows, following dependency and controlled Pilot gates.
5. Return to **BA-16** before live activation; verify backup/restore, security, capacity and legal readiness as recorded above.
6. Changes to approved architecture require a recorded impact assessment and explicit decision. Changes to business rules require D-series approval; scope changes follow OV-22.

**End of Backend Architecture Baseline — publication on `development` is a documentation action only; no application implementation or production deployment is authorized by this file.**
