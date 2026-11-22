# Development workflow

- `main` — stable baseline and release promotion target.
- `development` — ongoing integration and feature merge target.
- `feature/<short-name>` / `fix/<short-name>` — branch from `development`; PR to `development`.
- Release/promotion PR: `development` → `main` after review and passing checks.

This is the **proposed repository operating convention** for kickoff, not a claim that branch protection or CI has been configured. They must be configured separately. At setup time the repo contains documentation only, no production Laravel application.

## FND-01 — Spec Kit canonical documentation layout (APPROVED, Option A)

**Owner approval:** 2026-11-23. **Status:** documentation structure approved; official Spec Kit CLI initialization and agent integration are **not yet performed or verified**.

- `docs/03-modules/<module>/module-overview.md` is the source for module purpose, boundaries, owning data, public contracts, dependencies, feature inventory and module-level rules.
- `specs/<feature-id>-<feature-slug>/spec.md`, `plan.md` and `tasks.md` are the **one canonical source** for each Feature's requirements, technical design and executable work list.
- The module overview **links to** canonical Feature specs; it does not hold a second editable copy.
- Use the official `github/spec-kit` CLI with the agent-neutral `--integration none` to initialize `.specify/` in the existing repository on a dedicated feature branch. ChatGPT manages project files and PRs via GitHub; GitHub Actions is the execution environment for CLI setup and CI tests. Adding Codex/Copilot later is optional and requires separate authorization. Review generated changes before opening/merging a PR to `development`.
- Before implementation, the project's Spec Kit constitution must be derived from already approved D/OV/BA/MA/API/AUTHZ/DB/SEC/FBR-02 source decisions, with no invented product rules. Run Specify, Clarify, Plan, Tasks and appropriate consistency checks; approve specifications/plan before code.
- Initial planned Features: `F0-01` Laravel bootstrap and explicit Module Registry; `F0-02` API Foundation; `F0-03` Testing Foundation; `F0-04` Code Quality/CI; `F0-05` developer workflow and traceability. These are **planning IDs**, not implemented Features.
- Real compatibility and CI/PR evidence remain unverified. `D-201` OPEN, `BA-16` DEFERRED and `BA-30 G3` BLOCKED are unaffected.

**Current setup branch:** `feature/f0-spec-kit-setup`. No application code, Laravel installation, infrastructure deployment or production activation is authorized by this documentation choice alone.
