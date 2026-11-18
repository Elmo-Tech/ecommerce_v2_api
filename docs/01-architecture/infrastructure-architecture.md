# E-commerce SaaS V2 — Infrastructure & Deployment Architecture

**Version:** 1.0 — Approved Architecture Decisions  
**Date:** 2026-11-18  
**Target Repository:** Elmo-Tech/ecommerce_v2_api  
**Target Branch:** development  
**Target Path:** docs/01-architecture/infrastructure-architecture.md  
**Architecture Decision Status:** **APPROVED — INF-01 through INF-12 (Option A / approved amendment as applicable)**  
**Implementation & Deployment Status:** **NOT IMPLEMENTED OR VERIFIED BY THIS DOCUMENT**

> **Critical reading rule:** Approval of an infrastructure architecture is not evidence that Coolify, GitHub Actions, GHCR, PostgreSQL, Redis, Laravel, Next.js, health checks, backups, production hosting or live merchant workflows have been configured, deployed or tested. The current VPS is designated **Development-only**. Production has **not been provisioned**. BA-16 Backup & Restore remains **DEFERRED** and BA-30 G3 remains **BLOCKED** for any live Pilot. No Production launch is authorized by this document.

---

## 1. Purpose, Authority and Scope

### 1.1 Purpose

Define the operational infrastructure, container boundaries, release flow, environment isolation, security configuration, persistent storage, monitoring and migration coordination for E-commerce SaaS V2.

The design is **MVP-first**, deployable through **Coolify**, and compatible with the approved Laravel 13 / Next.js / PostgreSQL / Redis / Horizon architecture. It does not add a microservices layer, bespoke deployment platform, Kubernetes cluster or multiple servers solely for anticipated scale.

### 1.2 Governing architecture and source precedence

Read this file alongside the following existing architectural documents:

- [Backend Architecture](./backend-architecture.md) — BA-02, BA-03, BA-06, BA-09, BA-15 (amended), BA-16 (deferred), BA-17, BA-18, BA-19, BA-20, BA-21, BA-22 (amended), BA-23, BA-24, BA-25, BA-29 and BA-30.
- [Tenancy Architecture](./tenancy-architecture.md) — Central/Tenant DBs, conditional Stancl adoption, provisioning, migration orchestration, per-Tenant credentials and worker-context isolation.
- [Data Architecture](./data-architecture.md) — migration compatibility, integrity and gradual backfills.
- [Queues, Jobs & Event Processing Standards](./queue-jobs-event-processing-standards.md) — Horizon, transactional outbox, scheduling, retries, tenant job isolation and worker budgets.
- [Caching & Redis Architecture](./caching-redis-architecture.md) — workload namespaces, shared-service risks, session decision and memory/eviction protections.
- [File Storage, Media & Uploads Architecture](./file-storage-media-uploads-architecture.md) — Local Persistent Storage First, private/public storage, tenant namespace, exports and shared compatible file mounts.
- [Identity & Access Architecture](./identity-access-architecture.md) — authentication and least-privilege boundaries.
- [API Architecture](./api-architecture.md) — API and origin/contract boundaries.
- [Development Workflow](../WORKFLOW.md) — intended development/main branch flow; not evidence of actual branch protection.

**Later infrastructure clarifications made in INF-01…INF-12 govern the current deployment phase**, while the existing BA/TA/FS/QJ/CR safety invariants remain unchanged. In particular, the Backend Architecture's **first-Production single-VPS topology is a future target**: it does not mean that Production is deployed now, or that the present Development VPS will become the Production host.

If an exact operational mechanism cannot satisfy these architectural invariants with the installed Coolify version, stop and revise the implementation plan; do not silently weaken the requirements.

### 1.3 In scope

- Development VPS topology and future Production design boundaries.
- Coolify resources, Docker images and lifecycle management.
- Single reverse proxy, HTTPS, same-origin API routing and private networking.
- GitHub Actions build/testing, GitHub Container Registry (GHCR) and immutable deployments.
- Runtime secrets, PostgreSQL access tiers, persistent mounts and Redis workload behavior.
- Readiness, release checks, migrations, worker/scheduler coordination and safe rollback boundaries.
- Monitoring, bounded resources, load testing, scaling triggers and mandatory pre-Pilot gates.

### 1.4 Out of scope

Not selected here: concrete VPS provider/size, DNS names, operating-system hardening commands, final Dockerfiles, workflow YAML, Coolify credentials, automated production rollout, database provisioning execution, exact container resource limits, specific backup vendor or cadence, numerical SLOs/RPO/RTO, PgBouncer, Kubernetes, S3 migration or a production go-live date.

---

## 2. Approved Infrastructure Decision Register

