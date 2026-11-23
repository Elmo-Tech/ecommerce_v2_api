# E-commerce SaaS V2 — Proposed Spec Kit Constitution

> **Status:** DRAFT FOR OWNER REVIEW — not ratified and **not** an output of `/speckit-constitution`.  
> **Prepared:** 2026-11-23 | **Version:** Proposed 0.1.0 | **Ratified:** PENDING.  
> **Canonical file already created by CLI:** `.specify/memory/constitution.md` currently holds the **default placeholder**; replacing its content requires explicit owner approval of this proposal.

## Core Principles

### I. Decision traceability and bounded scope

Every Feature MUST cite relevant current approved Business D-series, OV scope, Architecture, Standards and FBR-02 decisions. Where a newer explicit owner decision refines a prior document, preserve the original record and cite the refinement; do not manufacture decisions, silently resolve D-201 or turn a non-MVP option into an MVP commitment. Unresolved material business or security behavior MUST be clarified before implementation. Limit work to an approved Feature and its direct dependencies.

### II. One modular monolith, explicit business ownership

The Backend MUST remain one Laravel API-first Modular Monolith as approved. Each domain has **one authoritative business-data writer**; other modules call narrowly typed, provider-owned Public Contracts rather than writing internal tables. Register Modules explicitly through reviewed Module Service Providers/Registry; no unbounded runtime folder discovery, unnecessary 1:1 physical modules for the 16 logical domains or empty layered boilerplate. Keep Controllers thin and business rules in appropriate Actions/Domain services. Use Eloquent in owning modules and repositories only when a real boundary warrants one.

### III. Trusted multi-tenancy and least privilege

Central PostgreSQL owns central identity/membership/Platform state; each Merchant has a separate Tenant PostgreSQL DB for its operational data, under approved isolated credentials. Every request/job/file/export MUST derive a trusted scope and enforce current membership, Tenant lifecycle/entitlement, Tenant Role, Location, resource ownership and business authorization where relevant. Do not trust a route/Host/customer-supplied Tenant ID as permission. No cross-Tenant data leakage, hidden Platform impersonation, cross-DB ACID assumption or stale positive Redis grant is acceptable. Clear context after requests, worker reuse, exceptions and retries.

### IV. Financial and inventory truth over convenience

Authoritative stock reservation, confirmation, actual collection/refund effects and related Outbox intent MUST use the owning PostgreSQL transaction and guarded concurrency/idempotency boundaries. Preserve legitimate historical snapshots and auditable corrections, not silent overwrites. Use BIGINT currency minor units in storage and exact decimal-string minor units with currency context in JSON; serialize BIGINT JSON identifiers as decimal strings. Apply approved exact manual-discount approval ceilings, timezone and Tax Input Mode locks. Redis/queues/reports MUST NOT become a substitute for authoritative money/stock state.

### V. Reviewed API and security contracts

Use one versioned `/api/v1` REST JSON contract, camelCase wire fields, standard response/error envelope, clear HTTP status/errorCode mapping and reviewed OpenAPI changes. Apply Sanctum/session/CSRF/browser origin and authorization boundaries; identified CSRF mismatch maps to **403/CSRF_TOKEN_MISMATCH**. Do not leak private responses to shared Next.js caches, assume Guest private order access, or fail open when required sensitive rate-limit protection is unavailable. Tenant-specific permission and approved access rechecks apply to files, exports and background work.

### VI. Durable post-commit work and protected data

Queue consumers MUST use trusted execution context, transactional Outbox for required durable event intent, at-least-once and consumer/business-effect idempotency, bounded retry and observable recovery; external uncertain financial effects require reconciliation. Commercial subscription suspension does not erase prior committed obligations or Outbox, but does prevent new commerce and permits only explicitly eligible prior obligations. Store private documents, Support screenshots and exports under business-owner permissions and current access checks; never equate possession of a filename with access rights.

### VII. Testable specifications and honest evidence

Each Feature MUST have an independently reviewable `spec.md` with behavior and negative cases, a `plan.md` demonstrating architecture/constitution fit, and `tasks.md` including appropriate tests. Actual test results, code revision and environment MUST be recorded; documentation, mocked tests and design approval are not production proof. Risk-based coverage MUST include real PostgreSQL Central + two Tenant DBs and Redis/Horizon for sensitive isolation, concurrency and outbox behavior once those dependencies exist. Use Pest/PHPUnit, Pint, PHPStan/Larastan and targeted API/architecture tests as compatible and applicable; never claim unverified package support.

### VIII. Simplicity, version compatibility and release gates

Prefer native Laravel, thin extensions and measurable optimization, not speculative frameworks, microservices or system-wide abstractions. Apply safe Tenant migrations with explicit version/compatibility gating and minimal privilege; do not auto-run schema migrations from every application/worker startup. Use feature/fix branches and reviewed PRs to `development`, with promotion to `main` separately controlled. **BA-16 backup/recovery is DEFERRED and BA-30 G3 remains BLOCKED**; no live Pilot or Production-readiness claim without independently approved recovery and all required release-gate evidence.

## Constraints and working boundaries

- Backend: Laravel 13, PHP-FPM first; Stancl tenancy, Sanctum, PostgreSQL, Redis/Horizon integration **conditional on real compatibility tests**. Next.js is a distinct frontend implementation context.
- Module descriptions: `docs/03-modules/<module>/module-overview.md`; Feature specs: `specs/<number>-<feature-slug>/`. Never maintain two editable copies of one Feature spec.
- MVP scope, feature ownership and cross-module contracts MUST follow approved existing documents. The detailed feature API/DB schema, numbers, fallback behavior and operational runbooks are only fixed by approved Feature plans, not this draft.
- No implementation code, migrations, Docker/CI deployment or production access is authorized merely by approving this Constitution.

## Development workflow and quality gates

1. Identify the owning module and relevant approved sources.
2. Draft and review `spec.md`; resolve blocking clarifications.
3. Draft and approve `plan.md` with current environment/dependency compatibility, Contracts, migration/security/tenancy and test strategy.
4. Generate `tasks.md`; run consistency checks and require a reviewed Feature branch/PR.
5. Implement/test only after explicit owner approval, with traceable test evidence and restricted deployment authority.
6. Reconcile changed design assumptions with upstream standards and module overview; do not silently amend historical business decisions.

## Governance

This proposed Constitution **summarizes**, but does not replace or outrank, explicitly approved D/OV/BA/MA and domain Standards. Contradictions MUST be escalated to the owner and resolved explicitly. Constitution changes need a separate reviewed versioned decision, affected-Feature impact analysis and PR. The initial formal version/date are set only upon owner ratification via the canonical Spec Kit workflow.

**Sources:** [Project Overview](../00-overview/project-overview.md), [Module Architecture](../01-architecture/module-architecture.md), [Code Standards](../02-standards/code-standards.md), [DB Standards](../02-standards/database-standards.md), [Security Standards](../02-standards/security-standards.md), [Testing Standards](../02-standards/testing-standards.md), [FBR-02](../reviews/FBR-02-architecture-gap-review.md), [Workflow](../WORKFLOW.md).
