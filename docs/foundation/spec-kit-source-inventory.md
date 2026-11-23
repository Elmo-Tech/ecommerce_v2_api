# Spec Kit v1.4.3 — upstream source inventory and post-initialization trace

**Status:** original upstream source assets mirrored, then **official Specify CLI v1.4.3 successfully initialized in Codespaces and pushed** in commit `c58a78ff0f4adec344f1486607a37ee124c8163b`; integration remains `none`, with no Codex/Copilot agent integration.  
**Official source:** https://github.com/github/spec-kit/tree/v1.4.3  
**Tag's commit SHA:** `980b3d027f4bd7958c35f97f618d854b41320e3f`  
**Repository:** `Elmo-Tech/ecommerce_v2_api` / `feature/f0-spec-kit-setup`.

The 12 assets below were initially mirrored from pinned official v1.4.3 source by content-preserving Git blobs. The original imported Bash scripts were marked executable. **The SHA column is a pre-initialization upstream provenance snapshot**. Subsequent official CLI initialization legitimately updated a subset of these files; it is not a current-content checksum table.

| Local path | Original upstream Git SHA |
| --- | --- |
| `.specify/scripts/bash/check-prerequisites.sh` | `1eadd991b28980b5105dea14dc962a698e2ec45c` |
| `.specify/scripts/bash/common.sh` | `55bcffa25c28f58cb20a4d4bafb9959cfc849563` |
| `.specify/scripts/bash/create-new-feature.sh` | `6c2f04b8bdff976130d2f9956a0395dd2a3424cb` |
| `.specify/scripts/bash/resolve-template.sh` | `da05d2df6d014fcc4b7cada4ea0a0a05752f075e` |
| `.specify/scripts/bash/setup-plan.sh` | `9c8d163409f8efcc75414b7b11fccb22cd3005ce` |
| `.specify/scripts/bash/setup-tasks.sh` | `a5a685cd0e3a8ba74bf36e5c104858e29c16e650` |
| `.specify/templates/checklist-template.md` | `9d1e801c3e168869a04ab60c66ca0dae4a77614f` |
| `.specify/templates/constitution-template.md` | `a4670ff46919b276a4c9663b4ca51830108fcfc0` |
| `.specify/templates/plan-template.md` | `36f2eab16880bac670fe43cbe7ef2b9bc8c3aa2f` |
| `.specify/templates/spec-template.md` | `ceb28776215a098e977650ac090c785dcbf53651` |
| `.specify/templates/tasks-template.md` | `7fff087cc5a3c51a889d865fd9126607a032d233` |
| `.specify/workflows/speckit/workflow.yml` | `5b91f379b85b9190ea1e7af9657602709ea70a68` |

## Verified post-initialization state

- The owner ran `specify version` and `specify integration status` in Codespaces. Version: **1.4.3**. Integration status: **OK**, default `none`, no modified/missing managed files.
- GitHub commit [`c58a78f`](https://github.com/Elmo-Tech/ecommerce_v2_api/commit/c58a78ff0f4adec344f1486607a37ee124c8163b) includes official initialization files, and is the checked-in PR #1 head history.
- Generated state now exists: `.specify/.gitignore`, `.specify/init-options.json`, `.specify/integration.json`, `.specify/integrations/none.manifest.json`, `.specify/integrations/speckit.manifest.json`, `.specify/memory/.constitution-template.json`, `.specify/memory/constitution.md`, `.specify/workflows/workflow-registry.json`, and `.specify/scripts/bash/markdown_sections.py`.
- The CLI refreshed existing `check-prerequisites.sh`, `setup-tasks.sh`, `checklist-template.md`, `plan-template.md` and `tasks-template.md`; those **must not** be compared byte-for-byte with their original mirror SHA above.
- The bundled `.specify/workflows/speckit/workflow.yml` exists, but cannot dispatch agent-driven steps with the `none` integration.
- The current canonical `.specify/memory/constitution.md` is a **placeholder**, not the owner's ratified Constitution.

## Separate outstanding gates

The unsuccessful one-time GitHub Actions runs #45083043107 and #45083678045 failed before visible runner steps and without retrievable logs. The workflow was removed. Successful **Codespaces CLI** setup does not fix or verify GitHub Actions, Laravel/PHP compatibility or CI execution.

Next, review `docs/foundation/constitution-proposal.md` with the owner, explicitly ratify its intended content, then replace the generated Constitution template. F0-01 `spec.md`, `plan.md`, `tasks.md` and implementation/release gates are separate. No automatic PR merge.
