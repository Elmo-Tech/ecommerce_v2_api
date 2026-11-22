# Spec Kit bootstrap — F0 setup plan

> **Status:** DRAFT / CLI NOT EXECUTED. Prepared in the GitHub-first setup branch on 2026-11-23.  
> **Owner-approved layout:** FND-01 A, in [WORKFLOW](../WORKFLOW.md).  
> **Scope:** Documentation and tool initialization only. No Laravel application scaffold, deploy, or CI evidence is implied.

## Goal and source of truth

Adopt the **official GitHub Spec Kit** within the existing `Elmo-Tech/ecommerce_v2_api` repository, without creating a replacement repository or losing existing docs. Keep one authoritative Module Overview per implemented module under `docs/03-modules/` and independent Feature specifications under `specs/<number>-<feature-name>/`. The existing architecture, standards, handoffs and the approved [FBR-02](../reviews/FBR-02-architecture-gap-review.md) register remain authoritative.

Official sources checked before drafting:
- https://github.github.io/spec-kit/how-to/existing-projects.html
- https://github.github.io/spec-kit/installation.html
- https://github.github.io/spec-kit/integrations/none.html
- https://github.github.io/spec-kit/reference/agentic-sdd.html

## One-time setup: pinned source mirror and blocked CLI execution

Use `feature/f0-spec-kit-setup`, created from `development`; **do not** initialize in `main`.

The execution model is **ChatGPT + connected GitHub repository** for writing/reviewing files, branch and PR management. **GitHub Actions is a planned test execution environment, but its current runner startup is blocked for this repository workflow.** Neither Codex nor Copilot is selected as the coding agent. Use official Spec Kit `--integration none` for its agent-neutral shared scaffolding, requiring only Python 3.11+, `uv`, Git and network-capable GitHub runner. Codespaces is an optional manual fallback if the Action cannot start. Verify the working tree and review generated files: `--force` may overwrite conflicting managed paths.

```bash
git status --short
uv tool install specify-cli
specify version
specify init --here --force --integration none --non-interactive --ignore-agent-tools
git status --short
git diff --stat
```

**Important:** GitHub Actions runs [#45083043107](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45083043107) and [#45083678045](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45083678045) both failed **before any reported Step**, with no retrievable job logs. The exact account/runner cause is unverified. We removed the nonfunctional automatic workflow from this branch and did not claim that CLI initialization succeeded. Instead, the repository stores an **SHA-verified mirror of 12 original Spec Kit v1.4.3 source assets** in `.specify/` (templates, scripts and the bundled workflow); see [inventory](spec-kit-source-inventory.md). This is **not** a completed `specify init`: no CLI-installed state, integration manifest or canonical constitution has been generated. The agent-neutral `none` integration **does not generate** Codex/Copilot Skills or dispatch `/speckit-*` / `$speckit-*` agent commands. Draft and review the equivalent `spec.md`, `plan.md` and `tasks.md` directly through GitHub using the official templates and approved SDD process, while using Actions for actual CLI setup, tests and validation. Never claim an agent-specific Spec Kit command ran when none was installed. Do not overwrite the proposed constitution/spec without comparing with generated artifacts.

When a real runner or Codespace becomes available, run the official pinned CLI and verify all generated `.specify/` assets and manifest state (no agent-specific Skills), comparing against the mirrored source. Review for accidental changes to existing documents. Keep the CLI-generated assets source-controlled. Review and commit only intended changes on this branch, in a Draft PR to `development`. Never run Laravel migrations or deploy merely to initialize Spec Kit.

## Constitution approval before implementation

Review [proposed project constitution](constitution-proposal.md) with the owner, then use the already-approved project rules to create/update canonical `.specify/memory/constitution.md` through the GitHub repository workflow. Agent-specific `/speckit-constitution` or `$speckit-constitution` commands are **not available** in `none` mode. Do **not** claim this proposal has been ratified. Approval of FND-01 concerns folder organization, not every future implementation detail.

Constitution must preserve the hierarchy and existing decisions, not invent new defaults. The official setup is considered **ready only after** CLI initialization and integration are verified, the Constitution is approved, and the resulting diff/PR has been reviewed.

## First vertical slice

- **F0-01** Laravel Bootstrap + Explicit Module Registry. A [draft specification](../../specs/001-laravel-bootstrap-module-registry/spec.md) is available for owner review, **not yet** a generated or approved Spec Kit plan.
- Next, after the agent-neutral official setup and review: collaboratively review and refine the draft `spec.md`, resolve clarifications, produce and review `plan.md` / optional checklist / `tasks.md` / consistency analysis as normal repository files. The **methodology** is Spec Kit SDD; no agent slash-command execution is claimed.
- Only after the owner approves `spec.md` and `plan.md`: a separate implementation branch with code authored through GitHub, CI test execution via GitHub Actions, and review/convergence against the approved spec. This is not a Codex/Copilot execution.

## Explicitly not changed

No new product requirements, prices, SaaS trial rules, legal retention or backup design. **D-201 stays OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.** No application runtime or development infrastructure is claimed active.
