# E-commerce SaaS V2 — File Storage, Media & Uploads Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/file-storage-media-uploads-architecture.md`  
**Version:** 1.0 — Approved Lightweight File Storage, Media & Uploads Architecture Baseline  
**Prepared:** 2026-11-18  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — FS-08 (A)**  
**Decision Coverage:** **FS-01 through FS-08; all eight approved in architecture discussions**  
**Implementation Status:** **Not implemented, installed, tested, validated or deployed by this document**

> **Reading rule:** This document records approved architectural choices and the documentation plan. It is not evidence of deployed storage, completed file processing, validated Laravel 13 / Stancl filesystem integration, measured capacity, legal retention compliance, backups or authorization to launch live merchants. Detailed Feature Specs, security reviews, integration tests and BA-30 Pilot gates remain binding.

---

## 1. Document Overview, Authority & Scope

### 1.1 Purpose

Define a secure, practical and S3-ready file architecture for the **Laravel 13 API-first Modular Monolith** and **Next.js** storefront/merchant surfaces, serving independent merchants through **one Central PostgreSQL database plus a separate PostgreSQL database per Tenant**.

The MVP-first goal is to use **one shared technical storage capability** without turning business files into an oversized central business module. Keep merchant files isolated; distinguish published public media from private documents; validate uploads; preserve authoritative business ownership; make exports and cleanup resilient; and support the approved single-VPS, Blue-Green deployment approach.

### 1.2 Governing documents

Read this standard together with the following existing architecture and domain references:

- [Backend Architecture](./backend-architecture.md): **BA-15 amended** (Local Persistent Storage First + S3-Ready), BA-17 single VPS, BA-18 Blue-Green, BA-22 two environments, BA-24 monitoring, BA-25 testing, BA-27 hybrid reporting and **BA-30 Pilot gates**.
- [Module Architecture](./module-architecture.md): **MA-01…MA-09**, One Writer Principle, Core Infrastructure versus Business Modules, domain-owned metadata and lifecycle, and the open Support data-placement decision.
- [Tenancy Architecture](./tenancy-architecture.md): trusted Tenant Context, conditional Stancl integration, separate Central/Tenant databases, file isolation and worker cleanup.
- [Data Architecture](./data-architecture.md): database-local identifiers, lifecycle ownership, safe changes and **DA-06** retention/history rules.
- [Identity & Access Architecture](./identity-access-architecture.md): Central workforce identity/membership, Tenant-local roles, Location scope, independent customers and immediate revocation.
- [API Architecture](./api-architecture.md): Sanctum host-scoped sessions, same-origin API proxy, authorization, rate limits and trust boundaries.
- [Transaction & Consistency Architecture](./transaction-consistency-architecture.md): PostgreSQL transaction boundaries, recoverable asynchronous work and duplicate-safe handling.
- [Queues, Jobs & Event Processing Standards](./queue-jobs-event-processing-standards.md): approved QJ-01…QJ-08, Laravel Horizon, trusted scoped jobs, retries, idempotency and capacity controls.
- [Caching & Redis Architecture](./caching-redis-architecture.md): approved CR-01…CR-08, scoped cache and shared Redis workload boundaries.

Approved D-series business rules and future Feature Specs control business meaning. This standard does **not** invent a customer-document product feature, a new legal invoice requirement, unrestricted Platform Admin visibility or a retention period.

### 1.3 In scope and exclusions

**In scope:** file type and ownership classification; Central/Tenant and Production/Development separation; safe upload intake; public/private delivery; storage metadata and portable references; lifecycle and cleanup; async exports; Blue-Green file compatibility; security threats, monitoring, tests and open implementation decisions.

**Out of scope:** PHP controllers, migrations, final SQL schemas, physical directory names, URL/endpoint contracts, final file limits, vendor procurement, malware scanner selection, precise retention durations, guest download-token design, CDN purchase, S3 rollout, backup vendor/strategy selection and claims of passed tests.

---

## 2. Approved Decisions — FS-01 to FS-08

| ID | Approved choice | Binding direction |
| --- | --- | --- |
| **FS-01** | **A — Unified Storage Service + Module-Owned Business Lifecycle (Hybrid Lightweight)** | One technical Core Storage capability handles file operations and common technical metadata mechanisms. Owning modules retain business references, publication/retention rules, authorization and lifecycle decisions. No oversized global Files business module. |
| **FS-02** | **A — Shared Persistent Storage + Strict Tenant-Scoped Namespaces** | Local VPS-mounted storage shared as infrastructure, with trusted Central/Tenant scoping, distinct public/private areas and independent Development/Production storage. Path separation is not authorization. |
| **FS-03** | **A — Laravel Native Secure Upload Pipeline** | Laravel validation/storage, content and type checks, safe names, bounded inputs, non-public pending files, justified processing via Horizon and risk-based file scanning. No general media-processing framework for MVP. |
| **FS-04** | **A — Hybrid Public / Private File Access** | Serve *approved published public media* efficiently through the web server; all private downloads require Laravel-mediated current authorization before safe delivery. |
| **FS-05** | **A — Module-Owned Lifecycle + Lightweight Scheduled Cleanup** | Owning features decide when a file is replaceable, retained or deletable; Scheduler/Horizon perform bounded, retry-safe technical cleanup. No universal retention rule or general lifecycle engine. |
| **FS-06** | **A — Horizon Async Exports + Blue-Green Shared Persistent Storage** | Heavy authorized exports run as bounded tenant-aware jobs, store private completed outputs and recheck download access; Blue/Green share compatible production persistent storage. |
| **FS-07** | **A — Risk-Based Storage Security, Performance & Integration Testing** | Central + Tenant A/B isolation, hostile uploads, revocation, failed writes, cleanup, workers, export integrity, Blue-Green and representative load; lightweight native observability. |
| **FS-08** | **A — Single Lightweight Architecture Document** | One coherent, traceable document for FS-01…FS-07, explicitly preserving deferred details and pre-Pilot verification requirements. |

