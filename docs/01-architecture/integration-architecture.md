# E-commerce SaaS V2 — Integration & Notifications Architecture

**Version:** 1.0 — Approved Architecture Baseline  
**Date:** 2026-11-18  
**Repository:** Elmo-Tech/ecommerce_v2_api  
**Branch:** development  
**Path:** docs/01-architecture/integration-architecture.md  
**Decision Status:** **APPROVED — INT-01 through INT-07 (Option A); INT-08 single-file documentation expressly authorized**  
**Implementation Status:** **NOT IMPLEMENTED / NOT VALIDATED**

> **Important:** This document records architecture approval, not a configured Email Provider, implemented Laravel code, running Coolify services, successful integration tests or permission to launch live Merchants. **The current VPS is Development only; Production is not provisioned. BA-16 Backup & Restore is DEFERRED, and BA-30 G3 remains BLOCKED for the live Pilot.** Provider selection, sending identity, DNS and webhook details, notification triggers and quantitative limits are pending separate validation.

---

## 1. Purpose, Authority and Scope

### 1.1 Purpose

Specify an MVP-first, Tenant-safe way to integrate approved transactional customer email and employee In-App notifications with Laravel 13 Modular Monolith, Next.js, PostgreSQL (Central + one DB per Merchant), Redis/Horizon and the selected Coolify deployment approach.

The goals are:

- Preserve business-module ownership and PostgreSQL as source of truth.
- Deliver eligible transactional messages asynchronously and independently of order persistence.
- Use one platform-managed sending service, versioned bilingual templates and controlled Merchant branding.
- Track message attempts, acceptance, verified delivery, failures and recipient-specific alerts.
- Process secure provider callbacks under trustworthy Tenant attribution and replay/idempotency protections.
- Contain external provider outages, rate limits, ambiguous outcomes and noisy-Tenant effects.
- Avoid introducing a generic integration bus, microservice, multi-channel router or marketing system.

### 1.2 Governing documents

This architecture complements and must not silently amend:

- [Project Overview](../00-overview/project-overview.md): **OV-10 B**, bilingual transactional emails, operational alerts, missing-email exceptions, explicit MVP exclusions and Pilot gates.
- [Backend Architecture](./backend-architecture.md): **BA-09 B** Redis/Horizon, **BA-28 B** narrow provider-agnostic adapters, **BA-16 DEFERRED**, **BA-30** readiness.
- [Module Architecture](./module-architecture.md): module-owned business state, One Writer Principle, public contracts and execution contexts.
- [Tenancy Architecture](./tenancy-architecture.md): trusted Central/Tenant resolution and conditional stancl/tenancy adoption.
- [Queues, Jobs & Event Processing Standards](./queue-jobs-event-processing-standards.md): **QJ-01…QJ-08**, durable Outbox, at-least-once delivery, idempotency and operational recovery.
- [Transactions & Consistency Architecture](./transaction-consistency-architecture.md): **TC-04**, atomic business/event intent, duplicate-safe consumers, uncertain third-party effects.
- [Identity & Access Architecture](./identity-access-architecture.md): Central staff membership, Tenant-local roles and Location permissions.
- [API Architecture](./api-architecture.md): scoped API endpoints, trust boundaries, authentication and abuse protection.
- [Infrastructure & Deployment Architecture](./infrastructure-architecture.md): **INF-01…INF-12**, Coolify Native Applications, secrets, environment/release isolation.
- [Caching & Redis Architecture](./caching-redis-architecture.md): shared Redis capacity and tenant-safe queue transport.

Approved business decisions, including **D-118/D-119** missing/invalid email handling and relevant order/refund state rules, remain authoritative. This document does not define new order, refund, stock or customer-consent business transitions.

### 1.3 MVP scope and exclusions

| Capability | Status in first MVP |
| --- | --- |
| Transactional customer emails on approved actual business events | **IN SCOPE** |
| Authorized employee In-App notifications | **IN SCOPE** |
| Minimal Central-only operational notifications where separately approved | **IN SCOPE AS JUSTIFIED** |
| Adapter for one platform-managed Email Provider | **IN SCOPE** |
| Delivery attempts/logs and authenticated provider callbacks | **IN SCOPE** subject to selected provider features |
| System-managed Arabic and English templates | **IN SCOPE** |
| Merchant-owned SMTP credentials / custom sender domains | **NOT SELECTED** |
| Merchant-editable email template builder | **NOT SELECTED** |
| WebSocket/Reverb real-time delivery | **NOT SELECTED** |
| WhatsApp, SMS, Facebook/Instagram messaging, social integration | **DEFERRED** |
| Marketing campaigns, bulk promotion and CRM automation | **DEFERRED** |
| Online payment gateway, courier/shipping API, automatic SaaS billing | **DEFERRED** |
| Enterprise integration gateway, multi-provider failover routing, workflow builder | **NOT SELECTED** |

