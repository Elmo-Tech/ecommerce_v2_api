# E-commerce SaaS V2 — Transactions & Consistency Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/transaction-consistency-architecture.md`  
**Version:** 1.0 — Approved Transactions & Consistency Architecture Baseline  
**Prepared:** 2026-11-17  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — TC-08 (A)**  
**Decision Coverage:** **TC-01 through TC-08**  
**Implementation Status:** **Not implemented or verified**

> **Reading rule:** This document approves an MVP-first consistency design, not code, a PostgreSQL schema, installed packages, passing tests, a new production capability or permission to activate merchants. Actual lock statements, transaction scopes, idempotency records, outbox schema, retry schedules and feature-specific financial rules require implementation plans and tests. This document must not silently override approved business decisions.

---

## 1. Document Overview & Authority

### 1.1 Purpose

Define how the **Laravel 13 API-first Modular Monolith** protects orders, stock, fulfillment, collections, refunds and merchant onboarding from partial commits, concurrent updates, duplicate execution, lost asynchronous events and uncertain outcomes.

The approved deployment model is **one Central PostgreSQL database and one independent PostgreSQL database per merchant (Tenant)**, with Redis queues, Laravel Horizon and conditionally adopted `stancl/tenancy v3.10.1`. Single-database business effects should be ACID; cross-database workflows must be explicitly coordinated and recoverable, not treated as one distributed transaction.

### 1.2 Authoritative references

Read together with:

- [Backend Architecture](./backend-architecture.md) — BA-08 transaction invariants, BA-09 Redis/Horizon, BA-25 risk-based testing, BA-30 Pilot gates and business safety.
- [Module Architecture](./module-architecture.md) — Application Orchestrators, One Writer Principle, Public Contracts, module ownership and event classification.
- [Tenancy Architecture](./tenancy-architecture.md) — trusted Tenant resolution, connection lifecycle, Stancl validation and provisioning/migration behavior.
- [Data Architecture](./data-architecture.md) — PostgreSQL constraints, tenant separation, identifiers, historical evidence and schema changes.
- [Identity & Access Architecture](./identity-access-architecture.md) — Central workforce identity, Tenant-local roles/Locations, safe membership initialization/revocation.
- [API Architecture](./api-architecture.md) — API-05 targeted `Idempotency-Key`, request correlation and consistent API errors.

Approved D-series business rules remain authoritative for order lifecycle, inventory, payment/collection evidence, returns and refunds. TC decisions define *how* those business outcomes remain consistent; they do not create new statuses or permission grants.

### 1.3 Boundaries

This is **not** a full database design, generic transaction library, queue operations runbook, payment-gateway architecture, disaster-recovery plan or performance benchmark. Feature `spec.md` / `plan.md` / `tasks.md` and their tests determine concrete implementation where it matters. The next Queue/Job standards may refine operations without changing the approved consistency guarantees.

## 2. Approved Decisions — TC-01 to TC-08

| Decision | Approved option | MVP baseline |
| --- | --- | --- |
| **TC-01** | **A — Laravel Native Transactions + Explicit Application Orchestrator Ownership** | The business use-case orchestrator owns a coherent transaction on one authorized Tenant DB; module writes go through Public Contracts. One local commit or rollback; no Tenant connection switch during an active transaction. |
| **TC-02** | **A — Targeted PostgreSQL Concurrency** | Appropriate row locks, atomic conditional updates, unique constraints and selective optimistic concurrency. Short transactions, stable lock order and bounded deadlock retries; neither global `SERIALIZABLE` nor Redis locks as the authority. |
| **TC-03** | **A — Two-Layer Idempotency** | Targeted HTTP `Idempotency-Key` plus independent module-owned business-effect protection with operation references, state guards, constraints and transactions. Legitimate repeated operations must remain possible. |
| **TC-04** | **A — PostgreSQL Transactional Outbox + Lightweight Relay + Redis/Horizon** | Commit event intent alongside the owning business write; relay after commit; at-least-once delivery, tenant-aware idempotent consumers, bounded retries and auditable recovery. No Kafka/enterprise streaming. |
| **TC-05** | **A — Lightweight Cross-Context Orchestration** | Durable, owning-module workflow state; independent Central/Tenant commits, retry-safe steps, reconciliation or appropriate compensation. No distributed ACID or generic Saga engine. |
| **TC-06** | **A — Classified Failures + Bounded Retries + Durable Reconciliation** | Distinguish transient, business, permanent and unknown-outcome errors; re-check authoritative evidence before retrying; manual escalation for unresolved financial effects. |
| **TC-07** | **A — Risk-Based Testing + Progressive Release Gates** | Actual PostgreSQL concurrency/transaction tests, Central plus two Tenant DBs, targeted failure injection, Horizon/outbox isolation and relevant PR gates; BA-30 G1–G10 remain mandatory. |
| **TC-08** | **A — Lightweight Transactions & Consistency Architecture Baseline** | Approve TC-01…TC-07 and this 15-section architecture document. Integration, testing, capacity and Pilot readiness require later evidence. |

