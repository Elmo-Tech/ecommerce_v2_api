# E-commerce SaaS V2 — Module Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target path:** `docs/01-architecture/module-architecture.md`  
**Branch:** `development`  
**Document version:** 1.0  
**Prepared:** 2026-11-16  
**Phase:** Module Architecture Documentation  
**Architecture baseline:** MA-01 through MA-09 approved  
**Document status:** **APPROVED — content reviewed and publication authorized by the project owner on 2026-11-16**

**Important decision status:**
- **BA-15:** Local Persistent Storage First + S3-Ready — APPROVED.
- **BA-16:** Backup & Restore architecture — DEFERRED; verified recovery remains mandatory before live Pilot.
- **D-201:** Alternative-location proposal follow-up marker — OPEN.
- **MA-09:** Final Module Architecture Baseline with Tracked Open Items — APPROVED.

> This document records the approved module architecture and its reviewed wording. It does not authorize implementation, create a new product feature, finalize open business decisions, or establish production readiness.

---

## 1. Purpose and Scope

### 1.1 Purpose

This document defines the internal module architecture of the E-commerce SaaS V2 backend.

Its purpose is to establish:

1. Module organization and internal layering.
2. Central Platform and Tenant execution boundaries.
3. Responsibilities and ownership of business capabilities.
4. Public Contracts and permitted cross-module dependencies.
5. Domain Events, Integration Events and Transactional Outbox boundaries.
6. Data ownership and database access rules.
7. Laravel module registration and configuration conventions.
8. Feature-level Spec Kit integration.
9. Module testing, architecture validation and quality gates.
10. Dependency-aware delivery sequencing.

This document is the architectural baseline for creating module-specific specifications and implementing independent features.

### 1.2 Scope

The backend is a Laravel 13 API-first Modular Monolith serving:

- The Central Platform Administration.
- Independent merchant administration.
- Merchant storefront APIs.
- Per-tenant commerce operations.
- SaaS subscription and entitlement management.
- Operational notifications, reporting, audit and support.

The frontend uses Next.js and is outside this backend repository.

The initial product is an Egypt-first Closed Pilot with selected small and medium retailers. It supports Arabic and English, EGP, platform subdomain storefronts, online orders, manually recorded external-channel orders and simplified online-connected direct sales.

### 1.3 Non-goals

This document does not define:

- Exact database tables, columns or indexes.
- Final REST endpoint paths and payload schemas.
- Concrete PHP interfaces or class implementations.
- Complete Order or Payment state-machine definitions.
- Final feature identifiers or task estimates.
- Pricing for SaaS plans and trial/grace/suspension policies.
- Backup products, RPO/RTO or disaster-recovery topology.
- Production resource sizing or performance targets.
- Final Pilot waves or merchant activation schedules.

These require separate standards, module specifications, business decisions or measured validation.

### 1.4 Authoritative references

1. [Backend Architecture](backend-architecture.md) — approved BA technical direction, with the published document text still subject to its own review.
2. [Project Overview](../00-overview/project-overview.md) — approved OV-01 through OV-25 product/MVP decisions; document wording remains a review draft.
3. [D-152 onward Decision Register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) — approved business decisions through D-200.
4. [Business Discovery Handoff](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) — historical context and detailed business requirements.
5. [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).
6. [Development Workflow](../WORKFLOW.md) — proposed repository workflow; not evidence of implemented CI/CD or branch protection.

**Decision authority:** D-series governs business behavior; OV-series governs approved product/MVP scope; BA-series governs backend architecture; MA-series governs module organization.

A module specification may clarify implementation details but must not silently override an approved decision.

---

## 2. Approved Module Architecture Decisions

| Decision | Approved selection | Architectural consequence |
| --- | --- | --- |
| **MA-01** | B — Domain Modules + Feature-Based Organization | Business Domains are organized into modules with independently managed features. |
| **MA-02** | B — Feature-First + Lightweight Clean Architecture | Feature Actions, shared Domain Rules, technical adapters and public contracts; no mandatory redundant layers. |
| **MA-03** | B — Public Contracts + Domain Events + Strict Dependency Rules | Synchronous contracts for critical operations; events/outbox for subsequent effects; no circular dependencies. |
| **MA-04** | B — Strict Module Data Ownership + Controlled Read Models | Module-owned writes and controlled reads, historical snapshots, tenant-local ACID boundaries. |
| **MA-05** | B — Domain Modules + Explicit Execution Contexts | Central-Only, Tenant-Only and Cross-Context Orchestrator classifications. |
| **MA-06** | B — Explicit Module Registry + Module Service Providers | Explicit route, contract, policy and listener registration; no unrestricted runtime module discovery. |
| **MA-07** | B — Minimal Shared Kernel + Module-Owned Contracts & Versioned Events | Limited shared technical primitives; DTO-based contracts; versioned published events. |
| **MA-08** | B — Feature-Level Spec Kit + Risk-Based Testing Gates | Independent feature specifications, plans, tasks and proportionate automated quality gates. |
| **MA-09** | A — Final Module Architecture Baseline with Tracked Open Items | Close architecture discovery; preserve unresolved business and implementation decisions explicitly. |

These decisions constrain subsequent module design. Exact implementation choices not covered by them remain subject to specification and review.

---

## 3. Architectural Model

### 3.1 Modular Monolith

The application is a single Laravel 13 deployment unit containing explicitly separated business modules.

A module:

- Owns a defined set of business capabilities.
- Owns its business rules and authorized write paths.
- Exposes deliberate public contracts.
- May publish versioned events.
- Contains independently specifiable features.
- Registers its integrations with Laravel explicitly.
- Must not depend on another module's internal implementation.

The application is **not** a microservices system. Internal module calls normally execute in-process, without making HTTP requests back to the same application.

### 3.2 Logical structure

~~~text
Laravel 13 Backend
|
+-- Core Infrastructure
|   +-- Module Registry
|   +-- Tenant Context & Resolution
|   +-- Connection Management
|   +-- Security Infrastructure
|   +-- API Infrastructure
|   +-- Outbox & Queue Infrastructure
|   +-- Storage Abstraction
|   +-- Observability
|
+-- Business Modules
|   +-- Central Platform Domains
|   +-- Tenant Business Domains
|   +-- Cross-Context Capabilities
|
+-- Feature-Level Application Operations
|
+-- API / Console / Queue Entry Points
~~~

This is a logical view, not an instruction to create every directory shown.

### 3.3 Logical domains versus physical modules

The approved architecture identifies **16 logical Business Domains**.

This number is not a mandatory number of Laravel directories, Composer packages or service providers.

Physical grouping may be refined according to:

- Data and business ownership.
- Feature cohesion.
- Contract boundaries.
- Dependency direction.
- Testability.
- Operational complexity.

Combining two domains into one physical module must not eliminate their separate business responsibilities or data ownership.

Likewise, splitting one logical domain into multiple physical modules requires a reason beyond matching directory names.

---

## 4. Internal Module Organization

### 4.1 Recommended structure

