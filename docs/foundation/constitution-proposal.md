# E-commerce SaaS V2 — Proposed Spec Kit Constitution

> **Status:** DRAFT FOR OWNER REVIEW — not ratified and **not** an output of `/speckit-constitution`.  
> **Prepared:** 2026-11-23 | **Version:** Reviewed Draft 0.2.0 | **Ratified:** PENDING — requires explicit owner approval.  
> **Canonical file already created by CLI:** `.specify/memory/constitution.md` currently holds the **default placeholder**; replacing its content requires explicit owner approval of this proposal.

## Core Principles

### I. Decision traceability and bounded scope

Every Feature MUST cite relevant current approved D-series business rules, OV MVP scope, approved Architecture/Standards, and applicable FBR-02 refinements. Authority is domain-specific: D-series determines business behavior; OV determines MVP/Pilot scope; Architecture determines technical boundaries; Standards determine engineering practice; an approved Feature spec/plan refines the implementation. Where a newer explicit owner approval refines a previous document, retain historical provenance and cite the refinement; never silently resolve a conflict or replace the business source of truth. Do not manufacture a decision, silently settle D-201, or promote a deferred feature into MVP. A material unresolved requirement, security exception, legal obligation or owner decision MUST be clarified before the affected implementation. Limit work to an approved Feature and its direct dependencies.

### II. One modular monolith, explicit business ownership

The Backend MUST remain one Laravel API-first Modular Monolith as approved. Each domain has **one authoritative business-data writer**; other modules call narrowly typed, provider-owned Public Contracts rather than writing internal tables. Register Modules explicitly through reviewed Module Service Providers/Registry; no unbounded runtime folder discovery, unnecessary 1:1 physical modules for the 16 logical domains or empty layered boilerplate. Keep Controllers thin and business rules in appropriate Actions/Domain services. Use Eloquent in owning modules and repositories only when a real boundary warrants one.

### III. Trusted multi-tenancy and least privilege

Central PostgreSQL owns the Tenant Directory, Central workforce identities/memberships and Platform state; each Merchant has a separate Tenant PostgreSQL DB for its operational data with approved isolated credentials. Enforce the **correct execution context and actor**, not a universal workforce-membership flow: Platform is Central-only by default; Merchant Dashboard uses the Central workforce session plus **current Central Tenant membership** and Tenant-local Role/Location/Resource authorization; public Storefront resolves a Tenant from the trusted subdomain and permits only approved public/guest actions; authenticated Customer uses the verified storefront Tenant and its separate Tenant-local customer guard and ownership rules. Every request/job/file/export MUST establish the correct trusted scope, relevant lifecycle/entitlement and current authorization; high-risk effects revalidate authority when required. A Tenant URI/Host/parameter selects context but is never permission. No cross-Tenant leakage, implicit Platform impersonation, global mutable active Tenant, cross-DB ACID assumption, fallback to another DB or stale positive Redis grant. Clear connection, execution and transient authorization context after requests, workers, exceptions and retries.

### IV. Financial and inventory truth over convenience

Authoritative stock reservation, order confirmation, actual collection/refund and required durable Outbox intent MUST use the owning **single PostgreSQL database** transaction with appropriate locks, constraints, concurrency and business/HTTP idempotency. Do not assume a transaction spanning Central plus separate Tenant DBs. Preserve one eligible fulfillment Location per order in the approved MVP, distinguish order/delivery events from actual collection, and preserve historical snapshots with auditable corrections rather than silent overwrites. Store monetary minor units as BIGINT and expose exact decimal-string minor units with currency context in JSON; serialize BIGINT identifiers as decimal strings. Preserve approved exact manual-discount approval ceilings, financial timezone and Tax Input Mode locks. Redis, queues and report projections MUST NOT substitute for authoritative PostgreSQL money/stock state.

### V. Reviewed API and security contracts

Use one versioned `/api/v1` REST JSON contract with its four approved logical API surfaces, camelCase application JSON/query fields, consistent response/error envelope, explicit HTTP status/errorCode mapping and reviewed OpenAPI/compatibility changes. Merchant Tenant identifiers in API paths are selectors, not credentials; scope authorized collections **before** filters, counts and pagination. Apply approved first-party Sanctum sessions, verified Hosts, CSRF and actor-specific authentication/authorization; actual CSRF mismatches map to **403/CSRF_TOKEN_MISMATCH**. No private shared Next.js SSR cache, Guest private self-service, blanket Platform-to-Tenant access, or fail-open sensitive rate limits when required protection is unavailable. Revalidate current permissions for protected files, exports and user-initiated deferred work according to risk.

### VI. Durable post-commit work and protected data

Queue consumers MUST use trusted execution context, transactional Outbox for required durable event intent, at-least-once delivery and consumer/business-effect idempotency, bounded retries, correlation and observable recovery. External uncertain financial effects need explicit reconciliation. A commercial Tenant suspension preserves previous confirmed obligations and durable Outbox intent, but stops new commerce and permits only **explicitly eligible** prior obligations under current authority; unavailable/security/legal suspended contexts fail closed. Store private documents, Support screenshots and exports with owner-appropriate authorization and current checks; file possession never establishes access. **Separate technical logs/metrics from domain-owned security/business Audit and authoritative original records**. Sensitive committed outcomes require DB-backed evidence, not a success log, completed job or provider acknowledgment. Use safe structured/correlated logging; do not expose Tenant data, secrets or confidential payloads in logs.