| ID | Decision | Approved baseline |
| --- | --- | --- |
| **INF-01** | A — Lean Containerized Services | Independent logical runtime services on an initial single VPS: Next.js, HTTP-serving Laravel API/PHP-FPM, Horizon, Scheduler, PostgreSQL, Redis and ingress; no premature microservices. |
| **INF-02** | Approved amendment — Development VPS Now, Production Later | The **existing VPS runs Development only**. Production is not configured now; keep future environment/data/secret isolation and portability. Production hosting and sizing to be decided later. |
| **INF-03** | A — Single Reverse Proxy + Same-Origin API | One managed HTTPS ingress, trusted hostname/tenant routing, Next.js frontends and same-origin API path forwarding; DB/Redis remain private. |
| **INF-04** | A — Shared Laravel Image | Build one versioned Laravel image used by API, Horizon and Scheduler with distinct process configuration; separately version Next.js image. |
| **INF-05** | A — Explicit Host-Mounted Persistent Volumes | Controlled persistent server paths / Coolify directory mounts for stateful data and files. Container writable layers are disposable; persisted data is not a backup. |
| **INF-06** | A — Coolify Native Applications | Operate Laravel roles and Next.js as independently managed Coolify application resources; PostgreSQL/Redis as private stateful resources, not one giant Docker Compose application. |
| **INF-07** | A — GitHub Actions + GHCR + Coolify | Run CI, build/publish versioned images outside the VPS, then deploy the approved image digests through Coolify with auditable release coordination. |
| **INF-08** | A — Coolify Runtime Secrets + GitHub CI Secrets | Separate runtime configuration from image build; least-privilege registry/CI secrets; no real .env, private keys or passwords in repository/images. |
| **INF-09** | A — Coolify Health Checks + Safe Release Gates | Application readiness, worker/scheduler health signals, smoke testing, graceful stop and compatible rollback; never assume health checks alone guarantee zero downtime. |
| **INF-10** | A — Controlled Central/Tenant Migration Runner | Separate migration execution from container startup; preflight, controlled batches, per-Tenant version tracking, partial-failure records and schema compatibility. |
| **INF-11** | A — Lightweight Monitoring + Measured Scaling | Coolify and native Laravel/Horizon/PostgreSQL/Redis/host telemetry; risk-based alerts, bounded resources and optimization/vertical scaling before distributing services. |
| **INF-12** | A — Single Comprehensive Document | Consolidate INF decisions, risks, deferrals and validation in one architecture document; implementation and Pilot readiness remain separate. |

**Approval meaning:** these are architectural directions accepted for future implementation. Neither a configured server nor passed integration tests are implied.

---

## 3. Logical Deployment Topology

### 3.1 Development VPS — current phase

The Development VPS will host one Coolify-managed project/environment with these logical resources:

| Runtime component | Type / role | External exposure |
| --- | --- | --- |
| **Coolify-managed ingress** | TLS termination, hostname/path routing and eligible application updates | Public HTTPS only where authorized |
| **Next.js** | Storefront and merchant-facing frontend surfaces; independent image | Through the ingress |
| **Laravel API** | HTTP-capable application service backed by PHP-FPM | Only via ingress / approved internal routing |
| **Laravel Horizon** | Redis queue consumers for tenant-aware and Central work | **No public HTTP domain** |
| **Laravel Scheduler** | Controlled scheduled tasks / outbox coordination | **No public HTTP domain** |
| **PostgreSQL** | One DB server initially; one Central DB plus an independent DB for each Merchant | Internal only |
| **Redis** | Queue/Horizon and appropriately isolated Redis-backed workloads | Internal only |
| **Persistent storage** | PostgreSQL state, public/private app files, temporary/export artifacts as governed | Host-mounted; not directly exposed as a raw directory |

**Logical traffic path:**

1. Client -> HTTPS -> Coolify-managed reverse proxy -> Next.js for approved host.
2. Same-origin API request -> ingress rule -> Laravel HTTP service -> authorized Central or Tenant DB connection.
3. Laravel committed business intents -> PostgreSQL transactional outbox -> controlled relay -> Redis/Horizon -> tenant-authorized background processing.
4. Published public assets -> approved public delivery mechanism; private files -> current Laravel authorization before download.
5. Scheduler, Horizon, database and Redis connect through appropriately restricted internal networking.

This is not an instruction to create one Docker container per Merchant or expose direct PostgreSQL/Redis ports. It is also not a statement that a Coolify environment label alone enforces network isolation.

### 3.2 PHP-FPM is not the external HTTP server

PHP-FPM serves FastCGI, not public HTTP. The Laravel API application must include or be paired with a properly configured HTTP-facing web server/gateway (for example a release-compatible Nginx-to-PHP-FPM arrangement) that the Coolify proxy can reach. The precise in-image or sidecar topology, health target and port will be chosen and tested during implementation.

Use **PHP-FPM for the initial Pilot design**. Laravel Octane remains a measured, safety-gated future option, **not the chosen initial runtime** (BA-19).

### 3.3 Service separation without business-service decomposition

Separate containers/resources for lifecycle, security and resource management. Keep Laravel a **Modular Monolith** with module-owned contracts and data rules. Splitting Horizon or the scheduler into a separate container does not create an independent domain microservice or cross-database distributed transaction.

---

## 4. Environments, Branches and Promotion Boundaries

### 4.1 Environments approved

| Environment | Git branch | Present status | Data / traffic policy |
| --- | --- | --- | --- |
| **Development** | **development** | **Current VPS is designated for this environment**; actual setup still to be verified | Synthetic/test data; no real Pilot merchant data; isolated DB/Redis/files/secrets |
| **Production** | **main** | **NOT PROVISIONED; future planning only** | Must have distinct credentials, domains, DBs, queues, volumes, release approval and BA-30 readiness evidence |