**No chosen option authorizes implementation, deployment, package installation or a live-merchant launch.**

## 3. Business Consistency Invariants

Business integrity takes precedence over convenience, including:

| Workflow | Required invariant |
| --- | --- |
| **Online and Manual Orders** | Confirmation follows approved eligibility, pricing, permission and stock rules; do not mark an order confirmed if its required reservation failed. |
| **Manual Pending Stock** | Remains unconfirmed and unreserved until a separately authorized confirmation; notifications or FIFO priority do not reserve stock. |
| **Location availability** | Check every required order line atomically against **one eligible fulfillment Location**; do not combine stock from separate Locations to fabricate availability. |
| **Inventory** | No overselling, duplicate reservation effect, duplicate stock movement or unauthorized mutation of another module's stock records. |
| **Direct Sale** | Follow approved immediate, fully verified collection and handover requirements, including the prescribed exception route when real external collection succeeded but handover could not complete. |
| **Fulfillment** | Authorized physical dispatch/pickup consumes stock or reservation once; delivery confirmation does not subtract stock again or imply COD settlement. |
| **Collections** | Record a genuinely verified financial event once; preserve separate permitted collections and the historical evidence. |
| **Refunds and returns** | A request/approval does not prove actual money returned. Do not repeat the same financial execution or restock without physical receipt/inspection eligibility. |
| **Tenant onboarding** | A Tenant is not `Ready` before its technical setup passes; `Ready` alone does not enable a subscription or business feature. |
| **Workforce access** | Central membership is not `Active` before required Tenant-local role/Location initialization. Revocation denies new access even if cleanup jobs are delayed. |

PostgreSQL is authoritative for local business facts; Redis, cached summaries, search indexes, email deliveries and other projections are not substitute authorities. Financial/stock history is corrected with authorized reversible/corrective records, never silent deletion or retroactive rewriting.

## 4. Transaction Boundaries & Ownership — TC-01

### 4.1 Owning Application Orchestrator

For an operation whose critical writes belong to the **same Tenant Database**, the owning **Application use case** controls one coherent Laravel native DB transaction. Contracts of other modules participate using the **same validated connection and transactional scope**; they cannot independently commit an incomplete sub-operation. The orchestrator coordinates business ordering, not permission to write every table.

**Example — authorized Manual Order confirmation:**

1. Validate actor, Tenant, Location scope, order state, catalog eligibility and authoritative prices/discounts.
2. Enter the correct Tenant transaction and protect any reads/states that can change concurrently; revalidate the necessary business predicates.
3. Through Inventory's Public Contract, verify **complete** single-Location availability and reserve all required lines atomically.
4. Through Orders-owned persistence, save confirmation and required historical snapshots; apply appropriate idempotency/state guards.
5. Insert the corresponding Tenant-local outbox intent in that same transaction.
6. Commit everything or roll back everything. Relay/publish *after* commit.

If the reservation fails, confirmed status and corresponding outbox publication must not survive. Reusable read-only contracts need not be artificially wrapped in a transaction unless their result must be protected through a dependent write.

### 4.2 Practical implementation discipline

