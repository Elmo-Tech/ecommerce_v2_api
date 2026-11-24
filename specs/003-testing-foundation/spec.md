# Feature Specification: F0-03 — Testing Foundation

**Feature Branch:** `feature/f0-03-testing-foundation`  
**Created:** 2026-11-25 (Africa/Cairo)  
**Status:** DRAFT FOR OWNER REVIEW — discovery and provisional design only; no implementation authorization.  
**Reviewed development baseline:** `97c5895bd5672261247b45f1b7eca5648afaf163`.  
**Input:** Owner authorized starting F0-03 discovery and preparation of Spec/Plan after verified F0-02 closure (30/30). No new business or infrastructure decision is implied.

## 1. Purpose and authority

Provide a small, repeatable, fail-closed backend testing foundation before business Modules are introduced. Preserve the fast working F0-01/F0-02 suites; separately exercise a real disposable multi-database PostgreSQL/Redis technical test environment, if the proposed F0-03 scope is approved. Prove infrastructure isolation and harness behavior, not application tenancy, permissions, financial correctness or production readiness.

Binding sources: [Constitution v1.0.0](../../.specify/memory/constitution.md) VII/VIII; [Testing Standards](../../docs/02-standards/testing-standards.md) TEST-01/02/03/05/06/09/10; [Database Standards](../../docs/02-standards/database-standards.md) DB-01/09/10; [Backend Architecture](../../docs/01-architecture/backend-architecture.md); [Module Architecture](../../docs/01-architecture/module-architecture.md); [Code Standards](../../docs/02-standards/code-standards.md); [Workflow](../../docs/WORKFLOW.md); [F0-02 merged evidence](../../docs/handoff/F0-02_MERGED_AND_CLOSEOUT_2026-11-25.md). D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED. The technical Foundation owns no business data.

## 2. User scenarios & acceptance tests

### US1 — Fast, isolated regression lane (P1)
A developer can execute the existing Unit, Feature and Architecture tests without PostgreSQL, Redis, a real customer database, production credentials or external network services. Existing F0-01/F0-02 behavior remains tested.

- Given the approved locked dependencies and clean isolated testing configuration, when the fast suites run, then all existing 98 tests remain discovered and pass, with their existing assertions and groups preserved; additional F0-03 cases may increase counts.
- Given an inherited production/shared-service connection, URL override, unexpected environment, unsafe cache/session/queue/mail setting or ambiguous configuration, when the supported test entry point starts, then it fails closed before service access or destructive operations; no raw credentials appear in output.
- Given a required empty suite or an unexpected failure, when CI runs, then the job fails visibly, not an empty green.

### US2 — Real disposable PostgreSQL foundation (P1; proposed DEC-01 A)
A developer can run an explicit Integration suite against three physically separate databases within one owned disposable test PostgreSQL instance: Central, Tenant A, Tenant B. Separate runtime roles and a restricted setup context support later features.

- Given verified job-owned service and separate restricted credentials, when each runtime role connects to its own DB, then the connection identity and committed synthetic probe data are verified.
- Given A and B have the same synthetic local ID but distinct markers, when queries are executed on the appropriate connections, then each returns only its own technical marker.
- Given a reachable verified test server and incorrect Central/A/B runtime role-database pairing, then PostgreSQL rejects the connection for the expected privilege reason; timeouts or bad credentials do not count as proof of grant isolation.
- Given a restricted runtime role attempts prohibited DB/role provisioning or schema creation, then the privilege check/controlled test proves denial. Setup privileges are not provided to the test runtime.
- Given invalid ownership, unknown target, wrong role/database, invalid URL or missing service, then the explicitly requested Integration run fails; it never falls back to SQLite or reports a skip as pass.
- Synthetic test-only fixtures are not Central/Tenant business migrations. No production migration runner or actual Stancl integration is introduced.

### US3 — Scoped Redis and disposable resources (P1; proposed DEC-01 A)
A developer can exercise a real owned Redis service and scoped temporary resources without a shared Development/Production Redis, session or queue.

