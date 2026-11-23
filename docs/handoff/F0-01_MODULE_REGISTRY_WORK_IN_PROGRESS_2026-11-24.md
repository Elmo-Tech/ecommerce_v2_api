# F0-01 — Module Registry implementation handoff (live work)

**Started:** 2026-11-24 (Africa/Cairo)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Working branch:** `feature/f0-01-laravel-bootstrap`; Draft [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2), stacked onto Draft [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) → `development`. **No merges or deployment.**

## Owner request (binding)

Continue implementation of **T010–T016** in F0-01 using connected GitHub Actions, **without asking the owner to use Codespaces**. **MOST IMPORTANT:** always leave a reliable dated handoff and keep `docs/handoff/NEXT_CHAT_START_HERE.md` accurate so the next chat knows exactly which files to read and what task is unfinished. Preserve earlier handoffs and governance. Previous owner approval of F0-01 Option A/T005 covers feature implementation, **not merge/deploy**.

## Starting checkpoint — verified before implementation

- GitHub feature branch head `90bc397d390afe49738cf57046bb5370daff5f2e` at 2026-11-24 starting review.
- **T001–T009 COMPLETE, T010–T026 PENDING** per live `specs/001-laravel-bootstrap-module-registry/tasks.md`.
- GitHub Actions run [45239265994](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45239265994) succeeded using committed real `composer.lock`, PHP 8.4, Laravel 13.43.0 and one Foundation smoke test (9 assertions); **does not prove any Module Registry tests**.
- Files reviewed directly: `specs/001-laravel-bootstrap-module-registry/{tasks.md,plan.md,spec.md,contracts/foundation-probe.md}`, `bootstrap/{app.php,providers.php}`, `app/Providers/AppServiceProvider.php`, `tests/{Pest.php,TestCase.php,Feature/Foundation/BootstrapSmokeTest.php}`, `.github/workflows/f0-01-laravel-bootstrap.yml`, `composer.json`.
- Design must use native Laravel 13 providers and explicit `config/modules.php` list; fail on duplicate/empty/invalid/non-provider/missing definitions; one non-business FoundationDemo; test-only `GET /api/v1/__foundation/module-probe` should be absent outside testing and from production route cache. **No business/auth/tenant DB, scanning/autoload discovery, migration, new npm/Composer package, or production secrets.**
- Source of upstream Laravel 13 provider lifecycle verified by reading `laravel/framework` `13.x`: app registers provider classes via `Application::register`; `RegisterProviders` merges `bootstrap/providers.php`; `Application::beforeBootstrapping(RegisterProviders::class, ...)` allows safe isolated boot fixture injection before registration; `ServiceProvider::loadRoutesFrom` skips files if route cache active.

## Work now being done — NOT YET VALIDATED

1. **T010**: pure negative registry tests at `tests/Unit/Core/ModuleRegistryValidationTest.php`.
2. **T011**: explicit list `config/modules.php`, validator `app/Core/Modules/ModuleRegistry.php`.
3. **T012**: `app/Providers/ModuleRegistryServiceProvider.php` and `bootstrap/providers.php` wiring.
4. **T013**: `app/Modules/FoundationDemo/{FoundationDemoServiceProvider.php,Contracts/FoundationMarker.php,Routes/testing.php}`.
5. **T014–T015**: actual provider/test HTTP positive & negative tests plus fresh application-process bootstrap fixture for invalid/unlisted config; never mutate already-booted provider config to fake results.
6. **T016**: extend existing GitHub Actions F0-01 workflow to run explicit registry tests, capture actual job IDs/logs, fix issues, then mark completed tasks in live task ledger **only if verified**.
7. Write final dated result snapshot and **update permanent entrypoint** with accurate task flags, proof URLs, actual Git SHA and next exact action. If interrupted, use this document + live task ledger, **do not assume any task was completed solely from this work-in-progress plan**.

## Must-read resume order

1. [Permanent entrypoint](NEXT_CHAT_START_HERE.md).
2. [Current F0-01 task checklist](../../specs/001-laravel-bootstrap-module-registry/tasks.md).
3. [Approved plan (D2–D4)](../../specs/001-laravel-bootstrap-module-registry/plan.md) and [Spec](../../specs/001-laravel-bootstrap-module-registry/spec.md).
4. [FoundationDemo probe contract](../../specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md).
5. [Last passed bootstrap evidence](F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md).
6. Actual feature code under `app/Core/Modules/`, `app/Providers/`, `app/Modules/FoundationDemo/`, `config/modules.php`, `bootstrap/providers.php`, `tests/Unit/Core/`, `tests/Feature/Foundation/`, `tests/Fixtures/` and `.github/workflows/f0-01-laravel-bootstrap.yml`.

**Decision continuity:** D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED. T017–T026 pending unless updated with live evidence. PRs remain Draft/unmerged.