All choices are **approved at architecture level**, not automatically built or successfully tested. The options labeled A belong to their respective FS discussions, not to an unrelated BA/QJ/CR option.

---

## 3. Logical Storage Model & Responsibility Boundaries — FS-01

### 3.1 Conceptual model

```text
Next.js / API / Admin / Background Workers
                 |
       Owning Business Module
     (business policy + access)
                 |
        Core Storage Service
  (validate/store/read/remove/reference)
                 |
      Laravel Native Filesystem
                 |
  VPS Persistent Production Storage
    |                         |
 Central scope            Tenant scopes
    |                     Tenant A / B / ...
 Public / Private         Public / Private
```

This is a **logical design**, not a required class diagram, disk configuration or deployed directory tree. The same technical service can be invoked under distinct authorized contexts; common infrastructure does not imply common business data ownership.

### 3.2 Division of responsibility

| Responsibility | Owner |
| --- | --- |
| File-to-product/order/report/ticket business association | **Owning Business Module** |
| Who may upload, publish, download, replace or request deletion | **Owning Feature/Module** through current authorization and rules |
| Business retention, historical evidence and active references | **Owning Feature/Module**, with applicable legal/privacy review |
| File-system operations, portable storage references and common technical metadata support | **Core Storage capability** |
| Tenant Context initialization and cleanup | **Trusted Tenancy infrastructure**, under the invoking request/job |
| Current identity, entitlement, resource and Location authorization | **Identity/Access contracts and owning resource policy**, not filename or folder alone |
| Bounded deferred processing and cleanup execution | **Laravel Scheduler / Horizon**, under explicit trusted context |
| Provisioning, mounted volumes, web-server restrictions and capacity monitoring | **Operations / deployment configuration**, subject to readiness checks |

**One Writer Principle:** Storage infrastructure does not become the owner of Catalog, Orders, Sales, Support or Reporting business state. Module data and state transitions must pass through their owning contracts.

### 3.3 Metadata and references

- Support a **unified technical metadata convention** where justified: opaque storage key, logical scope, content type, size, integrity/digest when needed, storage visibility/classification, lifecycle/processing state and relevant audit/correlation data. This is a conceptual field set, **not a mandatory table schema**.
- Business modules own their file associations and business-relevant metadata. Reuse the technical mechanism without duplicating low-level storage code or centralizing all business records.
- Store **disk-independent logical references**, not absolute VPS paths, container paths, web-server URLs or S3-specific keys baked into domain logic.
- Keep Central metadata in an authorized Central context and Tenant-local metadata in the appropriate Tenant database; do **not** assume one Central table containing unrestricted references to all tenants' private files.
- Tenant-local numeric IDs can repeat across databases; a local ID alone is never a globally unique file locator or access proof.
- Technical metadata and file bytes can become temporarily inconsistent due to failures. Reconciliation and idempotent repair are required where risk justifies them; do not pretend a PostgreSQL transaction includes filesystem writes.

---

## 4. File Classification & Business Ownership — FS-01

| File category | Business owner / context | Default visibility | Lifecycle notes |
| --- | --- | --- | --- |
| Product and variant images | **Catalog / Tenant** | Private while pending; public **only once eligible and published** | Catalog publication and references control continued use |
| Merchant logo, banners and approved branding assets | **Merchant Settings & Content / Tenant** | Private until published; approved copies become public | Settings/content rules control replacement and withdrawal |
| Catalog import CSV/XLSX | **Catalog / Tenant** | **Private** | Validate/preview/process; feature-defined temporary retention |
| Order documents, eligible invoice/receipt artifacts | **Orders or applicable owning commerce document Feature / Tenant** | **Private** | Preserve legitimate business/legal history; do not erase on cancellation by default |
| Direct-sale/payment evidence, if approved as a Feature artifact | **Respective owning Sales/Collection Feature / Tenant** | **Private** | Business/legal retention and access specific to document type |
| Delivery, pickup, return or refund evidence, where supported | **Relevant Fulfillment / Returns / Refund Feature / Tenant** | **Private** | Only for approved feature workflows; no implicit new upload feature |
| Excel/CSV/PDF report outputs | **Reporting & Exports / Tenant or Central by report** | **Private** | Scoped job metadata, expiry, download and cleanup |
| Support request attachments | **Support / Cross-Context capability** | **Private** | Exact authoritative ticket/attachment metadata placement **remains open** in Support Feature Specs |
| Platform-owned administrative files | **Owning Central Module / Central** | **Private by default** | Platform authorization, audit and feature-specific retention |
| Customer-supplied private files | **Applicable authorized Tenant Feature, if introduced** | **Private** | Not a blanket MVP upload feature; customer/resource access must be designed |