- Prefer Laravel's native `DB::transaction` semantics on the **explicitly correct database connection**; do not build a custom Transaction Coordinator/Unit of Work framework.
- Controllers remain HTTP adapters, not owners of critical business transitions.
- Each module maintains the **One Writer Principle** and authoritative invariants; a transaction boundary does not permit direct writes to another module's internal tables.
- Do not hold open transactions across user interaction, network email delivery, external provider calls, long-running queue jobs or different database connections.
- Never switch Tenant context or its connection while a transaction is active.
- Central-only operations commit within Central; Cross-Context Orchestrators are addressed in Section 9.

Exactly which persistence writes share a transaction is specified and tested per Feature.

## 5. Concurrency Control & Database Locking — TC-02

### 5.1 Targeted PostgreSQL protections

Choose the least complex **correct** mechanism for each owning workflow:

- **Pessimistic row locks** such as `SELECT ... FOR UPDATE` / Laravel `lockForUpdate()` when an operation must read a mutable business state and then make a protected decision.
- **Atomic conditional updates** when a single database-side predicate and affected-row check can safely enforce sufficient availability or an eligible state transition.
- **Unique / check / foreign-key constraints**, when applicable, as PostgreSQL-enforced backstops against races and invalid persisted states.
- **Optimistic concurrency** (e.g. version or expected-value conditional writes) where overwriting concurrent edits is a genuine risk but long locks are unnecessary.

A normal unprotected `read → check → write` sequence is **not** enough for competing last-unit reservations or one-time financial effects. For multi-line orders, acquire/protect the relevant stock records and check the whole eligible Location set inside a coherent transaction before confirming.

### 5.2 Lock/retry safeguards

Use **short transactions**, a **consistent lock acquisition order** for multi-row/multi-item updates and **bounded retries** for properly classified deadlocks/serialization failures. If a retry follows an uncertain outcome, recover the actual operation state first; retries must not create a new business operation.

Do not apply `SERIALIZABLE` globally or substitute Redis Distributed Locks for PostgreSQL business correctness. Higher isolation or supplementary coordination may be justified later for a measured, specific feature; neither is the default MVP architecture.

### 5.3 Illustrative operation choices

| Business action | Preferred protection (feature-specific) |
| --- | --- |
| Reserve stock / confirm order | Row locking and/or conditional updates covering all eligible lines, plus protected order transition |
| Direct Sale and stock movement | Atomic stock/state transitions, unique movement reference and one owning transaction |
| Dispatch / pickup | Protected lifecycle transition and once-only stock consumption |
| Collection / refund execution | Protected financial eligibility and unique real-world execution reference; concurrent total limits enforced |
| Nonfinancial product edits | Conditional update / optimistic concurrency if lost updates matter |
| Read-only catalog queries | No unnecessary write locks |

Lock targets, indices, isolation level and SQL statements remain **Feature Plan** details, verified on real PostgreSQL.

## 6. API & Business Idempotency — TC-03

### 6.1 Two distinct layers

**HTTP/API layer (API-05):** selected high-risk endpoints require/support client-generated `Idempotency-Key` with durable scope to the validated Tenant (or Central for Central-only work), operation and appropriate actor/contract context. The same key and canonical payload can recover/replay the already authorized outcome without repeating effects; different content with the same key conflicts (e.g. HTTP 409). In-flight attempts use a safe documented conflict/pending result, not parallel execution. A replay must not leak a stored response after access was revoked.

**Business layer:** the owning module ensures one physical/business effect occurs once **even if clients use different HTTP keys or a Horizon job is redelivered**. Use stable operation identity, guarded state transitions, unique records/constraints and ACID concurrency controls. Expiring HTTP deduplication state must not permit another refund, dispatch, reservation or real collection of the same effect.

`X-Request-ID` correlates an individual HTTP attempt; it is **not** an idempotency key.

### 6.2 Separate duplicate effects from legitimate repetitions

- A confirmed order must not be confirmed/reserved a second time, even under different request keys.
- A stock movement created for a particular authorized physical event must not be applied twice.
- Multiple **genuine** collections or partial refunds may legitimately belong to one order; deduplicate by the actual financial event/execution reference and enforce amount/state constraints, **not** by order ID alone.
- Two independent purchases with identical items/prices are not automatically duplicates; where clients submit different keys, infer common intent only from a *trusted business intent reference* defined by the Feature, never from superficial payload similarity.
- The `Idempotency-Key` record, business event identity and unique financial/stock constraint are complementary protections, not interchangeable tables or concepts.

