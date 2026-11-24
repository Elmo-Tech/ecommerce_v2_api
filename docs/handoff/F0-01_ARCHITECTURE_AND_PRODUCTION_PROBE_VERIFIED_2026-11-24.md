# F0-01 — Architecture and Production Probe Isolation VERIFIED (T017–T020)

**Snapshot:** 2026-11-24, Africa/Cairo  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Working branch:** `feature/f0-01-laravel-bootstrap`, stacked **Draft PR #2** on **Draft PR #1** (`feature/f0-spec-kit-setup` → `development`).  
**Binding user instruction:** **Keep the canonical next-chat handoff up-to-date with exact reading list/status. No manual Codespaces instructions. Do not merge/deploy or run migrations.**

## Real verified state

**T001–T020 COMPLETE (20/26); T021–T026 PENDING.** The approved F0-01 Option A technical plan and separate implementation authorization remain the governing scope.

**[GitHub Actions Run #45284904141](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45284904141) is SUCCESS**, tested source SHA `1a03b86b186030f723cea2e08ea625a929597ec6` (job `138078598196`); actual PHP **8.4.28**, Laravel **13.43.0**, pinned Composer lock, platform checks and `composer audit --locked` (no advisories reported at run time). **Pest: 26 passed, 116 assertions, zero failures**. Observed in CI logs:
- ModuleRegistry negative unit, explicit listed/unlisted/fresh-boot validation, base Foundation smoke — passed.
- T017 `tests/Architecture/ModuleRegistryBoundariesTest.php` — passed; focused tests inspect real registry/Provider code for implicit discovery/forbidden technical dependencies, and demonstrate catching a forbidden cross-module internal import while allowing a public Contract.
- T018 `tests/Feature/Foundation/FoundationProbeIsolationTest.php` — passed: independently booted `production` and `local` app processes have **no** FoundationDemo test probe route; actual app `route:cache` in production reports **no routes** and deliberately does not generate cache; a **scratch Laravel production app** copying the real config/providers, with a harmless redirect control route **only in scratch**, successfully generated an actual production route cache containing the redirect **without the FoundationDemo probe**. Scratch app/vendor symlink and cache were cleaned without touching repo or production.
- CI source-diff guard passed. No production database, auth/Tenancy, migrations, secrets, deployment or merge.

## Real bug discoveries and resolutions

1. First isolated route-cache assertion wrongly required a cache file from an actual production application with **zero routes**. Laravel 13 `RouteCacheCommand` source confirmed it declines caching an empty collection. Corrected the test to explicitly expect this behavior.
2. First scratch app test used `Symfony\Component\Filesystem\Filesystem`, a package not in the project's locked minimal dependencies. Switched to **native Laravel `Illuminate\Filesystem\Filesystem`**; no new Composer dependencies.
3. Architecture suite was initially not included by `phpunit.xml`; added an explicit `tests/Architecture` testsuite so code-boundary tests really run.
4. Scratch production Laravel app initially lacked normal exception-handler/config/storage setup. Mirrored the **real Laravel 13 bootstrap with `withExceptions` and `withMiddleware`** and copied only necessary local config files into the scratch fixture. This caused **Run #45284904141** to pass all test cases.
5. Earlier unsuccessful Actions runs while iterating are **not ignored**: `45284220423` (expected nonexistent cache), `45284564697` (missing optional symfony/filesystem package), `45284735775` (scratch exception/config), followed by real green run above.

## Current source layout and future boundaries

| Area | Actual files to read |
| --- | --- |
| Explicit registry | `config/modules.php`; `app/Core/Modules/ModuleRegistry.php` |
| Native Laravel registration | `bootstrap/providers.php`; `app/Providers/ModuleRegistryServiceProvider.php` |
| Only registered demo | `app/Modules/FoundationDemo/FoundationDemoServiceProvider.php`; `Contracts/FoundationMarker.php`; `Routes/testing.php` |
| Unit and provider tests | `tests/Unit/Core/ModuleRegistryValidationTest.php`; `tests/Feature/Foundation/BootstrapSmokeTest.php`; `ExplicitModuleRegistrationTest.php`; `InvalidModuleRegistrationTest.php` |
| Fresh bootstrap and isolation | `tests/Fixtures/isolated-module-bootstrap.php`; `tests/Feature/Foundation/FoundationProbeIsolationTest.php` |
| Architecture and config | `tests/Architecture/ModuleRegistryBoundariesTest.php`; `phpunit.xml`; `.github/workflows/f0-01-laravel-bootstrap.yml` |
| Single module overview | `docs/03-modules/foundation/module-overview.md` — links to canonical Spec, not a second editable copy |

The test-only `GET /api/v1/__foundation/module-probe` is NOT approved as public production API. The real production route collection has no routes; scratch cache fixture exists **only during isolated CI test and is removed**. The provider binding in Production is harmless non-business contract but the HTTP probe route is absent. **This does not prove business Auth/Tenancy, financial integrity or merchant DB safety**, which are separate Features.

## Exact next tasks after this snapshot

1. **T021** run dependency/platform/extension verification against **committed** `composer.lock` and preserve precise CI/versions.
2. **T022** run all F0-01 tests (including Unit/Feature/**Architecture**) under real Laravel/Pest with explicit results and commit SHA. Existing CI runs Foundation group, which currently includes every authored test; consider a final explicit unfiltered suite run for full proof.
3. **T023** run Laravel Pint `--test` and applicable PHPStan/Larastan checks **if installed and supported**; do not assume quality tools are present. Record true blockers and do not install extra dependencies without plan/approval.
4. **T024** perform final scoped Git diff/secret/auth/tenant/migration/route exposure review and document any limits.
5. **T025** update `quickstart.md`, `research.md`, `docs/reviews/F0-01-spec-review.md`, `spec.md` stale status wording, final handoff and current task ledger. Preserve ratified decisions.
6. **T026** PR code review and merge gate; upstream Draft PR #1 is separate, Draft PR #2 remains stacked, **do not merge/mark ready/retarget without the required approvals**.

## Resume read order (MANDATORY for next chat)

1. [`NEXT_CHAT_START_HERE.md`](NEXT_CHAT_START_HERE.md) — permanent status and precise next action (must be updated immediately after this snapshot).
2. [This real-green Architecture/Production snapshot](F0-01_ARCHITECTURE_AND_PRODUCTION_PROBE_VERIFIED_2026-11-24.md).
3. [Earlier T010–T016 registry snapshot](F0-01_MODULE_REGISTRY_VERIFIED_2026-11-24.md), then [original Laravel bootstrap validation](F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md).
4. [Live task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md) — checkbox state is authoritative, [approved plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [test-only probe contract](../../specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md).
5. [Original owner no-Codespaces instruction](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md) and [explicit implementation approval](../reviews/F0-01-implementation-authorization.md).
6. **Read code from GitHub** in the above source table and [workflow](../../.github/workflows/f0-01-laravel-bootstrap.yml); verify Draft PR #1/#2 heads, latest CI SHA and absence of concurrent branch edits before proceeding.

**Unchanged:** D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED; nothing deployed or merged. This file is a dated historical milestone: future chats should trust the latest `NEXT_CHAT_START_HERE.md` and newly dated snapshots if they supersede it.
