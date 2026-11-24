# F0-03 — source discovery and unresolved research

**Date:** 2026-11-25 | **Source revision:** `97c5895bd5672261247b45f1b7eca5648afaf163` | **Status:** repository discovery only; version research and decisions pending.

## Verified repository baseline
- PR #4 merged `2220a2afc0d97aeb1cf705e21da86af910ee6dda`; PR #5 pointer merged `f0febe3acdd20f84230476629aa9cb759df5ca7e`; PR #6 docs closeout merged `97c5895bd5672261247b45f1b7eca5648afaf163`. F0-02 tasks ledger contains 30 checked tasks and publication condition satisfied.
- [Actual CI #45497208466](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45497208466), job 138810749871: 98/911, standalone Architecture 5/52, original 31/134; actual integrated tree matches merged PR #4. No new execution in discovery.
- composer.json/lock: Laravel 13.43.0, PHP ^8.4, Pest 5.3.7, Pest Laravel plugin 5.0.1, PHPUnit 13.4.7. No Stancl/Sanctum/Horizon/Predis package in lock.
- phpunit.xml: Unit, Feature, Architecture. Pest binds Feature only. TestCase has no DB trait. Existing F0-01 BootstrapSmokeTest verifies no DB connections opened, array/sync. Existing F0-02 architecture checks preserve current packages, empty route seam, no business migrations. Existing fixtures under tests/Fixtures; no Integration/Contracts suite or reusable test support root yet.
- config/database.php contains native pgsql and Redis configurations but not actual provisioned/verified DB or Redis runtime. Redis client defaults phpredis. No production DB schema/migrations introduced. Existing CI sets sqlite :memory:, array cache/session, sync queue; isolated runner creates empty ignored .env; no real PostgreSQL/Redis service test, no JUnit artifact/report yet (existing lock artifact retention 7 days).

## Governing source evidence
- TEST-01/02: risk-based test layers, Pest default with existing PHPUnit permitted, central tests/type/Module/Feature organization, independent deterministic tests.
- TEST-03 §§5.1–5.7 and §16: real Central + two distinct Tenant PostgreSQL databases, restricted DB roles/setup privilege separation, independently positive environment/endpoint/name/role/namespace/run ownership, no blind RefreshDatabase or global flush; sequential before parallel; real service evidence when applicable. Recommended Foundation order includes safe three-DB/Redis harness. This supports proposed DEC-01 A; does not prove implementation or select concrete service versions.
- DB-01/09/10: real role denial, separate migration tracks, restricted runtime roles; no business migration designs invented in this Feature.
- TEST-09 §11.4: precise actual revisions, suite/environment/outcome/duration/safe artifacts; JUnit preferred where supported. Retention/runner policy to specify; 7 days is a proposed convention, not a preapproved report policy.
- Constitution VII/VIII: applicable Critical/High checks pass with actual evidence; no mandatory heavy suite for trivial docs; preserve reviewed Feature gates. Workflow 2026-11-25: ChatGPT review -> owner human review -> explicit merge; third-party AI review optional.
- D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## Research to complete after scope decision, before final plan
1. Verify supported PostgreSQL and Redis release lines, official container images/digests and PHP 8.4 pdo_pgsql/phpredis compatibility, record actual sources and propose exact versions. Current reviewed source documents do not mandate a concrete version. Do not claim install/runtime compatibility from research alone.
2. Verify Pest/PHPUnit configuration/selection and safe pre-boot guard ordering; no fallback or automatic skip; actual negative/positive tests required after implementation authorization.
3. Finalize owned disposable job resource topology, role and ACL grant/denial acceptance matrix, run identity and secret/report handling. Verify runner availability and fail-closed behavior on real CI.
4. Confirm proposed JUnit retention and reporting approach and separation from F0-04. No artifact/credentials created by this draft.
5. Once owner approves final choices and revised spec/plan, generate tasks/consistency and obtain separate implementation authorization. No runtime changes now.
