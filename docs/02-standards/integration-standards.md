# E-commerce SaaS V2 — Integration Standards

**Repository:** Elmo-Tech/ecommerce_v2_api  
**Target path:** docs/02-standards/integration-standards.md  
**Branch:** development  
**Version:** 1.0 — Approved Integration Engineering Standards  
**Prepared:** 2026-11-21  
**Decision status:** **APPROVED — INT-ST-01 through INT-ST-10, Option A**. The owner approved each of INT-ST-01…INT-ST-09 individually and expressly authorized **INT-ST-10 Option A**, this comprehensive document and publication on development.  
**Implementation status:** **DOCUMENTATION ONLY — NOT IMPLEMENTED / NOT TESTED / NOT CONFIGURED / NOT DEPLOYED.**

> **Mandatory reading rule:** These are engineering requirements for future Feature Specs and implementation. Approval is **not** evidence that an Email Provider, sending domain, webhook, Outbox, Laravel/Horizon application, PostgreSQL schema, Next.js polling client, CI, alert, Production environment or live customer communication exists or works. **BA-16 Backup & Restore remains DEFERRED; BA-30 G3 remains BLOCKED for real Merchant activation; D-201 remains OPEN.** No Production promotion or live Pilot authorization follows from this file.

---

## 1. Purpose, Authority, Scope and Reading Rules

### 1.1 Purpose

Define practical, enforceable and testable integration standards for the approved **Laravel 13 API-first Modular Monolith** and independent **Next.js** application, using **Central PostgreSQL plus one independent PostgreSQL database per Merchant Tenant**, Redis, Laravel Horizon, a transactional Outbox and the approved Coolify deployment architecture.

The standard translates nine approved engineering decisions plus a final review/publication decision into responsibilities for Features, developers, reviewers and release operators:

1. **INT-ST-01:** narrow Provider Adapters and owning-Module integration boundaries.
2. **INT-ST-02:** runtime-only secrets, separate environments, least privilege and rotation.
3. **INT-ST-03:** explicit provider contracts, result classification, timeouts and safe retries.
4. **INT-ST-04:** authenticated durable Webhook intake, trusted routing and idempotent processing.
5. **INT-ST-05:** versioned events, durable logical intents, message-level idempotency and delivery evidence.
6. **INT-ST-06:** system-managed bilingual templates, safe variables and Merchant branding.
7. **INT-ST-07:** database-backed In-App notifications, bounded polling and current authorization.
8. **INT-ST-08:** lightweight monitoring, fair rate budgets, provider failure isolation and safe recovery.
9. **INT-ST-09:** risk-based tests, real failure/tenancy evidence, capacity and release gates.
10. **INT-ST-10:** cross-document review, open-item handoff and documentation-only publication.

**Every applicable Feature** must translate these policies to its own reviewed Spec Kit **spec.md**, **plan.md**, **tasks.md**, contracts, tests and operational evidence. The requirements are not an instruction to implement all possible integration infrastructure immediately.

### 1.2 Source-of-truth and authority

