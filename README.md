# ecommerce_v2_api

Private **Elmo-Tech** repository for the **E-commerce SaaS V2 API**.

**Status:** project kickoff and architecture planning; documentation only. No Laravel application code yet.

## Product direction
Multi-tenant commerce SaaS, independent stores per merchant. Egypt-first MVP pilot for small/medium omnichannel retailers: online orders, manual orders and simple direct sales, with catalog/pricing, taxes/discounts, basic per-location inventory and single-location full-order fulfillment. Not a full ERP or POS.

**Tech direction (architecture NOT finalized):** Laravel API, PostgreSQL, Next.js and GitHub Spec Kit.

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

The next chat should start with MVP Scope v1 and blocking architecture decisions. Approving implementation requires a separate user decision.
