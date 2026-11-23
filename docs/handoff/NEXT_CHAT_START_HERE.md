# START HERE — E-commerce SaaS V2 API — current project handoff

**Last verified:** 2026-11-23  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development`; **current feature:** `feature/f0-01-laravel-bootstrap`  
**Current milestone:** F0-01 implementation authorized and source staged; CI runner initialization BLOCKED, no real lockfile or Laravel tests. **T005 remains unchecked.**

## Read first — la## Read first — authoritative current snapshot

1. **[LATEST: F0-01 Runner Triage + Pest Empty-Suite Guard — 2026-11-23](F0-01_RUNNER_TRIAGE_AND_TEST_GUARDS_2026-11-23.md)** — exact GitHub Actions diagnostics, [Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3), changes and next action.
2. [Prior: T005 CI blocked / T006–T008 prepared](F0-01_T005_CI_BLOCKED_TESTS_PREPARED_2026-11-23.md) and [Laravel 13 source staged](F0-01_T005_SOURCE_STAGED_2026-11-23.md).
3. [Owner's explicit T005 Option A implementation authorization](../reviews/F0-01-implementation-authorization.md) and [NO Codespaces workflow instruction](F0-01_WORKFLOW_NO_CODESPACES_2026-11-23.md).
4. [F0-01 task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [quickstart](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), [approved spec](../../specs/001-laravel-bootstrap-module-registry/spec.md), [plan](../../specs/001-laravel-bootstrap-module-registry/plan.md), [research](../../specs/001-laravel-bootstrap-module-registry/research.md), [review](../reviews/F0-01-spec-review.md).
5. [Historical T004](F0-01_T004_GITHUB_SAFETY_VERIFIED_2026-11-23.md), [T003](F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md), [T002](F0-01_T002_RUNTIME_VERIFIED_2026-11-23.md) handoffs; [constitution](../../.specify/memory/constitution.md), [workflow](../WORKFLOW.md), [business gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).

## Actual verified status — 2026-11-23

- **T001–T004 COMPLETE; T005 IN PROGRESS/BLOCKED; T006–T008 COMPLETE for limited static/docs/authoring scope; T009–T026 PENDING.** Exactly **7 of 26 tasks are checked**. Implementation authorization was separately granted by owner; **merge/deploy was not authorized**.
- Laravel 13 minimal API bootstrap and two authored Pest smoke-test files are on Draft [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2), stacked on Draft [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1). No production routes, migrations, default User/Auth or Tenancy. **No `composer.lock`, vendor install or live Laravel runtime/test result exists.**
- A read-only GitHub Actions [F0-01 workflow](../../.github/workflows/f0-01-laravel-bootstrap.yml) exists to generate a **candidate** lockfile artifact/install/bootstrap/test, but **four observed runs failed with zero job steps**, no usable logs/artifacts (including latest recorded run `45217288046`). Exact reason remains unconfirmed. Blocker tracked under [GitHub Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3).
- **Test-selection hardening:** `phpunit.xml` adds `failOnEmptyTestSuite="true"` and CI now uses `php artisan test --group=foundation --fail-on-empty-test-suite --no-ansi`; XML syntax parsed successfully in the isolated check. **No runtime Pest/PHPUnit tests executed**, so no green CI claims.
- **No viable runner:** local assistant container has PHP 8.4.24 but no Composer, required PHP extensions, Docker or Packagist DNS; connected desktop device is offline. Historical Codespaces PHP/Composer preflight + isolated dependency dry-run were successful but are not installed-project evidence. **Owner expressly forbids further manual Codespaces steps.**
- PR #2 title/body now accurately describe partial source implementation and blocked CI. Both PRs remain Draft/unmerged; no data, secret, migrations, deployment or merge touched.
- D-201 **OPEN**, BA-16 **DEFERRED**, BA-30 G3 **BLOCKED**.

## Next exact action (no user-run Codespaces work)

Unblock the GitHub Actions job initialization via concrete org/repo Actions runner and billing-policy evidence, or obtain an **actually online** independent PHP 8.4+/Composer runner with required extensions/Packagist access. [Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3) records the exact job IDs and **GitHub UI** diagnosis checks; the connector lacks Actions billing/admin APIs. Do not assume the cause or change billing settings without authorization.

When a runner truly starts, **generate a real project `composer.lock` from its committed manifest**, review package/security selection and commit it only after confirmation, then run actual Composer install/platform, Artisan boot/route-list and Pest smoke; record logs/SHA before marking T005/T009 complete. Do not begin further Module Registry gates, merge, deploy or ask the owner to execute Codespaces commands without required runtime evidence.

n continuity

The agreed product is an Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP. Not a full ERP/POS. The most recent historical business approval in the consolidated handoff was **D-200 A**. **D-201** (customer response marker for proposed alternative location) **remains open**; revisit it in the Orders/Fulfillment module, not during F0-01.

## Permanent instruction for future sessions

**Always refresh this file** when work advances. For each milestone, keep a dated snapshot in `docs/handoff/` containing precise approvals, task status, Git SHA/PR status, evidence source/environment, blockers and next action. Keep historical handoffs and decisions for traceability.
