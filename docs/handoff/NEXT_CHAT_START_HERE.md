# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Specification and Technical Plan **APPROVED (Option A)**; implementation has **NOT** started.

## Read first — latest status & decision

1. **[LATEST: T005 Laravel 13 official source staged, runtime/lock blocked — 2026-11-23](F0-01_T005_SOURCE_STAGED_2026-11-23.md)** — exact changed file inventory, static checks, limitations and next step.
2. [Owner's explicit T005 implementation authorization](../reviews/F0-01-implementation-authorization.md) — **Option A** separately approved beginning T005 on the same feature branch, **NOT** deploying/merging.
3. [No-Codespaces workflow rule](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md) — do not ask the owner to run commands in Codespaces.
4. [F0-01 task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [approved technical plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [specification](../../specs/001-laravel-bootstrap-module-registry/spec.md), [quickstart/evidence](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [research](../../specs/001-laravel-bootstrap-module-registry/research.md), [review](../reviews/F0-01-spec-review.md).
5. [Earlier T004 safety handoff](F0-01_T004_GITHUB_SAFETY_VERIFIED_2026-11-23.md), [T003 Composer dry-run](F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md), [T002 PHP/Composer check](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md), [Constitution](../../.specify/memory/constitution.md), [workflow](../WORKFLOW.md) and [open MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).

## Current implementation state — 2026-11-23

- F0-01 Specification + Technical Plan **APPROVED — Option A**. **Separate owner approval to START T005 also GRANTED** in this conversation (response `a`) and recorded on GitHub.
- **T001–T004 COMPLETE. T005 IN PROGRESS / PARTIALLY STAGED, NOT COMPLETE. T006–T026 PENDING.** A successful source-file commit is not a validated Laravel application.
- 33 Laravel 13 framework/config/structure files were selectively added on `feature/f0-01-laravel-bootstrap`, based on the official upstream `laravel/laravel` `13.x` skeleton. Root now includes `composer.json`, `artisan`, `bootstrap/app.php`, `bootstrap/providers.php`, `app/`, `config/`, `public/index.php`, `phpunit.xml`, safe `.env.example`, runtime storage markers.
- The skeleton uses PHP `^8.4`, Laravel `^13.0`, Pest `^5.0`; `composer.json` has **no install/migration scripts**. `bootstrap/app.php` registers **no web/API/health production route**. Default User/auth, migrations, Sanctum, tenancy and business features remain excluded. Config uses array cache/session, sync queue and in-memory SQLite placeholder.
- GitHub comparison verified the initial 33-source-file staging plus approval record did **not overwrite** historic approved docs/specs/.specify; `README.md` was subsequently **minimally updated only to describe the actual new status**.
- Static sanity: modified `bootstrap/app.php` and `routes/console.php` passed `php -l` in assistant scratch; new `composer.json` parsed as JSON. **NO actual Laravel bootstrap, Composer install, Pest tests, CI pass, migration, deployment or PR merge was executed**.
- **Critical T005 blocker:** project `composer.lock` is **absent**. Assistant container has PHP 8.4.24 but lacks Composer, required extensions and working package-network access; a connected remote desktop runner is offline; old GitHub Actions `init` jobs failed before steps. Earlier Codespaces T003 dry-run exit 0 (Laravel 13.43.0/Pest 5.3.7/PHPUnit 13.4.7) is historical, NOT this project's installed lock. **Never invent `composer.lock` or test success**.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) and [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) still **Draft/unmerged**; PR #2 is stacked on PR #1. No production credentials or tenant DB changes.
- Business **D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED**.

## Exact next action — continue T005 without Codespaces

Find/enable a truly compatible **assistant-managed** non-Codespaces runner or properly functioning GitHub Actions to generate the real `composer.lock` for the **committed** manifest and validate install/platform/Laravel boot; record actual logs and commit SHA. Keep lockfile and runtime-test gates open until checks run. Preserve all branch history and previous decisions. **Owner has already authorized starting T005 — do not re-ask for the same authorization; but no deploy/merge approval exists.** If a compatible runner remains unavailable, report the blocker instead of claiming completion. Never ask the owner to execute Codespaces commands.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