A generic system-wide business idempotency engine is **not selected**. Reuse existing domain references wherever sufficient; add purpose-built operation records only for real requirements.

### 6.3 Ambiguous completion

On timeout after a possible commit, read the durable operation/business state before re-executing anything. If an external financial effect is uncertain, **reconcile the external evidence first**; do not infer success or failure from an HTTP/network timeout.

## 7. Transactional Outbox — TC-04

### 7.1 Ownership and atomic recording

Record a published event *intent* in the **same authoritative PostgreSQL transaction** as the business change it describes:

- Tenant business operation → outbox record in that Tenant Database.
- Central-only platform operation → outbox record in Central Database.

A failed transaction saves neither the business effect nor its outbox record. After successful commit, the outbox record remains durable even if Laravel crashes or Redis/Horizon are unavailable.

Do not send business-critical external messages inside the transaction or depend on Laravel's `dispatchAfterCommit` **alone** as a durable event guarantee; there is still a potential crash gap between DB commit and queue dispatch.

### 7.2 Published event contract

Each deliberately published integration event uses appropriate, reviewed metadata: unique event ID, event type, schema version, occurrence time, Central/Tenant context, Tenant ID where applicable, aggregate/operation reference, correlation ID and **minimal authorized payload**. Domain-internal events do not become public contracts automatically.

Exact JSON/column schema, retention, index strategy, encryption and serialization belong to Queue/Integration Standards and Feature Specs; do not infer universal fields from illustrative examples.

### 7.3 Lightweight publisher / relay

An **Outbox Relay** discovers pending committed events, safely claims bounded batches and publishes them to Redis queues for Horizon consumers. Duplicate publishers may be constrained with ordinary PostgreSQL claims/locks; `FOR UPDATE SKIP LOCKED` is an implementation option *when justified*, not a mandatory table/query design. Use persisted publication/attempt state and periodic recovery of stale/unfinished intents; an optional post-commit wake-up is an optimization, never the sole reliability path.

Central and Tenant outboxes must be discovered under **explicit, trusted contexts**. Design bounded polling/connections and fairness across merchants; do not assume a permanently open DB connection or dedicated worker for each merchant. Queue topology, poll cadence, throughput and operational thresholds are intentionally deferred and measured.

**Delivery guarantee: at-least-once**, not exactly-once. A relay may publish successfully then crash before saving its published flag; that event will legitimately be retried.

## 8. Event Delivery & Consumer Consistency — TC-04

### 8.1 Safe consumers

Consumers must:

1. Validate event type/schema and trustworthy Central/Tenant identification; event-supplied Tenant ID is **not** authorization by itself.
2. Re-resolve the proper execution context and required permissions/entitlements when relevant; reject mismatched/unavailable Tenants.
3. Use **event ID + consumer-specific idempotency boundary** or equivalent domain-operation uniqueness. For local DB effects, persist both effect and processed-event proof atomically where practical.
4. Tolerate duplicate, delayed and out-of-order delivery. Re-check current aggregate state/sequence when order matters; do not blindly apply stale deltas.
5. Report failed attempts, permit bounded retry and audited recovery, and clean Tenant connection/context between jobs.

A received event is not permission to bypass a module's normal stock, money or authorization invariants.

### 8.2 Appropriate asynchronous effects

Email intentions, in-app notifications, reporting projections and noncritical downstream synchronization may follow successful commits. Critical eligibility, order confirmation, inventory reservation/consumption and verified financial collection **must remain synchronous authoritative checks** in their owning business workflows.

For outbound email, `queued`, `attempted`, `provider accepted`, `failed` and proven `delivered` are different concepts. External providers may not offer exactly-once delivery or verifiable delivery status. Duplicate prevention is best effort according to provider capabilities, and the system must not claim payment or delivery success on a mere queue acknowledgement.

Avoid Kafka, RabbitMQ and enterprise streaming infrastructure for the first MVP; Redis/Horizon are the approved asynchronous transport.

## 9. Central/Tenant Cross-Database Operations — TC-05

### 9.1 No shared ACID across databases