| Reference | Binding purpose |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | OV/MVP scope, Egypt-first closed Pilot, approved customer messaging and exclusions |
| [Backend Architecture](../01-architecture/backend-architecture.md) | BA-09 Redis/Horizon, BA-28 provider-agnostic integrations, BA-16 DEFERRED, BA-30 G1–G10 |
| [Integration & Notifications Architecture](../01-architecture/integration-architecture.md) | **INT-01…INT-08**, approved notification/integration topology, sending, webhook, delivery and security boundaries |
| [Module Architecture](../01-architecture/module-architecture.md) | One Writer, owning Modules, Public Contracts, Domain vs Published Events, Feature-level Spec Kit |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Trusted Central/Tenant context, independent databases/credentials, worker cleanup and fail-closed isolation |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | Central workforce membership, Tenant-local roles/Locations, Customer scope and revocation |
| [Transactions & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | TC-01…TC-08, local ACID, idempotency, Outbox and unknown-outcome reconciliation |
| [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | QJ-01…QJ-08, shared Redis/Horizon, durable relay, at-least-once, retry safety and fair workloads |
| [Infrastructure Architecture](../01-architecture/infrastructure-architecture.md) | INF-01…INF-12, Coolify secrets, Dev/Prod separation, GHCR images, release/worker safety |
| [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) | Redis transport/capacity, prefix and context isolation; cache is not authoritative business evidence |
| [API Standards](./api-standards.md) | AS-01…AS-10, versioned Laravel APIs, errors, current auth, HTTP idempotency and OpenAPI |
| [Authentication Standards](./authentication-standards.md) | Sanctum first-party Session/CSRF, Central/Tenant identity/session boundaries |
| [Authorization Standards](./authorization-standards.md) | Current membership, Tenant role/Location/resource, revocation and restricted Platform support |
| [Security Standards](./security-standards.md) | SEC-03 input trust, SEC-05 runtime secrets, SEC-07 incident response, SEC-08 abuse protection, SEC-09 privacy |
| [Testing Standards](./testing-standards.md) | TEST-03/05/06/08/09, real DB isolation, contracts, concurrency, fault injection, CI evidence |
| [Logging, Audit & Observability Standards](./logging-audit-observability-standards.md) | LOG-01…LOG-08, technical correlation, safe evidence, alerts, retention and recovery runbooks |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 OPEN and unresolved product/operational detail |
| [Repository Workflow](../WORKFLOW.md) | Reviewed work on development, independently authorized promotion to main |

**Precedence:** approved D-series business decisions control business truth; approved OV decisions control product scope; BA/MA/INT/TC/QJ/INF architecture controls system boundaries; these INT-ST decisions specify engineering practice. Feature Specs, OpenAPI, actual provider contracts and validated measurements choose implementation details **only within those boundaries**. Record any real conflict and obtain an explicit owner decision; never silently rewrite earlier approvals.

### 1.3 First MVP scope

**Included when a particular Feature authorizes the actual trigger:**
- Transactional customer email on a real, committed business event.
- One **platform-managed** transactional Email Provider through a narrow replaceable adapter.
- System-owned, versioned Arabic/English templates with safe Merchant branding.
- PostgreSQL notification intent/attempt/evidence history, durable Outbox and Horizon processing.
- Authenticated provider callbacks when the selected service actually provides relevant evidence.
- Tenant-local staff In-App notifications; optional separately approved Central-only operational alerts.
- Controlled operational monitoring, recovery and safe notification exceptions.

**Deferred / not selected:**
- WhatsApp, SMS, social messaging, marketing automation, CRM campaigns or bulk promotional sending.
- Online payment gateway, carrier/shipping API, automatic SaaS billing, or automated externally executed refunds.
- Merchant-managed SMTP keys, custom per-Merchant sending domain, Merchant HTML template editor.
- WebSocket/Reverb push, browser push, dedicated Notification microservice.
- Generic ESB/integration gateway, multi-provider failover router, Kafka, all-channel framework or generalized Saga engine.
- Additional production infrastructure, paid Monitoring/SIEM vendor, compulsory Vault, fixed SLO, retention or capacity numbers.

**An adapter interface, conceptual event or example is not approval for a deferred Product Feature.** No notification itself executes stock, order, collection or refund state transitions.

### 1.4 Status and evidence vocabulary

| Status | Meaning |
| --- | --- |
| APPROVED | Owner accepted the engineering choice; not proof that code exists |
| TO SPECIFY | Detailed Feature contract, field, route, table, owner, message trigger or configuration is undecided |
| PENDING VALIDATION | Real provider capability, compatibility, deployment behavior, legal review or measured thresholds need evidence |
| NOT RUN | Applicable test was not executed; must not be reported Passed |
| PASSED / FAILED | Recorded observation against exact revision, environment, command and evidence |
| DEFERRED | Explicit decision not selected; not permission to waive mandatory Pilot readiness |
| BLOCKED | Cannot proceed past the relevant gate without decision, correction and real evidence |

---

## 2. Approved Decision Register and Architecture Crosswalk

| Decision | Approved Option A | Primary binding references |
| --- | --- | --- |
| **INT-ST-01** | Lightweight Provider Adapters + Module-Owned Business Workflows + Reuse Outbox/Horizon | INT-01, BA-28, MA, TC-04, QJ |
| **INT-ST-02** | Coolify Runtime-Only Secrets + Environment Isolation + Least Privilege + Safe Rotation | INF-08, SEC-05, INT-02 |
| **INT-ST-03** | Typed Provider Contracts + Classified Outcomes + Explicit Timeouts + Bounded Safe Retries | INT-01/07, TC-06, QJ-04 |
| **INT-ST-04** | Verified Durable Webhook Intake + Trusted Tenant Correlation + Idempotent Async Processing | INT-06, SEC-03/05, TC-05 |
| **INT-ST-05** | Versioned Published Events + Durable Message Intent + PostgreSQL Dedup + Attempt/Evidence History | INT-04/05, MA, TC-04, QJ-05 |
| **INT-ST-06** | Platform-Owned Bilingual Versioned Templates + Allowlisted Variables + Safe Merchant Branding | INT-03, SEC-03, OV-10 |
| **INT-ST-07** | PostgreSQL In-App Notifications + Bounded Next.js Polling + Current Recipient Authorization | INT-05, IA, AUTHZ |
| **INT-ST-08** | Lightweight Monitoring + Provider Isolation + Global/Tenant Fairness + Retry-Storm Prevention + Recovery | INT-07, QJ-06/07, LOG-05/07 |
| **INT-ST-09** | Risk-Based Unit/Contract/Real Integration/Security/Failure/Load Tests + Release Gates | INT test matrix, TEST, SEC, BA-30 |
| **INT-ST-10** | Full Cross-Document Review + Checklist + Open-Item Handoff + Controlled Single-File Publication | All above; documentation only |

**Do not confuse the identifiers:** **INT-01…INT-08** name earlier *Architecture* decisions, while **INT-ST-01…INT-ST-10** name these *Engineering Standards*. INT-ST refines rather than supersedes INT.

### 2.1 Five non-negotiable invariants

1. Only an owning Business Module can authoritatively change its order, stock, collection, fulfillment or refund state; a provider adapter, email callback or notification consumer cannot.
2. **Local PostgreSQL business change plus required Outbox intent** commits or rolls back together in its **own authoritative database**, with no cross-Central/Tenant distributed ACID.
3. **Provider Accepted is not Delivered**, and external exactly-once delivery is **not** guaranteed. A timeout can leave an outcome **Uncertain**.
4. A callback-supplied Host, recipient, tenant_id or arbitrary identifier is **never** the trust anchor for selecting a Merchant database.
5. A previously created employee notification or cached permission is **not** permanent authorization after membership, Role, Location or resource permission changes.

### 2.2 Minimum logical path

~~~text
Owning Business Module / real committed fact
  -> Same-authoritative-DB Transactional Outbox
  -> Bounded durable Relay / Scheduler
  -> Shared Redis / Laravel Horizon with trusted scope
  -> Notifications Module and stable Message Intent (Tenant-owned)
  -> Narrow Provider Adapter using platform-managed credentials
  -> One selected transactional Email Provider
  -> Verified Webhook receipt + minimal Central correlation
  -> Tenant-owned Delivery Evidence and effective status
~~~

For approved In-App alerts, Notifications persists Tenant-local recipient state; authorized Laravel APIs serve the independently deployed Next.js dashboard through bounded polling. No internal HTTP microservices, new event broker or additional database are introduced.

---

## 3. INT-ST-01 — Integration Strategy, Ownership and Module Boundaries

### 3.1 Explicit responsibility map

| Owner | May do | Must not do |
| --- | --- | --- |
| Orders / Inventory / Fulfillment / Returns owning Modules | Validate and commit real business events under their Public Contracts | Ask an email adapter to decide business eligibility; let notification failure reverse an authorized commit |
| Application Orchestrator | Coordinate required in-process, single-DB actions/transaction and approved post-commit intent | Open a new Tenant DB mid-transaction, create partial hidden commits or assume distributed ACID |
| Published Event producer | Publish a reviewed, versioned minimal business fact after durable commit | Expose all internal Domain Events or serialize unrestricted internal Eloquent models |
| Notifications Module | Resolve approved trigger, recipient, channel, locale, intent, attempts, In-App state and delivery evidence | Directly mutate another Module's private tables or infer sending authorization from raw payload |
| Technical Email Adapter | Validate transport contract, submit, normalize results, verify/parse provider-specific callbacks | Own Orders business state, persist authoritative stock/money facts or bypass Notifications |
| QJ/TC shared infrastructure | Relay, queue, scoped retry, durable claim, consumer idempotency and diagnostics | Build a second parallel email-specific global event bus |
| Operational owner | Review scoped diagnostics and authorized recovery | Retry all unknown external effects or read all Merchant customer correspondence by default |

The **One Writer Principle** is mandatory even when a workflow spans multiple business Modules: use their **provider-owned Public Contracts** and a reviewed owning Application Orchestrator. Contracts expose only required DTOs/value objects, declared errors and transaction/authorization semantics, not private models/controllers/repositories.

### 3.2 Synchronous versus asynchronous

| Work | Correct boundary | Reason |
| --- | --- | --- |
| Price/eligibility check | In-process synchronous Public Contract | Must be authoritative before order decision |
| Full eligible stock reservation | In-process protected local PostgreSQL transaction | Must prevent oversell, partial confirmation and multi-Location fabrication |
| Collection eligibility / verified money state | Synchronous authoritative Module contract | Email or queued event cannot prove financial settlement |
| Transactional Email | Post-commit Outbox → queue → Notifications | Provider outage must not invalidate saved business fact |
| Staff In-App alert | Durable notification effect after actual event | Offline recipient and recoverable work |
| Noncritical projection | Async where independently approved | Safe eventual update, no required synchronous invariant |

Do not make internal HTTP calls between Modular Monolith Modules. Do not invoke external provider APIs while holding long-lived Order/Inventory database transactions. A transactional Outbox is the required durable bridge for approved critical post-commit notifications; an in-memory after-commit callback alone cannot cover the crash gap.

### 3.3 Minimal adapters, not generic frameworks

- Define a **small provider-facing interface** for actual transactional email needs; use the framework's supported HTTP/Mail/SDK transport underneath.
- Map provider-specific response codes, IDs, callbacks and errors into a narrow, reviewed internal result type.
- Keep provider SDK/client classes out of Orders, Fulfillment, Inventory and other business domains.
- Use a Fake/Test Adapter for fast deterministic tests; add real provider sandbox/contract tests once a provider is selected.
- Introduce another method, field or abstraction only for a verified, approved need; no speculative payment, shipping, WhatsApp or multi-provider routing interfaces in MVP.
- Provider replacement is **not** assumed zero-effort: verify authentication, callback, sender, quotas, idempotency and delivery-evidence differences.

### 3.4 Cross-context boundary

Merchant email intent, attempts and delivery history belong in the **owning Tenant DB**. Central platform notifications, when approved, belong in **Central DB**. Minimal Central routing/receipt records are **technical correlation**, not an unrestricted copy of Merchant order/recipient/message content. Distinct Central/Tenant commits need durable workflow stages and reconciliation; a single global transaction across databases is prohibited.

---

## 4. INT-ST-02 — Configuration, Secrets, Environments and Credential Rotation

### 4.1 Runtime-only custody

| Credential/configuration | Approved location/policy |
| --- | --- |
| Platform-managed Email API/SMTP secret | **Coolify runtime-only protected secret**, restricted to required Laravel processes |
| Provider webhook verification secret | Protected runtime secret for the validating intake runtime |
| Sender/transport nonsecret settings | Reviewed Laravel config sourced from approved environment values |
| Tenant business message data | Own Tenant PostgreSQL; do not store platform provider credentials per Tenant |
| Central provider-to-Tenant routing references | Minimal secured Central records; opaque refs, not plaintext secrets or full customer archive |
| Tenant DB access credentials | Protected per-Tenant secret resolution; Central directory holds opaque secret refs |
| Next.js public variables | Public-only configuration, never provider keys, database secrets or internal signing secrets |
| GitHub Actions / GHCR credentials | Scoped CI/registry rights; no automatic access to Production email or DB secrets |
| Provisioning/migration credentials | Separately privileged execution, not regular API/worker runtime |

Coolify can expose variables to build and runtime depending on configuration. **Explicitly disable build-time exposure** for runtime secrets and verify Docker build output/layers, image metadata, logs and Next.js client/SSR boundaries. Do not commit real .env, export secrets into diagnostics or place keys in event payloads.

### 4.2 Distinct resources and least privilege

- **API**, **Horizon** and **Scheduler** may be distinct Coolify applications using a common Laravel image. Share only necessary nonsecret configuration and give each runtime only the specific secrets it uses.
- Verify required values do not silently drift across processes; do **not** grant every process all external provider privileges simply because images are shared.
- **Development** is the current environment. Use sandbox/test transport or controlled test recipients, with no live Merchant-customer sending.
- **Production** is **not provisioned** by this architecture. It will require separate domains, provider keys, callback config, DBs, queues, file volumes, restricted access and release approval.
- Keep environment-specific Laravel APP_KEY stable across concurrent compatible processes and deployments, and different between Development and future Production; APP_KEY rotation is a separate controlled action.
- Never fall back from a missing Tenant/provider credential to Central, a prior Tenant, another Merchant or an unreviewed default account.

### 4.3 Sender identity and provider selection

The MVP uses **one platform-controlled transactional Email Provider**, but **its vendor, API-versus-SMTP method, sending domain/address, reply-to, DNS, quotas, region and commercial/data terms remain unselected/unverified**. Before live use verify platform-owned sender identity and actual SPF/DKIM/DMARC alignment, complaint/bounce controls, TLS, callback authenticity/idempotency evidence, provider retention and terms. Merchant branding does not authorize spoofing a Merchant From-domain.

### 4.4 Rotation and compromise

1. Confirm affected credential, environments, owner, operations and possible exposure; preserve safe incident evidence.
2. Revoke/rotate using provider-supported overlap or interruption controls as applicable.
3. Update protected runtime configuration only for authorized processes and safely restart/drain workers where required.
4. Invalidate stale client connections/tokens; test failed-old and successful-new credential behavior without making speculative duplicate sends.
5. Confirm webhook signature validation and provider callback continuation after rotation.
6. Audit operator, scope, reason, timing, changes and final validation. Define credential-specific rotation cadence at implementation, not one invented universal day count.

**INT-ST-02 acceptance:** no secret in Git/images/build/client/queue payloads/logs; separate Dev/Prod credentials; correct runtime-only control; least-privileged API/Horizon/Scheduler; failed key lookup safely denied; controlled rotation and incident evidence.

---

## 5. INT-ST-03 — Provider Contracts, Timeouts, Result Classification and Retries

### 5.1 Narrow typed transport contract

An outbound email adapter accepts only validated, approved logical message data:

| Input concept | Required meaning |
| --- | --- |
| Stable message/attempt identity | Existing authorized notification intent, not raw Order ID alone |
| Trusted execution scope | Verified Central/Tenant reference; never client-selected Tenant authorization |
| Approved recipient/channel | Checked by Notifications policy and owning Feature |
| Template identity/version and locale | Compatible system-owned content contract |
| Minimal rendered/structured payload | Explicit allowlist and safe encoding |
| Correlation/operation references | Diagnostics distinct from HTTP Idempotency-Key and Event ID |
| Provider idempotency reference | Only where actual provider supports it; no universal guarantee |

The adapter returns a **normalized outcome**, provider reference when reliably available, safe classified error and bounded timing/rate metadata. Method signatures, DTO/Enum names and SQL fields remain **TO SPECIFY** under the actual Feature.

### 5.2 External outcome classes

| Class | Correct interpretation | Action |
| --- | --- | --- |
| Accepted | Provider accepted responsibility to process | Store provider acceptance; **not Delivered** |
| Rejected / permanent | Confirmed nonacceptance or invalid recipient/config | Stop futile attempts; apply relevant exception/suppression policy |
| Confirmed-safe transient | Known retryable failure with trustworthy evidence of no acceptance | Bounded same-intent retry |
| Rate limited | Provider throttled request | Respect actual Retry-After/quota, delay/jitter and concurrency caps |
| Uncertain | Timeout/disconnect/crash where acceptance may have happened | Persist uncertainty and reconcile; **do not blindly resend** |
| Delivered | Authenticated provider proof of delivery | Store evidence; does not prove recipient read it |
| Bounced / Complaint | Trustworthy negative delivery/reputation signal | Preserve event history and apply reviewed suppression/follow-up |

A TCP connection failure demonstrably before request submission may be retryable; a timeout after possible submission **cannot automatically be treated as proof of nonacceptance**. Classify from the actual transport and provider behavior, not an arbitrary broad exception catch.

### 5.3 Timeout and job-budget discipline

- Explicit **connect timeout** and **total request/response timeout** for the actual HTTP/SMTP/SDK transport.
- **Horizon Job timeout** must account for bounded external waits, local persistence and safe termination behavior.
- Configure queue **retry_after** with headroom beyond expected worker/job timeout as required by the QJ baseline; minimize overlapping execution but never assume it provides business deduplication.
- Define per-Job tries, backoff, jitter, retry window and operational escalation by failure class.
- Use secure TLS verification; do not “fix” connectivity by disabling certificate checks.
- Exact seconds/attempt counts, provider concurrency and SLO budgets remain **PENDING MEASUREMENT AND PROVIDER VALIDATION**.

### 5.4 Idempotency and legitimate repetition

Each retry of an uncertain/failed attempt uses the **same logical message intent**. A separately permitted manual re-send or genuinely new Order transition must have its own approved operation/identity and remain possible. A single PostgreSQL constraint cannot atomically guarantee provider-side exactly-once email delivery. If provider supports request idempotency or lookup, use its validated semantics; otherwise explicitly reconcile or escalate unresolved acceptance.

### 5.5 Anti-patterns

- Calling provider SDK directly from an Order Controller, inside an open stock transaction or from arbitrary Eloquent hooks.
- Generic retry on all Exceptions, 4xx or timeouts without classifying acceptance.
- Treating HTTP 200 from provider as proof customer delivery.
- Returning generic success for a failed transport if no durable record exists.
- Unbounded logging of raw request/response bodies, recipient details or API tokens.
- Universal numeric timeout/backoff defaults asserted without measured constraints.

---

## 6. INT-ST-04 — Secure Webhook Intake, Durable Routing and Replay Protection

### 6.1 Mandatory inbound path

~~~text
Provider over HTTPS
  -> Dedicated Webhook route: bound payload + provider-specific authenticity checks
  -> Minimal verified durable Central receipt BEFORE positive acknowledgement
  -> Trusted correlation mapping from recorded outbound send/attempt
  -> Async scoped worker / owning Tenant DB verification
  -> Verified delivery event history and derived status
  -> Mark processed or durable pending/quarantined reconciliation
~~~

Provider-specific authentication, signing algorithm/header, timestamp tolerance, event identifier, retry contract and exact response code **cannot be selected before choosing the provider**. Authenticate/validate the actual raw request representation as the chosen protocol requires; apply body/type/size/depth limits, TLS and appropriate anti-replay checks.

### 6.2 Receipt-before-acknowledgement rule

1. Validate route/provider identity, signature/authentication, structural schema and permitted event class.
2. Persist **minimal verified receipt** durably. Record safe identity/provenance, arrival time and processing stage.
3. Only then send the provider-appropriate successful acknowledgement, without waiting for an unbounded Tenant DB scan or long worker execution.
4. Async processing must reconstruct verified scope and locate one registered outbound logical send/attempt.
5. Update owning Tenant history under appropriate unique/idempotent transaction guards.
6. On downstream failure, keep recoverable receipt and safe error class for bounded retry/reconciliation.

**Failure to persist a receipt must not produce a false acknowledgement**. Reject malformed/unauthenticated requests safely. Do not conflate a verified duplicate provider resend with a malicious unauthenticated replay; both must cause no duplicate effect, but the protocol-appropriate HTTP response may differ.

### 6.3 Trusted Central correlation and Tenant safety

A protected Central technical index may map **platform-controlled message/provider submission references** to **opaque Tenant and Tenant-owned intent/attempt references**. It must:

- Route only on a **previously registered outbound send** or another proven trusted reference; callback-provided tenant_id, recipient, Host, email address, or arbitrary metadata do not establish authority.
- Confirm provider identity, correlation scope, exact relevant attempt and Tenant DB state before mutation.
- Never search every Tenant DB for a recipient on each callback.
- Keep central receipt/routing data minimal; **no centralized customer email-body archive or unrestricted customer/Order replication**.
- Quarantine/reject unmatched/ambiguous references instead of guessing or falling back to default/Central/another Tenant.
- Handle early callbacks that arrive before provider reference persistence, without dropping verified evidence or falsely routing it.
- Handle Central receipt success followed by Tenant update failure as **two independent local commits**, with durable retry-safe stages and reconciliation; never promise distributed ACID.
- Enforce scoped operational access, safe indexes, event-retention policy and incident diagnostics.

### 6.4 Duplicate and out-of-order events

Use provider-scoped trustworthy event identity, plus the registered send/attempt identity and appropriate unique/guarded records, to avoid repeated effects. Do not deduplicate every event merely by Order ID, email or latest status. A Delivered event arriving before an older Accepted callback does not become Accepted again. Late Bounce/Complaint is preserved and evaluated from meaning/provenance, not naïve last-arrival-wins. Unknown/malformed events never authorize an Order, stock, refund or Tenant-state mutation.

### 6.5 Webhook threat matrix

| Threat | Required control |
| --- | --- |
| Forged signature/auth headers | Reject before durable business mutation |
| Valid request replayed | Provider-appropriate anti-replay plus idempotent no-op, without breaking legitimate delivery retries |
| Forged Tenant reference in payload | Ignore as routing authority; use trusted registered correlation |
| Callback to another Tenant's Provider Message ID | Match owning attempt and actual Tenant data, deny mismatches |
| Oversized/malicious nested payload | Bounded parsing and input allowlist; safe error |
| Duplicate concurrent callback | PostgreSQL uniqueness/atomic guards |
| Callback before correlation saved | Durable pending/unmatched and eventual reconciliation |
| Central receipt saved but Tenant unavailable | Retry-safe state; no false processed status |
| Secret missing or rotation overlap | Fail closed; verified planned rotation procedure |
| Request wants to mutate commerce state | Deny; callback may affect delivery evidence only |

---

## 7. INT-ST-05 — Published Events, Transactional Outbox and Contracts

### 7.1 Event classes and publishing threshold

| Category | Definition | Allowed audience |
| --- | --- | --- |
| Internal Domain Event | Internal representation of a domain occurrence | Owning Module; not automatically public |
| Published Integration Event | Explicitly reviewed, versioned report of a completed fact | Approved cross-Module consumers |
| Technical/Operational Event | Relay/job/connection/health lifecycle | Operational diagnostics, not business truth |
| Provider Callback Event | Verified external transport evidence | Notification delivery/evidence owner only |

Do not publish notification intents for events that have **not actually committed**. Example event types are illustrative, not the approved final trigger matrix; owning Features decide recipient/channel applicability and business-accurate wording. D-201 remains OPEN and does not justify a new automatic alternative-location notification or an automatic Order confirmation.

### 7.2 Published event envelope

The final serialization is **Feature TO SPECIFY**, but the reviewed contract includes:

- Unique **Event ID**, declared **Event Type**, **Schema Version** and **Occurred At**.
- Trusted execution scope **Central or Tenant**; verified Tenant reference where applicable.
- Owning aggregate/business operation reference (not arbitrary untrusted record ID).
- Safe **Correlation ID** for tracing distinct from HTTP X-Request-ID and API Idempotency-Key.
- Minimal, allowlisted payload and documented semantics/error handling.
- Producer, intended consumer and applicable permission/context constraints as the Feature requires.

No raw Session Cookies, tokens, provider secrets, unrestricted Customer objects, complete financial ledgers or arbitrary private model serialization. Consumers fetch additional authorized data through owner Public Contracts if needed.

### 7.3 Same-database atomic Outbox

For Tenant commerce operations, store the business fact and required Outbox row inside **one Tenant PostgreSQL transaction**. For Central-owned operations, store both in the relevant Central transaction. **Rollback removes both**. A post-commit scheduled bounded scanner/relay publishes to Redis/Horizon; durable claims/leases, scanner health and bounded Tenant scans follow QJ-03. Optional wake-ups can improve latency, not replace the durable recovery path.

~~~text
Transaction rollback    -> No business state and no corresponding outbox intent
Transaction commit      -> Both business state and outbox intent durable
Redis down after commit -> Outbox retained, relay re-discovers later
Publish then crash      -> Duplicate delivery possible; consumer idempotency required
~~~

A Redis acknowledgement proves queue transport acceptance, not consumer completion, provider acceptance or customer delivery. No exactly-once external guarantee, global FIFO, Kafka or enterprise distributed transaction coordinator.

### 7.4 Event versions and release compatibility

- Compatible optional additions may be accepted when documented and old consumers tolerate them.
- Breaking payload/meaning changes need explicit contract versioning, consumer migration, tests and controlled overlap.
- Do not rely solely on timestamps to order required business transitions; choose per-aggregate sequencing/version checks where a Feature demonstrates need.
- During approved Blue-Green overlap, old/new API, Horizon, Relay and callbacks must remain compatible with retained event/template versions.
- If an older release cannot safely consume queued data, the release is **BLOCKED until a safe migration/operational plan exists**.

---

## 8. INT-ST-05 — Logical Message Intent, Attempts and Delivery Evidence

### 8.1 Three independent identities

| Identity | Purpose | Not interchangeable with |
| --- | --- | --- |
| Published **Event ID** | A committed business fact and cross-Module processing identity | Email Message ID, HTTP Request ID |
| **Logical Message Intent** | One authorized communication for purpose/channel/recipient/source event | Each physical provider attempt |
| **Delivery Attempt / Provider Reference** | One submission attempt and its external correlation/evidence | Original business transaction |

A sensible business uniqueness basis is **trusted Central/Tenant scope + approved source Event/Operation + notification purpose + channel + logical recipient**, with stable IDs and PostgreSQL protection. The actual key/schema is Feature/Data TO SPECIFY; not all notifications may use the same shape. A recipient email address or Order ID **alone** is an unsafe general dedup key.

### 8.2 Persistence ownership

| Durable record | Authority/placement |
| --- | --- |
| Tenant customer/staff notification intent | Own Tenant DB |
| Tenant delivery attempts and verified provider events | Own Tenant DB |
| Recipient-specific In-App read/unread state | Own Tenant DB |
| Central-only platform notification, where authorized | Central DB |
| Minimal provider send-routing reference and verified Webhook receipt | Protected Central technical scope |
| Queue transport/metrics | Redis/Horizon/logs; **not authoritative delivery or commerce state** |

Candidate per-intent information: source operation/event, purpose, channel, recipient identity, selected template/version, locale and safe status. Candidate attempt: ordinal, timing, provider reference, classified result, safe diagnostic code. Candidate provider event: verified event ID/type/time, recorded time and provenance. **These are not finalized SQL table/column names.** Data minimization, uniqueness/indexes, retention and access belong to Feature/Data/Security specifications.

### 8.3 Conceptual lifecycle and exact semantics

~~~text
Pending -> Queued -> Attempting -> Provider Accepted -> Delivered (trusted proof)
                      |                   |
                      +-> Retry Pending   +-> Bounced / Complaint
                      +-> Failed
                      +-> Rejected
                      +-> Uncertain
~~~

- **Pending:** authorized logical notification intent exists; no assertion of external action.
- **Queued:** work is enqueued or awaiting the relay/consumer; not sent.
- **Attempting:** provider transport is in progress or its attempt state is being reconciled.
- **Provider Accepted:** submission accepted; **not** proof the recipient received or read the email.
- **Delivered:** a trustworthy provider report supports actual delivery under that provider's definitions; **not** proof of customer reading, consent or settlement.
- **Failed/Rejected:** record only with the classified supporting evidence.
- **Uncertain:** the provider may have accepted; do not silently set Failed and auto-resend.
- **Bounced/Complaint:** preserve late facts and adjust effective suppression/delivery interpretation according to trusted evidence and reviewed policy.

These are **conceptual states**, not a mandatory single DB enum or complete state machine. Keep appendable attempt/event evidence and derive effective state from valid chronology/semantics; the latest HTTP/Callback response may not be the latest business-relevant fact.

### 8.4 Two layers of duplicate safety

1. **Outbox consumer:** durable per-consumer Event processing identity prevents duplicate logical Notification creation even if Relay/Horizon redelivers; use owning-DB transaction, uniqueness, guarded updates or locks as appropriate.
2. **Message/provider:** one approved intent, attempt history, same-intent retries, provider idempotency/lookup **where actually supported**, explicit uncertainty and reconciliation for ambiguous effects.

Do not collapse independent legitimate transitions of the same Order, separate approved recipients or an authorized explicit re-send into one forever-deduplicated notification. Manual re-sends must record new approved communication identity/reason/actor as Feature-defined. No universal custom dedup engine is required.

### 8.5 Missing or invalid email addresses

Existing **D-118/D-119** decisions govern exception handling and authorized manual follow-up. A Guest Checkout is not newly required to contain an email merely because a transactional sending service exists. An absent/invalid address produces the Feature-defined unsendable/exception path, not a fictitious Delivered status or unapproved SMS/WhatsApp fallback.

---

## 9. INT-ST-06 — Bilingual Templates, Safe Variables and Merchant Branding

### 9.1 Platform-managed template library

Use one system-controlled set of templates for the actual approved notification types, with:
- Logical **Template ID** and explicit **Version**.
- Arabic **RTL** and English **LTR** variants with appropriate subjects and body rendering.
- Approved HTML and, where required, plain-text alternative.
- A typed/allowlisted **Variable Contract** per template; reject unexpected or missing required data safely.
- Captured template ID/version in the message intent or delivery evidence.
- Controlled compatibility when template versions change while older queued events/releases remain active.

Merchant staff **cannot** edit arbitrary email HTML, required legal/transactional wording or system template source as part of MVP. Actual storage format, filesystem/DB placement, locale fallback precedence, template registry fields and content-review process are Feature TO SPECIFY.

### 9.2 Permitted branding, not arbitrary authority

Allowed candidate Merchant values include **verified store name/logo, approved support contact, true Order reference/state, relevant monetary/date formatting and trusted storefront URL**. Review each variable and enforce output-context escaping. The sender remains platform-managed through a verified platform-owned identity to be configured pre-live; Merchant branding cannot spoof From domain or inject unsafe header values.

- Validate logo/public asset status and safe URLs; do not embed private file paths, raw internal hostnames or other Tenant assets.
- Generate links from approved Tenant domain/context, not user-controlled Host/Forwarded headers.
- Private linked resources still demand current authorization when opened; link delivery does not confer permanent access.
- Sanitize/escape untrusted names/notes for HTML/text/URL context; no arbitrary scripts, raw HTML or redirect destinations.
- Verify Arabic direction, English direction, mixed numerals/dates/EGP amounts, mobile rendering and accessibility.
- Choose applicable locale/fallback under reviewed Feature policy; **do not invent universal precedence** in integration infrastructure.

### 9.3 Business wording and content correctness

| Proven business fact | Appropriate semantic boundary |
| --- | --- |
| Order placed | Receipt/placement only after true committed placement |
| Order confirmed | Confirmation only after approved actual confirmation |
| Dispatch | Only after authorized physical dispatch recorded |
| Ready for pickup | Only after real ready state |
| Refund requested | Must not claim refund approved or executed |
| Refund approved | Must not claim money returned |
| Refund actually executed | Only after verified execution evidence |

The exact event/recipient/channel/frequency matrix is **NOT FINALIZED**. No automatic email for every state change, user note or D-201 unapproved follow-up. Sending/delivery does not prove customer consent, message reading or money settlement.

### 9.4 Safe template evolution

Existing queued intents and provider attempts must retain an interpretable template identity/version. During Blue-Green and rollback, verify both versions can handle retained message contracts. Do not presume storage of full rendered HTML bodies or indefinite PII copies; decide minimal historical snapshots, legal evidence and retention under a Feature-specific privacy review.

---

## 10. INT-ST-07 — Tenant-Local In-App Notifications and Current Access

### 10.1 Approved delivery model

Store In-App alert and recipient read-state in the **owning Tenant PostgreSQL database**. Provide authorized, bounded **Laravel API** operations for list, unread count, mark-read and opening referenced resources; use **lightweight Next.js polling**. Recipients who are offline can retrieve persisted eligible notifications upon return. No compulsory WebSocket, Reverb, SSE, browser push or dedicated real-time server in MVP.

Exact endpoints, DTOs, polling seconds, history retention, page size and alert targets are Feature/API/Capacity **TO SPECIFY**. Choose polling interval and inactive-tab/backoff behavior based on measured concurrent staff, DB index performance, payload volume and total API resource use; no universal 1-second polling guarantee.

### 10.2 Eligibility, access and revocation

For **every** notification list, badge/unread count, preview, mark-read and linked resource open:

1. Verify actual logged-in workforce identity and **current active Central Merchant membership**.
2. Verify trusted selected Tenant/Host agreement and eligible Tenant lifecycle/plan state where relevant.
3. Re-evaluate **current** Tenant-local Role/Permission and Location assignment.
4. Enforce the originating resource's owning-Module authorization and any recipient-specific permission.
5. Scope queries **before** count/search/pagination and avoid exposing another Tenant's existence or private details.
6. Deny newly revoked access even if the recipient row or a cached permission previously allowed it.

Platform admin authority is **Central-only by default**. An operator viewing Central health/status does not get unrestricted Merchant-customer message bodies or store Orders.

### 10.3 Recipient identities and read state

- Per-recipient read/unread state is independent; two employees seeing a shared operational alert must not overwrite each other's read state.
- Repeated mark-read is authorized and idempotent; never updates another employee's record.
- Role/Location target eligibility may be resolved at delivery time and must still be rechecked at read time.
- **Read does not mean Resolved**: opening an alert neither confirms/cancels Orders nor reserves stock, dispatches, collects funds or issues refunds.
- Feature-owned business workflow determines any separate resolution/action, its current eligibility and protected PostgreSQL transaction.
- Notification previews and counts must not reveal protected resource information indirectly after revocation.

### 10.4 Performance and test expectations

Use scoped PostgreSQL queries and appropriate indexes verified with EXPLAIN/representative load; paginate history, bound response payload and avoid unbounded per-alert joins. Poll only while useful, use safe backoff/error UX, and recover after reconnect. Redis counters are **not** automatically required; do not trade current authorization for stale unread-count caches. Verify many concurrent employees, idle tabs, large historical volumes, stale role changes and Central/Tenant A/B context reuse.

---

## 11. INT-ST-08 — Monitoring, Fair Capacity, Provider Failure Isolation

### 11.1 Lightweight telemetry signals

| Source | Minimum diagnostic signal | Failure/risk identified |
| --- | --- | --- |
| Outbox / Scheduler | Last scan/progress heartbeat, oldest unpublished intent, pending backlog | Stalled relay even when Horizon idle |
| Horizon | Queue wait/age, throughput, retries, timeouts, failed jobs, worker utilization | Starvation, retry storms, capacity |
| Email message/attempt DB state | Pending, Attempted, Accepted, Delivered, Failed, Uncertain counts/age | Actual communication gap and uncertain sends |
| Provider callbacks | Invalid signatures, replay rejections, unmatched/early receipt age, verified delivery/bounces/complaints | Routing, security, sender reputation |
| Tenant fairness | Global provider and per-Tenant sends/attempts, queue delay, throttling | Heavy Tenant starving others |
| Redis | Availability, memory, latency, rejected writes/evictions as relevant | Shared queue/cache transport pressure |
| PostgreSQL | Per-runtime connection count, slow/locked queries, unavailable Tenant DBs | Source-of-truth bottleneck |
| In-App APIs | Polling errors, access denials, response size and latency | Employee UX, access regressions |
| Host/Coolify | Container status, resource headroom, restart/deploy, disk/network | Shared VPS failure mode |
| Version/release | Worker release, event/template compatibility, Outbox scanner overlap | Broken Blue-Green migration |

Use existing Laravel structured safe logs, Horizon/Coolify, PostgreSQL/Redis/host metrics and owning message evidence. **Do not** create a custom monitoring system or mandate paid SaaS on day one. Numeric alert thresholds, SLOs, concurrent worker limits and capacity estimates **require representative measured tests**, not guesses.

### 11.2 Fairness and bounded rate budgets

- Apply actual **global provider quota/concurrency** and **bounded per-Tenant processing** so a heavy Merchant cannot monopolize notification queues or sender reputation.
- Separate or prioritize notification work logically relative to critical business jobs within **shared** Horizon. No mandatory worker/Redis deployment per Merchant.
- Respect provider Retry-After or documented rate hints, with bounded backoff/jitter and explicit max attempts/retry windows by error class.
- Prevent retry storms during long provider outages and control recovery ramp-up after service returns.
- Keep eligible In-App operations and committed commerce workflows functional independent of external email; shared host/DB/Redis contention still needs measured resource limits.
- A local adapter pause/circuit breaker is **optional if actual failure-load data justify it**; no required generic breaker framework.
- Use secure request size/rate controls for inbound webhooks without suppressing legitimate verified provider redelivery.

### 11.3 Alert severity and operator visibility

Prioritize proven cross-Tenant breach, secret compromise and unrecoverable durable-intent failures; then provider outage, aged backlog, stuck unmatched callbacks and missing scanner progress; then recovered/transient failures. Distinguish **Outbox committed**, **Redis published**, **consumer processed**, **provider accepted** and **externally Delivered when proven**. Do not label generic Horizon success as recipient delivery.

Alert owners/channels, thresholds, escalation and on-call coverage remain **TO SPECIFY AND TEST**. A dashboard displaying warnings is not evidence that responsible operators received/responded to an alert. Private details remain accessible only under scoped operator permissions.

### 11.4 Security and privacy of telemetry

Use safe request/correlation/event/operation/message IDs and verified opaque Tenant references. Do **not** log recipient email lists, full messages, raw webhook payloads, secrets, access/session tokens, unbounded Customer PII or financial evidence. Apply LOG-owned retention/access control and SEC-owned incident discipline. Technical metrics/logs cannot replace authoritative Tenant PostgreSQL records or prove stock/money effects.

---

## 12. INT-ST-08 — Safe Operations, Incident Triage and Reconciliation

### 12.1 Operational recovery runbook

1. **Detect:** inspect provider error class, Outbox scanner heartbeat, queue lag, failed jobs and affected scoped message intents.
2. **Authorize and scope:** identify actual operating actor, Central/Tenant owner, approved support authority, message/Event IDs and reason; no global read-all or impersonation shortcut.
3. **Inspect authoritative evidence:** original committed business operation, Tenant notification intent/attempts, trusted Central webhook receipt/correlation and verified provider outcome.
4. **Classify:** definitely unsent transient; permanent rejection; accepted; delivered; uncertain; unmatched early callback; Tenant DB unavailable; security incident.
5. **Act safely:** resume same intent when proven safe, reconcile with supported provider lookup/callback, perform verified no-op or documented authorized manual follow-up. Preserve original identity, avoid blanket retry-all.
6. **Verify:** inspect post-recovery Tenant records, actual external evidence, duplicate count, next eligible delivery and absence of cross-Tenant effects.
7. **Record:** operator/action/time/reason/outcome, remaining uncertainty, appropriate audit entry, alert closure and follow-up review.

Recovery by deleting Failed Job rows, truncating logs, declaring unknown effects Failed, manually editing an Order to match an email or force-running all jobs is prohibited. A system-state restore/DR plan is **separate** from queue replay and is still blocked by BA-16.

### 12.2 Known cross-context failure windows

| Failure window | Durable response |
| --- | --- |
| Business committed but Redis unavailable | Outbox remains, bounded scheduled relay eventually resumes |
| Published to Redis then relay crashes | Duplicate event tolerated by consumer-local dedup |
| Job commits logical intent then worker dies | New worker locates same intent |
| Provider accepts but outbound response is lost | Uncertain status; provider lookup/evidence/review before resend |
| Verified webhook before provider send reference saved | Durable unmatched/early receipt and later correlation |
| Central webhook receipt persisted, Tenant DB down | Retained processing stage; no distributed-commit claim |
| Two Webhook workers race | Atomic unique/guarded Tenant update and verified event provenance |
| Blue-Green workers overlap | Compatible schemas, bounded drains and no unprotected dual scanner |
| Provider credentials rotated during jobs | Recoverable classified failure; reconfigure/reconcile before retry |
| Operator loses authority during recovery | Current authorization wins; stop unauthorized action |

### 12.3 Abuse and customer exception handling

Invalid email or bounce/complaint must invoke the approved Feature and D-118/D-119 exception/manual follow-up rules, not fabricate success or activate an unapproved alternate channel. Shared sender reputation may justify reviewed suppression without constructing a cross-Tenant customer identity registry. A provider callback is **never** refund proof, customer consent or authorization to change commercial state.

---

## 13. INT-ST-09 — Required Test Strategy and Traceable Acceptance Matrix

### 13.1 Test layers and realistic environment

Use fast **Unit** tests for typed transport/error classification and templates; **Contract** tests for provider adapter/event schema/consumer versions; and realistic **Integration/Failure/Security** tests for PostgreSQL/Outbox/Redis/Horizon/Webhooks/Tenant context. Include targeted **Next.js API/Browser** tests for authorized polling and localization, and measured **Load/Soak** tests for real capacity.

Tests proving Tenant isolation, concurrency or durable crash recovery must use **real PostgreSQL Central + at least two physically separate Tenant DBs (A and B)**, appropriately restricted roles, actual isolated Redis/worker behavior and independent process/transaction synchronization as needed. Mock-only or SQLite-green evidence cannot replace a required real high-risk test. **No real Production customer sends or data use in Development.**

Run only the applicable suites per PR/risk, broadening selection for changed shared Core/Tenancy/Queue infrastructure and before release. Do not label unrun tests Passed.

### 13.2 Consolidated acceptance tests (INT-T01…INT-T48)

| ID | Scenario | Required observed outcome | Test layer |
| --- | --- | --- | --- |
| **INT-T01** | Order confirmation rolled back | No committed Order confirmation and no related Outbox intent | Real DB/transaction |
| **INT-T02** | Correct business commit | Business fact and required Outbox row atomically durable | Real DB |
| **INT-T03** | Redis down after local commit | Outbox retained and safely published after recovery | Integration/fault |
| **INT-T04** | Relay publishes then crashes before final marking | Duplicate delivery is possible but one logical Consumer effect | Integration/fault |
| **INT-T05** | Two scanner claimers overlap | Durable claims and idempotent downstream processing; no lost intent | Concurrency |
| **INT-T06** | Duplicate Event reaches same Notification consumer | One approved logical message | Real DB/queue |
| **INT-T07** | Concurrent Consumers race | Enforced uniqueness/guarding; one logical message | Real concurrency |
| **INT-T08** | Distinct legitimate transitions on same Order | Separate authorized communication intents remain possible | Domain/DB |
| **INT-T09** | Authorized explicit re-send | New recorded communication identity, not blocked by Order-only dedup | Feature/DB |
| **INT-T10** | Retry same message after proven safe failure | New attempt, same logical intent | Integration |
| **INT-T11** | Provider Accepted response | Recorded Accepted, never mislabeled Delivered | Contract/DB |
| **INT-T12** | Trusted delivery callback | Delivered only with provider-supported verified evidence | Webhook/DB |
| **INT-T13** | Outbound timeout after possible provider acceptance | Persist Uncertain and do not blind-resend | Fault/contract |
| **INT-T14** | Actual provider idempotency lookup where supported | Avoid unintended duplicate physical submission | Provider contract |
| **INT-T15** | Permanent invalid recipient | Stop futile retries; apply approved exception/suppression path | Domain/contract |
| **INT-T16** | Provider 429/Retry-After | Bounded delay, compliance with actual provider rules | Adapter/queue |
| **INT-T17** | Provider 5xx long outage | Controlled backoff, no retry storm, durable backlog | Load/fault |
| **INT-T18** | Invalid credentials or missing secret | Fail closed and safe alert; no fallback account | Security |
| **INT-T19** | Rotated secret during running jobs | Old key rejected, scoped new key works, no blind duplicated send | Integration/security |
| **INT-T20** | Invalid webhook signature | Reject before business mutation | Security |
| **INT-T21** | Provider-authenticated duplicate callback | Idempotent result and protocol-correct response | Webhook/DB |
| **INT-T22** | Replay and invalid/old timestamp under provider protocol | Secure response without duplicate effect | Security |
| **INT-T23** | Oversized, malformed or unknown webhook payload | Bounded safe rejection/quarantine | Security |
| **INT-T24** | Forged tenant_id/recipient/Host | No Tenant routing or state mutation from untrusted claims | Multi-Tenant |
| **INT-T25** | Unknown or mismatched Provider Message reference | No guessed Tenant; safe unmatched/quarantine handling | Multi-Tenant |
| **INT-T26** | Callback arrives before correlation save | Durable receipt and eventual trusted reconciliation | Race/fault |
| **INT-T27** | Central receipt committed, Tenant A DB unavailable | Recoverable pending state; no Tenant B fallback | Cross-DB fault |
| **INT-T28** | Two callback processors race on same event | One effective update, valid evidence retained | Real concurrency |
| **INT-T29** | Delivered arrives before older Accepted | Status does not regress solely by arrival order | Event order |
| **INT-T30** | Late Bounce/Complaint after prior events | Preserve all trusted facts; derive correct effective handling | Event order |
| **INT-T31** | Callback tries to affect Order, Stock, Collection or Refund | No unauthorized business write | Module/security |
| **INT-T32** | Tenant A employee asks for Tenant B list/count | Deny; no existence or PII leakage | Real multi-Tenant |
| **INT-T33** | Employee loses Role/Location after notification creation | List/count/read/open no longer discloses protected data | Authorization |
| **INT-T34** | Different eligible recipients share alert | Independent read states; marking one does not affect another | Real DB/API |
| **INT-T35** | Same recipient marks read twice | Idempotent same-user update | API/DB |
| **INT-T36** | Platform Admin accesses Tenant private alert without grant | Denied; Central privileges are not Store data access | Authorization |
| **INT-T37** | In-App alert is opened/marked read | Does not resolve/confirm/cancel any business operation | Domain/API |
| **INT-T38** | Arabic and English email templates | Valid RTL/LTR, dates/EGP/mixed text and mobile rendering | Template/E2E |
| **INT-T39** | Malicious template variable or untrusted Store URL | Escaped/denied; no script injection or cross-Tenant link | Security/template |
| **INT-T40** | Old queued Template/Event across new release | Compatible handling or blocking release plan | Contract/release |
| **INT-T41** | Customer email absent/invalid | Approved D-118/D-119 exception; never false Delivered | Feature |
| **INT-T42** | Wrong message wording for approved vs executed refund | Fail content approval; never claim money returned without proof | Domain/content |
| **INT-T43** | Scanner stopped while Horizon idle | Independent heartbeat/backlog detection | Monitoring |
| **INT-T44** | Heavy Tenant volume during normal other Tenants | Fair bounded progress, no protected-work starvation | Capacity |
| **INT-T45** | Large staff polling/history volume | Bounded query latency/payload, correct scoped counts, backoff | Load/API |
| **INT-T46** | Reused worker A → B → Central including failures | No stale DB/cache/actor/provider context leakage | Multi-Tenant/fault |
| **INT-T47** | Runtime build/image/Next.js/log inspection | No provider keys, Cookie/session secrets or unrestricted customer PII | Security/deployment |
| **INT-T48** | Blue-Green Horizon/Outbox/provider callback overlap | Compatible versions, bounded drain, no lost/duplicate unauthorized effect | Release/integration |

All test expectations must be checked against **actual** Feature semantics; this table does not predefine final status enums, table names, alert thresholds, role matrices or SMTP/provider callback format.

### 13.3 Reporting and PR evidence

Every applicable test result must identify **repository/branch/PR/source SHA**, selected suite and rationale, runtime/DB/Redis/provider sandbox environment, executable commands, observed **Passed/Failed/Skipped/Not Run/Blocked**, actual duration, privacy-safe artifacts and reviewer. Critical security or tenant isolation failures cannot be silently quarantined; a test that never ran cannot pass. Repeatable flaky-test diagnosis is required rather than hiding the first failure through retry.

For changes to Provider Adapter, trigger Feature, template, Webhook intake, Tenant scope, Public Event schema, Outbox, Horizon or shared runtime secrets, select matching tests from INT-T01…48 plus relevant TEST/SEC/API/LOG suites. Change-based selection must not under-test a shared Core impact.

### 13.4 Implementation sequencing, not an instruction to implement now

Future authorized engineering should: (1) define Feature trigger/recipients/locale; (2) create minimal owning notification contracts/intents; (3) connect existing Outbox/queue; (4) add templates/Fake Adapter; (5) attempts/status/uncertainty; (6) authorized In-App API/polling; (7) chosen provider/webhook/correlation; (8) DNS, rate budgets, privacy, monitoring and operator recovery; (9) real A/B isolation and failure tests; (10) live sending only after BA-30 gates. Any specific implementation task needs its own Feature Spec, PR/review and authorization.

---

## 14. INT-ST-09 — Security Blockers, Deployment Controls and BA-30 Gates

### 14.1 Critical release blockers

| Proven condition | Required gate action |
| --- | --- |
| Cross-Tenant notification content, count, DB credential or Webhook routing disclosure | **BLOCK** affected PR/release |
| Unauthorized employee/Location/private resource access after relevant revocation | **BLOCK** |
| Leaked Provider/DB/Session Secret or exploitable Webhook authentication bypass | **BLOCK** |
| Duplicate real Order/Stock/Collection/Refund effect caused by integration or unsafe retry | **BLOCK** |
| Committed critical business effect missing required Outbox intent | **BLOCK** |
| Blind external resend on unresolved provider acceptance with material duplicate risk | **BLOCK** until safe plan |
| Destructive or incompatible Event/Template/DB change during old/new worker overlap | **BLOCK** until controlled transition |
| Required high-risk real test failed, skipped or never executed | **BLOCK / UNVERIFIED**; never report green |
| Unverified sender/DNS, recipient handling, legal rules or required operator recovery for actual live use | **BLOCK live activation** |

Critical blocker resolution requires reproducible evidence and reviewed corrective action; green Unit tests or a healthy Coolify container are not substitutes.

### 14.2 Development-to-Production boundary

- **development:** reviewed feature/fix PRs, risk-selected checks, separately authorized merge and Development deployment using controlled sandbox email, isolated databases/Redis/secrets and no live sends.
- **main / future Production:** independent reviewed promotion, immutable compatible image digest, migration/change checks, correct runtime secrets, callback endpoints, signer/DNS, worker drain, one effective scanner strategy, post-deploy smoke, observability and explicit human **go/no-go**.
- Compatible overlap is a **target to validate**, not a promise of zero downtime; rollback does not undo provider-side messages or committed database effects.
- Publication of this markdown file alone does not configure GitHub Actions, Coolify, registry, Deployment or branch protection.

### 14.3 BA-30 explicit ten-gate integration traceability

| Gate | Required integration evidence | Current position |
| --- | --- | --- |
| **G1 Tenant safety** | A/B/Central callback routing, message/recipient access, reused workers, restricted DB credentials | NOT VERIFIED |
| **G2 Commerce integrity** | Provider/notification failure cannot mutate committed Order/Stock/Money; safe Outbox and retries | NOT VERIFIED |
| **G3 Backup & Restore** | **Separate BA-16 approval**, recoverable off-VPS Central + every Tenant DB + files, full/per-Tenant restore drills and measured approved RPO/RTO | **BLOCKED — BA-16 DEFERRED** |
| **G4 Release safety** | Tested CI/CD, versioned events/templates, expand-first schema, worker drain, callback continuation and rollback plan | NOT VERIFIED |
| **G5 Capacity/NFRs** | Representative provider quota/queue/DB/polling load, actual numeric bounds and alerts | PENDING VALIDATION |
| **G6 Security/privacy** | Real signed callbacks, sender/DNS, TLS, secret handling, scoped PII, operational access, retention | NOT VERIFIED |
| **G7 Legal readiness** | Applicable Egypt customer communication, privacy, sales/tax/document requirements reviewed for Pilot | LEGAL REVIEW REQUIRED |
| **G8 Operational completeness** | Real approved event/recipient matrix, D-118/D-119 exceptions, Arabic/English UX, manual follow-up | PENDING FEATURE ACCEPTANCE |
| **G9 Environment separation** | Development and future Production DB/Redis/files/queues/keys/sending identity isolated | NOT VERIFIED |
| **G10 Explicit sign-off** | Named owner, documented tests/evidence, Merchant/Feature scope, stop/recovery criteria and approval | APPROVAL REQUIRED |

**Nothing in this document satisfies a BA-30 gate.** G3 is still blocked while BA-16 remains deferred, even if integration tests pass. The first live Pilot cannot be authorized on the strength of this document.

---

## 15. INT-ST-10 — Developer, Reviewer and Operator Checklists

### 15.1 Feature specification readiness

- [ ] Identify exact approved D-series/OV business event and owning Module; do not invent a trigger.
- [ ] Identify authoritative Tenant/Central data owner and actor, trusted selected scope, current memberships/roles/Locations.
- [ ] Declare synchronous required invariant versus permitted post-commit asynchronous side effect.
- [ ] Define Public Contract/Published Event type/version, minimal payload and appropriate Outbox transaction.
- [ ] Define notification recipient/channel/locale/content, missing-email exception and relevant consent/legal handling.
- [ ] Define stable Event ID, logical Message Intent, attempt/provider correlation and legitimate repeat rules.
- [ ] Define actual state/evidence semantics, safe timeout/retry/Uncertain handling and supported provider reconciliation.
- [ ] Specify webhook authenticity protocol, registered send routing and early/unmatched processing if provider callback is involved.
- [ ] Declare user-facing permission/Location/resource checks, current revocation behavior and safe In-App count/preview.
- [ ] Decide required schema/indexes, lifecycle, retained PII, template version compatibility and safe audit evidence.
- [ ] Record measurable capacity/alert criteria **only with** provider limits and representative data; otherwise leave Pending.
- [ ] Map applicable INT-T test IDs, SEC/TEST/QJ cases, release gates and separate implementation authorizations.

### 15.2 Code/PR readiness

- [ ] No direct Provider SDK/API from owning Business Module, arbitrary controller/observer or open Order transaction.
- [ ] No cross-Module private-table writing; no Central/Tenant distributed ACID claim or untrusted Tenant fallback.
- [ ] Correct per-DB outbox atomicity; scanner/relay/worker safe for duplicates, crashes and reused Tenant contexts.
- [ ] No speculative extra provider/channel, gateway, task-specific microservice or new queue/database product.
- [ ] Secrets runtime-only and least privileged; never sent to Next.js, payloads, logs, image layers or CI artifacts.
- [ ] Typed validated transport DTOs and stable normalized outcomes; provider-specific errors not leaked to customers.
- [ ] Bounded external call, retries, delays, concurrency and time budgets; ambiguous acceptance not blindly resent.
- [ ] Verified Webhook authentication, trusted send correlation, minimal durable receipt, quarantine and no callback business write.
- [ ] PostgreSQL logical uniqueness/attempt evidence; no fraudulent Delivered status or accidental suppression of legitimate resends.
- [ ] Bilingual versioned safe templates, trusted Tenant links, lawful/accurate state text, accessible fallback policy.
- [ ] In-App list/count/read/open reflects current Central membership and Tenant Role/Location/resource authorization.
- [ ] Privacy-safe structured correlation, Outbox heartbeat, clear recovery path and reversible deployment plan.
- [ ] Actual test commands, DB setup, execution/observed result, failures/skips and reviewer evidence included in PR.

### 15.3 Operations / provider activation

- [ ] Provider selected by explicit operational decision; actual service/API/SMTP terms, quotas/region/privacy tested.
- [ ] Platform sender identity owned/verified; SPF/DKIM/DMARC and reply-to behavior validated.
- [ ] Distinct Development test transport, future Production credentials/webhooks/secrets and restricted access.
- [ ] Provider authentication/callback signatures, replay rules, status meanings and actual idempotency/reconciliation behavior tested.
- [ ] Event/recipient/template trigger matrix approved; D-118/D-119 exception route staffed.
- [ ] Controlled provider/global/Tenant limits, retry storm behavior, In-App polling and load budgets measured.
- [ ] Named alert owners and live alert-channel test; recovery/reconciliation runbook demonstrated.
- [ ] Compatible workers/templates/events/DB schema and Outbox scan policy demonstrated during release overlap.
- [ ] No critical tests unrun or failed; correct SHA, environment, results and artifacts reviewed.
- [ ] **BA-16 approved and G3 restore evidence present; all other applicable BA-30 gates and legal readiness confirmed** before real Merchant activation.

---

## 16. Deferred Implementation Decisions, Risks and Owners

| Topic | Current disposition | Future decision / proof owner |
| --- | --- | --- |
| Specific Email Provider / API vs SMTP | **NOT SELECTED** | Notifications/Infrastructure provider-selection Feature |
| Sending From/Reply-To and verified domain | **PENDING VALIDATION** | Provider/Operations, DNS/SPF/DKIM/DMARC checks |
| Provider event identity, signature, timestamp/replay semantics | **PROVIDER-DEPENDENT** | Webhook implementation/SEC review |
| Provider-side idempotency and send lookup | **UNVERIFIED** | Provider contract/capability tests |
| Exact event, recipient and channel trigger matrix | **TO SPECIFY** | Orders, Fulfillment, Returns, Support owning Feature Specs |
| Missing/invalid email exception UX and owner | D-118/D-119 governing; **DETAIL PENDING** | Customer/Orders notification Features |
| Template storage, HTML/plaintext contracts and locale fallback | **TO SPECIFY** | Notifications/Localization Features |
| Message intent/attempt/evidence tables, constraints and indexes | **TO SPECIFY** | Notifications/Data Feature Specs; real PostgreSQL tests |
| Technical Central callback correlation index and early-event races | **TO SPECIFY** | Notifications/Infrastructure implementation plan |
| PII, provider event, message evidence and template-snapshot retention | **TO SPECIFY / LEGAL REVIEW** | Feature/Security/Legal owner; no invented 30/90-day rule |
| Timeouts, retries, rate budgets, fair Tenant share | **PENDING PROVIDER AND LOAD MEASUREMENTS** | Provider/Queue/Capacity tests |
| In-App Poll interval, paging and UX | **PENDING MEASUREMENT / FEATURE DETAIL** | API/Next.js Feature Specs |
| Targeted adapter circuit breaker | **OPTIONAL IF JUSTIFIED BY MEASUREMENT** | Operations/Capacity, not baseline dependency |
| Alert channels, thresholds, escalation and recovery responsibility | **TO SPECIFY AND TEST** | Operations/LOG runbook |
| Laravel 13/Stancl v3.10.1/Horizon compatibility | **PENDING TECHNICAL VALIDATION** | Foundation/Integration tests |
| GitHub Actions, GHCR, protected PR/release checks | **NOT CONFIGURED BY THIS DOCUMENT** | Separate Foundation/Infrastructure implementation |
| Production environment / DNS/provider/secret provisioning | **NOT PROVISIONED** | Later approved release/operations |
| **BA-16 Backup & Restore** | **DEFERRED, NOT APPROVED** | Explicit separate decision; off-VPS Central/Tenant/files backup and full/per-Tenant restore |
| **BA-30 G3** | **BLOCKED** | Separate verified BA-16 recovery, RPO/RTO and approval |
| **D-201** | **OPEN** | Orders/Fulfillment business-owner decision; not a notification approval |
| Egypt legal/privacy/tax obligations | **LEGAL REVIEW REQUIRED** | G7 before affected live Pilot |

Open items are **not** authorization to add generic infrastructure or make up provider limitations, exact endpoint paths, SQL schemas, notification state enums, quotas, SLOs, sender identity, retention, legal interpretations or launch schedules.

---

## 17. INT-ST-10 — Final Reconciliation, Approval and Publication Boundary

### 17.1 Cross-document consistency review

| Compatibility check | Resolution in this Standard |
| --- | --- |
| Architecture INT-01/BA-28 vs engineering INT-ST-01 | Narrow Provider Adapters, owning workflows, same Modular Monolith, no new ESB/microservice |
| INT-02 / INF-08 / SEC-05 vs INT-ST-02 | Single platform-managed Email Provider, runtime-only secrets, Development/Production separation |
| INT-07 / TC-06 / QJ-04 vs INT-ST-03 | Classify errors, bounded timeouts/retries, uncertain provider outcomes reconciled |
| INT-06 / SEC-03 / Tenancy vs INT-ST-04 | Authenticated receipt-before-ack callback, minimal Central trusted routing, no untrusted Tenant fallback |
| INT-04 / TC-04 / QJ-05 vs INT-ST-05 | Owning local ACID + Outbox, at-least-once, message/attempt identities, PostgreSQL duplicate guards |
| INT-03 / approved MVP vs INT-ST-06 | Versioned platform-owned Arabic/English templates, safe merchant branding, no editor |
| INT-05 / IA / AUTHZ vs INT-ST-07 | Tenant PostgreSQL In-App, Next.js polling, current membership/Location/resource permission |
| INT-07 / QJ-06/07 / LOG vs INT-ST-08 | Light observability, fair shared resources, safe operator recovery, no invented throughput |
| TEST/SEC/BA-30 vs INT-ST-09 | Real cross-Tenant/failure/security tests, risk-selected CI/release gates; G3 blocked |
| Documentation workflow vs INT-ST-10 | New markdown file on development only; no code, CI, merge, production or live sending |

**No known deliberate override** of the referenced architecture is introduced. Later discovery of a real conflict requires a separately approved resolution, not silent modification of the business decision register.

### 17.2 Approved position

The owner has approved **INT-ST-01 through INT-ST-10 as Option A**. The first MVP integration standard is:

**Module-Owned Commercial Truth → Local Transactional Outbox → Shared Redis/Horizon → Tenant-Owned Notifications and Durable Intent → One Platform-Managed Email Provider behind a Narrow Adapter → Authenticated Durable Webhooks and Trusted Correlation → Verified Tenant-Local Delivery Evidence**, plus **PostgreSQL-backed employee In-App alerts with permission-aware Next.js polling**.

The implementation must honor typed contracts, provider outcome uncertainty, event/template versions, constrained retries, safe per-Merchant rate/fairness limits, runtime-only secrets, real Tenant isolation, privacy and observed release evidence.

### 17.3 Exact publication limits and next work

This file is **an approved documentation baseline created on development**. Publication authorizes **no** installation, migration, database creation, sending service enrollment, webhook DNS change, GitHub Actions setup, application code change, main merge or Production rollout.

The next proposed document is **Localization Standards**, subject to its own discussion and owner approval. A consolidated Backend Architecture/Standards consistency review may follow before independently planned Frontend Architecture and Spec Kit Foundation implementation.

**Critical outstanding status: D-201 OPEN · BA-16 DEFERRED · BA-30 G3 BLOCKED · Production NOT PROVISIONED · Integration Tests NOT RUN.**
