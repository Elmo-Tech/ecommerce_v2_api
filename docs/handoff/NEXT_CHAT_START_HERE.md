# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Specification and Technical Plan **APPROVED (Option A)**; implementation has **NOT** started.

## Read first (authoritative, newest to oldest)

1. [LATEST: F0-01 T002 VERIFIED — 2026-11-23](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md) — exact Codespaces PHP/Composer evidence, task ledger, T003 next step and gates.
2. [F0-01 Task Ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [T003 temporary Composer probe](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [updated dependency research](../../specs/001-laravel-bootstrap-module-registry/research.md).
3. [Approved Technical Plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [Specification](../../specs/001-laravel-bootstrap-module-registry/spec.md), [owner approval review](../reviews/F0-01-spec-review.md), [Constitution v1.0.0](../../.specify/memory/constitution.md).
4. [Older preflight snapshot](F0-01_preflight_2026-11-23.md) — historical partial environment observations only; do **not** override newer actual Codespaces evidence.
5. [Project workflow](../WORKFLOW.md), [open MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md), [Business Discovery Handoff v5.48](ecommerce_saas_handoff_v5_48_2026-11-16.md) and [decision register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md).

## Latest verified project status — 2026-11-23

- F0-01 Specification + Technical Plan **APPROVED, Option A** (owner decision `e730a5c`).
- **T001 and T002 COMPLETE**. **T003–T026 pending**, including T004 whose clean-branch check is partially evidenced but production secret/rollback checks remain open.
- Owner provided actual **project Codespaces terminal output**: `PHP 8.4.27 CLI`, `Composer 2.10.3`, **13/13 Laravel-required extensions OK**; clean worktree on `feature/f0-01-laravel-bootstrap` at local `accbe34`. Earlier assistant-container environment issues are irrelevant to the Codespace.
- Xdebug 3.5.3 tried `localhost:9003` without a debugging client; diagnostic only, not Composer solver failure. Use `XDEBUG_MODE=off` for package probes.
- Upstream research in November 2026 indicates **Pest 5 + PHPUnit 13** is the newer candidate test stack on PHP 8.4, with patch-level compatibility caveats. **No real Composer dry-run or package graph has run yet**; versions are not pinned/approved.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) and [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) **still Draft / unmerged**, stacked as planned.
- No Laravel skeleton, Composer install, DB migrations, application tests, green CI proof or deployment. **Separate authorization required before T005.**
- Business **D-201 OPEN**, **BA-16 DEFERRED**, **BA-30 G3 BLOCKED** remain unchanged.

## Exact next action: Composer T003 compatibility resolution, without installing

The project Codespace local SHA was `accbe34` at the runtime check; GitHub has since received documentation-only commits. First confirm a clean working tree, then fast-forward safely:

```bash
git status --short
git fetch origin
git pull --ff-only
git log -1 --oneline
```

Then run the **fully specified /tmp-only dry-run Composer probe** in [F0-01 quickstart](../../specs/001-laravel-bootstrap-module-registry/quickstart.md#t003-dependency-resolution-probe-pending--run-in-codespaces-only). Return the Composer solver output and `T003_COMPOSER_EXIT`; do not ignore platform/security constraints, install dependencies, scaffold Laravel, run migrations or merge PRs yet.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