Central and Tenant PostgreSQL databases commit independently. Cross-Context Orchestrators own **durable workflow progress**, stable step/operation references, retry-safe preconditions, result verification, reconciliation and appropriate **business-specific** compensation. They are not a general Saga/Distributed Transaction framework.

Cross-database references must include validated context; foreign keys do **not** span Central and Tenant databases. Do not change Tenant context during an active local transaction.

### 9.2 Tenant provisioning

The existing **Platform & Tenants** workflow is initiated by authorized **Platform Super Admin** and follows the approved conceptual states:

`Pending → Provisioning → Ready / Failed`

Use a single coordinated Horizon provisioning workflow, with guarded execution. Create or verify the tenant DB and restricted PostgreSQL roles; apply required migrations/bootstrap; validate readiness; mark Central `Ready` only after required Tenant validation. If a crash occurs after DB creation but before the Central state change, **inspect and resume** instead of recreating resources.

Never activate an incomplete tenant or automatically delete its database following provisioning failure/subscription expiry. Technically `Ready` does not bypass independent subscriptions and entitlements. Reconcile partial setup with a traceable authorized recovery path.

### 9.3 Workforce membership initialization and revocation

Central workforce membership is distinct from Tenant-local role/Location assignments. Invitation/acceptance cannot grant active access until required local setup is validated; failures remain inactive and recoverable.

**Revocation is safety-first:** deny new access using authoritative Central membership status immediately, even if Tenant-local cleanup or cache invalidation is delayed. Revoking access to Tenant A must not silently revoke the actor's independent legitimate Tenant B membership. Sensitive queued actions re-evaluate their required authorization before applying effects.

### 9.4 Other cross-context operations

SaaS entitlement propagation and authorized Central reporting use owning-module contracts, explicit scope and durable coordination where **linked writes** can partially succeed. A read-only cross-context query does not require a workflow state machine merely because it reads two databases. Never move Tenant-owned business data into Central by default.

## 10. Failure Recovery, Retries & Reconciliation — TC-06

### 10.1 Classify before retrying

| Failure class | Example | Safe direction |
| --- | --- | --- |
| **Transient, confirmed-safe** | Deadlock or temporary connectivity issue with clearly rolled-back/no effect | Bounded retry with backoff/jitter where appropriate; revalidate business state |
| **Business conflict** | Insufficient available stock, invalid order state, revoked authorization | Do not retry blindly; return a meaningful conflict or denial |
| **Unknown outcome** | Lost HTTP response after possible commit or ambiguous external collection | Inspect authoritative records/evidence **before** any retry or new external effect |
| **Infrastructure unavailable** | Redis or Tenant DB unavailable | Fail closed; retain durable work and resume after recovery |
| **Permanent/configuration error** | Bad schema, invalid credentials, unsupported event schema | Stop unsafe automation; alert and require authorized correction |
| **Partial Central/Tenant workflow** | Tenant setup succeeded but Central finalization failed | Resume from durable step/proof; reconcile without duplicating completed work |

Retry budgets, backoff, jitter, timeouts, alert thresholds and maximum age are defined per Queue/Feature Standards, **not guessed universally**. A retry must preserve the same logical operation reference; if a process cannot establish whether a previous financial or inventory effect committed, do not blindly repeat it.

### 10.2 Module-owned reconciliation

Orders validates order state against recorded reservation evidence; Inventory validates operation-keyed movements and actual location stock; Collections validates the **actual verified financial transaction**, not merely an attempted request; Returns/Refunds distinguishes approval from completed money movement and eligible physical restocking.

The Outbox relay reconciles pending/stale publications, Horizon tracks failed/retried jobs, and Central workflows compare their durable status with real Tenant initialization/membership state. The owner of each business fact performs any correction through authorized APIs/contracts, with preserved history and audit.

For an **uncertain external charge or refund**, do not issue a second request solely because of timeout. Obtain trustworthy provider/physical evidence or escalate for authorized manual review. Never fabricate a successful refund or invent automatic refunds without an approved business flow.

### 10.3 Recovery semantics

Maintain actionable failure state, safe correlation, bounded automated recovery, and **manual escalation** for unresolved or sensitive outcomes. Compensation is not synonymous with deleting a partially created DB or reversing financial history. Distinguish **application/workflow recovery** (this section) from disaster recovery and infrastructure restore policy (outside this document).

