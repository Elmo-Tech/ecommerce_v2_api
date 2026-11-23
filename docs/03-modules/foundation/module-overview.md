# Foundation — Technical Module Overview (F0)

> **Status:** PLANNING DRAFT; no Laravel application, providers, tests or runtime implemented yet.  
> **Scope:** Technical Engineering Foundation, not one of the 16 business-domain data owners.  
> **Source of truth:** [F0-01 Feature Specification](../../../specs/001-laravel-bootstrap-module-registry/spec.md), [technical plan](../../../specs/001-laravel-bootstrap-module-registry/plan.md), [Constitution v1.0.0](../../../.specify/memory/constitution.md).

## Purpose and owner

Prepare one verifiable Laravel 13 API-first Modular Monolith and an explicit, minimal Module Registry for later independently approved business Features. The owner is the **backend Engineering Foundation**; this document is a module-level map of technical responsibilities, **not** a second editable specification.

## Execution and data ownership

- **Execution context:** Laravel application bootstrap. No active Tenant context, no authentication, and no Central/Tenant DB access in F0-01.
- **Data ownership:** no business or platform tables, ledgers, customer records, stock, money, audit records or migrations.
- **Permitted responsibilities:** selected Provider registration, typed non-business bootstrap test contract, module availability validation, safe boot diagnostics.
- **Never:** scan module directories to register implicitly; retain mutable Tenant/request state; treat a Provider registration as SaaS entitlement; authorize cross-module database writes.

## Proposed structure — not implemented

~~~text
app/Core/Modules/ModuleRegistry.php
app/Providers/ModuleRegistryServiceProvider.php
config/modules.php
app/Modules/FoundationDemo/FoundationDemoServiceProvider.php
app/Modules/FoundationDemo/Routes/testing.php
~~~

Actual Laravel Provider initialization order and exact class paths must be verified in the approved implementation branch.

## Public contracts, APIs and events

- **Internal technical boundary:** Explicit Module Registry receives only the reviewed configuration list; no external public network API or merchant-owned state.
- **Demonstration module:** deterministic test-only Provider binding and [probe contract](../../../specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md) during isolated tests.
- **Production HTTP endpoints:** **none** introduced by F0-01.
- **Published business events/Outbox:** **none**.
- **Dependencies:** native Laravel provider/service-container facilities; external Stancl/Redis/queue/Identity modules **not installed** for this Feature.

## Feature inventory

| Feature | Canonical artifacts | Status |
| --- | --- | --- |
| F0-01 — Laravel Bootstrap + Explicit Module Registry | [spec](../../../specs/001-laravel-bootstrap-module-registry/spec.md) · [plan](../../../specs/001-laravel-bootstrap-module-registry/plan.md) · [tasks](../../../specs/001-laravel-bootstrap-module-registry/tasks.md) | **SPEC + PLAN APPROVED — Option A (2026-11-23)**; code not implemented |
| F0-02 / F0-03 / F0-04 / F0-05 | Planned F0 follow-ups per [WORKFLOW](../../WORKFLOW.md) | Not specified or implemented in F0-01 |

## Authorization, tests and gates

- FoundationDemo's HTTP probe must exist **only in testing**; route caches/normal prod routing cannot expose it.
- Mandatory proof before implementation/merge: owner-approved `spec/plan` (already satisfied), PHP/Composer compatibility, separate implementation authorization, real boot, registered/unregistered/duplicate/missing-provider scenarios and production route absence.
- Parent Spec Kit PR #1 remains separate; F0-01 implementation/CI/production availability is not proven by this planning document.
- Business/Release open items **D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED** remain unchanged.

## Dependencies and next modules

F0-01 supplies only the minimum safe integration seam for later F0-02 API Foundation, F0-03 testing infrastructure, F0-04 code quality/CI, and F1 Tenancy. It does not implement those features or approve any business rules.

**Source references:** [Module Architecture MA `13/16](../../01-architecture/module-architecture.md), [Code Standards CS-01/07](../../02-standards/code-standards.md), [Testing Standards](../../02-standards/testing-standards.md).
