# F0-02 API Foundation — discovery/specification draft opened

**Snapshot date:** 2026-11-24, Africa/Cairo  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration branch:** `development`  
**Working branch:** `feature/f0-02-api-foundation`  
**[Draft PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4):** **OPEN, DRAFT, documentation-only, NOT approved, NOT merged or implemented**  
**Owner request:** After fixing three final CodeRabbit issues on PR #1 and closing F0-01, enter F0-02. The owner has not separately approved F0-02 exact scope, material choices or production code.

## What was actually completed this turn

1. Fixed CodeRabbit's three remaining **Minor correctness** findings in `.specify/scripts/bash/create-new-feature.sh` and `.specify/scripts/bash/markdown_sections.py`, with six new regression tests in `tests/spec_kit/test_review_regressions.py`. [FND-01 Actions #45404474720](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45404474720) **SUCCESS (28 Python regressions + Bash syntax/clean diff)**; CodeRabbit closed **13/13 PR #1 discussions**.
2. [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1) **MERGED** to `development` as `199ea55ca6acc7137623baee5066c88635ea4a81`.
3. [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) **MERGED** to `development` as `0abf6772a2892410e62335ab0f82edd40c2514df` after genuine integrated [CI #45405086930](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405086930) and recheck [#45405284525](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45405284525) **SUCCESS**: 31 Pest tests / 134 assertions, standalone Architecture 3 / 23, locked dependencies and PHP syntax.
4. Updated on `development`: [F0-01 `tasks.md`](../../specs/001-laravel-bootstrap-module-registry/tasks.md) **26/26 COMPLETE**, [dated merged F0-01 handoff](F0-01_MERGED_AND_CLOSED_2026-11-24.md), [canonical `NEXT_CHAT_START_HERE.md`](NEXT_CHAT_START_HERE.md), root README, workflow, Foundation overview and closed spec/plan statuses. **No `main` merge, deployment, migrations, Auth, Tenant DB or business API.**
5. Disabled the old recurring `F0-01 Review & Conditional Merge` automation `6b04d2ee76dc81918794c538f0c0aec3` because both merges are already verified.
6. Created **new branch** `feature/f0-02-api-foundation` from the reconciled `development` head, and drafted **`specs/002-api-foundation/spec.md`**, **`plan.md`**, **`research.md`**, and **`checklists/requirements.md`** based on approved Constitution/AS/BA/MA/CS/TEST source documents. Opened **Draft PR #4** for review, not merge.

## Exact F0-02 status and content

**STATUS: SPEC/PLAN DISCOVERY ONLY — NOT APPROVED, NO EXECUTION.** Scope proposed:

- Minimal native Laravel `/api/v1` HTTP boundary for later module-owned APIs, not production business routes.
- Reusable `App\Helpers\ApiResponse`, int-backed `App\Enums\HttpStatusCode`, stable errorCode/real HTTP statuses, 204 empty response, optional truthful pagination meta.
- Safe central exception-to-API mapping and `X-Request-ID` header per attempt, ar/en generic error messages.
- Tiny shared OpenAPI contract/schema and planned real HTTP/unit/architecture negative tests.
- **FBR-02 overrides old illustrative API examples**: finalized Money minor units and DB BIGINT identifiers serialize as decimal **strings** where applicable.
- **NO Auth/Sanctum, CSRF integration, Tenant provisioning/connection, databases/migrations, Redis/Horizon, query-builder/search, durable idempotency, business API/production probe, new Composer packages, domain logic or deployment.**

The four explicit **review decisions** in spec/plan:
- **F0-02-DEC-01:** ar vs en fallback locale when `Accept-Language` is missing/unsupported.
- **F0-02-DEC-02:** stable shared string error codes with CS-05 int-backed PHP Enum constraint (proposed lightweight constants, not string-backed Enum).
- **F0-02-DEC-03:** canonical OpenAPI schema path/format and validation (proposed native JSON, no new package).
- **F0-02-DEC-04:** when to add empty native `routes/api.php` route group without any public business/debug route.

**The owner must review/approve F0-02 spec and plan before implementation, code tests or feature merge.** Do not confuse the owner's permission to *move to the next phase* with authorization to settle open decisions or modify API runtime behavior.

## Exact next-chat reading order / actions

1. [Canonical repository entrypoint](NEXT_CHAT_START_HERE.md) on `development` and [F0-01 finalized merge evidence](F0-01_MERGED_AND_CLOSED_2026-11-24.md).
2. **[F0-02 Draft PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4)** and files **`specs/002-api-foundation/spec.md`**, **`plan.md`**, **`research.md`**, **`checklists/requirements.md`** on `feature/f0-02-api-foundation`; this dated F0-02 handoff.
3. [Approved API Standards](../02-standards/api-standards.md) **AS-01/02/03/08/09**, FBR-02-05/06/07 (Money/IDs canonical string wire types), [Constitution v1.0.0](../../.specify/memory/constitution.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Module Architecture](../01-architecture/module-architecture.md), [Code Standards](../02-standards/code-standards.md), [Testing Standards](../02-standards/testing-standards.md), [open decisions](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).
4. Actual Laravel baseline **`bootstrap/app.php`**, `config/modules.php`, `app/Providers/ModuleRegistryServiceProvider.php`, `app/Modules/FoundationDemo/`, `composer.json`, `composer.lock`, F0-01 tests and CI. **No executable F0-02 code exists**.
5. **Next real action:** discuss/specify F0-02 decisions 01–04 and obtain explicit approval for the exact proposed scope/plan; then generate `tasks.md` with tests and dependencies, implement on feature branch after authorization, run real CI, review and only then merge. **No Codespaces commands** from owner.
6. Business decision **D-201 OPEN**, **BA-16 DEFERRED**, **BA-30 G3 BLOCKED** unchanged; do not introduce them as F0-02 implementation requirements.

## Accuracy and review caveats

The technical dependencies PHP ^8.4/Laravel 13/Pest 5 are **F0-01 verified**, not a claim that F0-02 was run. `docs/02-standards/api-standards.md` still contains historical documentation-only implementation status and older illustrative numeric Money/ID examples; the approved later FBR-02 string-wire decisions take precedence. No extra CI, Pint, PHPStan or OpenAPI generator tooling was installed. Preserve historical source and milestones; always update this dated handoff or create a newer one and refresh the canonical entrypoint after further work.
