# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Option A **APPROVED**, implementation start separately authorized; Laravel source and initial tests staged but actual runtime, lockfile and CI are **BLOCKED**.

## Read first — latest verified state

1. **[LATEST: F0-01 T005 CI BLOCKED / T006–T008 PREPARED — 2026-11-23](F0-01_T005_CI_BLOCKED_TESTS_PREPARED_2026-11-23.md)** — actual GitHub Actions job IDs/results, code/tests added, remaining prerequisites and exact continuation.
2. [T005 Laravel 13 staged source handoff](F0-01_T005_SOURCE_STAGED_2026-11-23.md) and [separate Option A implementation authorization](../reviews/F0-01-implementation-authorization.md).
3. [No-Codespaces workflow](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md): owner **does not want to run any more Codespaces commands**.
4. [Current F0-01 task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [updated quickstart](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [approved spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [research](../../specs/001-laravel-bootstrap-module-registry/research.md), and [F0-01 review record](../reviews/F0-01-spec-review.md).
5. Earlier [T004](F0-01_T004_GITHUB_SAFETY_VERIFIED_2026-11-23.md), [T003](F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md) and [T002](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md) handoffs; [Constitution](../../.specify/memory/constitution.md), [workflow](../WORKFLOW.md), [open business gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).

## Precise current project status — 2026-11-23

- Owner approved **F0-01 Spec + Technical Plan Option A**, and separately approved **beginning implementation T005** on `feature/f0-01-laravel-bootstrap`. No authorization to deploy or merge.
- **T001–T004 DONE; T005 IN PROGRESS/BLOCKED (no real `composer.lock` or Laravel install); T006–T008 DONE for static review/documentation/authored code ONLY; T009–T026 OPEN.**
- Laravel 13 API-first skeleton exists on GitHub: 33 source/config files from official `laravel/laravel` `13.x`, plus 2 Pest test files `tests/Pest.php` and `tests/Feature/Foundation/BootstrapSmokeTest.php`. The tests are **not executed**. `bootstrap/app.php` has no web/health/public API route, and no User/identity/tenant/default migrations were added.
- `.github/workflows/f0-01-laravel-bootstrap.yml` defines PHP 8.4, a candidate `composer.lock` artifact, a safe no-scripts/no-plugins dependency install, Laravel Artisan checks and smoke test. CI is path-filtered to avoid running on documentation-only pushes. Its three observed runs **45210517286**, **45211196092**, **45211745261** all **failed before any step** with zero job steps, no useful logs and no lock artifact; exact platform cause **unknown**. **Do not call CI or Laravel tests passed.**
- T006 remote static inspection of 9 files had 13/13 defensive assertions; the 54-file PR #2 patch review detected zero high-confidence secret-pattern hits and no unexpected migration/auth/default User files. **These are bounded checks, not historical secret or dependency audits.**
- PHP 8.4 syntax check passed for the two authored test files in isolated scratch. The assistant container lacks Composer, required extensions and usable Packagist DNS, so no real app install can run there. Earlier Codespaces T002/T003 results are historical preflight **only**, and the owner prohibits additional manual Codespaces work.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) and [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) remain **Draft/unmerged**; PR #2 is stacked on PR #1.
- D-201 **OPEN**, BA-16 **DEFERRED**, BA-30 G3 **BLOCKED**.

## Single next action — no user-run Codespaces commands

**Restore a functional independent PHP/Composer runner or GitHub Actions job initialization** before finalizing T005. Diagnose GitHub Actions organization/repository execution settings and runner availability **only with actual accessible evidence**; the present connector exposes job failure but no reason. When a runner is genuinely available, execute reviewed Composer manifest/lockfile generation and real install, inspect the generated `composer.lock`, run `php artisan about`, `php artisan route:list`, the Foundation smoke test and subsequent tests. Do not invent a lockfile or claim success from a dry-run. Keep PRs Draft, do not merge/deploy, and **do not ask the owner to use Codespaces**.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