No permanent Staging environment is selected. Do not create a Production database, environment, container, DNS record or deployment as a side effect of documenting this architecture.

The approved branch progression is feature/fix PR -> development -> tested integration, and later reviewed development -> main promotion. Actual GitHub Actions/branch protection settings need implementation and verification.

### 4.2 Future Production hosting

The pre-existing BA-17 baseline expects the **first Production deployment to be a single VPS**, subject to resource tests and acceptance; its exact host is **not selected**. A separate Production VPS is a reasonable future isolation proposal, **not an additional INF-02 approval**. The current Development VPS is not silently designated as Production.

Before a Production plan is approved, decide its physical isolation from Development, measured resource headroom, failure domain, domain strategy, recoverability, access control and operational ownership. Development and Production must never share live databases, Redis queue transport, session namespaces, writable business-file paths or secrets.

### 4.3 Configuration portability

Keep runtime endpoints, mounts, trusted hosts, certificates, secrets and worker budgets environment-driven. Preserve portable storage keys and one release manifest per deployed version. Future separation of API, workers, database or Redis to distinct machines must not require changes to core business invariants.

---

## 5. Coolify Resource Model — INF-06

### 5.1 Resource layout

Prefer **Coolify Native Applications**, not a single Git-based Docker Compose application for all runtimes:

- **Application — Next.js:** pulls its own prebuilt image and exposes its configured internal HTTP port through the ingress.
- **Application — Laravel API:** pulls the approved Laravel image digest and exposes an HTTP-capable internal application port.
- **Application — Laravel Horizon:** uses the **same approved Laravel image digest** with a worker-specific entrypoint/process; no internet-exposed application domain.
- **Application — Laravel Scheduler:** uses the **same approved Laravel image digest** with one controlled scheduler process and no public domain.
- **Stateful resources:** managed PostgreSQL and Redis where supported by the installed Coolify version, with private access and explicit persistence.

Creating separate Coolify resources does not automatically guarantee safe runtime overrides, private connections, persistent mount support, rolling update eligibility or process-specific health semantics. Verify these capabilities in the **installed** Coolify release before implementation.

Coolify Docker Image applications can pull versioned or digest-pinned images. A per-resource process entrypoint override (or an approved image-owned mode launcher) must be tested to ensure Horizon/Scheduler actually launch and stop gracefully. Do not build three inconsistent Laravel application copies.

### 5.2 Registry and private resource access

- Use the **same Laravel digest** for all three Laravel application roles within a release.
- Use a separate Next.js digest for the corresponding release.
- Authenticate the deployment host for private GHCR reads with a read-scoped credential and protect its lifecycle.
- Keep PostgreSQL/Redis reachable only on a required private network; no unnecessary host-published ports.
- Validate resource-to-resource DNS/connectivity, because Coolify project/environment labels by themselves are not a network or security boundary.
- Assign only the runtime variables, filesystem mounts and permissions needed by each process.
- Do not store domain credentials or raw database passwords in general app build arguments.

### 5.3 Application boundaries and lifecycle

API, Horizon and Scheduler share application code and dependency versions but not identical runtime responsibilities. Their deployments are coordinated at **release level** to preserve queue payload compatibility, schema version compatibility, safe Outbox scanning and a single effective scheduler.

Use Coolify for runtime orchestration and deployment operations; do not build a separate general-purpose deployment framework for the MVP.

---

## 6. Ingress, HTTPS, Tenant Routing and Networking — INF-03

### 6.1 Single managed ingress

Use a single Coolify-managed compatible proxy as the externally reachable ingress, with HTTPS, trusted domain routing, TLS renewal and controlled routing to frontend/API targets. The exact integrated proxy (**Traefik or Caddy**), DNS records and certificate handling remain implementation choices.

Only explicitly approved application endpoints are public. No PostgreSQL, Redis, Horizon or Scheduler public host ports.

### 6.2 Same-origin API contract

For browser sessions and the approved Laravel Sanctum/CSRF model, route frontend and API under a trusted **same-origin** request boundary where applicable. For a given authorized web host, the frontend handles page requests and the proxy forwards approved API paths to Laravel. Avoid introducing a separate browser-to-API origin and uncontrolled CORS dependency as the default architecture.

This does **not** mean all storefronts and staff dashboards share one unscoped cookie. Host, session and CSRF scope must match the Identity/Access and Tenancy designs.

### 6.3 Multi-tenant request safety

- Map approved platform subdomains/hosts through the trusted Central Tenant Directory.
- Validate active/eligible Tenant context and authorized staff membership for dashboard actions.
- Reject arbitrary untrusted Host/header/tenant parameter selection.
- If host context and explicit staff-selected Tenant both exist, require a match.
- Do not allow proxy rewrites to discard the effective trusted host/tenant authority.
- Test wrong-host, unknown-host, cross-Tenant, session-cookie and CSRF scenarios.

### 6.4 Network boundary

Use only required internal network paths, least-privilege database connections and firewall rules. Network reachability is **not** Tenant authorization. Enforce Tenant DB runtime role separation, Laravel policy checks and fail-closed worker/request context independently.

---

