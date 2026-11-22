# E-commerce SaaS V2 — Database Standards

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target file:** `docs/02-standards/database-standards.md`  
**Branch:** `development`  
**Version:** 1.0 — Approved Database Engineering Standards  
**Prepared:** 2026-11-21  
**Decision status:** **APPROVED — DB-01 through DB-11 (Option A)**  
**Implementation status:** **DOCUMENTATION ONLY — NOT IMPLEMENTED, CONFIGURED, BENCHMARKED, TESTED OR DEPLOYED**

> **Critical reading rule:** Approval establishes the standards that future Laravel/Spec Kit features must follow. It does **not** approve a final physical schema/ERD, establish database credentials, install `stancl/tenancy`, prove a release gate, authorize migrations against live data, or permit merchant activation. **BA-16 remains DEFERRED**, BA-30 **G3 remains BLOCKED** for live Pilot, and **D-201 remains OPEN**.

---

## 1. Purpose, Scope, Authority and Non-Goals

### 1.1 Purpose

Define consistent, pragmatic engineering standards for a **Laravel 13 API-first Modular Monolith** using **PostgreSQL**, a **Central Database** and a physically **independent PostgreSQL Database per merchant Tenant**. Future Feature Specs, Migrations, Eloquent queries, jobs, tests and PR reviews must implement these conventions where applicable.

The standards cover migration ownership; identifiers, relationships and constraints; column types; query-led indexing; transactions, locking and business idempotency; compatible schema releases; query and connection lifecycle; historical evidence; database security; monitoring; test evidence and release boundaries.

### 1.2 Governing documents

