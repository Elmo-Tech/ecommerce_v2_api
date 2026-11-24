# F0-01 — Explicit Module Registry / FoundationDemo verified (T010–T016)

**Snapshot date:** 2026-11-24, Africa/Cairo  
**GitHub:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Feature branch:** `feature/f0-01-laravel-bootstrap` — [Draft PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2), stacked on Draft PR #1 (`feature/f0-spec-kit-setup` → `development`).  
**Owner instruction:** **Keep a dated handoff and always refresh `docs/handoff/NEXT_CHAT_START_HERE.md` so a new chat can resume from actual verified files. No Codespaces or terminal commands from owner. No merge, deployment or migrations.**

## 1. Verified milestone — actual CI, not a claim from static review

**T001–T016 COMPLETE (16/26), T017–T026 PENDING.** Baseline T001–T009 evidence remains in [F0-01 Bootstrap Validated](F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md). **New GitHub Actions run [#45283746273](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45283746273) was fully successful** at source commit `9e7cfcd1dd03f552e65a7b70d2772519b4fc1738` (job `138074581411`):

- PHP **8.4.28**, Laravel **13.43.0**, committed Composer lock installed with no install scripts/plugins; platform requirements and `composer audit --locked` passed (no advisories at run time).
- Pest Foundation group **19 tests passed, 63 assertions**, **zero failed**. Suites observed: `Unit/Core/ModuleRegistryValidationTest`, `Feature/Foundation/BootstrapSmokeTest`, `ExplicitModuleRegistrationTest`, `InvalidModuleRegistrationTest`.
- Registered Provider/binding and `GET /api/v1/__foundation/module-probe` returned intended deterministic JSON in `testing`. An on-disk unlisted module was **not registered**, tested via a **fresh application process**. Duplicate ID/provider, missing/wrong-type Provider, blank IDs were refused with controlled `InvalidArgumentException` at startup.
- CI source diff guard passed; no production database credentials, auth/tenancy, migrations, deploy or merge.

**Important limitation:** The successful CI suite runs in `APP_ENV=testing` and therefore does **NOT YET prove absence of test-only endpoint in non-testing/production app or production-generated route cache**. These explicit gates are T017–T020 and must remain unchecked until separate proof.

## 2. Exact source implementation and paths

| Task | Source / proof |
| --- | --- |
| T010 | `tests/Unit/Core/ModuleRegistryValidationTest.php` — valid/empty list and 9 negative/shape cases |
| T011 | `config/modules.php` — explicit list containing **only** FoundationDemo; `app/Core/Modules/ModuleRegistry.php` — list, id, provider class, subclass, unique ID/provider validation before loading |
| T012 | `app/Providers/ModuleRegistryServiceProvider.php`, `bootstrap/providers.php` — native Laravel provider registration; no directory scans or mutable Tenant context |
| T013 | `app/Modules/FoundationDemo/FoundationDemoServiceProvider.php`, `Contracts/FoundationMarker.php`, `Routes/testing.php` — non-business binding and testing-only route; no DB, no identity |
| T014 | `tests/Feature/Foundation/ExplicitModuleRegistrationTest.php` — test HTTP contract and prove an unlisted on-disk module does **not** activate |
| T015 | `tests/Fixtures/isolated-module-bootstrap.php`, `tests/Feature/Foundation/InvalidModuleRegistrationTest.php` — native fresh Laravel registration and controlled negative cases, no post-boot config mutation |
| T016 | [successful CI Run #45283746273](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45283746273), **19 passed / 63 assertions** |

**Design:** A module is registered only through a code-reviewed `config/modules.php` list. `ModuleRegistryServiceProvider::register()` validates the entire list before any module activates and calls Laravel's `$app->register($provider)`. `FoundationDemoServiceProvider::boot()` guards routes with `$app->environment('testing')`; its probe is **not** an approved production API contract. No auto-discovery, Stancl, Sanctum, business models or Tenant connections.

## 3. Pending next steps (do not mark complete early)

1. **T017** focused architecture boundary tests `tests/Architecture/ModuleRegistryBoundariesTest.php` covering no module directory scanning/implicit registration, no unauthorized cross-module internal writer or globally retained Tenant, with at least one deliberately forbidden sample.
2. **T018** `tests/Feature/Foundation/FoundationProbeIsolationTest.php` and/or isolated CI process proving test route is present only in `APP_ENV=testing`, **absent in production/non-testing route collection and real production-generated route cache** (not merely a 403 handler). Treat test-generated route caches as non-shippable.
3. **T019** update `docs/03-modules/foundation/module-overview.md` with implementation paths, contracts, non-goals and link to canonical `spec.md`, no duplicate specs.
4. **T020** run architecture and negative route tests in real GitHub Actions and record job ID, actual counts and cache results; fix any failures before checking T017–T020.
5. **T021–T026** final dependency/platform/pinned security review, full F0-01 tests, Pint/PHPStan when compatible, diff/security audit, handoff/reviews and stacked PR approval workflow. **Do not merge PR #2 or PR #1 without owner review/authorization**.

## 4. Fresh chat read order — no guessing from prior chat memory

1. [Permanent entrypoint](NEXT_CHAT_START_HERE.md) — MUST link to this snapshot and reflect the latest result.
2. [This VERIFIED milestone](F0-01_MODULE_REGISTRY_VERIFIED_2026-11-24.md), then [prior bootstrap evidence](F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md).
3. [Live F0-01 task checklist](../../specs/001-laravel-bootstrap-module-registry/tasks.md) for exact task statuses; [approved plan D2–D4](../../specs/001-laravel-bootstrap-module-registry/plan.md), [spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [test-only probe contract](../../specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md).
4. [No-Codespaces owner instruction](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md), [original explicit implementation authorization](../reviews/F0-01-implementation-authorization.md), [Constitution](../../.specify/memory/constitution.md).
5. **Read actual code** under `app/Core/Modules/`, `app/Providers/`, `app/Modules/FoundationDemo/`, `config/modules.php`, `bootstrap/providers.php`, `tests/Unit/Core/`, `tests/Feature/Foundation/`, `tests/Fixtures/`, `.github/workflows/f0-01-laravel-bootstrap.yml`.
6. Verify latest [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) head, [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) stack, and new CI run SHA before modifying the next task.

## 5. Operational continuity

- **No Codespaces commands** required from the owner. Use connected GitHub tools and GitHub Actions, with SHA-checked updates and reviewed commits; never force push/reset shared branch.
- PR #1 and #2 are still Draft/unmerged, no production release/DB. **D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED**, unrelated to this foundation.
- Preserve all dated handoffs; update `docs/handoff/NEXT_CHAT_START_HERE.md` after **each meaningful milestone**, including partial failures. Do not claim unexecuted tests.