An adapter or a conceptual integration event is **not** product-scope approval for a deferred capability.

---

## 2. Approved Decision Register — INT-01…INT-08

| ID | Selection | Consequence |
| --- | --- | --- |
| **INT-01** | **A — Lightweight Integration Adapters + Module-Owned Workflows** | Business modules own facts; Notifications owns message logic; adapters own third-party transport; reuse QJ/TC infrastructure. |
| **INT-02** | **A — Platform-Managed Transactional Email Service** | One platform-controlled provider and verified sending identity, Tenant-aware branding and scoped tracking. |
| **INT-03** | **A — System-Managed Versioned Bilingual Templates + Tenant Branding** | Arabic/English platform templates, explicit variables, safe per-store identity, no Merchant template editor. |
| **INT-04** | **A — Horizon + Transactional Outbox + PostgreSQL Delivery Tracking** | Durable logical message intent, attempt history, proven statuses and guarded retries/deduplication. |
| **INT-05** | **A — Database-Backed In-App Notifications + Lightweight Polling** | Tenant-local alerts, per-recipient read state, current Tenant/Location authorization; no mandatory WebSockets. |
| **INT-06** | **A — Secure Provider Webhooks + Tenant-Aware Delivery Tracking** | Authenticate/replay-protect callbacks and correlate them to trusted Tenant-owned delivery records. |
| **INT-07** | **A — Laravel Native Resilience + Provider Isolation** | Timeouts, bounded retries/backoff, safe rate limits, shared capacity fairness, diagnostics and optional targeted breaker. |
| **INT-08** | **Single comprehensive architecture file** | User authorized immediate final review and upload; does not authorize implementation or production sending. |

No exact table schema, provider product, sending domain, request limit, polling interval or retention period is fixed by these architecture decisions.

---

## 3. Logical Ownership and Module Boundaries — INT-01

### 3.1 Responsibility map

~~~mermaid
flowchart TD
    B["Owning Business Module"]
    P["Authoritative PostgreSQL Transaction"]
    O["Same-DB Transactional Outbox"]
    R["Bounded Outbox Relay"]
    H["Redis / Laravel Horizon"]
    N["Notifications Module"]
    E["Email Provider Adapter"]
    I["Tenant In-App Store"]
    S["Platform-managed Email Provider"]
    W["Authenticated Callback Intake"]
    B --> P --> O --> R --> H --> N
    N --> E --> S
    N --> I
    S --> W
    W --> N
~~~

Logical architecture only. Components remain inside the approved Laravel Modular Monolith and existing infrastructure; no synchronous inter-module HTTP or separate Notification microservice is required.

### 3.2 Authoritative responsibilities

- **Orders, Fulfillment, Returns and other owning modules** decide when real approved business events occur. Only the owner changes its business state.
- **Owning module public contracts and reviewed integration events** expose the necessary committed facts, with minimal authorized payload.
- **The Notifications Module** decides notification eligibility, recipient, allowed channel, language/template version, per-message intent and attempts, in-app visibility and evidence handling.
- **A narrow technical adapter** performs provider-specific submission, response/error normalization and callback parsing. It must not independently confirm, cancel or refund an order.
- **QJ/TC infrastructure** handles post-commit publication, worker contexts, deduplication/retry mechanics. Do not build a second Outbox/event bus for email.

An internal Domain Event does not automatically become a public integration event. No unrelated module should depend on a provider SDK or edit another module's private notification records.

### 3.3 Central and Tenant boundaries

| Context | Authoritative state and access |
| --- | --- |
| Merchant notification | Intents, send attempts, delivery evidence and In-App recipient state live in the **owning Tenant DB** |
| Platform administrative notification | Only approved platform-owned notification state lives in **Central DB** |
| Email webhook routing | A **minimal secured Central technical correlation index** may reference a known outbound send and opaque Tenant identity, not archive all customer correspondence |
| Background execution | Trusted Tenant/Central scope, verified current state and guaranteed cleanup after every attempt |

