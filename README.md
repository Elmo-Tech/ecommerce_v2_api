# ecommerce_v2_api

Private **Elmo-Tech** repository for the **E-commerce SaaS V2 API**.

**Status (2026-11-24):** F0-01 Laravel 13 API Foundation and explicit Module Registry/FoundationDemo **implemented and validated in GitHub Actions**: real `composer.lock`, Laravel 13.43.0 bootstrap, **26 passing Unit/Feature/Architecture tests (116 assertions)** on [Run #45285351899](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45285351899). Pint/PHPStan are **not installed**; no merge/deployment, Auth, Tenancy or migrations. [Current verified handoff and read-first order](docs/handoff/NEXT_CHAT_START_HERE.md).

## Product direction
Multi-tenant commerce SaaS, independent stores per merchant. Egypt-first MVP pilot for small/medium omnichannel retailers: online orders, manual orders and simple direct sales, with catalog/pricing, taxes/discounts, basic per-location inventory and single-location full-order fulfillment. Not a full ERP or POS.

**Approved F0 technical direction:** Laravel 13 API-first Modular Monolith with explicit Module Registry, PostgreSQL for later database Features, separate Next.js frontend, and GitHub Spec Kit. This does not imply tenancy/identity/business module implementation.

## Essential documentation

- [Start here in the next ChatGPT conversation](docs/handoff/NEXT_CHAT_START_HERE.md)
- [Open decisions, deferred work and MVP gates](docs/decisions/OPEN_DECISIONS_AND_MVP_GATES.md)
- [Canonical decision register — D-152 through D-200, D-201 open](docs/decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md)
- [Full Business Discovery Handoff v5.48](docs/handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md)
- [Original Arabic discovery handoff (historical)](docs/handoff/ecommerce_saas_business_discovery_handoff_ar.md)
- [Branch workflow](docs/WORKFLOW.md)

**Last approved decision:** D-200 A. **Next unresolved question:** D-201, currently OPEN — not automatically approved.

## Branches

- `main`: stable branch.
- `development`: integration branch for daily development.
- `feature/*` and `fix/*`: new work from `development`, PR back into it.
- Promote reviewed, tested changes from `development` into `main`.

New chats must resume from [NEXT_CHAT_START_HERE](docs/handoff/NEXT_CHAT_START_HERE.md), then read the latest dated `docs/handoff/` snapshot, live F0-01 tasks/spec/plan and actual code. The owner approved F0-01 implementation, **not merge/deployment**. Latest CI/lock/runtime gates are green; independent PR review and upstream PR #1 integration remain separate. **No Codespaces commands** are required from the owner.
