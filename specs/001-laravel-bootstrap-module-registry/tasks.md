---
description: "F0-01 implementation task plan; spec and plan approved, code/testing pending"
---

# Tasks: F0-01 — Laravel Bootstrap & Explicit Module Registry

**Input**: [spec.md](spec.md) (F0-01 Option A owner-approved), [plan.md](plan.md) (F0-01 Option A owner-approved), [research.md](research.md), [test-only contract](contracts/foundation-probe.md), [quickstart](quickstart.md), [Constitution v1.0.0](../../.specify/memory/constitution.md).  
**Status:** **SPEC + PLAN APPROVED (F0-01 Option A, 2026-11-23); T001–T003 COMPLETE; T004–T026 PENDING.** Codespaces runtime prerequisites checked via owner-provided terminal output (PHP 8.4.27, Composer 2.10.3, 13/13 required Laravel extensions); Composer **temporary dry-run dependency solving passed** on Codespaces (118 simulated lock operations; Laravel 13.43.0, Pest 5.3.7, Pest Laravel Plugin 5.0.1, PHPUnit 13.4.7; exit code 0). No package installation, persisted lockfile, Laravel scaffold/code, framework tests or green CI evidence. Separate implementation authorization remains pending.  
**Format**: `[ID] [P?] [US#?] Description (file/path)`; `[P]` means independent files and no ordering dependency. All unchecked boxes represent future work.

## Phase 0 — Approval and runtime preflight (BLOCKING)

- [x] **T001** Obtain explicit owner approval for revised `spec.md` and `plan.md` (review record: `docs/reviews/F0-01-spec-review.md`).
- [x] **T002** In authorized project Codespaces, verified `php -v` = PHP **8.4.27** CLI, `composer --version` = Composer **2.10.3**, and all **13 Laravel 13 required extensions** via explicit `extension_loaded()` probe; owner also shared a partial `php -m` tail. Evidence and source provenance recorded in `quickstart.md` and dated handoff. No dependency graph, framework install or Laravel boot tested.
- [x] **T003** In authorized project Codespaces, owner ran isolated `/tmp` Composer probe: `composer validate --strict --no-check-publish` (`./composer.json is valid`) then `composer update --dry-run --no-install --no-scripts --no-plugins --no-interaction` with `XDEBUG_MODE=off`; exit `T003_COMPOSER_EXIT=0`, **118 planned installs**, zero updates/removals, **no security vulnerability advisories found**. Resolver chose Laravel **v13.43.0**, Pest **v5.3.7**, Pest Laravel Plugin **v5.0.1**, PHPUnit **13.4.7**, under PHP 8.4.27 / Composer 2.10.3. This is an **uninstalled hypothetical graph**; actual project lock/install/platform tests remain separate T005/T021 gates.
- [ ] **T004** Verify the existing Git tree/branch, a non-destructive patch approach, a clean worktree and safe Git rollback route; explicitly confirm no production credentials and no migrations (`docs/reviews/F0-01-spec-review.md`; `quickstart.md`).

**STOP GATE:** No implementation work (T005 onward) until T001–T004 and real environment compatibility are resolved. If CI is unavailable, record its condition rather than claiming success.

## Phase 1 — Bootstrap setup / User Story 1 (P1)

**Goal:** A clean Laravel framework can run after controlled integration without replacing prior repository content, touching a Tenant DB or installing deferred systems.  
**Independent test:** Boot framework from a clean checkout and execute a focused Laravel feature smoke test with no merchant/Tenant credentials.