~~~text
app/
|
+-- Core/
|   +-- Modules/
|   +-- Tenancy/
|   +-- Database/
|   +-- Security/
|   +-- API/
|   +-- Outbox/
|   +-- Storage/
|   +-- Observability/
|
+-- Modules/
    |
    +-- Inventory/
        |
        +-- Contracts/
        |
        +-- Domain/
        |   +-- Rules/
        |   +-- Services/
        |   +-- ValueObjects/
        |
        +-- Features/
        |   +-- ManageLocations/
        |   +-- StockMovements/
        |   +-- StockReservations/
        |   +-- StockTransfers/
        |
        +-- Infrastructure/
        |   +-- Persistence/
        |   +-- Integrations/
        |
        +-- Routes/
        +-- InventoryServiceProvider.php
~~~

**Status:** Illustrative implementation layout, not a committed directory tree.

Each feature may organize its application action, HTTP adapters, request validation and presentation resources according to its actual complexity.

Empty architectural layers should not be generated merely to maintain a template.

### 4.2 Layer responsibilities

| Layer | Responsibility |
| --- | --- |
| **HTTP / Delivery** | Controller, request validation, authentication handoff, API resources. |
| **Application** | Feature use cases, orchestration, transaction coordination and contract invocation. |
| **Domain** | Business invariants, policies, value objects and reusable domain behavior. |
| **Contracts** | Approved public capabilities, input/output types and compatibility boundaries. |
| **Infrastructure** | Persistence, Laravel adapters, external transport, storage and technical integrations. |

### 4.3 Lightweight Clean Architecture rules

1. Controllers must not contain critical business rules.
2. Feature Actions express a single meaningful use case or coherent business operation.
3. Reusable domain behavior remains within its owning module.
4. Domain logic must not depend on HTTP controllers or transport-specific objects.
5. Eloquent may be used inside module-owned persistence/application code where appropriate.
6. Repositories and interfaces are added when they establish a useful boundary; they are not mandatory for every model.
7. Application code may coordinate other modules through their public contracts.
8. Module internals must not be imported by unrelated modules.
9. Sensitive operations require explicit authorization, concurrency and audit behavior.
10. Long-lived processes must not retain mutable tenant context.

---

## 5. Execution Contexts

### 5.1 Central-Only

A Central-Only operation accesses platform-owned state under an appropriately authorized Central Context.

Typical capabilities:

- Platform tenant directory.
- SaaS plans and subscriptions.
- Platform administration.
- Shared merchant staff login identities.
- Tenant membership records.
- Central platform audit.

A Central-Only operation does not gain access to arbitrary merchant business data by virtue of executing centrally.

### 5.2 Tenant-Only

A Tenant-Only operation requires a verified tenant identity and an active tenant-local database connection.

Typical capabilities:

- Catalog management.
- Storefront customer records.
- Product pricing and discounts.
- Inventory.
- Orders.
- Direct sales.
- Fulfillment.
- Collection records.
- Returns and refunds.
- Tenant-local operational reporting.

Execution is restricted by effective authorization, subscription entitlement, resource ownership and Location scope.

### 5.3 Cross-Context Orchestrators

Cross-Context Orchestrators coordinate a workflow that needs both Central and Tenant systems.

Examples:

- Tenant provisioning.
- Staff access initialization.
- Tenant role bootstrap linked to central identity.
- SaaS entitlement propagation or reconciliation.
- Authorized platform-level aggregation.

They must use explicit, scoped contracts.

They must not assume Central and Tenant PostgreSQL databases share one ACID transaction.

A durable coordination state, retry policy, reconciliation procedure or appropriate compensation is required for workflows whose steps can partially succeed.

### 5.4 Context invariants

- Tenant resolution is trusted and fail-closed.
- Tenant identifiers from untrusted request inputs are not authorization proof.
- Staff membership is central, while tenant-specific roles and Location access are local.
- Storefront customer identity is tenant-local.
- Tenant-specific cache entries, jobs, events, files and exports remain segregated.
- Background consumers re-establish and validate their execution context.
- Context and connection state are cleaned after every request or job.

---

## 6. Business Domain Catalog

The table below defines the approved responsibility baseline.

| # | Logical Business Domain | Primary execution context | Main owned capabilities |
| --- | --- | --- | --- |
| 1 | Platform & Tenants | Central; provisioning orchestration | Tenant directory, merchant onboarding and lifecycle. |
| 2 | SaaS Subscriptions | Central | Plans, manually managed subscriptions, collections and entitlements. |
| 3 | Identity & Access | Central + Tenant | Central staff identities/memberships, tenant roles and permissions. |
| 4 | Merchant Settings & Content | Tenant | Store settings, localization, themes and basic content. |
| 5 | Catalog | Tenant | Products, variants, services, categories, attributes and imports. |
| 6 | Pricing & Promotions | Tenant | Pricing calculation, promotions, coupons and applicable tax rules. |
| 7 | Customers | Tenant | Independent shopper identities, guest data and addresses. |
| 8 | Inventory & Locations | Tenant | Locations, stock, reservations, adjustments and transfers. |
| 9 | Orders | Tenant | Online/manual orders, Pending Stock and order history. |
| 10 | Direct Sales | Tenant | Immediate direct sales and evidenced external-sale reconciliation. |
| 11 | Fulfillment | Tenant | Preparing, Ready, Hold, dispatch, delivery and pickup. |
| 12 | Payments & Collections | Tenant | Actual collections, verification and financial evidence. |
| 13 | Returns & Refunds | Tenant | Returns, exchanges, refund workflow and authorization. |
| 14 | Notifications | Central + Tenant | Transactional email intentions and operational in-app notifications. |
| 15 | Reporting & Exports | Central + Tenant | Authorized reports, read models and asynchronous exports. |
| 16 | Support | Cross-Context capability | Merchant help content, support requests and platform responses. |

**Audit & Activity** is a cross-cutting capability: tenant business audit belongs to tenant data, and platform administration audit belongs to central data.

---

## 7. Module Responsibilities and Boundaries

### 7.1 Platform & Tenants

**Owns:**

- Central Tenant Directory.
- Tenant identity and lifecycle status.
- Merchant onboarding initiated by Platform Super Admin.
- Provisioning coordination and readiness tracking.
- Tenant database reference and configuration metadata.
- Tenant activation eligibility checks.

**Does not own:**

- Merchant order or inventory records.
- Tenant staff roles.
- Shopper accounts.
- SaaS payment and subscription policy.

**Dependencies:** Central identity, provisioning infrastructure, database connection management and audit.

**Key boundary:** Merchant creation is not complete until tenant provisioning and readiness checks succeed. Retried provisioning must not duplicate resources.

### 7.2 SaaS Subscriptions

**Owns:**

- SaaS plans and subscribed feature definitions.
- Manual plan assignment, collection and renewal management.
- Central subscription records.
- Feature and limit entitlements.
- Authoritative eligibility for plan-controlled capabilities.

**Does not own:**

- Merchant employee permissions.
- Merchant customer payments for retail sales.
- Tenant catalog, stock or orders.

