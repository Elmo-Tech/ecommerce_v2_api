# Spec Kit bootstrap — F0 setup plan

> **Status:** DRAFT / CLI NOT EXECUTED. Prepared in the GitHub-first setup branch on 2026-11-23.  
> **Owner-approved layout:** FND-01 A, in [WORKFLOW](../WORKFLOW.md).  
> **Scope:** Documentation and tool initialization only. No Laravel application scaffold, deploy, or CI evidence is implied.

## Goal and source of truth

Adopt the **official GitHub Spec Kit** within the existing `Elmo-Tech/ecommerce_v2_api` repository, without creating a replacement repository or losing existing docs. Keep one authoritative Module Overview per implemented module under `docs/03-modules/` and independent Feature specifications under `specs/<number>-<feature-name>/`. The existing architecture, standards, handoffs and the approved [FBR-02](../reviews/FBR-02-architecture-gap-review.md) register remain authoritative.

Official sources checked before drafting:
- https://github.github.io/spec-kit/how-to/existing-projects.html
- https://github.github.io/spec-kit/installation.html
- https://github.github.io/spec-kit/integrations/copilot.html
- https://github.github.io/spec-kit/reference/agentic-sdd.html

## One-time setup, to run in GitHub Codespaces

Use `feature/f0-spec-kit-setup`, created from `development`; **do not** initialize in `main`.

Prerequisites: internet-connected Codespaces terminal, Python 3.11+, `uv`, Git, and an appropriate coding-agent integration. GitHub Copilot is the **provisional integration** used in this command; selecting a different supported agent needs its correct integration key. Verify the working tree and review generated files; `--force` can overwrite conflicting managed paths.

```bash
git status --short
uv tool install specify-cli
specify version
specify init --here --force --integration copilot --non-interactive --ignore-agent-tools
git status --short
git diff --stat
```

**Important:** The commands above are instructions for a future Codespaces run; they have **not** been run or verified by ChatGPT. A local execution environment available to this conversation cannot resolve external package hosts, so it cannot install Specify CLI. Do not claim the official `.specify/` and `.github/skills/speckit-*` integration is installed until Codespaces output and Git diff prove it. Do not overwrite the proposed constitution/spec without comparing with generated artifacts.

After init, verify the generated shared `.specify/` assets and the Copilot `.github/skills/speckit-*` skill files, and review for accidental changes to existing documents. Keep the CLI-generated assets source-controlled. Review and commit only the intended changes in this feature branch; open a PR to `development`. Never run Laravel migrations or deploy merely to initialize Spec Kit.

## Constitution approval before implementation

Review [proposed project constitution](constitution-proposal.md) with the owner, then use the coding agent's `/speckit-constitution` process (inside the agent chat, **not** terminal) to create/update canonical `.specify/memory/constitution.md`. Do **not** claim this proposal has been ratified. Approval of FND-01 concerns folder organization, not every future implementation detail.

Constitution must preserve the hierarchy and existing decisions, not invent new defaults. The official setup is considered **ready only after** CLI initialization and integration are verified, the Constitution is approved, and the resulting diff/PR has been reviewed.

## First vertical slice

- **F0-01** Laravel Bootstrap + Explicit Module Registry. A [draft specification](../../specs/001-laravel-bootstrap-module-registry/spec.md) is available for owner review, **not yet** a generated or approved Spec Kit plan.
- Next, after the selected agent is installed and this bootstrap PR is reviewed: run `/speckit-specify` or reconcile its output with the approved draft, then `/speckit-clarify`, `/speckit-plan`, `/speckit-checklist`, `/speckit-tasks` and `/speckit-analyze`.
- Only after the owner approves `spec.md` and `plan.md`: a separate, reviewable implementation branch with code and actual tests; then `/speckit-implement` and `/speckit-converge`.

## Explicitly not changed

No new product requirements, prices, SaaS trial rules, legal retention or backup design. **D-201 stays OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.** No application runtime or development infrastructure is claimed active.
