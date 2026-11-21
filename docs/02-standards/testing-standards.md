# E-commerce SaaS V2 — Testing Standards

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target path:** `docs/02-standards/testing-standards.md`  
**Branch:** `development`  
**Version:** 1.0 — Approved Engineering Testing Standards  
**Prepared:** 2026-11-21  
**Approval:** **TEST-01 through TEST-10 — Option A APPROVED**, with the owner explicitly authorizing completion of remaining decisions and publication on `development`.  
**Current implementation status:** **DOCUMENTATION ONLY — NO TEST RUN, CI CONFIGURATION, APPLICATION CODE, DATABASE OR DEPLOYMENT IS CREATED OR VERIFIED BY THIS DOCUMENT.**

> **Mandatory reading rule:** “Approved” means the testing strategy and acceptance rules are accepted, not that Laravel, Next.js, Pest, Vitest, Playwright, PostgreSQL, Redis, Horizon, CI, security controls or the Release Gates have been configured and tested. **BA-16 Backup & Restore is DEFERRED; BA-30 G3 remains BLOCKED for live merchant activation; D-201 remains OPEN.** The Production environment and legal Pilot sign-off are separate concerns.

---

## 1. Purpose, Authority, Scope and Non-Goals

### 1.1 Purpose

Make future testing **repeatable, realistic, traceable and proportional to risk** in the approved Laravel 13 API-first Modular Monolith, independently deployed Next.js frontend and PostgreSQL Database-per-Tenant architecture. Provide a standard that Developers, AI Agents, Feature Owners, Reviewers and release operators can follow without inventing a testing architecture for each new Module.

This document defines:

1. TEST-01 — Risk-Based Layered Testing Strategy.
2. TEST-02 — Frameworks, Directory Organization and Naming.
3. TEST-03 — Databases, Fixtures, Factories and Test Isolation.
4. TEST-04 — Unit, Domain, Money and Business Invariant Testing.
5. TEST-05 — API, Authentication, Authorization and Contract Testing.
6. TEST-06 — Transactions, Concurrency, Idempotency and Outbox Testing.
7. TEST-07 — Next.js Frontend, Browser E2E and UX Behavior Testing.
8. TEST-08 — Performance, Load, Stress and Capacity Validation.
9. TEST-09 — GitHub Actions, PR Quality Gates, Reports and Flaky Tests.
10. TEST-10 — Cross-Document Review, Acceptance Checklist and Handoff.

An owning Feature translates applicable rules into its Spec Kit `spec.md`, `plan.md` and `tasks.md`, tests, PR evidence and eventual Release artifacts. The Standard does not replace approved D-series business behavior or a Feature's actual API/schema contracts.

### 1.2 Governing documentation and decision precedence

