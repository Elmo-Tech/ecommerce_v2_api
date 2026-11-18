# E-commerce SaaS V2 — Caching & Redis Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/caching-redis-architecture.md`  
**Version:** 1.0 — Approved Lightweight Caching & Redis Architecture Baseline  
**Prepared:** 2026-11-18  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — CR-08 (A)**  
**Decision Coverage:** **CR-01 through CR-08**  
**Implementation Status:** **Not implemented, installed, tested, validated or deployed**

> **Reading rule:** This document approves an MVP-first architectural baseline, not live Redis configuration, code, verified package compatibility, observed performance, Session-driver selection or permission to launch merchants. Exact settings, integration evidence and BA-30 release gates remain pending.

---

## 1. Document Overview, Authority & Scope

### 1.1 Purpose

Define a small, safe and measurable caching strategy for the Laravel 13 API-first Modular Monolith, Next.js merchant/storefront consumers, one Central PostgreSQL database, independent PostgreSQL databases per Merchant (Tenant), and shared Redis/Horizon workloads.

Caching exists to accelerate suitable repeated **reads**; it does not replace PostgreSQL as the authority for orders, inventory, price eligibility, collections, refunds, entitlements, security, event durability or business idempotency.

### 1.2 Governing architecture

This standard is subordinate to, and must remain consistent with:

- [Backend Architecture](./backend-architecture.md) — BA-02/03 tenancy, BA-08 transaction correctness, BA-09 Redis/Horizon, BA-10 caching/rate limits, BA-24 observability, BA-29 measured scaling, BA-30 release gates.
- [Tenancy Architecture](./tenancy-architecture.md) — trusted tenant resolution, Stancl integration, environment/Tenant cache isolation, worker cleanup and no queue-prefix leakage.
- [Identity & Access Architecture](./identity-access-architecture.md) — Central workforce identity, independent Tenant customer identity, session isolation, current membership/role/Location/entitlement checks and revocation.
- [Transaction & Consistency Architecture](./transaction-consistency-architecture.md) — PostgreSQL authority, protected business transactions, PostgreSQL Outbox and duplicate-safe downstream processing.
- [Queues, Jobs & Event Processing Standards](./queue-jobs-event-processing-standards.md) — shared Redis/Horizon transport, trusted Central/Tenant job context, post-commit Outbox and operational recovery.
- [Data Architecture](./data-architecture.md), [Module Architecture](./module-architecture.md) and [API Architecture](./api-architecture.md) — data ownership, public module contracts, scoped reads and Sanctum/session expectations.

Approved business rules and Feature Specs remain authoritative. A cache entry, configured TTL or UI hint cannot change an approved commercial or security rule.

### 1.3 In scope and boundaries

In scope: ownership and eligibility of cached read models, safe Central/Tenant namespaces, context lifetime, targeted invalidation, freshness, selective stampede prevention, security-sensitive caching restrictions, shared Redis workload separation, failure behavior, metrics and validation.

Redis-backed **queues, sessions and rate limiting** are addressed only where they share infrastructure and affect cache or security boundaries. Queue durability belongs to QJ/TC; authentication mechanics belong to IA/API. The Redis-versus-database Session driver is **not selected here**.

**Architectural approval is distinct from implementation, runtime validation, capacity proof and release approval.**

---

## 2. Approved Decisions — CR-01 to CR-08