**Dependencies:** Platform & Tenants and Central Identity.

**Open details:** Trial periods, grace periods, exact quotas, suspension/reinstatement policies and commercial prices require their own business approvals.

**Key boundary:** An entitled feature still requires user permission and any applicable Location/business authorization.

### 7.3 Identity & Access

**Owns:**

- Platform administrator identities.
- Shared merchant staff login identities.
- Central tenant memberships.
- Tenant-local roles and permissions.
- Tenant-local staff Location scopes.
- Public authorization capabilities.

**Does not own:**

- SaaS plan limits.
- Orders or financial approval facts belonging to another module.
- Global shopper identities shared among unrelated merchants.

**Dependencies:** Tenant Context, Sanctum authentication infrastructure, subscription entitlement contracts and tenant-local persistence.

**Key boundary:** Staff identity, tenant membership and tenant authorization are separate checks. Storefront customer authentication uses isolated tenant customer identity.

### 7.4 Merchant Settings & Content

**Owns:**

- Merchant store identity and approved settings.
- Supported branding and theme configuration.
- Bilingual store content.
- Basic informational pages, navigation and footer.
- Merchant-specific operational settings within approved policy.

**Does not own:**

- SaaS plan configuration.
- Catalog business rules.
- Arbitrary visual page-builder capabilities.

**Dependencies:** Identity, entitlements, localization and file storage.

**Key boundary:** The initial MVP supports 2–3 predefined themes and basic content editing, not a general CMS or page builder.

### 7.5 Catalog

**Owns:**

- Products, Variants and Basic Services.
- Categories and reusable attributes.
- Product and Variant SKU definitions.
- Base-price definitions and eligible item configuration.
- Product publication and visibility rules.
- Product media references.
- Excel/CSV product and variant import with validation and preview.

**Does not own:**

- Actual stock or reservations.
- Order-specific final discounts/tax snapshots.
- Arbitrary free-text sales items in Manual Orders or Direct Sales.

**Dependencies:** Merchant Settings, authorization, entitlements and file storage.

**Key boundary:** Catalog defines what can be sold; Inventory determines stock availability; Pricing determines the approved calculated result.

### 7.6 Pricing & Promotions

**Owns:**

- Pricing calculation policies.
- Approved discounts and coupon rules.
- Pricing eligibility across sales channels.
- Relevant taxation calculations.
- Price breakdown contracts.

**Does not own:**

- Physical inventory.
- Historical rewriting of confirmed orders.
- Employee authorization identities.
- Payment collection records.

**Dependencies:** Catalog, SaaS entitlements, authorization and merchant settings.

**Key boundary:** Basic and Advanced promotion capabilities may be in MVP according to entitlements; Professional Promotions are deferred.

Registered customer requirements for coupon redemption must remain distinct from permission to perform guest checkout.

### 7.7 Customers

**Owns:**

- Tenant-local customer accounts.
- Registered customer profiles.
- Permitted guest details.
- Customer addresses and contact information.
- Customer profile search and approved management capabilities.

**Does not own:**

- Central merchant staff accounts.
- Financial settlement.
- Order state transitions.

**Dependencies:** Tenant customer authentication, merchant settings and authorization.

**Key boundary:** Different merchant stores do not automatically share customer accounts or profiles. Order history is exposed through authorized references/read contracts.

### 7.8 Inventory & Locations

**Owns:**

- Merchant Locations.
- On-hand, Reserved and Available quantities.
- Stock ledger and physical movements.
- Stock reservation/release/consumption operations.
- Authorized adjustments.
- Manual transfers, physical dispatch, in-transit status and actual receipt.
- Availability checks for one eligible Location.

**Does not own:**

- Order confirmation state.
- Customer consent.
- Final selling-price calculations.
- Fulfillment completion decisions.

**Dependencies:** Catalog, tenant authorization and Location permissions.

**Key boundary:** Inventory is the only owner of stock-changing operations. Aggregate stock across several Locations cannot satisfy a single-location fulfillment requirement.

### 7.9 Orders

**Owns:**

- Online order records.
- Staff-entered Manual Orders.
- Sales source-channel information for Manual Orders.
- Draft, confirmed and approved order lifecycle decisions.
- Manual Pending Stock processes.
- Line items and historical price/tax/discount snapshots.
- Customer agreement references and order-change history.

**Does not own:**

- Inventory ledger writes.
- Actual financial collection evidence.
- Physical dispatch operations.
- Direct Sale-specific immediate handover state machine.

**Dependencies:** Catalog, Pricing, Customers, Inventory, Collections, authorization and Fulfillment contracts.

**Key boundary:** Pending Stock does not imply reservation. Availability notifications and alternative-location suggestions do not confirm orders.

### 7.10 Direct Sales

**Owns:**

- Simplified immediate-sale workflow.
- Online-connected direct-sale records.
- Full-payment and immediate-handover coordination.
- Evidenced late recording of genuine external sales.
- Linked correction/reversal workflow for erroneous external-sale records.

**Does not own:**

- Full POS functionality or offline checkout.
- Split tender or credit sales.
- Unrestricted custom line items.
- Direct mutation of Inventory or Collections data.

**Dependencies:** Catalog, Pricing, Inventory, Collections and authorization.

**Key boundary:** Direct Sale is commercially distinct from ordinary Online/Manual Orders; shared services do not imply identical state machines.

### 7.11 Fulfillment

**Owns:**

- Preparing and packing workflow.
- Mandatory manual pack verification before Ready.
- Fulfillment Hold and authorized release.
- Physical carrier handover confirmation.
- Delivery confirmation from appropriate evidence.
- Store Pickup verification and handover.
- Manual delivery/carrier references.
- Approved fulfillment-mode and pickup-location changes.

**Does not own:**

- Inventory stock tables.
- Payment collection proof.
- Automatic cancellation or refund based solely on reminder expiry.
- Shipping carrier API integration in MVP.

**Dependencies:** Orders, Inventory, authorization, merchant settings and Notifications.

**Key boundary:** An authorized physical dispatch/pickup triggers one idempotent inventory consumption. Delivery completion and COD settlement remain separate.

### 7.12 Payments & Collections

**Owns:**

- Payment and collection records for commerce transactions.
- COD collection tracking and reconciliation.
- Authorized verification of external/manual payments.
- Evidence and status of actual financial movements.
- Financial result contracts for Orders, Direct Sales and post-sale workflows.

**Does not own:**

- Order fulfillment state.
- Actual inventory movements.
- Implicit integrated payment gateway behavior.
- Unverified claims that an external transaction succeeded.

**Dependencies:** Transaction references from Orders/Direct Sales, authorization and approved financial policies.

**Key boundary:** A recorded successful collection must represent a genuinely verified event. Approval to issue a refund is different from evidence that the refund was actually completed.

### 7.13 Returns & Refunds

**Owns:**

- Linked post-sale return and exchange cases.
- Return eligibility and authorization.
- Inspection and disposition workflow.
- Refund requests, approvals and post-sale case state.
- Prevention of duplicate or excessive return/refund claims.

