# Foundation — Technical Module Overview (F0)

> **Status (2026-11-24): F0-01 CLOSED — T001–T026 COMPLETE, MERGED to `development`.** All substantive CodeRabbit findings resolved; [integrated CI #45405086930](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405086930) passed **31 tests / 134 assertions**, standalone Architecture **3 / 23**, with no production route-cache leakage. Pint/PHPStan were not installed/run. See [final merged handoff](../../handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md).  
> **Scope:** Technical Engineering Foundation, not one of the 16 business-domain data owners.  
> **Source of truth:** [F0-01 Feature Specification](../../../specs/001-laravel-bootstrap-module-registry/spec.md), [technical plan](../../../specs/001-laravel-bootstrap-module-registry/plan.md), [Constitution v1.0.0](../../../.specify/memory/constitution.md).

## Purpose and owner

Prepare one verifiable Laravel 13 API-first Modular Monolith and an explicit, minimal Module Registry for later independently approved business Features. The owner is the **backend Engineering Foundation**; this document is a module-level map of technical responsibilities, **not** a second editable specification.

## Execution and data ownership

- **Execution context:** Laravel application bootstrap. No active Tenant context, no authentication, and no Central/Tenant DB access in F0-01.
- **Data ownership:** no business or platform tables, ledgers, customer records, stock, money, audit records or migrations.
- **Permitted responsibilities:** selected Provider registration, typed non-business bootstrap test contract, module availability validation, safe boot diagnostics.
- **Never:** scan module directories to register implicitly; retain mutable Tenant/request state; treat a Provider registration as SaaS entitlement; authorize cross-module database writes.

## Implemented technical structure

~~~text
bootstrap/providers.php                           # registers ModuleRegistryServiceProvider
config/modules.php                               # explicit list; only FoundationDemo
app/Core/Modules/ModuleRegistry.php              # fail-fast validation, no scanning
app/Providers/ModuleRegistryServiceProvider.php  # validates then calls Laravel register()
app/Modules/FoundationDemo/
  FoundationDemoServiceProvider.php              # container binding, testing-only route
  Contracts/FoundationMarker.php                 # deterministic technical payload
  Routes/testing.php                             # GET /api/v1/__foundation/module-probe
tests/Unit/Core/ModuleRegistryValidationTest.php
tests/Feature/Foundation/
  ExplicitModuleRegistrationTest.php
  InvalidModuleRegistrationTest.php
  FoundationProbeIsolationTest.php
tests/Fixtures/isolated-module-bootstrap.php      # fresh process for negative config
tests/Architecture/ModuleRegistryBoundariesTest.php
~~~

Native Laravel provider registration at application bootstrap has been verified with a real PHP 8.4/Laravel 13 CI runner; negative scenarios use fresh application boot instead of mutating a previously registered provider. No module is activated merely because its directory exists.

## Public contracts, APIs and events

- **Internal technical boundary:** `ModuleRegistry` receives only the reviewed ordered list in `config/modules.php`, validates duplicate IDs/providers, missing/invalid provider classes and blank identifiers; `ModuleRegistryServiceProvider` then registers validated classes using native Laravel lifecycle. No external public business API, data or merchant-owned state.
- **Demonstration module:** `FoundationDemoServiceProvider` binds typed `FoundationMarker` and registers a test-only HTTP endpoint returning `{"module":"foundation-demo","source":"explicit-registry"}` following the [test probe contract](../../../specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md). Registered route is only enabled when Laravel environment is `testing`; **production/local route and scratch-generated route-cache absence proven under T018/T020**.
- **Production HTTP endpoints:** **none** introduced by F0-01.
- **Published business events/Outbox:** **none**.
- **Dependencies:** native Laravel provider/service-container facilities; external Stancl/Redis/queue/Identity modules **not installed** for this Feature.

## Feature inventory

| Feature | Canonical artifacts | Status |
| --- | --- | --- |
| F0-01 — Laravel Bootstrap + Explicit Module Registry | [spec](../../../specs/001-laravel-bootstrap-module-registry/spec.md) · [plan](../../../specs/001-laravel-bootstrap-module-registry/plan.md) · [tasks](../../../specs/001-laravel-bootstrap-module-registry/tasks.md) | **T001–T026 complete (26/26), both upstream and feature PRs merged to `development` (2026-11-24)**; Pint/PHPStan limitation retained |
| F0-02 — API Foundation | [spec](../../../specs/002-api-foundation/spec.md) · [plan](../../../specs/002-api-foundation/plan.md) · [tasks](../../../specs/002-api-foundation/tasks.md) | **Merged and verified on development; T001–T029 verified, T030 effective on verified publication of PR #6; PR #4 and #5 merged; 98 tests / 911 assertions** (2026-11-25), [CI evidence](../../../specs/002-api-foundation/quickstart.md) |
| F0-03 — Testing Foundation | [draft spec](../../../specs/003-testing-foundation/spec.md) · [draft plan](../../../specs/003-testing-foundation/plan.md) · [discovery](../../../specs/003-testing-foundation/research.md) | Discovery and proposed scope, owner decisions pending; no implementation |
| F0-04 / F0-05 | Planned F0 follow-ups per [WORKFLOW](../../WORKFLOW.md) | Not implemented |

## Authorization, tests and gates

- FoundationDemo's HTTP probe must exist **only in testing**; route caches/normal prod routing cannot expose it.
- Approved/verified: owner-approved `spec/plan`, separate F0-01 implementation authorization, PHP/Composer locked real Laravel boot and positive/negative listed/unlisted/duplicate/missing/invalid Provider tests. Real [Module Registry CI Run #45283746273](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45283746273): **19 passed, 63 assertions**. **Subsequently verified:** independent production/local route absence, generated scratch production route cache, focused Architecture boundaries, full Unit/Feature/Architecture tests and scoped diff review. **Not verified:** standalone Pint/PHPStan analysis (tools absent). **Verified:** substantive CodeRabbit review remediation, final merged PRs, and integrated Laravel CI.
- Parent Spec Kit [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) and F0-01 [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) are **MERGED into `development`**. Neither successful Foundation tests nor this overview authorizes production deployment or migrations; the owner separately gave **conditional** authorization and the review, CI and development-only merge gates have since been satisfied.
- Business/Release open items **D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED** remain unchanged.

## Dependencies and next modules

F0-01 supplies only the minimum safe integration seam for later F0-02 API Foundation, F0-03 testing infrastructure, F0-04 code quality/CI, and F1 Tenancy. It does not implement those features or approve any business rules.

**Source references:** [Module Architecture MA `13/16](../../01-architecture/module-architecture.md), [Code Standards CS-01/07](../../02-standards/code-standards.md), [Testing Standards](../../02-standards/testing-standards.md).
