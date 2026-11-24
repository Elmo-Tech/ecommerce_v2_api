# F0-02 — T003 implementation authorization and T004 preflight start

**Date:** 2026-11-24 (Africa/Cairo)
**PR:** [#4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4), Draft. Feature baseline `e6ea55d3cfa86e2c7423184b114d42d73b26710f`; development `43f309749c544040385977a530e78fd378f43b91`.

The owner responded **“تمام يلا”** to the explicit T003 Implementation Authorization request (implementation tests first, no Codespaces, no merge without later approval). This authorizes T004–T027 scoped engineering work, not T028 merge authority or deployment. T001–T003 complete (3/30), T004 pending real runner result.

Preflight read-only compare: development-only change is NEXT_CHAT_START_HERE documentation; composer manifest/lock, bootstrap, workflow, module registry, PHPUnit, Pest binding and route-isolation test blobs identical. Locked Laravel v13.43.0, Pest v5.3.7, Laravel Pest plugin v5.0.1, PHPUnit 13.4.7, Symfony HTTP v8.1.11, PHP ^8.4. No runtime divergence. No dependency changes.

**Execution sequence adjustment within approved scope:** available GitHub connector has no workflow-dispatch action; existing PR path filters skip documentation-only changes. Apply the approved T022 path-filter/label-only adjustment ahead of T004 to trigger real baseline CI, without introducing application or test source changes. T022 remains unchecked pending final validation. Use PR-triggered merge candidate, isolated existing environment, same pinned Composer checks and complete suite. No new CI framework or external infrastructure.

Next: verify actual baseline workflow result and logs before adding F0-02 tests; then T005–T014 tests-first RED. Do not treat earlier F0-01 CI as a new run. No code, tests or executable OpenAPI contract yet; no merge, main, production, migrations, Auth/Tenant/Redis, packages or deployment. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.
