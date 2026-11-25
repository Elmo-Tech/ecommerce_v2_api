# Feature Specification: F0-03 — Testing Foundation

**Feature Branch:** `feature/f0-03-testing-foundation`  
**Created:** 2026-11-25 (Africa/Cairo)  
**Status:** OWNER-APPROVED SPEC — DEC-01…04 A and complete final technical design approved 2026-11-25; tasks/consistency prepared; separate implementation authorization recorded (T003), baseline pending.  
**Reviewed development baseline:** `97c5895bd5672261247b45f1b7eca5648afaf163`.  
**Input:** Owner explicitly approved the complete final F0-03 Spec and Plan at source SHA `722dd440f36269c2e6ac6d052801b35d6cac876e` on 2026-11-25, including DEC-01…04 A, exact proposed PostgreSQL/Redis test image pins, PHP drivers and technical safety design. Owner requested Tasks and Consistency Analysis only, explicitly without implementation. This authorizes planning documents, not source/test/CI execution, packages, provisioning, deployment or merge.

## 1. Purpose and authority

Provide a small, repeatable, fail-closed backend testing foundation before business Modules are introduced. Preserve the fast working F0-01/F0-02 suites; separately exercise a real disposable multi-database PostgreSQL/Redis technical test environment as approved by DEC-01 A. Prove infrastructure isolation and harness behavior, not application tenancy, permissions, financial correctness or production readiness.

Binding sources: [Constitution v1.0.0](../../.specify/memory/constitution.md) VII/VIII; [Testing Standards](../../docs/02-standards/testing-standards.md) TEST-01/02/03/05/06/09/10; [Database Standards](../../docs/02-standards/database-standards.md) DB-01/09/10; [Backend Architecture](../../docs/01-architecture/backend-architecture.md); [Module Architecture](../../docs/01-architecture/module-architecture.md); [Code Standards](../../docs/02-standards/code-standards.md); [Workflow](../../docs/WORKFLOW.md); [F0-02 merged evidence](../../docs/handoff/F0-02_MERGED_AND_CLOSEOUT_2026-11-25.md). D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED. The technical Foundation owns no business data.

## 2. User scenarios & acceptance tests

### US1 — Fast, isolated regression lane (P1)
A developer can execute the existing Unit, Feature and Architecture tests without PostgreSQL, Redis, a real customer database, production credentials or external network services. Existing F0-01/F0-02 behavior remains tested.

- Given the approved locked dependencies and clean isolated testing configuration, when the fast suites run, then all existing 98 tests remain discovered and pass, with their behavior and groups preserved, without weakening old assertions; additional F0-03 cases may increase counts.
- Given an inherited production/shared-service connection, URL override, unexpected environment, unsafe cache/session/queue/mail setting or ambiguous configuration, when the supported test entry point starts, then it fails closed before service access or destructive operations; no raw credentials appear in output.
- Given a required empty suite or an unexpected failure, when CI runs, then the job fails visibly, not an empty green.

### US2 — Real disposable PostgreSQL foundation (P1; approved DEC-01 A)
A developer can run an explicit Integration suite against three physically separate databases within one owned disposable test PostgreSQL instance: Central, Tenant A, Tenant B. Separate runtime roles and a restricted setup context support later features.

- Given verified job-owned service and separate restricted credentials, when each runtime role connects to its own DB, then the connection identity and committed synthetic probe data are verified.
- Given A and B have the same synthetic local ID but distinct markers, when queries are executed on the appropriate connections, then each returns only its own technical marker.
- Given a reachable verified test server and incorrect Central/A/B runtime role-database pairing, then PostgreSQL rejects the connection for the expected privilege reason; timeouts or bad credentials do not count as proof of grant isolation.
- Given a restricted runtime role attempts prohibited DB/role provisioning or schema creation, then the privilege check/controlled test proves denial. Setup privileges are not provided to the test runtime.
- Given invalid ownership, unknown target, wrong role/database, invalid URL or missing service, then the explicitly requested Integration run fails; it never falls back to SQLite or reports a skip as pass.
- Synthetic test-only fixtures are not Central/Tenant business migrations. No production migration runner or actual Stancl integration is introduced.

### US3 — Scoped Redis and disposable resources (P1; approved DEC-01 A)
A developer can exercise a real owned Redis service and scoped temporary resources without a shared Development/Production Redis, session or queue.

