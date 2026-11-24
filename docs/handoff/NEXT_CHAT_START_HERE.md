# START HERE — E-commerce SaaS V2 API — current authoritative handoff

**Last verified:** 2026-11-24 (Africa/Cairo)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration branch:** `development`  
**Current phase:** **F0-01 CLOSED (26/26 tasks)**; move to **F0-02 API Foundation — requirements/specification and architecture planning first**. No production deployment, `main` merge, schema migration or Codespaces commands.

## MUST READ FIRST — newly completed integration

**[LATEST VERIFIED FINAL HANDOFF: F0-01 MERGED AND CLOSED](F0-01_MERGED_AND_CLOSED_2026-11-24.md)** — read first. GitHub independently confirmed both merged PRs with green CI:

1. [PR #1 Spec Kit / Constitution](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) **MERGED** → `development`, commit `199ea55ca6acc7137623baee5066c88635ea4a81`; **13/13 CodeRabbit review threads resolved**, [CI #45404474720](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45404474720) **28 passing Python regressions**, Bash syntax and clean source diff.
2. [PR #2 F0-01 Laravel Bootstrap / Explicit Module Registry](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) **MERGED** → `development`, commit `0abf6772a2892410e62335ab0f82edd40c2514df`; **7/7 CodeRabbit threads resolved**. The genuine development+feature PR merge candidate passed [CI #45405086930](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405086930) and [ready recheck #45405284525](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405284525): **31 Laravel/Pest tests / 134 assertions**, standalone **Architecture 3 tests / 23 assertions**, Composer install/lock/platform/audit and tracked PHP syntax checks.
3. **Verified post-merge:** GitHub PR #1/#2 both report `merged=true`. `development` immediately after PR #2 merged was `0abf6772a2892410e62335ab0f82edd40c2514df` (subsequent docs-only commits update the branch head). PR #1's corrected `.specify/scripts/bash/markdown_sections.py` blob **matches exactly** on the merged branch.
4. [F0-01 task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md) updated on `development` to **T001–T026 ALL CHECKED (26/26)**. Historical handoffs remain for traceability; **do not trust their old “Draft/open/25-of-26” status after this verified merge**.

**Quality caveats:** Pint, PHPStan/Larastan were **not installed or run**; do not claim they passed. Review was substantive CodeRabbit `COMMENTED` with all actionable threads resolved; no formal GitHub `APPROVED` submission, which visible branch settings did not require. Owner conditionally approved the two merges. This milestone **does not** authorize production release or additional business/identity/tenancy behavior. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## NEXT EXACT ACTION — F0-02 API Foundation (do not skip specification gate)

1. Read [project workflow](../WORKFLOW.md), [Constitution v1.0.0](../../.specify/memory/constitution.md), [API Standards](../02-standards/api-standards.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Module Architecture](../01-architecture/module-architecture.md), [relevant Open Decisions](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md), [F0-01 actual source](../../app/Core/Modules/ModuleRegistry.php), [current composer manifest](../../composer.json) and [current routes](../../bootstrap/app.php).
2. Identify the approved **F0-02 API Foundation** responsibilities and exclusions from existing standards/decisions. Do not infer auth/Sanctum, Tenant provisioning, migrations or business APIs are approved by the name F0-02. Record a bounded scope and any open decisions.
3. Use a dedicated `feature/f0-02-api-foundation` branch from current `development` for **draft `spec.md`, `plan.md`, `research.md`/Contracts and tasks after review**. Cross-check Foundation module ownership, `/api/v1` conventions, expected envelope and errors; do not duplicate existing approved API Standards.
4. Obtain the required **owner approval of F0-02 specification and plan before implementation**. Do not claim tests passed or open/move PR to ready/merge without actual execution. Keep the latest source/CI and all future work handoffs current.
5. **No Codespaces commands**, deployment, production databases, Tenant migration, main promotion or undocumented automated workflows.

## Other useful context and exact files

- [Approved F0-01 Spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [Plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [Quickstart and actual CI](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [F0-01 final review handoff](F0-01_FINAL_REVIEW_READY_2026-11-24.md).
- [Current technical modules](../../config/modules.php), `app/Modules/FoundationDemo/`, `app/Providers/ModuleRegistryServiceProvider.php`, `tests/Unit/Core/`, `tests/Feature/Foundation/`, `tests/Architecture/`, `.github/workflows/f0-01-laravel-bootstrap.yml`.
- [No-Codespaces owner workflow](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md), [separate original implementation approval](../reviews/F0-01-implementation-authorization.md), [historical T026 review/merge snapshot](F0-01_T026_REVIEW_RECHECK_2026-11-24_1505.md).
- The old F0-01 hourly conditional PR review/merge monitor is **obsolete and must be disabled** after both completed merges. Start another automation only on a new, relevant owner request.

## Product and decision continuity

Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP; **not** a full ERP/POS. Last historical approved decision D-200 A; **D-201 OPEN** (customer response marker for alternative stock location) to revisit during Orders/Fulfillment, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