| ID | Approved option | Binding direction |
| --- | --- | --- |
| **CR-01** | **A — PostgreSQL Authoritative + Selective Module-Owned Read Cache** | Cache eligible read projections only; revalidate price, availability, authorization and any critical effects against authoritative data. No cache-controlled orders, money, stock or business idempotency. |
| **CR-02** | **A — Stancl-Supported Tenant Cache Isolation + Explicit Scopes** | Environment/Central/Tenant/Module separation; add actor, Location, locale, visibility or view-variant scope only when relevant. Trusted Tenant Context first; no global flush, tenant leakage or queue-transport prefixing. |
| **CR-03** | **A — Targeted Post-Commit Invalidation + Bounded TTL** | Owning module invalidates affected entries after successful commit. Recognize crash and stale-refill races; use selective durable Outbox integration only for justified projection-delivery needs. |
| **CR-04** | **A — Authoritative Auth/Authorization + Selective Security Metadata Cache** | Sanctum host-scoped sessions; trusted current membership, Tenant lifecycle/entitlement, local permissions, Location and resource checks; immediate revocation for new access. No stale cached positive grants. |
| **CR-05** | **A — Feature-Specific TTL + Selective Stampede Protection** | Laravel-native cache-aside; bounded freshness, selective short-lived cache locks for measured hot keys, controlled PostgreSQL fallback and fail-closed sensitive routes. Jitter/SWR only if justified. |
| **CR-06** | **A — Shared Redis + Protected Logical Workload Connections** | Separate Cache, queue/Horizon, optional Session and rate-limit connection/store policies; avoid queue tenant-prefix leakage. Start with `noeviction` when critical operational keys share a process, understanding OOM write failures; split processes by workload if tests show unsafe contention. |
| **CR-07** | **A — Selective Performance Measurement + Risk-Based Tests** | Compare cache on/off, measure latency, PostgreSQL impact, Redis memory and rejected writes, tenant isolation, security, races, outages, sessions, Horizon and Blue-Green. No enterprise monitoring platform. |
| **CR-08** | **A — Lightweight Architecture Baseline** | Approve CR-01…CR-07 and this 15-section documentation plan, with all runtime settings, integration evidence and release gates still pending. |

All eight decisions are **approved at architecture level only**.

---

## 3. PostgreSQL Source of Truth & Cache Eligibility

### 3.1 Authoritative boundaries

- Central PostgreSQL owns Tenant Directory/status, platform workforce identity and membership, SaaS subscriptions/entitlements and Central audit via their owning modules.
- Each Tenant PostgreSQL database owns local roles, Location scopes, storefront customers, catalog, inventory, orders, collections, returns/refunds and tenant-domain evidence.
- Protected writes, final prices/discount eligibility, availability/reservations, once-only dispatch/collection/refund, order confirmation, business idempotency and historical financial/stock records are **never decided exclusively from Redis**.
- Checkout/order confirmation re-evaluates relevant authoritative price, eligibility, permission, Location and complete-order availability within the appropriate protected workflow. Cached displayed stock may be stale; it is not a reservation guarantee.
- Authorization for new sensitive actions must come from trustworthy current state, not a cached permission grant that might survive a revocation.

### 3.2 Eligible read optimization

Potential candidates **only where measured or justified by workload**:

- Public Tenant Catalog categories, product detail/listing and safe public storefront presentation/settings.
- Module-owned read models/projections for bounded-staleness dashboards or reporting.
- Non-authoritative UI hints and stable Permission Registry definitions, with appropriate change propagation.

**Not blanket cache candidates:** confidential customer datasets, private documents, final authoritative money/stock balances, effective access decisions, business idempotency records or facts with a required immediate freshness guarantee.

### 3.3 No obligation to cache

A straightforward indexed PostgreSQL query may be sufficient. A Feature may select **no Redis Cache** where the added invalidation, privacy and operational costs exceed observed benefit.

---

## 4. Module Ownership & Cached Read Models

### 4.1 One owner

The module that owns a business fact controls its writes and decides whether a read model may be cached, which inputs affect the result, its freshness contract, appropriate scope and invalidation triggers. Other modules use approved Public Contracts, not internal cache keys as inter-module APIs.

Examples: Catalog owns product/category projections; Pricing owns the authoritative pricing contract; Inventory owns availability/reservations; Orders owns order state; Reporting owns its derived summaries; Identity & Access owns current authorization rules; Platform/Entitlements owns SaaS feature eligibility.

### 4.2 Cache result discipline

Cache only a **read representation** that has been selected from the correct owning DB and filtered by relevant visibility/ownership/Location constraints. A cache hit does not itself prove the requester is authorized. If a response varies by actor, Location, locale, channel, currency, visibility, filters or schema version, its safe keying/authorization contract must account for those variations.