## 11. Financial & Inventory Safety Rules

Use business-level constraints consistently **across** HTTP, scheduled jobs and replays:

| Risk | Required defensive behavior |
| --- | --- |
| Last unit sold to two competing orders | Locks or atomic conditional stock update; only one eligible reservation succeeds |
| Partial availability for multi-line confirmation | All relevant lines and one eligible Location validated atomically; no successful partial confirmation |
| Repeated Manual Order confirm | Protected lifecycle transition and one business reservation effect |
| Pending Stock becomes available | May notify/reprioritize according to feature rules; never auto-confirm or auto-reserve |
| Direct Sale with externally successful but incomplete handover | Follow the approved exception/reconciliation workflow; do not mark a fictitious completed sale/refund |
| Repeated dispatch or pickup | One authorized physical stock consumption; delivery is a separate lifecycle fact |
| Repeated financial reference | Prevent the same verified collection/refund execution twice, including across different API keys |
| Separate legitimate partial collections/refunds | Permit them under explicit rules; guard sum, state, evidence and authority rather than unique-by-order |
| Refund requested or approved | No claim of paid refund without verified execution evidence |
| Historical correction | Preserve original facts and add authorized correction/reversal lineage; never silently overwrite legitimate history |

Checks that must determine the immediate correctness of stock or actual financial facts **cannot** be deferred to asynchronous event consumers. No new payment gateway, offline sales mechanism, or universal auto-refund behavior is inferred from consistency architecture.

## 12. Tenant Context, Audit & Observability

### 12.1 Trusted scope and connection lifetime

Every HTTP operation, job, relay batch and reconciliation step must use its verified Tenant or Central context. Tenant identifier fields, domain names or request headers are selectors/hints only until validated against authoritative mappings and permissions. PostgreSQL runtime roles and connections must remain least-privileged and isolated. There is **no fallback to Central or a previous Tenant on error**.

For Horizon jobs: carry trusted context references, revalidate as appropriate, activate the correct connection, and clear context even on exceptions; sequential Tenant A / Tenant B / Central jobs must not leak state. Compatibility with Stancl/Laravel 13 and its `QueueTenancyBootstrapper` integration requires actual testing.

### 12.2 Audit and operational signals

Log business-critical operation identity, actor/authorized context, Location, state transitions/corrections, reason, safe correlation and outcomes where appropriate. Keep **Tenant business audit local** and Platform administrative audit **Central**, consistent with BA-14.

Operational visibility should cover database deadlocks/locks/slow queries, failed Horizon jobs, retry exhaustion, outbox backlog/age, unknown money outcomes, stuck provisioning and failed reconciliation. Use structured logs and `X-Request-ID` to correlate HTTP attempts to trustworthy business references; `Idempotency-Key` remains a logical operation mechanism. Avoid storing secrets, session values, full payment details or unnecessary personal data in logs/events.

No numeric SLO, alert limit or throughput claim is approved by this document; configure and validate thresholds with representative workload evidence.

## 13. Consistency Testing & Release Gates — TC-07

### 13.1 Testing strategy

**Risk-based progressive gates**: focused tests for every affected PR; real integration tests for sensitive code; broader E2E, fault, security, deployment and measured-load suites at suitable integration/pre-Pilot stages. Tests must use actual PostgreSQL behavior where locks/transactions are at issue; mock-only or sequential-only tests do not prove race safety.

A meaningful tenancy fixture includes **one Central PostgreSQL database and at least two isolated Tenant databases**, using actual trusted context and appropriate database roles. Include Redis/Horizon and the real event/outbox integration path when that boundary is under test.

### 13.2 Minimum critical consistency scenarios

