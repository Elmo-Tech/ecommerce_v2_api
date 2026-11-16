# Development workflow

- `main` — stable baseline and release promotion target.
- `development` — ongoing integration and feature merge target.
- `feature/<short-name>` / `fix/<short-name>` — branch from `development`; PR to `development`.
- Release/promotion PR: `development` → `main` after review and passing checks.

This is the **proposed repository operating convention** for kickoff, not a claim that branch protection or CI has been configured. They must be configured separately. At setup time the repo contains documentation only, no production Laravel application.
