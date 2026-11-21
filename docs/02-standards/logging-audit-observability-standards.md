# E-commerce SaaS V2 — Logging, Audit & Observability Standards

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target path:** `docs/02-standards/logging-audit-observability-standards.md`  
**Branch:** `development`  
**Version:** 1.0 — Approved Logging, Audit & Observability Engineering Standards  
**Prepared:** 2026-11-21  
**Approval:** **LOG-01 through LOG-08 — Option A APPROVED**, individually for LOG-01…LOG-07 and with explicit owner approval to complete LOG-08 and publish this document to `development`.  
**Implementation status:** **DOCUMENTATION ONLY — NO APPLICATION CODE, MONITORING COLLECTOR, AUDIT SCHEMA, ALERT CHANNEL, CI, CONFIGURATION, LIVE METRIC, BACKUP OR DEPLOYMENT IS IMPLEMENTED OR VERIFIED BY THIS DOCUMENT.**

> **Mandatory reading rule:** Approval defines future engineering requirements, not evidence of runtime behavior. **BA-16 Backup & Restore remains DEFERRED; BA-30 G3 remains BLOCKED for live merchant activation; D-201 remains OPEN.** Actual numeric SLOs, thresholds, log retention periods, provider choices and incident response coverage are not yet proven or selected. No Production promotion or permission to operate on real merchant data is granted.

---

## 1. Purpose, Scope, Authority and Non-Goals

### 1.1 Why this standard exists

E-commerce SaaS V2 is an Egypt-first closed Pilot of independent retail merchants built around a planned **Laravel 13 API-first Modular Monolith**, separately deployed **Next.js** surfaces, **one Central PostgreSQL database plus one physically separate PostgreSQL database per Tenant**, Redis/Horizon, transactional outbox, and containerized workloads on an initial VPS. The service must be diagnosable without sacrificing Tenant isolation, personal-data privacy, authoritative financial/stock evidence or runtime resources.

This standard establishes reproducible expectations for:

1. **LOG-01:** structured, context-aware technical logging.
2. **LOG-02:** trusted request/correlation/operation identifiers across HTTP, database-owned business work, outbox, relay, jobs and integrations.
3. **LOG-03:** domain-owned security/business audit and authoritative history.
4. **LOG-04:** consistent event severity, exception classification, API/worker error reporting and controlled log volume.
5. **LOG-05:** lightweight metrics, readiness/business progress, alerting, Multi-Tenant diagnosis and measured thresholds.
6. **LOG-06:** technical log storage/rotation/retention, privileged audit access, privacy and incident evidence preservation.
7. **LOG-07:** incident ownership, safe triage, evidence, recovery, business-state validation and review.
8. **LOG-08:** cross-document reconciliation, tests, checklists, open decisions and approved documentation-only publication.

Every relevant Spec Kit Feature must translate applicable obligations into `spec.md`, `plan.md`, `tasks.md`, executable tests, reviewed changes and actual operational evidence. No automatic audit of every model/UI interaction or blanket observability package is implied.

### 1.2 Sources of authority

