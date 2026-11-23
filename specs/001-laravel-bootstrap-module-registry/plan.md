# Implementation Plan: F0-01 — Laravel Bootstrap & Explicit Module Registry

**Branch**: `feature/f0-01-laravel-bootstrap` | **Prepared**: 2026-11-23 | **Spec**: [spec.md](spec.md)  
**Status**: **APPROVED — F0-01 Option A, 2026-11-23 (Spec + Plan).** **T005 separately authorized** (2026-11-23) and a minimal official-source skeleton **partially staged** on GitHub. **No project `composer.lock`, packages installed, Laravel boot, framework tests, CI success or deployment. T005 remains unchecked.**  
**Input**: Reviewed F0-01 `spec.md` v0.2 and ratified [E-commerce SaaS V2 Constitution v1.0.0](../../.specify/memory/constitution.md).  
**Method**: Reviewer-authored against official Spec Kit v1.4.3 `plan-template.md` using agent-neutral `none` integration; not a run of `/speckit.plan`.

## Summary

Create a minimal reproducible **Laravel 13 API-first backend** in the **existing repository root**, preserving approved documents, Spec Kit, history and branch conventions. Demonstrate a **small explicit Module Registry** loading only listed Providers through the Laravel lifecycle, with one **non-business FoundationDemo module**. The only demonstration route is test-only, with automated proof that it does **not** exist in a non-testing/production application. Provide runtime/bootstrap, positive and negative registration, and architectural tests without building Auth, Tenancy, business schemas, production endpoints, infrastructure or deployment.

The owner approved this **technical design approach** under F0-01 Option A on 2026-11-23. A **separate later owner response, A**, expressly authorized **starting T005** on `feature/f0-01-laravel-bootstrap`, recorded in [F0-01 implementation authorization](../../docs/reviews/F0-01-implementation-authorization.md). This is not approval to deploy/merge or to skip real Composer/project runtime tests. The [T005 staging handoff](../../docs/handoff/F0-01_T005_SOURCE_STAGED_2026-11-23.md) records what source actually exists and why T005 remains open.

## Technical Context

| Field | Proposed target / current evidence |
| --- | --- |
| Language / version | **PHP >=8.3**, within Laravel 13's currently documented support set (PHP 8.3–8.5); exact PHP minor/patch **TO VALIDATE**, no silent downgrade |
| Framework | Laravel Framework **`^13.0`** in a versioned Composer lock; exact patch **TO RESOLVE/VERIFY** |
| Existing project | Documentation-only Laravel API repo with `docs/`, `specs/`, `.specify/` and `README.md`; **no verified framework skeleton at planning time** |
| Primary dependencies | Laravel native providers and service container only; no `stancl/tenancy`, Sanctum, Redis/Horizon, Octane or generic Modules package at this step |
| Storage | **N/A to business data**. No migrations, Tenant DBs, Central DB or persisted business entities. No automatic DB I/O on HTTP/Artisan bootstrap |
| Tests | **Pest (preferred per TEST-02)** over PHPUnit foundation **subject to actual version compatibility**; focused Laravel Feature and PHP Unit/Architecture tests |
| Target platform | Linux/PHP-FPM **future initial API runtime**; local/test command execution possible after PHP/Composer installed. Codespaces Spec Kit's Python version says nothing about PHP |
| Project type | Single Laravel API backend at repository root; Next.js stays separate |
| Performance goals | No invented p95/RPS numbers; boot must not trigger Tenant/remote I/O, and must register only declared modules |
| Constraints | No production API probe, secrets, migrations, data modifications, CI/deploy claims, external auth/tenant/queue setup, module auto-scanning or 16 empty packages |
| Scale/scope | Framework bootstrap + one registry + one non-business demo + selected tests and documentation; F0-02/F0-03/F0-04/F1 remain separate Features |

## Constitution Check

*GATE: Review before implementation; repeat after real code and tests. No pass below implies live runtime validation.*