The taxonomy is a **classification policy**, not an authorization to implement every example. No file is public merely because it was uploaded under Catalog or because it has a predictable URL.

**FBR-02-08/19 now decides** the authoritative Support ticket/attachment metadata location as Central Support with trusted Tenant/ticket ownership. Cross-context access remains explicit, least-privilege and audited; this does not grant Platform Admin universal merchant-data access.

---

### FBR-02-08/19 — Central Support Ticket and Screenshot Ownership

Support tickets and the metadata associating private screenshot attachments to those tickets are authoritative in **Central Support**, with a verified originating Tenant, actor and ticket reference; this is a controlled **Support-owned Central resource**, not an unrestricted Central archive of Tenant Orders/Customers/Refunds. The initial Support attachment allowlist is **JPG, PNG and WebP raster images**, stored privately under opaque storage references. Central Platform Support may retrieve only the exact authorized ticket files; Merchant-side access requires verified membership, role and ticket scope. Each new upload/download rechecks authority; unsafe formats, direct public URLs and guessable IDs never grant access. Processing, size/count budgets and retention remain Feature/security specifications.

## 5. Tenant Isolation, Environment Boundaries & Storage Keys — FS-02

### 5.1 Shared physical storage; separate logical authority

- Initial backend is **Laravel Native Storage on VPS-mounted persistent volumes outside disposable Docker layers**. Dedicated disks/volumes per merchant are not required for MVP.
- Production storage is a shared **production** resource. Blue and Green deployments must safely reach the same required production files.
- **Development and Production must use separate volumes, namespaces, permissions, hostnames and secrets.** Even if hosted on one physical VPS, Development must not read or write Production merchant files.
- Separate Central and per-Tenant logical namespaces. Within each context, keep **publicly deliverable media distinct from private and pending material**.
- Tenant scope is derived from a **trusted, verified internal Tenant identity**. Tenant labels, merchant display names, client-submitted paths, unverified host headers and raw request IDs are not storage authorities.
- Generate opaque, normalized internal file keys. Prevent traversal, symlink escapes, prefix collisions, absolute-path injection and cross-scope rename/move operations.
- Every storage operation obtains the current context explicitly or from a proven tenant-aware integration; always clean or reinitialize scope in long-lived workers.

**Illustrative namespace concepts only:** `Central -> private/public` and `Tenant A -> private/public` versus `Tenant B -> private/public`. Final volume paths, disk names and folder templates belong to implementation configuration and must be tested with the selected Stancl/Laravel version.

### 5.2 Authorization remains separate

A private read/write/delete requires, as applicable:

1. Trusted host/route to the correct Central or Tenant context.
2. Current authenticated identity, permitted guest capability or explicit system authority.
3. Active merchant membership and subscription eligibility when relevant.
4. Tenant-local permission, owning resource relationship and **Location scope** when relevant.
5. File category, visibility, processing status and owning Feature business policy.
6. Scoped lookup of the stored reference and an authorization-controlled delivery path.

**Fail closed** for missing/mismatched Tenant context, stale entitlement/permission, unresolved Tenant database, invalid customer scope, missing file metadata or unauthorized resource association. Never fall back to Central or a previously initialized Tenant.

A Platform Admin role alone does not grant arbitrary access to Tenant order, customer, support or financial files. The same rule applies to worker credentials and technical file keys.

### 5.3 Stancl compatibility gate

The approved tenancy integration remains **conditional**, specifically `stancl/tenancy v3.10.1` with Laravel 13. Verify its filesystem bootstrapping, URL/path interpretation, tenancy ending and Central handling with the actual mounted-volume configuration before relying on it. Do not invent a parallel custom tenancy/filesystem framework or assert this compatibility is already proven.

---

## 6. Secure Upload Intake & Processing — FS-03

### 6.1 Minimal safe pipeline

1. **Authorize intent:** resolve verified context, actor/guest identity, permitted Feature action and resource/Location/entitlement constraints before accepting a file.
2. **Bound request:** enforce per-Feature allowed types, byte sizes, file counts, rate limits and relevant total resource budgets. Exact numeric limits are **not yet approved**.
3. **Inspect content:** compare extension, MIME and detected content/structure; never trust the browser `Content-Type` or client filename by itself.
4. **Stage privately:** store an incoming file outside public delivery paths with a generated safe name; track pending/processing state as needed.
5. **Process safely:** re-encode/resize approved images where warranted; inspect import structure and bound rows/columns/resources; assess malware/active-content risk. Use Horizon for justified heavy operations.
6. **Finalize only on success:** create or activate the authorized module association and eligible storage reference; move/copy a publishable artifact into a public-delivery area **only after publication approval**.
7. **Handle errors:** report a controlled failure, leave existing active files intact and schedule eligible cleanup/reconciliation for uncommitted temporary artifacts.