Avoid serializing secrets, credentials, raw sessions, excessive personal data, live DB handles or unrestricted models into cache values.

### 4.3 No general cache framework

Use Laravel-native Cache APIs and supported Stancl/Laravel isolation mechanisms. Do not introduce a generic custom cache registry, cross-module invalidation engine or proprietary read-model framework for the first Pilot.

---

## 5. Central/Tenant Cache Isolation & Key Scoping

### 5.1 Security invariants

1. Authenticate/identify and resolve the **trusted** Central or Tenant context before cache access.
2. Validate Tenant availability, membership, entitlement and required access independently of the cache key.
3. Segregate environments; keep Central and Tenant cache namespaces distinct and a Tenant's keys inaccessible from another Tenant's context.
4. Scope additional actor, Location, locale, storefront channel, visibility, filter, currency and version inputs **only where they alter values or authorization**.
5. Reject unknown, mismatched, unavailable or unauthorized Tenant contexts; never silently fall back to Central, a default Tenant or a previously processed Tenant.
6. A client-supplied Tenant ID or arbitrary hostname is a selector at most, never authorization evidence.

### 5.2 Illustrative logical conventions

These are examples, **not** a frozen raw Redis key format or mandate to layer duplicate prefixes atop Stancl:

- `app:prod:cache:central:platform:settings:v1`
- `app:prod:cache:tenant:T001:catalog:product:100:ar`
- `app:prod:cache:tenant:T001:catalog:list:location:5:channel:web:ar:variant:V`
- `app:prod:cache:tenant:T001:dashboard:actor:U20:summary:v1`

Two different Tenant DBs can legitimately have the same numeric product/customer IDs; the ID alone must never identify a cross-Tenant cached record. Scope names and dimensions must remain reasonable in key length/cardinality.

The concrete Laravel Store Prefix, Stancl tags and physical Redis key composition must be tested before configuration is finalized; avoid prefix duplication and unintended shared/global operations.

### 5.3 Private and public reads

A public storefront view can share a Tenant-scoped entry **only** when genuinely public to that audience. A dashboard/customer/private-file/export read must enforce actor and resource scope first, and may require actor/Location-specific cache variants or no cache.

### 5.4 Invalidation scope

No global `Cache::flush()` to clear one Tenant's data. Invalidate exact affected entries or carefully verified scoped tags. Laravel Cache flush behavior may ignore the configured prefix; verify Store/Stancl semantics before relying on bulk operations.

---

## 6. Stancl Cache Integration & Context Cleanup

### 6.1 Conditional package integration

Use Stancl's supported `CacheTenancyBootstrapper` or equivalent verified Laravel 13-compatible integration for Tenant cache isolation, rather than reinventing a tenant cache runtime.

This architecture **does not claim** the current Laravel 13 + selected Stancl version has been installed or tested. Verify cache-store tag support, prefix/tag behavior, Central execution, tenant initialization, tenancy ending and exception paths.

### 6.2 Worker and request lifecycle

For each HTTP request and Horizon job: derive/revalidate the intended trusted context, initialize only the correct scope, perform approved operations, then clear tenant-scoped mutable cache authorization state and DB connection/context. Test sequential **Tenant A → Tenant B → Central** operations in reused workers, including failure and retry.

A worker that is terminated unexpectedly does not run its own cleanup; the next worker/attempt must start from a clean trusted context. Persistent runtime support such as future Octane requires equally strong context-isolation testing.

### 6.3 Queue transport exception

The Redis **Queue/Horizon connection** is environment-scoped and stable; it must not inherit the selected Tenant cache prefix/tag behavior. Distinguish native/cache-bound Redis use from direct Redis access; do not globally activate a Redis tenancy bootstrapper in a way that mutates shared queue/session operational transport.

---

## 7. Post-Commit Invalidation & Consistency

### 7.1 Owning-module post-commit policy

