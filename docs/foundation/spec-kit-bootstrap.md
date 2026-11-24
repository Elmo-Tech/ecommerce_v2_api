# Spec Kit bootstrap — F0 initialization record

> **Status:** OFFICIAL CLI INITIALIZED AND PUSHED — 2026-11-23; **Constitution v1.0.0 RATIFIED (FND-02 A)**, F0-01 spec NOT APPROVED.  
> **Owner-approved layout:** FND-01 Option A, in [WORKFLOW](../WORKFLOW.md).  
> **Scope:** Spec Kit tooling and source-controlled assets only. No Laravel code, app deployment, CI passing evidence or release gate approval is implied.

## Goal and authority

Adopt official `github/spec-kit` in the existing `Elmo-Tech/ecommerce_v2_api` repository with **one** Module Overview per module at `docs/03-modules/<module>/module-overview.md` and independent Feature specifications at `specs/<number>-<feature-name>/`. The approved project business/architecture/standards decisions and [FBR-02](../reviews/FBR-02-architecture-gap-review.md) remain authoritative.

Our execution workflow is **ChatGPT ↔ GitHub** for documentation, code, branches and PRs. **Spec Kit integration `none`** installs shared scripts/templates without Codex or Copilot Skills or executable agent slash commands. A supported terminal environment is needed for framework/test runs; it is **not** required for ordinary document/PR changes through GitHub.

## Verified 2026-11-23 initialization

**Environment:** GitHub Codespaces, workspace `/workspaces/ecommerce_v2_api`, branch `feature/f0-spec-kit-setup`, Linux x86_64; CLI reported Python `3.14.2`.

1. The owner installed and ran official Specify CLI **v1.4.3** in Codespaces. The shared `.specify/` source assets were originally mirrored from official [v1.4.3](https://github.com/github/spec-kit/tree/v1.4.3) and reconciled by official CLI initialization.
2. Selected initialization settings, confirmed by committed [`.specify/init-options.json`](../../.specify/init-options.json): `here=true`, `integration=none`, `script=sh`, `feature_numbering=sequential`, `speckit_version=1.4.3`.
3. The owner's `specify integration status` output was **OK**: default `none`, installed `none`, multi-install safe `yes`, `0` modified/missing managed files, `0` invalid manifest paths.
4. Owner ran the reviewed `git add` / `git diff --cached --check` / `git commit` / `git push` sequence. Commit [`c58a78f`](https://github.com/Elmo-Tech/ecommerce_v2_api/commit/c58a78ff0f4adec344f1486607a37ee124c8163b) was independently verified via GitHub.
5. Committed, versioned project state includes `.specify/init-options.json`, `.specify/integration.json`, `.specify/integrations/none.manifest.json`, `.specify/integrations/speckit.manifest.json`, `.specify/memory/constitution.md`, `.specify/workflows/workflow-registry.json`, scripts and templates. The two manifests record the same v1.4.3 setup.

Following the separate **FND-02 Option A** owner approval on 2026-11-23, the canonical [`.specify/memory/constitution.md`](../../.specify/memory/constitution.md) has been replaced with the approved eight-principle **Constitution v1.0.0**. The original CLI-generated file was a placeholder, now superseded; the historical [Review Draft 0.2.0](constitution-proposal.md) is retained for traceability.

See [official source inventory and post-init differences](spec-kit-source-inventory.md) for upstream provenance versus generated state.

## Historical GitHub Actions blocker — separate from successful Codespaces init

Earlier one-time bootstrap Action runs [45083043107](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45083043107) and [45083678045](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45083678045) ended in failure **before visible job steps**; logs were unavailable. The exact cause was **not established**. The nonfunctional bootstrap workflow was removed from this branch. **Codespaces initialization resolves the tooling bootstrap, not the CI runner problem.** Do not infer that PHPUnit/Pest, GitHub-hosted runners or deployment work from this success.

## Constitution: approved FND-02 gate

**FND-02 Option A approved 2026-11-23.** The owner ratified Draft 0.2.0 as [Project Constitution v1.0.0](../../.specify/memory/constitution.md), with provenance and review rationale in [FND-02 approval record](../reviews/FND-02-constitution-review.md). The source proposal remains a historical draft and is no longer the governing artifact. No Codex/Copilot slash-command invocation is claimed; `none` integration intentionally has no agent-specific skills. Ratification does not grant implementation, merge or release approval.

## First implementation Feature: F0-01

- [`specs/001-laravel-bootstrap-module-registry/spec.md`](../../specs/001-laravel-bootstrap-module-registry/spec.md) is still a **DRAFT — NOT APPROVED** Feature specification.
- Review and approve its scope/acceptance scenarios, then create and review `plan.md` and `tasks.md` as GitHub files using the official templates and approved SDD methodology; agent-specific shortcut commands do not run in `none` mode.
- Laravel/bootstrap code and live integration tests belong to a **separate implementation branch/PR**, and require explicit owner approval and actual runner evidence.

**Unchanged gates:** D-201 **OPEN**; BA-16 **DEFERRED**; BA-30 G3 **BLOCKED**. Neither the CLI setup nor this documentation update changes product behavior or production readiness.