**Does not own:**

- Direct stock ledger writes.
- Fabrication of successful financial refunds.
- Deleting or silently overwriting the original completed sale.

**Dependencies:** Orders, Direct Sales, Inventory, Collections and authorization.

**Key boundary:** Return acceptance, physical receipt, stock restocking, refund approval and successful refund execution are separate business facts.

The detailed separation between post-sale refund workflow and financial movement evidence must be made explicit in feature contracts.

### 7.14 Notifications

**Owns:**

- Approved tenant operational in-app notifications.
- Platform administrative notification intentions.
- Transactional email template and delivery workflow.
- Retry and delivery-status records.
- Notification subscription to authorized published events.

**Does not own:**

- Order confirmation or cancellation decisions.
- Customer consent decisions.
- Stock reservation.
- Marketing automation or WhatsApp/SMS integration.

**Dependencies:** Domain events, Transactional Outbox, identity context and email provider adapter.

**Key boundary:** Notifications are post-commit side effects. Email failure must not silently revert or falsify a committed commercial transaction.

### 7.15 Reporting & Exports

**Owns:**

- Tenant operational dashboards and authorized reporting queries.
- Platform-level permitted summary metrics.
- Report projections and read models.
- Excel/CSV export jobs.
- Generated report file metadata and access workflow.

**Does not own:**

- Primary commerce ledgers.
- Original order or stock mutations.
- Unrestricted cross-tenant database queries.

**Dependencies:** Approved domain read contracts, reporting projections, Horizon, authorization and local protected file storage.

**Key boundary:** Reporting data may be eventually consistent, but critical current stock, collection and order facts require authoritative transactional sources.

### 7.16 Support

**Owns at the business-capability level:**

- Merchant Help Center.
- Merchant-submitted support requests.
- Support request status and reply workflow.
- Authorized Platform Admin support responses.

**Does not automatically authorize:**

- Unrestricted platform access to merchant records.
- Reading protected order/customer data without explicit access policy.
- Cross-tenant attachment exposure.

**Dependencies:** Central identity, merchant membership/context, authorization and storage.

**Open data-ownership decision:** The authoritative location of support ticket records, attachment metadata and selected platform access projections must be determined in the Support feature specifications.

The MVP includes basic support requests, not a full enterprise helpdesk or live-chat platform.

---

## 8. Shared Kernel and Cross-Cutting Technical Capabilities

### 8.1 Shared Kernel principle

The Shared Kernel must remain small.

It may contain reusable technical infrastructure and selected stable primitives, but must not become a general-purpose business service container.

### 8.2 Permitted shared capabilities

| Capability | Ownership |
| --- | --- |
| Module Registry | Core |
| Tenant Context and Resolver | Core |
| Connection Manager | Core |
| API middleware and contract infrastructure | Core |
| Security infrastructure and correlation | Core |
| Transactional Outbox transport | Core |
| Tenant-aware queue infrastructure | Core |
| Local/S3-ready storage abstraction | Core |
| Logging and metrics adapters | Core |
| Stable Money/Currency primitives, when needed | Small shared primitive layer |
| Domain-specific pricing rules | Pricing |
| Stock reservation rules | Inventory |
| Order lifecycle rules | Orders |
| Refund eligibility workflow | Returns & Refunds |

### 8.3 Shared code admission rules

A component should be added to the Shared Kernel only when:

1. Its responsibilities are clearly technical or genuinely stable across domains.
2. Its behavior is not owned by one business module.
3. It avoids circular business dependencies.
4. Its contract and lifecycle are testable.
5. It does not retain request-specific tenant state.
6. It is not merely an attempt to eliminate harmless duplication.

Business logic that belongs to a module must stay in that module and be shared through its contracts.

---

## 9. Public Contracts and Module Communication

### 9.1 Contract ownership

Each module owns the public interfaces it offers.

A consumer may reference:

- The published interface.
- Approved input DTOs or immutable Value Objects.
- Documented result and error types.
- Versioned published events.

It must not import:

- Internal controllers.
- Private Feature Actions.
- Unpublished domain services.
- Internal Eloquent models.
- Private persistence adapters.
- Another module's migration implementation.

### 9.2 Contract examples

The following are **conceptual capabilities**, not approved PHP method signatures.

| Provider | Published capability | Typical consumer |
| --- | --- | --- |
| Catalog | Check Item Eligibility | Orders, Direct Sales |
| Pricing | Calculate Authorized Totals | Orders, Direct Sales |
| Inventory | Check Full-Order Availability | Orders, Direct Sales |
| Inventory | Reserve Stock | Orders |
| Inventory | Consume Reserved Stock | Fulfillment |
| Inventory | Record Stock Adjustment | Authorized operations |
| Collections | Verify Collection State | Orders, Direct Sales |
| Orders | Retrieve Authorized Order Snapshot | Fulfillment, Returns |
| Identity | Evaluate Authorized Action | Business modules |
| Subscriptions | Evaluate Entitlement | Tenant business modules |

A contract must describe its permission/context requirements, transactional expectations, idempotency guarantees and failure modes.

### 9.3 Synchronous communication

Use synchronous in-process contracts when the caller needs an immediate authoritative result.

Examples:

- Confirming Catalog eligibility.
- Recalculating an order price.
- Reserving inventory during confirmation.
- Verifying whether a collection can be treated as successful.
- Performing an authorized inventory consumption during dispatch.

Where all critical writes belong to the same Tenant Database, the responsible Application Orchestrator must coordinate a single coherent transaction.

Contracts must not independently commit partial business outcomes that leave the outer operation inconsistent.

### 9.4 Asynchronous communication

Use published events and queued consumers for recoverable post-commit effects.

Examples:

- Transactional email notification.
- Operational in-app alert.
- Reporting projection update.
- Noncritical downstream synchronization.

Asynchronous events must not replace required synchronous confirmation, stock or financial checks.

### 9.5 Avoiding circular dependencies

Circular dependency chains among implementation modules are prohibited.

When two modules appear to need each other's internals:

1. Identify the owning business operation.
2. Define narrow provider-owned contracts.
3. Move orchestration to the module/application use case responsible for the workflow.
4. Use events only for genuinely subsequent effects.
5. Extract a shared technical primitive only if it has no domain ownership.
6. Verify the resulting dependency graph through architecture tests.

A shared DTO library should not become a hidden dependency cycle.

---

## 10. Domain Events, Integration Events and Outbox

### 10.1 Event classifications

| Event category | Meaning |
| --- | --- |
| Internal Domain Event | Records a domain occurrence within the owning module. |
| Published Integration Event | Communicates a completed occurrence to other modules through an approved contract. |
| Technical/Operational Event | Communicates an infrastructure or orchestration lifecycle occurrence. |

Internal domain events are not automatically public.

Only deliberately published events become cross-module contracts.

### 10.2 Published event requirements

A published event should have an explicit contract containing the relevant elements:

- Unique Event ID.
- Event type.
- Schema version.
- Time of occurrence.
- Central or Tenant context.
- Tenant ID where applicable.
- Aggregate or operation reference.
- Correlation ID.
- Minimal authorized payload.