Cross-context handling must not claim a single distributed ACID transaction. Platform admin rights do not automatically authorize reading every Merchant's email recipient or order details.

---

## 4. Platform-Managed Transactional Email — INT-02

### 4.1 Service selection

The platform configures and operates **one transactional sending provider** for all eligible Merchants in the MVP; individual stores do not enter SMTP passwords/API keys or install their own provider integration.

**The provider brand and API/SMTP transport are not selected yet.** Capability, quota, region, price, service conditions, callback format and reliability must be verified before activation.

### 4.2 Trusted sending identity

- Send from an approved **platform-owned and verified sending domain/address**.
- Display Merchant identity through safe store name, approved logo, subject, contact text and trusted storefront links.
- Do not spoof Merchant domain ownership, From address or display names in ways the provider disallows.
- Final sender/reply-to strategy and the DNS setup for SPF/DKIM/DMARC are **provider-selection and pre-live implementation details**.
- Protect reputation of the shared sender by controlling abusive recipients, bounces, complaints and one Merchant's excessive volume.

### 4.3 Business event families, not an automatic event list

Examples of approved essential customer communication **where applicable under Feature Specs**:

| Real event | Messaging rule |
| --- | --- |
| Order placement/receipt | Transactional receipt/notification on genuinely recorded placement |
| Order confirmation | Only after actual approved confirmation |
| Dispatch | Only after actual authorized dispatch |
| Ready for pickup | Only after genuine ready-for-pickup event |
| Cancellation | Only after committed authorized cancellation |
| Refund status change | Clearly distinguish requested, approved and actually executed refund; never claim money returned without evidence |

The notification trigger matrix, channel applicability, recipients, frequency and content are **not settled by this table**. Not every transition requires a separate email, and internal notes are not automatic external messages.

### 4.4 Missing address and consent

The approved **D-118/D-119** exception/manual follow-up requirements govern absent/invalid contact emails. Transactional emailing does not make every Guest Checkout require a valid email; do not silently mark an unsent message Delivered or invent an automatic communication method.

Email sending or even proven delivery is **not** customer consent, proof of reading, refund acceptance or settlement evidence. Legal and customer-contact duties require separate pre-live review.

---

## 5. Versioned Arabic/English Templates — INT-03

### 5.1 Platform-owned library

Use **system-managed** templates, each with an explicit notification type and **version**. Maintain Arabic RTL and English LTR variants; Merchant staff cannot edit full email HTML or required transaction/legal wording in MVP.

### 5.2 Approved dynamic branding

Candidate fields include verified store name/logo, real order reference/state, approved support contact, appropriate date/amount formatting and trusted store URL. Each template's variable contract is **allowlisted and validated** rather than interpolating arbitrary user text or HTML.

- Escape/sanitize untrusted fields and constrain external URLs/images.
- Generate links from the approved Tenant domain/context; do not permit cross-Tenant destinations.
- Private linked resources still require current authentication/authorization at open time.
- Test RTL/LTR, bilingual content, number and EGP formatting and mobile mail rendering.
- Prefer the applicable recipient language when known. **Locale fallback precedence must be specified** in the Feature Spec, not invented here.

### 5.3 Audit and safe evolution

Track the **logical template ID/version** used for a message. Do not retroactively change evidence of an earlier send when the platform updates a template.

Queued messages and prior application versions need compatible event/template contracts during Coolify rolling updates. Retaining fully rendered message bodies or duplicating unnecessary PII is not presumed; decide limited snapshots, privacy and retention requirements per Feature.

---

## 6. Durable Delivery Workflow and Message-Level Tracking — INT-04

### 6.1 Outbox → Horizon → adapter

1. Owning module commits business fact and its **Outbox event in the same relevant PostgreSQL transaction**.
2. Existing Outbox relay publishes committed intent to Redis after the commit.
3. Horizon reinitializes and validates trusted Tenant context; a Notification consumer creates/locates the **logical notification intent** under durable uniqueness.
4. The approved recipient/template/channel are resolved from the relevant Tenant state/rules.
5. An adapter sends the message **outside** the original transaction and records attempt outcome.
6. Trusted webhook/provider evidence advances status when available.

Do not use a simple post-commit in-memory queue dispatch as the **only** durable link to a critical notification; a crash can occur between transaction commit and Redis publishing.

### 6.2 Status meaning