The precise order of durable metadata writes and physical move/rename must preserve safe recovery. A partially written output or failed scan may **never** appear as a finished public image or downloadable private document.

### 6.2 Conservative MVP type baseline

| Usage | Initial candidate types | Notes |
| --- | --- | --- |
| Product / branding raster media | JPEG, PNG, WebP | Detect and validate actual image; processing and dimensions are feature-specific |
| Catalog import | CSV, XLSX | Strict parser/resource limits, validation and preview |
| Approved supporting document workflows | PDF where required | Private; enable only when the owning Feature authorizes it |
| Support attachments | Feature-specific limited allowlist | Do not choose broad formats before Support security and data ownership specs |

Do **not** enable unrestricted SVG, HTML, executable scripts, active documents or arbitrary archives as generic MVP uploads. A filename extension is not a security boundary. Scanner/vendor selection and whether additional scanning is mandatory are determined by documented risk assessment before enabling riskier formats; Option A **does not waive** required scanning.

### 6.3 Processing safeguards

- Prevent oversized decompression, image-parser exhaustion, spreadsheet bombs and unbounded background workloads; fail predictably when size/time/memory budgets are exceeded.
- Strip unnecessary image metadata when appropriate; never assume transformation makes malicious content safe without validation.
- Encode/sanitize output filenames and response headers; enforce safe content-disposition and browser content handling.
- Protect CSV/Excel import parsing from untrusted formulas/content. Generated spreadsheets must also mitigate **formula injection** from merchant/customer-controlled values.
- Do not serve upload staging directories through public web roots; prevent direct execution of uploaded content.
- Never log raw private file bodies, customer-sensitive document contents, filesystem credentials or temporary authorization secrets.

---

## 7. Public Media & Private File Access — FS-04

### 7.1 Published public media

- Public delivery is for **explicitly approved and published media**, principally storefront product imagery and approved merchant branding.
- The web server may serve those published artifacts directly without invoking a Laravel/PHP worker for every request.
- Pending, failed, unpublished and private originals must **not** be incidentally exposed by static routing. Publish from a validated artifact to a deliberately public surface, or use an equivalent design proving the same separation.
- Public media may be cached by browsers or intermediaries. Replacing/unpublishing an asset changes the authoritative publication state, but cannot guarantee instant removal of every previously cached public copy. Feature-defined versioning, cache policy and withdrawal expectations require testing.
- Public URLs are **not secrets**. Do not reuse public links for sensitive attachments or order/export documents.

### FBR-02-13 — No MVP Guest Private Order/Document Self-Service

Guest Checkout may proceed without mandatory email/account creation, but the MVP does **not** authorize Guest self-service download/track/list of private orders, exports, receipts or claimable documents by guessed order IDs, phone or stale checkout sessions. When required and lawful, an explicitly authorized Merchant workflow supplies order documentation through approved channels; the platform must not introduce an unapproved Guest private capability.

### 7.2 Private files

- Private order artifacts, import sources, exports, customer materials and support attachments must reside outside directly reachable public file routes.
- A request goes through an authorized Laravel API operation, verifies the **current** actor/guest capability and resource/Location/Tenant policy, then streams or delegates delivery through an access-controlled server mechanism.
- Authorization is checked before **each new download**. A cached permission grant or possession of a filename, opaque key, order number or local numeric ID does not authorize the file.
- Employee membership/role revocation blocks new private access. Client-specific order documents require explicit owner/guest authorization; **guest token design is not selected here**.
- Private responses must use appropriate content headers and cache controls to avoid unintentional public/proxy/browser sharing. Avoid loading entire large files into PHP memory when a safe streaming mechanism is available.
- No S3 presigned URL design is assumed with the approved local backend. Any future time-bounded local download tokens or internal web-server delivery require separate review and current-scope safety.

---

## 8. File Lifecycle, Retention & Cleanup — FS-05

### 8.1 Ownership and decisions

The **business-owning Feature**, not Core Storage, decides whether a file should remain active, be unpublished, replaced, retained for audit/history or become eligible for deletion.

- **Catalog/Branding:** retain referenced active media; replace without breaking current images; clean up old unreferenced artifacts only after a safe eligibility check.
- **Pending uploads/imports:** expire abandoned or failed temporary artifacts under Feature-specific rules.
- **Exports:** apply a Feature-approved availability/retention window; expiration removes download eligibility immediately, with physical cleanup possibly following asynchronously.
- **Order/sale/collection/refund records:** preserve documents and supporting evidence subject to approved business/legal needs, regardless of order cancellation or user removal where retention remains required.
- **Support:** follow the Support Feature's eventual file ownership, access and retention policy.
- **Account/merchant closure:** expiry of a subscription does not automatically erase its Tenant database or all files. Privacy deletion and justified retention exceptions need their own reviewed workflows.

There is **no universal retention duration**, global `softDeletes()` rule or automatic purge merely because a parent business record was soft-deleted. Soft deletion is not equivalent to erasure.