The exact serialization schema, field names and transport envelope remain for Queue/Integration Standards.

### 10.3 Transactional Outbox

The business change and corresponding outbox record must commit within the same authoritative database transaction when they form one atomic local operation.

For tenant business operations, the outbox record belongs to the relevant Tenant Database transaction.

For central platform operations, use the relevant Central Database transaction.

Publishing occurs after the local commit.

### 10.4 Consumer guarantees

Consumers must:

- Validate and reconstruct trusted execution context.
- Process their permitted event schema versions.
- Apply idempotency to prevent duplicate effects.
- Tolerate delivery retries.
- Handle out-of-order or delayed events where relevant.
- Record failed processing and support authorized recovery.
- Avoid leaking tenant context between worker executions.

No exactly-once external delivery guarantee is assumed.

### 10.5 Event versioning

Compatible changes may add optional documented fields when consumers remain compatible.

Breaking changes require a controlled event-schema transition, consumer review and contract tests.

A renamed business event or changed meaning cannot be treated as a harmless serialization adjustment.

### 10.6 Data minimization

Events must not carry unnecessary personal data, credentials, raw payment secrets or unrestricted customer records.

When a consumer needs additional authorized information, it should use a controlled contract rather than exposing excessive data in every event.

---

## 11. Data Ownership and Database Boundaries

### 11.1 Database placement

**Central PostgreSQL Database:**

- Platform entities.
- Tenant Directory.
- Shared merchant staff login identities.
- Memberships.
- SaaS plans/subscriptions/entitlements.
- Platform audit.

**Separate PostgreSQL Database per Tenant:**

- Tenant staff roles and Location scopes.
- Customer identities and profiles.
- Store settings.
- Catalog.
- Pricing/promotion records.
- Inventory.
- Orders.
- Direct Sales.
- Collections.
- Fulfillment.
- Returns and refunds.
- Tenant business audit.

Central and Tenant databases may initially reside on the same physical PostgreSQL instance. This does not remove their logical, credential or transaction boundaries.

### 11.2 One Writer Principle

Each module owns the authorized write path for its data.

Another module must not directly modify its tables or internal models.

Cross-module changes require Public Contracts.

The owner remains responsible for validating the operation, even when the caller previously performed validation.

### 11.3 Controlled reads

Permitted read patterns include:

- Public Query Contracts.
- Authorized read DTOs.
- Approved reporting projections.
- Carefully reviewed optimized database queries.

A performance-motivated direct read across module tables is an explicitly reviewed exception, not an unrestricted default.

The read owner must define:

- Required authorization.
- Tenant and Location scope.
- Supported fields.
- Schema dependency.
- Historical versus current-data semantics.
- Required regression and compatibility tests.

### 11.4 Foreign keys

Within one module and one database, normal PostgreSQL foreign keys and constraints may be used where appropriate.

Between modules in one Tenant Database, a foreign key may be considered when compatible with ownership and migration constraints, but it is not a replacement for business contracts.

Across Central and Tenant Databases, references require application-level validation and reconciliation. No cross-database foreign key or shared ACID transaction is assumed.

### 11.5 Historical snapshots

Confirmed financial/commercial records retain the snapshots necessary to explain the original transaction.

For example, an order must not be silently repriced because Catalog's current price later changes.

Relevant snapshots may include:

- Item/variant identity and descriptive details.
- Approved unit price.
- Discount and tax calculation results.
- Quantity and totals.
- Fulfillment commitments.
- Customer consent or authorization references.
- Original timestamps and correction lineage.

Exact fields belong to the relevant feature specifications.

### 11.6 Migrations

Central and Tenant migrations are managed separately.

Tenant migrations require version tracking, controlled batching, failure isolation and compatibility with concurrent Blue-Green application versions.

Registering a module does not automatically run its migrations on every application bootstrap.

Runtime database credentials must not have unrestricted provisioning or migration privileges.

---

## 12. Cross-Module Business Workflows

### 12.1 Manual Order confirmation

**Owning orchestrator:** Orders application use case.

Required coordination:

1. Verify actor, tenant and permitted Location.
2. Verify Catalog item eligibility.
3. Recalculate and validate applicable prices, taxes and discounts.
4. Review existing collections/deposits and customer agreement where required.
5. Verify complete order availability from one eligible Location.
6. Request atomic Inventory reservation.
7. Persist the approved Order confirmation and snapshots.
8. Record corresponding local Outbox events.
9. Commit the Tenant Database transaction.
10. Publish subsequent events for authorized consumers.

Failure to reserve the required stock must not leave the order successfully Confirmed.

Manual Pending Stock remains unconfirmed and unreserved until an authorized manual confirmation.

### 12.2 Online Order

The Orders module coordinates storefront order acceptance and confirmation using approved eligibility, pricing, customer, stock and payment/collection policies.

Online Orders do not enter the Manual Pending Stock workflow.

The first Pilot is COD-first, without an integrated online payment gateway.

Gateway-specific asynchronous payment states are not introduced merely because the architecture supports events and adapters.

### 12.3 Simplified Direct Sale

**Owning orchestrator:** Direct Sales application use case.

Required coordination:

1. Validate catalog-eligible sale items.
2. Calculate approved totals.
3. Check actor, tenant and Location eligibility.
4. Verify full collection under one permitted payment method.
5. Verify availability and actual handover requirements.
6. Persist consistent sale, collection and inventory effects.
7. Record auditable evidence and published events as appropriate.

A pending or unknown external electronic payment does not establish successful collection.

If an external collection succeeded but full immediate handover cannot be completed, the approved D-171 exception process applies. The system must not invent a completed sale or a successful automatic refund.

A late-entered genuine external sale is a separate evidenced reconciliation capability, not offline checkout.

### 12.4 Dispatch and delivery

**Owning physical workflow:** Fulfillment.

Orders retains the authoritative order record and relevant lifecycle outcome; Inventory owns actual stock movements.

Actual authorized carrier handover:

- Requires the appropriate Fulfillment checks.
- Performs inventory consumption once.
- Persists the corresponding business outcome consistently.
- Produces post-commit events where appropriate.

Delivery confirmation is a separate authorized action based on evidence.

Operational completion after delivery must not be confused with successful COD collection.

Store Pickup similarly requires actual recipient verification and one-time stock consumption.

### 12.5 Returns, restocking and refunds

**Owning post-sale workflow:** Returns & Refunds.

The workflow coordinates:

- Return authorization.
- Physical receipt and inspection.
- Inventory restocking eligibility.
- Refund approval.
- Actual financial execution evidence.
- Historical order/sale references.
- Audit and duplicate-effect prevention.

A return request does not automatically restock inventory.

A refund approval does not automatically mean money was returned.

Collections owns the authoritative evidence of the actual financial movement, while the post-sale module owns the corresponding case workflow.

### 12.6 Tenant provisioning

**Owning orchestration:** Platform & Tenants.

Provisioning spans Central and Tenant resources, so it cannot rely on one ACID transaction.