| Governing source | Testing authority |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | MVP/Pilot scope, Egypt closed Pilot, no silently added integrations |
| [Backend Architecture](../01-architecture/backend-architecture.md) | **BA-25** risk-based tests, **BA-29** measured capacity, **BA-30** G1–G10 readiness gates |
| [Module Architecture](../01-architecture/module-architecture.md) | **MA-08** Feature-Level Spec Kit, One Writer, Module/Feature contracts, Module Testing |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Separate Central/Tenant PostgreSQL, privileged provisioning and real context isolation |
| [Commerce Workflow Architecture](../01-architecture/commerce-workflow-architecture.md) | **CW-01…CW-09** commercial invariants and scenario matrix |
| [Transaction & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | **TC-01…TC-07** local ACID, two-layer idempotency, outbox, retries and release blockers |
| [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | **QJ-01…QJ-08** worker/relay retry, ordering, Tenant safety and workload testing |
| [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) | **CR-01…CR-08**, authorization cache isolation and meaningful performance measurement |
| [Infrastructure Architecture](../01-architecture/infrastructure-architecture.md) | **INF-07** GitHub Actions/GHCR, **INF-09** release overlap, **INF-11** observability |
| [File Storage Architecture](../01-architecture/file-storage-media-uploads-architecture.md) | **FS-01…FS-08**, public/private storage, exports, cleanup and persistence under Blue-Green |
| [Database Standards](./database-standards.md) | **DB-01…DB-10**, real PostgreSQL constraints, grants, locking, migrations, diagnostics |
| [Code Standards](./code-standards.md) | **CS-01/09** Module organization, quality gates and honest PR evidence |
| [API Standards](./api-standards.md) | **AS-01…AS-09**, OpenAPI, response/validation, auth and Tenant API contract tests |
| [Authentication Standards](./authentication-standards.md) | **AUTH-01…AUTH-10**, Sanctum, session lifetimes, password and login limits |
| [Authorization Standards](./authorization-standards.md) | **AUTHZ-01…AUTHZ-10**, Location/resource/sensitive approvals, revocation and real isolation |
| [Security Standards](./security-standards.md) | **SEC-01…SEC-10**, negative test matrices, privacy/secret and release blockers |
| [Open Decisions & Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 OPEN, BA-16 DEFERRED, BA-30 gate verification |
| [Repository Workflow](../WORKFLOW.md) | Feature/fix → `development`; reviewed `development` → `main` release promotion |

**Precedence:** Explicit approved D-series decisions determine business behavior; OV decisions determine MVP scope; Architecture determines domain ownership/trust/release boundaries; Standards define engineering discipline; reviewed Feature Specs/OpenAPI define exact implementation-specific contracts and expected test results. Surface actual conflicts for an owner decision. Examples below do **not** finalize open Routes, Status Enums, Schema Names, Rate Limits, Checkout/Refund details, service integrations or legal obligations.

### 1.3 Working assumptions and explicit non-goals

- **Backend:** Laravel 13 planned; modular monolith; one Central PostgreSQL DB plus **one physically separate PostgreSQL DB per merchant Tenant**.
- **Frontend:** Next.js separate deploy/repository; approved first-party Sanctum Session/cookie + CSRF via same-origin proxy; Arabic/English and RTL.
- **Async:** Redis queue and Laravel Horizon plus PostgreSQL transactional outbox; jobs are **at-least-once**, not magically exactly-once.
- **Infrastructure:** GitHub Actions → immutable GHCR image digests → authorized Coolify Development/Production operations; initial live topology single VPS, PHP-FPM (not Octane).
- **Conditional validation:** Laravel 13 + `stancl/tenancy v3.10.1`, Spatie Query Builder and other chosen package integrations must actually be proven compatible.
- **Not mandated here:** strict TDD everywhere, universal 90% coverage, mutation testing everywhere, a proprietary test SaaS, enterprise chaos platform, automatic Production deployment, universal SLO/RPS/worker counts, new Backup/Restore product, new production payment/shipping gateways, full mobile app testing, one Feature per physical Module package or a custom testing framework.

### 1.4 Evidence statuses

| Status | Meaning |
| --- | --- |
| **APPROVED** | Test policy agreed, not proof of implementation |
| **TO SPECIFY** | Particular Feature/path/runner/version/threshold still needs a decision |
| **PENDING VALIDATION** | Working behavior/compatibility not yet proven in a real environment |
| **NOT RUN** | Test was omitted, disabled or not executed; **never Passed** |
| **PASSED / FAILED** | Result of actually executed named test against a recorded environment and commit |
| **BLOCKED** | Relevant critical gate failed, absent or is a separately deferred live-Pilot prerequisite |

---

## 2. Approved Testing Decision Register — TEST-01…TEST-10

| ID | Approved Option A | Enforceable direction |
| --- | --- | --- |
| **TEST-01** | Layered Automated Testing + Risk-Based Quality Gates | Run relevant tests for each PR; require real critical Integration tests; extended suites at appropriate release stages |
| **TEST-02** | Pest/PHPUnit + Vitest/React Testing Library + Playwright | Feature-oriented naming/organization; verify actual package/version compatibility in Foundation |
| **TEST-03** | Real PostgreSQL Isolation + Factories + Parallel-Safe Environments | Central + Tenant A + Tenant B, restricted roles, isolated Redis/files/workers; prohibit accidental destructive targets |
| **TEST-04** | Business-Invariant-Driven Testing | Domain/price/discount/stock/order/collection/refund tests with correct money precision, boundaries and negative cases |
| **TEST-05** | Contract-First API + Real Sanctum/Authorization Integration | HTTP/OpenAPI compatibility, Cookie/CSRF/Session, Tenant/Location/resource ownership and revocation |
| **TEST-06** | Real ACID/Concurrency/Idempotency/Outbox Failure Testing | Independent PostgreSQL transactions, post-commit evidence, safe retries, at-least-once consumers and recovery |
| **TEST-07** | Next.js Unit/Component + Playwright Critical Browser E2E | Storefront/Merchant/Platform journeys, same-origin auth, multi-tab, ar/en RTL, responsive and safe failure UX |
| **TEST-08** | Workload-Based Load/Stress/Soak + Measured Capacity | Realistic merchant workloads, P95/P99, shared resources, Tenant fairness, Blue-Green headroom, no invented thresholds |
| **TEST-09** | Progressive GitHub Actions + PR Evidence + Managed Flakiness | Change/risk-based required checks, real reports, critical blockers, controlled quarantine and protected promotion |
| **TEST-10** | Final Consolidated Review + Publication | Cross-reference standards, state pending gates, attach acceptance matrices/checklists and publish this document on `development` |

**Approval scope:** TEST-01…TEST-08 approved separately; TEST-09 (A) and TEST-10 (A) explicitly accepted by the owner together with authorization to publish. **This authorizes documentation publication only**, not implementation, package installation, CI edits, database creation, branch merge or deployment.

---

## 3. TEST-01 — Layered Automated Testing and Risk-Based Selection

### 3.1 Test layers

| Layer | What it proves | Typical trigger |
| --- | --- | --- |
| **Unit** | Isolated pure function/value/rule correctness | Relevant pure-code changes |
| **Domain** | Valid/invalid business transitions, precision and invariants | Any changed owning Domain rule |
| **Laravel Feature/API** | Form Request, controller mapping, guard, policy, response and authorized behavior | API/Feature change |
| **Module Integration** | Real persistence and published Module contract cooperation | Module DB/contract changes |
| **Contract** | OpenAPI, module Public Contract, typed DTO and event schema compatibility | Contract/interface/schema change |
| **Architecture** | No forbidden module dependencies/circular foreign writes | Module structure/contract change |
| **Tenant Isolation** | HTTP/DB/Session/Redis/job/file/export boundaries | Tenancy, identity, permission and sensitive data changes |
| **Concurrency/Idempotency** | Independent competing transactions and once-only effects | Stock, discounts/coupon capacity, money, order transition changes |
| **Queues/Outbox/Fault** | Durable intent, at-least-once consumers, worker cleanup and retry correctness | Outbox/Horizon/integration changes |
| **Frontend Unit/Component** | UI interaction, state and frontend adapter behavior | Next.js feature changes |
| **E2E** | Actual browser → proxy → Next.js/Laravel business journeys | Affected high-value journeys; release validation |
| **Security** | Negative adversarial cases; scoped secrets/CSRF/upload/private files | Sensitive surface/permission changes |
| **Migration/Deployment** | Schema version drift, release overlap, worker drain, compatibility/rollback | DB/release changes |
| **Performance/Recovery** | Representative capacity, resource budgets and tested restore evidence | Significant change and pre-Pilot acceptance |

A real PostgreSQL concurrency test is not replaced by Unit mocks; a real CSRF/browser test is not replaced by a PHP-only session fake; a high-cost Soak Test is not mandated for an isolated spelling correction.

### 3.2 Risk classification, based on the *change*, not merely the module name

| Risk | Examples | Minimum relevant proof |
| --- | --- | --- |
| **Critical** | Tenant isolation, auth bypass, actual stock reservation/dispatch, real Collection/Refund, required Outbox durability | Targeted Unit/Domain + API/Integration + actual concurrent/fault/security tests **before affected merge** |
| **High** | Roles/Locations, private files/exports, Tenant provisioning, schema changes, Session/CSRF | API + negative auth + relevant PostgreSQL/Redis/Browser integration |
| **Medium** | Ordinary Catalog and settings, noncritical business read/write | Unit/Feature + selected auth/contract/DB tests |
| **Low** | Documentation, non-functional UI content, harmless formatting | Appropriate documentation/lint/build or directly affected checks |

A Product feature change affecting authoritative price calculations may be **Critical**, even if unrelated product text edits are Low.

### 3.3 Gate stages

1. **During implementation:** run fast relevant tests and negative scenarios; record unrun steps.
2. **Feature/fix PR:** static checks, owning Feature regression, required contract and risk-selected integration.
3. **Before merge to `development`:** all **applicable Critical/High** checks must pass with real evidence; no “we will test after merge” for material Tenant/financial risk.
4. **Integration/Development release:** broader Laravel/Next.js compatibility, worker and migration validation.
5. **Before `development` → `main` / Production:** affected release suites, separate human approval and release preflight.
6. **Before actual Live Pilot:** ALL applicable **BA-30 G1–G10** evidence; passing CI alone never grants live-data permission.

### 3.4 Acceptance

Every affected Feature has explicit expected successful and unsuccessful outcomes; required test family follows impact, with no blanket heavy-suite requirement for trivial code changes. Cross-Tenant disclosure, unauthorized effect, overselling, duplicate actual financial/stock effect, lost required outbox intent or destructive history rewrite are **blocking**, not warnings.

---

## 4. TEST-02 — Frameworks, Structure and Naming

### 4.1 Framework baseline

| Scope | Adopted tool/style | Condition |
| --- | --- | --- |
| Laravel new tests | **Pest** as default authoring style, using **PHPUnit** as testing foundation | Confirm versions and Laravel 13/PHP compatibility before install |
| Existing/necessary PHPUnit classes | PHPUnit directly permitted | Avoid gratuitous rewrites or duplicate tests |
| Laravel formatting | **Laravel Pint** | PHP code changes |
| Laravel static analysis | **PHPStan/Larastan** | Applicable PHP changes, configured level TO SPECIFY |
| Next.js logic | **Vitest** | Real chosen Next.js/React/TypeScript compatibility |
| Next.js components | **React Testing Library** + Vitest | Behavior/user semantics, not internal component implementation |
| Browser E2E | **Playwright** | Real browser/proxy and approved test environment |
| API contracts | Reviewed **OpenAPI** validation + consumer compatibility tests | Tooling selected in Foundation |
| Security/Dependencies | Risk-proportional Composer/npm/container/CI advisory checks | Exact tooling/versions TO SPECIFY |
| Load | k6 or Artillery **candidate**, not a locked product choice | Evaluate one lightweight tool in Foundation |
| Architecture checks | Selected static dependency/contract rules | No custom full framework required |

If a future package cannot be installed with the selected Laravel 13/PHP or Next.js versions, **record the compatibility failure and approve a supported substitute**, not a silent dependency downgrade or incompatible forced install.

### 4.2 Proposed backend layout — illustrative, not a claim of existing files

~~~text
tests/
  Unit/
    Modules/
      Pricing/DiscountCalculationTest.php
      Inventory/QuantityRulesTest.php
  Feature/
    Modules/
      Orders/ConfirmOrder/ConfirmOrderTest.php
      Customers/CustomerAccessTest.php
  Integration/
    Tenancy/TenantIsolationTest.php
    Inventory/LastUnitConcurrencyTest.php
    Payments/RefundExecutionTest.php
    Queues/OutboxRetryTest.php
  Contracts/
    Orders/OrderResponseContractTest.php
  Architecture/
    ModuleBoundariesTest.php
  Support/
    Factories/
    Fixtures/
~~~

Default to a central `tests/` tree organized **type → Module → Feature**, with test ownership aligned to its business Module. The approved CS-01 sample permits local `app/Modules/.../Features/.../Tests/`, but do **not** duplicate tests in two trees. Final PSR-4 path, bootstrap and namespace registration belong to Foundation. The **16 logical Domains** are not mandatory physical directories.

### 4.3 Proposed frontend layout — illustrative

~~~text
src/
  features/
    catalog/components/ProductCard.tsx
    catalog/components/ProductCard.test.tsx
    checkout/components/CheckoutSummary.tsx
    checkout/components/CheckoutSummary.test.tsx
e2e/
  storefront/guest-checkout.spec.ts
  merchant/confirm-order.spec.ts
  platform/tenant-management.spec.ts
~~~

The Next.js application is independently deployed and may be in a separate repository. Its exact `src/` organization is not imposed on an unseen repo by this backend documentation; use the naming principles and define actual locations in its Foundation setup.

### 4.4 Naming and readability

| Object | Convention | Example |
| --- | --- | --- |
| PHP test file | PascalCase + `Test.php` | `LastUnitConcurrencyTest.php` |
| Pest scenario | Natural behavior statement in English | `it rejects an unreserved order confirmation` |
| Relevant groups | Stable feature/risk tags when useful | `orders`, `critical` |
| Frontend unit/component | `.test.ts` / `.test.tsx` | `PriceSummary.test.tsx` |
| Browser E2E | `.spec.ts` | `guest-checkout.spec.ts` |
| Fixture builder | Business behavior/state and owner | `PendingStockOrderScenario` |

Use explicit Arrange/Act/Assert or Given/When/Then meaning, descriptive expected results and data-driven tests when multiple inputs share one real business rule. Do not assert only on a boolean fixture calculation or the test's own duplicated algorithm; **invoke production-owned behavior** and verify independent expectations.

### 4.5 Test hygiene

- Deterministic, independently runnable tests; no dependency on order or old global state.
- Do not use arbitrary `sleep()` in place of correct synchronization/condition waits.
- Use narrowly scoped mocks for external/future integrations; no mock-only proof of DB locks, grants, CSRF proxy flows, Horizon or actual API contracts.
- Avoid brittle DOM CSS/XPath selectors, oversized snapshot approvals, database IDs hardcoded without a controlled fixture, or a custom abstraction for every trivial test.
- No automatic entire-suite rebuild just to change naming; follow Feature impact and maintenance value.

---

## 5. TEST-03 — Test Database, Fixtures, Factories and Isolation

### 5.1 Minimum meaningful multi-tenant integration environment

~~~text
Isolated test infrastructure (never Production/Shared Development data)
  Central PostgreSQL test DB — Central workforce, Tenant Directory, memberships
  Tenant A PostgreSQL test DB — independent Tenant-local catalog/orders/roles
  Tenant B PostgreSQL test DB — independent Tenant-local catalog/orders/roles
  Scoped test Redis — sessions/rate limits/cache/queue as relevant
  Test Laravel API + actual relevant Stancl/Sanctum integration
  Reusable Horizon workers + isolated test filesystem where relevant
~~~

These are **three separate PostgreSQL databases**, not three mandatory servers. Real relevant tests use **per-Tenant restricted runtime DB roles**, distinct Central role and privileged **test-only setup/migration credentials**. Tenant A and B may each legitimately contain `id=100`; a local numeric PK alone is not an authorization boundary. A single SQLite database with Tenant labels does not prove this architecture.

### 5.2 Hard safety barriers — destructive test operations

- **Never** execute `migrate:fresh`, `db:wipe`, bulk drops or test cleanup on Production, shared Development or an unverified connection.
- Require a testing-only environment, **plus independent positive allowlist checks** for DB host, DB name, role, namespace and test-run ownership. `APP_ENV=testing` alone is insufficient.
- Test setup credentials may provision/drop owned *test-only* DBs; API/worker runtime roles cannot do arbitrary provisioning/DDL, connect to another Tenant or read Production secrets.
- Guard dynamic Tenant connection resolution against accidental fallback to Central or prior Tenant. Fail closed on unknown tenant, wrong DB or missing secret.
- CI has no ordinary Production DB/Redis/Coolify runtime credentials; separate Development and test resource budgets.
- Clean only resources known to belong to the current test job/shard; no unbounded `flushall` or shared volume wipe.

### 5.3 Real migration definitions

Use the reviewed Central and Tenant migration tracks from **DB-01**, including actual constraints, indexes, PostgreSQL types, roles and test setup hooks. Do not invent alternate test migrations that hide real schema incompatibility; the sample `database/migrations/{central,tenant}` is not an approved final path.

Provisioning tests include successful setup, failed bootstrap, repeated execution, missing Role/grant, version drift, migration failure and no premature `Ready`. Privileged migration setup is deliberately separated from normal runtime credentials.

### 5.4 Factories and scenario fixtures

| Tool | Intended purpose |
| --- | --- |
| **Model Factory** | Safe, reproducible rows with valid defaults and explicit traits |
| **Module-owned Scenario Builder** | Assemble a real multi-Module use case with required Public Contracts |
| **Seeder** | Only narrowly required common test reference/registry data |
| **Mock/Fake** | Isolation of a dependency **not** under test, e.g., outgoing email |
| **Fault Injector** | Controlled rollback/crash point in a test-only harness; never Production backdoor |

Required useful cases include Tenant A/B overlapping PKs, valid/invalid Location assignments, expired Session, stale permission cache, last-unit stock, all-items-single-Location, approved/declined discount, duplicate business operation, pending/unresolved Refund, correctly scoped time boundary and actual merchant-local calendar day.

Construct financial facts using **currency-aware integer minor units**, with precise intermediate calculations. Use controlled clock/time travel for time rules rather than altering OS time or arbitrary waiting. Factory values are examples, **not new approved quotas or Enum mappings**.

### 5.5 Isolation mechanism by test type

| Family | Isolation rule |
| --- | --- |
| Pure Unit/Domain | No DB when possible; explicit inputs |
| Simple Laravel Feature DB | Transaction rollback may be used **where safe on the involved connection** |
| Multi-connection Integration | Account for all real connections and Tenant context, not one implicit default transaction |
| Concurrency | Independent connections and real competing committed transactions |
| Horizon/Outbox | Actual commit and visible durable data for the worker/relay; no blanket test wrapper transaction that hides committed records |
| Migration/Provisioning | Dedicated disposable test-only DBs/roles, explicit re-creation |
| File/Export | Per-run temp root, private access tests, robust cleanup |
| Recovery/Fault | Retain required scoped diagnostic artifacts; no unapproved cross-environment writes |

Laravel `RefreshDatabase` or transactions must **not be applied blindly** to a multi-DB/committed-worker test. Validate Laravel 13, Stancl tenancy and runner connection hooks before codifying one universal trait.

### 5.6 Parallel runs and shared services

- Prefer isolation **per CI job / parallel test shard**: Central/TenantA/TenantB DB set, generated schema/test prefixes where appropriate, files, Redis sessions/cache/limiters and queue workers.
- A per-shard DB naming convention and cleanup ownership must prevent one shard deleting another's data.
- True Redis queue transport and Horizon workers must be scoped; Tenant cache prefixes **must not accidentally alter queue transport**.
- Run tests sequentially first, then enable parallel execution after confirming deterministic isolation and measuring PostgreSQL connection/CPU/RAM limits.
- Do not choose a universal parallel worker count without CI capacity measurements.
- At worker teardown, verify that Tenant connection, actor, Location, cache context and job state do not leak across next execution, including exceptions.

### 5.7 Acceptance

The test harness proves Central/Tenant DB separation; restricted roles deny forbidden DB access; no destructive command can target non-test assets; every applicable run cleans its own resources; real concurrence and queue tests actually observe committed state. **No test has been executed just because this strategy is approved.**

---

## 6. TEST-04 — Unit, Domain, Business Rule and Money Testing

### 6.1 Principle: assert business invariants, not just API success

Each owning Module exposes testable rules; test the public behavior and its owning Domain/Action, not private implementation minutiae. Every critical Feature identifies happy, rejected, boundary, state-changing, permission and relevant duplicate/concurrent cases.

| Area | Minimum invariant-driven scenarios |
| --- | --- |
| Pricing/Discount | Correct basis, stacking, rounding and authorized limits |
| Money | Finalized integer minor units; currency context; precise intermediate math and approved rounding |
| Inventory | Correct available/reserved/on-hand relation and **whole order at one eligible Location** |
| Orders | No Confirmed without valid reservation; no implicit Pending Stock confirmation |
| Fulfillment | Authorized handover, packing/hold constraints, once-only stock consumption |
| Collections | Evidence of actual collection, no duplicate execution, distinct eligible partial events remain possible |
| Refunds | Approval vs real payout, limits, pending exposure and no duplicate external execution |
| Membership/Authorization | Role, Location, resource and sensitive delegation invariants |
| Cross-context | Durable setup/status, fail-closed revocation, retry-safe state transitions |

Do not duplicate the production calculation into an “expected result” helper that makes an identical bug pass. Use independently checked fixtures, decision tables and known expected values.

### 6.2 Money and time accuracy

- Finalized Money stored as PostgreSQL `BIGINT` in **currency minor units** with explicit currency; never float as the authoritative final amount.
- Intermediate percent/tax/stacking calculations use precise decimal rules and the **Feature-approved rounding order**.
- EGP in the first Pilot does **not** imply all future currencies use two fractional digits.
- Test zero, invalid negative amounts, threshold-1/threshold/threshold+1, cumulative limits, small-value rounding, historical snapshot stability and large-but-supported bounds.
- UTC stored timestamps and merchant-local calendar day must be separated for daily financial approval limits; test DST/time boundary when applicable to the configured merchant locale.
- Final Enum values/state transitions are Feature-owned; **all project-defined PHP application Enums are int-backed** under CS-05, and DB `SMALLINT` where specified; wire output must match its actual OpenAPI contract.

### 6.3 Manual Discounts — honor D-078…D-088

| Case | Required result |
| --- | --- |
| Applying discount without correct Permission | Denied |
| Role's approval-free threshold exceeded | Independent authorized approval where required |
| Discount requester approves own exceptional request | **Denied** under D-084 |
| Approved exceptional discount | Does **not** automatically confirm or collect the Order |
| Financially material change after approval | Reassess/revoke that approval as D-083 requires |
| Nonfinancial-only change | Does not automatically revoke approval |
| Fixed manual discount | Effective percentage uses **remaining eligible merchandise amount before manual discount**, after earlier eligible stacked discounts |
| Shipping/tax | Excluded from relevant manual discount limit base under D-087/088 |
| Hard combined ceiling | Cannot be bypassed by any approver |

D-085 role-specific approval ceilings and any proposed numbers require actual Feature/business configuration; **do not invent a universal 10/20/30% rule**. D-084 narrow individually delegated approver is permitted where properly authorized; this is **not** general per-user Permission assignment.

### 6.4 Inventory and Orders

- Two valid single-unit requests against one remaining unit do not both confirm.
- Whole multi-line physical order must be fulfillable from **one** eligible Location, not two Location fragments.
- **Manual Pending Stock** has no confirmed reservation, no automatic stock promise, cancellation, customer notification, new refund or auto-confirmation when stock arrives.
- Online COD **manual confirmation** order placement is not confirmed stock reservation; actual confirmation re-evaluates current rules under transaction.
- Cart entry is not stock reservation; **coupon hold vs actual stock reservation are separate lifecycles**. Registered-only coupon redemption and hold/expiry rules follow CW/D-050…D-064.
- Order snapshots preserve agreed historic amounts/tax/discount and are not overwritten by later Product changes.
- Fulfillment On Hold retains legitimate unaffected Reservations, without silently releasing stock.
- Dispatch/Pickup consumes the legitimate physical stock effect **once**; Delivery completion does not subtract again or prove COD settlement.

### 6.5 Collections, Refunds, Direct Sales

- Collection confirms **actual verified event**, never merely a Delivered Order.
- One verified Collection/Refund execution reference cannot be re-recorded as another real effect, even with different HTTP keys or Horizon replay.
- Two genuinely different permitted partial Collections/Refunds for one Order remain possible; do **not** declare all same-order events duplicates.
- Refund cumulative exposure includes applicable pending/unresolved cases without double count; optional role amount and daily cap rules D-126…D-135 use merchant-local day and final authorization time.
- An exposure **alert** threshold under D-135 does not become an automatic hard block; authorization validity is not blindly reset at midnight.
- D-178 correctly authorized external-sale correction **may** be self-approved in specifically approved scenarios; do not impose blanket two-person approval inconsistent with this exception.
- Direct Sale requires approved immediate fully verified collection and handover, one payment method, online connectivity; no newly invented offline checkout or automatic refund.
- Failed/external ambiguous financial effect is reconciled from evidence; never treat provider timeout as verified failure.

### 6.6 Property-based and mutation testing

Data-driven datasets and boundary/property tests are encouraged for real high-value cases. Selective Mutation Testing can reveal weak money/discount tests; **it is not mandatory globally**, and coverage percentage alone cannot demonstrate financial correctness. Do not force TDD as a blanket architecture requirement, but acceptance criteria must ultimately be backed by appropriate tests.

---

## 7. TEST-05 — API, Authentication, Authorization and Contract Tests

### 7.1 Stable contract and HTTP semantics

Validate actual Laravel API Feature responses and OpenAPI against approved AS-02/03/04/08:

| Result | Contract assertion |
| --- | --- |
| `200` | Actual successful read/operation with proper body |
| `201` | Actual resource creation |
| `202` | Accepted **pending** async operation, not false final business success |
| `204` | **No JSON body** |
| `400/401/403/404/409/422/429/500/503` | Correct classification according to Feature/API rules, no fake `200 success=false` |
| Validation `422` | Stable `VALIDATION_FAILED`, camelCase nested field errors |
| Unauthorized `401/403` | Correct guard/permissions with safe information |
| Conflict `409` | Defined business/idempotency conflict, no second effect |
| Rate limit `429` | `RATE_LIMIT_EXCEEDED` + meaningful `Retry-After` + trusted `X-Request-ID` |
| File/download | Correct file headers and current private access, not forced JSON envelope |

Validate `ApiResponse` success/error shape, optional truthful pagination metadata, all app-owned `camelCase` wire keys, stable `errorCode` and language-specific `ar/en` messages. `X-Request-ID` is a trusted per-request header, distinct from Idempotency/Business Operation IDs.

### 7.2 Filters, search and pagination

- Approved `perPage=20` default, **ordinary max 100**; reject malformed, nonpositive or unbounded values.
- Allowed `filter[field]` and `sort=field/-field` only; unknown/invalid sort/filter returns proper `422`.
- Validate SQL identifier allowlists, no injection into arbitrary ordering/column names.
- Apply verified Tenant/Location/Resource scope **before** listing, filtering, related records, counts, search and export.
- `totalItems`/`lastPage` must match real supported pagination mode and authorized data; no fake totals for cursor/simple lists.
- Search includes representative **Arabic/English**, indexing and truthful authorized counts; no assumed Arabic FTS morphology.
- Check JSON/pagination against actual reviewed OpenAPI and supported Next.js consumer; do not silently break active consumers.

### 7.3 Real Sanctum/CSRF and Session testing

Test **same-origin reverse-proxy Next.js + real Laravel Sanctum** in a representative browser; standard Laravel Feature testing may not exercise production CSRF behavior fully. The Session ID stays `Secure`/`HttpOnly`/host-only; only appropriate XSRF token is JS-readable when required. Validate trusted Host/Forwarded headers and a deliberately denied wrong Tenant Host.

| Session | Approved idle / absolute limit |
| --- | --- |
| Platform | **30 min / 8 hours** |
| Merchant | **2 hours / 12 hours** |
| Tenant Customer | **24 hours / 7 days** |

- Correct login regenerates Session ID; Logout and logout-all-devices actually revoke relevant **server-side** Sessions.
- Password change/reset, disabled Central user, revoked Tenant membership and disabled Tenant Customer deny protected new access within their respective scopes.
- Remember Me disabled for MVP (all three user classes); no app MFA for Platform/Merchant/Customer, in line with approved IA/AUTH.
- Password length **8 Unicode characters minimum**, >=64 supported, reject known weak/compromised passwords. Test 7-character rejection, safe 8-character acceptance, Unicode and accidental-trimming risks.
- Employee invite **72 hours**, email verification **24 hours**, password reset token **30 minutes**, one-use/replay-safe with correct identity/Tenant scope.
- Login AUTH-05 thresholds: **5 failures same account+IP, 20 account across IPs, 100 IP across accounts, all over 15 minutes**. Safe temporary handling, NAT fairness and trusted IP; non-Login numeric limits remain TO SPECIFY.
- CSRF invalid/missing token rejects state change, with reviewed Next.js handling of nonstandard Laravel **419** or approved `403`; no blanket CSRF exemptions.

### 7.4 Guard, Tenant, Location and ownership matrices

| Adversarial case | Required observation |
| --- | --- |
| Workforce Session used as Tenant Customer / vice versa | Denied under wrong guard/provider |
| Customer Session of Store A sent to Store B | Denied, no cross-store identity |
| Staff of Tenant A tries Tenant B route/data | Denied without valid B membership |
| Staff belongs to A and B, opens two tabs | Each request uses route-verified Tenant and corresponding current grants; no shared mutable `activeTenant` |
| Staff Role allows view but Location doesn't | No row, count, search, export, file or write from denied Location |
| Platform Admin queries random Tenant business Order | Denied without explicit approved Support/Cross-Context authority |
| Guest uses order number/phone to retrieve private evidence | Denied without approved ownership/proof |
| Role/membership/Location permission revoked mid-session | Next sensitive request denied even with stale positive cache |
| User-initiated export revoked while queued/before download | Recheck and deny data disclosure |
| Redis unavailable with stale grant | Fail closed for current permission-dependent operation |
| Tenant A/B share same Local ID | Resolve with verified Tenant; no wrong-context disclosure |

Audit and special permission cases follow AUTHZ: **single active Tenant Role** by default, narrow D-084 individual discount delegation only, non-self discount approval, correct D-178 correction exception, Owner/Location constraints and entitled-but-not-automatically-authorized features.

### 7.5 Contract compatibility

Run Schema/HTTP/DTO compatibility tests for supported consumers whenever an API response, optional/required field, Enum wire representation, error or pagination mode changes. Keep backend and independently deployed frontend revisions traceable; a Laravel “green” build alone does not prove Next.js compatibility. No speculative `/api/v2` is required solely because a response schema changed.

---

## 8. TEST-06 — Transactions, Real Concurrency, Two-Layer Idempotency and Outbox

### 8.1 Local atomicity tests

Inside a **single verified Tenant PostgreSQL connection**, the owning Application Orchestrator coordinates the Module Public Contracts and one short local transaction:

~~~text
Authorize and validate trusted Tenant/Location/actor
  -> protect mutable current business predicates
  -> Inventory-owning contract reserves all eligible stock
  -> Orders-owning contract confirms and stores snapshots
  -> same-Tenant outbox intent recorded
  -> one COMMIT or complete ROLLBACK
~~~

Failure injection must prove that an invalid order, rejected Reservation, failing snapshot or outbox insert **does not leave a false Confirmed, half-reserved Order or required event published**. Commit success followed by Redis outage must retain the durable event intent. Examine actual PostgreSQL rows and operation identities after transaction completion; HTTP `200`/exception assertions alone are insufficient.

### 8.2 True independent transaction concurrency

Use real **PostgreSQL**, separate connections/processes/transactions, a controlled synchronization barrier and durable after-state assertions; sequential fake calls are not concurrency tests.

| Race | Expected invariant |
| --- | --- |
| Last stock unit, two competing valid orders | At most one full-order Reservation/confirmation, no negative available stock |
| Multi-line order, one unavailable item | No partial authorized Confirmed state |
| Full order split across A/B Locations | Cannot fabricate a combined Location |
| Two concurrent Dispatch/Pickup confirmations | One actual physical stock movement |
| Duplicate Collection reference across requests/jobs | One financial effect |
| Two independent legitimate partial Collections | Both allowed where approved; cumulative/state guard holds |
| Concurrent Refund requests near exposure limit | Correct cumulative/order/local-day/active-exposure checks and authority |
| Concurrent coupon last allowed redemption | Capacity not exceeded; coupon hold is not stock Reservation |
| Concurrent privilege removal and high-risk action | Correct protected authorization boundary; no unauthorized committed effect |
| Concurrent ordinary edits | No prohibited lost update where optimistic guard is specified |

No globally required PostgreSQL `SERIALIZABLE` isolation or Redis lock substitute. Verify actual selected row locks, conditional updates, unique/check constraints and transaction scope per Feature.

### 8.3 Deadlocks, retries and ambiguous results

PostgreSQL `40P01` deadlock and `40001` serialization failure can permit a **bounded classified retry** when the use case is safe; other business conflicts or unknown external outcomes cannot be retried blindly. Confirm rollback, recheck protected conditions and retain the same logical operation reference. Specific retry count/backoff/timeout is **Feature/Queue-owned**, not chosen universally by this Standard.

### 8.4 API versus Business idempotency

| Case | Expected behavior |
| --- | --- |
| Same `Idempotency-Key` + canonical input after completion | Authorized replay/return of prior result; **no new business effect** |
| Same key + different canonical input | **409 Conflict** |
| Same key while original in-flight | Defined pending/conflict; no parallel duplicate effect |
| Different keys for **one actual** Dispatch/Collection/Refund | Owning business identity/unique constraint prevents duplicate |
| Different legitimate partial financial operations for same Order | Allowed under all approved limits; not deduplicated by Order ID alone |
| Two independent equal-looking carts | Not automatically a duplicate purchase |
| Client loses response after possible commit | Inspect durable operation evidence before retry |
| Permission revoked before response replay | No unauthorized disclosure of stored response |
| HTTP dedup retention eventually ends | Expiration cannot permit second real stock/money event |

`X-Request-ID` is diagnostic, `Idempotency-Key` is HTTP retry identity, and stable Business Event/Operation ID is actual once-only effect identity. They are **not interchangeable**. No universal business idempotency engine is automatically authorized.

### 8.5 Transactional Outbox and Horizon failure windows

| Injected failure | Required durable outcome |
| --- | --- |
| Rollback before commit | No committed business effect or corresponding outbox intent |
| PostgreSQL commits, Redis down | Business and outbox intent remain durable; later relay can recover |
| Relay stops before publish | Pending event retained and safely re-discovered |
| Relay publishes then crashes before marking published | Duplicate delivery is possible; Consumer idempotently handles it |
| Two Relay claimers race | No unsafe concurrent claim/effect; state recovered |
| Two Consumers concurrently receive same Event ID | Atomic once-only consumer-specific local effect/proof |
| Duplicate/out-of-order delivery | Current-state/sequence reconciliation without discarding valid late immutable events |
| Worker times out after committing effect | Retry does not reapply money/stock |
| Revoked user-initiated export waits in Queue | Access rechecked and disclosure denied |
| Committed valid system-owned post-commit obligation | Not incorrectly cancelled just because original user Session expired |
| Tenant A → Tenant B → Central through reused Worker | No leaked connection/cache/actor/permissions, including exceptions/retries |

The agreed delivery guarantee is **at-least-once**; a DB processed-event receipt cannot atomically guarantee a third-party email or terminal delivery. Do not claim exactly-once external side effects. Confirm actual external financial result by trustworthy evidence before any repeated action.

### 8.6 Cross-DB and migration workflows

Central and Tenant DBs cannot form one distributed ACID transaction in the approved architecture. Provisioning and membership initialization must record durable stages and resume after partial Central/Tenant commit, without inventing another DB or premature `Ready`/Active status. Revocation denies new access immediately even when cleanup is delayed. Tenant migration tests cover schema version drift, isolated failure, bounded rerun, old/new app overlap and safe continuation without silent success.

---

## 9. TEST-07 — Next.js Frontend, Browser E2E and Experience

### 9.1 Tool responsibilities

- **Vitest + React Testing Library:** pure frontend calculation/presentation, Components, form behavior and accessible interaction.
- **Playwright:** real browser/Next.js/approved proxy/Sanctum flows and critical business journeys against an authorized isolated test Laravel backend.
- **Backend Pest and PostgreSQL:** the authoritative financial, inventory and Tenant truth behind browser-visible outcomes.
- **Human UAT:** complements automation for merchant workflow clarity, accessibility, language and lawful operational completeness; it is not a replacement for critical backend isolation tests.

Avoid asserting implementation details, massive brittle full-page visual snapshots or mocked backend responses as proof of a fully integrated Checkout. Use stable semantic locators first; `data-testid` only where appropriate.

### 9.2 Browser flow coverage by surface

| Surface | Representative required flows when enabled |
| --- | --- |
| Public Storefront | Tenant-correct Catalog, variations/services, cart, address, approved shipping/pickup, guest/registered COD Checkout, authoritative Order result |
| Customer | Tenant-local identity, own Orders, logout/expiry, no cross-shop access, registered-only coupon eligibility |
| Merchant Dashboard | Login, per-Tenant/Location abilities, Catalog, Manual Order, Pending Stock review, confirm, discount approval, packing, dispatch/pickup, Collections/Refunds according to enabled scope |
| Platform | Central admin Login, authorized Provisioning progress, memberships, Plan/Entitlement operations, no blanket Tenant private business read |
| Cross-feature | Critical denial/revocation, session resume, stale data, tenant A/B multi-tab, client retry and error presentation |

### 9.3 Commercial E2E invariants

- **Guest Checkout** does not force a registered Customer profile; guest cannot redeem registered-only coupon.
- A displayed frontend price is not authoritative final commercial pricing. A material change requires approved customer agreement/review (D-108 and Feature rules).
- **Online COD manual-confirmation placement** does not claim full stock reservation. Coupon hold and stock reservation are different.
- Manual Pending Stock availability alerts do not auto-confirm, reserve, cancel, notify customers or issue refunds.
- Proposed alternative Location requires valid whole-order eligibility and customer agreement; **D-201 is OPEN**, and no marker/timer behavior may be invented as already approved.
- Dispatch/Pickup triggers a real once-only physical effect; Delivery does not prove COD collection/remittance.
- Approved Refund is not a verified externally completed refund.
- The first Pilot is COD-first; **no online payment gateway, automatic carrier integration or offline checkout is added by these test cases**.

### 9.4 Browser/session/error/responsive coverage

| Category | Required checks |
| --- | --- |
| Same-origin Session and CSRF | Login bootstrap, cookie attributes, valid/missing/expired token, secure handling of 401/403/419 |
| Multi-tab | A/B Tenant route contexts are independent; no Session-wide active Tenant switch |
| Revocation | UI may briefly show stale capability, but next Backend action denies; 403 does not trigger global Logout |
| Error UX | 422 field messages; 404 privacy; 409 conflict; 429 Retry-After; 500 safe feedback |
| Unknown checkout result | No automatic new-key repeat or false success on timeout |
| Async/loading | No misleading success, missing pending state or stale server response overwrite |
| Localization | Arabic/English translations, RTL, mixed-direction product text, forms, amounts, date display |
| Responsive | Representative mobile/tablet/desktop touch, viewport/scroll and form usability |
| Accessibility | Label/role, keyboard, focus and dialogs as appropriate |
| Browser coverage | Chromium primary; Firefox/WebKit and device-specific checks proportionate to release/real support needs |

Browser viewport simulation ≠ proof of every real device/OS. Use targeted real-device testing if a genuine browser-specific issue requires it. Next.js Async Server Component boundaries may require Playwright E2E rather than relying on direct Vitest unit rendering.

### 9.5 E2E execution reliability

Use isolated test shoppers/merchants, test email provider rather than real customer mail, non-production endpoints, deterministic setup, explicit waits for actual UI/network conditions and no arbitrary sleeps. Capture Playwright trace/screenshot/video when appropriate, **with safe handling of cookies, identities and test artifacts**. E2E Smoke runs on affected PRs; expanded business/Bilingual/cross-browser suites at integration/release gates.

---

## 10. TEST-08 — Performance, Load, Stress, Soak and Capacity

### 10.1 No arbitrary numeric capacity promise

BA-29 requires measurement rather than guessing merchant count, RPS, P95, VPS size, worker count, connection pool, queue SLA or availability. The initial **one-VPS** PHP-FPM/Coolify deployment design has shared CPU/RAM/IO/PostgreSQL/Redis capacity. **Hundreds of merchants as a product ambition are not proof of current host capacity.**

### 10.2 Workload classes

| Class | Profile to design and measure |
| --- | --- |
| Baseline | Low-contention representative UI/API journeys |
| Normal load | Realistic mixed Storefront browse/search, cart, checkout, Dashboard, catalog updates |
| Ramp/Stress | Controlled increase beyond planning load and safe degradation point |
| Spike | Sudden short storefront/checkout increase and recovery |
| Soak | Sustained mixed operation and memory/connection/backlog drift |
| Contention | Last-unit stock, coupon cap, Refund exposure/collection contention |
| Multi-Tenant fair-use | Hot merchant mixed with low-traffic peers; avoid one Tenant starving others |
| Bulk/Background | Import/export, Horizon notifications, Outbox, Scheduler, report generation |
| Fault/degradation | Redis/cache/queue/service interruption, reconnect/fail-secure behavior |
| Blue-Green | Two releases overlapping, migration and worker drain with shared persistent files |

Specify merchant/Location counts, catalog/item sizes, arrival rates, browser versus API composition, transaction mix, authentication sessions and test data lifecycle **in each performance plan**. Generate load outside the measured application host or account explicitly for generator overhead.

### 10.3 Measurement matrix

| Layer | Required representative metrics |
| --- | --- |
| Browser/API | P50/P95/P99, completed business operations per period, error/timeout rates, relevant checkout completion |
| Laravel/Next.js | Route-family latency, PHP-FPM active/busy workers, memory, SSR/API failures |
| PostgreSQL | `pg_stat_activity`, connections, lock wait, deadlock, transaction duration, query count/cost, buffer/IO/temp files |
| SQL/Indexes | Appropriate `EXPLAIN (ANALYZE, BUFFERS)` on controlled representative data and selective indexed query evidence |
| Redis | Cache hit/miss, memory/eviction, latency, rejected writes, sessions/limits/cache interaction |
| Horizon/Outbox | Queue wait, processing, retries/failures, oldest pending outbox, scanner progress, bulk starvation |
| Host/Docker | CPU/RAM/disk IOPS/network, restarts, disk headroom, Blue-Green overlap |
| Business safety | No overselling, false success, duplicated money/stock effect, cross-Tenant leak or lost required event |

Report percentile methodology, operation sample sizes, actual concurrent load, hardware, relevant DB sizes and revision. A “fast HTTP 202” for an indefinitely queued export is not a completed business throughput success.

### 10.4 Cache and shared service comparisons

Measure cold/warm cache and comparable uncached cases; validate PostgreSQL fallback pressure, cache freshness and permission/stock safety. Cache hit rate alone is not a success criterion. Redis memory pressure must not silently compromise Sessions, rate limits or Horizon. Shared PostgreSQL per-Tenant DBs do not isolate server CPU, I/O or connections by themselves.

Heavy exports/imports and Tenant provisioning/migration must not starve protected commerce workloads beyond reviewed capacity/fairness objectives. Do not choose per-merchant Redis/worker/server or PgBouncer automatically.

### 10.5 Capacity acceptance

No universal numeric threshold is adopted in this document. Define **Pilot-specific measured** API and business-operation latency, acceptable error rate, worker/DB/Redis/storage headroom, queue lag, burst recovery and Blue-Green resource budgets. Compare test results against **explicitly approved** targets; if unapproved, report observations as measured findings, not Passed against invented SLOs.

BA-29 scaling priority: optimize actual queries, locks, cache and queue/connection discipline → proportionate vertical VPS scale → independently move services only if measured need → horizontal architecture only after shared storage/tenant routing/lifecycle verification.

Performance regressions are assessed against the impacted workload and documented goals. Business safety and isolation failures remain blockers **regardless of good P95**.

---

## 11. TEST-09 — Progressive CI/CD Quality Gates and Reports

### 11.1 Intended repository workflow — NOT configured by this file

~~~text
feature/* or fix/* from development
  -> Reviewed Pull Request to development
  -> Required scope/risk-selected GitHub Actions checks
  -> Authorized merge to development
  -> Integration/release checks
  -> Approved immutable Laravel / Next.js GHCR image digests
  -> Authorized separate Coolify Development deployment
  -> Release PR development -> main
  -> Independent Production human go/no-go, gate evidence and safe deploy
~~~

`docs/WORKFLOW.md` is a **proposed operational convention**, not proof branch protections, GitHub Actions, environments, GHCR tokens, CI runners or frontend workflow triggers exist. The independent frontend repository needs its own relevant jobs; consuming API changes must be checked against the corresponding reviewed compatible frontend version/artifact.

### 11.2 Applicable PR gate matrix

| Change category | Required baseline |
| --- | --- |
| PHP code | Pint + configured PHPStan/Larastan + affected Pest |
| Laravel Feature/Domain | Unit/Domain/Feature + relevant negative/regression |
| API contract | OpenAPI shape/error/versioning + relevant Next.js consumer compatibility |
| Module boundary/public contract | Architecture dependency checks + provider/consumer tests |
| Tenant/Auth/Session/Location | Central + TenantA + TenantB real permission/isolation; applicable Redis/browser Session |
| Stock/Orders/Financial | Real PostgreSQL atomicity, race, idempotency and negative invariants |
| Outbox/Horizon/Scheduler | Actual queue/worker/Redis, crash/retry/context/duplicate tests |
| New dependency/CI/Dockerfile | Lockfile consistency, supply-chain vulnerability and privileged workflow review |
| Next.js code | ESLint/TS/Vitest/RTL + relevant build and targeted Playwright |
| DB migration | Correct Central/Tenant tracks, constraint/compatibility/recovery tests |
| Shared technical Core | Broader consumers and critical Integration suite; no unsafe path-only selection |
| Documentation only | Relevant format/link/decision-consistency checks, not meaningless full DB load suite |
| Release/Production promotion | Version/digest compatibility, smoke, tenant migrations, Blue-Green headroom, G1–G10 applicable evidence |

**Risk selection is not a magic path filter:** Core/Tenancy/authorization changes can affect many Modules. If reliable impact determination fails, broaden tests; do not treat “no tests selected” as green proof.

### 11.3 Mandatory blockers

The affected PR/release is **BLOCKED** by a real applicable failure proving any of:

- Cross-Tenant data disclosure, denied direct DB grant bypass, Session/Guard leakage or stale privilege after required revocation.
- Unauthorized Role/Location/resource actions, exceptional discount self-approval or actual financial/stock operation.
- Last-unit oversell, Confirmed without required Reservation, duplicate Dispatch/Pickup/Collection/Refund real effect or unsafe unknown-outcome retry.
- Required Outbox intent missing after actual business commit, duplicate critical effect on event replay or corrupt business history.
- Significant exploitable credentials/secret, CSRF/private file issue or critical API contract break.
- Unsafe destructive migration, incompatible overlapping release or another real safety violation covered by BA/TC/SEC blockers.

A test that failed for a legitimate critical behavior cannot be “Quarantined” to bypass the required gate. An unexecuted required test cannot be recorded as Passed.

### 11.4 Report schema and PR evidence

For every required job, record at least:

| Field | Required semantics |
| --- | --- |
| `repository / branch / PR / source SHA` | Exact source under review, not mutable `latest` |
| `suite / tags / cases` | What was selected and why |
| `environment` | Runtime versions, CI job/shard, relevant DB/Redis/Browser setup |
| `result` | Actually **Passed, Failed, Skipped/Not Run, Blocked**; do not collapse |
| `duration` | Actual elapsed test time; useful trend/timeout context |
| `failure details` | Safe error context and exact reproducible command |
| `artifacts` | Scoped logs/report/trace, stored with minimum safe permissions |
| `risk and exceptions` | Named accountable reviewer, reason, expiry and follow-up for permitted noncritical cases |
| `merge / deployment` | Whether these actions were explicitly authorized and actually carried out |

Prefer standard test-report formats (e.g. JUnit XML where supported) and GitHub Checks/Actions artifacts. Artifact retention, report tool and public/private access are Foundation decisions; prevent disclosure of Session Cookies, credentials, sensitive personal/financial records, URL tokens and unauthorized screenshots. A formatting pass or an AI assertion is **not** integration-test evidence.

### 11.5 Flaky test policy

| Situation | Rule |
| --- | --- |
| Fail once, pass on retry | Record both outcomes; investigate, do **not** erase first failure |
| Shared Test DB/Redis/file collisions | Fix test isolation/sharding, not arbitrary repeated reruns |
| Timing-dependent browser/worker test | Replace arbitrary sleep with explicit synchronization/condition checks |
| Suspected CI infrastructure outage | Diagnose and documented controlled rerun |
| Repeated noncritical flakiness | Time-bounded quarantine **only with owner/issue/review**, plus alternate relevant risk evidence |
| Critical Security/Financial/Tenant test flaky | **Treat gate as unverified/blocked** until reliable proof; never silently demote |
| Skipped required test | Explain why, classify blocker, obtain proper action; never change to Passed |
| Removed test after bug fix | Preserve regression coverage where testable; review deletion rationale |

Limited diagnostic retries may be configured with explicit runner policy; **a last green attempt does not prove that the initial red result was irrelevant**. Tool versions, retry limits, static-analysis level, required reviewers and precise branch protection checks remain TO SPECIFY.

### 11.6 Promotion and deployment gate

- A passing PR does **not** automatically authorize merging, creating registry credentials or changing Production.
- After acceptable `development` gates, GitHub Actions may build **immutable GHCR digests**, only in separately authorized implementation.
- Deployment requires real readiness (not only container liveness), safe migrations, old/new compatibility, isolated runtime secrets, worker drain and post-deploy smoke.
- `development` → `main` is a reviewed Promotion PR, and Production requires explicit human go/no-go.
- Never run unapproved real-customer load tests, destructive test cleanup or CI against Production.
- BA-30 Pilot gate evidence is separate from CI green checks.

---

## 12. TEST-10 — Final Reconciliation and Documentation Publication

### 12.1 Cross-document traceability

| Test decision | Governing basis | Distinct implementation responsibility |
| --- | --- | --- |
| TEST-01 | BA-25, MA-08, CS-09, TC-07 | Identify relevant risk suites and progressive gates per Feature |
| TEST-02 | CS-01/09, MA-08, AS-09 | Pest/Next.js/Playwright versions, test layout, naming and runner compatibility |
| TEST-03 | DB-01/09/10, TA, TC-07, AUTHZ-09 | Real Central+two Tenant PostgreSQL test DBs, least-privilege DB roles and safe cleanup |
| TEST-04 | D-series, CW-01…09, CS-04/05, DA/DB-03 | Money/discount/stock/refund/state invariant coverage |
| TEST-05 | AS-01…09, AUTH, AUTHZ, SEC-02/08 | Sanctum/browser/CSRF, location, resource, contract and revocation |
| TEST-06 | TC-01…07, DB-05, QJ-02…06 | ACID/locking, HTTP+business idempotency, outbox, retries, cross-context recovery |
| TEST-07 | AS-08/09, AUTH-04, AUTHZ-08, CW | Next.js buyer/staff/admin E2E, ar/en, RTL, multi-tab and error UX |
| TEST-08 | BA-24/29, INF-11, CR, QJ-07, DB-10 | Workload, percentile, PostgreSQL, Redis, Horizon and shared-VPS capacity evidence |
| TEST-09 | CS-09, INF-07/09, SEC-06, WORKFLOW | GitHub Actions selections, reports, branch protection and safe release promotion |
| TEST-10 | BA-30 G1–G10, current Architecture and Standards | Consolidated checklists, unresolved items and documentation-only publication |

**Reconciliation rules**:
1. **TEST-02** fixes Pest as the default new Laravel test style, while existing API/Code documents' earlier `Pest or PHPUnit` were open choices; PHPUnit remains the underlying engine/compatible option.
2. **TEST-03/06** make real PostgreSQL obligatory where physical Tenant separation, DB locks/grants/transactions or real workers are claimed; this does not imply every Unit Test requires a DB.
3. **TEST-04/05** preserve D-series exceptions and accepted policy, not a generic Finance/Approval workflow invented for test convenience.
4. **TEST-07** does not imply the frontend repository/file layout has been inspected or modified; test plan must match its actual setup.
5. **TEST-08** approves **measurement**, not any specific VPS capacity, latency, RPS, SLO or availability claim.
6. **TEST-09** approves a future CI policy, **not active YAML, Branch Protection, GitHub token provisioning or automated merge/Production deployment**.
7. **BA-16** remains DEFERRED; **BA-30 G3** remains BLOCKED. Testing documentation cannot select disaster recovery design, RPO/RTO or a backup provider.
8. **D-201** remains OPEN; no implicit alternative-Location marker/timer/status acceptance case is approved.
9. All examples are illustrative, not approved final database tables, API endpoints, event schema, status integer values, legal interpretations or production limits.

### 12.2 Traceable Spec Kit and PR handoff

| Artifact | Testing information it must own |
| --- | --- |
| Feature `spec.md` | D/OV/BA/MA references; accepted/rejected behaviors; critical invariants; Tenant/Location/actor scope |
| Feature `plan.md` | Unit/Domain/API/Integration/E2E selection; resources; concurrency and fault injection; contracts; migration/release considerations |
| Feature `tasks.md` | Concrete test implementation, fixture setup, regression work, command and evidence tasks |
| OpenAPI / Module Public Contract | Exact wire DTO/error/Schema/Event types and compatibility cases |
| Implementation PR | Exact source changes, affected modules/consumers, **actual test commands/outcomes**, missing evidence and reviewer decision |
| Integration/release record | Exact image digest/source and compatibility, migration schema inventory, smoke and measured capacity |
| Pilot evidence record | G1–G10 accountable sign-off, legal/recoverability and merchant-specific operational scope |

Create one canonical Feature specification; do not maintain competing independently editable copies. A Feature is not complete because code compiled or an AI-generated summary says Passed.

---

## 13. Consolidated Critical Acceptance Test Matrix

**This is a required *future* set of representative cases when the related Features exist. No test result is claimed.**

| Scenario | Minimum proof | Typical owner | Gate |
| --- | --- | --- | --- |
| Unknown/mismatched Tenant Host/route | Fail closed, no fallback DB | Foundation / Tenancy | G1/G6 |
| Central staff route access to B from A-only membership | Deny, no data/metadata leak | Identity & Access | G1 |
| Distinct Tenant runtime DB roles | DB-level denial across A/B/Central | Tenancy / Database | G1/G6 |
| Same local Order/Product IDs on A and B | Correct scoped resources only | Each owning Module | G1 |
| Customer from Store A Session on Store B | Deny and no cross-guard authority | Customer Auth | G1/G6 |
| Multi-tab staff A/B and revocation | Correct tenant-specific roles; A revoke not B | Authorization | G1/G6 |
| Search/aggregate/export with wrong Location | Deny rows, **counts and files** | API/Reporting | G1/G6 |
| Private file/publication/path attack | Access denied; safe staging and replacement | Storage / owner | G1/G6 |
| User-initiated queued export revoked | Recheck before job and download | Reporting / AuthZ | G6 |
| AUTH Login abuse 5/20/100 over 15m | Correct isolated trusted counters; 429/Retry-After | Auth / Security | G6 |
| Guest Checkout | No forced account; no Guest coupon redemption | Orders / Customers | G8/G2 |
| Cart versus coupon hold versus stock | Distinct lifecycles; correct confirmed redemption | Pricing / Orders | G2 |
| Last capped coupon under concurrency | No oversubscribed allowed coupon use | Pricing | G2 |
| Two last-unit Checkouts concurrently | At most one full Reservation and Confirmed outcome | Orders / Inventory | G2 |
| One Location cannot cover all lines | No full Confirmed / no partial success | Inventory / Orders | G2 |
| Manual Pending Stock stock arrival | No auto-confirmation/reservation/refund | Orders | G2/G8 |
| Manual exceptional discount self-approval | Deny (D-084); D-178 correction exception honored where applicable | Pricing/AuthZ | G2/G6 |
| Outbox insert fails before commit | No order/reservation/outbox partial committed success | Orders / Outbox | G2 |
| Redis fails after commit | Durable outbox intent recovered | Outbox / Horizon | G2/G8 |
| Duplicate/reordered events and worker retry | Consumer once-only effect under at-least-once delivery | Queue / owner | G2 |
| Tenant A→B→Central reused Horizon worker | No connection/actor/cache or file context leakage | Tenancy / Queue | G1/G2 |
| Duplicate Confirm Dispatch / Pickup | Physical stock moved once | Fulfillment / Inventory | G2 |
| Confirm Delivery after Dispatch | No second debit or assumed COD Paid | Fulfillment / Collections | G2/G8 |
| COD received versus courier remitted | Truthful independent evidence/state | Payments/Collections | G2/G8 |
| Duplicate versus distinct partial Collections | Same financial event once; different valid events allowed | Collections | G2 |
| Refund pending/approval versus verified payout | No fake paid refund; unknown external outcome reconciled | Returns / Collections | G2 |
| Refund exposure/local-day limit under competition | Approved D-126…135 caps and reauthorization without replay | Returns / Authorization | G2/G6 |
| Direct Sale paid but cannot handover | No fictitious completed sale; approved reconciliation path | Direct Sales | G2/G8 |
| Multi-Tenant schema drift and batch migration failure | Safe bounded resume, no premature Ready | Provisioning / Migration | G4 |
| Old/new release + worker overlap | Compatible queues/schema/files and safe drain/rollback | Infra / owner | G4 |
| Noisy Tenant / export spikes / Redis/cache failure | Document measured fair use, headroom, safe failures | Infra / Performance | G5 |
| Storefront/Merchant ar/en, RTL and real browser flows | E2E acceptance for enabled journeys | Frontend / Feature | G8 |
| Full + per-Tenant **off-VPS** restore of DB and files | Real recovery proof and approved RPO/RTO, **currently BLOCKED** | Future BA-16 owner | **G3** |
| Egypt merchant sales documents, privacy, customer rights | Applicable reviewed legal evidence | Business/Legal | G7 |
| Dev/Production separation and final launch sign-off | No test data/secrets/resources leaked; named authorization | Ops / Release owner | G9/G10 |

The matrix is an acceptance planning tool. Each actual test must have a Feature-specific expected result from an approved business rule, not a guessed universal numeric ceiling. Initial two-Tenant proof is necessary for isolation but **does not prove capacity for hundreds of Tenants**.

---

## 14. Practical Testing and PR Checklist

### Business and scope

- [ ] Read approved business decisions, target Feature Spec, changed Modules, Public Contracts, Tenant/Location scope and open items.
- [ ] Record affected Critical/High/Medium/Low risks and relevant positive/negative/boundary/concurrent cases.
- [ ] Use real owned rules for money, discounts, stock, order lifecycle, external receipts and Refund state; no invented numeric defaults.

### Test design

- [ ] Use Pest + PHPUnit Foundation for new Laravel cases; Vitest/RTL and Playwright for applicable Next.js tests.
- [ ] Tests are isolated, deterministic, independently runnable and behavior-named; data/expected values are independently correct.
- [ ] Use appropriate Factories and Scenario Builders, not Production data or fragile assumed IDs.
- [ ] Real Central + Tenant A + Tenant B PostgreSQL and restricted roles for critical Tenant/DB assertions.
- [ ] Test-only allowlists prevent destructive operations on shared Development/Production.
- [ ] Separate true committed/concurrent/worker tests from same-connection rollback-only tests.
- [ ] Redis/Horizon/Outbox/file scopes are isolated when they participate in the test.

### Security and consistency

- [ ] Actual HTTP/OpenAPI Status, envelope, ar/en, camelCase, validated filters/metadata and consumer compatibility.
- [ ] Real CSRF/host-only Session/guards/Location/Resource authorization, revocation and multi-tab tests where affected.
- [ ] PostgreSQL constraints/locking, last-unit competition, full-order atomicity and no duplicate real effect where affected.
- [ ] HTTP idempotency and durable owning Business Operation dedup both tested; permitted partial financial events remain possible.
- [ ] Outbox commit, Redis outage, publish-crash, consumer duplicate/out-of-order/retry and Tenant worker cleanup tested where affected.
- [ ] Private files/exports, historical evidence, audit and security logs do not leak secrets or unauthorized PII.

### CI, release and evidence

- [ ] Actual selected tests were executed on a recorded commit/environment; not-run/skipped are not misreported as Passed.
- [ ] Formatting/static analysis/dependency/architecture checks applied as required.
- [ ] Relevant Concurrency, Tenant and Critical Security failures **block** affected merge.
- [ ] Flaky cases investigated; no critical quarantine/“retry until green” bypass.
- [ ] PR records source SHA, commands, tests/results/artifacts, risk, missing evidence, reviewer and deployment/migration effect.
- [ ] Production promotion/merchant Pilot is authorized separately; **BA-30 G3 is still BLOCKED** until BA-16 recovery strategy and tested restore.

---

## 15. BA-30 Live Pilot Readiness Gates — Not Satisfied by This File

| Gate | Required proof | Current source-backed status |
| --- | --- | --- |
| **G1 — Tenant safety** | HTTP/admin/worker/file/cache/export/search isolation, permissions and DB runtime privilege denial | **NOT VERIFIED** |
| **G2 — Commerce consistency** | Concurrency, idempotency, protected full reservations, collections/refunds and outbox durability | **NOT VERIFIED** |
| **G3 — Backup and restore** | **Explicit BA-16 approval**, off-VPS recoverable Central + all Tenant DBs + files, full/individual Tenant restore drills and measured/approved RPO/RTO | **BLOCKED — BA-16 DEFERRED** |
| **G4 — Releases** | Actual GitHub Actions/GHCR, compatible migrations, Blue-Green headroom, readiness, drain and rollback | **NOT VERIFIED** |
| **G5 — Capacity/NFRs** | Representative load, measured thresholds, appropriate alerts and resource/queue capacity | **PENDING VALIDATION** |
| **G6 — Security & privacy** | Auth, authorization, CSRF/cookies/TLS, secrets, files, audit and incident process | **NOT VERIFIED** |
| **G7 — Legal readiness** | Egypt merchant/channel-specific tax, invoicing/e-receipt, privacy, sales documents and customer rights review | **LEGAL REVIEW REQUIRED** |
| **G8 — Operational completeness** | Real E2E/UAT for enabled commercial and post-sale journeys, bilingual workflows/support | **PENDING FEATURE ACCEPTANCE** |
| **G9 — Environment isolation** | Real Development/Production separation of DB/Redis/files/secrets/deployment resources | **NOT VERIFIED** |
| **G10 — Explicit release sign-off** | Named responsible owner, merchant/feature scoped release record and stop/rollback criteria | **APPROVAL REQUIRED** |

The architecture permits documentation and non-production engineering while BA-16 remains deferred. **It does not permit entrusting live merchant/customer records to an unverified deployment.** This Testing Standard cannot select or implement a Backup/Restore system, nor claim an RPO/RTO.

---

## 16. Pending Implementation Decisions and Foundation Handoff

| Topic | Status / correct next step |
| --- | --- |
| Laravel 13 + `stancl/tenancy v3.10.1` + Sessions/Queue/File and selected PHP version | **PENDING REAL COMPATIBILITY VALIDATION** |
| Test runner and dependency versions (Pest/PHPUnit/Vitest/RTL/Playwright) | **TO INSTALL/VERIFY IN FOUNDATION**, not created here |
| Exact test tree, PSR-4, Pest bootstrap, Laravel test hooks | **TO SPECIFY**, using TEST-02 conventions |
| Actual Central/Tenant test DB names, ephemeral DB provisioning and destructive-operation allowlists | **TO IMPLEMENT SAFELY**, verify against shared/Production risk |
| Restricted test DB runtime roles and migration/setup grant separation | **TO IMPLEMENT AND TEST** |
| Concurrent-process harness and deterministic fault-injection implementation | **TO DESIGN/VERIFY** for affected Features |
| Redis namespace/queue transport/session store and Horizon worker isolation | **TO VALIDATE IN REAL ENVIRONMENT** |
| Frontend repo location, Next.js version, actual E2E/consumer CI interface | **TO DISCOVER/CONFIGURE** in frontend Foundation |
| Browser matrix, device support, accessibility thresholds, snapshot policy | **FEATURE/RELEASE-OWNED** |
| Load test tool (k6/Artillery or approved equivalent), workload mix and measured SLO | **TO SELECT/MEASURE**; no fabricated capacity |
| GitHub Actions YAML, branch protection, static-analysis level, review count, retry count, artifact retention | **TO SPECIFY/CONFIGURE** with actual repo capabilities and security |
| CI test report adapter, cross-repo API consumer trigger and deployment approval mechanism | **PENDING IMPLEMENTATION/VALIDATION** |
| Source D-201 alternative-Location follow-up choice | **OPEN**; no test may assert an invented resolution |
| BA-16 recoverability architecture, provider, backup cadence and RPO/RTO | **DEFERRED**; G3 remains blocked for live Pilot |
| Legal, privacy, real merchant tax/document obligations | **LEGAL/FEATURE OWNER REVIEW REQUIRED**, not a test framework choice |

**Recommended implementation handoff order** (not a new deployment authorization): Foundation validates package compatibility → setup safe three-DB/Redis test harness and Test Factories → implement Pest baseline and API/contract tests → add critical Tenant, financial concurrency and outbox failure suites alongside owning Features → configure GitHub Actions/reporting/branch controls → integrate Next.js Vitest/Playwright E2E → measure workloads and complete all release gates. Do not enable test code against Production simply because a script has a `--force` flag.

---

## 17. Final Approved Position and Publication Boundary

**TEST-01…TEST-10 — Option A APPROVED.** This single consolidated document is authorized for publication on **`development`** at `docs/02-standards/testing-standards.md`.

The project commits to **realistic, risk-based, repeatable, Multi-Tenant-aware tests** and actual evidence—not synthetic pass reports or uniform test quotas. Engineering teams and AI agents use this Standard when implementing each authorized Feature, but must not treat document publication as actual package installation, passing CI, safe code, configured branch protection, finished security, legal compliance or permission to go live.

**End of Testing Standards — Version 1.0.**