- Given dedicated test-only Redis access, two job-scoped logical namespaces with identical logical keys remain distinct. ACLs restrict test clients to their approved prefixes/commands and prohibit global cleanup; a controlled negative test proves forbidden access denied.
- Given missing/mismatched namespace, service identity or unexpected target, then fail before writing; missing service is a failure for the Integration lane.
- Given distinct job/run identities, a descriptor from one cannot authorize access or cleanup for the other; record real current-job cleanup and bounded independent-run evidence. Cleanup is limited to run-owned resources and ephemeral runner service teardown. No global flush, shared volume wipe or deletion based merely on a name prefix.
- Use native test fixtures and deterministic clock/temporary filesystem helpers where concrete tests justify them. No universal factory framework, fake business models or queue/worker readiness claim.

### US4 — Traceable test evidence and review (P2)
A reviewer can identify the exact source and actual integration candidate, executed suites, environment, pass/fail/not-run states, counts and elapsed durations.

- Given an actual run, record exact source/base/checkout SHA, commands, PHP/Pest/PHPUnit and relevant service/extension versions, test counts, durations, safe failure details and scoped reports.
- Given a failing or unavailable required Integration check, preserve its result; do not retry away a real failure or label it passed.
- Given green applicable checks and review, follow ChatGPT technical review -> owner human review (plus qualified independent human review as required by risk) -> explicit merge authorization -> verified development merge -> handoff.

## 3. Functional requirements

- **FR-001** Preserve the existing locked Laravel 13/PHP 8.4/Pest 5/PHPUnit baseline; no unnecessary package changes, test rewrites or runtime features.
- **FR-002** Define native central test organization (type -> Module -> Feature), explicit fast vs Integration entry points, and group conventions; support existing tests and fixtures without duplication.
- **FR-003** Enforce test environment safety before service access, independently checking effective environment, trusted endpoint/port, DB and role, namespace and run ownership. No ambient URL override, credential fallback, ambiguous target or production/shared-data access.
- **FR-004** provision isolated real PostgreSQL Central/A/B test databases with separate least-privilege runtime roles and distinct setup authority. Verify positive own-DB and negative cross-DB/DDL privileges, including PUBLIC/indirect grants.
- **FR-005** use real PostgreSQL technical fixtures and verify committed data separation with overlapping IDs. No business schema, migration/provisioning or concurrency invariant asserted for unimplemented domains.
- **FR-006** verify real Redis test-only namespace/ACL isolation and own-run cleanup; preserve fast array/sync defaults. No actual Sanctum/session/queue/Horizon/Stancl readiness claim.
- **FR-007** Test-only support must never be registered in runtime app routes/providers or depend on real merchant data. No unconditional RefreshDatabase, migrations, DB wipe, global Redis flush or parallelization.
- **FR-008** Explicitly requested suites fail on missing services, empty discovery, unsafe targets or applicable failure. No auto-skip/fallback that manufactures green results. Keep independent execution reproducible.
- **FR-009** Record risk-selected actual CI and standard machine-readable JUnit XML reports with seven-day retention, with safe artifacts, honest statuses, duration and precise revision/environment. No secret values or real personal data in test files/logs/artifacts.
- **FR-010** Preserve F0-01/F0-02 contracts, explicit module registry, test-only probe isolation and empty production API seam; document remaining integration dependencies and approved review/merge gates.

## 4. Edge cases / negative tests

Inherited APP_ENV/DB_URL/REDIS_URL; hidden config cache overriding intended settings; wrong or stale role/namespace/run ID; endpoint not created by this job; server unavailable; role cannot connect because wrong password (not evidence of DB privilege denial); PUBLIC grants unexpectedly enabling cross-DB access; stale connection reused for a different target; unauthorized Redis namespace or dangerous command; cleanup called twice or with mismatched ownership; fixture error during setup; empty or skipped suite; original fast tests accidentally discover integration tests; report generation on failure without leaking secrets. Each needs an explicit safe outcome.

## 5. Scope exclusions

No business models/factories, domain migration tracks or application DB changes; no real tenant creation/switching, Stancl/Sanctum/Horizon integration, actual authorization/session/CSRF/browser flow, business concurrency/outbox correctness, production Redis configuration, API endpoints, data migration, live services or deployment. Do not invent migration paths/schema or production service versions. F0-04 owns Pint, PHPStan/Larastan, full OpenAPI conformance tooling, broad CI/branch protection and action modernization; F0-05 owns broader workflow automation/traceability. No parallel worker-count, coverage percentage or capacity target without evidence.

## 6. Success criteria

- SC-01: existing 98 tests and prior behavior remain green; standalone Architecture suite remains independently runnable; fast lane accesses no real services.
- SC-02: unsafe config/ownership and missing required integration resources fail closed, with no prohibited writes or exposed credentials.
- SC-03: actual real Central/A/B identities, committed isolated markers and expected cross-role denials verified; no SQLite substitute.
- SC-04: actual scoped Redis positive/negative access and resource ownership/cleanup verified.
- SC-05: final source and tested integration candidate, actual full applicable results, report status and review evidence recorded; no production changes.
- SC-06: separate owner approval of revised complete spec/plan, tasks/consistency review and implementation authorization before source edits; later human review and separate development-only merge approval.

