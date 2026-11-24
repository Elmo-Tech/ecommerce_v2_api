# Development workflow

- `main` — stable baseline and release promotion target.
- `development` — ongoing integration and feature merge target.
- `feature/<short-name>` / `fix/<short-name>` — branch from `development`; PR to `development`.
- Release/promotion PR: `development` → `main` after review and passing checks.

This was the **proposed kickoff repository convention**. **Current state 2026-11-24:** FND-01 and F0-01 have been merged into `development` with verified Laravel 13 CI. This is not evidence of production deployment, `main` promotion or configured branch protection.

## FND-01 — Spec Kit canonical documentation layout (APPROVED, Option A)

**Owner approval:** 2026-11-23. **Status:** documentation structure approved; official Spec Kit CLI **v1.4.3 initialized and verified in Codespaces**, with default agent-neutral integration **`none`** committed in `c58a78f`. Project Constitution v1.0.0 was **ratified under FND-02 Option A**; Laravel/F0-01 CI was subsequently verified and integrated into `development` on 2026-11-24.

- `docs/03-modules/<module>/module-overview.md` is the source for module purpose, boundaries, owning data, public contracts, dependencies, feature inventory and module-level rules.
- `specs/<feature-id>-<feature-slug>/spec.md`, `plan.md` and `tasks.md` are the **one canonical source** for each Feature's requirements, technical design and executable work list.
- The module overview **links to** canonical Feature specs; it does not hold a second editable copy.
- Use the official `github/spec-kit` CLI with the agent-neutral `--integration none` to initialize `.specify/` in the existing repository on a dedicated feature branch. ChatGPT manages project files and PRs via GitHub; CLI initialization was completed in Codespaces. GitHub Actions is a planned CI environment, not yet validated for this repository. Adding Codex/Copilot later is optional and requires separate authorization. Review generated changes before opening/merging a PR to `development`.
- Before implementation, comply with the **ratified Project Constitution v1.0.0** and approved D/OV/BA/MA/API/AUTHZ/DB/SEC/FBR-02 source decisions, with no invented product rules. Prepare/review the equivalent Specify, Clarify, Plan, Tasks and consistency artifacts through GitHub; the agent-neutral `none` integration does not provide agent slash commands. Approve Feature specifications/plans before code.
- Feature roadmap: **`F0-01` Laravel bootstrap and explicit Module Registry COMPLETED and merged 2026-11-24**. `F0-02` API Foundation, `F0-03` Testing Foundation, `F0-04` Code Quality/CI and `F0-05` developer workflow/traceability are **planned separately**, not yet implemented by F0-01.
- **F0-01 real locked Composer/Laravel/Pest/CI and merged PR evidence is verified**; later modules, tenancy and production evidence remain unverified. `D-201` OPEN, `BA-16` DEFERRED and `BA-30 G3` BLOCKED are unaffected.

**Historical setup branch:** `feature/f0-spec-kit-setup` (now [PR #1 merged](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1)). Laravel 13 was separately approved and merged via [F0-01 PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2); no infrastructure deployment or production activation was authorized.

## FND-02 — Project Constitution (APPROVED, Option A)

**Owner approval:** 2026-11-23. The owner selected **A**, ratifying the eight-principle Review Draft 0.2.0 as the canonical [Project Constitution v1.0.0](../.specify/memory/constitution.md). [FND-02 approval and review record](reviews/FND-02-constitution-review.md) preserves scope, source review and open gates; [historical draft](foundation/constitution-proposal.md) is retained for traceability.

Constitution approval makes the governance principles binding for subsequent Feature planning and PR review. It **did not itself** approve F0-01 requirements, code, deployment or PR merge; those later received separate approvals and verified development-only merges. It still does not authorize release or production deployment. D-201 OPEN, BA-16 DEFERRED and BA-30 G3 BLOCKED remain unchanged.