| Principle | Design compliance assessment | Implementation evidence required |
| --- | --- | --- |
| I — Decision traceability | **Design PASS** — MA F0, CS-01/07, TEST-01/02, FND-01/02 are cited | Owner approves `spec.md` and this `plan.md`; source references preserved |
| II — Modular ownership | **Design PASS** — explicit Provider/Module Registry and no unbounded discovery or business writers | Tests for listed/unlisted, duplicates and invalid providers; architecture scope review |
| III — Tenant / least privilege | **Design PASS by exclusion** — no Central/Tenant connection, identity or mutable request Tenant state | Cold boot/test proves no Tenant initialization or unexpected DB work |
| IV — Money / stock | **N/A** — none in this technical slice | Confirm no financial/inventory schema/code or migration effects |
| V — API / security | **CONDITIONAL** — test-only demo HTTP route, no production API or Sanctum | Actual production/non-testing route absence and route cache checks |
| VI — Jobs / files / Audit | **N/A** — no outbox, queue workers, private storage or business effects | Diff inspection excludes related packages and code |
| VII — Tests / evidence | **PENDING** — required test design identified | Run real tests and record versions, commands, revision, actual output; don't claim unrun CI |
| VIII — Simplicity / releases | **Design PASS, runtime pending** — native Laravel + minimal custom registry, PHP-FPM first | Composer compatibility, one-root scaffold diff, no auto-migrations, owner/test/PR gates |

**Implementation gate (updated 2026-11-23):** Owner approval of Spec + Plan and the **separate T005 implementation authorization are SATISFIED**. T002 PHP/Composer prerequisites, T003 temporary Composer solver dry-run and T004 scoped GitHub safety checks are documented as complete. **T005 is PARTIAL**, because a real project `composer.lock` and compatible non-Codespaces install/bootstrap/test runner are not yet available; runtime/code-security checks remain open and must not be claimed as passed.

## Phase 0 — Research (Documentary Only)

See [research.md](research.md) for official-source checks and pending runtime probes.

