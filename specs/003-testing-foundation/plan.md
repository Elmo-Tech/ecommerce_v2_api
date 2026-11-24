# Implementation Plan (provisional): F0-03 — Testing Foundation

**Branch:** `feature/f0-03-testing-foundation` | **Date:** 2026-11-25 | **Spec:** [spec.md](spec.md)  
**Status:** DRAFT; all proposed choices in spec §7 await owner approval. This is not an implementation authorization or assertion of verified integration.  
**Baseline:** `97c5895bd5672261247b45f1b7eca5648afaf163` on development. PR #4, #5, #6 merged; F0-02 T001–T030 verified complete.

## 1. Summary

Keep the existing native testing foundation and introduce a safe explicit test execution boundary. Recommended DEC-01 A builds only real disposable technical service isolation (Central/A/B PostgreSQL and scoped Redis) alongside fast tests; no production DB, Tenancy/Identity or business feature. TEST-03's full applicable domain/worker proof remains for those owning Features.

## 2. Technical context

- Current locked PHP requirement ^8.4; CI PHP 8.4.28, Laravel 13.43.0, Pest 5.3.7, pest-plugin-laravel 5.0.1, PHPUnit 13.4.7. Preserve composer.json/lock; no new Composer packages proposed.
- Current phpunit.xml registers Unit, Feature, Architecture; tests/Pest.php binds Laravel TestCase only to Feature. Current CI uses sqlite :memory: configuration (no real DB connection proven by original smoke), array cache/session/mail, sync queue, no service containers, native test fixtures.
- Proposed real-service lane uses native pdo_pgsql and phpredis (the existing Laravel Redis config default); verify PHP extension availability/compatibility and exact official supported image versions/digests in revised plan and actual preflight. No production version inferred.
- GitHub-hosted Ubuntu 24.04 runner, isolated job services/containers, read-only repository permissions, no external deployment credentials. Proposed sequential execution first. No invented test duration/worker count/SLO.
- Final baseline evidence: [CI #45497208466](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45497208466), actual integrated 98/911, Architecture 5/52, original foundation 31/134. Merged runtime tree verified; later PR #6 changed documentation only.

## 3. Constitution check

| Principle | Proposed compliance |
| --- | --- |
| I / approved sources | TEST-01–10, DB-01/09/10 and F0-01/02 evidence govern; no new D-series rule. |
| II / modular ownership | tests-only engineering infrastructure, no business owner/table or new production providers. |
| III / tenant and storage isolation | real separate technical databases and restricted roles; actual Stancl/authorization not claimed. No shared services. |
| IV / consistency | committed probe separation only; actual business transactions/concurrency/outbox later with affected Features. No mock-only production proof. |
| V / reviewed API contracts | preserve F0-02 API/error contracts, route/cache isolation, no business endpoint. |
| VI / security/diagnostics | fail closed, test-only generated credentials, bounded ownership, safe reports; no arbitrary cleanup or production access. |
| VII / testing and evidence | preserve all regressions, actual required positive/negative cases and exact run/environment; no skip/fallback masquerading as pass. |
| VIII / simplicity & releases | native Laravel/Pest/PDO/Redis tools, scoped test harness only; explicit review, no deployment. |

No exception to the Constitution proposed. Recheck after final decisions/design; unresolved Critical/High checks block affected merge.

## 4. Proposed structure (do not create yet)

```text
tests/
  Unit/Core/                         # existing, preserve
  Unit/Foundation/                   # guard/config/ownership regressions
  Feature/Foundation/                # existing + scoped harness tests
  Architecture/                      # existing + testing boundary checks
  Integration/Foundation/            # only if DEC-01 A, real PostgreSQL/Redis
  Support/                           # small test-only preflight + owned resource support
  Fixtures/                          # existing + narrowly scoped technical fixture if needed
  Pest.php                           # explicit suite bindings only
  TestCase.php                       # preserve default no-global-DB trait
phpunit.xml                           # default fast suites
phpunit.integration.xml               # proposed explicit opt-in real-service suite
.github/workflows/f0-01-laravel-bootstrap.yml
                                     # narrow, reviewed preflight/report/integration job extension
specs/003-testing-foundation/
  spec.md
  plan.md
  research.md
  checklists/requirements.md
  tasks.md                           # later after approved spec/plan
  quickstart.md                      # actual commands/evidence after implementation
```

Future owning Modules use tests/{Unit,Feature,Integration,Contracts}/Modules/<Module>/<Feature> when concrete tests exist; do not add empty suites or migrate existing tests just for naming. Existing F0-02 contract cases stay discovered. Native Pest/PHPUnit; no duplicate frameworks.

## 5. Technical design proposals

### 5.1 Fast lane / preflight

Keep default suite free of real DB/Redis requirements. Test entrypoint guard runs before application boot or potentially dangerous work; independently verify process env and effective isolated Laravel config, no cached conflicting config or arbitrary URL/service fallback. Do not rely on APP_ENV alone or rewrite existing .env. Prefer direct Pest entry with guarded bootstrap; retain current isolated CI Artisan command only if pre-boot guard executes first and regression verified. No blanket migrations, transaction traits or global state. Validate existing environment expectations and no real service connections. Explicitly separate Integration config so it cannot be accidentally selected by default. Integration opt-in must fail if required resources are missing; never silently skip or downgrade.

### 5.2 Real-service lane and resource ownership (DEC-01 A)

Initial supported environment is a dedicated GitHub-hosted job creating its own disposable service containers (not a self-hosted/shared or external DB/Redis endpoint). Proposed design uses runtime-generated credentials, pinned approved image digests, runner-owned loopback/isolated network with job-selected endpoints and readiness/ownership verification. Validate exact runner capabilities in preflight; no external URLs, ambient DB/REDIS credentials, privileged deploy tokens or remote service fallback. Prefer job-local provisioner acting inside the owned container and teardown by runner/container lifecycle. Allowlist endpoint, exact run-derived DB/role/namespace names and separately trusted job-owned identity/marker; validate BEFORE privileged setup or connection use. A name prefix and APP_ENV alone cannot establish ownership. No general-purpose drop/flush/cleanup command for arbitrary host, and no production or shared .env access. Store temporary run descriptor/credentials only with restrictive permissions, never committed, logged or uploaded; test runtime receives only scoped runtime access, not provisioning authority. Cleanup validates actual resource ownership; idempotent for current job and no cross-run cleanup.

### 5.3 PostgreSQL technical probes (not business schema)

One dedicated owned PostgreSQL service, three separate DBs named from validated job/run token (exact grammar to finalize in revised plan). Separate Central/A/B restricted runtime roles; distinct setup owner creates only technical fixture schema/table with committed synthetic marker and overlapping IDs. Explicitly revoke unsafe PUBLIC CONNECT/create privileges and validate no inherited/superuser/createdb/createrole or other excessive grants. Runtime role access only its own verified database/approved fixture privileges. Native PDO or explicit named Laravel connections with no default or tenant fallback; use one narrow implementation and verify actual identities after connection. Test own-db connectivity, expected cross-role denials (distinguish SQLSTATE privilege failure from authentication/network failure), restricted DDL/role provisioning, committed marker separation. No live migration paths, tenant provisioning contract or real business row. No destructive DB operations on any unverified target. Discard owned service at job teardown; no shared Development DB.

### 5.4 Redis and files

Separate test-only Redis service and job-owned namespace(s); propose ACL-restricted test runtime identities with allowed key patterns and minimal commands. Verify real namespaced keys and negative access/forbidden global commands in the owned service; clean only explicitly owned resources or dispose service. No FLUSHALL/FLUSHDB cleanup, no queue/session/runtime configuration changes, no claim of actual Horizon/worker semantics. Keep fast defaults array/sync. Use per-run temporary filesystem root and explicit cleanup when tests need it; avoid arbitrary shared paths or symlink escapes. No speculative helper framework or business factory. Controlled clock/time travel when testing a relevant deterministic scenario, restore after use.

### 5.5 Evidence and CI

Retain existing CI regression path; add narrow relevant test job(s) for F0-03 scope, same locked dependencies, actual extensions/versions and safe report recording. Under DEC-04 A emit standard JUnit XML for actual fast/Integration suites and upload only scoped reports with proposed 7-day retention, including failed runs if safe, without hiding job failure. Record actual commands, source/head/base/checkout, job/shard, durations, counts and safe errors; do not publish config/environment dumps, credentials or real data. No automatic retries, no arbitrary sleeps, no fake green skip. Unavailable service or missing required suite is blocked/failed. Sequential first; parallel worker design and capacity measured later. F0-04 handles broad pipeline/static-analysis/OpenAPI validator and action modernization; no production deployment.

## 6. Risk-selected acceptance and execution gates

1. Before code: owner confirms DEC-01…04; research and propose exact version/extension/image pinning, safety contract and final relevant CI/report detail; approve complete revised Spec/Plan.
2. Generate tasks.md with dependency DAG and consistency analysis; explicit implementation approval (separate).
3. Real baseline against actual reviewed integrated candidate; tests-first guard/negative isolation regression and expected RED where feasible, preserving original 98 cases.
4. Minimal test-only implementation for selected scope, actual fast and required Integration GREEN. Controlled failures/invalid targets verify no unauthorized I/O; real Postgres/Redis positive and negative checks if selected. Test role separation, no production path leakage, old baseline and full applicable suites. No branch or runtime changes outside scope.
5. Record exact run evidence, bounded safety/diff review, ChatGPT technical review, owner review and independent human reviewer when required by risk, final current candidate recheck and explicit development-only merge authority.
6. Verify actual merged tree/revisions; reconcile dated and canonical handoff. No F0-04 implementation, main promotion or deployment.

## 7. Decisions and research still required

See spec §7 and [research.md](research.md). PostgreSQL/Redis concrete supported releases/digests, extension compatibility and exact test resource names are not yet selected. Confirm safety strategy against runner and native framework APIs before final plan approval. If owner chooses narrower DEC-01 B, revise FR-004/05/06 and scope honestly before tasks (do not treat real-service isolation as verified). No executable files, dependencies or CI changes in this draft.
