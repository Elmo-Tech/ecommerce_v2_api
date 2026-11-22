# E-commerce SaaS V2 — Queues, Jobs & Event Processing Standards

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/queue-jobs-event-processing-standards.md`  
**Version:** 1.0 — Approved Queue & Event Processing Architecture Baseline  
**Prepared:** 2026-11-17  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — QJ-08 (A)**  
**Decision Coverage:** **QJ-01 through QJ-08**  
**Implementation Status:** **Not implemented, installed, tested, validated or deployed**

> **Reading rule:** This document approves MVP-first architecture decisions, not executable code, configuration, package compatibility, observed performance or permission to activate live merchants. Feature specifications, integration evidence, measured resource budgets and separate release sign-off remain necessary.

---

## 1. Document Overview, Authority & Scope

### 1.1 Purpose

Define reliable, tenant-safe and operationally manageable asynchronous work for a **Laravel 13 API-first Modular Monolith** with **one Central PostgreSQL database and an independent PostgreSQL database per Merchant (Tenant)**, shared Redis, Laravel Horizon and the conditionally selected Stancl tenancy integration.

The goals are to maintain PostgreSQL as the authority for critical business facts, provide durable post-commit event intent, tolerate duplicate/reordered delivery, fail closed on tenant context errors, isolate heavy work and make runtime failures observable and recoverable without adding excessive infrastructure.

### 1.2 Authority

Read with these approved sources:

- [Backend Architecture](./backend-architecture.md): BA-09 (Horizon/Redis), BA-24 (observability), BA-28 (notifications), BA-29 (measured scaling), BA-30 (Pilot gates).
- [Module Architecture](./module-architecture.md): Central/Tenant execution contexts, One Writer Principle, Application orchestrators, Public Contracts, event categories and publishing rules.
- [Tenancy Architecture](./tenancy-architecture.md): trusted Tenant identity, Stancl `QueueTenancyBootstrapper`, cleanup, fail-closed isolation and PostgreSQL role boundaries.
- [Transactions & Consistency Architecture](./transaction-consistency-architecture.md): TC-01…TC-08, especially idempotency, per-database Outbox, at-least-once delivery, classified recovery and risk-based tests.
- [Data Architecture](./data-architecture.md): PostgreSQL-owned records and integrity, database-local IDs and restricted Central/Tenant references.
- [Identity & Access Architecture](./identity-access-architecture.md): Central workforce membership, Tenant-local roles/Locations and immediate access revocation.
- [API Architecture](./api-architecture.md): targeted API `Idempotency-Key` and separate `X-Request-ID`.

Approved D-series business rules and owning Feature specifications define business meaning. This document does not invent new inventory, orders, payments, customer messaging or permission behavior.

### 1.3 Boundaries

In scope: queue topology, job context and minimal payload, scheduled Outbox relay, publishing semantics, retry/failure policy, consumer idempotency/ordering, monitoring, recovery, capacity testing and release acceptance. Out of scope: actual PHP implementation, a final SQL schema, installed infrastructure, numeric SLOs, new payment providers, generic distributed transactions and the separate cache architecture.

## 2. Approved Decisions — QJ-01 to QJ-08

| Decision | Approved option A | Binding architectural direction |
| --- | --- | --- |
| **QJ-01** | **Shared Redis + Laravel Horizon with Logical Queue Separation** | Shared queue infrastructure, optional logical `platform` / `critical` / `default` / `notifications` / `bulk` classes, bounded worker capacity and strict Central/Tenant scope; no per-Merchant workers or Redis instances. |
| **QJ-02** | **Stancl Tenant-Aware Jobs + Explicit Trusted Execution Context** | Scoped references, revalidation of current permissions as appropriate, fail-closed behavior, idempotent work and cleanup after success/error/retry; Laravel 13/Stancl/Horizon compatibility unverified. |
| **QJ-03** | **Shared Scheduled Outbox Scanner/Relay** | Laravel Scheduler, bounded Central/Tenant scans, durable PostgreSQL claims/leases, post-commit publish to Redis, periodic recovery and at-least-once semantics. |
| **QJ-04** | **Job-Specific Native Retry, Timeout & Failed Job Policies** | Failure classification, bounded attempts, safe backoff, timeout/`retry_after` alignment, reconciliation of unknown outcomes, failed-job visibility and authorized recovery. |
| **QJ-05** | **Durable Consumer-Level Idempotency + Selective Ordering** | Consumer-scoped business-effect deduplication and per-aggregate ordering only when required; no global FIFO, sequencer or Redis cache lock as the sole guarantee. |
| **QJ-06** | **Native Horizon Monitoring + Lightweight Operational Recovery** | Structured logs, safe correlation, Outbox/Scanner/Horizon health, tenant-aware diagnostics, risk-based alerts and recoveries governed by authoritative PostgreSQL state. |
| **QJ-07** | **Measured Capacity + Bounded Budgets + Progressive Scaling** | Throughput, lag, P95/P99, Redis/PostgreSQL pressure, multi-Tenant fairness, normal/burst/recovery/Blue-Green testing, vertical scaling first. |
| **QJ-08** | **Lightweight Queue & Event Processing Standards Baseline** | Approval of QJ-01…QJ-07 and this 15-section document, not a claim of implementation or readiness. |

**All QJ decisions are approved for architecture.** Runtime settings, package integration tests, capacity measurements and Pilot gates are **Pending Validation**.

## 3. Synchronous vs Asynchronous Processing Boundaries

### 3.1 Synchronous, authoritative business operations

A queue must never replace the owning Application use case's protected PostgreSQL transaction for critical eligibility and factual effects. Synchronous safeguards remain mandatory for:

- Order/Manual Order confirmation with required prices, permissions, entire-order availability and atomic eligible single-Location reservation.
- Direct Sale's verified full collection and immediate handover, with its approved exception procedure.
- Physical dispatch/pickup's authorized once-only stock consumption and independent delivery outcome.
- Verified actual collection and refund execution evidence, financial amount/state guards and audit.
- Critical Tenant/Location authorization and irreversible lifecycle transitions.

The logical `critical` queue means **important appropriate asynchronous follow-up**, not asynchronous business truth. Redis/Horizon do not become the source of order/stock/money correctness.

### 3.2 Eligible background work

Suitable examples include approved email intentions/in-app alerts, derived reporting projections, authorized exports/imports, bounded platform provisioning, cross-context follow-up and integration-event consumers. They reference committed facts, not invented completed business events.

### 3.3 Durable transition into asynchronous processing

When an event must accompany a business write, commit its **Outbox intention in the same authoritative PostgreSQL transaction**. A post-commit in-memory callback or direct Redis dispatch alone cannot eliminate the crash window between PostgreSQL commit and queue submission. Do not keep the business transaction open across external network activity.

## 4. Shared Redis & Horizon Queue Topology

### 4.1 Logical workload classes

| Queue class (proposed) | Intended jobs | Capacity policy |
| --- | --- | --- |
| `platform` | Central/provisioning coordination | Scoped Central authority; bounded processing |
| `critical` | Time-sensitive *post-commit* operations | Protected from bulk starvation |
| `default` | Normal background actions | Tuned to real workload |
| `notifications` | Email and in-app messaging | Provider-aware, bounded concurrency |
| `bulk` | Imports, exports, heavy projections | Chunked and restricted resources |

These are **logical classes**, not a mandate for five Redis servers or dedicated supervisors from the start. Lower-traffic classes may share appropriate Horizon pools; separation and priorities are tuned after measurement.

### 4.2 Shared transport and isolated authority

The Redis **queue connection** must remain explicitly environment-scoped and **not acquire an unintended Tenant cache prefix**. Tenant-specific cache namespaces do not grant jobs access to other Tenant data and cannot silently change Horizon's queue transport.

Central-only and Tenant jobs use explicit, distinguishable execution contexts on shared workers. No worker or redis deployment is permanently assigned per Merchant.

### 4.3 Resource safety

Budget Horizon worker concurrency alongside API RAM/CPU, PostgreSQL connections and Redis pressure. Reserve capacity for important work; do not let large exports/imports consume every worker. Worker count, supervisor/queue allocations and infrastructure sizes are **not predetermined**.

## 5. Central/Tenant Job Execution Context

### 5.1 Execution types

| Type | Scope | Examples |
| --- | --- | --- |
| **Central-only** | Authorized Central connection only | SaaS lifecycle and guarded platform administration |
| **Tenant-only** | Verified Tenant identity and its own PostgreSQL connection | Notifications, exports, tenant reporting |
| **Cross-context workflow** | Owning durable orchestrator coordinating independent scopes | Tenant provisioning and workforce role initialization |

The cross-context category does not establish shared Central/Tenant ACID. Every stage has an explicit scope, proof of progress, retry-safe preconditions and reconciliation when steps partly succeed. A Platform role is not unrestricted Tenant business authority.

### 5.2 Trusted context initialization

Every job attempt:

1. Determines its intended Central/Tenant/workflow scope.
2. Resolves a trusted Tenant reference or approved workflow state from the Central directory, not a raw client-supplied ID as authorization proof.
3. Verifies applicable Tenant state/entitlement and actor or system authority.
4. Initializes the correct Stancl-managed tenant or explicit Central connection.
5. Executes module-owned constraints and idempotency under that scoped connection.
6. Cleans execution state after the attempt.

An unknown Tenant, unauthorized actor, mismatched scope, unavailable database or credential error must **fail closed**. No default-Central or previous-Tenant fallback is permitted.

### 5.3 Stancl integration is conditional

Use Stancl's `QueueTenancyBootstrapper` and Laravel/Horizon mechanisms when proven compatible with **Laravel 13**, including long-running worker lifecycle, database switching and failed/retried jobs. No custom Job Context Framework is selected.

Mandatory behavioral proof includes **Tenant A → Tenant B → Central** in a reused worker, plus failure/retry variations and correctly unprefixed queue transport.

## 6. Job Payload, Authorization & Cleanup

### 6.1 Minimal job contract

A Job carries only justified logical references: execution scope, trusted Tenant/workflow identifier, stable Event/Operation ID, minimal resource reference or authorized payload, correlation ID, event type/schema version and optionally actor/Location reference where current permission matters.

This is a **conceptual contract**, not a final field list or JSON schema. Do not serialize session cookies, unrestricted Eloquent object graphs, plaintext credentials, DB connections, unnecessary personal data or full sensitive payment records. Load current business objects from the verified owning database when executing.

### 6.2 Authorization by job nature

**User-initiated deferred jobs**, such as exporting customer records, recheck the initiating workforce identity's **current** active Central membership, tenant-local role, Location/resource scope and feature eligibility before revealing data or performing an action. Revocation blocks unexecuted unauthorized work.

**System-owned follow-up to an already committed business event**, such as an order-confirmation email intention, does not become invalid **solely** because its original staff actor later lost membership. Such work runs under a scoped system obligation, while still validating Tenant state, event authenticity and business/privacy rules. Do not treat an expired actor token as the only authority for a committed business obligation.

Cross-context lifecycle steps use the durable workflow's authority and independently approved operator permissions where a new operator action is requested.

### FBR-02-04/18 — Tenant Lifecycle-Aware Outbox and Obligation Recovery

Suspension does **not** delete previously committed Tenant-local Outbox intent or erase confirmed business history. **FBR-02-04:** a scheduled relay/consumer encountering a Tenant suspended/unavailable for the intended effect must retain/hold its durable pending event, avoid automatic unauthorized side effects, expose the reason/backlog and retry only when verified state and policy permit. Never treat a queued event as an authorization bypass.

**FBR-02-18:** distinguish **Commercial Suspension** (no new orders/sales or new entitlement-dependent activity; narrowly enumerated actions to complete previously confirmed obligations may remain available under current permissions) from **Security/Legal Suspension**, which fails closed unless a separately authorized lawful recovery process is approved. A valid system-owned already-committed obligation is not cancelled merely because the originating employee lost permission, but it still needs Tenant lifecycle, obligation eligibility, consent/privacy, business state and idempotency validation. An older Manual Pending Stock is **not** a confirmed obligation or automatic stock reservation. Resume/cancel decisions are module-owned and audited, not a blind bulk replay after renewal.

The SaaS Subscription Feature must publish a versioned **allowed-operations-by-suspension-reason matrix** before enabling commercial suspension. No new workflow engine, blanket exceptional Platform access, or duration/grace-period default is approved here.

### 6.3 Cleanup and retry

Clear Stancl Tenant Context, DB connections and mutable authorization/cache scope after each attempt, including exceptions. A killed process cannot run its own `finally`: the **next attempt/worker must initialize cleanly**, and any stale lease must recover safely.

Never switch Tenant in an active transaction. Retries preserve stable Event/Operation IDs and revalidate necessary current permissions. Private files/exports require authorization beyond storage-path isolation.

## 7. Transactional Outbox Relay & Scheduling

### 7.1 Ownership and publication boundary

**Tenant business transaction → Tenant PostgreSQL Outbox.**  
**Central platform transaction → Central PostgreSQL Outbox.**

Business effect and associated published-event intent must commit together. Redis publishing happens after commit; no universal Central copy of tenant Outboxes or cross-database ACID is introduced.

### 7.2 Shared scheduled relay

Use **Laravel Scheduler** to trigger a lightweight scanner that does **not depend exclusively on Redis jobs to start**. The relay:

1. Includes Central Outbox and discovers eligible Tenant DBs through the trusted Central Tenant Directory.
2. Visits databases with bounded scan time, batch size and live connection concurrency; clears each scope between visits.
3. Locates committed pending or stale-recoverable events in the owning DB.
4. Atomically claims available events using PostgreSQL conditional state/short locks and a recoverable **lease/ownership** concept.
5. Releases the claim transaction **before** network publication, avoiding long DB locks across Redis calls.
6. Publishes minimal, trusted event references to the selected shared Redis queue with the original Event ID.
7. Records the publication attempt/outcome back in the originating Outbox under safe claim-ownership checks.
8. Recovers expired/stuck claims, rechecks pending work and exposes failures to operational monitoring.

`FOR UPDATE SKIP LOCKED` is one possible PostgreSQL claim implementation, not a frozen schema/query requirement. A stale worker must not overwrite another worker's newer claim outcome; duplicate publication can still occur and is acceptable only because consumers are idempotent.

### 7.3 Failure cases

| Failure | Behavior |
| --- | --- |
| Business transaction rolls back | No committed business effect or outbox event |
| Business commits, Redis unavailable | Outbox remains; scanner retries after recovery |
| Scanner/relay dies before publishing | Stale claim reappears for safe recovery |
| Relay publishes then crashes before marking outcome | Same Event ID may be published again |
| One Tenant DB unavailable | Fail closed for that scope; safely continue other scopes |
| Scanner stopped while Horizon looks healthy | Monitor scan liveness, backlog and oldest pending age |
| Two scheduler instances overlap | Durable claim discipline; consumers still protect against duplicates |

An optional post-commit wake-up may improve latency later but **scheduled polling remains the recovery path**. Do not depend on a permanently connected per-Tenant relay.

### 7.4 Bounded fairness

Tenant scanning must not create one permanent DB connection per Merchant. Bound each Tenant's work so a heavy backlog cannot monopolize the scanner; measure fairness and connection churn. Exact eligibility/ordering/interval/batch sizes/lease duration remain for implementation and performance tests.

## 8. Event Publishing, Contracts & Delivery Semantics

### 8.1 Explicit event classification

**Internal Domain Events** remain internal unless deliberately published; **Published Integration Events** are reviewed contracts; **Technical Events** describe operational lifecycle. Published metadata includes a unique Event ID, event type, schema version, occurrence time, Central/Tenant scope, Tenant reference where applicable, aggregate/operation reference, correlation ID and minimal authorized payload.

Final serialization, version fields, retained data and event naming belong to contract specifications. Optional compatible extensions are allowed when consumers remain compatible; breaking semantic/schema changes require controlled transition and contract tests.

### 8.2 At-least-once is not exactly-once

- PostgreSQL ACID proves both authoritative local business effect and intended event committed.
- Relay publish success means Redis accepted work, **not** that a consumer processed it.
- Horizon completion for a notification does not prove externally delivered email unless trusted provider evidence exists.
- A crash after publish but before recording outcome may cause duplicate event delivery.
- Consumer effects must be guarded against duplicates and reordered delivery; no global FIFO or exactly-once external guarantee exists.
- Actual Redis/worker durability and recovery behavior need integration testing; a transport acknowledgement alone is not business evidence.

### 8.3 Notification/provider boundary

For email, distinguish queued, attempted, provider-accepted, failed and **Delivered only with trusted evidence**. Provider idempotency can help where available, but ambiguous acceptance must be reconciled or safely handled rather than automatically assumed failed.

The architecture is COD-first and does not introduce an online payment gateway, auto-refund, WhatsApp/SMS or shipping integration merely by defining adapters.

## 9. Retry, Timeout & Failed Job Policies

### 9.1 Classified failure handling

| Class | Action |
| --- | --- |
| **Confirmed-safe transient** | Bounded automatic retry with backoff/jitter as needed |
| **Business conflict / revoked authority** | Stop; do not retry in an attempt to bypass rules |
| **Unknown outcome** | Inspect authoritative business records/provider evidence before repeating any effect |
| **Infrastructure unavailable** | Fail closed; preserve durable outbox/workflow facts, then resume |
| **Permanent schema/configuration** | Stop futile retries, alert and require correction |
| **Cross-context partial** | Resume owning workflow from validated durable stage |

External payment/refund/collection outcomes are **never** blindly retried after uncertainty. A financial refund approval does not imply completed refund. Do not invent compensation, an automatic refund or a second financial execution.

### 9.2 Laravel/Horizon native controls

Use **job-specific** `tries`, `backoff`, `timeout` and `retryUntil` as appropriate, with queue connection `retry_after` aligned so the worker/job timeout is shorter than the effective retry/redelivery window, with safe headroom and external-call timeouts considered.

This reduces accidental overlap but does not substitute PostgreSQL uniqueness and business state guards. Numeric values, jitter and time budgets must be tested, not guessed globally.

### 9.3 Failed-job visibility and recovery

Horizon Failed Jobs describe technical attempts; **PostgreSQL owning-module state remains the business source of truth**. On retry exhaustion, show actionable diagnostic identity and authorize operator review: inspect persisted operation, distinguish already-committed effects, safe resumption, invalid authorization and unresolved external outcomes.

Never blindly `retry all` across production business jobs. Deleting/clearing a failed-job record does not reconcile the underlying workflow. Manual recovery preserves original logical identity, trusted Tenant scope and auditable operator action.

## 10. Consumer Idempotency & Event Ordering

### 10.1 Consumer-specific durable idempotency

Logical identity: **trusted execution scope + Consumer identity + Event ID**, or equivalent business-operation uniqueness. The same event may have different legitimate consumers, each of which processes its own effect once. Numeric local IDs can repeat across Tenant databases and cannot identify scope alone.

Where effect and consumption proof are in the same PostgreSQL DB, persist them atomically via an owning module transaction; use unique constraints, guarded transitions, conditional updates or targeted row locks to prevent two consumers concurrently applying the same effect. Reuse existing module operation references where sufficient; do not require a universal dedup table for every case.

Laravel cache-based uniqueness/overlap locks are optional coordination aids, **not** final integrity guarantees. Independent purchases, separate partial collections and legitimate separate refunds must not be incorrectly collapsed by payload similarity or just an order ID.

### 10.2 Ordering only where needed

Redis/Horizon workers and retries can reorder arrival. Choose per Feature:

| Effect | Safe ordering policy |
| --- | --- |
| **Latest-state projection** | Use authoritative aggregate version or rebuild; ignore stale snapshots only when semantically sound |
| **Dependent workflow** | Verify predecessor state/sequence; defer/retry/reconcile gaps where required |
| **Immutable financial/stock event** | Each legitimate movement still needs its effect once even if it arrives late |
| **Independent notification** | No artificial FIFO unless notification semantics require it |

Selective **per-aggregate** sequence/version is allowed when justified; timestamps alone are not guaranteed business ordering. No global event sequencer or global FIFO is selected.

### 10.3 External side effects

A DB-atomic processed-event receipt cannot atomically guarantee a third-party email/payment delivery. Provider-side idempotency, trustworthy outcome checks and explicit reconciliation/manual escalation remain required where applicable. Never claim exactly-once external behavior.

## 11. Monitoring, Diagnostics & Operational Recovery

### 11.1 Native and lightweight signals

| Source | Required useful visibility |
| --- | --- |
| **Laravel Horizon** | Worker state, queue lag/wait time, throughput, retries, failed jobs and processing time |
| **Outbox/Scheduler/Relay** | Last successful scan, backlog and oldest unpublished event, stuck claims, failures and publish progress |
| **Redis/host** | Availability, connection errors, memory/CPU and resource pressure |
| **PostgreSQL** | Connection count, lock/slow-query/IO pressure, unavailable Tenant DB |
| **Owning Module state** | Stuck provisioning, unresolved reconciliation, email intentions and genuine money/stock outcome |
| **Structured logs** | Safe request/correlation ID, event/operation ref, scoped Tenant identity, error class and authorized recovery actor |

Separate Production and Development monitoring. Protect Horizon/operational controls from unapproved access, keep Tenant diagnostics scoped, and never log session cookies, passwords, raw provider secrets, unrestricted PII or full financial details.

### 11.2 Operational alerts and distinctions

Alert by risk: urgent safety-critical failures, operational delays/backlog and lower-severity recovered transients. **Horizon can be idle while the scanner is broken**, so record scan heartbeat/progress and Outbox event age rather than Redis queue length alone.

Distinguish `outbox saved`, `published to Redis`, `consumer processed` and `externally delivered when proven`. No numeric SLO, backlog alarm, age limit or fixed latency assertion is approved before representative testing.

### 11.3 Safe recovery sequence

1. Detect stuck/failed/uncertain processing.
2. Identify owning module, trusted Tenant scope and original Event/Operation reference.
3. Inspect authoritative PostgreSQL and any trustworthy external outcome.
4. Classify transient/business/permanent/unknown failure.
5. Perform an authorized same-identity retry, resume, verified no-op or escalation.
6. Confirm outcome and write a suitable audit trail.

No Custom Monitoring Platform or full Recovery Dashboard is required for MVP. Horizon and scoped operational procedures may be used, with authorization and consistency checks. Queue/workflow recovery is distinct from disaster recovery.

## 12. Capacity Planning, Fairness & Scaling

### 12.1 Workload model

Capacity is driven by active Merchants, Locations/branches, peak orders, background events per operation, notifications, import/export frequency, concurrent users, P95 execution time and workload skew—not Merchant count alone.

A target of hundreds of Merchants is a **planning goal**, not verified capacity of an unspecified VPS. State scenario assumptions and measure them against real performance before approval.

### 12.2 Metrics and budgets

Measure Queue waiting time, processing duration (P95/P99 where appropriate), throughput, oldest job/event age, retries, failures and Outbox drain; redis pressure, Horizon CPU/RAM, PostgreSQL connection concurrency, locks, IO and API latency during heavy jobs.

Bound Horizon worker counts, supervisor contention, Tenant DB connections and Outbox scanner concurrency/lease/batch time. Prevent a heavy Tenant or `bulk` export from starving other Tenants or `critical` work.

### 12.3 Representative test profiles

| Scenario | Validation |
| --- | --- |
| Normal load | Stable queue latency, database/Redis resources and safe baseline |
| Burst | Queue backlog behavior, eventual draining and no lost business intent |
| Heavy Merchant | Bulk jobs do not starve other Merchants or protected queues |
| Redis / Worker interruption | Outbox recovery, redelivery and no duplicate money/stock effects |
| Multi-Tenant scanner | Fair scans across many databases and bounded connections |
| Gradual worker increase | Actual throughput and point of database/host contention |
| Blue-Green overlap | Worker drain, scheduler overlap and RAM/connection headroom |
| Isolation under load | No Central/Tenant context or private-resource leakage |

High-risk integration checks use real PostgreSQL, Redis/Horizon and **Central plus at least two independent Tenant DBs**. Broader capacity testing needs scaled, representative tenant/workload profiles; two tenants alone do not prove hundreds of merchants.

### 12.4 Scaling order

Follow **BA-29 B**: tune current queries/queues/concurrency, then vertical VPS CPU/RAM/IO when justified; separate workers/Redis/PostgreSQL/API only when measured constraints warrant; consider horizontal/multi-node after validating shared files, tenant routing, connections and failure domains.

No fixed worker count, host size, PgBouncer/custom pooling or aggressive pre-provisioning is selected for the first Pilot.

## 13. Integration Tests & Release Gates

### 13.1 Package and isolation validation

Before implementation is claimed complete, verify real **Laravel 13 + Stancl `QueueTenancyBootstrapper` + Horizon + Redis + PostgreSQL** integration. Test Tenant A → Tenant B → Central jobs in a reused worker, including failures and retries, no queue-transport prefix leakage, least-privilege Tenant DB roles and proper cleanup.

### 13.2 Minimum critical scenarios

| Area | Required evidence |
| --- | --- |
| **ACID and Outbox** | Business transaction/outbox atomicity; rollback emits no committed event |
| **Relay crash windows** | Redis down; stale claim; crash before and after publish; safe re-publication |
| **Duplicate Consumers** | Concurrent same Event ID; atomic effect/proof; independent consumers unaffected |
| **Out-of-order** | Old projection cannot overwrite newer state; late immutable money/stock event still handled |
| **Retry/timeouts** | Bounded attempts; safe `retry_after`; no repeated irreversible effects |
| **Authorization** | Revoked user's pending export denied; valid system-owned committed follow-up evaluated correctly |
| **Isolation** | Tenant A/B/Central jobs and file scope never cross |
| **Operational Recovery** | Scanner failure detected, aged backlog visible, privileged recovery auditable |
| **Capacity** | Normal/burst/bulk/multi-Tenant/failure/Blue-Green measured without starvation |

Cross-Tenant data exposure, serious authorization bypass, duplicate critical money/stock effects, false business success and critical lost event intent are merge/release blockers as applicable.

### 13.3 Progressive quality gates

Relevant PR tests and contract checks are risk-proportionate; concurrency, fault injection and multi-Tenant integration target affected sensitive changes. Wider E2E, security, recovery and load tests belong at suitable integration/pre-Pilot gates, not every single PR.

Existing **BA-30 G1–G10** still govern live Pilot activation; QJ-02 maps to Tenant safety, QJ-04/05 to consistency, scheduled deployment to release operations, and QJ-07 to capacity/NFRs. **Integration, Capacity and Pilot Gates remain Pending Validation. BA-16 remains deferred and G3 blocked for Live Pilot**, without selecting or implementing its deferred solution here. Documentation/development may continue independently.

## 14. MVP Boundaries, Deferred Settings & Open Items

### 14.1 Included in the approved lightweight baseline

Laravel Scheduler + Redis queue driver + Horizon, conditional Stancl bootstrapping, trusted minimal references and cleanup, PostgreSQL local Outbox with bounded scanner/lease/recovery, job-specific retries, consumer/business idempotency, selective ordering, scoped operational monitoring and evidence-based capacity tuning.

### 14.2 Intentionally not selected

Kafka, RabbitMQ, distributed transaction/Saga engine, universal exactly-once delivery, global FIFO/event sequencer, Redis-only financial/stock consistency, per-Merchant Redis/workers/continuous relays, custom job execution framework, global blind retry, enterprise monitoring from day one, custom comprehensive Recovery UI or numeric capacity claims without tests.

### 14.3 Implementation specifics pending

| Topic | Later evidence or owner |
| --- | --- |
| Worker counts, queue/supervisor allocation | Horizon runtime/config and capacity measurement |
| Job timeout, attempts, `retry_after`, jitter/backoff | Per-Feature integration/fault tests |
| Outbox columns/indexes, processed-event uniqueness | Data/Feature specs and PostgreSQL migrations |
| Claim ownership, lease TTL, scan interval and batch size | Relay implementation and recovery/load tests |
| Exact event envelope, schema versioning and contracts | Integration Events/Feature specs |
| Email provider delivery semantics | Notifications Feature/provider verification |
| Monitoring thresholds, retention, per-Tenant diagnostics | Operations/security validation |
| Tenant Fairness, Redis/PostgreSQL budgets, VPS sizing | Measured workload and BA-29 capacity report |
| Laravel 13/Stancl/Horizon compatibility | Mandatory Foundation integration tests |
| CI/Blue-Green/worker drain and rollback | Release engineering and BA-30 gates |
| Tenant cache prefixes, invalidation, TTL | Separate Caching & Redis Architecture phase |

Do not introduce unsupported new workflows to resolve a pending implementation setting. Do not pretend package/API compatibility or numeric scale is proven by this document.

## 15. Final Approval & Implementation Boundary

**APPROVED: QJ-08 (A) — Lightweight Queues, Jobs & Event Processing Standards Baseline, including QJ-01 through QJ-07.**

The binding direction is: **shared Laravel Redis/Horizon with logical work classes; trusted, fail-closed Central/Tenant job execution; scheduled bounded per-database Transactional Outbox relay; at-least-once publishing and consumer-level idempotency; selective per-aggregate ordering; classified bounded retry and reconciliation; native monitoring with authorized recovery; and measured resource budgets with phased scaling**.

**Not claimed:** installed Laravel/Stancl/Horizon integration, implemented migrations/Jobs, live queue uptime, numeric SLO, capacity proof, passing security/performance tests, or permission for live merchant activation.

This document authorizes **no application-code, dependency, environment, deployment or other file changes**. Implementation remains subject to approved Feature specifications, acceptance tests, repository workflow and independent release sign-off.