### 8.2 Safe replacement and deletion

1. Validate and successfully persist a **new** file before changing the active business reference.
2. Apply the owning module's authorized reference transition in its proper database.
3. Confirm the **old** file is no longer needed/referenced and is not protected by retention, audit or an in-flight process.
4. Record or schedule cleanup through a scoped, recoverable operation.
5. Delete only the eligible target under the same trusted scope. Failed deletion can be retried; missing/already-deleted targets must be handled idempotently.

PostgreSQL and the filesystem are **not one ACID transaction**. Compensate or reconcile orphaned bytes, dangling references and failed cleanup after crash windows; avoid falsely reporting a completed publication/deletion.

Use **Laravel Scheduler and Horizon where warranted** for bounded cleanup; include safe retries, logging and visibility. Do not blindly scan and delete all unreferenced-looking files across Tenants, bypass module retention rules, or rely on Redis TTL as the only proof a file may be erased.

---

## 9. Async Exports & Reporting File Integrity — FS-06

### 9.1 Export processing pattern

- Lightweight operational views may use efficient, authorized Tenant-local PostgreSQL queries.
- Expensive reports and CSV/XLSX exports use **bounded Horizon background jobs** with streaming/chunking, controlled concurrency and measured query/memory/time budgets.
- The **Reporting & Exports** domain owns export jobs, file metadata, status, eligible download and cleanup workflow. A Central report remains Central-scoped; Tenant operational reports stay Tenant-local.
- Accept the export request only after validating current actor, tenant membership, feature eligibility, report filters and Location/resource scope.
- Pass minimal trusted job references and an operation/correlation identity; never serialize unrestricted customer datasets, authentication sessions or database connections into queue payloads.
- On execution, resolve trusted Tenant/Central context anew and **recheck the initiating actor's current permissions, membership and applicable Location scope** before materializing user-requested sensitive data.
- On download, **recheck current authorization again**. A completed export is not permission to retrieve it indefinitely.
- Generate into a non-public temporary artifact; only mark **ready** after a complete, verified output has been finalized atomically or through an equivalent failure-safe process.
- Store the final artifact privately using a portable logical storage reference; expose only an authorized download workflow.

### FBR-02-20 — Financial Export Data As Of and Snapshot Integrity

Use **the start of actual report execution/read** as the data-as-of boundary, not the time the request entered Horizon. Persist/communicate meaningful requested, read/as-of and completed instants. A multi-query financial export must use a **transactionally consistent PostgreSQL snapshot or equivalent proven technique** for both rows and totals; ordinary chunking, repeated READ COMMITTED page queries and a mere timestamp do not prove a consistent snapshot. Avoid prolonged/unbounded holding of MVCC transactions: choose measured bounded techniques per Feature. Operational cached dashboards may use disclosed freshness; never report stale aggregates as current authoritative stock or financial state. D-176 late-recorded sales remain attributed to actual sale dates with traceable corrections; one Platform summary spanning multiple DBs is **not a cross-DB atomic snapshot**.

### 9.2 Retries, fairness and failure states

- Track meaningful states such as queued, processing, ready, failed and expired without claiming a specific schema.
- Jobs may run more than once. Use stable operation identity, scoped idempotency and safe output naming/promotion to prevent duplicate visible results or contradictory ready states.
- A failed/terminated worker must not leave a partially generated output downloadable. Orphan partials require controlled cleanup.
- Terminated jobs need safe recovery, bounded retries and an observable terminal failure. Never claim exactly-once queue delivery.
- Keep bulk export concurrency restricted so one heavy Tenant cannot exhaust PHP workers, PostgreSQL connections, memory, disk space or starve important queues.
- Preserve query authorization and appropriate snapshot/report semantics under the relevant Feature rules. Reports cannot become a route to bypass Tenant/Location or current permission boundaries.
- Data placed in CSV/XLSX cells must be handled safely to prevent spreadsheet formula injection.

No dedicated worker fleet per merchant, generic report engine or separate export-storage service is required at MVP scale.

---

## 10. Blue-Green Storage & Environment Compatibility — FS-06

### 10.1 Shared production volume

- Blue and Green are **application releases**, not separate merchant file universes. Both require safe access to **the same intended Production persistent data** during approved overlap/switch/rollback.
- Persistent mounts must outlive individual Docker containers; releases must not store authoritative files solely inside immutable image layers or disposable container writable layers.
- Storage reference representation, technical metadata and public path semantics must be backward-compatible across simultaneously active releases and a permitted application rollback.
- Treat file format/path migrations as controlled, compatible changes. Use expand-first metadata evolution and deliberate migrations instead of destructive rename/move during release overlap.
- Stage deploys, run health/readiness checks, coordinate scheduler and Horizon worker drain/restart, switch traffic only when compatible and observe storage failures during/after promotion.
- Old and new workers must not both process incompatible job payloads or race to mutate the same file without safeguards. Retried jobs re-establish clean Tenant context.
- Blue-Green application rollback **does not automatically roll back databases or file mutations**. Assess compatibility before announcing rollback safe.