| Test group | Required evidence |
| --- | --- |
| **Atomicity** | Reservation/order/outbox writes commit together; injected failure anywhere before commit rolls back all effects |
| **Concurrent availability** | Two independently connected concurrent orders compete for last unit; no overselling or false confirmation |
| **State transitions** | Repeat confirm/dispatch/pickup with same and different request keys; each physical stock effect once |
| **Financial integrity** | Duplicate collection/refund execution rejected while independent approved partial events remain possible; uncertain external outcomes not automatically replayed |
| **Two-layer idempotency** | Same key/same request, same key/different request, concurrent in-flight, different keys/same business intent, post-commit lost response |
| **Outbox/Horizon** | Redis outage after commit, relay crash after publish, duplicate/reordered event, consumer retry, failure recovery and no repeated local effects |
| **Central/Tenant coordination** | Crash after Central commit and after Tenant commit, resumed provisioning, failed staff-local setup before Central activation, safety-first revocation |
| **Isolation** | Tenant A/B/central job sequence; invalid Tenant and Location IDs, denied cross-tenant reads/writes and safe connection cleanup |
| **Migration/release** | Tenant schema drift, retryable batch migration, compatible Blue-Green activation/rollback behavior where applicable |

Use deterministic failure injection in meaningful places; do not require a bespoke Chaos Engineering platform or exhaustive load suite for each PR. Each Spec Kit Feature identifies its own negative cases and concrete tests; PR review and CI evidence match risk level.

### 13.3 Mandatory blocking failures

Cross-tenant data leakage, authorization bypass, continued unauthorized access after required revocation, overselling, duplicate critical stock/collection/refund effects, invalid committed order-reservation state, destructive loss of historical business evidence and unhandled loss of durability for required critical events are **merge/release blockers as applicable**. Do not mark such failures as nonblocking warnings.

**Pilot readiness** remains governed by existing BA-30 gates **G1 through G10** (Tenant safety; commerce consistency; recovery readiness; release process; capacity; security/privacy; legal; operational completeness; environment separation; explicit sign-off). All need real owned evidence. **BA-16 is DEFERRED and G3 remains BLOCKED for a live Pilot**; this document does **not** select, implement or discuss a backup product/strategy. Documentation and nonproduction development may proceed while separate Pilot prerequisites remain pending.

## 14. MVP Boundaries & Pending Details

### 14.1 Approved lightweight approach

Use Laravel native transactions, PostgreSQL row/conditional locking and constraints, owning-module Application Orchestrators, targeted HTTP + business idempotency, per-database transactional outbox, lightweight relay, Redis/Horizon idempotent consumers, explicit staged Central/Tenant workflows and risk-based tests.

**Not selected for MVP:** custom transaction / Unit-of-Work framework, global `SERIALIZABLE` policy, Redis locks as a substitute for PostgreSQL integrity, universal idempotency engine, generic Saga / distributed-ACID framework, Kafka/enterprise streaming, automatic financial compensation without evidence, or heavy fault/load suites on every PR.

### 14.2 Deferred implementation specifics

Final transaction/lock choices, business-reference/unique-key designs, idempotency table layout and TTL, record status semantics, outbox schema and versioned event envelopes, relay polling/claiming/retention, retries and backoff, queue concurrency, per-Tenant fairness and connection budgets, deadlock/timeout thresholds, financial evidence verification, reconciliation tools, operational alerts and measured capacity belong in **Queue/Job/Integration Standards**, **Feature Specs**, **Foundation integration validation** and operational testing.

Pending package/environment verification (including Laravel 13 / Stancl PostgreSQL and Horizon integration) remains distinct from approval of a design. Do not infer benchmark results, deployability or a date for merchant activation.

## 15. Final Approval & Implementation Boundary — TC-08 (A)

**APPROVED: Lightweight Transactions & Consistency Architecture Baseline, decisions TC-01 through TC-08.**

The approved baseline is: **strong, tenant-local PostgreSQL ACID consistency; targeted concurrency protection; two-layer idempotency; durable database-local transactional outbox; at-least-once, retryable event processing; lightweight, recoverable Central/Tenant coordination; and risk-based verification of business-critical invariants**.

This architecture does **not** claim that production migrations, actual locks, code, queue consumers, CI tests, recovery procedures or Pilot gates are implemented or passing. Follow approved D/OV/BA/MA/TA/DA/IA/API decisions; obtain explicit Feature-level approval before implementing a sensitive workflow and independent deployment authorization before a live Pilot.

**This document authorizes no application-code changes, package installations, environment changes, deployment, or modification of other repository files.**