After a successful **owning PostgreSQL transaction**, invalidate only cached read variants made obsolete by that write. A rollback must not be represented as a committed business change. Invalidation outside the transaction is deliberately **not atomic** with Redis.

The transaction and required Outbox intent, where applicable, commit together in the owning database; direct after-commit cache work is an optimization, not a durable cross-system guarantee.

### 7.2 Failure and concurrency windows

- **DB committed, Redis unavailable or process crashed:** retain the committed PostgreSQL fact. Invalidation may be missed; bounded TTL limits how long an ordinary display projection can remain stale. Sensitive operations still use authoritative checks.
- **Concurrent stale refill:** a read begun before a newer commit may refill a cache entry after its invalidation. Bounded TTL is not Strong Consistency; use version-aware reads/keys or bypass cache for Features requiring tighter guarantees.
- **Duplicate/delayed event:** asynchronous projection consumers must not let older events overwrite newer business state. Apply QJ/TC ordering and idempotency rules where relevant.
- **Cross-module read model:** use an approved module contract/event rather than directly writing another module's internal cache data.

### 7.3 Selective durable invalidation

Reuse the already approved PostgreSQL Transactional Outbox **only when a Feature truly requires durable notification/rebuilding of a derived projection**. Do not introduce an Outbox record/queue job for every ordinary cache key. Async invalidation is never the enforcement mechanism for immediate membership revocation or eligibility.

### 7.4 Accuracy contract

A cache-aside entry is a bounded-staleness convenience. Do not claim Redis/PostgreSQL Strong Consistency, globally atomic invalidation or exactly-once asynchronous cache updates.

---

## 8. Authentication, Authorization & Entitlement Safety

### 8.1 Identity and sessions

Use the approved Laravel Sanctum first-party session cookie + CSRF scheme and explicit Central-workforce versus Tenant-customer guards. Authentication Session Store selection (Redis or PostgreSQL-backed database) is **deferred**. If Redis sessions are selected, configure logical workload separation, timeouts and recovery; verify host-only cookie behavior, Tenant customer `ScopeSessions`, expiry, logout, password-change invalidation and replay isolation with actual integration tests.

### 8.2 Current authorization gates

For relevant Merchant Dashboard requests: authenticate Central workforce identity; resolve validated Tenant; check **current Central membership** and Tenant lifecycle/entitlement; initialize Tenant DB; check tenant-local roles/permissions, assigned Location, resource ownership and feature-specific financial/approval constraints.

Authorization/entitlement changes or Central security disablement must deny **new** unauthorized requests immediately through a reliable authoritative check. A stale Redis grant, remembered active-Tenant UI preference, still-valid browser session or async invalidation cannot override revocation. If authoritative validation cannot be performed, **fail closed**.

Tenant membership revocation is scoped to that merchant and must not silently remove independent valid access to another merchant.

### 8.3 What can be cached

Potentially cache stable Permission Registry definitions and UI/menu display hints, appropriately scoped and invalidated. These hints are **not** API authorization. Avoid broad effective-permission caching or versioned revocation-barrier infrastructure in the first Pilot without a measured need and separately validated safe design.

### 8.4 Background work

User-initiated deferred sensitive work (for example private exports) rechecks the initiating actor's current relevant permissions and scope before execution. A valid **system-owned obligation from an already committed business event** is not cancelled solely because the original actor later lost permission; apply QJ's scoped system authority and current Tenant/business/privacy rules instead.

---

## 9. TTL, Freshness & Cache Stampede Prevention

### 9.1 Feature-specific bounded TTL

Each eligible Feature specifies a maximum tolerated display staleness, read/rebuild cost, change rate and targeted invalidation policy. Categories, public product details/settings, catalog listings and derived reports may legitimately have different TTLs; there is no global default duration approved here.

No unbounded lifetime by default for mutable cached data. TTL is **not** proof of freshness, immediate revocation or correctness of a dependent business write.

### 9.2 Cache-aside and hot-key protection