### 10.2 Deployment boundaries

The only approved environments are `development` and `main`/Production, separately isolated. Do not point Development tests, workers or cleanup at Production storage.

Initial deployment is a **single VPS**, with capacity/headroom to run overlapping releases only if measurements show it is safe. Blue-Green is **not** multi-host High Availability or protection from disk/VPS loss.

### 10.3 Future S3 migration compatibility

Laravel storage contracts and disk-independent references make a **future migration possible**, not automatic. Any selected S3-compatible backend would still require explicit data copying, checksum/integrity verification, reference/URL and visibility checks, authorization equivalence, fallback/rollback planning and testing. **Initial S3 deployment is not approved.**

---

## 11. Threat Model & Security Controls — FS-03/04/07

| Risk | Required control direction |
| --- | --- |
| Tenant A reads Tenant B's private file | Trusted Tenant-scoped lookup plus current owner/permission check, fail-closed, independent Tenant A/B tests |
| Central admin accesses merchant secrets by role alone | Explicit approved Support/administrative capability, no blanket Tenant read |
| Traversal / forged storage path / symlink escape | Server-generated keys, normalized scope, safe filesystem primitives and no raw client paths |
| MIME spoofing / malicious parser content | Type/content validation, conservative allowlist, risk-based scanning, bounded processing |
| Hidden private upload served as public media | Non-public staging/private storage, separate publish transition and web-server route validation |
| Embedded active content / browser content sniffing | Safe supported formats, sanitized delivery headers, no execution in upload directories |
| Guessable download identifier / stale permission | Current per-request authorization, no path-as-permission or long-lived cached positive grants |
| Resource exhaustion / disk flood | Input limits, rate limiting, quotas/budgets where justified, queue fairness and host monitoring |
| CSV/XLSX formula injection | Safe serialization/escaping of untrusted values in generated spreadsheet formats |
| Wrong-Tenant cleanup | Scoped durable eligibility, revalidation, idempotency and tests on overlapping local IDs |
| Retained public asset after unpublish | Publication-state control, bounded caching policy and explicit limitations of public-cache withdrawal |
| Worker reuse or retry leakage | Reinitialize trusted context on every attempt; clean DB/Tenancy/storage mutable state; failure/retry tests |

All applicable file actions follow **least privilege**. Authorization at the UI layer alone is never sufficient. Logging should preserve request/correlation, safe Tenant scope, action and failure category without leaking sensitive file contents or secrets.

Security controls are requirements to **implement and verify**, not evidence that they are presently configured.

---

## 12. Storage Performance, Capacity & Observability — FS-07

### 12.1 Resource controls

- Direct web-server delivery for genuinely public media avoids routing each image read through Laravel.
- Private file delivery should stream safely, avoid unbounded memory buffering and use authorized delivery mechanisms compatible with local storage.
- Measure real upload/download latency, processing latency, concurrent transfers, image conversion overhead, export throughput and job waiting time.
- Track disk capacity, growth rate, available space, filesystem error rates, inode pressure where relevant, write/read IO contention, cleanup backlog and export artifact size distribution.
- Account for Blue-Green overlap, PHP-FPM/Horizon RAM/CPU, shared PostgreSQL connections, Redis and storage IO on the **same VPS**.
- Control large, concurrent or abusive upload/export work with validated inputs, appropriate API rate limits and measured concurrency limits.
- Consider scaling VPS resources or separating worker/storage services **only when evidence justifies it** under BA-29; do not assume a target number of merchants proves capacity.

### 12.2 Minimal operational telemetry

Use Laravel structured logs, Horizon dashboards and ordinary VPS/storage monitoring initially. Observe:

| Signal | Why it matters |
| --- | --- |
| Free capacity / growth / failed writes | Prevent production outages and missing artifacts |
| Upload rejection, validation and processing failures | Detect malformed or hostile files and usability regressions |
| Private download authorization failures | Investigate forbidden access without logging secrets |
| Export waiting/processing time and failed/retried jobs | Detect bulk starvation and incomplete reports |
| Cleanup eligibility, backlog, failure and retry | Prevent uncontrolled storage growth and incorrect removal |
| Per-scope file operation errors | Detect Central/Tenant isolation or mount problems |
| Blue-Green overlapping IO and compatibility failures | Detect unsafe deploy/rollback behavior |

No numeric service levels, worker counts, size limits, quotas, retention values or monitoring vendor are approved merely by this document. Set them from realistic Pilot profiles and focused measurements.

---

## 13. Fault Handling, Recovery & Data Integrity

### 13.1 Expected failure cases

| Failure | Required behavior |
| --- | --- |
| Validation or processing fails | Do not publish; preserve old active artifact; report controlled failure |
| Database association write fails after physical staging | Retain no false active reference; safely reconcile/expire orphan staged bytes |
| Storage write fails after a metadata intention | Never mark ready; retry/reconcile with bounded policy |
| Process dies mid-export | Partial file stays private and non-ready; recover or fail visibly |
| Cleanup deletion fails | Preserve an auditable pending cleanup state; retry idempotently |
| New access after staff revocation | Re-evaluate membership/permission and deny |
| Tenant unavailable or mismatched | Fail closed; do not use prior/default Tenant |
| Production storage mount unavailable | Return controlled error; do not silently redirect writes to ephemeral container storage |
| Blue-Green release cannot read older files | Stop/safely roll back traffic where compatible; repair compatibility rather than accept silent loss |
| Entire VPS or storage lost | Requires **separate approved off-VPS recovery design and tested restore**, not provided by local persistence |

