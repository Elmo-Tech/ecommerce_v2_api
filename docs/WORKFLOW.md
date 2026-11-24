# Development workflow

- `main` — stable baseline and release promotion target.
- `development` — ongoing integration and feature merge target.
- `feature/<short-name>` / `fix/<short-name>` — branch from `development`; PR to `development`.
- Release/promotion PR: `development` → `main` after review and passing checks.

This is the **proposed repository operating convention** for kickoff, not a claim that branch protection or CI has been configured. They must be configured separately. At setup time the repo contains documentation only, no production Laravel application.

## FND-01 — Spec Kit canonical documentation layout (APPROVED, Option A)

**Owner approval:** 2026-11-23. **Status:** documentation structure approved; official Spec Kit CLI **v1.4.3 initialized and verified in Codespaces**, with default agent-neutral integration **`none`** committed in `c58a78f`. Project Constitution v1.0.0 has been **ratified under FND-02 Option A**, while Laravel/CI implementation is not verified.

- `docs/03-modules/<module>/module-overview.md` is the source for module purpose, boundaries, owning data, public contracts, dependencies, feature inventory and module-level rules.
- `specs/<feature-id>-<feature-slug>/spec.md`, `plan.md` and `tasks.md` are the **one canonical source** for each Feature's requirements, technical design and executable work list.
- The module overview **links to** canonical Feature specs; it does not hold a second editable copy.
- Use the official `github/spec-kit` CLI with the agent-neutral `--integration none` to initialize `.specify/` in the existing repository on a dedicated feature branch. ChatGPT manages project files and PRs via GitHub; CLI initialization was completed in Codespaces. GitHub Actions is a planned CI environment, not yet validated for this repository. Adding Codex/Copilot later is optional and requires separate authorization. Review generated changes before opening/merging a PR to `development`.
- Before implementation, comply with the **ratified Project Constitution v1.0.0** and approved D/OV/BA/MA/API/AUTHZ/DB/SEC/FBR-02 source decisions, with no invented product rules. Prepare/review the equivalent Specify, Clarify, Plan, Tasks and consistency artifacts through GitHub; the agent-neutral `none` integration does not provide agent slash commands. Approve Feature specifications/plans before code.
- Initial planned Features: `F0-01` Laravel bootstrap and explicit Module Registry; `F0-02` API Foundation; `F0-03` Testing Foundation; `F0-04` Code Quality/CI; `F0-05` developer workflow and traceability. These are **planning IDs**, not implemented Features.
- Real compatibility and CI/PR evidence remain unverified. `D-201` OPEN, `BA-16` DEFERRED and `BA-30 G3` BLOCKED are unaffected.

**Current setup branch:** `feature/f0-spec-kit-setup`. No application code, Laravel installation, infrastructure deployment or production activation is authorized by this documentation choice alone.

## FND-02 — Project Constitution (APPROVED, Option A)

**Owner approval:** 2026-11-23. The owner selected **A**, ratifying the eight-principle Review Draft 0.2.0 as the canonical [Project Constitution v1.0.0](../.specify/memory/constitution.md). [FND-02 approval and review record](reviews/FND-02-constitution-review.md) preserves scope, source review and open gates; [historical draft](foundation/constitution-proposal.md) is retained for traceability.

Constitution approval makes the governance principles binding for subsequent Feature planning and PR review. It **does not approve F0-01 requirements/plan, application implementation, deployment, release, or merger of PR #1**. D-201 OPEN, BA-16 DEFERRED and BA-30 G3 BLOCKED remain unchanged.