~~~text
Pending -> Queued -> Attempting -> Provider Accepted -> Delivered (trusted proof)
                      |                   |
                      +-> Retry Pending   +-> Bounced / Complaint
                      +-> Failed
                      +-> Rejected
                      +-> Uncertain
~~~

This is a conceptual lifecycle, not a complete DB enum. A late Bounce/Complaint must be preserved as evidence rather than overwritten simply because a previous Delivered/Accepted event existed. Keep history and derive effective status using verified event meaning, not arrival order alone.

- **Queued:** local background work exists; no provider outcome.
- **Attempting:** an outbound request is in progress.
- **Provider Accepted:** provider accepted responsibility for processing; recipient delivery not proven.
- **Delivered:** trusted provider delivery evidence exists; not proof a human read it.
- **Failed / Rejected:** according to known failure type.
- **Uncertain:** provider may have accepted despite timeout/disconnect; reconcile before potentially duplicate send.

### 6.3 Conceptual fields only

| Record responsibility | Candidate metadata |
| --- | --- |
| Intent | Tenant/Central scope, notification type, business Event/Operation reference, target/channel, stable logical identity |
| Attempt | attempt ordinal, timestamps, provider correlation, safe error class, safe response metadata |
| Delivery history | verified provider event ID/type/time, recorded-at time, safe status/evidence |
| Template | logical ID/version and selected locale |
| In-App recipient state | authorized staff recipient and read/unread state |
| Diagnostics | minimal correlation and sanitized error context |

These are **not mandatory final table columns**. All Merchant business notification records remain Tenant-local. Retention, indexing, recipient PII controls and actual schema require Feature/Security/Data specifications.

### 6.4 Idempotency and legitimate repetition

Use a **stable message identity** keyed to a specific approved source event, purpose/channel and recipient within the Tenant. Enforce appropriate PostgreSQL uniqueness so duplicate Horizon events cannot create new logical messages.

Do not deduplicate all notifications by Order ID; one order can have multiple legitimate transitions and separately authorized re-sends. Preserve the difference between a retried attempt **of the same intent** and a genuinely new communication.

Local uniqueness **cannot guarantee external exactly-once email delivery** if the provider accepted a request but the network failed before response. Use provider-side idempotency or send lookup when supported, otherwise reconcile or escalate uncertainty; do not blindly retry uncertain acceptance.

### 6.5 Classified retries and recovery

| Failure | Safe response |
| --- | --- |
| Known transient, definitely not accepted | Bounded retry with tested backoff/jitter |
| Rate limit (such as HTTP 429) | Honor supported Retry-After, cap provider and Tenant concurrency |
| Permanent invalid recipient / reject | Stop futile attempts, log safe failure and apply policy-based suppression |
| Ambiguous network failure after possible acceptance | Mark Uncertain; resolve via trusted provider evidence or documented manual escalation |
| All allowed attempts exhausted | Persist failed state; authorize support/manual follow-up where required |
| Duplicate message event | Idempotent processing of existing logical intent |
| Tenant unauthorized/unavailable | Fail closed; preserve recoverable state and do not switch to default Tenant |

Horizon retries/backoff/timeout and Redis retry_after relationships follow **QJ standards**, not one universal constant. Mail-provider outage must not roll back or modify committed order, inventory or refund state.

---

### FBR-02-04/18 — Pending Intent and Suspended Tenant Delivery

A committed Tenant-local notification/event intent stays durable if Tenant state or commercial entitlement changes after commit. Do not delete it or treat queue acceptance as successful delivery. During **Commercial Suspension**, only separately approved, scoped processing of an earlier **confirmed business obligation** is eligible, subject to current Tenant/receiver/privacy/legal checks; new marketing or commerce effects are not implicitly enabled. Security/legal suspension is separately fail-closed. Hold, visibly reconcile, or resume by original event identity, never blindly replay or grant blanket Platform Tenant access.

## 7. Tenant-Local In-App Notifications — INT-05

### 7.1 Delivery model

Use **PostgreSQL-backed In-App notifications** in the owning Tenant DB, exposed via authorized Laravel API reads and **lightweight Next.js polling**.

An alert persists if the employee is offline. Each eligible recipient has independent read/unread tracking. Polling frequency, inactive-tab suppression, pagination, payload size and feature-specific notification history are tuned from measured load; **no universal one-second interval** is approved.

WebSocket/Reverb, browser push and a dedicated real-time server are **not part of MVP**.