## 7. Images, GitHub Actions, GHCR and CI/CD — INF-04 / INF-07

### 7.1 Build once; deploy immutable artifacts

**GitHub Actions** is the approved build/test/publish system. A successful integration workflow on **development** creates:

1. One release-specific **Laravel image** (API + Horizon + Scheduler codebase and dependencies).
2. One release-specific **Next.js image**.
3. A traceable release record: source commit, image digests, test results and intended environment.

Prefer digest-pinned deployments; do not rely on mutable latest tags as release identity. Protect images and scan dependencies with risk-proportionate checks before release. Images must contain application code and runtime dependencies, **not** per-environment secrets.

### 7.2 Development release sequence

1. Reviewed feature/fix changes merge to development under the eventual CI policies.
2. GitHub Actions runs static checks and relevant Laravel/Next.js tests, including risk-based tenant/integration coverage.
3. Build immutable images and publish to **GHCR** only after acceptable gates.
4. Identify digests and coordinate updating Coolify Docker Image references for all affected resources.
5. Execute the controlled schema/release preflight and compatible migrations **as a distinct operation** (section 10).
6. Deploy/restart components in an order that preserves compatibility and avoids accidental duplicate schedulers.
7. Verify readiness, smoke tests, migration inventory, Horizon/Outbox progress and logs; record release outcome.

The actual mechanism for triggering a Coolify deployment or updating image references (approved API integration, secured webhook or explicit operator action) is an **implementation detail pending verification**. The documentation does not authorize creating GHCR tokens, modifying GitHub Actions or calling a deployment webhook now.

### 7.3 Future Production promotion

The approved future flow is reviewed **development -> main**, successful release gates, explicit human Production approval, a tested Coolify deployment/rollback plan and post-deploy observation. It must not be auto-enabled when the Development CI/CD workflow is established.

A successful Development deployment does not substitute for Production preflight, security, recovery, measured resource capacity or a formal BA-30 release sign-off.

---

## 8. Runtime Configuration, Secrets and Credentials — INF-08

### 8.1 Responsibility split

| Secret/configuration class | Approved custody | Restrictions |
| --- | --- | --- |
| Laravel APP_KEY, DB/Redis runtime credentials, private provider keys | **Coolify runtime environment/secrets** | No build-time image inclusion, no public frontend export |
| CI workflow credentials to build/push GHCR | **GitHub Actions secrets / scoped permissions** | Minimum repo/package privileges; no production DB access |
| GHCR image pull on deployment VPS | **Restricted registry login / Coolify host configuration** | Read-scoped, rotatable; do not reveal in logs |
| Tenant DB runtime credentials | **Protected environment/secret resolution mechanism** | Per-Tenant least-privilege DB role; opaque references in Central data |
| Provisioning/migration credentials | **Restricted release/provisioning execution context** | Separate from normal API and worker DB roles |
| Next.js public build configuration | **Explicitly public-only variables** | Never expose server secrets in client bundles |

Laravel APP_KEY must remain stable across the compatible processes/restarts of a given environment; do not regenerate it for each container. Development and future Production must have separate keys.

### 8.2 Coolify runtime-only controls

Coolify variables can be available at build time, runtime or both. **Explicitly mark runtime secrets as unavailable at build time** and prefer locked/safely scoped values; the default visibility configuration is not sufficient evidence of secrecy. Secrets must not enter Docker build arguments, image layers, Next.js public variables, source control, build logs or verbose exception payloads.

Use appropriately scoped shared variables only where needed. Since API/Horizon/Scheduler are separate Coolify applications, test their configuration agreement and prevent accidental drift. Restrict Coolify role membership and audit configuration changes.

### 8.3 Rotation and failure handling

Plan documented secret rotation, expiry, protected registry refresh, forced connection teardown where needed and safe restart/drain procedures. Missing tenant credentials or failed secret resolution **fail closed**: never substitute a Central, default or previous Tenant connection.

Actual secret-manager product choice, database credential retrieval mechanism, rotation automation and operator access matrix remain implementation/standards decisions.

---

## 9. Persistent Storage and Redis Safety — INF-05

### 9.1 Host-mounted persistence baseline

Use **explicitly managed VPS directories / Coolify Directory Mounts** for durable state that needs a host path, supported by the selected resource type and verified against its actual configuration. The exact folder paths, Unix owners, ACLs and mount definitions are **not fixed by this document**.

| State | Required treatment |
| --- | --- |
| PostgreSQL data | Durable exclusive state mounted outside container writable layers; the **same data directory must never be concurrently mounted read/write by two PostgreSQL server instances** |
| Public media | Approved published data only; safe public delivery, portable relative storage keys and Tenant scoping |
| Private media/documents | Non-public physical location; authorization on each eligible download |
| Export artifacts | Tenant-authorized private storage with job lifecycle and bounded cleanup |
| Temporary upload staging | Non-public, bounded and cleanup-controlled |
| Redis operations | Explicit persistence/failure policy where queue/session continuity requires it; validated memory/eviction behavior |

Coolify may offer **named volumes** as a product default for stateful services. INF-05 approves **explicit host-mounted control** as the preferred implementation direction; verify database/resource support before overriding a managed service's storage layout. Do not sacrifice a correct PostgreSQL data lifecycle or database-image upgrade procedure to force a mount shape.