Required properties:

- Durable provisioning state.
- Restricted provisioning credentials.
- Retry-safe resource creation.
- Versioned tenant schema initialization.
- Readiness validation.
- Failure isolation and recoverability.
- Central activation only after successful checks.
- Auditable remediation and reconciliation.

The statuses Pending, Provisioning, Ready and Failed remain the approved conceptual lifecycle.

### 12.7 SaaS entitlements and business authorization

SaaS Subscriptions owns the authoritative subscription/entitlement decision.

Identity & Access owns identity, membership and relevant role/permission checks.

A business operation may proceed only if all applicable authorization layers pass.

Cached entitlements or permissions cannot silently override known authoritative restrictions.

---

## 13. Module Registry, Routes and Configuration

### 13.1 Explicit module registration

The application uses a lightweight explicit Module Registry.

Each module provides appropriate Laravel Service Provider registration for:

- Public contract bindings.
- Module-specific dependencies.
- API routes.
- Policies and authorization integration.
- Event listeners and consumers.
- Configuration and technical adapters.

Dynamic folder scanning is not the required module-loading mechanism.

A separate provider for every Feature is not mandatory.

### 13.2 Route surfaces

The logical API surfaces are:

| Surface | Execution context | Access model |
| --- | --- | --- |
| Platform API | Central | Authorized Platform Admin |
| Merchant Dashboard API | Tenant after validated membership | Merchant staff with entitlement and local permissions |
| Public Storefront API | Tenant from trusted storefront mapping | Public/guest operations where approved |
| Authenticated Customer API | Tenant | Tenant-local customer authentication |

All APIs follow the approved `/api/v1` versioning direction.

Final route naming and OpenAPI payload schemas belong to API Standards and feature specifications.

### 13.3 Middleware rules

Middleware and request handling must ensure:

- Trusted tenant resolution.
- Correct authentication guard/session.
- Membership validation where relevant.
- Entitlement checks.
- Tenant permission and Location scope.
- Resource ownership.
- Business-specific authorization.
- Failure-closed access on context mismatch.

Platform Admin access does not automatically bypass tenant business authorization.

### 13.4 Configuration ownership

| Configuration type | Authoritative location |
| --- | --- |
| Application infrastructure configuration | Laravel configuration and protected environment/secret management |
| Module technical configuration | Module-owned technical configuration |
| Merchant business settings | Tenant-owned business records |
| SaaS entitlements and subscription rules | Central authoritative records |

Registering a module does not mean a merchant is entitled to all its features.

Modules are registered at application bootstrap; feature availability is evaluated through entitlement and authorization contracts.

### 13.5 Long-lived runtime safety

Providers, singletons and shared services must not retain request-specific tenant connections or identity state.

Horizon workers and any future Octane runtime must explicitly restore and clean execution context.

The initial production runtime remains PHP-FPM. Octane is future-ready, not activated by this module architecture decision.

---

## 14. Storage, Queues, Audit and Reporting Boundaries

### 14.1 File storage — BA-15

The approved initial storage model is **Local Persistent Storage First + S3-Ready**.

Module-owned files must:

- Use a storage abstraction rather than fixed filesystem paths in business logic.
- Be placed on persistent host-mounted storage outside disposable containers.
- Respect Production/Development isolation.
- Preserve tenant ownership.
- Distinguish public media from private files.
- Re-authorize private file access.
- Support safe operation across Blue-Green releases.
- Allow later migration to S3-compatible storage without redefining business ownership.

The Module Registry or Shared Kernel must not grant general read access to all merchant files.

### 14.2 Queues

Redis and Horizon provide asynchronous transport.

Jobs are tenant-aware and idempotent where necessary.

Queue infrastructure is shared, but job execution authority and data access remain scoped to the appropriate owning domain and tenant context.

### 14.3 Audit

Tenant business audit records belong to tenant-local state.

Platform administrative audit belongs to central state.

Central security monitoring may collect privacy-limited operational signals but is not a license to copy entire tenant business records centrally.

### 14.4 Reporting

Reporting uses authorized read contracts and projections.

Heavy Excel/CSV exports use bounded asynchronous jobs and protected local storage.

Operational reports must distinguish sales, collections, returns and refunds accurately.

Late-recorded external sales require the actual historical business date and separate entry evidence under the applicable D-series decisions.

---

## 15. Dependency Rules and Delivery Direction

### 15.1 Dependency principles

The dependency graph is directed and must remain acyclic at the implementation-module level.

General preferred directions:

- Business modules depend on Core technical capabilities.
- Consumers depend on provider-owned Public Contracts.
- A business module does not depend on another module's private infrastructure.
- Shared primitives do not depend on business modules.
- Notifications and Reporting consume events/read contracts rather than becoming critical transactional dependencies.
- Cross-context orchestration is explicit and avoids bidirectional implementation coupling.

### 15.2 Dependency-sensitive integration points

| Relationship | Rule |
| --- | --- |
| Catalog ↔ Pricing | Catalog owns item/base-price definition; Pricing owns calculation and promotions. |
| Orders ↔ Inventory | Orders orchestrates; Inventory owns reservations and stock movements. |
| Orders ↔ Collections | Separate order and collection facts, coordinated through contracts without circular internal dependencies. |
| Direct Sales ↔ Collections/Inventory | One authorized workflow coordinates verified payment and immediate stock handover. |
| Orders ↔ Fulfillment ↔ Inventory | Physical execution and order lifecycle require one coherent operation; stock must not be decremented twice. |
| Returns ↔ Collections ↔ Inventory | Financial and physical outcomes are separate, with traceable coordination. |
| Tenant Platform ↔ Tenant DB | Cross-context orchestration uses retries/reconciliation, not presumed distributed ACID. |
| Reporting ↔ Operational Modules | Read-only contracts/projections, no operational source-of-truth takeover. |

### 15.3 Dependency checks

Architecture tests and PR review should detect:

- Forbidden internal module imports.
- Direct write access to another module's data.
- Circular implementation dependencies.
- Unapproved API calls between internal modules.
- Domain logic placed into generic shared infrastructure.
- Unauthorized Central/Tenant connection usage.
- Contract changes without consumer compatibility tests.

---

## 16. Dependency-Aware Implementation Sequence

This section provides an **architectural implementation sequence**, not an approved calendar or final feature-to-Pilot-wave assignment.

### F0 — Engineering Foundation

**Focus:**

- Laravel 13 application structure.
- Explicit Module Registry.
- Public Contract conventions.
- API versioning foundation.
- Basic test architecture.
- CI baseline and integration workflow.

**Exit evidence:** A minimal module can register successfully, expose its intended API contract, and pass module boundary/API tests.

### F1 — Tenancy Foundation

**Focus:**

- Central database bootstrap.
- Tenant Directory.
- Trusted tenant resolution.
- Central/Tenant Connection Manager.
- Restricted database access.
- Provisioning infrastructure.
- Tenant migrations and schema-version tracking.
- Minimal secure Platform Admin bootstrap required for initial onboarding.

