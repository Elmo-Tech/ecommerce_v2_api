# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 Specification and Technical Plan **APPROVED (Option A)**; implementation has **NOT** started.

## Read first (current > previous)

1. [LATEST: F0-01 T003 COMPOSER VERIFIED — 2026-11-23](F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md) — exact resolution evidence, status and T004 next action.
2. [Task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [T004 safety commands and T003 procedure](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [technical research](../../specs/001-laravel-bootstrap-module-registry/research.md), [review record](../reviews/F0-01-spec-review.md).
3. [Approved F0-01 plan](../../specs/001-laravel-bootstrap-module-registry/plan.md) and [spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [Constitution](../../.specify/memory/constitution.md).
4. [T002 handoff (historical)](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md), [older preflight handoff (historical)](F0-01_preflight_2026-11-23.md).
5. [Workflow](../WORKFLOW.md), [open MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md), [Business Discovery v5.48](ecommerce_saas_handoff_v5_48_2026-11-16.md), [decision register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md).

## Latest verified project status — 2026-11-23

- F0-01 Specification and Technical Plan **APPROVED (Option A)** by owner; design approval commit `e730a5c` remains authoritative.
- **T001, T002, T003 COMPLETE**; **T004 PARTIAL/PENDING**, **T005–T026 NOT STARTED**. Implementation authorization remains a **separate pending gate**.
- The owner supplied authentic Codespaces results: PHP `8.4.27`, Composer `2.10.3`, all 13 required extensions; clean working tree at local `accbe34` at T002 time.
- **T003 dependency dry-run SUCCESS** in `/tmp/f0-01-composer-qwmppK`: `composer validate --strict --no-check-publish` valid; `composer update --dry-run --no-install --no-scripts --no-plugins --no-interaction` exit `0`; 118 **simulated** installs; no security advisories reported. Selected `laravel/framework v13.43.0`, `pestphp/pest v5.3.7`, `pestphp/pest-plugin-laravel v5.0.1`, `phpunit/phpunit 13.4.7`.
- **Not done:** actual project `composer.lock` and install, Laravel skeleton, `composer check-platform-reqs`, Laravel boot/test, migrations, CI pass, deployment or merge. **No real application code exists.**
- [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) is Draft/unmerged and [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) is Draft/unmerged, stacked on PR #1.
- Business **D-201 OPEN**, **BA-16 DEFERRED**, **BA-30 G3 BLOCKED**.

## Exact next action — T004 Git and credential safety checks

In project Codespaces, with an initially clean worktree, run the **read-only/fast-forward Git and tracked-filename checks** in [quickstart T004](../../specs/001-laravel-bootstrap-module-registry/quickstart.md#t004-non-destructive-git--credential-filename-preflight-pending). They inspect the current local SHA, branch ancestry, tracked credential-like paths and presence of code/migrations, **without revealing secret contents**. Share output. The repository branch is likely ahead of the prior local `accbe34` due to handoff commits; do not force-reset.

**T004 must also verify** a non-destructive skeleton integration and safe rollback procedure and confirm no production credentials are being used. A filename check alone cannot prove credentials are absent. **STOP BEFORE T005**, and obtain the separate owner implementation authorization before writing code. The current handoff does not authorize deployment or PR merges.

## Product/decision continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