### 7.2 Recipient scope and revocation

A notification may target a specific employee or an authorized role/Location audience, but list/count/mark-read/open requests must **revalidate current**:

1. Central workforce authentication and active Merchant membership.
2. Trusted selected Tenant/host agreement and eligible Tenant state.
3. Tenant-local role/permission and assigned Location scope.
4. Resource ownership and any required per-record authorization.

A saved recipient row or previously valid Location is **not permanent authority** after revocation. Notification counts, previews, read-state updates and links must not leak another Tenant's or Location's private order/customer data.

Central platform alerts must be scoped to Central-only privileges; Platform Super Admin is not automatically a Tenant customer-data reader.

### 7.3 Meaning and allowed effects

Approved business alerts can include pending stock review, overdue pickup review and other specifically approved staff follow-up. New order/Support alerts require owning Feature rules and proper recipient qualification.

**Read does not mean resolved**, and an alert never automatically confirms/cancels orders, reallocates stock, releases reservations or issues refunds. Exact event type, audience, urgency, status and retention rules belong to Feature Specs.

---

## 8. Provider-Agnostic Email Adapter — INT-01 / INT-02

### 8.1 Narrow technical capability

Expose only the operations actually needed for transactional sending:

- Submit a validated, authorized message or structured message payload.
- Normalize provider acceptance/rejection and message reference.
- Classify temporary, permanent, throttled and unknown send outcomes.
- Authenticate/parse provider delivery callbacks where supported.
- Query or reconcile uncertain sends **only if provider actually supports it**.
- Surface bounded request timing, provider rate metadata and safe error diagnostics.

Do not expose SDK-specific details to Orders or other business modules. No generic provider marketplace or all-channel integration framework is required.

### 8.2 Selection gate

Before live sending, verify actual capabilities and terms:

- Provider delivery regions, data handling, costs and quotas.
- Verified sender domain, SPF/DKIM/DMARC and provider identity alignment.
- HTTP API or SMTP behavior, message IDs and idempotency/reconciliation support.
- Callbacks, signatures, event retry behavior and evidence of delivery.
- Bounce/complaint suppression, recipient privacy and rate limiting.
- Secure credentials, rotation, reliability and operator support.

**No Email Provider has been selected or purchased by this architecture.** No provider-dependent delivered status may be invented.

---

## 9. Secure Webhooks and Tenant-Aware Reconciliation — INT-06

### 9.1 Authenticated, durable intake

1. Receive external provider callback on a dedicated HTTPS route.
2. Enforce selected provider's actual signature/authentication mechanism, payload validation, size control and anti-replay checks as specified by provider.
3. Persist a **verified, minimal durable receipt** before acknowledging success.
4. Return promptly, not after scanning all Tenant databases or carrying out long work.
5. Process asynchronously/idempotently with trusted Tenant resolution.
6. Apply only verified delivery-state updates to the owning Tenant's message record.

If the receipt cannot be persisted, do not return a false success response. Exact HTTP response codes, event IDs and timestamp windows are **provider-specific** and pending selection.

### 9.2 Trusted routing without Central PII archive

The shared platform provider needs a **minimal protected Central send-correlation routing index** mapping a known outbound send/attempt or provider message reference to an opaque Tenant delivery-intent reference.

- Never treat a callback-provided tenant_id, recipient, host or provider metadata as sufficient Tenant authorization.
- Correlate against a **registered outbound intent/send** and validate the correct Tenant/attempt before updating its record.
- Do not search every Tenant DB upon each callback.
- Minimize Central routing data; **no global customer email message archive** or broad customer/order replication.
- Unknown/mismatched callbacks are quarantined or rejected with safe diagnostics, never routed by guesswork.
- A callback may arrive before provider correlation is fully saved. Durable unmatched/early-event recovery must handle that race.
- Central receipt and Tenant update have **no distributed ACID**; maintain staged retry-safe state and reconcile partial failure.
- Store provider event identity/provenance and process repeated or out-of-order events without duplicate effects.

### 9.3 Provider event meanings

| Event / fact | State/evidence policy |
| --- | --- |
| Accepted | Accepted by provider, **not delivered** |
| Delivered | Record only with trusted provider delivery evidence |
| Bounced | Record bounce, classify and suppress according to approved policy |
| Complained | Record and stop inappropriate further sends |
| Rejected / failed | Safe reason classification and action |
| Duplicate | Idempotent no-op for already processed event |
| Unknown/malformed | No speculative status mutation or Tenant lookup |
| Unmatched/early | Quarantine and retry/reconcile, with monitored age |