- [ ] **T005 [US1]** Generate Laravel 13 skeleton in a reviewed temporary workspace and selectively integrate necessary framework root files (`composer.json`, `composer.lock`, `artisan`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/`, `app/`, `public/` and required framework paths); preserve `docs/`, `specs/`, `.specify/` and repository history.
- [ ] **T006 [P] [US1]** Review `.gitignore`, `.env.example` and `phpunit.xml`; prevent tracked credentials, `vendor/`, generated route/config caches, copied secrets and unapproved auth/customer/default DB migrations.
- [ ] **T007 [US1]** Document deterministic `composer install`, package version checks and baseline `php artisan about`/`route:list` commands in `specs/001-laravel-bootstrap-module-registry/quickstart.md`; verify any scripts do not execute Tenant provisioning or destructive migrations.
- [ ] **T008 [P] [US1]** Author framework smoke test at `tests/Feature/Foundation/BootstrapSmokeTest.php`: boot app without Central/Tenant DB, no Tenant config, no customer/merchant identity, no real network and no migrations.
- [ ] **T009 [US1]** Execute bootstrap + smoke against the locked runtime, record the **actual output and SHA** in implementation PR; resolve missing extensions/Composer failures before proceeding.

**US1 checkpoint:** real Laravel bootstrap and smoke pass with no unintended side effects; unsupported runtime is a blocking failure.

## Phase 2 — Explicit registry and demo / User Story 2 (P1)

**Goal:** One declared, non-business module is loaded via real Laravel providers while unlisted/invalid/duplicate registrations are rejected.  
**Independent test:** Test one registered demo, one unregistered fixture, missing provider and duplicate cases, including restricted HTTP probe.

- [ ] **T010 [P] [US2]** Write negative configuration/validation test at `tests/Unit/Core/ModuleRegistryValidationTest.php` for duplicate IDs, duplicate providers, non-provider classes, nonexistent Providers and empty identifiers.
- [ ] **T011 [US2]** Implement minimal reviewed explicit list in `config/modules.php` and fail-fast `app/Core/Modules/ModuleRegistry.php`. Reject bad entries before loading; no filesystem scanning or implicit package discovery.
- [ ] **T012 [US2]** Implement `app/Providers/ModuleRegistryServiceProvider.php` and explicit registration in `bootstrap/providers.php` using native Laravel provider lifecycle; keep register/boot semantics and prohibit Tenant/request global state.
- [ ] **T013 [US2]** Implement `app/Modules/FoundationDemo/FoundationDemoServiceProvider.php` with a deterministic, non-business binding and **test-mode-only** route registered from `app/Modules/FoundationDemo/Routes/testing.php`.
- [ ] **T014 [P] [US2]** Write positive/negative provider and HTTP contract tests in `tests/Feature/Foundation/ExplicitModuleRegistrationTest.php` that prove a declared module binding and test probe exist, but on-disk unlisted fixtures do not activate.
- [ ] **T015 [US2]** Verify unregistered and modified config cases against an **isolated fresh application bootstrap** (not a config mutation after the provider is already loaded). Record expected registration failures; do not accidentally turn deliberate negative-case exceptions into false test failures.
- [ ] **T016 [US2]** Execute tests T010/T014/T015 against real framework, record actual environment, route output and failures before any claim of success.

**US2 checkpoint:** one legitimate Provider loads; invalid/duplicate/unlisted Providers fail predictably; no model, data or Tenant connection exists.

## Phase 3 — Boundary verification / User Story 3 (P2)

**Goal:** Prevent future module auto-discovery/coupling and prove demo API visibility cannot leak into production.  
**Independent test:** Negative architecture and non-testing/route-cache tests reject forbidden activation/route cases.

- [ ] **T017 [P] [US3]** Write focused `tests/Architecture/ModuleRegistryBoundariesTest.php` assertions against implicit directory scanning, unexpected business dependencies and an example forbidden cross-module internal write; avoid an arbitrary universal code-inspection framework.
- [ ] **T018 [US3]** Write `tests/Feature/Foundation/FoundationProbeIsolationTest.php` proving test-only probe availability and its **absence** under non-testing/production environment; ensure production route cache is generated in proper environment and no test cache is copied.
- [ ] **T019 [P] [US3]** Update proposed `docs/03-modules/foundation/module-overview.md` with actual approved paths, owned technical contracts, non-goals and link to canonical `spec.md`; do not create a second editable Feature spec.
- [ ] **T020 [US3]** Execute the architecture, negative route-exposure and ordinary boot checks; fail the review if test routes, unexpected data access or auto-activation appear outside their intended context.

**US3 checkpoint:** new modules can be added only through an explicit reviewed registry; diagnostic endpoints remain outside every production route collection.

## Phase 4 — Finishing / evidence / PR gates

- [ ] **T021 [P]** Run `composer validate --strict`, `composer check-platform-reqs` and required dependency/extension checks against actual `composer.lock`; report exact versions and outcome.
- [ ] **T022** Run all F0-01 targeted smoke/negative/architecture tests with real Laravel 13 and selected Pest/PHPUnit; list command, environment, Git SHA, passed/failed/skipped count and logs. Never substitute design claims for test output.
- [ ] **T023 [P]** Execute compatible Laravel Pint `--test` and applicable PHPStan/Larastan checks if supported; record actual configured levels/tool versions, or explicitly flag blockers rather than claiming pass.
- [ ] **T024** Review Git diff for unchanged historical `docs/`, `specs/`, `.specify/`, no credential files, no business/auth/tenant/default migration additions, no production probe, and no forbidden dependencies.
- [ ] **T025** Update `quickstart.md` / `research.md` / `docs/reviews/F0-01-spec-review.md` with **actual** completed checks, limitations, and owner-reviewed plan changes; do not reclassify D-201, BA-16 or BA-30.
- [ ] **T026** Obtain risk-appropriate PR review and approved test evidence **before** merging; wait for upstream PR #1 integration, then retarget the stacked F0-01 PR to `development` if approved. Do not merge/mark ready simply because planning files were committed.

## Dependencies and execution order

- **Phase 0 T001–T004** must complete before writing framework code; **T001–T003 completed** (T003 dry-run only), while **T004 and separate implementation authorization remain pending**. Stop if PHP/Composer compatibility or authorized execution environment is missing.
- **US1 T005–T009** prepares the skeleton; US2 registry work relies on actual framework bootstrap and test harness.
- **US2 T010–T016** proves provider registration; US3 T017–T020 tests boundaries and route exposure once the registry exists.
- **Final T021–T026** requires real test evidence, code review and upstream setup PR dependency handling.
- Marked **[P]** work may be parallelized when it touches separate files, without bypassing its enclosing phase prerequisites.

## Story-to-requirements trace

| Story | Requirement IDs | Test / review gate |
| --- | --- | --- |
| US1 | FR-001/005/006/008/013/014, SC-001/004 | T005–T009, T021–T024 |
| US2 | FR-002/003/004/007/009/012, SC-002/003/006/007 | T010–T016, T018 |
| US3 | FR-002/007/009/010, SC-005/006/007 | T017–T020, T024 |
| Cross-cutting | FR-011 / Constitution I–VIII | T001–T004, T025–T026 |

## Implementation strategy

1. **Approved (F0-01 Option A, 2026-11-23):** F0-01 spec and plan, separate from the already ratified Constitution. Implementation authorization and compatibility evidence are separate.
2. Produce a real Laravel 13 baseline under verified PHP/Composer, preserving the repository, and validate US1 first.
3. Add just enough Provider Registry / test-demo behavior for US2; prove success and failure cases.
4. Add focused architectural/production-route isolation proof for US3; do not infer that passing this proves Tenancy, Auth or financial safety.
5. Record reproducible command/commit evidence, stop on blockers, review and merge only under the approved workflow.

**T001 (approval), T002 (Codespaces runtime prerequisites) and T003 (successful temporary Composer dry-run resolution) are complete. T004–T026 are pending.** No Composer package installation or project lockfile, Laravel code/bootstrap, framework tests, deployments or PR merges were performed. T002/T003 terminal evidence was provided by the owner, not independently executed in the Codespace by this assistant.