### 9.2 Local shared storage under Blue-Green

When Production is later deployed on its approved host, **compatible old and new application releases** must see the same authorized production file state where required. A host-mounted compatible media path avoids storing uploads in replaceable application containers. It also creates shared-write risks: verify file naming, atomic publication, deletion/retention coordination and rollback compatibility.

**The local VPS remains a single failure domain.** A volume surviving a container restart does not survive every VPS/disk failure, and it is **not** a backup or substitute for disaster recovery.

### 9.3 Multi-tenancy and file ownership

Central and Tenant data must have strict logical namespaces. Module-owned business metadata governs lifecycle, publication and retention; Core Storage provides technical storage operations. Never infer file authorization solely from the path. Private downloads must validate the current requesting actor and tenant context.

The File Storage Architecture (FS-01…FS-08) remains authoritative for upload validation, access, export processing, cleanup and S3-ready abstraction. S3 is **future-ready**, not an initial required service.

### 9.4 Redis protection

One Redis infrastructure service may initially support queue/Horizon, scoped cache and other explicitly chosen workloads, but logical Redis databases/prefixes do **not** isolate memory pressure or process failure. Queue transport must **not** inherit a Tenant-specific cache prefix.

Prefer conservative memory behavior initially, then validate actual maxmemory/eviction/rejected-writes and operational persistence with representative workloads. If a shared Redis process threatens critical queue or session behavior, split Redis **processes by workload** when tests require it; do not create Redis per Merchant.

**Redis Session driver is not yet selected.** Do not assume sessions are deployed in Redis. Do not assume that Redis queue persistence alone replaces the PostgreSQL transactional Outbox or a verified recovery strategy.

---

## 10. Central/Tenant Migrations and Release Coordination — INF-10

### 10.1 Separate migrations from startup

**Do not invoke migrations automatically from each API, Horizon or Scheduler container startup.** Multi-resource startup and overlapping releases can cause duplicate execution or readiness before schema compatibility.

Use **Laravel Native Migrations** plus the conditionally adopted **Stancl Tenant migrations** and a lightweight, controlled, separately authorized **migration runner**. No bespoke enterprise workflow engine is needed.

### 10.2 Controlled rollout

1. Record the target release and required Central/Tenant migration inventory.
2. Preflight DB connectivity, runtime/migration credentials, disk capacity, active locks, schema versions and old/new code compatibility.
3. Apply the required **Central migrations** using migration-authorized credentials.
4. Apply **Tenant migrations in controlled batches**, respecting dependencies, concurrency budgets and lease/lock protections per Tenant.
5. Record each Tenant's progress/status and failed migration details in an appropriately secured Central rollout record; retain authoritative per-DB migration history.
6. For partial failures, classify, safely retry/resume or quarantine the affected Tenant and **block any unsafe release activation**.
7. Verify required schema compatibility before changing application traffic or restarting incompatible consumers.
8. Record post-release schema state and use a separately approved, tested **contract** phase for destructive changes when old readers/writers are retired.

Tenant provisioning uses the same migration definitions and cannot mark a Tenant ready with partial schema initialization. Migration/provisioning privileged roles are **not** ordinary application roles.

### 10.3 Compatibility and rollback

Favor expand-first migrations compatible with both old and new versions during eligible Blue-Green overlap. Larger data conversions use bounded, resumable backfills and integrity checks.

A failed migration affecting one Tenant must not be silently reported as overall success; it may be isolated only when the feature/release compatibility rules prove other Tenants safe to continue. Returning traffic to an older application **does not automatically roll back schema, data writes, queue messages or shared file mutations**.

Exact runner technology, operator workflow, batch/concurrency count, timeout and rollback strategy require implementation tests, not guessed defaults.

---

## 11. Readiness, Deployment and Blue-Green Safety — INF-09

### 11.1 Liveness vs readiness vs business progress

| Signal | Required interpretation |
| --- | --- |
| **Laravel API readiness** | HTTP application can serve relevant approved traffic and pass a lightweight essential dependency/configuration check |
| **Next.js readiness** | Its HTTP handler is serving correctly; not a permanent unconditional 200 placeholder |
| **Container liveness** | Process/container still running, **not** proof of business correctness |
| **PostgreSQL / Redis health** | Database/transport availability and actionable status, with no unnecessary public exposure |
| **Horizon progress** | Supervisors and workers plus queue lag, retries, failed jobs and tenant-context errors |
| **Scheduler/Outbox progress** | Scheduled-run heartbeat/progress and oldest Outbox event age; Horizon may appear idle if Outbox scanning is broken |

For eligible HTTP applications, configure Coolify-supported HTTP/CMD health checks with correct internal ports and required in-image health tools. Validate the active proxy's routing semantics. Worker and Scheduler resources need appropriate **process and progress checks**; do not invent a public HTTP endpoint solely to satisfy the app dashboard.

### 11.2 Blue-Green and Coolify eligibility

The governing **BA-18 target is Blue-Green / near-zero disruption**, **not unconditional zero downtime**. Coolify supports overlapping replacements/rolling updates for eligible Docker Image application configurations; **Docker Compose applications do not use Coolify's application-level rolling updates**. Even eligible applications can fall back to stop/start behavior under incompatible host ports, naming/networking or other runtime constraints.

