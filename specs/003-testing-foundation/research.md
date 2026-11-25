# F0-03 — source discovery and completed design research

**Date:** 2026-11-25 | **Source revision:** `97c5895bd5672261247b45f1b7eca5648afaf163` | **Status:** DEC-01…04 and final Spec/Plan approved; tasks and analysis prepared; actual execution pending.

## Verified repository baseline
- PR #4 merged `2220a2afc0d97aeb1cf705e21da86af910ee6dda`; PR #5 pointer merged `f0febe3acdd20f84230476629aa9cb759df5ca7e`; PR #6 docs closeout merged `97c5895bd5672261247b45f1b7eca5648afaf163`. F0-02 tasks ledger contains 30 checked tasks and publication condition satisfied.
- [Actual CI #45497208466](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45497208466), job 138810749871: 98/911, standalone Architecture 5/52, original 31/134; actual integrated tree matches merged PR #4. No new execution in discovery.
- composer.json/lock: Laravel 13.43.0, PHP ^8.4, Pest 5.3.7, Pest Laravel plugin 5.0.1, PHPUnit 13.4.7. No Stancl/Sanctum/Horizon/Predis package in lock.
- phpunit.xml: Unit, Feature, Architecture. Pest binds Feature only. TestCase has no DB trait. Existing F0-01 BootstrapSmokeTest verifies no DB connections opened, array/sync. Existing F0-02 architecture checks preserve current packages, empty route seam, no business migrations. Existing fixtures under tests/Fixtures; no Integration/Contracts suite or reusable test support root yet.
- config/database.php contains native pgsql and Redis configurations but not actual provisioned/verified DB or Redis runtime. Redis client defaults phpredis. No production DB schema/migrations introduced. Existing CI sets sqlite :memory:, array cache/session, sync queue; isolated runner creates empty ignored .env; no real PostgreSQL/Redis service test, no JUnit artifact/report yet (existing lock artifact retention 7 days).

## Governing source evidence
- TEST-01/02: risk-based test layers, Pest default with existing PHPUnit permitted, central tests/type/Module/Feature organization, independent deterministic tests.
- TEST-03 §§5.1–5.7 and §16: real Central + two distinct Tenant PostgreSQL databases, restricted DB roles/setup privilege separation, independently positive environment/endpoint/name/role/namespace/run ownership, no blind RefreshDatabase or global flush; sequential before parallel; real service evidence when applicable. Recommended Foundation order includes safe three-DB/Redis harness. This supports approved DEC-01 A; does not prove implementation or select concrete service versions.
- DB-01/09/10: real role denial, separate migration tracks, restricted runtime roles; no business migration designs invented in this Feature.
- TEST-09 §11.4: precise actual revisions, suite/environment/outcome/duration/safe artifacts; JUnit preferred where supported. Retention/runner policy to specify; 7 days is explicitly approved by DEC-04 A.
- Constitution VII/VIII: applicable Critical/High checks pass with actual evidence; no mandatory heavy suite for trivial docs; preserve reviewed Feature gates. Workflow 2026-11-25: ChatGPT review -> owner human review -> explicit merge; third-party AI review optional.
- D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## Owner approvals

Owner explicitly approved F0-03 DEC-01 A, DEC-02 A, DEC-03 A and DEC-04 A on 2026-11-25, and requested research plus the final Spec/Plan before implementation. These four choices are approved; the complete detailed Spec/Plan and concrete technical selections below await separate owner sign-off. No execution or merge authority.

## External source research (2026-11-25)

| Topic | Verified documentation / choice | Limits |
| --- | --- | --- |
| PostgreSQL | [Version policy](https://www.postgresql.org/support/versioning/) and [17.12 release](https://www.postgresql.org/docs/17/release-17-12.html), dated 2026-11-12. 17 supported until 2029-11-08. Choose 17.12 rather than a new major release to limit novelty for technical fixture tests. | Does not establish actual installed compatibility or a production DB version. |
| Redis | [OSS release policy](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/) identifies 8.2 extended through 2030-09-01; [release archive](https://download.redis.io/releases/) includes 8.2.11 (2026-10-26). Choose maintained extended line rather than expiring standard lines. | Test-only choice; not queue/Horizon integration proof. |
| PostgreSQL image | [Official Docker layer](https://hub.docker.com/layers/library/postgres/17.12-bookworm/images/sha256-c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03) lists linux/amd64 manifest `sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03` for 17.12-bookworm. | Registry API not reachable from web retrieval; source-published manifest found. Actual digest pull/inspect and version still mandatory. |
| Redis image | [Official Docker layer](https://hub.docker.com/layers/library/redis/8.2.11-bookworm/images/sha256-0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87) lists linux/amd64 manifest `sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87` for 8.2.11-bookworm. | Same limitation. Distinguish platform manifest from multi-platform index and mutable tag. |
| PHP drivers | [PHP PDO_PGSQL manual](https://www.php.net/manual/en/ref.pdo-pgsql.php); [PECL Redis 6.3.0 stable](https://pecl.php.net/package/redis/6.3.0), PHP >=7.4 published requirement; [PECL listing](https://pecl.php.net/package/redis) marks 6.4.0RC1 beta. | No actual extension installation or compatibility run; verify on PHP 8.4, no forced fallback. |
| Job isolation | [GitHub container networking](https://docs.github.com/en/actions/tutorials/use-containerized-services/use-docker-service-containers) documents job service lifecycle and host port mapping; [runner/container model](https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/run-jobs-in-a-container). | Choose job-managed containers for generated credentials and loopback-only mapping; verify runner and ownership in real preflight. |
| PostgreSQL grants | [Privileges](https://www.postgresql.org/docs/17/ddl-priv.html), [error codes](https://www.postgresql.org/docs/17/errcodes-appendix.html): object ownership, CONNECT, TEMP/CREATE and SQLSTATE insufficient privilege. | Actual connect-denial SQLSTATE may be abstracted by PDO; need real safe server evidence and grant checks, not generic connection failure. |
| Redis | [ACL docs](https://redis.io/docs/latest/operate/oss_and_stack/management/security/acl/): per-user key/command rules and subcommands. | Actual ACL runtime NOPERM and admin ACL DRYRUN for prohibited commands must be verified. |
| Test CLI | [Pest CLI reference](https://pestphp.com/docs/cli-api-reference): explicit config, bootstrap, fail-on-empty/warning/risky/skipped/incomplete, JUnit logging. Existing locked PHPUnit/Pest known working for older suite. | PHPUnit 13 documentation URL was unavailable via retrieval; exact locked binary flags/bootstrap behavior verified during execution, not assumed pass. |

## Alternatives considered and decisions in final plan

- Fast + separate Integration config/guard rather than one group-selected suite; no accidental real DB/Redis execution by default. Reject unsafe ambient env, nonempty dotenv/cached config; no change to actual developer env. Test-only native PDO and PhpRedis give real infrastructure evidence with no premature application tenancy. Existing Feature and route-cache tests remain independent.
- Job-owned containers with dynamic 127.0.0.1 binding, fresh generated run identity/passwords, actual Docker identity/label/endpoint verification, no external targets. Avoid provisioning/drop on shared service, global flush, broad cleanup, or prefix-only ownership. Setup/runtime access separated. No local integration tooling, no remote daemon.
- 3 separate PostgreSQL DBs and restricted roles; committed read-only marker fixture, actual positive/negative matrix. Redis key and command ACLs rather than logical DB labels/prefix alone; global denial checked non-destructively with ACL DRYRUN. Explicit required suites fail on missing services. See detailed matrix in final spec and plan.
- Keep existing locked PHP packages and current workflow family. Narrow two-job test pipeline, approved 7-day JUnit artifacts and safe summary; no broad pipeline or F0-04 static-analysis work now. Run naming, deadlines and support paths are approved plan selections at the recorded final revision, not actual execution facts.
- Published Docker page digests can change when tags rebuilt; pin exact retrieved amd64 manifest, not current tag. Check availability and applicable material vulnerabilities at preflight; stop and obtain reviewed replacement if needed. No full image audit or claim of production readiness.

## Remaining execution-only verification and owner gate

Design research completed for this review candidate. Actual image retrieval, runtime version checks, extension loading, setup/run/cleanup, native denied-access evidence and JUnit behavior require real CI after tasks/consistency review and separate implementation authorization. No test or service executed here. If an unexpected compatibility or safety blocker changes scope or pin, get owner approval before substitution. Final Spec/Plan approved and tasks/analysis prepared; next action is T003 separate implementation authorization.


## Final Spec/Plan approval and task planning

Owner explicitly approved the complete final F0-03 Spec and Plan at source SHA `722dd440f36269c2e6ac6d052801b35d6cac876e` on 2026-11-25, including DEC-01…04 A, exact proposed PostgreSQL/Redis test image pins, PHP drivers and technical safety design. Owner requested Tasks and Consistency Analysis only, explicitly without implementation. This authorizes planning documents, not source/test/CI execution, packages, provisioning, deployment or merge.

See [tasks](tasks.md) and [consistency analysis](consistency-analysis.md). No new runtime evidence; pins and real service/driver/runner compatibility remain to verify in T005/T027.