Email callback **never** confirms an order, executes an external refund, changes stock or proves customer financial consent.

---

## 10. Outbound Resilience, Fairness and Security — INT-07

### 10.1 Laravel-native resilience

Use bounded connect/request timeouts, classified errors and limited retries/backoff with jitter and provider rate-limit compliance. Apply equivalent controls to SMTP/SDK clients when the selected provider is not a plain HTTP API.

Avoid an enterprise gateway, multi-provider failover router or heavy circuit breaker framework. **An adapter-local breaker/pause is optional** only if failure-load measurements justify it.

### 10.2 Failure isolation

- Business transactions and In-App alerts remain functional when external mail is down.
- Keep email workers logically separated or appropriately prioritized within Horizon, without requiring one worker pool per Merchant.
- Bound global provider concurrency and fair Tenant use so one heavy Merchant cannot starve other Merchant notifications or core workflows.
- Preserve durable backlog/failed attempts; avoid retry storms under prolonged outage.
- Apply secure inbound request controls without indiscriminately dropping legitimate callback retries.
- Avoid automatic repeat sends after ambiguous acceptance and avoid futile retries for invalid recipients.

### 10.3 Secrets, networks and privacy

- Email API credentials and webhook verification secrets are **Coolify Runtime Secrets**, not Git-tracked env files, Docker layers, Next.js public variables or CI build logs.
- The email adapter reaches the provider over authenticated TLS and the inbound webhook uses verified TLS and documented provider authentication.
- Apply least-privilege credentials, protected rotation, safe logging, constrained payloads and sanitized provider errors.
- Never put sensitive customer records, financial evidence, unrestricted email bodies or raw secrets into shared metrics and logs.
- If a credential or routing reference is missing, **fail closed**, not to another Tenant or a default provider account.

---

## 11. Operational Monitoring, Alerts and Recovery

### 11.1 Minimal signals

| Signal | Risk detected |
| --- | --- |
| Pending intent age, Outbox backlog and scanner heartbeat | Lost/stalled downstream intent even when Horizon idle |
| Horizon queue age, retries and failed jobs | Starvation or repeated send failure |
| Provider attempt/accept/delivery/bounce/complaint rates | Deliverability and sender reputation |
| Uncertain sends and unmatched webhook age | Duplicate risk and correlation gaps |
| Bad signatures, replay rejects and payload validation | Attack, provider misconfiguration |
| In-App polling errors and permission denials | Staff UX and Tenant/Location leakage |
| Shared provider and per-Tenant send volume | Quota exhaustion and noisy neighbors |
| Tenant context failures across worker reuse | Critical isolation risk |

Use existing Laravel logs, Horizon/Coolify and database monitoring with safe correlation IDs and privacy-limited references. Define numeric alert thresholds and worker budgets **only after representative testing**.

### 11.2 Safe operator recovery

1. Identify the owning Tenant/event/message and verify operator authorization.
2. Inspect durable local state and trustworthy provider evidence.
3. Classify known non-acceptance, accepted, uncertain or permanent failure.
4. Retry **same intent** only where safe, reconcile provider evidence or perform documented manual follow-up.
5. Record operator decision, timestamps and final outcome without altering unrelated business state.

Blind failed-job retries, deleting logs as “recovery” and assuming provider Accepted = Delivered are unsafe.

A scoped logs/status UI may be specified later; no enterprise delivery analytics platform is required by this decision.

---

## 12. Bilingual UX, Consent and Data Protection

- Test Arabic RTL and English LTR for email subjects, HTML/plaintext, in-app previews and dates/numbers.
- Preserve accurate transactional language: “refund requested” is different from “refund executed”.
- Determine recipient locale fallback and approved content per feature; not invented in infrastructure config.
- Do not expose private data in notification previews, unread counts, email URLs, headers, central diagnostics or callback payloads.
- Customer email address validity and manual follow-up follow governing D-118/D-119 decisions.
- Transactional mail and marketing are different legal/business purposes; **no marketing campaigns** authorized.
- Bounce and complaint suppression must protect shared sender reputation while respecting Tenant data isolation and privacy. Do not silently add global cross-Tenant identity matching.
- Message/attempt/webhook record retention and any rendering snapshot must have justified owner and duration, not a default universal 30/90-day policy.

---