Therefore, **using Coolify Native Applications is necessary for the selected approach but not proof of a successful Blue-Green deployment**. Verify with the installed Coolify version, actual port/network choices, readiness configuration and application behavior.

### 11.3 Safe release sequence

- Before switching traffic: validate build, tests, capacity for old/new overlap, mounted files, DB version compatibility and migrations.
- Bring up the new API/frontend release when compatible and probe readiness through the real proxy path.
- Switch traffic only if the replacement is healthy **and** tenancy, auth/session, storage and crucial smoke tests pass.
- Coordinate Horizon worker drain/termination/restart and ensure old/new job payloads and schema assumptions are compatible.
- Prevent duplicated effective schedulers and simultaneous unguarded Outbox scanning across overlapping releases.
- Observe error rates, API latency, queue age/failures, storage and database pressure after switching.
- Roll back **application images/traffic** only if the older image remains compatible with the current DB/files/queues. Otherwise stop for a controlled recovery plan.

If a single VPS lacks adequate RAM, CPU, DB connection or disk IO headroom for safe overlapping releases, require a documented maintenance window or defer the rollout. The platform must not claim Zero Downtime without passing resource and failover evidence.

### 11.4 Shutdown semantics

Set and verify process-specific graceful stop behavior and deadlines for HTTP requests, Horizon workers, scheduler tasks and one-off jobs. Do not kill a running business transaction in a way that creates duplicate money/stock effects; retain idempotent consumer/outbox safety for retried work.

---

## 12. Horizon, Scheduler and Outbox in a Multi-Release World

The approved queue/event architecture remains:

- Redis + Horizon for asynchronous jobs, with bounded, workload-sensitive supervisors.
- PostgreSQL-local transactional Outbox for durable intent following authoritative committed business transactions.
- At-least-once delivery; no claim of globally exactly-once external effects.
- Tenant-aware jobs: trusted tenant reference, reauthorization/state checks and cleanup between **Tenant A -> Tenant B -> Central** jobs.
- Protected important processing from heavy import/export starvation.

**Scheduled work must be single-effective for non-idempotent tasks**, achieved by a verified active-scheduler lifecycle and/or application-native distributed locks and business idempotency as appropriate. Correct locks, timeouts, queue contexts and heartbeat/progress signals matter during deploy overlap.

Outbox relay fairness and failure recovery remain QJ responsibilities, not a deployment-only Redis queue length check. A paused or failed scheduler must be visible even if Horizon reports no backlog.

---

## 13. Lightweight Observability, Resource Budgets and Scaling — INF-11

### 13.1 Minimum signal matrix

| Area | Observe | Why |
| --- | --- | --- |
| VPS / Docker / Coolify | CPU, RAM, disk free/IO, network, container restart state, deploy outcome | Shared-host exhaustion and failures |
| Laravel API | Requests, HTTP errors, P50/P95/P99 where measured, latency by route family, correlation IDs | Usability, errors and release regressions |
| Next.js | Availability, frontend/server-side failures and latency | User-visible release health |
| PostgreSQL | Total connections, active/idle use, slow queries, locks, IO, storage growth, tenant-version drift | Contention and tenant isolation |
| Redis | Memory headroom, rejected writes, eviction, latency, connection count | Cache/queue/session risk |
| Horizon / Outbox | Queue age, processing duration, failures/retries, protected vs bulk workload, Outbox backlog and scanner heartbeat | Business follow-up integrity |
| Scheduler | Successful run heartbeat, overlaps and missed schedules | Undetected stalled jobs |
| File storage | Capacity, failed writes, cleanup progress, public/private access failures | Local persistence and safety |
| Deployment | Readiness failures, overlap resource pressure, in-flight draining, post-deploy smoke results | Release safety |

Use native Coolify monitoring/logs plus standard Laravel/Horizon/DB/Redis/host instrumentation, introducing additional collection tools **only when measured needs justify them**. Coolify health is not a complete observability or transactional correctness system.

### 13.2 Limits and alerts

Implement safe resource bounds, connection caps and job workload limits **after representative measurement**. Do not fix CPU, RAM, queue worker count, max DB connections, backlog severity, SLO or availability percentages by guess.

Prioritize alerts for merchant-safety/tenant-boundary failures, missing recoverability, Redis write errors, aged Outbox or blocked migrations, database exhaustion and storage failure. Use privacy-limited tenant references; never log passwords, tokens, full private documents, unrestricted customer data or sensitive financial evidence.

### 13.3 Progressive scaling order

1. Profile real representative merchant/location/order/visitor/export patterns.
2. Optimize SQL, appropriate cache, queue fairness, IO and worker concurrency.
3. Increase single-host CPU/RAM/IO/storage where measurements support it.
4. Separate API, worker, Redis and/or PostgreSQL **when measured contention, security or failure isolation warrants**.
5. Consider horizontal/multi-host designs only after validating shared file access, tenant routing, secret resolution, data connections, workers and deployment compatibility.

A planning ambition of hundreds of Merchants is not proof that the present VPS can sustain that load.

---

## 14. Security, Operational Ownership and Change Control