- Given dedicated test-only Redis access, two job-scoped logical namespaces with identical logical keys remain distinct. Proposed ACLs restrict test clients to their approved prefixes/commands and prohibit global cleanup; a controlled negative test proves forbidden access denied.
- Given missing/mismatched namespace, service identity or unexpected target, then fail before writing; missing service is a failure for the Integration lane.
- Given two independent test runs, their resources cannot be reused or cleaned by the other. Cleanup is limited to run-owned resources and ephemeral runner service teardown. No global flush, shared volume wipe or deletion based merely on a name prefix.
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
- **FR-004** If DEC-01 A approved, provision isolated real PostgreSQL Central/A/B test databases with separate least-privilege runtime roles and distinct setup authority. Verify positive own-DB and negative cross-DB/DDL privileges, including PUBLIC/indirect grants.
- **FR-005** If DEC-01 A approved, use real PostgreSQL technical fixtures and verify committed data separation with overlapping IDs. No business schema, migration/provisioning or concurrency invariant asserted for unimplemented domains.
- **FR-006** If DEC-01 A approved, verify real Redis test-only namespace/ACL isolation and own-run cleanup; preserve fast array/sync defaults. No actual Sanctum/session/queue/Horizon/Stancl readiness claim.
- **FR-007** Test-only support must never be registered in runtime app routes/providers or depend on real merchant data. No unconditional RefreshDatabase, migrations, DB wipe, global Redis flush or parallelization.
- **FR-008** Explicitly requested suites fail on missing services, empty discovery, unsafe targets or applicable failure. No auto-skip/fallback that manufactures green results. Keep independent execution reproducible.
- **FR-009** Record risk-selected actual CI and standard machine-readable test reports if approved, with safe artifacts, honest statuses, duration and precise revision/environment. No secret values or real personal data in test files/logs/artifacts.
- **FR-010** Preserve F0-01/F0-02 contracts, explicit module registry, test-only probe isolation and empty production API seam; document remaining integration dependencies and approved review/merge gates.

## 4. Edge cases / negative tests

Inherited APP_ENV/DB_URL/REDIS_URL; hidden config cache overriding intended settings; wrong or stale role/namespace/run ID; endpoint not created by this job; server unavailable; role cannot connect because wrong password (not evidence of DB privilege denial); PUBLIC grants unexpectedly enabling cross-DB access; stale connection reused for a different target; unauthorized Redis namespace or dangerous command; cleanup called twice or with mismatched ownership; fixture error during setup; empty or skipped suite; original fast tests accidentally discover integration tests; report generation on failure without leaking secrets. Each needs an explicit safe outcome.

## 5. Scope exclusions

No business models/factories, domain migration tracks or application DB changes; no real tenant creation/switching, Stancl/Sanctum/Horizon integration, actual authorization/session/CSRF/browser flow, business concurrency/outbox correctness, production Redis configuration, API endpoints, data migration, live services or deployment. Do not invent migration paths/schema or production service versions. F0-04 owns Pint, PHPStan/Larastan, full OpenAPI conformance tooling, broad CI/branch protection and action modernization; F0-05 owns broader workflow automation/traceability. No parallel worker-count, coverage percentage or capacity target without evidence.

## 6. Success criteria

- SC-01: existing 98 tests and prior behavior remain green; standalone Architecture suite remains independently runnable; fast lane accesses no real services.
- SC-02: unsafe config/ownership and missing required integration resources fail closed, with no prohibited writes or exposed credentials.
- SC-03: under DEC-01 A, actual real Central/A/B identities, committed isolated markers and expected cross-role denials verified; no SQLite substitute.
- SC-04: under DEC-01 A, actual scoped Redis positive/negative access and resource ownership/cleanup verified.
- SC-05: final source and tested integration candidate, actual full applicable results, report status and review evidence recorded; no production changes.
- SC-06: separate owner approval of revised complete spec/plan, tasks/consistency review and implementation authorization before source edits; later human review and separate development-only merge approval.

## 7. Owner decisions (PROPOSED, not approved)

| ID | Recommended option | Alternative / consequence |
| --- | --- | --- |
| F0-03-DEC-01 | A — Fast harness plus real disposable PostgreSQL Central/A/B and Redis technical infrastructure smoke now; real business/Stancl/Horizon integration later with owning Features. | B — Fast harness only; explicitly defer real DB/Redis infrastructure to subsequent separately approved feature(s), no isolation proof now. TEST-03 §16 recommends A. |
| F0-03-DEC-02 | A — Separate fast default and explicit Integration configuration, with guarded test-only support under tests/Support; preserve existing fixtures/groups and no blanket database traits. | B — One suite with manually selected integration groups; easier accidental service execution/skip ambiguity. |
| F0-03-DEC-03 | A — First verify real services on job-owned GitHub-hosted CI containers, sequential runs; no remote/shared Development service. Exact supported PostgreSQL/Redis image versions/digests and native extension compatibility to be proposed and approved in the revised plan before implementation. No new Composer dependency. | B — Also support local disposable integration tooling now (larger safety/maintenance scope); no universal local access to shared DB. |
| F0-03-DEC-04 | A — Scoped JUnit XML/report artifacts for affected suites, proposed 7-day retention matching existing lock artifact convention, failure evidence preserved, no automated retry/quarantine. Narrow CI integration only; broad quality pipeline F0-04. | B — Retain existing console-only evidence until F0-04; no machine-readable report claim now. |

These decisions are technical planning proposals. The current standards do not select concrete PostgreSQL/Redis versions, image digests, runner budgets or exact provisioning names. Confirm scope and version approach, then review the revised complete specification and plan. No tasks ledger generated and no code or workflow changes authorized in this draft.