| Existing source | Binding architecture / engineering baseline |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | Egypt-first closed Pilot, merchant journeys and deliberate MVP exclusions |
| [Backend Architecture](../01-architecture/backend-architecture.md) | **BA-14** Central/Tenant Audit ownership; **BA-24** structured observability; **BA-29** measured scaling; **BA-30** mandatory G1–G10 gates |
| [Module Architecture](../01-architecture/module-architecture.md) | Owning Modules, One Writer, Public Contracts, Feature-level specs and orchestration |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Trusted scope and DB-role isolation; worker cleanup; no fallback to another Tenant |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | Central workforce and Tenant-local customer identity; current permissions and membership |
| [Data Architecture](../01-architecture/data-architecture.md) | Separate PostgreSQL databases, money/history, feature-owned data lifecycle |
| [Transactions & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | **TC-01…TC-08**, atomic local writes, idempotency, outbox, retry classification and reconciliation |
| [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | Horizon, relay, scheduler progress, worker retry semantics and Business vs Technical outcome |
| [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) | Redis diagnostics, outages, authorization-safe caches and bounded metric cardinality |
| [Infrastructure Architecture](../01-architecture/infrastructure-architecture.md) | **INF-09** readiness/release verification, **INF-11** lightweight observability, shared VPS constraints |
| [File Storage Architecture](../01-architecture/file-storage-media-uploads-architecture.md) | Private files, mounted persistence, lifecycle and unauthorized-export prevention |
| [Code Standards](./code-standards.md) | **CS-10** structured safe logging, identifier separation and business audit; exception/PR discipline |
| [API Standards](./api-standards.md) | **AS-02/03/07**, server `X-Request-ID`, error mapping, HTTP status and browser boundary |
| [Database Standards](./database-standards.md) | **DB-10**, PostgreSQL statistics, query/lock observability and tested constraints |
| [Authentication Standards](./authentication-standards.md) | **AUTH-09**, identity audit, session privacy and verified staff/customer scope |
| [Authorization Standards](./authorization-standards.md) | **AUTHZ-08/09**, privileged access to Audit, permissions/Location changes, revocation |
| [Security Standards](./security-standards.md) | **SEC-07** security events/incident response, **SEC-09** privacy/retention and controls |
| [Testing Standards](./testing-standards.md) | **TEST-03/04/05/06/08/09**, realistic Multi-Tenant fixtures, fault injection, capacity and release evidence |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | **D-201 OPEN**, **BA-16 DEFERRED**, Pilot blockers |
| [Repository Workflow](../WORKFLOW.md) | Documentation on `development`, reviewed Production promotion via `main` |

**Precedence:** accepted business D-series decisions govern business meaning; OV/MVP agreements govern scope; architecture governs ownership, tenancy and transaction boundaries; these standards refine logging/observability discipline; reviewed Feature Specs, OpenAPI, real infrastructure and measured load define exact implementation settings. If a conflict is discovered, explicitly surface it instead of silently replacing the previous approval.

### 1.3 Status vocabulary and honest evidence

| Status | Meaning |
| --- | --- |
| **APPROVED** | Engineering direction accepted; not proof of a live system |
| **TO SPECIFY** | Configuration, concrete field, UI, retention or ownership details are not yet selected |
| **PENDING VALIDATION** | Package compatibility, actual runtime behavior, metrics, thresholds or law require evidence |
| **BLOCKED** | A required Pilot gate cannot proceed without an explicit decision and proof |
| **IMPLEMENTED / VERIFIED** | Only after actual configuration/tests/operational artifact and reviewer evidence |

### 1.4 Explicit non-goals

- No mandatory ELK/OpenSearch, Datadog, Sentry, OpenTelemetry collector, Jaeger, Prometheus/Grafana, paid SIEM or custom Monitoring Platform from day one.
- No blanket audit on every row update, read, UI click, successful HTTP request or cache access.
- No global Central database of all Tenant business/audit records; no cross-Tenant unbounded diagnostic queries.
- No invented SLO, P95 budget, availability percentage, disk/IOPS budget, logging sampling rate, specific alert timing, incident SLA, on-call roster, retention days or backup RPO/RTO.
- No claim ordinary PostgreSQL audit rows are cryptographically immutable to privileged DBA access.
- No logging of raw browser Sessions, Tokens, passwords, payment/card details, full private documents, unbounded customer data, entire HTTP request bodies or raw provider responses.
- No new payment gateway, SMS/WhatsApp, shipping service, SaaS compliance certificate, Backup/DR provider or automatic refund process.
- No changes to PHP-FPM vs Octane (initial architecture is **PHP-FPM**), Tenant DB topology, code, GitHub Actions, branch protection, infrastructure or Production in this publication.

---

## 2. Approved Decision Register — LOG-01…LOG-08

| ID | Approved option | Engineering result / acceptance target |
| --- | --- | --- |
| **LOG-01** | **A — Structured & Context-Aware Logging** | Consistent searchable JSON, trusted scope, safe identifiers; Laravel-native and light operational collectors |
| **LOG-02** | **A — End-to-End Context Propagation** | Per-attempt `X-Request-ID` separated from correlation, business operation, idempotency, event and job identifiers |
| **LOG-03** | **A — Domain-Owned Audit + Central/Tenant Isolation + Transactional Integrity** | Business-owning audit and preserved history; right DB, local atomicity, current privileged access |
| **LOG-04** | **A — Risk-Based Log Levels + Centralized Exception Handling** | Consistent severity, expected conflict vs failure, one diagnostic owner, safe bounded logging |
| **LOG-05** | **A — Lightweight Observability + Risk-Based Alerting** | API/Next.js/PG/Redis/Horizon/Outbox/Scheduler/host metrics, business progress, measured thresholds and owner |
| **LOG-06** | **A — Classified Retention + Rotation + Restricted Access** | Technical log budgets, protected audit/evidence, Production/Development separation and privacy |
| **LOG-07** | **A — Structured Incident Response + Safe Recovery Runbooks** | Detect/triage/contain/preserve/remediate/verify/review with financial/Tenant consistency checks |
| **LOG-08** | **A — Final Reconciliation and Publish** | Resolve wording against governing documents, publish only this standard on `development`, preserve all Pilot blockers |

**Approval boundary:** The owner approved **LOG-01…LOG-07 individually** and then **LOG-08 Option A with the instruction to publish on development**. This register does not mean any runtime control is active.

---

## 3. LOG-01 — Structured & Context-Aware Application Logging

### 3.1 Logging architecture

Prefer Laravel's existing logging abstractions and appropriately configured PSR-3 levels, with a **consistent structured JSON log representation** readable by the selected container/host collection path. Ensure Laravel API, Horizon, scheduler, outbox relay and operational entrypoints use aligned fields and environment identifiers, without requiring one new cross-module logging service.

A recommended first implementation candidate is JSON output to the container's managed stdout/stderr stream or another deliberately approved channel. **Candidate does not equal deployed configuration.** Validate the actual Laravel 13 Monolog configuration and Docker/Coolify logging driver, rotation, persistence and read permissions before calling it complete. Do not assume viewing text in Coolify proves an adequate retention store.

Logging is an *operational diagnostic signal*, not the authoritative source for Order, Inventory, Collection, Refund, Tenant activation or business/audit state. Those remain in the appropriate PostgreSQL owning Module. Redis, Horizon dashboards and email provider statuses are similarly not proof of a business effect.

### 3.2 Logical structured event contract

The following is a **logical recommended contract**, not an approved database schema or installed formatter. Final field naming, typing, ingestion and redaction implementation are **TO SPECIFY in Foundation**.

| Field | Rule |
| --- | --- |
| `timestamp` | UTC, precise and parseable; avoid relying on the server's human timezone |
| `level` | Actual technical severity per LOG-04; not arbitrary business status |
| `event_name` | Stable, low-cardinality machine-readable event such as `orders.confirmation_conflict` |
| `message` | Short safe diagnostic statement, not a raw SQL error or sensitive body |
| `environment` | Verified Development/Production context; never mix data or secrets |
| `service` / `component` | API, Horizon, scheduler, relay, Next.js server or approved host component |
| `module` / `feature` | Owning bounded context where known |
| `request_id` | Trusted ID for one HTTP attempt, where one exists |
| `correlation_id` | Correlates approved distributed/async lifecycle |
| `business_operation_id` | Business-effect reference where relevant, not required for every entry |
| `event_id` / `job_id` / `attempt` | Outbox/worker reference when relevant; different roles |
| `execution_scope` | `central` or verified `tenant`; fail closed if scope cannot be established |
| `tenant_ref` / `actor_ref` / `location_ref` | Only trusted, permitted references when diagnostically required; privacy controlled |
| `error_code` / `error_class` | Stable sanitized classification, not a raw credential-rich exception |
| `duration_ms` / `outcome` | Meaningful values only when measured/known; do not claim completion before commit |
| `release` | Immutable image/commit identifier when reliably supplied by release pipeline |

Illustrative JSON event (field names and values are placeholders):

~~~json
{
  "timestamp": "2026-11-21T18:00:00.000Z",
  "level": "warning",
  "event_name": "orders.confirmation_conflict",
  "service": "laravel-api",
  "environment": "development",
  "execution_scope": "tenant",
  "tenant_ref": "trusted-tenant-a",
  "request_id": "example-request-101",
  "correlation_id": "example-correlation-55",
  "business_operation_id": "example-operation-200",
  "error_code": "INSUFFICIENT_STOCK",
  "outcome": "rejected"
}
~~~

Example fields are not a requirement to persist a Customer name, email, full order payload or payment instrument. The example does **not** represent real data or a measured timestamp.

### 3.3 Component and severity discipline

- **HTTP API:** log meaningful failures, exceptional latency if thresholds have been reviewed, unusual security events and diagnosable operations; avoid recording every full request/response by default.
- **Modules:** record events with the owning Module name and authoritative outcome; normal validation and expected `409` business conflicts are **not automatically `ERROR`**.
- **Database diagnostics:** use safe duration/query fingerprint/lock class and connection scope, not raw SQL bindings or confidential customer values.
- **Redis/cache:** track availability, eviction/rejected writes and relevant fallback/consistency risk; no sensitive cached values in logs.
- **Horizon / scheduler / outbox:** distinguish attempt, retry, final failure, relay progress and business completion. No claim a successful relay proves external email delivery.
- **Next.js:** server-side error tracing and frontend-facing safe request context, scoped to the three agreed surfaces; browser telemetry must not expose secrets, Cookies or unrestricted customer PII.
- **Security:** bounded identity/auth/Host mismatch signals with verified scope; not an indiscriminate body capture.

### 3.4 Logging ownership and safe emission

Use a well-defined reporting boundary per operation to avoid the same exception being logged independently by Controller, Action, Repository and global Handler. A lower layer may add structured context or translate a typed error but should not independently emit repeated full exception dumps unless there is a specific independent signal.

Events that claim a business effect **committed** must reflect actual committed evidence, not a log statement emitted before transaction success. Do not put operational log availability into the core business transaction unless a specifically approved required Audit rule demands transactional persistence in PostgreSQL.

**Acceptance:** a representative API failure and worker error can be correlated with safe JSON fields; no secrets or cross-Tenant context appear; normal high-frequency operations do not overwhelm the host.

---

## 4. LOG-02 — Request Context, Correlation and Trace Propagation

### 4.1 Identifiers must not be conflated

| Identifier | Authority and lifecycle | Must not be mistaken for |
| --- | --- | --- |
| `X-Request-ID` | Server-generated unique identifier for **one HTTP attempt**, returned in a response header where feasible; a client-supplied value is untrusted until explicitly validated | Idempotency, a Tenant permission, a business effect |
| **Correlation ID** | Safe, propagated lifecycle reference connecting HTTP, Action, outbox, jobs and provider attempts | Authorization, guaranteed distributed transaction |
| **Business Operation ID** | Stable owning-Module commercial-operation identity; usable in actual business idempotency and reconciliation | An HTTP request/queue attempt |
| `Idempotency-Key` | Client retry identity for approved sensitive routes, subject to scope/payload matching and independent server-side effect safety | `X-Request-ID` or a security grant |
| **Outbox Event ID** | One persisted event intent and its publication history, scoped to the authoritative DB | Business-effect completion or delivery guarantee |
| **Job / Attempt ID** | Physical worker execution and retry diagnosis | Permission to repeat stock/money effects |
| **Trusted Tenant and Actor context** | Verified routing, current membership and, where necessary, current Location/role/permission | Arbitrary header, Cookie claim, client text or mutable global worker state |

Client-provided tracing hints may be accepted only under a deliberately reviewed validation/trust policy. Create or normalize the server's `X-Request-ID` at the trusted middleware boundary; do not treat an arbitrary incoming ID as server authority. Exact format/length/entropy and propagation headers are Foundation details, not chosen here.

### 4.2 Context lifecycle — HTTP to async

1. **Ingress:** verify the Host/proxy chain, resolve Central/Tenant scope, authenticate/authorize using the existing approved model, generate a trusted `X-Request-ID`, and establish a safe request correlation context.
2. **Application Action:** retain the lifecycle reference, current verified actor/Location where needed, and the stable business operation identity. Authorization checks remain independent of trace metadata.
3. **Local PostgreSQL transaction:** commit true business facts and any **required local Audit + Outbox intent** under the approved transaction ownership. Correlation data is metadata, not the transaction authority.
4. **Outbox relay:** read only committed events, publish at least once and preserve appropriate event/correlation and trusted Tenant reference.
5. **Horizon consumer:** resolve and revalidate the actual target Tenant/System context before work, use a fresh attempt identity and preserve the logical event/operation identity. Validate business state/idempotency before side effects.
6. **Cleanup:** always clear Tenant connection, authorization and request/logging context on both success and exception (including reused workers).
7. **External adapter:** record outbound intention, attempt/provider reference and trustworthy acceptance/failure/uncertainty separately. Do not label `Delivered` without acceptable evidence.

Conceptual lifecycle only:

~~~text
Next.js request
  -> Laravel Request ID (one HTTP attempt)
  -> Trusted Tenant + Correlation ID
  -> Orders Application Action / Business Operation ID
  -> PostgreSQL committed business record + required Audit + Outbox Event ID
  -> Relay publication attempt(s)
  -> Horizon Job ID / worker attempt(s)
  -> Notification intent / provider attempt / proven outcome if available
~~~

A new HTTP retry can have a **new Request ID**, while retaining the same legitimate idempotent business operation reference. A relay re-publication or job retry can have another attempt ID without constituting a second Order, Refund or Collection.

### 4.3 Central / Tenant A / Tenant B in one process

- Verified scope is mandatory for every scoped emission; there is no fallback from Tenant A to Tenant B or Central.
- Context propagation must never copy a previous worker's Tenant, DB connection, actor, Location or log fields into a later job.
- No globally mutable "active tenant" logger context may survive a request/job; use appropriately scoped per-execution context and a guaranteed cleanup/finally path.
- A Tenant selector or correlation ID is insufficient authentication; recheck business permissions when the deferred work's authorization depends on a still-authorized initiating user.
- For a system-owned continuation of a previously committed obligation, use explicit verified system context and business policy, not stale user Session Cookies.
- Stable local numeric IDs can repeat across separate Tenant databases. Cross-environment logs/events need the verified scope to disambiguate references.

### 4.4 Next.js and API behavior

The separate Next.js deployment participates in the observability contract through agreed `/api/v1` and same-origin reverse-proxy behavior, not through a new authentication mechanism. The backend response exposes `X-Request-ID` as the response header where feasible, not automatically in the JSON envelope.

Browser logs must not contain access tokens, raw Cookies, private HTTP bodies, customer financial evidence or other customers' error detail. Correlation across SSR/browser, API and server errors is designed and verified in the future frontend implementation; no assumption it exists in the present repo.

### 4.5 LOG-02 acceptance

- A single Checkout/Order lifecycle can be followed across HTTP, SQL-committed operation, Outbox, relay, Horizon and provider-attempt state without falsely claiming the provider delivered.
- Two independent HTTP retries have different `X-Request-ID` values while preserving exactly the appropriate business-effect identity.
- Identifiers are distinguishable in generated logs and incident evidence.
- Tenant A → Tenant B → Central worker sequences and exception paths leave **no stale context**.
- Malicious supplied correlation/Tenant headers cannot impersonate authority or flood logs.

---

## 5. LOG-03 — Domain-Owned Security and Business Audit

### 5.1 Three independent sources of evidence

| Type | Owner / purpose | Authority |
| --- | --- | --- |
| **Technical Log / Metric** | Application/Infrastructure: failures, timing, resource usage, retry diagnosis | Not authoritative for stock, payment, fulfillment or access history |
| **Security Audit / Security Event** | Central Identity/Platform or Tenant-local Identity/Authorization: access, role, memberships, privileges, Host/tenant anomalies | Scoped, permissioned evidence of relevant security decision/attempt |
| **Business Audit + Original Ledger/Record** | Owning commerce Module: approvals, Order/stock/Collection/Refund changes and correction lineage | Business record/ledger is authoritative for the actual effect; Audit is evidence of who/why/what/outcome |

An Outbox row, Redis message, Horizon completed job, provider response or generic activity logger cannot independently prove that stock moved, money was collected or a refund was executed.

### 5.2 Audit placement and ownership

- **Central PostgreSQL:** Platform administrative changes, shared workforce Login/security, Tenant directory/membership, Platform permissions, Plan/Subscription/Entitlement changes and Central-owned support/administrative activity under their authorized owners.
- **Tenant PostgreSQL:** Tenant-local staff roles, grants and Location scope, independent Tenant Customer auth/security, commercial business decisions, Order/Inventory/Collections/Returns/Refunds/fulfillment history and Domain-owned Audit.
- **Cross-context actions:** write local evidence in the owning context(s) when required; use durable orchestration/reconciliation for separate databases. **Never promise one ACID transaction across Central and Tenant DBs.**
- Limited central security telemetry may identify a verified affected Tenant and safety signal, subject to privacy controls; it must not turn Central into a raw copy of all Tenant commerce audit.

Use owning Module Public Contracts; do not introduce a universal audit-writing service that directly mutates every business Module or circumvents One Writer boundaries.

### 5.3 Focused audit event matrix

| Domain / owner | Representative material event | Audit scope and nuance |
| --- | --- | --- |
| Central Identity | Workforce account disablement, relevant credential/security change, Session revocation | Central; no passwords, hashes unnecessarily, or recovery tokens |
| Tenant Memberships | Invite, accept, activate, suspend, revoke, current membership-related privileges | Central source of membership authority |
| Platform Administration | Tenant lifecycle, approved plan/entitlement change, privileged operation | Central; durable progress for cross-DB provisioning |
| Tenant Identity & Authorization | Tenant Role CRUD, role assignments, permission/Location grants and revocations | Tenant-local; verified actor, target, result |
| Delegated Discounts | Grant/revoke of narrowly approved personal `Approve Manual Discounts` permission | Tenant-local; preserve D-084/D-085 exceptions; no universal user-permission override |
| Tenant Customer Security | Verification/account security/suspension, safe authentication evidence | Tenant-local, proportional and rate-bounded |
| Pricing & Promotions | Manual discount request, decision, special approval and related reason | Owning pricing workflow; no invented blanket two-person approval |
| Inventory | Actual adjustment, transfer/reservation/consumption/correction | Owning ledger/operation reference, Location, authorized reason |
| Orders | Sensitive order state transition and approved correction | Owning state machine; distinguish Pending Stock, confirmed, canceled |
| Fulfillment | Eligible dispatch/pickup, stock consumption and correction | Preserve physical vs delivery/collection distinctions |
| Collections | Actual verified collection, reversal/adjustment, partial payment | Preserve primary financial evidence and legitimate multiple partial collections |
| Returns & Refunds | Request, authorization, actual execution, correction/reconciliation | Approval is **not** proof money refunded; avoid duplicate execution |
| Sensitive Reports/Files | Approved export or access to sensitive report/document | Privileged evidence where mandated; access not automatically granted by role label |
| Future Support Access | Grant, use and revoke of approved exceptional support authority | Central and optionally local audit, only if such Feature is later explicitly approved |
| Infrastructure / Release | Privileged DB role changes, secret rotation, Coolify/GitHub releases | Appropriate platform/operator audit source and accessible evidence |

This matrix establishes classes. Every Feature's `spec.md` identifies **exact** affected records, fields, reasons, permission names, capture rules, retention and tests. A rejected 403 may deserve a bounded security signal without a durable row for every attempt; do not silently write every failed Login or UI click to PostgreSQL.

### 5.4 Logical audit entry — not an approved table

A material event records proportional: event type, owning Module, safe actor and subject references, verified Tenant or Central scope, Location if relevant, original business operation, timestamp, observed/committed outcome, relevant previous/new state or delta (minimized), authorized reason/approval reference, correlation/request reference and correction/reversal lineage.

**Not every event requires every field.** Tenant-local schema, indexes, actor identity cross-database reference representation, archive settings and physical table names remain per-Feature/Foundation decisions. Storing a raw whole before/after JSON object for sensitive resources is **not** a safe default.

### 5.5 Atomicity and truthfulness

When a required Audit fact is an essential part of a **single-database** committed operation, persist it atomically with the actual business mutation and associated required Outbox intent, under the existing owning orchestrator and PostgreSQL transaction. If the required same-transaction Audit write fails, do not leave a committed commercial effect falsely labeled fully audited.

~~~text
BEGIN on verified Tenant DB
  validate current scope/business eligibility
  apply authoritative commercial write
  persist any required local Business Audit and history
  persist required Outbox intent
COMMIT -> true committed outcome

Any required write fails -> ROLLBACK local transaction
~~~

Not all attempts are committed business effects. A rejected unauthorized request may be tracked through a separate bounded security event; do not intentionally abort a required security-event write merely because a business transaction rolls back. Cross-database/platform operations need separate local commits, durable coordination and reconciliation; no arbitrary global commit illusion.

**Technical log loss is not equivalent to required Audit failure.** If a technical collector is unavailable, determine the real persisted business state from PostgreSQL; never blindly repeat a financial action solely because its log is missing.

### 5.6 Append-oriented history and integrity

- Use authorized **correction/reversal/compensating business workflows**, preserving original facts, effective effect and reasoning. Do not simply overwrite/delete real historical financial/stock rows.
- Make ordinary application writes to Audit history append-oriented, appropriately privileged and constrained. Do not expose general-purpose Audit UPDATE/DELETE operations to merchant staff.
- A normal PostgreSQL table is **not proof of cryptographic tamper prevention against privileged DB administrators**. Backups, privileged operations, immutable media or external attestations require their own tested design; this standard does not claim them.
- Privacy/erasure requests require lawful minimization and Feature-specific retention evaluation. Neither `softDeletes()` nor an "immutable forever" claim supersedes legitimate privacy controls.
- Historical audit and original ledger records must remain consistent when a correction is authorized.

### 5.7 Audit authorization and disclosure

Audit read/export is a distinct privileged action. Re-evaluate the user's current Central/Tenant membership, Tenant/Location scope, resource ownership and relevant permission. An actor allowed to edit Roles or approve discounts is not thereby allowed to rewrite history. Private audit exports must check permission again before execution and download when user-initiated deferred work is involved.

Protect against enumeration of other Tenant/Customer resources; use `403`/privacy-aware `404` semantics under API Standards. Tenant A audit exports must never contain Tenant B data. Operators with privileged server/DB access are a distinct security boundary and require separately managed credentials and accountability.

### 5.8 LOG-03 acceptance

- Required same-DB audit failure rolls back its dependent business effect in a real PostgreSQL test.
- Central Platform/membership and Tenant-local business/role changes appear in the **correct DB only**.
- Same business-operation Retry does **not** create a duplicate successful money/stock fact; technical attempts can still be visible separately.
- Independent legitimate partial collections/refunds produce independently authorized and referenced facts, not accidental de-duplication by Order ID.
- Financial correction retains the original and linked correction history; approval and actual execution states differ.
- Unauthorized audit reads/exports and post-revocation exports fail; sensitive data are not blindly copied into audit.

---

## 6. LOG-04 — Log Severity, Error Classes and Exception Handling

### 6.1 Normalized levels

Use existing PSR-3-style levels consistently; not every Module must emit all of them.

| Technical log level | Typical intended meaning | Non-example / guard |
| --- | --- | --- |
| `debug` | Controlled development/on-demand diagnostics | Never raw credentials, full request bodies or default verbose Production dumps |
| `info` | Important routine application/worker lifecycle fact | Not a substitute for authoritative transaction or Audit |
| `notice` | Relevant unusual but expected condition | Not a confirmed outage |
| `warning` | Recoverable/transient issue, meaningful business conflict or safety signal | Not automatically a page-worthy incident |
| `error` | Failed required technical action or unexpected exception | Not every `422`, `404` or expected `409` |
| `critical` | Confirmed severe integrity/security/system failure | Requires owner-led triage; do not infer solely from one isolated `403` |
| `alert` / `emergency` | Exceptional operational conditions requiring urgent response | Separate Incident Severity and actual delivery still need verification |

**Log level ≠ Business outcome ≠ Incident severity.** A `warning` log can contribute to a high-severity trend; an individual `500` may have limited scope, while silent duplicate stock effects can be Critical.

### 6.2 HTTP, domain and worker error classes

| Class / example | Correct handling | Typical API/log guidance |
| --- | --- | --- |
| Invalid API fields | Form Request/contract validation; safe localized message | `422`; not automatic `error` |
| Missing Session | Auth boundary; no raw Cookie in diagnostic | `401`; selectively aggregated security signal |
| Forbidden role/Location/resource | Deny using current policy and privacy rules | `403` or privacy-aware `404`; log high-value anomalies proportionately |
| Missing/hidden resource | No cross-Tenant existence leakage | `404`; not automatically a system fault |
| Business conflict | Report actual state/eligibility; no blind retry | Usually `409` where contract requires; `info`/`warning` at need |
| Rate limit | Correct bounded rate-limit response | `429` and safe aggregates; no flood |
| CSRF/session expiry | Keep approved Sanctum/Next.js behavior; finalize browser handling | Laravel `419` behavior requires implementation review; do not weaken CSRF |
| Unexpected internal fault | Central safe exception renderer; diagnose once | `500`, internal `error`; no stack trace to client |
| Real service unavailable | Fail closed; correct service-degradation status | `503` where applicable; bounded alert |
| Confirmed Tenant data exposure | Security incident, contain and preserve evidence | `critical` incident; an attempted blocked request alone is not proof |
| Actual duplicate financial/stock effect | Stop unsafe affected path, reconcile owning records | `critical` integrity incident; no automatic repeated refund |
| Confirmed safe transient job failure | Bounded retry/backoff; attempt diagnostics | `warning` as warranted, escalate if exhausted/stuck |
| Unknown external outcome | Do not label failure or success without evidence | Explicit **Uncertain** business state and reconciliation |
| Permanent misconfiguration/schema | Stop futile retries, require correction | `error` or higher according to impact |

Actual domain error identifiers and HTTP mapping belong to the owning Feature/OpenAPI; LOG-04 must not invent new generic error codes or change previously approved API responses.

### 6.3 Exception handling ownership

- **Domain:** return typed domain outcomes/errors and enforce business invariants; do not blindly send HTTP or log stack traces.
- **Application Action:** decide appropriate domain effect and local transaction, typed exceptions and safe retryability.
- **Infrastructure adapter:** distinguish temporary failure, permanent configuration failure and unknown outcome; attach safe diagnostic context.
- **Global API exception renderer:** produce one consistent, sanitized HTTP error envelope and appropriate status. No raw SQL, private path, secret, Customer PII or Tenant existence leak.
- **Logging/reporting boundary:** emit the primary diagnostic once with Correlation/Request IDs. Extra context is preferable to duplicate full stack traces at all layers.
- **Horizon/job boundary:** preserve the meaningful attempt/failure identity and let approved retry/reconciliation policy operate; never hide a failure by falsely marking the Job or underlying business fact successful.

For errors where the exact completion status is unknown, **inspect the PostgreSQL authoritative record** and trustworthy provider evidence before any repeat operation; logs alone cannot prove an earlier transaction rolled back.

### 6.4 Volume and safe failure policy

- Avoid unbounded logs inside hot loops, HTTP access paths, high-volume `404`/`422`, login bursts or repeated failed provider retries.
- Use proportionate aggregation or rate control for routine technical diagnostics; sampling and drop policies, if any, require measurement and cannot erase required Security/Business Audit.
- Never suppress critical integrity failures, required security evidence or actual operation state simply to make metrics green.
- Structured error fields and stack diagnostics may be visible internally under restricted roles; clients receive only approved safe messages.
- Technical logging sink failure must be visible where possible, but must not be confused with required PostgreSQL Audit failure or automatically trigger a second commercial effect.

### 6.5 LOG-04 acceptance

Expected Validation, ordinary `404` and safely rejected stock conflicts do not fill Production with error-level stack traces. Unexpected PostgreSQL/Redis failures produce privacy-safe, scoped diagnosis and truthful HTTP or worker outcomes. A retry and its final state are distinguishable. One exception does not get redundantly emitted by multiple layers. Logs never expose Session IDs, Tokens or private SQL parameters.

---

## 7. LOG-05 — Monitoring, Metrics, Business Progress and Alerting

### 7.1 Instrumentation baseline

Start with actual available Laravel/Next.js, PostgreSQL statistics, Redis, Horizon, Docker/Coolify and host-level mechanisms. Separate Development and Production data, credentials, retention and alert visibility. Introduce a dedicated metrics collector, tracing platform or vendor **only after** operational measurement/need and approved cost, access and reliability review.

**No dashboard existing in a UI is evidence that collection, historical retention, alerts or business completeness are configured.** Metrics instrumentation is planned, not installed by this Markdown.

### 7.2 Minimum measurement matrix

| Layer | Required observable dimensions | Safety / interpretation |
| --- | --- | --- |
| Laravel API | Request counts, error rates, actual P50/P95/P99 latency and route-family signals | Avoid one metric label per raw URL/resource ID or untrusted Tenant selector |
| Next.js | Availability, server-side render/API-boundary failures, meaningful server latency | Browser privacy and future frontend-specific implementation; no token capture |
| PostgreSQL | Connection pressure, active/idle/long sessions, locks, deadlocks, slow queries, IO, disk, temp files | Prefer native statistics, safe query fingerprints, deltas and `stats_reset` awareness |
| Tenant migration/provisioning | Schema/version drift, failed migration and stuck provisioning stages | Do not report global success when one Tenant remains failed |
| Redis | Availability, latency, memory headroom, evictions, rejected writes, connections | Redis is not money/stock authority; fail closed where correctness needs it |
| Horizon | Queue wait/age, throughput, failed jobs, retries, processing duration, worker state | Successful job processing != external delivery or actual business effect |
| Outbox relay | Last successful scan/progress, pending count, oldest unpublished event age, stale claims and publish failures | Horizon may be idle while scanner has stopped |
| Scheduler | Effective ownership, last run/heartbeat, missed runs, overlaps and failures | Prevent double-effective scheduling during blue-green overlap |
| Local file storage | Disk free/IO, failed writes, mount persistence, public/private authorization failures, cleanup progress | Container liveness alone cannot prove private file safety |
| Docker/Coolify/VPS | CPU, RAM, free disk, IOPS, network, restarts, proxy/ready state and rollout resources | One VPS is a shared failure domain |
| Deployment | Image/digest, readiness, old/new overlap, drain, post-deploy smoke and rollback viability | Healthy container != tenant-safe completed release |
| Commercial outcomes | Material failed/uncertain order, reservation, Collection, Refund, reconciliation and fulfillment states | Authoritative owning PostgreSQL evidence, not logs alone |
| Security posture | Privilege change anomalies, trusted Host/Tenant mismatch, meaningful denied-cross-scope patterns | Suppress noise carefully; preserve material evidence |

Where percentiles are requested, compute them from valid timed measurements and state the environment/workload. Do not fabricate a measured baseline or numeric capacity claim from theoretical architecture.

### 7.3 Health states are different

| Signal | What it proves | What it does not prove |
| --- | --- | --- |
| Container **liveness** | Process/container seems alive | API can safely accept business traffic |
| API **readiness** | Approved HTTP checks and dependency/config checks pass | All business workflows and Tenants are correct |
| DB/Redis health | Component connectivity or approved health signal | Correct commercial ledger or sufficient capacity |
| Horizon operational state | Workers/supervisor can execute | Outbox relay progress, user authorization or provider delivery |
| Outbox scanner heartbeat | Scanner has made progress per instrumentation | Every event consumed or email delivered |
| Business reconciliation status | Actual verified material outcome for scoped operation | Whole application has zero defects |

A meaningful outage may exist with 200-ready health endpoints: e.g., valid Orders and committed Outbox intent while relay scanning has stopped. Observe **scanner heartbeat + oldest Outbox event** as well as queue length.

### 7.4 Tenant-aware diagnosis without unsafe cardinality

- Segregate Production/Development and Central/Tenant evidence by verified trust scope.
- Use aggregate environment/service/Module/route-family/queue-class metrics as the default. Add bounded per-Tenant diagnostic references only where necessary and secured.
- Avoid `tenant_id`, `actor_id`, `order_id` or arbitrary URL as unrestricted time-series labels across all measurements: they can cause high-cardinality storage/cost and leak scoped references.
- Identify a noisy Tenant affecting shared DB, Horizon, CPU, Redis or IO without globally exposing every merchant's operations.
- No shared log interface may return other Tenants' raw customer, Order, Session, financial or private file records to a merchant.
- Tenant A outage must not cause Tenant B/Central fallback; diagnose current DB connectivity and error outcomes.

### 7.5 Incident-oriented alert severity

| Severity | Representative trigger class | Action |
| --- | --- | --- |
| **Critical** | Confirmed unauthorized Tenant exposure, credential compromise, unauthorized/duplicate financial effect, severe actual business corruption | Named incident owner, immediate containment of unsafe affected operation and evidence preservation |
| **High** | Material repeated Host/scope mismatches, actionable service unavailability, stalled critical Outbox, substantial data/DB write safety risk | Prompt assigned investigation, assess blast radius, escalate if business impact proven |
| **Medium** | Performance degradation, meaningful retry spike, developing storage/resource pressure | Diagnose, aggregate and trend |
| **Low / Informational** | Isolated expected failures, recovered transient retries or routine state | Proportionate visibility without unnecessary paging |

A single blocked `403` is not confirmed compromise. Severity is determined by **impact/evidence**, not by numeric HTTP class alone. Alert policies require an explicit Owner, source, condition, affected scope, correlation pointers, intended delivery channel, safe runbook and recovery proof.

### 7.6 Numeric budgets and service commitments

**PENDING VALIDATION, not approved values:** P95/P99 target, uptime, RPS, CPU/RAM/IOPS caps, PostgreSQL connection count, stale scheduler/outbox age, queue wait, Redis memory, worker count, log loss budget, sampling percentage, alert grouping/delay, incident response time and log-retention day counts.

Define proposed thresholds during Foundation/Infrastructure work, then verify/adjust them with **TEST-08** representative normal/burst/stress/soak/noisy-Tenant/Blue-Green measurements, including memory/connection overlap on the initial VPS. The ability to produce a number does not mean it is a meaningful SLO.

### 7.7 Alert delivery and ownership

Every release-critical alert needs an **actual, tested** notification or review mechanism and named operator. The channel/provider and operator roster are **TO SPECIFY**; do not assume SMS, Slack, WhatsApp, paid SaaS or 24/7 on-call support exists. Verify alerts route to the intended recipient and can be acknowledged/escalated. Monitor or test the monitoring failure path where feasible.

Do not create an externally accessible privileged monitoring endpoint just to make a dashboard show green. Protect Horizon/Coolify credentials and private diagnostics under their own operator access boundaries.

### 7.8 Deployments and Pilot relevance

Observe pre-deploy baseline, overlap resource headroom, rollout/readiness, current PostgreSQL schema and Tenant migration drift, worker/scheduler coordination, `X-Request-ID` error diagnosis, queue/outbox progress and post-deploy commercial smoke. Rollback is safe only where the old image is compatible with DB/files/jobs already changed.

**BA-30 G5** requires measured performance/SLO/alerts, **G6** requires real security/incident controls, and **G4/G9** require genuinely verified release/environment boundaries. No documentation-only PASS.

---

## 8. LOG-06 — Retention, Rotation, Storage, Access and Privacy

### 8.1 Classify by purpose, not file extension

| Data class | Storage/owner | Retention direction |
| --- | --- | --- |
| Technical Laravel/API logs | Approved runtime collector/managed log storage | Bounded operational period; actual days/size **TO SPECIFY** |
| Horizon/scheduler/relay diagnostics | Approved runtime collector plus real job/workflow state | Technical diagnostics bounded; durable business/Outbox state separate |
| Host, PostgreSQL and Redis diagnostics | Infrastructure operator with least privilege | Bounded and appropriate to incidents/operations |
| Security signals | Central/Tenant-owning audit or protected security telemetry | Event/risk/legally appropriate policy; high-volume failures may be aggregated |
| Platform security/administrative Audit | Central PostgreSQL, respective owning Module | Feature/legal/privacy and legitimate business retention |
| Tenant business/security Audit | Tenant PostgreSQL, respective owning Module | Feature/legal/privacy and correction-history retention |
| Original Collection/Refund/Inventory/Order evidence | Owning authoritative tables/documents | **Never deleted by a generic technical-log rotation policy** |
| Protected incident evidence | Authorized incident-evidence handling path | Incident-specific documented legal/operational hold and release |

There is **no one global retention window**. The owning Feature must specify legal basis/purpose, privacy minimization, access/export, data lifecycle and permitted erasure exceptions. An "immutable forever" promise is not adopted; nor is automatic deletion of legitimate financial evidence.

### 8.2 Container / host technical log hygiene

- Configure deliberate **rotation by size/age**, bounded copies/quota and cleanup; validate the chosen runtime/logging driver and Coolify configuration. Exact max size/files/days are **TO SPECIFY after measurement**.
- Favor compression when appropriate; verify retained records stay queryable to the extent required for operations.
- Avoid unbounded JSON stdout/stderr or Laravel file logs consuming the shared VPS disk/IOPS. Remember PostgreSQL and mounted business files share important host resources in the initial topology.
- Inspect real persistence behavior across Docker container replacement, deploy, reboot and log collector interruption. Logs inside disposable container filesystems should not be assumed to survive.
- Separate environments at collection, authorization, retention and filesystem/tenant diagnostic paths; Development tests must never access or overwrite Production logs or secrets.
- Record/log collector failures and disk-near-full conditions in an independent feasible operational signal; do not promise the same exhausted disk can always persist its own failure record.
- Enforce permissioned access to technical traces and incident files, not public URLs or merchant UI endpoints.

### 8.3 Who may inspect/export

| Role / scope | Access principle |
| --- | --- |
| Developer | Approved Development diagnostics; Production only by explicit authorized operational process |
| Infrastructure Operator | Minimal host/container diagnostics to operate the system; separately protected powerful credentials |
| Security / Incident Owner | Scope-limited evidence needed for investigation; preserve custody |
| Platform Admin | Application role alone does **not** grant access to all host logs, customer payloads or all Tenant Business Audit |
| Merchant Employee / Customer | No raw server/DB logs; only feature-authorized audit/activity views as deliberately specified |
| Tenant Business Auditor | Current Tenant/Location/resource permission and privacy scope, explicitly approved export |
| Privileged DB/host administrator | Acknowledged capability/risk boundary: operations and grant changes require authorization/accountability |

An Audit export is sensitive and must validate current permissions on request, deferred execution where applicable and download. Revocation must not be defeated by a stale cached capability or previously issued unrestricted link.

### 8.4 Data minimization and incident holds

1. Prevent sensitive values at emission: redact/omit tokens, passwords, Sessions, full provider payloads, payment instrument data, unnecessary addresses/contact data and private document contents.
2. Limit exceptional stack trace/SQL diagnostics to authorized, secured environments and redact sensitive bindings.
3. Define technical retention periods from actual threat/operational need and lawful data processing, not from convenience alone.
4. If a serious incident requires evidence, identify the relevant safe records, authorized custodian, reason, preservation time, access history and eventual hold release policy. Do not preserve all customer information indefinitely by default.
5. Consider downstream caches, exports, job payloads and later approved backup copies in privacy/deletion decisions. Soft delete is not proof of permanent erasure.
6. **BA-16 remains DEFERRED.** This standard does not select backups, snapshot interval, PITR, vendor, encryption settings, off-VPS archival or RPO/RTO.

### 8.5 When a log or disk write fails

| Failure | Required interpretation |
| --- | --- |
| Optional technical sink unavailable | Diagnose/recover safely; check PostgreSQL for actual business outcome, do **not** repeat money/stock effect because log is absent |
| Required same-DB business Audit write fails | Apply owning atomic transaction rollback under LOG-03 |
| PostgreSQL full/read-only/unavailable | Treat authoritative write as failed/uncertain until verified; never return fictitious success |
| Host disk pressure | Trigger actual resource response before service corruption; never erase business/Audit history as arbitrary cleanup |
| Collector outage | Handle visibility gap and preserve privacy; re-evaluate reliability before claiming observability healthy |
| Container replaced | Verify intentional persistence and rotation policy; do not assume old ephemeral logs remain accessible |
| Incident retention hold | Exclude properly held evidence from ordinary cleanup according to approved incident policy |

### 8.6 LOG-06 acceptance

Load a safe representative log volume; verify actual rotation, space budgets and cleanup; inspect service restart/replacement semantics; test restricted read/export, Production/Development segregation, secret redaction, disk pressure and incident-evidence preservation. All numeric retention limits must be recorded as **specified/tested**, not inferred.

---

## 9. LOG-07 — Incident Response, Recovery Diagnostics and Runbooks

### 9.1 Lifecycle and accountability

~~~text
DETECT -> TRIAGE -> CONTAIN -> PRESERVE EVIDENCE
       -> REMEDIATE -> VERIFY RECOVERY -> REVIEW / REPORT
~~~

1. **Detect:** capture the triggering alert, user report or automated test result, with trusted correlation and time.
2. **Triage:** determine material impact, confidence, security/privacy/commercial risk, blast radius (Central, Tenant A, other Tenants, shared host) and whether writes remain safe.
3. **Contain:** stop/disable only the unsafe route, worker, credential, export or operation when possible; do not invoke a broad outage without justified risk.
4. **Preserve evidence:** secure pertinent logs, immutable image/commit evidence, original PostgreSQL records, relevant Audit, operation references, schema/migration state and trusted provider evidence.
5. **Remediate:** fix the cause, revoke/rotate impacted secrets or Sessions where required, resume guarded workflow stages and execute authorized corrections rather than deleting historical records.
6. **Verify recovery:** prove affected Tenant/Location permissions, Order/Inventory/Collections/Refund invariants, session/proxy routing, worker/outbox progress and application readiness; do not stop at green container health.
7. **Review:** record cause, actual impact, correction, residual risk, owner, technical regression tests, changed runbooks and legal/customer-notification review where applicable.

### 9.2 Incident owner and supporting responsibilities

| Role | Accountability |
| --- | --- |
| **Incident Owner** | Tracks status, assigned scope, evidence, decision timeline, communication and final verification |
| Backend / Domain Owner | Determines source-of-truth business state, transactions, reconciliations and code defects |
| Infrastructure Operator | Docker/Coolify, DB/Redis/host, release, credentials, resources and recovery capability |
| Security Reviewer | Containment, audit custody, privilege/PII impact and authentication/authorization risk |
| Business / Legal reviewer, where required | Merchant impact, accepted corrections and applicable notification/tax/privacy obligations |

One person may legitimately hold multiple roles in an early team. **No fixed-size on-call team or staffed 24/7 coverage is assumed**. Assign real names/channels/availability **before Pilot**, and test the delivery/acknowledgment of critical alerts.

### 9.3 Triage: classify before retry

| Classification | Typical signal | Safe next step |
| --- | --- | --- |
| Confirmed safe transient | Rolled-back PostgreSQL deadlock; demonstrably no business effect | Bounded retry/backoff with current eligibility checks |
| Expected business conflict | Stock depleted; invalid state; revoked role | Stop, return accurate error; do not brute-force repeat |
| Unknown/ambiguous result | HTTP timeout after possible commit; uncertain external collection/refund | Reconcile authoritative database/provider evidence before re-execution |
| Infrastructure unavailable | Redis or correct Tenant DB unreachable | Fail closed, retain durable intent, isolate scope |
| Permanent/configuration | Invalid schema, role grants, unsupported job payload | Stop futile retries, correct with reviewed deployment path |
| Cross-DB partial workflow | Tenant DB created but Central activation incomplete | Resume from durable stage; no assumed distributed rollback |
| Confirmed unauthorized cross-Tenant exposure | Protected records accessible outside intended scope | Critical containment, preserve evidence, determine blast radius |
| Confirmed duplicate business effect | Duplicate stock or verified financial movement | Halt unsafe affected path, protect original evidence, authorized reconciliation/correction |

### 9.4 Minimum operational runbook matrix

| Scenario | First reliable evidence | Safe action and recovery proof |
| --- | --- | --- |
| PostgreSQL connections exhausted / lock storm | `pg_stat_activity`, `pg_locks`, long transactions, affected DB, service connection rates | Identify source and block/resume safely; never kill arbitrary money transaction without outcome analysis |
| Tenant DB unavailable | Verified tenant routing, connection errors, DB role, current schema | Fail closed to that DB, no previous-Tenant/Central fallback; prove unaffected Tenants and restored permissions |
| Redis failure / rejected writes | Redis availability, Sessions, Horizon transport, API errors | Preserve authoritative PostgreSQL and outbox facts; do not represent queued work as processed |
| Horizon stopped / retries exhausted | Worker/supervisor state, job IDs, exception class, owning Module state | Diagnose, revalidate idempotency, retry only proven safe operations; **no blind `retry all`** |
| Outbox scanner stopped | Heartbeat, oldest unpublished event, relay claims and DB Outbox | Restore verified scan/publish progression, distinguish published/processed/delivered; no duplicate commercial effects |
| Scheduler overlap at deploy | Scheduler identity/heartbeats, lease/lock, rollout context | Ensure only a safe effective scheduler for non-idempotent work and valid job payload compatibility |
| Storage / mount failure | Disk and mounted path state, failed authorized file writes | Protect actual files/evidence; check privacy and safe recovery; do not delete business history |
| Deployment regression | Immutable image digest, readiness, HTTP error rates, schema state, worker overlap | Roll back **application traffic/image only if DB/file/job compatibility is proven**; otherwise controlled remediation |
| Partial Tenant provisioning / migration | Central durable state, tenant schema version and migration logs | Resume/reconcile only validated stages; do not activate unready Tenant or declare blanket success |
| Duplicate inventory reservation / Collection | Original business operation/ledger, conditional writes, Audit and competing attempts | Stop unsafe path, determine actual effects, authorized correcting transaction with lineage and tests |
| Uncertain external Refund/Collection | Real provider or physical evidence, owning persisted state | Do not infer completion or blindly send a second request; escalate for manual reconciliation |
| Privilege change or Host/Tenant anomaly | Verified scope, rejected vs actual accessed resources, relevant Audit | Limit compromised permissions/credentials; preserve facts; distinguish denied attempt from confirmed breach |
| Monitoring/alert collector outage | Collector health/expected signal test and trusted independent host state | Mark observability degraded; use approved fallback visibility and do not claim all-clear without evidence |
| Blue-Green resource exhaustion | Memory, CPU, IO and DB connections during overlap | Adjust release plan or maintenance window; safe cutoff/review, no unconditional zero-downtime claim |

An incident runbook must name which operators may perform privileged actions, how success is verified, how to avoid accidental cross-Tenant access, and where the evidence/timeline will be stored. Commands to forcibly clear queues, wipe DBs or repeat payments are **not** safe generic runbook steps.

### 9.5 Business recovery is more than green health

Do not close a meaningful commerce incident until its affected authoritative invariants are verified:

- **Orders:** correct state/confirmation and eligible single-Location reservation; Pending Stock did not auto-confirm.
- **Inventory:** no duplicate reservation, consumption or transfer; no overselling from concurrent attempts.
- **Collections:** recorded actual verified collection(s), partial payments and permitted adjustments reconcile to original money evidence.
- **Returns/Refunds:** approval, execution, actual money and physical restock distinguished; no fictitious success.
- **Fulfillment:** authorized dispatch/pickup consumed stock once; Delivery status is not automatically COD settlement.
- **Outbox/Horizon:** durable intents and consumers progress correctly; provider acceptance vs proven delivery correctly distinguished.
- **Tenancy/Auth:** correct current membership/Location, scoped DB role/connection and no cross-Tenant session/data leak.
- **Infrastructure:** environment/cookies/secrets, file mounts, DB schema and old/new image compatibility are demonstrably safe.

If the outcome of a financial action remains unknown, mark it as such and escalate; do not close the incident as fully reconciled.

### 9.6 Incident record template — logical fields

| Field | Evidence expectation |
| --- | --- |
| Incident ID / status / detected time | Stable internal record and actual timeline |
| Named owner / reviewer | Accountable person and authorization |
| Scope and affected operations | Central/Tenant(s), relevant services/Locations, verified blast radius |
| Severity and confidence | Based on real impact, not guessed from a single 403/500 |
| Original Operation / Correlation / Request / Event references | Safe trace pointers, no leaked credentials |
| Evidence locations and access | Relevant logs, Audit, business records, release, DB, trusted provider |
| Containment/rollback/correction actions | Who performed what and why, with immutable/protected original evidence |
| Root cause and uncertainty | Confirmed, probable or pending; avoid unsupported conclusions |
| Recovery validation and sign-off | Repeatable smoke/invariant proof and responsible approval |
| Remaining risk, prevention tasks and legal review | Follow-up tests/runbook/permission fixes and notification review where applicable |

### 9.7 LOG-07 acceptance

Rehearse failures without touching live merchant data: Redis interruption after local commit; stopped relay despite idle Horizon; Tenant A DB failure; repeated worker attempts; revoked export access; controlled deployment incompatibility; confirmed high-value security signal and recipient delivery. Each rehearsal must show the correct affected scope, the owner, evidence, avoidance of repeated effects and verified recovery.

**Workflow recovery is not Disaster Recovery.** Off-VPS backup selection, full/per-Tenant restore rehearsal, measured RPO/RTO and sign-off remain BA-16 and BA-30 G3 work, which is **BLOCKED** at present.

---

## 10. LOG-08 — Cross-Document Reconciliation and Publication

### 10.1 Explicit compatibility matrix

| LOG decision | Governing sources | Resolved interpretation |
| --- | --- | --- |
| **LOG-01** | BA-24, CS-10, SEC-07, INF-11 | JSON structured output and safe Laravel/host instrumentation; vendor/formatter/runtime still needs setup |
| **LOG-02** | AS-02, CS-10, TC-04/06, QJ, TA | Request ID, correlation, Business Operation, Event and Job are separate; Tenant context always verified |
| **LOG-03** | BA-14, CS-10, AUTH-09, AUTHZ-09, TC-01/05, SEC-09 | Tenant Business Audit stays Tenant-local; Platform/membership audit stays Central; same-DB atomic evidence |
| **LOG-04** | AS-02/03, CS-10, SEC-07, QJ-09 | Expected `409`/`422` are not automatic errors; safe API and worker exceptions without duplicated logging |
| **LOG-05** | BA-24/29/30, INF-09/11, DB-10, CR, TEST-08 | Business progress and readiness are distinct; no arbitrary SLO or thresholds; real load validation required |
| **LOG-06** | SEC-09, DA-06, FS-05, INF-11 | Tech rotation distinct from business Audit retention; future legal limits and BA-16 are not selected |
| **LOG-07** | SEC-07, TC-06, QJ-11, INF-09, BA-30 | Preserve original evidence, do not blind-retry, verify financial/Tenant outcome before closing |
| **LOG-08** | All above; WORKFLOW | Approved document publication only; no code/CI/metrics/backup/deploy/main merge |

**No material conflict identified in the reviewed approved baseline.** This is a document-level consistency conclusion, not a proof that all runtime systems are configured or safe.

### 10.2 Important reconciliation clarifications

1. **Audit does not mean central data aggregation.** Central Platform security monitoring can contain privacy-limited Tenant alerts; commercial Audit and ledgers stay in their owning Tenant databases.
2. **Atomic Audit only when local and required.** A required business Audit can share its owning DB transaction; separate Central/Tenant writes cannot be disguised as one transaction.
3. **Retries vs history.** Technical retries may emit distinct attempt diagnostics, while stable business operation keys must suppress duplicate actual effects without suppressing legitimate separate partial Collections/Refunds.
4. **HTTP status != log severity.** Expected `401`, `403`, `404`, `409`, `422` and `429` remain governed by API semantics and risk. A rejected unauthorized request is not proof of a breach.
5. **Readiness != consistency.** A healthy Laravel Container/Horizon does not establish active Outbox scanning, correct stock or completed notification delivery.
6. **No global per-Tenant metrics labels.** Use measured, privacy-limited diagnostic references where needed; avoid unbounded cardinality.
7. **Retention is not Backup/DR.** Technical log rotation and incident evidence policies do not select BA-16, off-VPS backups or measured RPO/RTO.
8. **No permanent incident staffing assumption.** Real operator roster/alert channels and actual critical alert delivery are to be selected and tested.
9. **No frontend implementation claim.** Next.js metrics and browser correlation are interface expectations; frontend architecture/repository and implementation require their own work.
10. **Production is not authorized.** Neither document approval nor green-looking mock dashboards proves G1–G10 have passed.

### 10.3 Evidence tiers and intended handoff

- **Design evidence:** the approvals and linked architectural rules in this document.
- **Implementation evidence:** inspected Laravel/Next.js logging/exception/audit code, versioned configuration, schema and permissions.
- **Integration evidence:** actual Central plus two separate Tenant PostgreSQL databases, Redis/Horizon, correct Host/Session and fault injection.
- **Operations evidence:** measured metrics, verified alerts/owners, disk rotation/restart tests, log access/retention and incident rehearsal.
- **Release evidence:** immutable image/commit, CI and smoke results, measured capacity, backup restore/DR, legal and named production sign-off.

A higher evidence tier is **not** inferred from a lower tier.

---

## 11. End-to-End Operational Event and Evidence Examples

### 11.1 Order Confirmation with a safe concurrent conflict

1. Two legitimate checkout requests compete for the last available item at the same eligible Location.
2. PostgreSQL atomic availability/reservation rules permit only one authorized confirmation. The other obtains an approved conflict state.
3. Technical logs show unique Request IDs and safe correlation and a non-error classification for the expected conflict.
4. The successful business operation's actual Order/Inventory records, required Audit and Outbox intent share the correct local commit.
5. Relay/consumer attempt(s) may retry with the original logical event identity, without a second reservation.
6. The incident or test report cites actual authoritative records. A `warning` for the losing request is not treated as evidence of overselling.

### 11.2 Stopped relay while Horizon looks idle

1. A confirmed Order has a committed Outbox intent.
2. Redis queue length is zero because the relay stopped scanning; Horizon seems idle.
3. Scheduler/relay **last progress** and **oldest unpublished event age** reveal lack of business progress.
4. Operator verifies committed intent and trustworthy Tenant scope, then resumes safe publication.
5. Consumer idempotency and actual Notification status prove whether work was processed and what provider evidence was observed; **no invented Delivered claim**.

### 11.3 Refund timeout with an uncertain provider outcome

1. An authorized refund attempt is initiated under an approved real Feature workflow.
2. The external/physical financial outcome cannot be established from the response alone.
3. Technical logs preserve safe IDs and timeout classification; owning state is marked/handled as **Uncertain** where the Feature permits.
4. No blind second refund is executed; operator reconciles original financial/provider evidence.
5. Required Audit and correction lineage record authorized later resolution. This standard **does not introduce an online payment/refund gateway to the COD-first Pilot**.

### 11.4 Revocation during a pending sensitive export

1. An authorized user requests an export; a bounded background job may be pending.
2. Membership/Role/Location authorization is revoked before execution or download.
3. The job revalidates the current actor/Scope and refuses unauthorized effect/disclosure.
4. Audit of access/revocation and privacy-safe diagnostic evidence are preserved; no old session or stale cache overrides current permissions.

### 11.5 Cross-Tenant worker sequence

~~~text
Worker attempt 1: Tenant A -> verified A DB -> safe logs -> cleanup
Worker attempt 2: Tenant B -> verified B DB -> safe logs -> cleanup
Worker attempt 3: Central  -> verified Central DB -> safe logs -> cleanup
Error/retry path: no retained A/B connection, actor, Location or logger context
~~~

Actual tests must prove this sequence even after exceptions and aborted transactions; a single mocked Tenant string is insufficient.

---

## 12. Consolidated Observability, Audit and Incident Test Matrix

| ID | Test / failure injection | Required evidence / result |
| --- | --- | --- |
| **LOG-T01** | JSON log on representative Laravel API error | Parseable structured event with safe timestamp, event name, level, environment, request/correlation where applicable |
| **LOG-T02** | Application exception across Controller/Action/handler | One primary diagnostic, no repeated full stack dump per layer |
| **LOG-T03** | Expected validation and missing resource | No unbounded Error-level flooding; correct API response |
| **LOG-T04** | Expected insufficient-stock conflict | Correct business conflict without claiming system corruption |
| **LOG-T05** | Actual duplicate business-effect detection | Critical incident signal, authoritative scope and safe containment |
| **LOG-T06** | Secret/Cookie/token/raw request data in error path | Not recorded or correctly redacted; no unsafe client response |
| **LOG-T07** | Client-supplied fake trace or Tenant ID | No trusted authorization or log-scope takeover |
| **LOG-T08** | Two HTTP retries to same valid operation | Distinct Request IDs; stable business identity; no duplicate effect |
| **LOG-T09** | Outbox publishes and Horizon retries same Event | Original correlation preserved; attempts distinct; owning idempotent outcome |
| **LOG-T10** | Tenant A → Tenant B → Central reused worker | Correct DB, logger and actor context after every success/exception |
| **LOG-T11** | Required Tenant Audit write fails inside transaction | Local business effect rolled back |
| **LOG-T12** | Central membership change | Central audit only where owning contract mandates |
| **LOG-T13** | Tenant Role/Location change | Tenant audit, current permissions and actor/target references correct |
| **LOG-T14** | Refund request/approval vs actual execution | Audit/financial record distinguishes stages, no fictitious money success |
| **LOG-T15** | Authorized stock/financial correction | Original record and linked correction retained |
| **LOG-T16** | Attempt to read/export Tenant B Audit from A | Denied, with no cross-Tenant data |
| **LOG-T17** | Revocation before queued export/download | Unauthorized deferred operation is prevented |
| **LOG-T18** | Ordinary failed Login burst | Controlled/aggregated security signal and no unbounded DB write storm |
| **LOG-T19** | PostgreSQL lock/deadlock or connections pressure | Accurate native statistics, useful safe diagnosis and no private bindings |
| **LOG-T20** | Redis failure / rejected writes | Visible service impact; durable PostgreSQL business/Outbox state not misreported |
| **LOG-T21** | Horizon idle while Outbox scanner stopped | Detected by scanner progress/oldest unpublished Event, not queue length alone |
| **LOG-T22** | Scheduler missed execution/overlap | Heartbeat/owner signal reports relevant anomaly |
| **LOG-T23** | DB down for one Tenant | Correct affected scope, Fail Closed, other Tenant context not used |
| **LOG-T24** | Blue-Green release overlap and incompatibility | Tracked resource/connection headroom, compatible rollback decision |
| **LOG-T25** | Large technical log volume | Actual rotation/copies/quota verified, no uncontrolled shared-VPS disk growth |
| **LOG-T26** | Container replacement / collector unavailable | Real persistence/gap behavior documented, recovery signal where possible |
| **LOG-T27** | Development/Production separation | No accidental shared log stream, access, secrets or data leakage |
| **LOG-T28** | Unauthorized operational log/audit export | Denied by appropriate operator/application boundary |
| **LOG-T29** | Incident evidence under approved hold | Not removed by ordinary log cleanup; access/audit trail protected |
| **LOG-T30** | Real Critical alert trigger in test environment | Intended owner/channel receives/verifies alert; not a simulated "sent" flag only |
| **LOG-T31** | Recovery drill with uncertain financial outcome | No blind retry, explicit reconciliation and correct closure status |
| **LOG-T32** | Privacy deletion/retention case | Minimizes allowed personal data without silently deleting legitimate business history |
| **LOG-T33** | Monitoring metric label volume / noisy Tenant | Bounded cardinality and no private cross-Tenant exposure |
| **LOG-T34** | One Tenant migration fails | Accurate partial rollout status; not a globally successful deploy |
| **LOG-T35** | Incident after worker/outbox recovery | Order/Stock/Collection/Refund/Tenant invariants verified, not just healthy containers |

### 12.1 Realism requirements

- Unit tests can verify formatter/redaction/severity/serializer behavior without a DB.
- Tenant/audit/transaction/concurrency tests need **real Central PostgreSQL + at least two physically separate Tenant PostgreSQL DBs**, with appropriate least-privilege roles and genuine transaction boundaries; shared SQLite/mock-only fixtures are insufficient.
- Queue/relay/context failure tests require relevant actual Redis/Horizon/Outbox path, including retries, transport interruption and reused workers.
- Frontend integration/e2e tests require actual Next.js/browser behavior in its future repository and the intended Host/Sanctum contract.
- Capacity, alert and resource tests run in an authorized representative non-Production environment with recorded workload/host settings and evidence. No destructive load/fault tests against active merchants without separate explicit authorization.
- Require issue/PR references for blocking failures, and re-run targeted regression after fixes. A passing log assertion is not proof that alert delivery or legally compliant retention exists.

---

## 13. Implementation Handoff — Foundation, Feature Specs and Operations

### 13.1 Suggested implementation order (not permission to code)

1. **Foundation contract:** define trusted execution scope and `X-Request-ID` / Correlation lifecycle, logging event registry, safe context creation/cleanup and selected Laravel JSON formatter.
2. **Exception boundary:** align Laravel API renderer and Horizon failure classification with Code/API Standards; redaction, severity and duplicate-report tests.
3. **Central/Tenant Audit primitives:** define Module ownership, permission/access contracts and minimal logical event schema; actual tables/migrations through reviewed Features and locally atomic writer paths.
4. **Runtime wiring:** select and validate container log path/driver, quotas, retention/rotation, Production vs Development separation and safe operator credentials.
5. **Business-progress metrics:** API/DB/Redis/Horizon plus independent Outbox/Scheduler heartbeat and oldest Event age, not only liveness.
6. **Security/alert policy:** identify actual Incident Owner, channel, severity and test real delivery in non-Production before Pilot.
7. **Feature adapters:** adopt relevant telemetry contracts in Orders/Inventory/Collections/Refunds/Notifications/Reporting as Features ship, without generic duplication.
8. **Recovery rehearsals:** safe Redis/worker/DB/migration/deploy failure injection, incident report template, actual Post-incident business checks.
9. **Measured capacity:** TEST-08 representative workloads, shared-VPS/Blue-Green headroom, revise numeric thresholds and budgets from evidence.
10. **Release readiness:** evaluate BA-30 gates independently, including **BA-16 decision and tested off-VPS restore** before any live merchant activation.

### 13.2 Proposed logical folders/contracts — illustrative

The following is an example of separation of responsibilities, **not evidence that these paths exist or a requirement to create precisely this layout**:

~~~text
Laravel application
  Core / HTTP middleware        trusted request and correlation context
  Core / Logging                formatter + redaction + severity policy
  Core / Observability          safe metric emission / diagnostic interfaces
  Core / Exception handling     safe API and worker failure boundary
  Central / Identity + Platform owning audit writers
  Tenant / Identity, Orders, Inventory, Collections, Refunds owning audit writers
  Infrastructure / Outbox       event correlation / relay progress
  Infrastructure / Queues       Horizon execution context, attempts and cleanup
  Operations / Runbooks         incident workflow and verification evidence
~~~

Avoid implementing a new cross-domain central write path that bypasses approved Module Public Contracts or Tenant transaction ownership.

### 13.3 Feature Spec checklist input

Every sensitive Feature's `spec.md` / `plan.md` / `tasks.md` should answer:

1. What exact authoritative business record/ledger represents success?
2. Which events require permanent Business/Security Audit and which are optional technical diagnostics?
3. Where does that Audit live: Central or the owning Tenant DB?
4. Which actor, permission, Location, approval, reason and before/after fields are necessary and privacy-safe?
5. Does the required Audit share the business transaction? If not, how is cross-context progression reconciled?
6. What business operation and idempotency identities survive retry?
7. Which request/correlation/event/job fields enable reliable diagnosis?
8. What are the correct error classes, API statuses, expected conflict and permanent/unknown outcome behavior?
9. What specific progress and error signals are emitted? How are they collected without high-cardinality labels?
10. What are the Access/Retention/Export/Erasure rules and approved legal/Feature exceptions?
11. What non-Production failure, concurrency, security and regression tests demonstrate the contract?
12. What remains **TO SPECIFY**, **PENDING VALIDATION** or a live release **BLOCKER**?

### 13.4 PR / Review checklist

- [ ] Approved Feature/D-series scope and Module ownership cited.
- [ ] Trusted execution context verified; no client-supplied tenant/log authority.
- [ ] Structured events use stable names, safe minimal diagnostic fields and correlation.
- [ ] Context clears on successful and exceptional worker/request paths.
- [ ] Secret/PII/Session/provider payload redaction covered by tests.
- [ ] Domain errors vs unexpected failures have consistent severity and truthful HTTP status.
- [ ] No redundant exception reporting and no uncontrolled hot-loop logging.
- [ ] Required Audit belongs to correct Central/Tenant owner, protected by the right local transaction.
- [ ] Audit access/export uses current effective permissions and deferred action reauthorization.
- [ ] Retry preserves logical business-effect identity and does not produce duplicate money/stock.
- [ ] Outbox/relay/Scheduler progress, not just Queue length, is observable where affected.
- [ ] Metrics avoid unbounded user/Order/Tenant labels and cross-Tenant leakage.
- [ ] Storage/rotation/log reader credentials and environment segregation reviewed.
- [ ] Tests include real Central + two Tenant DBs and Redis/Horizon when the boundary requires them.
- [ ] Capacity, numeric alarms and retention described as tested only with evidence.
- [ ] Error/incident recovery runbook links a real owner and verifies business invariants.
- [ ] Critical alert recipient/channel demonstrably works before Pilot.
- [ ] CI/PR report lists actual tests and results, unresolved failures and verified SHA.
- [ ] No unsupported claim of backups, disaster recovery, live Monitoring, release success or legal compliance.
- [ ] No merge to `main` or Production activation without explicit authorization and independent gates.

---

## 14. Pending Implementation Decisions, Measurement and Risk Register

| Item | Status | Where it must be resolved |
| --- | --- | --- |
| Laravel 13 JSON logging formatter/channel, logger context cleanup, Next.js server/browser error integration | **TO SPECIFY + PENDING VALIDATION** | Foundation, frontend implementation, actual runtime tests |
| Event name/field schema, correlation ID creation/propagation format, redaction tooling | **TO SPECIFY** | Foundation and Feature contracts |
| Business/security audit table shape, indexes, append permissions, before/after minimization | **TO SPECIFY** | Owning Central/Tenant Feature Specs and DB migrations |
| Which exact events get permanent Audit and which are aggregate security signals | **TO SPECIFY per Feature** | Identity, Orders, Pricing, Inventory, Collections, Refunds and Support Features |
| Operator access and private log/audit export permissions | **TO SPECIFY/TEST** | Authorization and infrastructure security reviews |
| Container logging driver, stdout/files, persistence, rotation, per-service quota | **TO SPECIFY + TEST** | Actual Coolify/Docker runtime and host capacity |
| Technical retention days, incident holds, Audit legal/financial lifecycle | **TO SPECIFY / LEGAL REVIEW** | SEC-09, relevant Features, Egypt Pilot legal gate |
| Metric collector and visualization/alert tools (if warranted) | **NOT SELECTED** | Operational evaluation after available native tooling assessed |
| SLOs, alert thresholds, per-service/queue/connection budgets, worker counts | **PENDING MEASUREMENT** | TEST-08/INF-11 representative load tests |
| Named operators, working alert channel, response runbooks and escalation timing | **TO SPECIFY + TEST** | Operations before Pilot; no invented 24/7 team |
| Real Laravel 13/Stancl/Redis/Horizon setup, worker context cleanup, PostgreSQL grants and isolation | **PENDING IMPLEMENTATION/VALIDATION** | Foundation and integration tests |
| Production/Development separate monitoring, volume, secrets and actual release readiness | **PENDING VALIDATION** | INF-09/BA-30 gates |
| **BA-16 Backup & Restore design** (off-VPS backups, tested full/per-Tenant restore, RPO/RTO) | **DEFERRED; G3 BLOCKED** | Separate explicit architecture decision and restore drills before live Pilot |
| **D-201** alternative fulfillment Location follow-up indicator | **OPEN** | Orders/Fulfillment business decision; this document does not choose |
| Legal/privacy/tax applicability to merchants and live channels | **REVIEW REQUIRED** | BA-30 G7, not established by telemetry design |

### 14.1 Known architectural risks to validate

- Shared VPS disk/IO contention between logs, PostgreSQL and file mounts.
- Monitoring blind spots when scanner or scheduler stops but Horizon is idle.
- High-cardinality Tenant/resource metric labels causing cost or memory pressure.
- Worker logger/request context leaking Tenant A into B/Central.
- Misleading log severity or 200 health statuses masking financially incorrect outcomes.
- Unguarded retries after uncertain financial results.
- Inaccessible incident evidence after container replacement or misconfigured rotation.
- Required Audit not atomically committed, or audit exports revealing other Tenant/Location records.
- Missing real alert recipient, ineffective escalation or impossible recovery criteria.
- No validated off-VPS recovery plan while BA-16 remains deferred.

These risks are **not claimed to have occurred**; they are verification targets and pre-Pilot control gaps.

---

## 15. BA-30 Live Pilot Gates — This Document Does Not Satisfy Them

| Gate | Relevant evidence required | Status conveyed by this document |
| --- | --- | --- |
| **G1 Tenant safety** | Cross-Tenant/Location isolation across Logs, Audit, API, worker, export and DB roles | **NOT VERIFIED** |
| **G2 Commerce consistency** | Actual concurrent stock/money tests and duplicate-effect protection | **NOT VERIFIED** |
| **G3 Backup & Restore** | Explicit BA-16 approval, off-VPS Central + Tenant + file restore drills, measured RPO/RTO | **BLOCKED — BA-16 DEFERRED** |
| **G4 Releases** | Tested CI/CD, readiness, worker/scheduler drain, compatible Blue-Green/rollback | **NOT VERIFIED** |
| **G5 Capacity/NFRs** | Representative latency, DB/Redis, Horizon/Outbox, storage/IO, alert thresholds and SLO evidence | **PENDING VALIDATION** |
| **G6 Security & Privacy** | Protected logs/audit, safe incident response, real redaction/access/recovery tests | **NOT VERIFIED** |
| **G7 Legal readiness** | Actual Egyptian merchant/channel-specific data/privacy, tax/e-receipt obligations review | **LEGAL REVIEW REQUIRED** |
| **G8 Operational completeness** | Real enabled journeys, live-safe reconciliation/notification/reporting and bilingual UX tests | **PENDING FEATURE ACCEPTANCE** |
| **G9 Environment separation** | Production/Development DB/Redis/files/logs/secrets/resource isolation | **NOT VERIFIED** |
| **G10 Explicit release approval** | Named release owner, documented go/no-go and reviewed deployment decision | **APPROVAL REQUIRED** |

**Important:** Approval to write standards, scaffolding or tests can occur while BA-16 is deferred. **No merchant live data/workflow activation is authorized while G3 is blocked**; passing other checks does not override this.

---

## 16. Final Approved Position and Publication Boundary

**Approved and documented on 2026-11-21:** **LOG-01…LOG-08 Option A** as listed in Section 2. The engineering baseline is **structured, privacy-safe Laravel/Next.js diagnostics; distinct trusted request/correlation and business-effect references; Central/Tenant domain-owned and transactionally correct Audit; risk-based severity and exception ownership; lightweight metrics with actual Outbox/Scheduler progress; controlled retention and privileged access; owner-led Incident Response and evidence-based commerce recovery**.

**This publication permits only creation of this documentation file on `development`**, preserving the existing branch/repository workflow. It does **not** imply:

- Composer/npm installation, migrations, implementation, CI/branch protection, new providers, metrics dashboards or an operational incident team.
- Verified package integration, alert routing, performance SLO, legal retention, secure Production logging or completed recovery drill.
- Approval of BA-16, resolution of D-201, BA-30 gate closure, any deployment, Promotion PR or merge to `main`.

Future implementation must record actual test results, configuration, owning decisions and release evidence separately. Any divergence from the governing D-series decisions or existing architecture is raised for deliberate review, not silently adopted.

**Next documentation sequence, subject to separately authorized work:** Integration Standards, then Localization Standards, then a consolidated Backend Architecture/Standards review before separately planned Frontend Architecture and Spec Kit Foundation implementation.
