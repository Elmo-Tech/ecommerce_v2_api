# START HERE — E-commerce SaaS V2 API — current authoritative handoff

**Last verified:** 2026-11-24 (Africa/Cairo)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration branch:** `development`  
**Current phase:** **F0-01 CLOSED (26/26)**; **F0-02 API Foundation discovery/spec/plan DRAFT OPEN** on branch `feature/f0-02-api-foundation` in **[Draft PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4)**. **No F0-02 owner approval or runtime implementation yet**. No production deployment, `main` merge, schema migration or Codespaces commands.

## MUST READ FIRST — newly completed integration

**[LATEST VERIFIED FINAL HANDOFF: F0-01 MERGED AND CLOSED](F0-01_MERGED_AND_CLOSED_2026-11-24.md)** — read first. GitHub independently confirmed both merged PRs with green CI:

1. [PR #1 Spec Kit / Constitution](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) **MERGED** → `development`, commit `199ea55ca6acc7137623baee5066c88635ea4a81`; **13/13 CodeRabbit review threads resolved**, [CI #45404474720](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45404474720) **28 passing Python regressions**, Bash syntax and clean source diff.
2. [PR #2 F0-01 Laravel Bootstrap / Explicit Module Registry](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) **MERGED** → `development`, commit `0abf6772a2892410e62335ab0f82edd40c2514df`; **7/7 CodeRabbit threads resolved**. The genuine development+feature PR merge candidate passed [CI #45405086930](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405086930) and [ready recheck #45405284525](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405284525): **31 Laravel/Pest tests / 134 assertions**, standalone **Architecture 3 tests / 23 assertions**, Composer install/lock/platform/audit and tracked PHP syntax checks.
3. **Verified post-merge:** GitHub PR #1/#2 both report `merged=true`. `development` immediately after PR #2 merged was `0abf6772a2892410e62335ab0f82edd40c2514df` (subsequent docs-only commits update the branch head). PR #1's corrected `.specify/scripts/bash/markdown_sections.py` blob **matches exactly** on the merged branch.
4. [F0-01 task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md) updated on `development` to **T001–T026 ALL CHECKED (26/26)**. Historical handoffs remain for traceability; **do not trust their old “Draft/open/25-of-26” status after this verified merge**.

**Quality caveats:** Pint, PHPStan/Larastan were **not installed or run**; do not claim they passed. Review was substantive CodeRabbit `COMMENTED` with all actionable threads resolved; no formal GitHub `APPROVED` submission, which visible branch settings did not require. Owner conditionally approved the two merges. This milestone **does not** authorize production release or additional business/identity/tenancy behavior. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## NEXT EXACT ACTION — REVIEW F0-02 DRAFT PR #4, THEN SPEC/PLAN APPROVAL

1. Open **[F0-02 Draft PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4)** and read [spec.md](https://github.com/Elmo-Tech/ecommerce_v2_api/blob/feature/f0-02-api-foundation/specs/002-api-foundation/spec.md), [plan.md](https://github.com/Elmo-Tech/ecommerce_v2_api/blob/feature/f0-02-api-foundation/specs/002-api-foundation/plan.md), [research](https://github.com/Elmo-Tech/ecommerce_v2_api/blob/feature/f0-02-api-foundation/specs/002-api-foundation/research.md), and [requirements checklist](https://github.com/Elmo-Tech/ecommerce_v2_api/blob/feature/f0-02-api-foundation/specs/002-api-foundation/checklists/requirements.md). Read the [dated F0-02 handoff](https://github.com/Elmo-Tech/ecommerce_v2_api/blob/feature/f0-02-api-foundation/docs/handoff/F0-02_DISCOVERY_AND_DRAFT_PR_2026-11-24.md) on the **feature branch**.
2. **Resolve four explicit review decisions** from spec/plan: F0-02-DEC-01 ar/en fallback locale; DEC-02 stable error-code representation under int-backed PHP Enum rules; DEC-03 canonical OpenAPI format/path without new packages; DEC-04 empty native API route seam versus deferred. The document proposes safe defaults but **none are pre-approved**.
3. Owner reviews and approves/revises **both** F0-02 spec and implementation plan. Only after approval, generate final tasks and tests, then implement authorized API Foundation scope on `feature/f0-02-api-foundation` with real CI and PR review. Draft PR #4 is **documentation-only** and must stay Draft/unmerged until its required gates.
4. Governing references: [Constitution](../../.specify/memory/constitution.md), [API Standards AS-01/02/03/08/09](../02-standards/api-standards.md) plus FBR-02-05/06/07, [Module Architecture](../01-architecture/module-architecture.md), [Code Standards](../02-standards/code-standards.md), [Testing Standards](../02-standards/testing-standards.md), and real merged F0-01 baseline. **Do not copy older numeric Money/ID illustrative examples as wire contracts; FBR-02 requires decimal strings.**
5. **No Codespaces commands**, production DB, Tenant/Auth implementation, migration, deployment, `main` promotion or unapproved API implementation.

## Other useful context and exact files

- [Approved F0-01 Spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [Plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [Quickstart and actual CI](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [F0-01 final review handoff](F0-01_FINAL_REVIEW_READY_2026-11-24.md).
- [Current technical modules](../../config/modules.php), `app/Modules/FoundationDemo/`, `app/Providers/ModuleRegistryServiceProvider.php`, `tests/Unit/Core/`, `tests/Feature/Foundation/`, `tests/Architecture/`, `.github/workflows/f0-01-laravel-bootstrap.yml`.
- [No-Codespaces owner workflow](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md), [separate original implementation approval](../reviews/F0-01-implementation-authorization.md), [historical T026 review/merge snapshot](F0-01_T026_REVIEW_RECHECK_2026-11-24_1505.md).
- The old F0-01 hourly conditional PR review/merge monitor was **DISABLED** after both verified merges (automation ID `6b04d2ee76dc81918794c538f0c0aec3`). Start another automation only for a new, relevant owner request.

## Product and decision continuity

Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP; **not** a full ERP/POS. Last historical approved decision D-200 A; **D-201 OPEN** (customer response marker for alternative stock location) to revisit during Orders/Fulfillment, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