If a critical committed business workflow requires durable post-commit follow-up, follow the approved **TC/QJ DB-local Outbox** rules. Do **not** enqueue every ordinary file operation into an Outbox by default.

### 13.2 Backup boundary

**BA-16 Backup & Restore architecture remains DEFERRED**. Persisting uploads on a mounted local volume is **not** a backup. The live Pilot requires an approved approach that can recover Central database, each Tenant database **and file storage**, including tested off-VPS recovery, appropriate completeness/integrity evidence and measured approved RPO/RTO.

**BA-30 G3 remains BLOCKED** until BA-16 and restore evidence are resolved. This standard does not select PITR, a snapshot cadence, a vendor or an automatic backup configuration.

---

## 14. Required Integration, Security & Release Tests — FS-07

### 14.1 Environment

Sensitive integration coverage must use real Laravel/PostgreSQL/Redis/Horizon/storage wiring where applicable, with a **Central database and at least two independent Tenant databases**. Validate conditional `Laravel 13 + stancl/tenancy v3.10.1` filesystem and queue bootstrapping before asserting compatibility.

Mocks are useful for unit tests but **cannot replace** cross-tenant filesystem authorization or real mount/Blue-Green behavior evidence.

### 14.2 Minimum test matrix

| Test group | Required evidence |
| --- | --- |
| Namespace safety | Central, Tenant A and Tenant B files stay segregated; local-ID collisions cannot resolve another scope |
| Access control | Correct actor, wrong Tenant, wrong Location, unauthorized Platform role, unprivileged customer and revoked membership cases |
| Host/proxy context | Forged Tenant headers/paths/host input cannot change trusted file authority |
| Private delivery | Direct URL/static-server attempts fail; no private source under web root; headers/cache behavior safe |
| Public lifecycle | Only eligible published copies served; staging/unpublished copies inaccessible; withdrawal/cache limitations demonstrated |
| Hostile upload | MIME spoof, invalid image, oversize/count, traversal, active formats, dangerous filename, parser/resource stress |
| Safe replacement | Failure at every stage preserves old active reference and prevents premature deletion |
| Cleanup | Duplicate/retried deletion, cross-Tenant reference confusion, protected retention and orphan reconciliation |
| Async export | Revoked actor before execution, revoked actor before download, scoped filters, partial output failure, duplicate jobs |
| Shared worker | Tenant A -> Tenant B -> Central on reused Horizon worker, also after exceptions/retries; no leaked storage/DB context |
| Resource pressure | Burst uploads, concurrent exports, heavy merchant fairness, disk pressure, memory and IO metrics |
| Blue-Green | Both releases read/write compatible files, worker drain, old/new job compatibility, safe application rollback |
| Environment isolation | Development cannot access Production files/credentials; no accidental fallback to ephemeral storage |
| Recovery | Real off-VPS backup + restore drill when BA-16 is separately approved; no live Pilot without it |

Unexpected cross-Tenant file exposure, privilege bypass, false-ready exports, destructive cleanup, unsafe release compatibility or unverified required recovery are **release blockers** to the extent applicable under BA-30.

### 14.3 Progressive validation

- **Feature PR:** unit/API/contract checks plus relevant scoped file tests.
- **Integration:** real Tenant A/B/Central, storage mount, queue context, authorization and failure-path checks.
- **Pre-Pilot:** representative load/fault/deploy/restore evidence, legal/privacy retention review, operational runbooks and all applicable BA-30 gates.

Approving FS-07 does **not** mark any test as passed. Record actual test results, environment, selected configuration and evidence separately.

---

## 15. Open Items, Deferred Detail & Owners

| Open item | Where / when to resolve |
| --- | --- |
| Final storage disks, paths, mounts, web-server static/private rules | Infrastructure configuration and tested deployment |
| Exact file type allowlists, MIME inspection, maximum sizes/counts and quotas | Owning Feature specifications + security/performance tests |
| Selected image transformations, dimension limits and processing libraries | Catalog / Merchant Settings Feature specifications |
| Malware scanning requirement and vendor for specific file categories | Security risk assessment before enabling those categories |
| File metadata schema, logical keys, status fields and unique constraints | Data/Feature specs respecting Central versus Tenant databases |
| Publication, versioned media URLs, stale public cache and unpublish semantics | Catalog / Merchant Settings Feature and CDN/browser-cache testing |
| Customer/guest document access proof and optional expiring URLs | Orders/Customer access feature specifications, with security review |
| Support ticket/attachment authoritative metadata placement and allowed Central access | **FBR-02-08/19 APPROVED:** Central Support metadata, ticket-scoped approved access and limited private image attachments; exact tables, retention and UX in Support Feature. |
| Historical legal retention, privacy erasure, upload/import/export retention periods | Owning Features with Egypt-specific legal/privacy review |
| Export status schema, streaming/chunk size, concurrency, limits, retry/backoff/timeouts | Reporting & Exports Feature, QJ and load testing |
| Laravel 13 / Stancl v3.10.1 filesystem + Horizon integration | Actual package/runtime integration tests; **conditional** |
| Production/Development volume isolation and Blue-Green read/write/rollback headroom | Infrastructure and release preflight testing |
| Storage metrics thresholds, disk budgets, measured SLOs and alerts | BA-24/29/30 performance and Pilot acceptance |
| **BA-16 off-VPS backup/restore selection and verified recovery** | **Separate deferred BA-16 decision; mandatory before live Pilot** |