## 13. Coolify Development Deployment and Blue-Green Compatibility

### 13.1 Current environment

The current VPS is **Development only**. Approved tooling is Coolify Native Applications, GitHub Actions + GHCR, shared Laravel Image for API/Horizon/Scheduler, separate Next.js Image, runtime secrets and private PostgreSQL/Redis. No configured mail service or actual successful sending is claimed.

Run development/test messaging through a safe mail-testing transport, sandbox or explicitly controlled test recipient setup. Do not send to real Merchant customers from Development.

### 13.2 Future Production

Production is **not provisioned**. Future deployment requires separate credentials, domains, provider callback configuration, databases, tenant-safe monitoring and tested release/recovery operations. Infrastructure Architecture governs the future approval path.

### 13.3 Release compatibility

During eligible rolling/Blue-Green overlap:

- Old/new workers must understand queued event schemas and template versions.
- Provider callbacks must continue to resolve to the same trusted intent despite deployment.
- Coordinate Horizon drain/restart and one effective Outbox scanner/scheduler.
- Apply compatible expand-first schema updates and controlled Tenant migrations.
- Do not claim that rolling update guarantees no downtime or exactly-once external email delivery.
- Rollback to an older image does not automatically undo database, file or provider-side effects.

---

## 14. Security, Functional and Capacity Test Matrix

**All rows are required future evidence; none are verified merely by writing this document.**

| Area | Minimum validation |
| --- | --- |
| Scope | No automatic activation of WhatsApp/SMS/social/marketing/payment/courier providers |
| Atomic intent | Domain transaction rollback saves no Outbox event; Redis outage after commit recovers |
| Business independence | Provider outage never undoes valid order/stock/finance fact |
| Logical duplication | Duplicate events/jobs create only one authorized logical notification |
| Legitimate repetition | Distinct order transitions or authorized re-sends are not accidentally collapsed |
| Provider uncertainty | Timeout after possible acceptance is reconciled/escalated, not blindly resent |
| Status evidence | Provider Accepted never mislabeled Delivered; late Bounce/Complaint preserved |
| Webhook verification | Invalid signature, replay, unknown provider event and oversized payload handled safely |
| Callback timing | Duplicate, early, delayed and out-of-order callbacks cannot corrupt Tenant status |
| Trusted routing | Forged tenant_id/recipient does not redirect to another Merchant DB |
| Real multi-Tenant | **Central + Tenant A + Tenant B** PostgreSQL and independent restricted runtime credentials |
| Worker reuse | Tenant A → Tenant B → Central with failures/retries; no leaked DB/cache/job scope |
| Staff access | Wrong membership, Location, recipient, linked resource, read/unread and count denied |
| Localization | Real Arabic/English templates, fallback, safe Merchant links, escaped variables |
| Quota/outage | Provider 429/5xx, queue backlog, fairness and no starvation of protected work |
| Secrets/PII | No confidential data in Git, image, public frontend bundle or broad logs |
| Deployment | Template/event version compatibility, Horizon drain, Outbox heartbeat and rollback preflight |
| Compliance | Verified sender domain, privacy/contact duties and legally eligible Pilot scope |
| Recovery | BA-16 and full off-server restore evidence before real merchant activation |

Use representative workloads and failure modes; do not assert a fixed message throughput, queue latency or arbitrary SLO before measurement.

---

## 15. Open Items, Deferred Configuration and Ownership

| Item | Status / next owner |
| --- | --- |
| **Email Provider brand/service** | **NOT SELECTED**; Notification/Infrastructure implementation decision |
| Sending domain, sender/reply-to, SPF/DKIM/DMARC | **PENDING VALIDATION** before live sending |
| Specific event/recipient/channel matrix | **NOT FINALIZED**; Orders/Fulfillment/Returns/Support Notification Feature Specs |
| D-118/D-119 exception procedure | Existing business rule governs; detailed action/UX belongs to relevant Feature Specs |
| Locale fallback and template variable registry | **FEATURE DETAIL PENDING** |
| Message DB schema, unique keys and retention | **FEATURE / DATA / SECURITY DETAIL PENDING** |
| Minimal central webhook routing identity, race handling | **INTEGRATION IMPLEMENTATION DETAIL PENDING** |
| Provider webhook signature and delivery event semantics | **PROVIDER-DEPENDENT, NOT YET SELECTED** |
| Retries, backoff, timeouts, send limits and suppression thresholds | **MEASURE AND CONFIGURE** |
| In-App polling interval and Notification UX | **FEATURE / CAPACITY DETAIL PENDING** |
| Provider circuit breaker | **OPTIONAL ONLY IF MEASURED FAILURE RISK** |
| Actual Laravel 13 / Stancl / Horizon integration | **TECHNICAL VALIDATION PENDING** |
| Production deployment | **NOT PROVISIONED** |
| **BA-16 Backup & Restore** | **DEFERRED; decision and tested off-VPS restore required before live Pilot** |
| **BA-30 G3** | **BLOCKED** pending BA-16 and verified recovery |