**Exit evidence:** Authorized execution reaches only the intended Tenant DB; provisioning and migration retries are controlled; failures do not expose another tenant.

### F2 — Identity & Governance

**Focus:**

- Central staff identity.
- Tenant memberships.
- Tenant-local role and permission infrastructure.
- Location access.
- Entitlement enforcement foundations.
- Essential platform/tenant audit.

**Exit evidence:** Correct enforcement of identity, membership, entitlement, role, Location and special authorization boundaries.

### F3 — Operational Foundation

**Focus:**

- Redis and Horizon integration.
- Transactional Outbox.
- Tenant-aware background processing.
- Idempotency infrastructure.
- Local protected persistent file storage.
- Structured logging, tracing/correlation and operational metrics.

**Exit evidence:** Context isolation, retry safety, outbox durability, file authorization and operational failure visibility.

F0–F3 may overlap when needed to satisfy their dependencies. No commerce feature should bypass a Foundation safeguard merely because it has not been implemented yet.

### B1 — Merchant Core

**Focus:**

- Merchant Settings and Content.
- Catalog and Variant capabilities.
- Customer identity/profile workflows.
- Pricing, discounts and tax calculation foundation.
- Locations.

Parallel work is permitted where public contracts and prerequisites are stable.

### B2 — Inventory Core

**Focus:**

- Stock ledger.
- Per-Location availability.
- Reservations.
- Adjustments.
- Authorized transfers.
- Concurrency and overselling prevention.

### B3 — Commerce Core

**Focus:**

- Online Orders.
- Manual Orders.
- Manual Pending Stock.
- Collection records and verification contracts.
- Order snapshots and transactional coordination.

### B4 — Direct Sales & Fulfillment

**Focus:**

- Simplified Direct Sales.
- Evidenced external-sale reconciliation.
- Preparing, Hold, Ready and verification.
- Manual dispatch and delivery.
- Store Pickup and allowed conversions.

### B5 — Post-Sale & Operations

**Focus:**

- Returns and exchanges.
- Refund workflows.
- Reporting and exports.
- Basic support.
- Remaining approved MVP operational capabilities.

### Parallel capabilities

Notification templates, audit, authorization, monitoring, test fixtures and relevant post-sale safety must be developed alongside the workflows that depend on them.

A complete Pilot wave cannot omit required post-sale/legal/customer obligations merely because the corresponding domain appears later in this architectural sequence.

---

## 17. Feature-Level Spec Kit Planning

### 17.1 Feature boundary

A Feature is an independently specifiable business or technical capability with:

- Defined purpose and scope.
- Clear ownership.
- Approved business rule references.
- Dependencies.
- User or system acceptance scenarios.
- Negative and exceptional cases.
- Explicit architectural constraints.
- Test requirements.
- Reviewable implementation deliverables.

A Feature should not be so large that it becomes an entire uncontrolled module implementation, nor so small that it cannot represent a meaningful operation.

### 17.2 Module documentation

Each module should have an authoritative overview containing:

- Purpose and business ownership.
- Execution context.
- Data ownership.
- Public Contracts.
- Published Events.
- Allowed dependencies.
- Feature inventory.
- Authorization responsibilities.
- Testing responsibilities.
- Open decisions and risks.

This overview must reference the parent architectural documents rather than repeat the entire BA or D decision registers.

### 17.3 Feature documentation

Each Feature receives:

| Artifact | Purpose |
| --- | --- |
| `spec.md` | Business requirements, scenarios, invariants and acceptance criteria. |
| `plan.md` | Technical approach, persistence, contracts, security, transactions, integrations and testing. |
| `tasks.md` | Ordered implementation tasks, including tests and dependencies. |
| Tests | Executable verification of approved behavior and edge cases. |
| PR review evidence | Traceability, test results, contract/architecture impact and reviewer feedback. |

Clarification is required before implementing behavior that depends on an unresolved material business decision.

### 17.4 Specification location

The repository's Project Overview proposes module documentation under `docs/03-modules/`.

A single canonical location for executable Spec Kit feature artifacts must be selected during Foundation/Spec Kit setup.

**Recommended convention for review, not yet an approved path:**

~~~text
docs/
  03-modules/
    inventory/
      module-overview.md

specs/
  <feature-id>-<feature-name>/
    spec.md
    plan.md
    tasks.md
~~~

The module overview would reference the canonical Feature specification.

Do not maintain two independently editable copies of the same specification in different directories.

The concrete Feature IDs, Spec Kit configuration and final repository layout remain for the Foundation setup decision.

### 17.5 Feature traceability

Every Feature should record:

- Source D/OV/BA/MA decisions.
- Applicable open or deferred decisions.
- Owning Module.
- Required Contracts and Events.
- Tenant/Central context.
- Business acceptance cases.
- Automated tests.
- Affected existing features.
- PR and eventual release evidence.

### 17.6 Approval and implementation

The intended workflow is:

1. Feature Discovery.
2. Specification and clarification.
3. Specification review/approval.
4. Implementation Plan review.
5. Task breakdown.
6. Implementation on an authorized feature branch.
7. Automated verification.
8. PR review and integration.
9. Release promotion under approved gates.

Spec Kit preparation does not by itself authorize writing application code.

---

## 18. Module Testing and Quality Gates

### 18.1 Testing layers

| Testing layer | Objective |
| --- | --- |
| Unit / Domain Tests | Verify isolated invariants, calculations and state rules. |
| Feature / API Tests | Validate API behavior, authentication, authorization and responses. |
| Module Integration Tests | Validate module-owned persistence and external contract coordination. |
| Contract Tests | Verify public API, module contract and event schema compatibility. |
| Architecture Tests | Enforce module visibility and dependency rules. |
| Tenant Isolation Tests | Prevent cross-tenant access in HTTP, workers, cache, files and exports. |
| Concurrency & Idempotency Tests | Prevent duplicate or conflicting financial/inventory effects. |
| End-to-End Workflow Tests | Verify complete supported business journeys. |
| Migration & Deployment Tests | Verify batched schema evolution, compatibility and release safety. |
| Security / Recovery / Load Tests | Verify critical security, restoration and measured capacity gates. |

### 18.2 Critical cross-module scenarios

At minimum, risk-based suites must cover:

1. Two orders competing for the last available stock unit.
2. Repeated Manual Order confirmation.
3. Pending Stock receiving availability notification without automatic confirmation/reservation.
4. Repeated physical dispatch or pickup confirmation.
5. Actual delivery confirmation without assuming COD collection.
6. Duplicate externally verified payment recording attempts.
7. Direct Sale that cannot complete handover after externally verified collection.
8. Repeated refund requests or financial execution records.
9. Duplicate/reordered Outbox events.
10. Worker context switching between different tenants.
11. Tenant DB version drift during migrations.
12. Private file or export access attempted from another tenant.
13. Unauthorized Location or role-scoped operations.
14. Late-entered genuine external sale and associated correction/reversal.
15. Application rollback compatibility after a Blue-Green switch.

### 18.3 PR quality gates

Every implementation PR must satisfy the applicable gates:

