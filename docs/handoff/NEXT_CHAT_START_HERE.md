# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Specification and Technical Plan **APPROVED (Option A)**; implementation has **NOT** started.

## Read first (current, authoritative)

1. [Latest dated F0-01 handoff: 2026-11-23 preflight](F0-01_preflight_2026-11-23.md).
2. [F0-01 Task Ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [Technical Plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [Specification](../../specs/001-laravel-bootstrap-module-registry/spec.md).
3. [F0-01 owner approval review](../reviews/F0-01-spec-review.md), [Constitution v1.0.0](../../.specify/memory/constitution.md).
4. [Architecture and workflow conventions](../WORKFLOW.md) and [open MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).
5. [Full Business Discovery Handoff v5.48](ecommerce_saas_handoff_v5_48_2026-11-16.md) and [decision register D-152 onward](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md).

## Verified status — do not assume more

- **T001 completed** (F0-01 Option A approved, commit `e730a5c`). **T002–T026 pending**.
- In an isolated assistant container, **PHP 8.4.24** was found but **Composer was absent**, as were required PHP extensions **curl, dom, mbstring, xml**. **This was not the project's Codespace**. Codespaces runtime is still unverified.
- Laravel 13 requires PHP >=8.3. PHPUnit ^12 and Pest ^4 are documented upgrade guidance, **not a tested Composer resolution**.
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) Draft/unmerged; [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) Draft/unmerged and stacked on PR #1.
- No Laravel skeleton (`composer.json`/`artisan`) existed at original commit `e730a5c`; no migrations, implementation tests, CI passing proof or deployment.
- Business **D-201 OPEN**, **BA-16 DEFERRED**, **BA-30 G3 BLOCKED**. Approved architecture/decisions remain authoritative; no silent choice changes.

## Exact next action: T002 Codespaces runtime and worktree evidence

Run inside the **authorized project Codespace** and share output. Read-only:

```bash
php -v
composer --version
php -m
git status --short
git branch --show-current
git log -1 --oneline
```

Then finish T003 dependency/version verification and T004 clean-worktree/safe-scaffold review. **Do not begin T005 or merge** without actual preflight and the separate implementation authorization. This handoff update does not change approved F0-01 Option A.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