| Source | Binding concern |
| --- | --- |
| [Backend Architecture](../01-architecture/backend-architecture.md) | BA-02/03/06/08/20/21/24/25/29/30: Tenant separation, access, migrations, ACID, credentials, telemetry and Pilot gates |
| [Data Architecture](../01-architecture/data-architecture.md) | DA-01…DA-07: schema placement, IDs, constraints, money/time, indexing and safe lifecycle |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | TA-01…TA-08: conditional Stancl, trusted context, independent runtime DB roles, migrations, workers |
| [Transactions & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | TC-01…TC-08: transaction ownership, targeted concurrency, idempotency, outbox and recovery |
| [Module Architecture](../01-architecture/module-architecture.md) | Module ownership, One Writer Principle, public contracts and application orchestration |
| [Infrastructure & Deployment Architecture](../01-architecture/infrastructure-architecture.md) | INF-09/10/11: release coordination, batch migration, Blue-Green, monitoring and measured capacity |
| [Queue/Jobs/Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | Bounded Horizon workers, trusted Tenant jobs, outbox relay and consumer idempotency |
| [Code Standards](./code-standards.md) | CS-05 int-backed PHP Enums, PostgreSQL SMALLINT/BOOLEAN and safe programming conventions |
| [API Standards](./api-standards.md) | AS-04 filtering/pagination, AS-06 idempotency and scoped API queries |
| [Authentication Standards](./authentication-standards.md) | Trusted Central identity and session boundaries |
| [Authorization Standards](./authorization-standards.md) | Tenant/Location/resource scoping, no Platform bypass, sensitive action controls |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 and unresolved or deferred decisions |

**Authority order:** binding approved D-series business decisions define what operations are allowed; OV/MVP boundaries define scope; approved Architecture defines system/data ownership; Standards define engineering practice; each reviewed Feature `spec.md` / `plan.md` / `tasks.md` fixes its exact schema, invariants, code and tests. Examples below never create new business rules, statuses, amounts, limits, permissions or physical table contracts.

### 1.3 Fixed physical baseline

- **Central PostgreSQL DB:** Platform/Tenant Directory, Central Workforce identities and memberships, SaaS entitlements/subscriptions and platform audit.
- **Independent PostgreSQL DB per Tenant:** Tenant-local staff Roles/Location scopes, storefront customers, catalog, inventory, orders, direct sales, fulfillment, collections, returns/refunds, Tenant audit and appropriate transactional outbox.
- **One `public` PostgreSQL schema per database** initially; business module ownership is enforced by application boundaries, not a schema per module.
- **Shared PostgreSQL server initially**; database-per-Tenant does not imply a database server/container or permanently open connection for every Tenant.
- Laravel native database features; **`stancl/tenancy v3.10.1` is conditionally selected**, pending Laravel 13/PostgreSQL/Horizon/provisioning/security integration validation.
- Initial Pilot API runtime is **PHP-FPM**; Octane is a separate, safety-gated future possibility, not an assumption for database implementation.

### 1.4 Explicit non-goals

No universal Repository/Unit of Work framework, distributed ACID, generic Saga engine, Kafka/event sourcing, per-module PostgreSQL schemas, blanket UUID, blanket Soft Deletes, global optimistic row versioning, globally `SERIALIZABLE` transactions, application-level replacement for PostgreSQL integrity, speculative indexes, upfront partitioning, read replicas, PgBouncer, external search service, fixed connection counts, numeric SLO/RPO/RTO, default legal retention periods, or backup provider/strategy selected by this document.

---

## 2. Approved Decision Register — DB-01 to DB-11

| Decision | Approved Option A | Binding engineering direction |
| --- | --- | --- |
| **DB-01** | Explicit Central/Tenant Migration Tracks + Module Ownership | Separate Laravel migrations; controlled Tenant runner; shared approved migration definitions for provisioning |
| **DB-02** | BIGINT Default + Selective UUIDv7 + Constraints | Pragmatic same-DB FKs, PostgreSQL constraints, safe deletion and no cross-database FK |
| **DB-03** | Strict Types + Nullability + Naming | Consistent `snake_case`, SMALLINT Enum state, BOOLEAN true/false, BIGINT minor-unit money, TIMESTAMPTZ |
| **DB-04** | Query-Driven Indexing + Measured Optimization | Suitable B-tree/composite indexes, real plan analysis, no speculative over-indexing |
| **DB-05** | Native Transactions + Targeted Locking + Idempotency | Owning use-case local ACID; row/conditional concurrency; stable lock order; bounded safe retries |
| **DB-06** | Expand/Backfill/Contract + Controlled Rollout | Compatible Blue-Green changes, per-Tenant schema tracking, resumable work and safe PostgreSQL DDL |
| **DB-07** | Tenant-Aware Lazy Connections + Bounded Queries | Trusted context/cleanup, smart pagination, prevent N+1, measured total connection budgets |
| **DB-08** | Business-Aware Lifecycle + Historical Evidence | Snapshots, Archive/Soft/Hard Delete only as justified, auditable corrections and reversals |
| **DB-09** | Dedicated Least-Privilege Runtime Role per Tenant | Separate Central/migration/provisioning privileges, secure secrets and actual isolation tests |
| **DB-10** | Native Monitoring + Risk-Based Validation Gates | PostgreSQL/Laravel/Horizon signals, real concurrency and Tenant tests, measured capacity/release acceptance |
| **DB-11** | Final Cross-Document Review + Publication | Consolidate DB-01…DB-10 in this document on `development`; documentation-only approval |

**Approval scope:** these options were discussed and approved individually. No unimplemented sample is a tested production pattern or an actual approved Feature schema.

---

## 3. DB-01 — Migration Structure, Ownership and Execution

### 3.1 Central and Tenant tracks

Keep migration definitions **physically and operationally separate**. The following is an **illustrative folder convention**, not a verified final Laravel/Stancl directory configuration:

~~~text
database/
  migrations/
    central/       # Central-only tables and platform data
    tenant/        # Tenant-local domain tables
~~~

Confirm actual paths, migrator registration, Laravel 13 commands and Stancl hooks in the Foundation implementation before locking the physical layout. The approved requirement is separation, not the example path names.

A module owns schema changes to its tables even when migrations live in a shared Central/Tenant track. Document the owning module, dependency requirements, backward compatibility, data impact and test case in the owning Feature Spec and PR. Do not require every Module to invent its own migrator or independent migration directory.

### 3.2 Placement and lifecycle

- **Central track:** identity, Tenant Directory, platform memberships, platform/SaaS state and Central-owned audit/outbox when relevant.
- **Tenant track:** Tenant-local Roles/Location scope, Catalog, Inventory, Orders, Payments/Collections, Returns/Refunds and owned operational/audit/outbox data.
- **No cross-DB foreign key.** Central references carried into Tenant data require a documented validated context and reconciliation policy, not a fictional FK.
- **Provisioning and upgrade** apply the same reviewed Tenant migration definitions; no separate hidden schema bootstrap that diverges from existing Tenant upgrades.
- Migrations are versioned in each database, and a secured Central rollout record tracks target release, Tenant status, failures and progress. Concrete table names/schema remain to specify.
- A dedicated migration runner is invoked by an authorized release process, **never automatically by every API/Horizon/Scheduler container on startup**.
- Prevent concurrent migrations against the same Tenant; retries examine committed migration state and partial DDL effects before resuming.
- Apply necessary Central migrations under Central migration authorization, then bounded Tenant batches according to dependencies and compatibility.

### 3.3 Failure guarantees

A Tenant migration failure is reported and isolated where safely compatible; it is never recorded as successful. A release cannot activate incompatible code for affected Tenant schema states. A Tenant is not marked technically `Ready` before migration/bootstrap and validation succeed; `Ready` alone does not activate paid entitlements.

**DB-01 acceptance:** separated tracks; migration ownership review; controlled version/progress tracking; provisioning parity; safe retries; no concurrent same-Tenant migration; privilege separation; compatibility-aware release.

---

## 4. DB-02 — Primary Keys, Relationships and Database Constraints

### 4.1 Identifier strategy

| Data/relation | Standard |
| --- | --- |
| Local row in one Central or Tenant DB | PostgreSQL auto-increment `BIGINT` primary key by default |
| Central identity or cross-DB/integration reference requiring global unambiguity | **Selective UUIDv7**, only with an explicit requirement |
| Business-visible order/reference number | Separate from internal PK; format and uniqueness scope decided by Feature |
| Tenant-local row reference | Resolve within **trusted Tenant context**, never trust a numeric ID as authorization |
| Central-to-Tenant or Tenant-to-Tenant reference | No PostgreSQL cross-database FK; validate scope and reconcile at application boundary |

Tenant A and Tenant B may each have local `id = 100`. Identical numeric values do **not** identify the same resource. Avoid duplicating both BIGINT and UUID on every table without a reviewed use case.

### FBR-02-07 — Public BIGINT and Selective UUIDv7 Identity

The physical key strategy remains **BIGINT by default** with selective UUIDv7 when a genuine cross-database/integration identity is needed. At every JSON API boundary, BIGINT IDs and related references are **canonical base-10 decimal strings**, never JS-unsafe JSON Numbers. Reject overflow, noncanonical or cross-Tenant identifiers; Tenant-local numeric collisions are expected and do not confer global identity. Add wire-contract serialization tests independently of FK/index tests.

### 4.2 Relational integrity

Use PostgreSQL `PRIMARY KEY`, `FOREIGN KEY`, `NOT NULL`, `UNIQUE` and `CHECK` for stable invariants where appropriate; complement them with Domain and authorization checks.

- Same-module, same-database FKs are preferred when the relationship and lifecycle justify them.
- Cross-module **same-Tenant-DB** FKs are **permitted selectively** after reviewing dependencies, ownership, delete rules and migration ordering; not every cross-module reference requires an FK.
- Cross-DB Central/Tenant FKs are **not possible in this approved layout**.
- An FK does not grant permission to write another Module's table. The **One Writer Principle** and public contracts remain in force.
- Use unique constraints/partial uniqueness for actual business identifiers and once-only operation identities; uniqueness scope must be explicit.
- Use `CHECK` for row-local structural rules (e.g. positive quantities where approved). Cross-row sums, permissions, inventory eligibility and financial limits typically require transactional Domain logic.
- Do not assume a PostgreSQL FK automatically indexes the referencing/child column. Review its query/lock/update/delete workload under DB-04.

**Illustrative DDL only — not an approved Orders schema:**

~~~sql
CREATE TABLE example_order_items (
    id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    order_id BIGINT NOT NULL,
    quantity INTEGER NOT NULL,
    CONSTRAINT fk_example_order
        FOREIGN KEY (order_id) REFERENCES example_orders(id),
    CONSTRAINT chk_example_quantity
        CHECK (quantity > 0)
);
~~~

Review each `ON DELETE` choice. Never use indiscriminate cascades that erase orders, collections, stock movements, refunds or required audit evidence. A restrictive reference, nullable historical reference with independent snapshot, or tightly reviewed cascade may be appropriate in different features.

### 4.3 Database is a backstop, not authorization

A valid FK says the row exists **in the same database**, not that the requester is allowed to act on it, that a Location is authorized, or that a payment occurred. Apply current Tenant, membership, Role, Location and resource authorization in the approved Application/Policy/query boundary.

**DB-02 acceptance:** predictable PK types, reviewed unique/check/FK rules, proper same-DB boundaries, safe delete actions and real constraint/race tests.

---

## 5. DB-03 — Column Types, Naming, NULL and Defaults

### 5.1 Standard physical types

| Domain meaning | PostgreSQL | Laravel/engineering rule |
| --- | --- | --- |
| Local primary ID | `BIGINT` | Laravel conventional big integer identity where applicable |
| Selective globally unique reference | `UUID` | UUIDv7 only when justified |
| Project-defined multi-state Enum | `SMALLINT` | **Int-backed PHP Enum**, explicit stable case values (CS-05) |
| True/false fact | `BOOLEAN` | Native boolean, not pseudo-status Enum |
| Finalized financial amount | `BIGINT` | Integer **minor units**, currency-aware |
| Intermediate precise computation | Precise decimal arithmetic / reviewed `NUMERIC(p,s)` if persisted | Never float/double for money; Feature-approved rounding |
| Timestamp representing an instant | `TIMESTAMPTZ` | Normalize persistence to UTC |
| Calendar-only date | `DATE` | No artificial UTC conversion |
| Normal text | `TEXT` or `VARCHAR(n)` | Length limit only when a real invariant requires it |
| Structured optional metadata | `JSONB` when justified | Not a substitute for core normalized relations |
| Simple integer quantity | Appropriate `INTEGER`/`BIGINT`/`NUMERIC` | Depends on UOM/fractional quantity rules in Feature Spec |

PostgreSQL **does not have `TINYINT`**. Use `SMALLINT` for project state Enums and `BOOLEAN` for genuine binary flags. A Feature must review any status requiring a different representable range. Do not persist raw arbitrary string states just to avoid modeling a real project Enum.

### 5.2 Enum evolution and API independence

- Declare PHP Enum cases with explicit int values; never depend on declaration order.
- Do not silently renumber cases or reuse retired numeric values.
- Validate legal states and transitions in Domain logic; apply reviewed constraints when suitable.
- When a public API/provider uses string status values, map intentionally at its adapter/Resource boundary. Internal `SMALLINT` storage does **not** silently change the agreed wire format.
- Any Enum change reviews old records, OpenAPI, reports, caches, exports, events, jobs and Blue-Green readers/writers.

### 5.3 Money, timezone and snapshots

Final financial amounts use BIGINT minor units with a stored/authoritative currency context. EGP is the initial Egypt Pilot currency, **not a universal two-decimal assumption**. Use precise intermediate calculations and approved rounding before final persistence. Preserve necessary item, price, discount/tax and collection history per owning Feature.

Use `created_at` and `updated_at` where appropriate. When physical event time differs from recording time and the workflow requires evidence, model `occurred_at` vs `recorded_at` intentionally; do not add both to every table.

### FBR-02-06/15 — Money Wire and Exact Discount Ceilings

PostgreSQL stores finalized Money as **BIGINT currency minor units**, while the JSON contract exposes those values as **decimal strings** with currency context. Never round or cast through binary floats or JS Number. Intermediate percentages, proportional allocation and tax calculations remain exact until the approved Feature-specific final rounding point.

Discount authorization uses **exact monetary comparison against the effective percentage ceiling**, without rounding the percentage for policy decisions. D-086 eligible base excludes shipping/tax and reflects earlier discounts; reject positive discounts where the eligible base is zero, and handle zero discount as a no-op. Use safe overflow-aware arithmetic and versioned state checks; no default discount ceiling is invented.

### 5.4 Nullability and defaults

- Default to `NOT NULL` for required facts; allow `NULL` only when absence has a documented meaning.
- `NULL`, `0`, `false` and empty text are not interchangeable.
- Avoid fabricated defaults that imply confirmation, collection, authorization, availability or a successful financial event.
- A status default is permissible only when the Feature establishes a valid initial state.
- Avoid booleans encoding multiple lifecycle states; use the approved Enum model for multi-state business flows.
- Avoid redundant `is_active` + `archived_at` + `deleted_at` without independent documented meanings.

### 5.5 Naming

| Item | Convention | Example only |
| --- | --- | --- |
| Table | Lowercase plural `snake_case` | `order_items` |
| Primary key | `id` | `id` |
| FK | `resource_id` | `order_id` |
| Event timestamp | `event_at` naming | `paid_at` |
| Standard timestamps | `created_at`, `updated_at` | Native Laravel conventions |
| True/false | Clear predicate name | `is_active` |
| Money | Expose stored unit/meaning | `total_amount_minor` |
| Business identifier | Clear distinct name | `order_number` |

Avoid vague `data`, `flag`, `value` or `type` where ambiguous. Name actual FK/unique/check indexes consistently and within PostgreSQL identifier limits; exact names belong to Feature Migrations.

### 5.6 JSONB discipline

Use `JSONB` for genuinely semi-structured settings, nonauthoritative metadata or justified snapshots. Do **not** hide the canonical Order Items, payments, stock relationships or numeric financial invariants in JSON merely to avoid relational design. Index JSON paths only when a measured query merits it.

**DB-03 acceptance:** types, nullability, semantics, enum evolution and presentation/API mappings reviewed; precise financial/time tests; no accidental FLOAT/TINYINT/blanket JSONB design.

---

## 6. DB-04 — Query-Driven Indexing and Performance

### 6.1 Index policy

- PostgreSQL B-tree is the ordinary starting point for equality/range, suitable filters and deterministic ordering.
- PK/UNIQUE constraints already provide corresponding indexes; avoid redundant duplicates.
- Add composite indexes to serve **identified, authorized query shapes**; consider filter selectivity, column order, sort order and tie-breakers.
- Index FK-side columns only where join, lookup, referencing-row update/delete behavior or observed plans justify the cost.
- Use partial indexes for meaningful frequent predicates and reviewed uniqueness semantics; check predicate compatibility and query-plan use.
- Use GIN for justified JSONB/full-text/trigram workloads; optional `pg_trgm` and PostgreSQL FTS require relevant Arabic/English search tests.
- Consider BRIN only for suitably large, physically correlated data with measured benefits.
- Every index adds storage and write/update/maintenance cost across **each Tenant DB**. Avoid speculative blanket indexes, premature partitioning, read replicas and external search infrastructure.

**Illustrative query, not a final table contract:**

~~~sql
SELECT id, order_number, status, total_amount_minor
FROM example_orders
WHERE location_id = :authorized_location_id
  AND status = :requested_status
ORDER BY created_at DESC, id DESC
LIMIT 20;
~~~

A candidate composite index may support this specific access path; do not prescribe it for all order queries without representative cardinality, workload and `EXPLAIN` evidence.

### 6.2 Optimization review procedure

1. Identify the real endpoint/job, scoped SQL shape, frequency, affected Tenant size and business impact.
2. Capture a representative **before** baseline, query count, latency distribution and writes.
3. Review `EXPLAIN (ANALYZE, BUFFERS)`, planner row estimates, actual rows, joins, sorting, relevant I/O and indexes. **EXPLAIN ANALYZE executes the statement**; never casually analyze production mutations.
4. Apply the smallest query/index change that addresses the demonstrated bottleneck.
5. Measure **after** latency, query count, write overhead, storage and potential contention.
6. Document the evidence and safe migration/release plan.

`pg_stat_statements` is an optional monitoring aid when available/configured; its enablement, permissions and data handling require implementation validation. A sequential scan can be correct for small or low-selectivity workloads; index usage alone is not a success metric.

### 6.3 Laravel query safety

Apply trusted Tenant, current Location and resource ownership scope **in SQL before** applying user-provided filters/sorts and pagination. Avoid N+1 via selective eager loading; fetch needed columns and relations, preserving relationship keys. Use bounded queries and async streaming/chunking for large exports; no unbounded `get()` across whole Tenant datasets.

**DB-04 acceptance:** proposed indexes traced to query shapes, plan evidence, measured tradeoffs, authorized result scopes and no unauthorized index sprawl.

---

## 7. DB-05 — Transactions, Locking, Atomicity and Idempotency

### 7.1 One owning local transaction

For coherent critical effects in one **verified Tenant DB**, the owning **Application Use Case/Orchestrator** opens one Laravel native transaction on the correct connection. Other Modules participate through Public Contracts on the **same connection/transaction scope**, without direct writes to their internal tables or premature independent commits.

Protected order confirmation must ensure approved prices, permissions and **all required order lines available at one eligible fulfillment Location**, with reservation, confirmed state, necessary snapshots and associated Tenant-local outbox intent committed atomically. A failed reservation must not leave a confirmed order or required event published.

**Illustrative boundary only:**

~~~php
DB::connection('tenant')->transaction(function () use ($command) {
    // Revalidate mutable business predicates under suitable protection.
    // Coordinate Inventory through its public contract.
    // Persist Orders-owned transition and snapshots.
    // Persist Tenant-local transactional outbox intent.
    // A failure rolls back every change on this same connection.
});
~~~

Do not hold open DB transactions over user interaction, provider/network calls, sending email, queue work or a connection/tenant switch. Central-only commits occur in Central; **there is no cross-DB ACID**.

### 7.2 Choose targeted concurrency primitives

| Risk | Preferred protection — Feature Plan decides exact SQL |
| --- | --- |
| Last unit / multi-line reservation | Protected rows (`FOR UPDATE`) and/or atomic conditional updates; whole eligible Location set guarded |
| One-time dispatch/stock consumption | Guarded state transition, stable unique physical operation identity, same-local-DB atomic writes |
| Verified collection/refund | Guard authoritative eligible state, real execution reference, cumulative limits and relevant cross-order limits |
| Concurrent ordinary edits | Expected-value or selective optimistic version check where lost updates matter |
| Duplicate event delivery | Consumer/operation uniqueness plus transactionally recorded processed-effect evidence |
| Read-only catalog | No unnecessary write lock |

**Illustrative SQL only, not an approved Inventory schema:**

~~~sql
UPDATE example_stock_balances
SET available_qty = available_qty - :quantity,
    reserved_qty  = reserved_qty + :quantity
WHERE id = :stock_id
  AND available_qty >= :quantity
RETURNING id;
~~~

An empty returned result means the predicate did not match at write time. Multi-line, multi-location, UOM and restock semantics require the actual Inventory Feature invariants and coherent transaction; this one-row illustration does **not** implement a whole-order reservation.

### 7.3 Deadlocks, retries and uncertain outcomes

- Keep transactions short and acquire multiple locks in a stable, documented order.
- Classify PostgreSQL `40P01` deadlock, `40001` serialization failure and other errors correctly; make **bounded** safe retries where permitted by the use case.
- A rollback/retry must not create a second genuine collection, refund, dispatch or stock movement; if the outcome is uncertain, first inspect authoritative persisted state and external evidence.
- Global `SERIALIZABLE` and Redis locks as a replacement for PostgreSQL integrity are **not** the default architecture. Feature-specific higher isolation may be separately justified and measured.

### 7.4 Two-layer idempotency and transactional outbox

1. **HTTP layer:** targeted `Idempotency-Key` scoped to verified Tenant/Central, operation and relevant actor/input; same key + different canonical payload conflicts; replay rechecks authorization.
2. **Business-effect layer:** stable actual event/operation identity, unique constraints, guarded state and transaction prevent duplicate effects **even with different HTTP keys or replayed jobs**.
3. Multiple legitimate partial collections/refunds may occur for one order; **do not** deduplicate them by order ID alone. Same items/price do not prove two independently authorized purchases are duplicates.
4. Commit critical event **intent** into the owning PostgreSQL outbox in the **same local transaction**; publish after commit via bounded relay/Redis/Horizon. `dispatchAfterCommit` alone is not a durable replacement.
5. Delivery is **at-least-once**. Consumers require independent, Tenant-aware idempotency and ordering checks where needed.

### 7.5 Cross-context processes

Central and Tenant transactions commit separately. Provisioning and other approved cross-context workflows require durable owning-module progress, stable step IDs, verification, reconciliation and explicit business-specific compensation where suitable. Do not invent a general Saga engine or assume atomic rollback across databases.

**DB-05 acceptance:** PostgreSQL concurrency tests prove no overselling, invalid partial order, duplicate money/stock execution, failed outbox atomicity, lost update where prohibited or Tenant context switch within transactions.

---

## 8. DB-06 — Safe DDL, Backfills and Multi-Tenant Releases

### 8.1 Expand → Backfill → Contract where justified

For an incompatible schema change, plan a **release-compatible transition**, not an instant destructive rename:

1. **Preflight:** DB privileges/connectivity, storage, schema versions, expected locks, code compatibility, backup/recovery prerequisites and affected workflow.
2. **Expand:** introduce additive columns/tables/constraints compatible with active old/new releases.
3. **Compatible code:** ensure both live versions and any overlapping queue workers can read/write the approved transition state.
4. **Backfill:** chunk/resume/reconcile existing rows; handle concurrent source updates so values are not silently lost.
5. **Verify/cutover:** check counts, constraints, business evidence and reader/writer compatibility.
6. **Contract later:** remove old fields only after verified retirement of old code/consumers and explicit approval.

A simple safe additive migration does **not** require all stages. Destructive type changes, column drops, large constraints, live data conversion and financially significant transformations get stricter review.

### 8.2 Controlled migration runner

- No app-container startup auto-migration.
- Run Central migrations with the right elevated credential separately from Tenant migrations.
- Roll Tenant updates in **measured bounded batches**, with no parallel migration against one Tenant.
- Persist per-DB migration history and separately secured Central rollout state, target release, failures and retry checkpoints.
- On one Tenant failure, classify/quarantine/retry safely; continue other Tenants only when compatibility conditions genuinely permit.
- Provisioning reuses the current Tenant migrations; no `Ready` after partial initialization.
- Validate mixed schema versions, worker compatibility and rollback behavior before shifting traffic.

### FBR-02-09 — Compatibility-Gated Partial Tenant Rollouts

Central and Tenant migrations remain independently versioned. A failed Tenant migration is **not** counted as success, and does not force unrelated compatible Tenants to stop; it **does** block incompatible application operations for the affected Tenant until an approved compatible schema/code pairing is restored. A release gate tracks per-Tenant target version, actual version, failure, canary/batch status, old/new reader-writer compatibility and safe recovery/rollback. Use expand/backfill/contract when justified; never assume cross-database atomic migration or blindly roll back destructive DDL. These are approved rollout semantics, not verified migrator code or permission to launch.

### 8.3 Large PostgreSQL changes

- Review PostgreSQL `ALTER TABLE` locks, scan/rewrite behavior, `lock_timeout` and `statement_timeout` in representative conditions.
- For suitable large indexes, `CREATE INDEX CONCURRENTLY` may limit write blocking, but **cannot run in a PostgreSQL transaction block**. Its migration must be deliberately executed outside that block and account for failed/invalid index cleanup.
- Prefer bounded, idempotent backfill batches, durable progress and reconciliation over an unbounded single transaction.
- Do not overwrite fresh user changes while backfilling an old column. Coordinate writers and last-update resolution explicitly.
- Avoid forced `NOT NULL` or uniqueness constraints until old data and active writers comply.

### 8.4 Blue-Green and rollback semantics

The old application release, new release, queues and Tenant schema versions may overlap. A traffic rollback **does not** reverse DDL, committed data, Redis events or files. Maintain a reviewed compatibility window and explicit stop/rollback criteria. Do not invent batch count, migration timeout or approval threshold without measurement.

**DB-06 acceptance:** preflight, migration inventory, mixed-version tests, safe retry and per-Tenant isolation, backfill reconciliation, PostgreSQL DDL locks/transaction behavior and verified compatibility.

---

## 9. DB-07 — Query Execution, Pagination and Connection Management

### 9.1 Trusted connection lifecycle

Central models/platform work use an explicit `central` connection. Tenant models use the dynamic Stancl-managed `tenant` connection **only after** authoritative Tenant identification and authorization. A user-supplied Tenant ID/host/header is not itself authorization.

For merchant staff, Central Identity and active membership must agree with the trusted Tenant context; Tenant-local Permissions and Locations further restrict business queries. Storefront subdomains must map through the Central Tenant Directory and meet relevant availability/entitlement checks.

- Open Tenant connections lazily where supported by the validated integration.
- Do **not** fall back to Central or a previous/default Tenant if context resolution, connection or secret retrieval fails.
- Never switch Tenant or connection during a transaction.
- Clear tenant-specific mutable connection/context after all request/job completion paths, including exception and retry. Use supported Laravel/Stancl lifecycle APIs rather than ad-hoc unsafe resets.
- Validate sequential Tenant A → Tenant B → Central jobs on the **same Horizon worker**; ensure Redis queue transport does not accidentally inherit Tenant cache prefixes.
- Future Octane requires separate long-lived worker state-safety testing; the first Pilot design is PHP-FPM.

### 9.2 Connection budget, not permanent tenant sockets

Count active/idle sessions across API PHP-FPM processes, Horizon workers, Scheduler/relay and migration/provisioning activity, including Blue-Green overlap. Capacity depends on **concurrent requests/worker behavior**, not simply the number of merchant databases.

Bound worker concurrency, job/DB operation timeouts, long transactions and idle connections based on measured PostgreSQL resource limits. Avoid blindly increasing `max_connections`. **PgBouncer and custom connection pooling are not selected for the first Pilot**, and require later measured justification and tenancy/session-state compatibility validation.

### 9.3 Query and pagination standards

- Scope to trusted Tenant, Location, resource ownership and current authorization in the SQL query **before** filters, aggregates, relationship loading, exports and pagination.
- Prevent N+1 via suitable eager loading; do not over-fetch entire relation graphs. Select only needed columns, including keys required for relations.
- API `perPage` default **20**, ordinary maximum **100**, with validation and rejection of out-of-range/unbounded requests (AS-04). A reviewed Feature can enforce a smaller cap.
- `paginate()` for required total counts/page navigation; `simplePaginate()` where total counts are unnecessary; `cursorPaginate()` only with stable deterministic ordering, index support and a compatible cursor contract.
- Offset pages may get slower at high offsets; cursors do not promise historical snapshot consistency across independent page requests.
- Heavy exports/reports run as **bounded, permission-scoped Horizon jobs** with chunk/stream processing and controlled memory/concurrency.

**Illustrative Eloquent query, not an authorized or final Feature contract:**

~~~php
$orders = Order::query()
    ->whereIn('location_id', $verifiedAllowedLocationIds)
    ->with('items')
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->paginate(20);
~~~

This assumes an already verified Tenant and Location list. Real policies, customer/resource ownership and query composition must be defined in the Feature; an empty/missing authorized scope must not widen results.

**DB-07 acceptance:** bounded list/extract tests, N+1 measurements, appropriate pagination contract, authorized aggregate counts, safe A/B/Central connection/job cleanup and measured total connections.

---

### FBR-02-20 — Consistent Export Read Boundary

For financial exports, \`queued\` time is **not** the report data cutoff. The governing data viewpoint starts at actual **execution-time read**; capture meaningful request time, read/as-of point, completion time and freshness meaning. Multi-query/streamed financial totals and details must observe **one consistent PostgreSQL snapshot or a proven equivalent**, without unbounded long transactions or assuming cursor/chunk iteration implies repeatable-read. A cross-Tenant Platform aggregate is not one shared ACID snapshot across independent databases. Operational eventually-consistent read models must disclose freshness and never replace current stock, order or collection truth. Preserve D-176 original-sale-date/late-entry semantics and reauthorize before execution/download.

## 10. DB-08 — Historical Records, Corrections and Safe Deletion

### 10.1 Each resource owns its lifecycle

| Record class | Default design discipline — Feature controls exact policy |
| --- | --- |
| Unused catalog data | Editable/disable/archive/delete when allowed and without breaking references |
| Catalog data referenced by completed orders | Preserve required referential history and order snapshots; no destructive cascade |
| Draft orders | Approved editable/cancelable lifecycle |
| Confirmed orders and sale evidence | Preserve committed commercial facts and snapshots; allow only approved, auditable changes |
| Inventory movements | Once-only physical event evidence; corrections/reversals rather than silent mutation |
| Verified collections and refunds | Preserve actual operation references, verification, amounts and authorized correction lineage |
| Audit/activity history | Protected retention and justified redaction according to governance |
| Customer personal data | Separate lawful retention/erasure policy; do not equate Soft Delete with privacy deletion |

### 10.2 Historical snapshots and immutable evidence

Current Product name or price must never silently rewrite the commercial truth of a previously confirmed Order. Snapshot only the commercially significant agreed fields required by each Feature (e.g. item identity/description, quantity/UOM, price, discount/tax, currency, totals, relevant fulfillment/approval evidence).

**Immutable evidence** means original material facts are retained with auditable subsequent decisions. It does **not** prohibit valid business lifecycle transitions, an authorized correction workflow, or legally required controlled personal-data minimization.

- Preserve the original collection/refund/stock movement reference and reason/actor/time of approved corrections.
- Do not infer actual payment/refund from approval, dispatch, delivery or an attempted notification.
- Do not restock simply because a Return was approved; follow physical receipt/inspection and the agreed inventory rules.
- No universal financial Ledger, Event Sourcing or generic temporal-table framework is introduced.

### 10.3 Archive vs Soft Delete vs hard deletion

**Archive:** stop normal use while retaining data. **Soft Delete:** mark logical deletion while row still exists. **Hard Delete:** physically remove only when business/legal/relational requirements permit. **Correction/Reversal:** express an authorized historical change without erasing the original effect.

Use the minimum semantics required per Feature. Do not put `deleted_at` on every table or treat `softDeletes()` as sufficient for legal erasure.

### 10.4 Unique identifiers and soft deletion

A normal `UNIQUE(sku)` still applies to soft-deleted rows. A partial unique index such as:

~~~sql
CREATE UNIQUE INDEX example_active_sku_unique
ON example_products (sku)
WHERE deleted_at IS NULL;
~~~

would permit reuse after soft deletion. **Do not assume SKU reuse is allowed.** The Feature must explicitly decide identifier reuse, historical reconciliation and cross-channel integration implications. This SQL is illustrative only.

### 10.5 Privacy, retention and off-system copies

Define legal/operational retention, permitted anonymization/erasure and backups/exports/audit implications in the relevant Feature/compliance decisions; no universal period is approved here. Subscription expiration does **not** auto-delete Tenant DBs or override a required retention/recovery process.

**DB-08 acceptance:** snapshot integrity, non-destructive financial/stock evidence, appropriate delete behavior, tested unique-with-soft-delete cases and explicit privacy-retention specifications.

---

## 11. DB-09 — Database Security, Roles, Grants and Isolation

### 11.1 Independent PostgreSQL runtime identity per Tenant

- Every Tenant has a **dedicated, least-privilege PostgreSQL runtime role**, restricted to its own Tenant Database.
- Central runtime has separate privileges for Central; it does not become a universal Tenant runtime.
- Migration/provisioning credentials and infrastructure administrator identity are separate privilege tiers; no privileged provisioning secrets inside ordinary storefront/API requests.
- An app runtime role is **not** a superuser and cannot create databases/roles or alter arbitrary schemas.
- Tenant staff RBAC/Permissions/Location scopes are **application roles**, not separate PostgreSQL logins per employee.
- Platform Super Admin status is **not** unrestricted direct merchant DB access.

### 11.2 Required PostgreSQL grant review

Check `CONNECT` on each database; inherited/explicit membership; `PUBLIC` access; schema `USAGE`/`CREATE`; object owners; existing and future `TABLE`/`SEQUENCE` privileges; Default Privileges; untrusted extensions/functions; and actual restricted Role behavior. Schema `public` does **not** mean data is publicly readable, nor does merely creating a Tenant DB establish isolation.

**Illustrative only — not a complete hardened provisioning script:**

~~~sql
-- Executed only by a specifically authorized administrator/provisioner.
REVOKE ALL ON DATABASE example_tenant_b FROM PUBLIC;
GRANT CONNECT ON DATABASE example_tenant_b TO example_tenant_b_runtime;
-- Explicitly review owners, other role grants, role inheritance,
-- public schema privileges, existing/new tables and sequences.
~~~

The test of `tenant_a_runtime` connecting to `tenant_b` must be **denied**, including indirect grants. Privilege checks need real PostgreSQL roles, not just mocked Laravel tenancy middleware.

### 11.3 Secrets, network and rotation

- Resolve Tenant credentials from protected runtime secret/configuration references; Central Directory stores **opaque secret references**, not plaintext passwords.
- Never log secrets, unredacted DSNs, full payment data or unrestricted personal information.
- Restrict PostgreSQL to approved internal network paths; validate service exposure, host-based access and appropriate connection encryption per Infrastructure threat model.
- Failed secret resolution is **fail closed**; never select another Tenant as a fallback.
- Define guarded credential rotation/revocation with connection teardown, availability checks, auditing and reconciliation.
- Use parameterized Eloquent/Query Builder/SQL, and specially review privileged SQL or `SECURITY DEFINER` execution contexts.

**Security boundary:** a restricted Tenant database role limits the blast radius of misuse of **that credential**. It is not a defense against an attacker who has compromised all application secrets or the database administrator. Maintain layered authorization, secret access controls, network restrictions and independent validation.

### 11.4 Required negative tests

| Attempt | Required result |
| --- | --- |
| Tenant A runtime authenticates to Tenant B DB | **Denied** |
| Tenant A runtime accesses Central DB | **Denied** |
| Ordinary runtime tries `CREATE DATABASE` / `CREATEROLE` / unauthorized DDL | **Denied** |
| Role inherits an overly broad grant | Detected and corrected before acceptance |
| New migrated table unintentionally grants broad access | Test detects regression |
| API changes route/tenant ID or Location filter | Cannot widen tenant/resource authorization |
| Job fails or retries after Tenant A then runs Tenant B | Context and connections clean |
| Secret missing or invalid | Fail closed; no default/previous Tenant |
| Platform administrator uses unapproved direct Tenant access | Denied outside an explicitly authorized workflow |

**DB-09 acceptance:** actual Role/Grant matrix, safe connection initialization, targeted cross-tenant negative tests and auditable secret handling.

---

## 12. DB-10 — Observability, Tests, Capacity and Release Gates

### 12.1 Lightweight monitoring baseline

Use native PostgreSQL statistics, structured Laravel logs, Horizon/Redis and existing Coolify/host signals. No mandatory enterprise observability stack is selected.

| Signal | What to inspect |
| --- | --- |
| PostgreSQL sessions | `pg_stat_activity`, active/idle age, session errors, total connection pressure |
| Database stats | `pg_stat_database` commits, rollbacks, deadlocks, temporary files, I/O when available |
| Locking | `pg_locks`, blocking sessions and long open transactions |
| Tables/indexes | `pg_stat_user_tables` / `pg_stat_user_indexes`, bloat/vacuum concerns and useful index access |
| Expensive queries | Measured query durations, selective `EXPLAIN (ANALYZE, BUFFERS)` and optional `pg_stat_statements` |
| API business impact | Request correlation, P95/P99, error rate, availability and critical workflow failures |
| Worker/outbox | Horizon failures, lag, retry/reconciliation state, pending durable events |
| Multi-Tenant release | Per-Tenant migration version/drift, partial failures, release compatibility and Blue-Green resources |
| Server limits | PostgreSQL CPU/RAM, disk utilization/IOPS, storage growth and shared connection headroom |

Statistics counters are often **cumulative**; evaluate deltas and `stats_reset`, not isolated raw values. Use sensitive-data-safe query fingerprints rather than logging full customer SQL bindings. High-cardinality Tenant/user IDs should not be attached indiscriminately as time-series metric labels.

**Illustrative PostgreSQL diagnostic read:**

~~~sql
SELECT datname, numbackends, xact_commit,
       xact_rollback, deadlocks, stats_reset
FROM pg_stat_database
WHERE datname IS NOT NULL;
~~~

Observability accounts require reviewed read privileges. Monitoring access is not unrestricted access to merchant business data.

### 12.2 Real integration test fixture

For PostgreSQL-sensitive tests require **one Central and at least two distinct Tenant PostgreSQL databases**, with dedicated restricted runtime roles and actual Laravel/Stancl integration once compatibility is validated. Use Redis/Horizon and the real outbox path for tests that exercise those boundaries.

| Test family | Required evidence |
| --- | --- |
| Schema/constraints | Correct types, NOT NULL/CHECK/UNIQUE/FK and safe historical delete rules |
| Identity/isolation | Tenant A ↔ B DB privilege denial; Central vs Tenant access; invalid/mismatched membership/Location |
| Concurrency | Concurrent last-unit order: no oversell; whole order reservation atomic at one eligible Location |
| Financial idempotency | Same event duplicate denied, genuine partial events allowed, cumulative refund limits concurrency-safe |
| Local ACID | Injected failure rolls back Order/reservation/outbox; event intent survives worker outage after commit |
| Worker consistency | Outbox at-least-once, duplicate/reordered event handling, A → B → Central cleanup |
| Migration/release | Tenant version drift, batch failure/resume, invalid incomplete `Ready`, old/new code compatibility |
| Money/time/history | Currency-exponent precision, snapshots after catalog edits, UTC/date correctness, correction lineage |
| Query performance | Scoped pagination/counts, N+1, representative query plans, heavy export containment |
| Security | Runtime DDL restrictions, secret-failure fail-closed and negative direct connection attempts |
| Fault/capacity | Deadlocks/retries, unknown external outcome reconciliation, mixed-Tenant peak, Blue-Green headroom |

Mock/SQLite/unit tests are useful for other boundaries, but they cannot by themselves prove PostgreSQL locking, grants, isolation or deadlock behavior.

### 12.3 PR and release blockers

Relevant cross-Tenant leakage, role/permission bypass, duplicate critical stock/collection/refund effects, overselling, inconsistent committed order-reservation state, destructive historical evidence loss and missing required durable events are **blocking**, not warnings.

An index regression or moderately slower query is judged against the affected workload and measured performance goals; no invented universal latency threshold is set in this document.

### 12.4 BA-30 Pilot gates remain binding

| Gate | Evidence / position |
| --- | --- |
| **G1 — Tenant safety** | Cross-Tenant integration and penetration/regression testing; actual PostgreSQL Role privilege checks — **NOT VERIFIED** |
| **G2 — Commerce consistency** | No overselling/duplicate financial or inventory effects, verified transactional state — **NOT VERIFIED** |
| **G3 — Backup and restore** | **BLOCKED** until **BA-16 is explicitly approved**, off-VPS Central/Tenant/file backups and full + per-Tenant restore drills are tested, and measured RPO/RTO are approved |
| **G4 — Releases** | CI/CD, compatible migrations, Blue-Green headroom, graceful workers and rollback checks — **NOT VERIFIED** |
| **G5 — Capacity/NFRs** | Representative workload, numeric SLOs, measured resource/connection budgets and alerts — **PENDING VALIDATION** |
| **G6 — Security/privacy** | Secrets, role grants, security hardening, lawful data handling and incident response — **NOT VERIFIED** |
| **G7 — Legal** | Applicable Egypt Pilot merchant/channel obligations — **LEGAL REVIEW REQUIRED** |
| **G8 — Operational completeness** | E2E supported commerce flow, reporting and staff controls — **PENDING FEATURE ACCEPTANCE** |
| **G9 — Environment isolation** | Independently scoped Development/Production DBs, credentials, queues and files — **NOT VERIFIED** |
| **G10 — Explicit release approval** | Named owner, recorded evidence and scoped go/no-go — **APPROVAL REQUIRED** |

Neither a mounted PostgreSQL volume nor a Coolify resource, Redis persistence, a successful image rollback or a local copy is proof of recoverability. **DB-10 does not decide the backup vendor, PITR/snapshot architecture, cadence, encryption policy or RPO/RTO.** Those belong to deferred BA-16 and its separate approval. Development documentation and non-production engineering can continue while G3 is blocked; **live merchant activation cannot**.

### 12.5 Measurement-driven capacity

Benchmark normal, peak and mixed-Tenant traffic: authorized catalog browsing, online/manual confirmation, direct sale, stock reservation contention, fulfillment, collections/refunds, exports, migration rollout, Tenant provisioning and overlapping Blue-Green releases.

Capture database/worker/app resource pressure, latency/error distribution, connection/lock waits, disk I/O and fairness across Tenants. Tune query/index/worker budgets and vertical resources before considering separate database services or horizontal scaling.

**DB-10 acceptance:** actionable observability and diagnostics plan, representative load design, real database test matrix, high-risk blocking failures, evidence/ownership for Pilot gates and honest pending status.

---

## 13. DB-11 — Final Review, Cross-Document Fit and Publication

### 13.1 Cross-document consistency

| This standard | Compatible binding references | Verified interpretation |
| --- | --- | --- |
| DB-01/06 | DA-01/06; TA-05; BA-06; INF-10 | Separate Central/Tenant tracks, controlled batch runner and compatible releases |
| DB-02 | DA-02/03; Module Architecture | BIGINT by default; selective UUIDv7; pragmatic same-DB FKs; One Writer |
| DB-03 | DA-04; CS-05 | Minor-unit money, UTC TIMESTAMPTZ, project int Enums/SMALLINT, BOOLEAN |
| DB-04/07 | DA-05; AS-04; BA-20 | Query-based indexes; 20/100 API page sizes; scoped and bounded connections |
| DB-05 | TC-01…TC-06; BA-08; AS-06 | Owning same-DB ACID, selective locks, two-layer idempotency, transactional outbox |
| DB-08 | DA-04/06; approved commerce decisions | Feature-specific archival and historical evidence; no destructive implicit cascades |
| DB-09 | BA-21; TA-02/03/08; Authorization | Dedicated Tenant runtime role, trusted context, explicit grants and negative tests |
| DB-10 | BA-24/25/29/30; TC-07; INF-11 | PostgreSQL-first monitoring, risk-based PR blocking and measured Pilot capacity |
| DB-11 | Existing standards publication sequence | Documentation-only approval on `development`, not a feature/release sign-off |

No approved DB-01…DB-10 choice requires reversing the cited architecture decisions. **The exact physical Migration paths, schema names beyond `public`, individual fields, index designs, SQL lock targets, feature-specific retention and deployment settings remain intentionally subject to specification and testing.**

### 13.2 Pending and deferred items — do not silently close

| Item | Status and ownership |
| --- | --- |
| `stancl/tenancy v3.10.1` + Laravel 13 + PostgreSQL role and Horizon integration | **CONDITIONALLY SELECTED; PENDING REAL VALIDATION** |
| Tenant-specific actual schema/DDL, tables, FK constraints, indexes | **TO SPECIFY** in owning Features |
| Postgres role grant scripts and secret provider/rotation | **TO IMPLEMENT/VERIFY** with security and infrastructure |
| Migration runner code, batch sizes, concurrency, timeouts and version tracking schema | **TO SPECIFY/IMPLEMENT/TEST** |
| Real load numbers, P95/P99 targets, connection budget, alerts | **PENDING MEASUREMENT AND APPROVAL** |
| PHPUnit/Pest selection, CI runners, branch policies, live monitoring | **NOT VERIFIED AS CONFIGURED** |
| **BA-16** backup/restore architecture | **DEFERRED**, cannot be implied here |
| **BA-30 G3** live Pilot backup/restore | **BLOCKED** until BA-16 and actual recovery evidence |
| **D-201** alternative-Location proposal workflow business decision | **OPEN**, not resolved by a database standard |
| Privacy/legal retention specifics and Pilot legal eligibility | **PENDING FEATURE/LEGAL REVIEW** |

### 13.3 Publication and authority

The owner approved **DB-01 through DB-10 Option A** sequentially and then explicitly authorized **DB-11 Option A** publication of this single consolidated file on `development`. This approval is for engineering documentation only. Creating this file does not constitute an approved ERD, a schema migration, a test result, a deployed backend, a CI gate configured in GitHub or permission to activate real merchant data.

---

## 14. Illustrative Patterns and Anti-Patterns

### 14.1 Good: protected single-Tenant use case

~~~text
Authenticated actor → trusted Tenant membership + current Location permission
→ owning Application Action → correct Tenant DB transaction
→ revalidate mutable pricing/order/stock predicates
→ owning Inventory public contract: protected whole-order availability/reservation
→ Orders-owned confirmed record + required snapshots
→ same-Tenant transactional outbox intent
→ COMMIT once → bounded relay/Horizon follows asynchronously
~~~

This is the architectural sequence, **not executable application code**. Pricing, stock/UOM, order state, financial and permissions details belong to their approved Feature and D-series rules.

### 14.2 Bad: read/check/write without concurrency protection

~~~php
// NOT SAFE for competing stock reservations:
$stock = StockBalance::findOrFail($stockId);
if ($stock->available_qty >= $quantity) {
    $stock->available_qty -= $quantity;
    $stock->save();
}
~~~

Two transactions can read the same old availability. Use a correctly owned, protected transaction and appropriate row locking/atomic condition for the actual Inventory model.

### 14.3 Bad: tenant isolation through client filters alone

~~~php
// NOT SAFE: arbitrary client-provided tenant/location is not authorization.
Order::query()
    ->where('location_id', $request->input('locationId'))
    ->get();
~~~

Trusted Tenant DB selection and current Location/resource authorization must be established independently; apply only allowed filters after the authorized query scope.

### 14.4 Bad: release-time destructive shortcut

~~~sql
-- NOT an approved Blue-Green procedure:
ALTER TABLE example_orders DROP COLUMN old_amount;
~~~

May break old/new app overlap, background workers or historical evidence. Use the reviewed migration compatibility plan, correct data migration and verified contract-phase release when justified.

### 14.5 Bad: application-time privileged database credentials

~~~text
NOT ALLOWED:
one database superuser/CREATEDB credential in ordinary Laravel API,
one runtime account with broad CONNECT and DML over all Tenants,
fallback to last Tenant when secret resolution fails,
auto-running all Tenant migrations during each container start.
~~~

### 14.6 No performance or availability guarantees by assertion

Avoid statements such as “this index will always be used,” “500 merchants require 500 open connections,” “Green health check proves zero downtime,” or “DB backup exists because the volume persists.” All require actual verification and a relevant acceptance boundary.

---

## 15. Developer / AI Agent Pre-PR Database Checklist

### Scope and authority

- [ ] Feature cites approved business decisions and owning Module; **D-201** not silently decided.
- [ ] Identify correct **Central vs Tenant** database; no implicit shared cross-DB FK or transaction.
- [ ] Verify current Tenant, membership, Location and resource access before querying; deny on failure.
- [ ] Maintain One Writer Principle and Public Contracts for cross-module writes.

### Schema quality

- [ ] Use default local BIGINT PK; UUIDv7 only for real cross-DB/integration identity requirements.
- [ ] Review FKs, unique rules, check constraints, NULL/default values and historical delete actions.
- [ ] Correct PostgreSQL type for money, status, boolean, time and calendar dates.
- [ ] PHP Enum is int-backed with stable values and API mapping; no `TINYINT`.
- [ ] Avoid unreviewed JSONB replacing relationships; avoid universally nullable or soft-deleted tables.
- [ ] Document constraints and identifier uniqueness scope, including conditional/partial uniqueness.

### Concurrency, effects and history

- [ ] Critical local business writes commit/rollback in one proper DB transaction.
- [ ] Lock/conditional update protects mutable decisions; stable lock order and bounded retries defined.
- [ ] Handle uncertain external outcomes through persisted proof and reconciliation, not blind retries.
- [ ] Idempotency protects **actual event reference**, while legitimate repeated partial actions stay allowed.
- [ ] Outbox intent shares owning DB transaction; consumers accept at-least-once delivery.
- [ ] Correct snapshots, physical stock/collection evidence, correction/reversal and audit lineage preserved.

### Queries and migrations

- [ ] Tenant/Location/ownership scopes precede filters, related data, counts, exports and pagination.
- [ ] Avoid unbounded reads, N+1, speculative indexes, high-offset/unneeded COUNT and huge in-memory exports.
- [ ] Gather relevant query plan and latency evidence for impactful indexes/optimizations.
- [ ] Central/Tenant migration placement and module ownership reviewed; no startup auto-migrate.
- [ ] Blue-Green compatibility and worker overlap tested; destructive changes separated from expand phase.
- [ ] Big backfills bounded, resumable, race-aware, with checkpoints and completion checks.
- [ ] DDL lock/timeout review; `CREATE INDEX CONCURRENTLY` not inside PostgreSQL transaction blocks.

### Privileges, operations and release

- [ ] Tenant runtime role accesses only its own database, with real `PUBLIC`/inheritance grants tests.
- [ ] Central runtime, migrations, provisioning, administration privileges separated.
- [ ] Credentials from protected secret references; no plaintext passwords or unredacted SQL bindings in logs.
- [ ] Requests/Horizon exceptions and sequential Tenant jobs clear Tenant context and stale connections.
- [ ] Verify target DB against real PostgreSQL, including failure injection and concurrent requests/jobs.
- [ ] Record migration and schema drift/release evidence; stop unsafe partial rollouts.
- [ ] Monitor scoped connection budget, slow queries, lock contention, IO and worker/outbox health.
- [ ] Confirm required BA-30 G1–G10 evidence before Pilot; **BA-16 deferred and G3 blocked** remain explicit.

---

## 16. Final Approved Position

**DB-01 through DB-11 — Option A: APPROVED for documentation and future engineering standards.**

The engineering baseline is **PostgreSQL-first, database-per-Tenant, Laravel-native, feature-owned, explicit about isolation, protected by database integrity and targeted concurrency, safely migrated, measured before scaling, and evidence-gated before any live activation**.

**This file documents a decision, not an implementation result.** Do not mark any database migration, PostgreSQL grant, Laravel/Stancl compatibility test, CI workflow, backup/restore drill, performance benchmark or Pilot gate as complete without its separately recorded technical evidence.

**End of Database Standards — Version 1.0.**