### 14.1 Security baseline

- Coolify administrator access is restricted, MFA and least-privilege operator practices are expected under the subsequent security/operations standards.
- Protect Docker host access, SSH keys, registry tokens, GitHub Actions permissions and infrastructure credentials.
- TLS termination, verified host routing, CSRF/session boundaries and per-Tenant authorization remain mandatory.
- Expose only approved HTTPS endpoints; PostgreSQL/Redis/Horizon/Scheduler stay private.
- Maintain separate PostgreSQL roles for Central runtime, each Tenant runtime, tenant migrations/provisioning and infrastructure administration.
- Application runtime users must not have privileged Docker/socket or broad host filesystem access.
- Preserve incident-relevant audit trails for releases, secret rotation, schema changes and privileged operations.

### 14.2 Operational ownership

Specific names and runbooks are pending, but **named owners** must eventually cover: Development deploys, Production release approval, DB migration decisions, monitoring/alerts, secret rotation, scheduled-work recovery, restore drills, tenant provisioning failures and incident escalation.

Documentation can define gates without asserting that an on-call team or alert provider already exists.

### 14.3 No silent infrastructure expansion

This document does **not** authorize enabling production tenancy, publishing merchant applications, creating live databases, adding external services, running destructive migrations or activating payment/notification integrations without their own approved scope, tests and gates.

---

## 15. Architecture Risks, Constraints and Explicitly Deferred Decisions

| Item | Status | Required next action |
| --- | --- | --- |
| **BA-16 — Backup & Restore** | **DEFERRED; NO OPTION APPROVED** | Make separate architecture decision, provide recoverable **off-VPS** backups of Central DB, every Tenant DB and local file storage, perform full and per-Tenant restore drills, approve measured RPO/RTO before live Pilot |
| **BA-30 G3** | **BLOCKED** | No live Pilot or real merchant/customer data until backup choice and restore evidence are approved |
| **Current Development VPS** | **Designated; actual implementation not verified** | Confirm CPU/RAM/IO, Docker/Coolify version, resource limits, storage mounts and test data isolation |
| **Production infrastructure** | **NOT PROVISIONED** | Separately approve host/size/physical separation, domains, secrets, capacity, rollback and operations before launch |
| **Coolify integration** | **Selected; runtime configuration unverified** | Test image digest update, per-application entrypoint, private registry auth, HTTP gateway, mounts, network and health checks in installed release |
| **Blue-Green / Rolling update** | **Target; not verified** | Prove overlap, proper health routing, graceful stop, scheduler exclusivity, migrations and rollback under load |
| **Laravel 13 + stancl/tenancy v3.10.1** | **Conditionally selected** | Pass package compatibility, PostgreSQL and worker/cache/filesystem integration gates before committing to runtime |
| **Secret resolution and rotation** | **Policy approved; mechanics pending** | Verify protected runtime secret references, constrained privileges and fail-closed tenant DB connections |
| **Redis operations/session/persistence** | **Partial details open** | Decide Session driver, measure memory protection and determine operational Redis persistence/recovery settings |
| **Monitoring thresholds / capacity / SLOs** | **PENDING VALIDATION** | Benchmark representative load, mixed Tenant workloads and resource headroom |
| **Concrete host directories, DNS and image tags** | **Implementation details pending** | Choose after inspecting the actual VPS/Coolify setup; avoid invented values |
| **D-201 commerce rule** | **OPEN elsewhere** | Keep in Orders/Fulfillment feature decisions; do not resolve through infrastructure |
| **Production legal/privacy readiness** | **INDEPENDENT GATE** | Confirm applicable Egyptian tax, receipt/e-invoicing, privacy and customer-rights duties for enabled Pilot cohort |

### 15.1 Backup boundary

Neither a PostgreSQL persistent mount, a Coolify managed database resource, Redis persistence, a local copy nor a successful image rollback alone fulfills a verified backup and restoration strategy. **Do not implicitly approve snapshots, PITR, a vendor, a cron policy, external object storage or a backup timetable here.**

Business-data recoverability must be proved with off-server backup plus actual restoration of Central, Tenant and file content with authorization and integrity checks, as required by BA-30.

---

## 16. Required Validation and Acceptance Tests

These are **required future tests**, not completed results.

### 16.1 Development implementation checks

| Category | Representative validation |
| --- | --- |
| **Images** | One Laravel digest for API/Horizon/Scheduler; separate Next.js digest; no embedded credentials; reproducible redeploy |
| **Coolify resources** | Correct independent runtime command, service stop/restart, non-HTTP background resource handling and private registry pull |
| **PHP HTTP gateway** | Coolify proxy reaches actual internal HTTP handler, not raw PHP-FPM FastCGI |
| **Networking / auth** | TLS, trusted tenant host mapping, same-origin API/CSRF, no public DB/Redis ports or cross-Tenant routing |
| **Tenant safety** | Real Central + Tenant A + Tenant B PostgreSQL; runtime-role denial across DBs; fail-closed untrusted Tenant selection |
| **Jobs** | Tenant A -> Tenant B -> Central on reused workers; no connection/prefix leakage; retry/idempotency; protected queue priority |
| **Storage** | Container replacement preserves media and database state; shared old/new file view; private access and safe cleanup |
| **Migrations** | Controlled Central/tenant batches, locking, schema version tracking, failed-Tenant isolation, resumable retries |
| **Health / shutdown** | Readiness fails until truly ready, healthy routes served, graceful HTTP/worker shutdown within tested deadlines |
| **Scheduler / Outbox** | No duplicated irreversible scheduled side effects, scanner heartbeat visible, Redis outage/recovery handled |
| **Secrets** | Runtime-only values absent from build/image/client bundles; registry credentials scoped; failure does not fall back to wrong Tenant |
| **Capacity / alerts** | Measured normal and burst loads, heavy exports, disk/DB/Redis pressure, appropriate failure alert delivery |