### VII. Testable specifications and honest evidence

Each Feature MUST have one canonical reviewable `spec.md` including positive/negative behavior, a `plan.md` demonstrating architecture/constitution fit, and `tasks.md` with dependencies and risk-selected tests. Record **actual commands, environment, revision and outcomes**; documentation, mock-only checks, a green unrelated check or approval is not production evidence. Applicable **Critical/High** risk checks MUST pass before the affected merge to `development`; relevant multi-Tenant security/concurrency/outbox coverage requires real PostgreSQL Central plus at least two Tenant DBs and real Redis/Horizon once applicable dependencies are available. Trivial documentation changes do not require heavy suites. Pest/PHPUnit, Pint, PHPStan/Larastan and targeted API/architecture tests are selected only after verifying version/package compatibility; failed or unrun tests must be reported as such, never described as passed.

### VIII. Simplicity, version compatibility and release gates

Prefer native Laravel, thin extensions and measured optimization instead of speculative frameworks, microservices, generic repository/event frameworks or speculative infrastructure. Laravel 13 / Stancl v3.10.1 / Sanctum / Redis-Horizon interoperability remains **conditional on real tests**; initial API runtime is PHP-FPM. Use explicitly owned safe Central/Tenant migration tracks, per-Tenant schema compatibility gates and least-privilege credentials; never run Tenant schema migrations automatically at every app/worker startup. Follow reviewed feature/fix PRs to `development` and separately authorized promotion to `main`; branch naming is not proof of active CI or protection. **BA-16 Backup/Restore remains DEFERRED, BA-30 G3 remains BLOCKED**; no live Pilot without independently approved, tested off-VPS recovery and all applicable BA-30 G1–G10 gates, including merchant/channel legal eligibility.

## Constraints and working boundaries

- Backend: Laravel 13 API-first Modular Monolith, PHP-FPM first; stancl/tenancy v3.10.1, Sanctum, PostgreSQL, Redis/Horizon integration **conditional on real compatibility tests**. Next.js is a separate frontend deployment/context, and host/proxy/session behavior needs real browser validation.
- Module descriptions: `docs/03-modules/<module>/module-overview.md`; Feature specs: `specs/<number>-<feature-slug>/`. Never maintain two editable copies of one Feature spec.
- MVP scope, feature ownership and cross-module contracts MUST follow approved existing documents. The detailed feature API/DB schema, numbers, fallback behavior and operational runbooks are only fixed by approved Feature plans, not this draft.
- No implementation code, migrations, Docker/CI deployment or production access is authorized merely by approving this Constitution.

## Development workflow and quality gates

1. Identify the owning module and relevant approved sources.
2. Draft and review `spec.md`; resolve blocking clarifications.
3. Draft and approve `plan.md` with current environment/dependency compatibility, Contracts, migration/security/tenancy and test strategy.
4. Generate `tasks.md`; run consistency checks and require a reviewed Feature branch/PR.
5. Implement/test only after explicit owner approval, with traceable negative/security test evidence as required by change risk; satisfy applicable Critical/High gates before affected merge.
6. Reconcile changed design assumptions with upstream Standards, Module Overview and approved source decisions; record privileged Audit and safe diagnostic/operational evidence when relevant, rather than silently rewriting business history.

## Governance

This proposed Constitution **summarizes** explicit approved D/OV/BA/MA/API/AUTH/AUTHZ/DB/SEC/LOG/TEST and FBR-02 decisions; it does **not** replace or silently outrank their domain-specific binding authority. A contradiction MUST block the affected work until the owner explicitly resolves it; never use a Constitution edit to bypass a deferred Pilot gate. Every Feature plan/PR MUST include a Constitution compliance review and reference relevant governing decisions.

Amendments require: (1) explicit owner approval, (2) documented rationale/source decision, (3) impact assessment for active Feature specs, tests and migrations, (4) a reviewed PR, and (5) updated version/date and affected references. The proposed versioning convention is MAJOR for incompatible governance/removed safeguards, MINOR for new/expanded rules, PATCH for meaning-preserving clarifications; this convention is **itself proposed**, not yet ratified. The first binding Constitution version and ratification date will be set **only after explicit owner approval** and promotion into `.specify/memory/constitution.md`. This review draft has no binding ratification date.

**Sources:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Module Architecture](../01-architecture/module-architecture.md), [Tenancy Architecture](../01-architecture/tenancy-architecture.md), [Transaction Consistency](../01-architecture/transaction-consistency-architecture.md), [Code Standards](../02-standards/code-standards.md), [API Standards](../02-standards/api-standards.md), [Authentication Standards](../02-standards/authentication-standards.md), [Authorization Standards](../02-standards/authorization-standards.md), [DB Standards](../02-standards/database-standards.md), [Security Standards](../02-standards/security-standards.md), [Logging/Audit Standards](../02-standards/logging-audit-observability-standards.md), [Testing Standards](../02-standards/testing-standards.md), [FBR-02](../reviews/FBR-02-architecture-gap-review.md), [Open Decisions](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md), [Workflow](../WORKFLOW.md).