Start with Laravel-native **cache-aside**. For a measured high-traffic/expensive key, selectively use a short, bounded, appropriately scoped native cache lock to reduce redundant rebuilding. Other requests must have a bounded wait or safe alternative; no indefinite block.

A cache rebuild lock can expire or a process can crash: duplicate rebuilds remain possible, and business correctness must not rely on the lock. Key/lock identity includes the verified Tenant and any other outcome-affecting dimensions.

### 9.3 Optional optimizations

TTL jitter can spread expirations when demonstrated useful, within Feature-approved freshness limits. Stale-while-revalidate is **not global default** and may be used only for explicitly suitable noncritical public/reporting views with bounded allowed staleness. Neither is authorized for granting access, confirming checkout, reserving stock or financial effects.

Exact TTLs, lock leases, retry/wait bounds, jitter ranges and refresh strategies remain pending Feature and performance validation.

---

## 10. Cache Failure, Controlled Fallback & Degradation

### 10.1 Failures by workload

| Workload | Failure behavior |
| --- | --- |
| Public/noncritical cache read | Permit **bounded**, authorized PostgreSQL fallback only when the query cost and load permit it. |
| Expensive report/projection cache | Cap work or return a clear temporary error rather than unleash unbounded recalculation. |
| Price/stock display | A stale/missing display cache never relaxes authoritative checkout/availability decisions. |
| Authentication session store | Do not invent a session, bypass login or silently accept unauthorized access when the store is unavailable. |
| Membership/permission/entitlement check | Use trusted current source; if verification fails, **fail closed**. |
| Security-sensitive rate limiter | Never silently fail open; exact endpoint-specific behavior belongs to Security Standards. |
| Horizon transport | Follow QJ: PostgreSQL Outbox retains durable work intent; queue recovery and idempotent consumers handle later progress. |

### 10.2 Prevent a fallback storm

A Redis outage may turn many cache hits into simultaneous PostgreSQL reads. Budget expensive queries, execution time, connections and per-Tenant workload; control concurrency and use existing API/security limits and safe error responses. Do not assume all cache misses can be forwarded to the database at unlimited volume.

No custom circuit-breaker or cache failure-orchestration framework is selected for the MVP.

### 10.3 Redis return to service

Recovery may warm normal entries gradually. No stale Redis value can establish a successful collection/refund, order confirmation, entitlement or security permission. Preserve PostgreSQL business facts and follow the already approved Outbox/Horizon recovery process.

---

## 11. Redis Connections, Eviction & Resource Separation

### 11.1 Explicit logical workspaces

One shared Redis **service/infrastructure** may initially support explicitly configured Laravel Cache Store/connection, Queue/Horizon transport, optional Redis Session driver, rate-limit state and Horizon internal state. Define clear environment names and distinct namespaces/stores. Do not reuse Horizon's reserved internal Redis connection name `horizon` for unrelated application purposes.

**Important:** different Redis logical databases, connections or prefixes on the **same process do not isolate memory, eviction policy, latency or process failure**.

The queue/Horizon connection is stable and must never acquire a Tenant-dependent cache prefix. Deployment changes to prefix/connection configuration must account for already queued jobs and in-flight/old sessions during Blue-Green switching.

### 11.2 Memory and eviction safety

When durable operational keys (queues/sessions/rate-limit state as applicable) share a process with optional cache values, `noeviction` is the **initial non-evicting direction subject to verification**, not a complete safety guarantee:

- Reaching `maxmemory` may reject writes, including session or queue operations; `noeviction` does **not** mean uninterrupted service.
- An evicting policy such as `allkeys-lru` can remove operational keys; policies scoped to TTL-bearing keys can still affect expiring session/limiter keys.
- Cap optional cache growth, preserve measured memory headroom and monitor failures before allowing a shared configuration into a live Pilot.

If representative memory/failure tests show that cache pressure threatens session availability, Horizon progress or rate-limit security, **separate Redis processes by workload before launch** (potentially initially on the same appropriately budgeted host), with independent resource/eviction policies. Do not use one Redis process per Merchant.

