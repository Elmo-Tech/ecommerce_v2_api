# E-commerce SaaS V2 — Data Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/data-architecture.md`  
**Version:** 1.0 — Approved Data Architecture Baseline  
**Prepared:** 2026-11-17  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — DA-07 (A)**  
**Implementation Status:** Not implemented or verified

> **Reading rule:** This document approves a lightweight PostgreSQL data architecture, not specific tables, feature schemas, completed migrations, performance results, or permission to activate live merchants. The Backend, Module, and Tenancy Architecture baselines and approved business decisions remain authoritative within their respective domains.

---

## 1. Purpose and Boundaries

Define consistent, MVP-first data conventions for E-commerce SaaS V2, implemented through **Laravel 13**, **PostgreSQL**, and the conditionally selected **`stancl/tenancy v3.10.1`**.

This document covers database schema organization, identifiers, relationships, money and time, historical data, indexing, data lifecycle, and safe schema changes. It does **not** prescribe an ERD, a universal repository pattern, module-specific table definitions, retention periods, or an additional data framework.

Read with:

- [Backend Architecture](./backend-architecture.md) — system-wide BA decisions and business invariants.
- [Module Architecture](./module-architecture.md) — data ownership, One Writer Principle, and module boundaries.
- [Tenancy Architecture](./tenancy-architecture.md) — Central/Tenant connections, PostgreSQL roles, provisioning, migrations, and validation gates.

## 2. Approved Decisions — DA-01 to DA-07

| Decision | Approved Option | Direction |
| --- | --- | --- |
| **DA-01** | **A — Single PostgreSQL Schema per Database** | Use one `public` schema in each Central/Tenant database; enforce module ownership in Laravel. |
| **DA-02** | **A — BIGINT by Default + UUIDv7 Where Needed** | Use BIGINT primary keys for local records; use UUIDv7 for selected central identities and cross-database references when justified. |
| **DA-03** | **A — Pragmatic Constraints + Controlled Foreign Keys** | Use PostgreSQL constraints and suitable FKs, including reviewed cross-module FKs within one database, without violating module ownership. |
| **DA-04** | **A — Simple Money & Time + Targeted Historical Snapshots** | Store final money in minor units, use UTC-aware timestamps, and preserve critical historical evidence. |
| **DA-05** | **A — Query-Driven Indexing + Measure Before Optimizing** | Index known access paths; measure and optimize before adding specialized infrastructure. |
| **DA-06** | **A — Feature-Specific Lifecycle + Safe Native Changes** | Apply archive/delete/retention rules by feature; use compatible Laravel/Stancl schema changes and safe backfills. |
| **DA-07** | **A — Lightweight PostgreSQL Data Architecture Baseline** | Approve DA-01…DA-06 as the data architecture baseline without overengineering. |

## 3. Database Placement and Schema — DA-01

The existing **database-per-tenant** model remains unchanged:

- **Central PostgreSQL Database:** Tenant Directory, platform administrators, shared staff identities and memberships, SaaS subscriptions/entitlements, and platform audit.
- **Independent PostgreSQL Database per merchant:** local staff roles and Location scopes, storefront customers, catalog, inventory, orders, direct sales, collections, fulfillment, returns/refunds, and tenant audit.

Each database starts with a single **`public` schema**. Do not introduce a PostgreSQL schema per Laravel business module for the MVP.

Business modules retain explicit ownership of their writes and public contracts. A shared `public` schema does not authorize one module to write another module's tables.

The `public` schema name does **not** make data publicly accessible. Enforce appropriate PostgreSQL `PUBLIC`, database/schema/table/sequence privileges and **least-privilege runtime roles per tenant**, as required by BA-21 and Tenancy Architecture.

## 4. Primary Keys and Identifiers — DA-02

- Use **BIGINT auto-incrementing primary keys** as the default for records local to one database, following suitable Laravel conventions.
- Use **UUIDv7** for identities/references that must remain unambiguous across Central and Tenant databases, or across integration boundaries, **only when needed**.
- Do not add both BIGINT and UUID columns to every table without a feature-level requirement.
- Numeric IDs may legitimately repeat across separate tenant databases; an ID alone is never proof of tenant scope or authorization.
- Keep customer-facing business reference numbers (for example, order numbers) distinct from internal primary keys, with uniqueness and format decided by the owning feature.