- Approved specification and decision traceability.
- Required acceptance tests.
- Static analysis and code-quality checks.
- Module boundary compliance.
- Contract compatibility.
- Tenant/Location authorization tests.
- Concurrency/idempotency coverage for sensitive operations.
- Migration compatibility when schema changes.
- Regression checks for affected workflows.
- Human review at the appropriate risk level.

No arbitrary global code-coverage percentage is defined here.

Coverage metrics may support review but cannot replace business-critical negative and concurrency tests.

### 18.4 Repository workflow

The proposed repository convention is:

- `feature/*` branches originate from `development`.
- Feature PRs target `development`.
- Integration checks gate merge.
- Reviewed promotion goes from `development` to `main`.
- Production promotion follows the approved Blue-Green release process.

This document does not assert that GitHub Actions, branch protection or automated deployments are already operational.

---

## 19. Open Decisions and Deferred Items

The following matters remain explicitly unresolved or require later specification.

| Area | Current status | Required follow-up |
| --- | --- | --- |
| **BA-16 — Backup & Restore** | DEFERRED | Select and test recoverable off-VPS backups of Central/Tenant databases and local files; establish RPO/RTO before live Pilot. |
| **D-201 — Alternative-location follow-up** | OPEN | Decide dedicated internal marker versus ordinary communication notes within Orders/Fulfillment. |
| **Support data ownership** | OPEN DETAIL | Decide authoritative ticket/attachment storage and controlled Platform Admin access. |
| **Exact physical module grouping** | PLANNING DETAIL | Finalize only when concrete feature boundaries and contracts justify grouping. |
| **Spec Kit canonical path** | IMPLEMENTATION CONVENTION OPEN | Choose one authoritative structure during Foundation setup. |
| **SaaS commercial lifecycle** | OPEN DETAIL | Plan prices, trials, grace periods, suspension and reinstatement behavior. |
| **Permission Registry** | OPEN DETAIL | Exact permissions, default roles, merchant custom-role rules and privileged exceptions. |
| **Catalog imports** | OPEN DETAIL | File schema, limits, validation, duplicate handling and retries. |
| **Inventory exceptions** | OPEN DETAIL | Transfer loss/partial receipt, adjustment controls, reconciliation. |
| **Financial contracts** | OPEN DETAIL | Specific Collection/Refund DTOs, error states and manual evidence requirements. |
| **Tax and statutory documents** | LEGAL REVIEW REQUIRED | Determine actual Egyptian obligations for each enabled merchant activity and sales channel. |
| **Notification templates** | OPEN DETAIL | Approved triggers, failure handling, bilingual templates and sender/provider setup. |
| **Reporting definitions** | OPEN DETAIL | KPIs, historical attribution, freshness and export filtering. |
| **API wire format** | STANDARD PENDING | Exact response envelope, pagination, error schema and idempotency conventions. |
| **SLOs and capacity** | MEASUREMENT PENDING | Representative loads, measured thresholds and acceptance evidence. |
| **S3 migration** | FUTURE-READY ONLY | Initial backend remains local persistent storage under BA-15. |
| **Octane / dedicated search / PgBouncer** | FUTURE OPTIONS | Require independent measurement and safety evaluation, not implicitly enabled. |

A deferred item is not an approved choice.

The open details above are not permission to invent default business behavior during feature implementation.

---

## 20. Pilot Readiness and Release Boundaries

Module Architecture acceptance is separate from production readiness.

Before any merchant is activated with real customer and business data, the relevant Pilot release must demonstrate:

- Tenant isolation and least-privilege database access.
- Correct authorization and entitlement enforcement.
- Safe inventory, order, collection and refund operations.
- Idempotent handling of repeated and concurrent actions.
- Tested migrations and release compatibility.
- Production/Development environment isolation.
- Operational monitoring and incident handling.
- Tested database and file backups, off-VPS recoverability and approved recovery objectives.
- Verified statutory requirements for the enabled Egyptian merchant activities.
- Completed user acceptance of the actual enabled business workflows.
- Explicit release approval and rollback/stop criteria.

**BA-16 remains a blocking go-live dependency**, even though architecture documentation and Foundation planning may continue while it is deferred.

A phased Pilot must not enable a partial business workflow that lacks required operational, financial, legal or post-sale safeguards.

---

## 21. Final Architecture Compliance Checklist

### Module organization

- [x] Domain-oriented module architecture approved.
- [x] Feature-first lightweight internal layering approved.
- [x] No mandatory physical directory for each logical domain.
- [x] Core limited to technical infrastructure and stable primitives.

### Communication

- [x] Public Contracts required for cross-module business operations.
- [x] Critical synchronous coordination distinguished from asynchronous side effects.
- [x] Transactional Outbox and idempotent consumers required.
- [x] Circular implementation dependencies prohibited.
- [x] Versioned published events required.

### Data and execution

- [x] Central/Tenant context separation approved.
- [x] Database-per-tenant baseline preserved.
- [x] Module Data Ownership approved.
- [x] Authorized read contracts and read models approved.
- [x] Historical snapshots and tenant-local ACID boundaries preserved.
- [x] No assumed Central/Tenant distributed transaction.

### Laravel integration

- [x] Explicit Module Registry approved.
- [x] Module-owned Service Provider registration approved.
- [x] Routes, Policies, Bindings and Listeners explicitly registered.
- [x] API versioning aligned with `/api/v1`.
- [x] No unrestricted automatic module discovery.
- [x] No retained Tenant Context in long-lived providers.

### Delivery and verification

- [x] Feature-Level Spec Kit approach approved.
- [x] Risk-based test gates approved.
- [x] MA-09 final discovery review approved.
- [x] Document wording reviewed and signed off.
- [ ] Exact repository conventions verified or implemented.
- [ ] Individual Module Overviews and Feature Specifications prepared.
- [ ] Foundation implementation and CI tests completed.
- [ ] Pilot readiness gates demonstrated.

Checkboxes marked as approved describe **architecture decisions**, not completed implementation.

---

## 22. Next Documentation and Planning Steps

After publication of this approved document:

1. Keep the approved `module-architecture.md` in `development` as the module-level baseline.
2. Create the necessary focused architecture and standards documents for Tenancy, Data, Identity, API, Transactions, Queues, Security, Storage and Testing, without duplicating the whole architecture baseline.
3. Prepare the Foundation module and feature breakdown.
4. Establish the canonical Spec Kit directory convention.
5. Write independently reviewable Foundation Feature specifications.
6. Resolve the business and technical decisions that block each particular Feature before its implementation.
7. Continue through the approved domain dependency sequence, keeping Pilot activation gates separate from implementation progress.
8. Resolve BA-16 and demonstrate backup/recovery readiness before live Merchant Pilot activation.

**Architecture decisions remain traceable to MA-01 through MA-09, BA-01 through BA-30, approved OV decisions and the authoritative D-series business register.**

**End of Module Architecture — Approved Version 1.0.**

Implementation, PR creation, deployment and Pilot activation each require their own applicable authorization and readiness checks.