### 11.3 Production, development and scaling

Production and Development require data isolation **and** safe resource isolation; prefixes alone do not protect production capacity from development load on a common process.

Follow BA-29: measure, optimize keys/queries/concurrency, vertically scale where justified, and separate Redis/workers/DB/API where actual limits or isolation demand. Do not presume Redis Cluster, per-Tenant Redis, multiple hosts or a fixed VPS size for the first Pilot.

---

## 12. Performance Monitoring & Capacity Validation

### 12.1 Measure actual cache benefit

For chosen Features compare **without cache** and **with cache** under representative, comparable workloads:

- Hit/miss rate and rebuild duration; read-model freshness/invalidation failures.
- API P50/P95/P99 where useful, error rates and throughput.
- PostgreSQL query count/duration, CPU/IO, connections, slow queries and fallback load.
- Redis memory, `maxmemory` headroom, eviction statistics, rejected/failed writes, latency, connection count and operational errors.
- Horizon wait/lag/failed jobs, queue transport health, Outbox backlog/age and session/auth errors.

Cache hit rate alone is **not a success criterion**. If a cache does not improve meaningful performance or costs too much operationally, remove or simplify it.

### 12.2 Lightweight observability

Use existing Laravel instrumentation, structured safe logs, PostgreSQL/Redis host metrics and Horizon. Aggregate by environment and Feature/cache class. Tenant diagnostics may use bounded, privacy-protecting references where needed; avoid high-cardinality labels for every actor/key and never log cache values containing private records, secrets, session IDs or financial evidence.

No bespoke tenant-by-tenant cache dashboard or enterprise tracing platform is required.

### 12.3 Capacity scenarios

Measure normal store traffic, catalog hot spots, many active Tenants, high-churn invalidations, reporting load, Redis interruption, fallback bursts, recovered workloads and Blue-Green overlap. A planning target of hundreds of Merchants is **not capacity evidence**. Numeric SLOs, hit-rate targets, limits and alert thresholds are **pending tests**.

---

## 13. Multi-Tenant Integration Tests & Release Gates

### 13.1 Mandatory environment

Use real Laravel 13, PostgreSQL (one Central DB plus **at least two independent Tenant DBs**), Redis, the selected Stancl integration, appropriate Sanctum/session behavior and Horizon where the boundary requires it. Mock-only or sequential-only coverage cannot prove concurrency or context safety.

### 13.2 Minimum targeted test matrix

| Area | Required scenario / expected evidence |
| --- | --- |
| Tenant cache identity | Same Product/Customer numeric ID in Tenant A and B produces independent correct authorized results. |
| Central/Tenant context | Central entries do not collide with Tenant entries; untrusted/mismatched Tenant access fails closed. |
| Worker lifetime | Tenant A → Tenant B → Central reused worker and exception/retry cleanup; queue transport remains unprefixed. |
| Visibility and Location | Actor/Location/locale/filter variants cannot disclose another scope's private values. |
| Post-commit | Commit triggers proper targeted invalidation; rollback does not claim a new authoritative state. |
| Crash/invalidation miss | PostgreSQL remains authoritative, acceptable public staleness is bounded; sensitive flows remain correct. |
| Concurrent cache refill | Older read cannot corrupt protected commercial/security effects; Feature-specific freshness addressed. |
| Checkout and stock | Stale displayed price/availability cannot confirm an invalid price or oversell. |
| Security revocation | Membership, role, Location and entitlement removal blocks **new** unauthorized work despite stale hints or a valid session. |
| Session and guards | Staff/customer guard separation, tenant session replay/ScopeSessions, logout/expiry/password invalidation. |
| Cache stampede | Measured simultaneous cold misses; bounded lock waiting/fallback; no unbounded PostgreSQL fan-out. |
| Shared Redis pressure | `noeviction`/OOM rejected writes understood; unacceptable queue/session interference forces workload separation. |
| Failure/recovery | Redis unavailable/recovered: controlled read fallback, no fail-open auth/rate limit, Outbox/Horizon recovers safely. |
| Blue-Green | Prefix/queue/session compatibility and memory/connection headroom across overlap/restarts. |
| Performance ROI | Before/after cache metrics on chosen Features; sustained benefit without security regressions. |