### 16.2 Future Production and Pilot gates

Before any **live merchant activation**, produce documented evidence for all applicable **BA-30 G1–G10**:

| Gate | Infrastructure evidence focus |
| --- | --- |
| G1 — Tenant Safety | Real Central/Tenant authorization, DB roles, files, cache and worker isolation |
| G2 — Commerce Consistency | Concurrent stock, order, collection/refund idempotency and Outbox integrity |
| **G3 — Backup & Restore** | **BLOCKED until BA-16 approval and tested off-VPS full/tenant restore** |
| G4 — Releases | Tested CI/CD, compatible migrations, Blue-Green/rolling behavior, shutdown and rollback |
| G5 — Capacity/NFRs | Benchmarked numeric limits, headroom, alerts, resources and SLOs |
| G6 — Security & Privacy | TLS, secrets, cookie/CSRF, privileges, secure files, logging and response procedures |
| G7 — Legal Readiness | Merchant/channel-specific compliance review |
| G8 — Operational Completeness | Enabled flows' E2E tests, worker/notification/report operations |
| G9 — Environment Isolation | Development/Production DB/Redis/files/secrets/resource containment |
| G10 — Explicit Release Approval | Named release owner, recorded sign-off and stop/rollback criteria |

**Cross-Tenant access, duplicate irreversible stock/money effects, broken shared files, incompatible schema releases and unverified required recovery are blockers.** No numeric availability guarantee or production go-live date is approved here.

---

## 17. Implementation Sequence and Acceptance Boundary

The intended engineering order, only when separately authorized, is:

1. Inspect the current Development VPS, installed Coolify version, repo/project setup and security posture.
2. Prepare source-controlled Dockerfiles and application mode/entrypoint conventions; choose HTTP gateway for PHP-FPM.
3. Configure GitHub Actions tests/builds and GHCR image publishing with least-privilege credentials.
4. Configure private Coolify applications, stateful DB/Redis resources, mounts and network routing for **Development only**.
5. Add runtime secrets, health signals, Horizon/scheduler/Outbox safeguards and a separate migration runner.
6. Test Central + at least two Tenant DBs, dangerous failure paths, resource headroom and rollback compatibility.
7. Record repeatable Development runbooks, test results, operational alerts and performance evidence.
8. **Later:** separately approve Production topology, recovery (BA-16), BA-30 gates and explicit release before live use.

This sequence is a planning aid, not permission to make infrastructure changes. Development documentation, engineering and synthetic-data testing may proceed while BA-16 is open; live merchant activation may **not**.

---

## 18. Final Architecture Position

**INF-01 through INF-11 and INF-12 Option A are APPROVED as an MVP-first infrastructure documentation baseline.**

The current target is a **single Development VPS**, managed through **Coolify Native Applications**, with **one Laravel image shared by API/Horizon/Scheduler**, **a separate Next.js image**, **PostgreSQL Central plus independent Tenant DBs**, **Redis/Horizon**, **host-mounted persistent data**, **single HTTPS ingress and same-origin API routing**, **GitHub Actions -> GHCR -> Coolify releases**, **runtime-only secrets**, **controlled tenant migrations**, **health/release gates** and **lightweight, measured observability**.

Future **Production is not deployed or sized yet**. Blue-Green/near-zero disruption remains **conditional on actual Coolify/runtime compatibility, application behavior and VPS capacity**. Backup architecture **BA-16 remains deferred** and **BA-30 G3 remains blocked**. No implementation, security test, recovery rehearsal or Production readiness is claimed by writing this file.

---

## Official Implementation References (non-binding; revalidate during implementation)

- [Coolify — Docker Image Deployments](https://coolify.io/docs/applications/deployments/docker-image)
- [Coolify — Docker Registries](https://coolify.io/docs/applications/builds/docker-registries)
- [Coolify — Custom Docker Options / Entrypoint Overrides](https://coolify.io/docs/applications/builds/custom-docker-options)
- [Coolify — Rolling Updates](https://coolify.io/docs/applications/deployments/rolling-updates)
- [Coolify — Health Checks](https://coolify.io/docs/applications/configuration/health-checks)
- [Coolify — Networking](https://coolify.io/docs/core/networking-in-coolify)
- [Coolify — Persistent Storage](https://coolify.io/docs/applications/configuration/persistent-storage)
- [Coolify — Environment Variables](https://coolify.io/docs/applications/configuration/environment-variables)

**Documentation is not deployment. Architectural sign-off does not replace configuration, tests, backup/restore approval or the explicit Production Pilot release decision.**
