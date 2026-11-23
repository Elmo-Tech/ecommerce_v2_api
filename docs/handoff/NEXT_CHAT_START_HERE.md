# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Specification and Technical Plan **APPROVED (Option A)**; implementation has **NOT** started.

## Read first — latest working instructions

1. [LATEST: No-Codespaces workflow handoff — 2026-11-23](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md) — **do not request any further owner-run Codespaces commands**; alternative execution plan and real limitations.
2. [T003 Composer verified snapshot](F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md) and [T002 runtime evidence](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md); both remain historical, user-provided Codespaces output, not current environment evidence.
3. [F0-01 tasks](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [research](../../specs/001-laravel-bootstrap-module-registry/research.md) and [review](../reviews/F0-01-spec-review.md).
4. [Constitution](../../.specify/memory/constitution.md), [workflow](../WORKFLOW.md), [open MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md), [Business Discovery v5.48](ecommerce_saas_handoff_v5_48_2026-11-16.md).

## Current state — 2026-11-23

- F0-01 Specification + Technical Plan **approved Option A**, commit `e730a5c`.
- **T001–T003 complete**. T003 is a **temporary Composer dry-run** only; not package install or locked application. **T004 remains open**, T005–T026 unstarted. Separate implementation authorization remains required.
- User explicitly ended **manual Codespaces work**. Future GitHub checks/documentation/PR maintenance should be handled through connected GitHub; real PHP tests must use an assistant-managed compatible environment or approved CI, if available.
- Assistant container (independently probed) has PHP 8.4.24 but lacks Composer and several extensions, and Docker is absent; it is **not yet a validated Laravel runner**. GitHub Actions previously had runner startup failures and is **not proven green**.
- Earlier Codespaces evidence: PHP 8.4.27, Composer 2.10.3, 13/13 required extensions; T003 dry-run selected Laravel v13.43.0, Pest v5.3.7, Plugin v5.0.1, PHPUnit 13.4.7, solver exit 0, 118 **simulated** installs.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) and [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) remain **Draft / unmerged**, with PR #2 stacked on PR #1.
- No Laravel code, project `composer.lock`, package installation, live tests, migrations, deployment or merge has been performed.
- D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED.

## Next action — without user-run Codespaces steps

Continue T004 **via GitHub and an isolated execution alternative**, verify remote refs and docs-safe diff, establish credential-safety/rollback evidence and confirm a usable runtime/CI before claiming test pass. Keep old Codespaces commands in `quickstart.md` only as reproducibility history, **not** as a user task. Do not start T005 without explicit implementation permission and the remaining preflight evidence. Do not merge or deploy.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
