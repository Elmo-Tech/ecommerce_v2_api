# Implementation Plan: F0-03 — Testing Foundation (owner-approved)

**Branch:** `feature/f0-03-testing-foundation` | **Date:** 2026-11-25 | **Spec:** [spec.md](spec.md)  
**Status:** DEC-01…04 A and full detailed plan approved 2026-11-25 at the recorded source revision. Tasks and analysis prepared; T003 implementation authorization recorded; T004 baseline pending. No code/CI changes or actual service compatibility claimed.  
**Baseline:** `97c5895bd5672261247b45f1b7eca5648afaf163` on development. F0-02 PR #4/#5/#6 merged; 30/30 verified.

## 1. Summary and approved scope

Owner explicitly approved the complete final F0-03 Spec and Plan at source SHA `722dd440f36269c2e6ac6d052801b35d6cac876e` on 2026-11-25, including DEC-01…04 A, exact proposed PostgreSQL/Redis test image pins, PHP drivers and technical safety design. Owner requested Tasks and Consistency Analysis only, explicitly without implementation. This authorizes planning documents, not source/test/CI execution, packages, provisioning, deployment or merge.

Keep native Pest/PHPUnit fast tests and explicitly guarded real-service Integration tests separate. F0-03 establishes a test-only PostgreSQL Central/A/B and Redis isolation harness; no application Tenancy, Identity, worker, domain models, migration tracks or business APIs. Test runtime has restricted credentials; only job-owned setup provisions synthetic fixtures. No new Composer dependency.

## 2. Technical baseline, version pinning and compatibility gate