### 13.3 Proportionate gates and blockers

- **Relevant Feature PR:** focused key/scoping/invalidation/authorization tests.
- **Sensitive integration change:** real Central+Tenant A/B, Redis/Stancl/Session/Horizon integration and targeted concurrency/failure cases.
- **Pre-Pilot:** representative load and recovery, safety, memory/queue/session protection, environment separation and Blue-Green behavior.

Cross-Tenant data leakage, authorization bypass/failed required revocation, invalid stock/money/order effects, loss of required durable intent or unsafe Redis configuration that cannot keep critical workloads reliable are **release blockers as applicable**.

These tests contribute evidence to existing **BA-30 G1–G10**, especially Tenant safety, commerce integrity, capacity, security/privacy, environment separation and release readiness. This document does not mark any gate passed or override separate outstanding Pilot prerequisites.

---

## 14. MVP Exclusions, Pending Settings & Open Items

### 14.1 Intentionally excluded

- Redis/server/worker per Merchant.
- Redis as final authority for money, stock, permissions, orders, entitlements or idempotency.
- A custom cache engine, dynamic authorization-cache engine, universal invalidation coordinator or generic distributed business locks.
- Global Cache Flush for scoped invalidation.
- Global stale-while-revalidate, cache prewarming, universal stampede lock or a forced cache for each read.
- Kafka or other streaming introduced solely for invalidation.
- Custom cache observability dashboard, mandatory full load suite on every PR or fixed unmeasured scale/SLO claims.

### 14.2 Implementation decisions pending

| Topic | Owner / required evidence |
| --- | --- |
| Laravel 13 + Stancl `CacheTenancyBootstrapper` | Actual compatibility and Central/Tenant isolation integration tests |
| Stancl `ScopeSessions` and Sanctum session isolation | Real session/guard/cookie replay and lifecycle tests |
| Redis vs database session driver | Implementation/Security decision with outage and logout behavior |
| Connection names, Laravel stores, key prefixes, tags | Configuration and queue transport isolation tests |
| Cache eligibility, TTL, freshness, invalidation triggers | Feature Specs and measured read/write workloads |
| Cache lock waits/lease, jitter, optional SWR | Targeted concurrency and performance tests |
| Redis `maxmemory`, `noeviction`, headroom and alert budgets | Shared workload memory/Failure and capacity tests |
| Need for workload-separated Redis processes | Mandatory safety decision if shared process tests fail |
| Fallback rate/connection budgets and sensitive limiter response | Security/Operations/Performance testing |
| P50/P95/P99, hit-rate targets, alert thresholds | Measured pilot-like workload evidence |
| Horizon/Blue-Green/Outbox and runtime cleanup | QJ/Release engineering integration tests |
| BA-30 Pilot Gates G1–G10 | Independent documented evidence and explicit release sign-off |

### 14.3 Explicit status

No package was installed or verified, no Redis or Laravel runtime setting selected or applied, no source code changed, no performance benchmark executed and no capacity/launch claim made by approving this architecture.

---

## 15. Final Approval & Implementation Boundary

**APPROVED: CR-08 (A) — Lightweight Caching & Redis Architecture Baseline, including CR-01 through CR-07.**

The chosen MVP direction is **PostgreSQL-authoritative, selective module-owned tenant-safe read caching, feature-scoped post-commit invalidation and bounded TTL, authoritative security checks, lightweight selective stampede protection, shared Redis with operational workload safeguards and risk-based measured validation**.

Implement only through later Feature Specs and controlled changes. Before a real Pilot, prove Laravel 13/Stancl/Session compatibility, Central+Tenant isolation, protected Redis workload capacity/failure behavior, fallback safety, Horizon and Blue-Green compatibility, and all applicable BA-30 gates. This document alone does not authorize implementation, deployment or live-merchant activation.