No entry here silently changes an approved D-series business rule, makes a deferred Feature part of MVP, or approves a previously open BA/Support decision.

---

## 16. MVP Implementation Boundary & Traceability

### 16.1 Included lightweight baseline

- Shared Core Storage capability using Laravel Native Storage and safe, portable logical references.
- Module-owned file relationships, access rules and lifecycle; one technical storage mechanism rather than one central business-files owner.
- Trusted Central/Tenant scope and Public/Private segregation on local persistent VPS mounts.
- Native upload validation, constrained file types and non-public processing; optional bounded Horizon processing when justified.
- Direct web-server access to **authorized published** public media, Laravel-authorized private downloads.
- Bounded Scheduler/Horizon cleanup with safe replacement and retry/reconciliation.
- Tenant-aware private async exports, safe output publication, current permission rechecks and Blue-Green production file compatibility.
- Risk-based real integration tests, modest native monitoring and explicit Pilot gates.

### 16.2 Deliberately not selected

- Dedicated physical disk/volume per merchant at MVP start.
- A large generalized Files/Media business module that owns every domain's business metadata, permissions and retention.
- A custom media-processing framework, universal file-retention engine or enterprise storage monitoring suite.
- Upload-anything or default-public semantics; arbitrary SVG/executable/archive uploads.
- Initial S3/CDN purchase, mandatory S3 presigned URLs, distributed file system, multi-node High Availability or one storage namespace across Production and Development.
- Per-Tenant Redis/Horizon workers or an unbounded export processing pool.
- Unrestricted Central administrative access to Tenant business documents.
- Universal automatic deletion when a subscription expires or a record is soft-deleted.

### 16.3 Relationship to the existing decision register

| Existing decision | FS alignment |
| --- | --- |
| **BA-15 (AMENDED)** | Local Persistent Storage First; S3-ready portable abstractions; public/private separation |
| **BA-17, BA-18, BA-22** | Single VPS, controlled Blue-Green overlap and isolated Development/Production |
| **BA-27, BA-29** | Bounded reporting/export work and measured scaling |
| **MA-01…MA-09** | Core technical service, One Writer, Feature-owned lifecycle and Public Contracts |
| **TA-01…TA-08 / IA-01…IA-09** | Trusted Tenant/identity isolation, current authorization and conditional package integration |
| **DA-06** | Feature-specific retention, historical protection and non-universal soft deletion |
| **QJ-01…QJ-08** | Horizon, trusted jobs, authorization rechecks, safe retries, idempotency and worker cleanup |
| **CR-01…CR-08** | Shared Redis/cache consistency without stale authorization or transport-prefix leakage |
| **BA-16 / BA-30 G3** | Backup choice still deferred; verified off-VPS restore remains a blocker for live Pilot |

### 16.4 From architecture approval to delivery

1. Keep this document as the **single FS architectural baseline**.
2. Resolve implementation-specific requirements in the owning **Feature Specs** (`spec.md`, `plan.md`, `tasks.md`, tests and suitable PRs), without rewriting earlier approved business rules.
3. Implement Core Storage and feature integrations incrementally under `development`, with risk-proportionate reviews and tests.
4. Verify Central + Tenant A/B storage isolation, real package integration, safe upload/download/cleanup/export behavior, production mount compatibility and measured resource capacity.
5. Satisfy **all applicable BA-30 G1–G10**; BA-16 recovery and other release conditions cannot be waived by completion of FS documents.
6. Promote code to `main` only through the separately approved review and release workflow, with explicit sign-off.

---

## 17. Final Architecture Position

**FS-01 through FS-08 are APPROVED as an MVP-first architectural baseline.** This standard preserves the approved **one shared technical Storage Service with per-Module business ownership**, **strict Tenant isolation**, **private-by-default intake**, **explicitly published public media**, **authorized private downloads**, **safe retention/cleanup**, **Horizon-driven exports** and **Blue-Green persistent storage compatibility**.

The architecture is **S3-ready but Local Persistent Storage First**. It intentionally stays small until Feature specifications, real measurements and tested operating constraints justify additional complexity.

**Documentation completion is not implementation completion or Pilot readiness.** In particular, conditional Stancl/Laravel compatibility, Support attachment placement, legal retention, operational limits, actual tests and **BA-16 verified off-VPS recovery** remain unresolved as stated above.