- Official Laravel 13 [release notes](https://laravel.com/docs/13.x/releases) specify PHP minimum **8.3** and recommend `^13.0` when requiring the framework.
- Laravel 13 [Service Providers](https://laravel.com/docs/13.x/providers) uses `bootstrap/providers.php`; `register()` binds dependencies, `boot()` initializes routes/listeners.
- Laravel 13 [routing documentation](https://laravel.com/docs/13.x/routing) states `php artisan install:api` **installs Sanctum**; intentionally do not invoke it in F0-01.
- **Subsequently verified:** Owner-provided real Codespaces PHP 8.4.27/extensions + temporary Composer dry-run resolver (Laravel 13.43.0 / Pest 5.3.7 / PHPUnit 13.4.7); **not** a project install/lock or application test. A curated Laravel 13 source scaffold was later staged; framework boot/Provider lifecycle remain untested.

## Phase 1 — Proposed Design

### D1. Integrate official framework skeleton non-destructively

1. Inspect current repository tree; generate Laravel 13 application skeleton in a separate temporary directory under a verified compatible PHP/Composer runtime, **not over** the current root.
2. Review source-file inclusion and compose a controlled patch into repository root; preserve `docs/`, `specs/`, `.specify/`, `README.md` (or reconcile deliberate README changes) and Git history.
3. Commit a reviewed `composer.json` / `composer.lock` pair and Laravel runtime paths; do not commit `vendor/`, generated secrets, `.env`, tokens, disposable build caches or unsanctioned default auth/database schema.
4. Use a deterministic local test environment with array-based in-memory sessions/cache and no network/DB dependency for this Foundation slice. The operational PostgreSQL choice remains authoritative for later DB work; SQLite mocks are not tenancy/finance proof.
5. Do **not** run `php artisan install:api`, default/demo migrations, `migrate:fresh`, Tenant provisioning, `stancl` install, SaaS fixtures, queues, Docker deployments or CI rollout merely to boot the app.

### D2. Explicit Module Registry with fail-fast validation

**Proposed minimal implementation:**

- `config/modules.php` lists approved enabled module definitions explicitly; suggested shape: `['id' => 'foundation-demo', 'provider' => FoundationDemoServiceProvider::class]`. A list rather than associative keys preserves duplicate entries for validation.
- `app/Core/Modules/ModuleRegistry.php` validates **unique IDs, unique provider references, nonempty class names, class existence and `ServiceProvider` inheritance** before registration; errors are controlled diagnostics with no tenant data, no auto-skip of invalid entries and no fallback auto-discovery.
- `app/Providers/ModuleRegistryServiceProvider.php` loads the validated list via native Laravel provider registration; register container bindings before route/listener boot. It must not retain request-specific identity, Tenant context or database connections.
- `bootstrap/providers.php` registers this central registry provider. If actual Laravel 13 lifecycle tests reveal ordering issues, adjust to the simplest native wiring and amend this plan **before** implementation approval/merge.
- A module not present in the reviewed explicit list is **never loaded**, even if its folder exists. Module availability for a merchant is later enforced by Tenant entitlements; registry inclusion is **not** authorization.

**Intentional simplicity:** one small config, registry and provider (if all three prove necessary). Avoid Composer package discovery for business modules, scanning directory trees, plugin managers, a module database table, automated 16-domain scaffolding or a general service locator.

### D3. Non-business FoundationDemo provider and private probe

- One minimal `app/Modules/FoundationDemo/FoundationDemoServiceProvider.php`, with a simple public **test contract** (e.g. a container binding or deterministic probe response), no models, financial/tenant data, DB access, queue or external integration.
- Registration uses the **same** Module Registry and Provider lifecycle as subsequent real modules; test-only behavior must not bypass registration merely to make a green test.
- A test HTTP probe (proposed `GET /api/v1/__foundation/module-probe`) is installed **only when the app is running in isolated testing mode**. It is **not** an approved production API, published OpenAPI contract, customer endpoint or F0-02 response-envelope precedent.
- Explicit tests must verify the route is **not** present under non-testing/production boot, including when generating production route caches; never ship route-cache artifacts from a testing environment.
- Fail closed when a listed provider is missing or invalid; an unlisted module cannot activate from its on-disk directory alone.

See [test-only probe contract](contracts/foundation-probe.md). If hiding a test route under production is not reliable under actual framework route caching, **replace with a safer test-only fixture**, update this plan and rerun negative tests; never deploy the test route.

### D4. Module ownership and documentation

- `app/Core/Modules` owns the neutral technical registry, not any domain's data.
- `app/Modules/FoundationDemo` is a **temporary non-business demonstration module** and does not stand for the 16 logical business domains.
- `docs/03-modules/foundation/module-overview.md` documents the Foundation's technical responsibilities, permitted providers/dependencies and link to this one canonical Feature specification; it must not duplicate `spec.md` or invent an implementation status.
- `tests/Unit/Core/`, `tests/Feature/Foundation/` and `tests/Architecture/` own focused tests; no duplicate test trees under the module itself.

### D5. Targeted test matrix (proposed)

| Test | What it must verify | Risk / evidence |
| --- | --- | --- |
| `BootstrapSmokeTest` | Framework boots and minimal health or Artisan query works with no DB/migrations | Runtime, no hidden side effects |
| `ExplicitModuleRegistrationTest` | Listed FoundationDemo Provider binding and test HTTP probe exist in tests | Real Laravel provider lifecycle |
| `UnregisteredModuleTest` | On-disk but unlisted test fixture does not load | Reject implicit discovery |
| `InvalidModuleRegistrationTest` | Missing/invalid provider or duplicate ID/class results in clear diagnostic | Fail-fast and safe |
| `FoundationProbeIsolationTest` | Probe route exists only in `testing`, absent in production routes/cache | Security boundary |
| `ModuleRegistryBoundariesTest` | No directory enumeration, no business model/foreign module writes, no retained Tenant globals | Simple architectural rule |
| `NonDestructiveBootstrapTest` / diff checklist | Source tree preservation, no auth/tenant packages and no migrations | Review evidence |

Tests must run on the chosen actual Laravel/PHP/Composer environment; mocks or static document inspection do not prove framework behavior. Pest preferred, PHPUnit as its underlying runner; package version selection remains conditional.

### D6. Compatibility and command-evidence gate

Record in the implementation PR (not invented now):
- exact `php -v` and required extensions, `composer --version`, `composer check-platform-reqs` and `composer validate --strict` results;
- exact Laravel patch/Pest/PHPUnit/Composer package lock, negative dependency probes where relevant;
- `php artisan about` / `route:list`, targeted `artisan test`, production probe absence and formatting/static checks;
- actual command output, environment, commit SHA and any skipped/failing checks; GitHub Actions status independently;
- no production changes, DB migrations, secret/credential access or tenant data.

The two earlier GitHub Actions startup attempts failed before visible steps. This **still requires independent CI investigation**, separate from successful Spec Kit Codespaces CLI setup. A manual Codespaces test is labeled manual, never green CI.

## Project Structure

### Documentation (this Feature)

~~~text
specs/001-laravel-bootstrap-module-registry/
  spec.md                 # One canonical feature specification
  plan.md                 # This reviewed technical design draft
  research.md             # External/source evidence and open compatibility items
  quickstart.md           # Planned setup/verification procedure, NOT yet executed
  contracts/
    foundation-probe.md   # Internal TEST-ONLY contract; no production API
  checklists/
    requirements.md       # Requirements quality + pending approval/test gates
  tasks.md                # Planned tasks; not executed
docs/
  03-modules/foundation/
    module-overview.md    # Technical foundation owner, links to Feature
  reviews/
    F0-01-spec-review.md  # Constitution, scope and decision review
~~~

### Proposed Laravel code additions after separate approval

~~~text
composer.json
composer.lock
artisan
bootstrap/
  app.php
  providers.php
config/
  app.php
  modules.php
app/
  Core/
    Modules/
      ModuleRegistry.php
  Providers/
    ModuleRegistryServiceProvider.php
  Modules/
    FoundationDemo/
      FoundationDemoServiceProvider.php
      Routes/
        testing.php
tests/
  Unit/
    Core/
      ModuleRegistryValidationTest.php
  Feature/
    Foundation/
      BootstrapSmokeTest.php
      ExplicitModuleRegistrationTest.php
      FoundationProbeIsolationTest.php
  Architecture/
    ModuleRegistryBoundariesTest.php
~~~

The above are **proposed paths**, not created implementation files. A real Laravel skeleton will also have framework-required files; compare and minimize carefully rather than assuming this abbreviated tree is complete. The chosen exact test paths and Laravel classes can change only by reviewed `plan.md` update.

**Structure Decision:** Laravel at **repository root** (backend repository already separated from Next.js). Small `app/Core/Modules` registry. One disposable test module proves registration; no separate microservice, package manager or global repository layer.

## Acceptance Traceability

| Spec goal | Proposed proof |
| --- | --- |
| FR-001/006/014, SC-001 | Deterministic clean Laravel 13 bootstrap without overwrite and documented real PHP/Composer smoke output |
| FR-002/003/007/009, SC-002/007 | Explicit list, Provider registration and negative unlisted/duplicate/missing-provider tests |
| FR-004/012, SC-003/006 | Test-only probe exposed only under test, absent under production routes/cache |
| FR-005/008/010/013 | Inspect dependency tree and cold boot for no Tenant/auth/financial/queue/DB operations |
| FR-011/SC-004/005 | Owner approvals, actual test and diff evidence, Foundation overview, reviewed PR gates |

## Complexity Tracking

No Constitution violation or new custom framework is approved in this design. If runtime tests force a more complex provider bootstrap or test strategy, document the failed simple approach and seek approval before implementation.

## Decision / Delivery Gates

1. **Owner approved F0-01 `spec.md` and this `plan.md` — Option A on 2026-11-23 (COMPLETE).** The approved direction is still subject to real framework compatibility evidence.
2. **COMPLETE for pre-code gates:** T002 PHP/Composer extensions, T003 isolated Composer dry-run compatibility, T004 bounded GitHub safety review; **separate T005 authorization also recorded**. Actual project install/runtime evidence remains outstanding.
3. Implement tasks in [tasks.md](tasks.md), review the actual diff, record tests with environment/commit and resolve any blocker before merge.
4. PR #1 (Spec Kit + Constitution) is upstream to this planning branch. This F0-01 PR must remain Draft/stacked until PR #1 merges; then consider retargeting to `development` following review. Do **not** merge from this plan alone.
5. D-201 **OPEN**; BA-16 **DEFERRED**; BA-30 G3 **BLOCKED**. F0-01 gives no release/Pilot authority.

## References

- [Constitution v1.0.0](../../.specify/memory/constitution.md)
- [Module Architecture](../../docs/01-architecture/module-architecture.md), especially MA `13 and `16
- [Code Standards](../../docs/02-standards/code-standards.md), CS-01, CS-07, CS-11
- [Testing Standards](../../docs/02-standards/testing-standards.md), TEST-01/02/09
- [Backend Architecture](../../docs/01-architecture/backend-architecture.md), F0 scope and PHP-FPM runtime
- [FBR-02](../../docs/reviews/FBR-02-architecture-gap-review.md), FBR-02-01
- [Laravel 13 official releases](https://laravel.com/docs/13.x/releases), [providers](https://laravel.com/docs/13.x/providers), [routing](https://laravel.com/docs/13.x/routing), [deployment requirements](https://laravel.com/docs/13.x/deployment)
