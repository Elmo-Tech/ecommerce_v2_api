# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-24 (T010–T016 in progress; no test completion claimed yet)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone (2026-11-24):** **Module Registry plus Architecture and Production Probe/Route-Cache Isolation VERIFIED by GitHub Actions**. T001–T020 COMPLETE (20/26), T021–T026 PENDING. PR #1/#2 Draft/unmerged; no Codespaces, merge or deployment.

**Last implemented milestone:** T010–T020 including production route and cache isolation verified in real GitHub Actions. See [latest dated handoff](F0-01_ARCHITECTURE_AND_PRODUCTION_PROBE_VERIFIED_2026-11-24.md) for exact read order and T021–T026 next actions. No Codespaces; do not merge/deploy.

## Read first — current source of truth

1. **[LATEST VERIFIED: F0-01 Architecture + Production Probe Isolation T017–T020 — 2026-11-24](F0-01_ARCHITECTURE_AND_PRODUCTION_PROBE_VERIFIED_2026-11-24.md)** — **26 tests passed, 116 assertions** in [Actions #45284904141](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45284904141). Complete source/review read order and next T021–T026.
2. **[Earlier registry proof T010–T016](F0-01_MODULE_REGISTRY_VERIFIED_2026-11-24.md)** — **19 tests / 63 assertions passed** in GitHub Actions [#45283746273](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45283746273), source inventory, decisions, required read order and T017 next step.
2. [Historical module registry WIP checkpoint](F0-01_MODULE_REGISTRY_WORK_IN_PROGRESS_2026-11-24.md) — do not treat earlier WIP statuses as current.
2. **[LAST PASSED: F0-01 Bootstrap Validated — 2026-11-23](F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md)** — verified green baseline before registry changes.
2. [Task ledger T001–T009 complete](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [approved Spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [approved Plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [Quickstart](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [Research](../../specs/001-laravel-bootstrap-module-registry/research.md).
3. [Separate T005 authorization](../reviews/F0-01-implementation-authorization.md) and [No-Codespaces workflow](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md).
4. [Historical private Actions blocker](F0-01_PRIVATE_REPO_RUNNER_TRIAGE_2026-11-23.md), [Constitution](../../.specify/memory/constitution.md), [branch workflow](../WORKFLOW.md), [open business gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).

## Current verified project state — 2026-11-23

- **T001–T020 COMPLETE (20/26); T021–T026 PENDING.** F0-01 Spec + Plan Option A and separate implementation authorization approved. **[CI Run #45284904141](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45284904141) SUCCESS at code SHA `1a03b86b`**: PHP 8.4.28/Laravel 13.43.0, **26 Pest tests / 116 assertions**, including explicit registry, fresh-boot invalid/unlisted scenarios, architecture checks, absent test probe in `production`/`local`, real-app empty production routes, and a real production-generated cache in a separate scratch Laravel fixture without the probe. No migrations, Tenant data, deployment or merges.
- [GitHub Actions Run #45239265994](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45239265994) **PASSED** at tested source SHA `a446e0be`. Real Ubuntu runner PHP `8.4.28`, Composer `2.11.2`, Laravel `13.43.0`; committed lockfile validated, locked install (no scripts/plugins), platform checks, `composer audit --locked` (no advisories reported), `php artisan about`, `php artisan route:list --json` (`[]`), and DB-free Pest Foundation smoke **1 passed, 9 assertions, no warnings**. Tracked-file diff check passed.
- `composer.lock` generated from a real GitHub Actions artifact, verified and committed (`5d334ecd`, 118 packages). CI installs **pinned dependencies** rather than running `composer update` each time.
- Test failures were real and fixed: missing `tests/Unit` directory, Laravel's default private Storage GET/PUT routes disabled via local `serve=false`, and missing dotenv PHPUnit warning resolved by an **empty, ignored, ephemeral CI-only `.env`**. No production credentials, default User/Auth, Sanctum, tenancy, migrations or data access.
- Owner reported former GitHub Actions limit on private repositories was lifted; subsequent CI runs actually executed. The historical runner blocker [Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3) is **CLOSED**; the assistant did not inspect billing amounts.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) remains Draft/unmerged → `development`; [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) remains Draft/unmerged → `feature/f0-spec-kit-setup`. No merges/deployment. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.
- **Updated scope limit:** Registry, invalid/unlisted providers, test-only route absence in non-testing/production and scratch generated production cache, and narrow architecture boundaries **have been proven by actual tests**. **Still pending:** independent dependency/full-suite, Pint/PHPStan compatibility, comprehensive final diff/secret review, docs and PR gates T021–T026. Read code under `app/Core/Modules/`, `app/Providers/`, `app/Modules/FoundationDemo/`, `config/modules.php`, `bootstrap/providers.php`, `tests/{Unit,Feature,Architecture,Fixtures}/`, and `.github/workflows/f0-01-laravel-bootstrap.yml`.

## Next exact action — T021–T026 (no Codespaces)

**Now T021**: verify committed Composer lock/platform/extensions and record exact CI output. **T022**: run the entire F0-01 PHPUnit/Pest Unit+Feature+Architecture suite in a fresh GitHub Actions run (not only the `foundation` group), record counts/commit. **T023**: check whether Pint/PHPStan/Larastan are actually installed/compatible; run what is available and record limitations. **T024**: review full final code diff for Secrets, Auth/Tenancy/Migrations, public probe, undesired packages and non-destructive preservation. **T025**: correct all stale quickstart/research/spec/review docs and dated handoff. **T026**: independent PR review and upstream PR #1 integration **only with appropriate approval**; keep Draft/unmerged. **No Codespaces, production migrations or deployment**.

## Product and decision continuity

Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP; **not** a full ERP/POS. Last historical approved decision D-200 A; **D-201 OPEN** (customer response marker for alternative stock location) to revisit during Orders/Fulfillment, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