## 7. Approved owner decisions and final technical selections

| ID | Owner-approved A | Concrete design |
| --- | --- | --- |
| DEC-01 | Real disposable PostgreSQL Central/A/B + Redis technical harness and fast tests | Three separate DBs; native PHP driver/role and ACL tests; no business/Stancl/Horizon integration. |
| DEC-02 | Separate fast default and explicit Integration execution | Native Pest/PHPUnit; guarded test-only launcher/bootstrap; no global DB trait; existing regression groups preserved. |
| DEC-03 | Sequential, job-owned GitHub-hosted CI services first | Ubuntu 24.04 linux/amd64; exact official image manifests, localhost-only dynamically allocated ports, no shared/local integration target. |
| DEC-04 | Scoped JUnit reports, seven-day retention, preserve failures | Separate fast and integration artifacts; sanitized technical evidence, no automated retry/quarantine. |

**Detailed technical choices approved with final plan:** PostgreSQL 17.12 Bookworm, Redis 8.2.11 Bookworm, PHP 8.4 with native pdo_pgsql and stable PhpRedis 6.3.0; image manifest references and source evidence in [plan](plan.md) / [research](research.md). These are test-only version choices, not a production version decision. Pin digest plus verify actual image, extension and server versions in implementation preflight; stop on unavailable image, incompatibility or applicable material security finding. No silent version substitution.

## 8. Additional acceptance matrix and boundary clarifications

| AC | Required evidence | Trace |
| --- | --- | --- |
| AC-01 | Full fast suite discovers prior 98 cases and retains old 31/134 and 5/52 standalone regression evidence; no services; integration suite excluded. | FR-001/002/008/010 |
| AC-02 | Process-level negative preflight (inherited non-testing env/unsafe DB or Redis URL/host/role, stale cache, nonempty dotenv, unsafe drivers, unexpected integration descriptor); fail nonzero before connecting, no secret values. Existing intentional production/local route-isolation subprocesses remain safely isolated. | FR-003/007/008/010 |
| AC-03 | Job-owned container identity, exact pinned image, loopback mapping, separate per-run descriptor and least-privilege setup/runtime separation; reject mismatched run or endpoint before use; no external service. | FR-003/004/006/007 |
| AC-04 | Own-DB positive connections for C/A/B, committed overlapping-ID markers; six directed cross-DB denials plus maintenance database checks; role attributes, PUBLIC/indirect membership and schema/DDL restrictions proved. Reject mere connection/network/auth failure as proof. | FR-004/005/008 |
| AC-05 | Real Redis A/B same logical key, own GET/SET/DEL, cross-prefix GET/SET/DEL denied; both clients verify identity; administrator ACL DRYRUN confirms global/destructive/admin commands denied without executing them. | FR-006/008 |
| AC-06 | Wrong run/ownership and stale descriptor denied; two independent synthetic run identities cannot claim each other's resources; actual current job cleanup removes only owned containers/volumes/temp files, safe repeat and partial-setup failure; separate-run evidence recorded without a parallel capacity claim. | FR-003/006/007/008 |
| AC-07 | Actual full fast and Integration checks fail for required empty/skipped/incomplete/risky/warning cases or missing required service; report includes source/base/checkout, durations, versions, counts and honest result; no false green or leaking credentials. | FR-008/009 |
| AC-08 | Diff and architecture test confirm no production route/provider/package/schema changes, no real secrets or business data. Human review and merge consent remain separate. | FR-001/007/010 |

Fast lane remains supported locally only in an isolated checkout with safe test environment; no local PostgreSQL/Redis integration support is introduced by DEC-03 A. A preflight guard is an accidental-target barrier, not a sandbox for malicious arbitrary PHP/Docker/shell code or a defense against a compromised runner. Production and shared data credentials must not be available in either job.

Full Spec/Plan approved at the recorded source revision; [tasks](tasks.md) and [consistency analysis](consistency-analysis.md) prepared. T003 explicit implementation authorization recorded; T004/T005 and T006–T015 complete as documented in the canonical task ledger. T016 test-only guard implementation is next; real service behavior remains unverified. No executable files, migrations, package installs, workflows or deployments in this revision.


**Implementation authorization:** Owner explicitly approved Tasks and Consistency Analysis at `74f45e00e289568aca85e071317100f04b20c8be` and authorized F0-03 implementation within the approved Spec/Plan on 2026-11-25. No merge authority; final ChatGPT technical review, owner/qualified human review and separate development-only merge approval remain mandatory.