Cross-database references require explicit context and application-level validation; PostgreSQL cannot enforce a foreign key between separate Central and Tenant databases in the approved design.

## 5. Relationships and Integrity — DA-03

Use PostgreSQL-native integrity rules where appropriate:

- **Primary Keys, Foreign Keys, NOT NULL, UNIQUE, and CHECK constraints** for stable data invariants.
- **Foreign Keys within a module and one database** when relationships justify them.
- **Cross-module Foreign Keys within the same Tenant Database** only where the relationship, lifecycle, ownership, and migration dependencies have been reviewed.
- **No Foreign Keys across Central and Tenant databases.** Validate and reconcile those references in application workflows.
- Never use an indiscriminate **ON DELETE CASCADE** that would erase legitimate orders, collections, refunds, historical evidence, or required audit records.
- Use database constraints and concurrency-safe transactions for critical integrity, while the owning module enforces authorization and business rules.

**One Writer Principle remains mandatory.** An FK does not grant another module permission to mutate the owning module's records. Cross-module writes use approved public contracts, and cross-context operations do not assume a shared ACID transaction.

Exact relationships, constraint expressions, and delete actions belong in the relevant Feature Specification and migration.

## 6. Money, Time, and Historical Records — DA-04

### 6.1 Money

- Store **finalized monetary amounts** as **BIGINT integer minor units**, according to the applicable currency exponent.
- EGP is the initial Pilot currency; do not assume all future currencies have exactly two decimal places.
- Do not use FLOAT or DOUBLE for monetary calculations.
- Use appropriately precise decimal arithmetic for intermediate prices, discounts, tax, and calculations, then apply the feature-approved rounding rules before persisting finalized amounts.
- Preserve currency context and financially significant calculation snapshots where required.

### 6.2 Time

- Use PostgreSQL **TIMESTAMPTZ** for instants and normalize application persistence to **UTC**.
- Convert to the appropriate display timezone at the application/API presentation boundary.
- Use **DATE** for calendar dates that do not represent an instant.
- Retain ordinary `created_at`/`updated_at` where appropriate. Distinguish `occurred_at` (when an event actually happened) from `recorded_at` (when the system recorded it) **only for workflows that require this evidence**, such as approved late entry of a genuine external sale.
- Do not impose a generic event-time model on every table.

### 6.3 Historical evidence

Confirmed commercial and financial records retain the required **historical snapshots** of approved item identity/descriptions, quantity, price, discounts/taxes, totals, fulfillment commitments, and relevant authorization/consent details, as specified by the owning feature.

Changes to a product's current name or price must not silently rewrite an already confirmed order.

Preserve original collection, stock, refund, and correction evidence. Apply authorized **corrections/reversals** instead of destructive mutation of historical financial facts. Operational state transitions remain allowed under their approved business rules.

No system-wide Event Sourcing, generic temporal tables, or universal row-versioning framework is selected for the MVP.

## 7. Indexes and Query Performance — DA-05

Adopt **PostgreSQL-first, query-driven indexing**:

- Keep necessary **primary-key and unique indexes**.
- Add suitable **B-tree and composite indexes** for identified feature queries, such as authorized filters, lookups, and ordering.
- Review FK-side indexing where actual access patterns and delete/update behavior justify it; do not assume every column needs an index.
- Require bounded **pagination** for list APIs; avoid unbounded tenant-wide reads.
- Prevent **N+1 queries**, use appropriate eager loading, return only needed fields, and keep tenant/Location authorization in query paths.
- Use representative data and **EXPLAIN ANALYZE** or relevant slow-query measurements to validate nontrivial optimizations.
- Evaluate PostgreSQL full-text search or `pg_trgm` only when justified and tested for Arabic/English behavior; no external search engine is selected for first Pilot.
- Use bounded Horizon jobs for heavy CSV/Excel exports and reporting, preserving tenant and Location authorization.