| Component | Verified or proposed version | Evidence |
| --- | --- | --- |
| PHP | ^8.4; previously tested 8.4.28 | Current lock/CI; verify actual 8.4 patch in each new run |
| Laravel | 13.43.0 | Existing locked/real CI |
| Pest / Laravel plugin / PHPUnit | 5.3.7 / 5.0.1 / 13.4.7 | Existing locked/real CI |
| PostgreSQL | **17.12-bookworm** | [release 2026-11-12](https://www.postgresql.org/docs/17/release-17-12.html), [supported through 2029-11-08](https://www.postgresql.org/support/versioning/), [official amd64 manifest](https://hub.docker.com/layers/library/postgres/17.12-bookworm/images/sha256-c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03) |
| Redis Open Source | **8.2.11-bookworm** | [extended support through 2030-09-01](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/), [release archive](https://download.redis.io/releases/), [official amd64 manifest](https://hub.docker.com/layers/library/redis/8.2.11-bookworm/images/sha256-0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87) |
| Native PHP drivers | pdo_pgsql matching PHP 8.4, PhpRedis **6.3.0** | [PDO_PGSQL](https://www.php.net/manual/en/ref.pdo-pgsql.php), [PECL stable 6.3.0](https://pecl.php.net/package/redis/6.3.0); not installed/tested yet |
| Runner | GitHub-hosted Ubuntu 24.04, linux/amd64 | Existing runner family; verify architecture and Docker availability at preflight |

The exact approved **platform manifest** pulls (not multi-platform index digests, not mutable tags):
```text
docker.io/library/postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03
docker.io/library/redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87
```
No production version selection. Official release/manifest pages were examined on 2026-11-25. Docker Hub API retrieval was inaccessible, but official layer pages give full amd64 digests; pulling and validating the actual immutable images remains a mandatory implementation-time preflight. Compare platform, image ID/manifest and actual server versions, verify installed PHP 8.4, PDO pgsql driver and PhpRedis version, record exact versions and available applicable security evidence. Use existing setup-php action family; request pdo_pgsql and redis-6.3.0, verify actual extension version (do not assume installation success). No force-install or silent downgrade, mutable-tag fallback, RC or unreviewed pin refresh. If the digest is unavailable or an applicable Critical/High issue or incompatibility prevents safe use, stop and submit a revised pin/plan for approval. This is not a comprehensive image vulnerability audit; image supply-chain hardening and broader tooling remain F0-04, without waiving an applicable blocker.

Prior real evidence: [CI #45497208466](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45497208466), job 138810749871, 98/911, standalone Architecture 5/52, baseline group 31/134. Actual tested candidate tree equals PR #4 merged tree; PR #6 docs-only. No new CI was run in this planning milestone.

## 3. Constitution and source reconciliation

| Principle | Final design |
| --- | --- |
| I | Approved TEST-01…10, DB-01/09/10 and F0-01/02 decisions; no invented business rule. |
| II | Tests-only Engineering Foundation, no data owner/module/provider change. |
| III | Separate physical DBs + least privilege, Redis ACL namespace, no claim of actual tenant/actor isolation in the app. |
| IV | Synthetic committed data isolation; real domain atomicity/races/outbox require owning Feature tests. |
| V | Preserve F0-02 API/error/route contracts and existing fixture-only routes. |
| VI | Fail-closed accidental-target guard, owned disposable services, no shared/prod credentials, safe diagnostics. |
| VII | Actual required fast/integration results; preserve negatives, no skipped/empty false green; exact evidence. |
| VIII | Native libraries/thin test helper; no speculative framework; separate human review and merge, no release. |

No constitutional exception proposed. TEST-03 §5.2 requires positive environment/endpoint/name/role/namespace/run-ownership checks; DB-09 requires actual role denials. TEST-09 requires truthful evidence. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## 4. Concrete implementation layout (planned, not created)

```text
tests/
  Unit/Core/                             # unchanged existing tests
  Unit/Foundation/                       # guard, run descriptor and cleanup policy
  Feature/Foundation/                    # existing + supported-entrypoint regressions
  Architecture/                          # existing + test harness boundaries
  Integration/Foundation/
    PostgreSqlIsolationTest.php
    RedisIsolationTest.php
  Fixtures/                              # existing + test-only fixture definitions
  Support/
    run-tests.php                        # thin supported test command and raw preflight
    bootstrap.php                        # default fast runner guard, require autoload
    integration-bootstrap.php            # explicit mode/descriptor gate, require autoload
    TestEnvironmentGuard.php
    OwnedTestRun.php                     # small immutable run descriptor / validation
    IntegrationConnections.php           # native PDO/PhpRedis only, fixed logical slots
    ci/                                  # narrow setup/cleanup and safe report handling
  Pest.php                               # existing Feature binding; Integration native Pest
  TestCase.php                           # no blanket migrations / transactions
phpunit.xml                               # fast Unit/Feature/Architecture only
phpunit.integration.xml                   # Integration only, explicit separate config
.github/workflows/f0-01-laravel-bootstrap.yml
                                          # extend relevant filters and two jobs
specs/003-testing-foundation/
  spec.md / plan.md / research.md / checklists/requirements.md
  tasks.md / consistency-analysis.md       # prepared, execution approval pending
  quickstart.md                           # actual evidence after execution
```

Use Tests PSR-4 already mapped. Names under Support are design paths, not permission for unnecessary classes; combine helpers if simpler. Keep old Fixtures and groups, no duplicate Contracts suite or empty tests. Future Modules organize by type/Module/Feature. Integration tests use native PDO/PhpRedis directly for engine/driver isolation; do not boot full Laravel app or set real application DB as default for this lane. Native Laravel named connection and Stancl/session/worker compatibility must be proved later with owning Features. No app/, config/, bootstrap/, production routes or composer manifest/lock edits expected.

## 5. Fast lane — safe entry and separation

- Supported entrypoint: `php tests/Support/run-tests.php fast`; optionally an explicitly allowlisted suite/group selector and report destination under the owned report directory. Wrapper validates raw process environment BEFORE loading Composer/Laravel, and invokes Pest using argument arrays (no arbitrary shell or user-controlled CLI/config override). No generic extra args escape hatch for required CI.
- Reject explicitly conflicting env from getenv, ENV, SERVER rather than silently force-overwrite: APP_ENV must be unset or testing; DB_CONNECTION unset or sqlite; DB_DATABASE unset or :memory:; DB_URL empty/unset; no actual DB host/credentials/PG service overrides or REDIS_URL/host/credentials, no inherited F0-03 integration descriptor. Array cache/session/mail, sync queue, null broadcast, local test filesystem; reject external service configuration and ambient cloud credentials in the supported environment. CI normalizes its current MAIL_MAILER=log to array consistently with XML. Set missing safe defaults only; do not rely on XML force=true to hide unsafe input.
- Use isolated checkout; refuse nonempty, symlinked or unexpected .env/.env.testing, cached Laravel configuration or arbitrary cache-path overrides. Do not delete, rewrite or read/print developer/Production .env contents. CI may create an ignored zero-byte .env only if absent in its own clean workspace (existing pattern); developer fast tests use an isolated checkout with explicit empty safe file. Never tell a developer to clear their actual application config or database.
- Default phpunit.xml points to guarded bootstrap, retains three existing suites and APP_ENV=testing; dedicated Integration XML and bootstrap validate explicit integration mode and owned descriptor without global application DB changes. Backstop validates effective process config before loading application; tests validate actual effective Laravel config and absence of open DB connections. Direct supported Pest invocation with default XML still guarded; intentional arbitrary alternate config, CLI or manually executed code is outside guard's safety guarantee. No unsupported `--no-configuration` gate pass. Guard itself is tested in subprocesses with benign synthetic sentinel/unsafe settings, not real Production targets.
- Keep existing independent Architecture and foundation group selection. Preserve existing intentionally local/production route-isolation subprocesses: their sanitized child env and owned scratch route-cache path are narrowly reviewed test fixtures, not an exemption for running real integration or destructive commands outside testing. Keep old 98 scenarios and assertions; no blanket RefreshDatabase/DatabaseTransactions. If effective configuration or bootstrap change breaks original tests, fix the real cause, not remove test coverage.
- Fail-on-empty, fail-on-warning, fail-on-risky, fail-on-skipped and fail-on-incomplete in required lanes; no skip due to missing extensions/services. Record actual selected suites and test cases. Test guard ordering with child entry sentinel that must not be reached on rejected configuration.

## 6. Integration job lifecycle and resource safety (Critical)

**Topology:** separate GitHub-hosted job on ubuntu-24.04, sequential, fresh runner; two explicitly job-created Docker containers, pinned linux/amd64 images. Prefer small runner-step provisioner over predeclared service containers to generate per-run credentials before startup and bind published ports only to 127.0.0.1 (dynamic host port); [GitHub supports job-local container networking](https://docs.github.com/en/actions/tutorials/use-containerized-services/use-docker-service-containers). No remote Docker daemon, self-hosted runner, external host, pre-existing DB/Redis, production env/secrets, shared host data volume or host network mode. No privileged container, Docker socket mount into service, or arbitrary host path.

1. **Preflight before creating/connecting:** require APP_ENV=testing, actual GitHub-hosted Linux/amd64 context and expected repo/job mode; reject inherited DOCKER_HOST/remote context/TLS overrides and nonlocal Docker endpoint. Read-only GITHUB_TOKEN permissions; no deployment credentials; checkout credentials not persisted where supported. Install locked packages with existing no-scripts/no-plugins; verify versions. No pull_request_target or untrusted external workflow inputs interpolated into commands.
2. Generate cryptographically random 16 lowercase hex run token (8 bytes, validated exact grammar), separate 256-bit random hex runtime/setup passwords. Bind record to GitHub run ID, attempt, job and random token. Per-run directory under RUNNER_TEMP, 0700, files 0600; validate canonical directory, no symlinks/escaping parent. Generate config only in this directory; never overwrite a developer file. Mask generated secrets before use; no xtrace/printenv/docker inspect full output. No secret on command-line arguments or uploaded files; use protected files/stdin and scoped process environment. Setup material stays outside test runtime descriptor.
3. Pull exact pinned images; start new containers with unique names, run label(s), capture exact returned IDs, inspect only selected identity, image, network and published-port fields. PostgreSQL data and Redis state disposable, no existing named volume; use new anonymous volumes or private disposable storage. Bind only loopback dynamic ports; verify actual mapping. Supply private setup/ACL configuration from owned temp files (read-only mount of generated file only if necessary). Never derive ownership merely from container names, prefixes or APP_ENV. Verify captured ID + labels + expected image + job-created resource record and local daemon before any admin setup. Start/readiness checks bounded (proposed 60 seconds per service, condition polling with deadline; no arbitrary fixed sleeps). Actual runner/image readiness validated at execution.
4. Provision using admin inside exact owned container, not a network admin connection to a supplied host. Scope privileged setup credentials to setup step; PostgreSQL container-local admin only, host network auth SCRAM; Redis admin ACL only for setup/verification. No trust-mode network authentication. Validate no shared service exposure. Generate separate runtime descriptor with fixed literal 127.0.0.1, discovered ports, exact DB names/users and Redis users/prefixes, run identity and expected markers; do not include admin credential or arbitrary URL. Descriptor under protected directory, canonical paths, independent trusted current-job identity record; runtime guard confirms owned resource provenance and expected endpoints before socket open. Verify server identity and synthetic marker after auth before fixture write. No fallback to default, previous or user-provided connection.
5. Integration entry only `php tests/Support/run-tests.php integration` under owned job; required extension/descriptor/service absent means nonzero, not skip. Enforce typed fixed slots (central,a,b and Redis a,b), strict numeric port/identity/name/prefix grammar, no query parameters or supplied DSNs. No persistent connection/pconnect or retry to alternate service. Fresh connections per independent test where needed; close after use.
6. Test normal/negative scenarios; on any failure preserve original exit status and safe evidence. Cleanup in unconditional job step (also on partial setup failure) validates captured ID + labels/image/ownership record; removes only those exact containers and their newly created anonymous volumes, and only canonical owned temp files. No name-pattern bulk removal, prune, broad rm, SQL drop of unknown targets, Redis FLUSH*. Cleanup repeated with absent own resource is safe; a mismatched resource is not deleted and job fails. GitHub hosted runner disposal is a secondary containment boundary, not proof cleanup succeeded. Test negative ownership/cleanup with harmless fabricated records and no unauthorized I/O; verify actual current job containers disappear, and independent run tokens/descriptors cannot claim each other's resources. No requirement to parallelize CI or claim real cross-shard performance.

**Threat boundary:** accidental configuration/ownership mistakes in a trusted reviewed test harness, not adversarial arbitrary code executing with runner Docker access. Code review, least privileges and no sensitive credentials are still required. This protects test resources, not a Production authorization control.

## 7. PostgreSQL isolation contract

Concrete names (all fit PostgreSQL 63-byte identifiers): `f03_<token>_central`, `f03_<token>_a`, `f03_<token>_b`; corresponding roles append `_rt`. Fixed technical schema `f03_probe`, table `markers`. No domain schema or migration file. Per-run distinct synthetic markers with known ID 100 in all three databases, created and committed by setup before tests. Setup owner owns databases/schema/table, not runtime role.

- Create each runtime login with SCRAM password, NOSUPERUSER, NOCREATEDB, NOCREATEROLE, NOINHERIT, NOREPLICATION, NOBYPASSRLS; no membership/SET ROLE path into other runtime/owner/admin roles. Revoke PUBLIC CONNECT/TEMP on each test DB and accessible maintenance/template databases (template0 remains nonconnectable), grant CONNECT only to matching runtime role; retain owner/admin setup access. Revoke unsafe PUBLIC schema create/access, verify no inherited privileges; runtime has only own probe schema USAGE and marker table SELECT. Runtime fixture is read-only because committed separation needs no business writer. Do not create DB/schema under runtime role. [PostgreSQL privileges](https://www.postgresql.org/docs/17/ddl-priv.html).
- Native PDO uses explicit validated host/port/db/user/pass, exception mode, bounded connection/statement timeout, no persistent connection or external libpq service/PG* ambient override. Explicit identity checks (current_database, current_user/session_user, server version, own marker). Pre-connection authorization rejects invalid target normally; a **separate closed test matrix** intentionally attempts only known owned cross-role/database pairs with valid runtime credentials to prove server denial (not bypassing guard for arbitrary target).
- Positive: C→C, A→A, B→B; own committed marker ID 100 distinct; compare independent connection results. Negative: C→A/B, A→C/B, B→C/A; maintenance postgres/template1 denial as applicable. Check credentials valid on own DB before negative. Assert actual denied connection with server insufficient_privilege (42501) evidence; PDO may collapse connect errors into generic connection SQLSTATE, so also inspect actual bounded safe server diagnostic and admin privilege facts (has_database_privilege, role attributes/memberships). Capture only safe code/classification, role/slot, not raw server dumps; network/auth timeout or wrong password never accepted as proof. [SQLSTATE reference](https://www.postgresql.org/docs/17/errcodes-appendix.html). Verify server log format and correlation in actual preflight, if code/reason cannot be established the check fails.
- DDL restrictions: verify actual role attrs, membership and database/schema grants; run harmless denied CREATE SCHEMA/table and controlled role-creation probe in owned test DB only (rollback if unexpected allowed, fail); verify no createdb privilege and all foreign DB grants denied. No production-oriented migration/fresh/drop command or arbitrary database-creation probe. Unexpected permission is blocking. Synthetic marker schema is fixture only; later DB-01 migrations/real Stancl must use actual reviewed definitions and new tests.

## 8. Redis isolation contract and temp files

Single owned Redis 8.2.11 service, DB 0; two runtime users `f03_<token>_a_rt`, `f03_<token>_b_rt` and exact key prefixes `f03:<token>:a:`, `f03:<token>:b:`. No relying on logical DB number or string prefix as sole security boundary. Explicit generated ACL file: default user disabled; separate test-only admin used during setup; each runtime user reset then allowed only own key pattern and minimal PING/GET/SET/DEL and ACL WHOAMI subcommand to verify identity. No allcommands/allkeys, SELECT, EVAL, CONFIG, ACL mutation, FLUSHALL/FLUSHDB, persistence/replication admin, broad scan. [Redis ACL reference](https://redis.io/docs/latest/operate/oss_and_stack/management/security/acl/). Use native PhpRedis 6.3.0, direct nonpersistent connection/auth username/password, finite timeouts, exact full keys (no implicit Laravel cache prefix). Verify auth, WHOAMI, PING and actual expected server version from setup safe metadata; runtime descriptor carries no admin access. Setup preflights namespace/ACL grants before tests. Runtime test same logical key yields distinct markers; actual cross-prefix GET/SET/DEL return NOPERM, own-key deletion leaves other unchanged; authenticate successfully first. Admin ACL DRYRUN checks global/destructive and admin commands denied **without executing** them; no global flush even for cleanup. Exact own keys deleted or container discarded. No actual Redis queue, cache/session driver switching, Horizon or tenant worker readiness claim.

For needed temporary test files, create unique directory within owned RUNNER_TEMP, restrict permissions, verify canonical descendants and no symlink traversal; close connections and cleanup in finally, runner step still handles partial failures. Preserve existing scratch fixture tests; no broad storage deletion. No universal factory/time abstraction; fixture helpers only where concrete tests require.

## 9. CI and reports

- Extend current read-only Laravel foundation workflow narrowly: retain current dependency lock/platform/audit, old foundation group, complete fast Unit/Feature/Architecture, standalone Architecture, PHP lint, clean tracked diff and current optional-tool notices. Replace direct test commands with guarded equivalent after actual regression verification. Add new integration config/support path filters; broad Core/test/config/dependency/workflow changes trigger applicable checks, not only integration file names. Separate job for PostgreSQL/Redis, fresh runner, no shared job credentials or database; sequential tests. Required gates for this Feature are both real lanes on reviewed integrated PR candidate; actual GitHub branch protections are not assumed or changed in F0-03.
- CLI flags supported by [Pest CLI](https://pestphp.com/docs/cli-api-reference): explicit config/suite, fail-on-empty-test-suite, fail-on-warning/risky/skipped/incomplete and log-junit. Verify exact locked Pest 5.3.7/PHPUnit 13.4.7 behavior in preflight; preserve original fixture subprocess behavior and group count. Do not use coverage floor, automatic retries/quarantine, parallel runners, new dependency or full static-analysis infrastructure. Proposed Integration job timeout 20 minutes (existing CI convention); service readiness 60 seconds; record durations, adjust only with actual evidence and reviewed plan if needed.
- Native JUnit XML generated separately for fast complete, foundation baseline and standalone Architecture as applicable, plus Integration; separate artifact names incorporating job/run/attempt, retention **7 days** (approved DEC-04). Safe JSON or Markdown summary generated from actual commands/exit status/report counts and exact source/base/checkout/environment. Required report missing/invalid blocks evidence, not manufactured passed; if preflight fails before tests, record BLOCKED/NOT RUN + failure reason; job still nonzero. Failure upload uses always-condition but only existing validated/sanitized scoped reports; never upload entire workspace, .env, credential descriptor, raw DB/container logs or unreviewed stack traces. Verify safe escaping and scan actual report for generated credentials before upload; suppress unsafe report and fail evidence check, emit only sanitized failure summary. No raw secret values in output. Maintain original failure even when cleanup/report upload succeeds. No retry to erase first failure.
- Keep F0-04 separate: Pint, PHPStan/Larastan, full OpenAPI document validator, action version modernization, broader reporting/quality/branch controls. F0-05 workflow automation/traceability separate. No CI/deploy on main; no new build/release/production service.

## 10. Risk acceptance and execution gates

| Check family | Risk | Required proof after authorization |
| --- | --- | --- |
| Fast entry/unsafe config/ownership | Critical | Unit + subprocess negative preflight, sentinel untouched; no external network/DB operations; baseline preserved. |
| PostgreSQL isolation and privileges | Critical | Real 3 DB identities, 6 directed role denials, maintenance grants, actual controlled DDL denial, committed overlapping markers. |
| Redis isolation and safe cleanup | Critical | Real ACL allowed/denied behavior, no global commands executed, independent run descriptor negative and verified owned teardown. |
| CI suite selection/reporting | High | Actual integration and fast results, missing-service fail, skipped/empty fail, report/failure/cleanup semantics; exact revision. |
| Production boundary and old regressions | High | Diff/architecture plus F0-01/02 full regression, unchanged packages/production seam. |

1. Owner approval of complete final Spec/Plan — recorded 2026-11-25 (T001 complete).
2. [Tasks](tasks.md) and [consistency analysis](consistency-analysis.md) prepared (T002 complete). T003 separate explicit implementation approval recorded.
3. Actual integrated baseline and pinned-service/extension preflight as scoped by approved tasks; tests first (negative guard/provenance regressions expected RED when feasible), preserve old tests. No service provisioning before approved implementation and verified disposable target.
4. Minimal implementation and actual GREEN fast + Integration; reproduce/verify required negative cases; bounded secret/scope review, actual reports. Do not claim test count for unwritten tests; no false pass.
5. ChatGPT technical review, owner review, qualified independent human review for sensitive/high-risk when owner cannot provide it, separate owner development-only merge consent, exact final head/base and actual required CI. CodeRabbit/Copilot optional. No self-approval.
6. Verify actual merge SHA/tree, publish final handoff/ledger. No F0-04 code, main merge/deployment/Pilot approval.

**Current state:** owner-approved technical design; documentary T001–T002 complete (2/40). No runtime, driver, image, PostgreSQL/Redis service, JUnit report or F0-03 test has been executed. Actual availability/compatibility and real safety evidence remain blocking execution gates. Implementation authorized within approved scope; merge remains unauthorized.


Owner explicitly approved Tasks and Consistency Analysis at `74f45e00e289568aca85e071317100f04b20c8be` and authorized F0-03 implementation within the approved Spec/Plan on 2026-11-25. No merge authority; final ChatGPT technical review, owner/qualified human review and separate development-only merge approval remain mandatory.
