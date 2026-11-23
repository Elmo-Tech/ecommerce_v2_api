# F0-01 — Feature Specification and Constitution Review

> **Current status (2026-11-24): F0-01 Option A APPROVED; T001–T024 COMPLETE, T025–T026 pending.** Verified through real [GitHub Actions #45285351899](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45285351899): Laravel 13.43.0 with authentic committed lockfile, Module Registry + FoundationDemo, invalid/unlisted Provider checks, production/local probe isolation, scratch production route-cache proof, **26 tests / 116 assertions (both grouped and unfiltered)**, PHP lints and no-advisory Composer audit. **Pint/PHPStan were NOT installed or executed**. No merge/deployment/migrations.
> **Branch:** `feature/f0-01-laravel-bootstrap` (implementation verified at code/test level, Draft [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) stacked on Draft [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1); unmerged).  
> **Canonical specification:** [F0-01 spec](../../specs/001-laravel-bootstrap-module-registry/spec.md); [Constitution v1.0.0](../../.specify/memory/constitution.md).

## Current code and test verification addendum — 2026-11-24

**This section supersedes earlier dated planning/preflight statuses below**. F0-01's approved engineering design has now been implemented and actually tested. Real GitHub Actions [Run #45285351899](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45285351899) executed both group-filtered and unfiltered Unit/Feature/Architecture suites **26 passed / 116 assertions / 0 failed or skipped** on PHP **8.4.28**, Composer **2.11.2**, Laravel **13.43.0**. Strict Composer lock validation/install/platform, `composer audit --locked` (no advisories at run time), PHP `-l` all tracked PHP source, and clean working diff succeeded. `Pint`, `PHPStan` and `Larastan` are **not present in the approved Composer lock**; CI emitted explicit unavailable notices, **not a lint/static-analysis pass**. Source/security review: PR #2 **75 file patches**, no high-confidence credential-pattern hits, no committed `.env`, Auth/User/Tenancy/migrations, and no unapproved packages; `composer.lock` was separately parsed because its patch was omitted due size. This bounded check does not prove historical repository secrets are absent.

- [T010–T016 registry validation](../handoff/F0-01_MODULE_REGISTRY_VERIFIED_2026-11-24.md): native Laravel ModuleRegistry + Demo Provider, negative missing/duplicate/unlisted in freshly booted app.
- [T017–T020 architecture/prod cache](../handoff/F0-01_ARCHITECTURE_AND_PRODUCTION_PROBE_VERIFIED_2026-11-24.md): test route absent in non-testing, real app production routes empty, scratch copied app with harmless redirect generates real production route cache without demo endpoint. No scratch artifact shipped.
- [Live task status](../../specs/001-laravel-bootstrap-module-registry/tasks.md): **T001–T024 complete** with explicit quality tool limitation. **T025** documentation and final handoff; **T026** separate independent PR review and owner/branch merge gate. Approval to implement is not approval to merge or deploy. PR #1/2 remain Draft/unmerged; D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED.

## Review result

The existing three user stories, requirements FR-001…FR-011, edge cases and outcomes fit the approved **F0 Engineering Foundation** direction. No business-domain implementation, Tenant schema, authorization flow or live merchant behavior is within this Feature. The review adds precise non-destructive bootstrap, test-only diagnostics, package-boundary and negative registration criteria (FR-012…014; SC-006…007). **These are proposed clarifications, not new D/OV product decisions.**

| Constitutional principle | Assessment | F0-01 treatment / remaining evidence |
| --- | --- | --- |
| I. Approved sources and bounded scope | PASS — spec scope | MA F0, FND-01, FND-02 and existing CS/TEST govern; F0-02/F1 excluded; owner Spec + Plan approval recorded; no runtime evidence yet |
| II. One modular monolith, One Writer | PASS — planned design | Explicit providers only, no directory discovery or 16 empty modules; lightweight `app/Core/Modules` and test probe proposed |
| III. Trusted tenancy and least privilege | N/A to business data / PASS on exclusions | No Central/Tenant connection, identity, permissions or untrusted Tenant selection in F0-01; future F1 separately reviewed |
| IV. Stock/money correctness | N/A, no affected business state | No DB tables, transactions, inventories, money or events introduced |
| V. API and security contracts | CONDITIONAL — verify test-only routing | No production feature/API envelope or `install:api`; test probe only within test environment; production route absence must be proven |
| VI. Outbox, files and audit | N/A to runtime effects | No queues, outbox, uploads or business logs; do not imply their delivery |
| VII. Spec and test evidence | CONDITIONAL — future runtime tests | Separate `spec.md` / `plan.md` / `tasks.md` drafts exist; automated smoke, duplicate/missing/unregistered and production exclusion tests require real PHP runtime |
| VIII. Simple, compatible and gated | CONDITIONAL — compatibility & owner gates | Laravel 13 requires PHP >=8.3 (official docs); exact environment/Pest/Composer and CI must be verified before implementation/merge |

## Verification against repository architecture

- MA §13 and CS-01 require an **explicit** Module Registry, module-owned Providers, route registration, no unrestricted directory scanning and no circular ownership.
- MA §16 F0 says a minimal registered module must expose a contract and pass Module/API boundary tests, **not** that tenant or other domain modules should be built immediately.
- TEST-01/02 require risk-based tests; PHPUnit/Pest compatibility and actual executable tests must not be guessed from an empty repository.
- Existing `spec.md` aligns with the Spec Kit v1.4.3 user stories/requirements/success outcomes template; agent-neutral `none` mode means no agent `/speckit.plan` invocation is claimed.
- FND-01 uses exactly one Feature folder under `specs/001-laravel-bootstrap-module-registry/`; technical Foundation Overview links to it but does not duplicate it.

## Technical choices accepted under F0-01 Option A (now verified by real CI)

1. **Repository layout:** Laravel skeleton at repository root, incrementally integrated without replacing existing documentation or history.
2. **Provider registry:** a tiny static config-list-driven Module Registry; validate duplicate/missing/invalid Providers; never auto-scan module folders.
3. **Probe boundary:** one non-business `FoundationDemo` provider; a route/contract visible in the **testing environment only**, and absent in production/route caches.
4. **Dependency ordering:** F0-01 installs only the minimum Laravel skeleton and chosen test dependencies; defer Sanctum installation, Tenant provisioning, DB migrations, Redis and normal production API envelopes.
5. **Testing:** real framework bootstrap, registration (positive and negative), no unwanted side effects and route-exclusion verification; record command/runtime output accurately.

These are **owner-approved technical design choices** in [plan.md](../../specs/001-laravel-bootstrap-module-registry/plan.md) under **F0-01 Option A (2026-11-23)**. Laravel/Composer compatibility, CI execution, explicit registry, negative tests and production route/cache isolation were **subsequently verified** as documented in the current addendum.

## Historical implementation gates at original approval (now superseded by current verification addendum)

| Gate | Current status | Needed before action |
| --- | --- | --- |
| Spec approval | **APPROVED — Option A, 2026-11-23** | Scope and FR-001…014 / SC-001…007 accepted |
| Technical plan approval | **APPROVED — Option A, 2026-11-23** | Explicit Provider Registry and test-only probe design accepted; actual runtime verification outstanding |
| PHP/Composer + candidate dependency compatibility | **T002 + T003 PASS (preflight scope only)** | Codespaces PHP 8.4.27, Composer 2.10.3, all 13 required extensions; isolated Composer dry-run solver exit 0 (Laravel 13.43.0 / Pest 5.3.7 / Plugin 5.0.1 / PHPUnit 13.4.7). **No actual app install, project lock or runtime tests.** |
| Test runner reliability | NOT VERIFIED | Codespaces/manual runs can produce evidence; GitHub Actions #45083043107/#45083678045 previously failed before steps |
| Tasks authorization | **T001–T004 pre-code complete; T005 authorization PENDING** | Owner must explicitly authorize implementation after verified bounded GitHub-only safety review; no automatic code approval |
| Parent Spec Kit setup PR | OPEN / DRAFT | PR #1 must be approved and integrated separately before direct F0-01 merge into `development` |
| Release/legal/recovery | UNCHANGED | D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED |

**Decision recorded:** On 2026-11-23, the owner replied **A** to the explicit question approving **F0-01 Specification + Technical Plan — Option A**. This records binding approval of the reviewed technical scope and design for further preflight, not authorization to run production operations, start Laravel implementation without its separate gate, touch Tenant DBs, declare CI passed or merge a PR.

**Current next gate (as of 2026-11-24):** Historical T004 and separate T005 authorization were completed, and source + CI evidence for T001–T024 exists. Only final documentation/handoff T025 and independent code/PR review T026 remain. Upstream Spec Kit PR #1 still requires separate approval and integration before any F0-01 merge into `development`.

## T004 GitHub-native pre-code safety review — 2026-11-23

**T004 COMPLETE for the bounded pre-implementation remote workflow**, according to [T004 GitHub safety handoff](../handoff/F0-01_T004_GITHUB_SAFETY_VERIFIED_2026-11-23.md). The repo's accepted branch structure and ancestor-only comparisons were verified, and all 66 changes since `main` were under `.specify/`, `docs/` or `specs/`. PR #1 and #2 patch additions and 26 architecture documents were inspected for high-confidence secret-pattern matches (none found); no application runtime/migration or production environment file was added. The assistant has not used application production credentials or performed any database operation.

The workflow avoids relying on an owner's local Codespaces worktree. For future implementation, GitHub content SHA preconditions, new feature-only commits, a fresh isolated runner and reviewed reverse commits/reverts are mandatory. **The scoped textual pattern review is not a comprehensive historical repository secret scan**, and later T006/T024 checks must review the actual implementation delta. The owner's instruction **not to perform further manual Codespaces work** remains binding.

**Historical STOP at T004 (resolved for implementation):** The owner separately authorized T005 and real Laravel/GitHub Actions validation followed. **Current STOP:** no PR merge/deployment until T026 independent review and required approvals. PR #1/#2 remain Draft/unmerged; no migrations/production data/deployment.

## Subsequent pre-implementation evidence — 2026-11-23

The Codespaces owner provided (not independently executed via connector): PHP `8.4.27`, Composer `2.10.3`, and positive checks for all 13 Laravel-required extensions (**T002 complete**); and a strict Composer manifest validation plus **no-install/no-scripts/no-plugins Composer dry-run** in `/tmp/f0-01-composer-qwmppK` with exit `0` (**T003 complete**). It simulated 118 lock operations selecting Laravel `v13.43.0`, Pest `v5.3.7`, Laravel test plugin `v5.0.1`, PHPUnit `13.4.7`, and printed `No security vulnerability advisories found.`

This is package **resolution evidence only**; no project `composer.lock`, packages, Laravel runtime/application, actual test runner execution or CI pass exists. GitHub comparisons through the former head showed only documentation changes on F0-01; local T004 safety/secret checks and rollback evidence still need to be completed. Neither prior owner approval nor this evidence relaxes the separate implementation permission or merge requirements. See [latest dated handoff](../handoff/F0-01_T003_COMPOSER_VERIFIED_2026-11-23.md), [quickstart](../../specs/001-laravel-bootstrap-module-registry/quickstart.md), and [research](../../specs/001-laravel-bootstrap-module-registry/research.md).

## Latest implementation authorization and staging evidence — 2026-11-23

The owner explicitly replied **A** to starting T005 on the existing feature branch, separately from the Spec + Plan approval. The exact permission is in [implementation authorization](F0-01-implementation-authorization.md).

Using official Laravel 13 source, **33 framework/config/structure files** were selectively added, plus the decision record; prior `docs/`, `specs/`, `.specify/` and `README.md` were preserved. The new `bootstrap/app.php` exposes no web/health/API route, `routes/console.php` has no demo work, and `composer.json` omits install/setup migration scripts. No User model, Auth config, default migrations, Sanctum, tenancy or production secrets were introduced. See [T005 staging handoff](../handoff/F0-01_T005_SOURCE_STAGED_2026-11-23.md).

**Historical T005 source-staging state (superseded):** at that time no project `composer.lock`, install or Laravel boot had yet occurred. The assistant's isolated container lacks a compatible Composer/extensions/package-network environment; the user's no-Codespaces instruction remains in force. Preserve all future T006/T021–T026 test and review gates, and do not merge or deploy from this source-only state.

## Latest validated bootstrap evidence (supersedes earlier partial-status notes) — 2026-11-23

Real GitHub Actions runner startup was restored after the owner reported a private-repository Actions limit. Framework source fixes were verified through CI (restore missing `tests/Unit` directory, disable default `storage/{path}` HTTP routes, provide an **empty** ignored test-runner-local `.env` to eliminate warnings). Real project lockfile committed (`5d334ecd`) and pinned dependency install plus platform checks passed. [Run #45239265994](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45239265994) succeeded using PHP 8.4.28, Composer 2.11.2, Laravel 13.43.0; `route:list --json` was `[]`; Pest foundation **1 passed (9 assertions, zero warnings)**; `composer audit --locked` reported no advisories. This satisfies T005/T009 but **does not satisfy** T010–T026 Module Registry, test-only FoundationDemo, negative/route-cache/architecture, final review/merge gates. [Full snapshot](../handoff/F0-01_BOOTSTRAP_VALIDATED_2026-11-23.md).

**Sources:** [Backend Architecture](../01-architecture/backend-architecture.md), [Module Architecture](../01-architecture/module-architecture.md), [Code Standards](../02-standards/code-standards.md), [Testing Standards](../02-standards/testing-standards.md), [FBR-02](FBR-02-architecture-gap-review.md), [Laravel 13 release notes](https://laravel.com/docs/13.x/releases), [Laravel 13 providers](https://laravel.com/docs/13.x/providers).