Do **not** add speculative indexes, upfront table partitioning, read replicas, or specialized search infrastructure without measured need. No fixed performance guarantee is asserted before validation.

## 8. Data Lifecycle and Safe Changes — DA-06

Each owning feature defines whether a record can be edited, archived, soft-deleted, permanently deleted, or must remain historically preserved.

- **Archive/soft delete only when justified** by the record's lifecycle; never apply `softDeletes()` indiscriminately.
- Retain legitimate order, sale, collection, inventory, refund, and audit history according to applicable business and legal requirements.
- Use reviewed correction/reversal paths instead of deleting historical financial effects.
- Address customer privacy, lawful deletion, and retention exceptions in the appropriate feature and compliance specifications. **Soft deletion is not equivalent to erasure.**
- Expired subscriptions do **not** automatically delete a merchant's Tenant Database.
- Do not introduce a generic data-lifecycle framework or universal retention period.

### Safe migrations and backfills

Use **Laravel Native Migrations plus the conditionally adopted Stancl tenant migration capabilities**, keeping Central and Tenant migrations separate.

- Track tenant schema versions and rollout outcomes.
- Preflight compatibility; migrate tenants in controlled batches with failure isolation and safe retries.
- Avoid concurrent migrations for the same tenant.
- Prefer **expand-first, backward-compatible** schema changes when old and new application versions can coexist during Blue-Green deployment.
- Use bounded, resumable data backfills when necessary; validate completion before removing old fields or assumptions.
- Review destructive changes separately. Switching application traffic back is **not** a database rollback.
- Runtime credentials must not gain unrestricted migration or provisioning privileges.

A simple, safe additive migration does not require a multi-stage rollout when compatibility and risk do not justify it.

## 9. Validation and Release Boundaries

Feature Specifications must define the actual tables, constraints, query shapes, data retention, authorization, historical snapshots, and tests needed for their business rules.

Focused validation must cover:

- Central/tenant and two-tenant isolation with real PostgreSQL roles and connections.
- Constraint violations, uniqueness, foreign-key policies, and appropriate concurrency/idempotency behavior.
- Minor-unit money arithmetic, rounding, timestamps/timezone handling, and historical snapshot integrity.
- Pagination, representative query plans, and heavy export isolation.
- Mixed tenant migration versions, failed batches, safe retries, and Blue-Green schema compatibility.

**Cross-tenant access, loss of critical historical evidence, or duplicate financial/stock effects are release blockers.** Architecture approval does not mean tests have passed.

## 10. Fixed Boundaries and Open Items

- **TA-08 / Stancl v3.10.1:** conditionally approved; Laravel 13, PostgreSQL per-tenant runtime roles, Central/Tenant authentication, Horizon, provisioning, and migrations remain **PENDING VALIDATION**.
- **BA-15:** **APPROVED — Local Persistent Storage First + S3-Ready**. Tenant files/exports use authorized Laravel Storage references on persistent, isolated production/development mounted storage; avoid hard-coded host paths.
- **BA-16:** **DEFERRED** as an architecture choice. Nevertheless, **verified off-server backups and restoration** of Central/Tenant PostgreSQL data and required local files are mandatory **before live Pilot activation**; RPO/RTO remain to be established.
- **D-201:** **OPEN** business decision, outside this data architecture document.
- Specific schemas, legal retention periods, performance targets, physical partitioning, and implementation configuration remain within later features, standards, or measured Pilot gates.

**No scope expansion:** No new database technology, generic lifecycle/versioning framework, event sourcing, PgBouncer, or additional search service is approved by this document.

---

## 11. Approval and Implementation Boundary

**Architecture decision:** **DA-07 A — APPROVED**. DA-01 through DA-06 are the agreed lightweight PostgreSQL data conventions.

This document is the approved **design baseline** for future Feature Specifications; it does **not** authorize feature implementation, production deployment, or live merchant activation. Outstanding validation and Pilot gates remain in effect.

**End of Data Architecture — Version 1.0.**
