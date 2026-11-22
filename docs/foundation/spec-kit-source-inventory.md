# Spec Kit v1.4.3 source mirror — inventory

**Status:** original upstream source assets mirrored; **Specify CLI not run**; no Codex/Copilot integration.  
**Official source:** https://github.com/github/spec-kit/tree/v1.4.3  
**Tag's commit SHA:** `980b3d027f4bd7958c35f97f618d854b41320e3f`  
**Repository:** `Elmo-Tech/ecommerce_v2_api` / `feature/f0-spec-kit-setup`.

The 12 assets below are copied from the pinned official v1.4.3 source by content-preserving Git blobs. The imported Bash scripts are marked executable. This documents provenance without claiming the official CLI generated/installed any of them.

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

## Critical distinction

This is **not a successful `specify init`** and is not an agent integration. The shared source templates retain original command placeholders; the official tool may transform them. There is no authoritative `.specify/memory/constitution.md`, no generated shared manifest/init state, and no verified version output. The workflow file under `.specify/workflows` is bundled reference material and cannot dispatch in agent-neutral `none` mode.

GitHub Actions attempts #45083043107 and #45083678045 failed before any visible runner step and did not provide retrievable logs. The unsuccessful automatic bootstrap action was removed from this PR. It must not be reintroduced as a required passing check until runner availability is independently established.

## Next verifiable gate

On a working GitHub-hosted runner or Codespace, from this feature branch:
```bash
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git@980b3d027f4bd7958c35f97f618d854b41320e3f
specify version
specify init --here --force --integration none --non-interactive --ignore-agent-tools
git status --short
git diff --stat
```

Review generated files against this inventory and the existing docs before any commit or merge. Complete and approve the Constitution and then plan/tasks for F0-01 separately. The source mirror itself is reviewable and allows GitHub-first manual drafting of Feature specs; it does not prove runtime compatibility or production readiness.