No missing implementation detail is permission to expand the approved MVP scope.

---

## 16. Feature Delivery Sequence and Pilot Gates

### 16.1 Future engineering order (not an implementation instruction)

1. Define approved customer/staff Notification Feature Specs: source event, permission, recipient, locale, missing address and business-accurate content.
2. Implement Tenant/Central notification intent and minimal module public contracts.
3. Connect to **existing** transactional Outbox and Redis/Horizon infrastructure.
4. Implement versioned bilingual templates and the provider-agnostic adapter using a safe test transport.
5. Add PostgreSQL attempts/status, message-level idempotency, ambiguous outcome and safe retry rules.
6. Implement current-authority Tenant-local polling/read-state APIs for Next.js.
7. For chosen provider, add secure durable webhook intake, minimal Central routing and reconciliation as supported.
8. Add provider DNS/sender verification, quotas, privacy, monitoring, operator recovery and integration tests.
9. Demonstrate Central/Tenant A/B isolation, worker reuse, delayed callback and deployment compatibility.
10. Activate real Pilot sending **only** after explicit production/legal/security/BA-30 gates, including BA-16 recovery.

### 16.2 BA-30 linkage

| Gate | Integration evidence needed |
| --- | --- |
| **G1 Tenant safety** | Tenant logs, callback routing, employee alerts, worker authorization |
| **G2 Commerce integrity** | Mail outcomes cannot change authoritative order/stock/money facts |
| **G3 Backup & restore** | **BLOCKED while BA-16 deferred**; verified recoverable off-server backup/restore |
| **G4 Releases** | Compatible events/templates, safe migrations, worker drain and callback continuation |
| **G5 Capacity** | Real provider/queue/polling load, alerts and numerical limits |
| **G6 Security/privacy** | Verified sender, callback authentication, secrets, scoped PII and log protection |
| **G7 Legal readiness** | Transactional contact and merchant/channel statutory obligations |
| **G8 Operational completeness** | Proper status messaging, manual exceptions, bilingual handling |
| **G9 Environment isolation** | Development test sender and future Production secrets/queues/data separated |
| **G10 Release sign-off** | Named owner, recorded evidence, approval and halt/recovery criteria |

No BA-30 gate is considered passed by architecture documentation. Real customer/merchant data and sending remain gated.

---

## 17. Final Approved Architecture Position

**INT-01 through INT-07 are APPROVED as Option A.** The user authorized completion of **INT-08** as one consolidated reviewed architecture document on the development branch.

The first MVP design consists of **module-owned business facts**, a **lightweight Notifications Module**, a **single platform-managed transactional Email Provider through a narrow replaceable adapter**, **system-owned versioned Arabic/English templates**, **PostgreSQL notification/delivery records**, **transactional Outbox + Horizon delivery**, **classified bounded retries and logical idempotency**, **authenticated provider webhooks with trusted Tenant routing** and **database-backed staff notifications with lightweight authorized polling**.

This design **does not authorize** SMS, WhatsApp, social marketing, payment gateways, courier APIs, automatic SaaS payment collection, Merchant SMTP management, WebSockets, a general integration gateway or Production deployment.

**Development only for now.** Production is not provisioned; the provider and technical settings remain undecided, required tests are unrun, **BA-16 is deferred**, and **BA-30 G3 is blocked**. Architecture acceptance is not implementation completion or Pilot readiness.

---

**Reference documents:** [Project Overview](../00-overview/project-overview.md) · [Backend](./backend-architecture.md) · [Module](./module-architecture.md) · [Tenancy](./tenancy-architecture.md) · [Queues & Events](./queue-jobs-event-processing-standards.md) · [Transaction Consistency](./transaction-consistency-architecture.md) · [Identity & Access](./identity-access-architecture.md) · [Infrastructure](./infrastructure-architecture.md)
