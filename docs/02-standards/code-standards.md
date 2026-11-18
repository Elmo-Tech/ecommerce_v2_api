# E-commerce SaaS V2 — Code Standards

**Version:** 1.0 — Approved Engineering Standards Baseline  
**Date:** 2026-11-19  
**Repository:** Elmo-Tech/ecommerce_v2_api  
**Branch:** development  
**Path:** docs/02-standards/code-standards.md  
**Approval:** **CS-01 through CS-11 APPROVED; CS-12 Option A APPROVED for comprehensive documentation and publication**  
**Implementation:** **DOCUMENTATION ONLY — NOT IMPLEMENTED / NOT VERIFIED / NOT DEPLOYED**

> **Reading rule:** The approved standards are instructions for **future Laravel code**, not a claim that a running application, Composer dependencies, GitHub Actions, configured branch protection, tests, production database, or deployment already exists. This repository is documentation-only at the time of publication. No implementation or Coolify deployment is authorized by this file. **D-201 remains OPEN, BA-16 Backup & Restore remains DEFERRED, BA-30 G3 remains BLOCKED for a live Pilot.**

---

## 1. Purpose, Scope, Authority and Precedence

### 1.1 Purpose

Standardize how Developers and AI Agents produce maintainable, testable, secure **Laravel 13 API-first Modular Monolith** code for E-commerce SaaS V2, with **PostgreSQL Central + isolated Tenant databases**, Next.js frontend, Redis/Horizon and tenant-local Transactional Outbox.

Goals:

1. Keep business logic in the correct owner with **One Writer Principle** and narrow Public Contracts.
2. Preserve existing D-series commercial rules, approved MVP boundaries, and workflow invariants.
3. Prefer native Laravel/PHP mechanisms and simple justified abstractions over framework proliferation.
4. Make PHP source, DTOs, Enums, errors, dependencies, logs and PRs consistent.
5. Require honest evidence for formatting, testing, concurrency, security and AI changes.
6. Avoid silent business, architecture, schema, permission or deployment decisions.

### 1.2 Normative sources

| Reference | Source authority |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | Approved product/MVP boundary and Pilot readiness |
| [Business Decision Register D-152 onward](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) | Binding detailed business behavior |
| [Handoff v5.48](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) | Approved earlier D-series decisions and business context |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | Unresolved decisions such as D-201 |
| [Backend Architecture](../01-architecture/backend-architecture.md) | BA-01…BA-30, Laravel/runtime/quality/release architecture |
| [Module Architecture](../01-architecture/module-architecture.md) | MA-01…MA-09, features, public contracts, data ownership, module registry |
| [API Architecture](../01-architecture/api-architecture.md) | API-01…API-08, ApiResponse, Form Requests, OpenAPI and errors |
| [Data Architecture](../01-architecture/data-architecture.md) | DA-01…DA-07, PostgreSQL, money, time, constraints and historical data |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | Verified identity, membership, roles, permissions and Location scopes |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Central/Tenant context, isolation and conditional stancl integration |
| [Transaction Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | TC-01…TC-08, ACID, locking, idempotency, Outbox, recovery |
| [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | QJ-01…QJ-08, tenant-aware Horizon, retries and delivery |
| [Commerce Workflow Architecture](../01-architecture/commerce-workflow-architecture.md) | CW-01…CW-09, business operation ownership and invariants |
| [Integration & Notifications Architecture](../01-architecture/integration-architecture.md) | Approved notification and external adapter boundaries |
| [Repository Workflow](../WORKFLOW.md) | Proposed feature/fix PR workflow; not proof of configured CI |

**Decision hierarchy:** Detailed approved D-series rules govern business behavior; OV governs MVP scope; architecture records govern technical boundaries; CS records how to code consistently within those approvals. Unapproved Feature details must not be guessed. Explicit user approval is needed for scope, architecture, Public Contract, persisted Enum, business permission, critical schema or deployment changes.

### 1.3 What this document owns

**Owns:** internal module/code organization, Actions, Domain Services, Eloquent/Repository rules, DTOs/Value Objects, integer-backed Enums, exceptions, dependency injection, naming/types, PR quality, structured logging and AI conduct.

**Does not replace:** dedicated API, Security, Authentication, Authorization, Database, Transaction/Idempotency, Money Calculation, Tenancy, Testing, Queue, Integration, Localization, File Storage or Audit/Observability standards. Their detailed implementation choices remain subject to separate approved specifications.

**Status terms:** APPROVED = coding convention agreed; TO SPECIFY = exact feature choice pending; PENDING VALIDATION = technology chosen in principle but not executed/verified; NOT IMPLEMENTED = no runnable artifact; DEFERRED = postponed business/architecture scope.

---

## 2. Final Approved CS Decision Register

| ID | Approved choice | Binding rule |
| --- | --- | --- |
| **CS-01** | **A — Pragmatic Modular Architecture** | Feature-first organization, lightweight HTTP/Application/Domain/Infrastructure boundaries when useful; no empty boilerplate layers. |
| **CS-02** | **A — Eloquent-First + Selective Repositories** | Own-module Eloquent is allowed; Repository only when justified, no one-Repository-per-Model mandate. |
| **CS-03** | **A — Use-Case Actions + Focused Domain Services** | One meaningful business use case per Action; thin Controller; reusable domain rules in focused services. |
| **CS-04** | **A — Typed DTOs at Boundaries + Selective Value Objects** | Provider-owned public contracts use explicit types, not foreign Models or raw HTTP objects. |
| **CS-05** | **Owner direction — int-backed PHP Enums + compact PostgreSQL types** | All project-defined PHP Enums are backed by int; SMALLINT for enum state, BOOLEAN for genuine true/false flags; PostgreSQL has no TINYINT. |
| **CS-06** | **A — Typed Domain Exceptions + Centralized Handling** | Laravel-native validation/auth, typed meaningful errors, centralized mapping to approved ApiResponse. |
| **CS-07** | **A — Explicit DI + Selective Facades** | Constructor/Method Injection, interfaces at justified boundaries, no global mutable Tenant/User state. |
| **CS-08** | **A — Naming + PSR-12 + Strong Typing** | Consistent names, strict_types, appropriate return/property types, Pint and targeted static analysis. |
| **CS-09** | **A — Automated Quality Gates + Risk-Based Tests + PR Review** | Formatting, static analysis, applicable contract/security/DB tests and review evidence before merge. |
| **CS-10** | **A — Structured Logging + Correlation + Separate Audit** | Safe log context, distinguished request/operation IDs, Tenant-aware business audit. |
| **CS-11** | **A — Spec-Driven AI Development + Controlled Autonomy** | Follow actual approved Specs, restrict scope, verify work, escalate sensitive design changes. |
| **CS-12** | **A — Comprehensive single-file Standard** | Review against approved Architecture and publish this document on development only. |

The CS-05 preference is explicitly interpreted for **PostgreSQL**: there is **no native TINYINT** type to select. No decision here mandates a new custom Laravel framework or automatic production action.

---

## 3. Module Structure and Code Boundaries — CS-01

### 3.1 Required architectural boundaries

- The backend remains **one Laravel deployment** with disciplined business modules. The approved **16 logical business domains** are **not** a mandatory number of physical directories/packages.
- Organize new code by **business Module and Feature**, keeping related business use cases discoverable.
- Each Module is the **only writer** for its own authoritative persistence. No direct foreign Module writes, regardless of speed or convenience.
- Reuse **published provider-owned Public Contracts** for in-process synchronous critical interactions; use **Outbox/events** for recoverable post-commit work.
- Use the approved **Explicit Module Registry and Module Service Providers** (MA-06) for routes, interfaces, policies and listeners; no unrestricted auto-discovery.
- Do not create circular dependencies. Resolve with a narrow contract and an owning application orchestrator; extract genuinely shared technical primitives only when domain-neutral.
- Shared Core/Kernel remains small: Tenancy, API response infrastructure, security primitives, database connection handling, Outbox, storage, safe observability and appropriate common value primitives.
- Establish whether the operation is **Central-Only**, **Tenant-Only** or an authorized **Cross-Context Orchestrator**. Do not assume Central and Tenant databases share one ACID transaction.

### 3.2 Illustrative file layout — not a mandated directory tree

~~~text
app/
  Core/
    Modules/
    API/
    Tenancy/
    Security/
    Outbox/
    Observability/
  Modules/
    Orders/
      Contracts/
      Domain/
        Rules/
        Services/
        ValueObjects/
      Features/
        ConfirmOrder/
          ConfirmOrderAction.php
          ConfirmOrderData.php
          ConfirmOrderController.php
          ConfirmOrderRequest.php
          ConfirmOrderResource.php
          Tests/
      Infrastructure/
        Persistence/
      Routes/
      OrdersServiceProvider.php
    Inventory/
      Contracts/
      Domain/
      Features/
        StockReservations/
      Infrastructure/
      Routes/
      InventoryServiceProvider.php
~~~

No empty directories or redundant class layers must be generated just to match this example. The actual namespaces, PSR-4 mapping and app bootstrap are Foundation implementation decisions. Tests may be placed in the repository's agreed test tree rather than within Features if that is clearer.

### 3.3 Layer responsibilities

| Layer | Primary ownership | Must not do |
| --- | --- | --- |
| **HTTP / Delivery** | Controllers, Form Requests, API Resources, route adapters | Business-critical inventory/payment decisions |
| **Application** | Feature Actions, business orchestration, transaction coordination, Public Contract calls | Direct mutation of another Module's data |
| **Domain** | Invariants, rules, small reusable Domain Services, Value Objects | Import HTTP Requests/Controllers or depend on third-party transport |
| **Contracts** | Published interfaces, typed inputs/results, applicable error/lifecycle guarantees | Expose internal Eloquent Models, private Actions or DB query builders |
| **Infrastructure** | Module-owned Eloquent/persistence, adapters and external integrations | Overrule module ownership or create unauthorized success |

**Rule:** A dedicated Action, DTO, Service or Repository is required only where it makes a meaningful boundary, preserves invariants or simplifies real complexity.

---

## 4. Eloquent, Query Discipline and Repositories — CS-02

### 4.1 Eloquent-First

Use Laravel Eloquent **inside the owning Module** when it is the simplest correct persistence mechanism. Normal Eloquent Models, relations, scopes and appropriately bounded queries are acceptable; **no mandatory Repository interface for every table**.

A Model being readable does **not** authorize cross-Tenant/Location access or another Module's direct write.

| Example | Verdict |
| --- | --- |
| Orders Action updates an Orders-owned row under authorized Tenant transaction | **Allowed** |
| Inventory Action reserves its own stock using PostgreSQL conditional updates/locks | **Allowed** |
| Orders Action calls Inventory's published reservation contract | **Correct cross-module boundary** |
| Orders Action imports Stock Model and decrements stock directly | **Forbidden** |
| Authorized Reporting consumes an explicitly reviewed read projection | **Allowed when approved and properly scoped** |

### 4.2 When a Repository is worth creating

A Repository/query adapter may be justified by complex reusable persistent queries, a stable persistence isolation boundary, multiple real data sources, measurable query optimization or a genuinely useful test seam.

**Avoid** an abstract BaseRepository, IRepository and concrete repository wrapping every one-line Eloquent operation. A general repository framework must not bypass genuine PostgreSQL behavior, Tenant isolation or business permissions.

### 4.3 Data access correctness

- All queries use the validated Tenant or Central context. Deny access on invalid scope; never silently fall back to an old Tenant connection.
- Use eager loading and bounded pagination to prevent N+1/unbounded memory when appropriate.
- Apply only reviewed indexes and optimizations based on observed access/query performance.
- For last-unit stock, refunds, collections, coupon limits and critical statuses, use **TC-approved PostgreSQL locks, conditional writes and unique constraints**. A check-then-write outside the lock/transaction is insufficient.
- Redis cache is not authoritative for inventory or financial facts.
- **Final money** uses PostgreSQL **BIGINT in currency minor units**, not float, under DA-04; intermediate calculations use appropriately precise decimal arithmetic.

---

## 5. Use-Case Actions, Controllers and Domain Services — CS-03

### 5.1 Application Actions

An Action represents **one meaningful use case**. Examples: ConfirmOrderAction, RecordCollectionAction, MarkOrderDispatchedAction, ReleaseFulfillmentHoldAction and ApproveReturnAction.

The owning Action coordinates the real dependencies: trusted actor/Tenant/Location, current applicable business rules, permission, current price/stock evidence, correct transaction, module public contracts and a typed success/failure result.

Critical local writes that must succeed/fail together execute in **one short transaction on the correct validated Tenant connection**. The called module remains responsible for its own writes and guards. A contract must not independently commit a partial success.

Do **not** hold a DB transaction across network I/O, physical delivery, cashier interaction, long-running queue jobs, email or an external payment terminal. Unknown real external outcomes require evidence-based reconciliation.

### 5.2 Focused Domain Services

Use a Domain Service for **genuinely reusable business rules** that do not naturally fit one entity/Action. Do not create a giant OrderService/God Service containing unrelated operations. Avoid chains of Services delegating one line each without clear value.

### 5.3 HTTP vs Application flow

~~~text
HTTP Request
  -> Trusted Tenant/Actor/Location middleware
  -> Form Request (shape + preliminary authorization)
  -> ConfirmOrderAction
       -> Pricing public contract (approved totals)
       -> Inventory public contract (whole eligible Location)
       -> Orders-owned state + Outbox in correct Tenant transaction
  -> API Resource + approved ApiResponse
~~~

This is a **conceptual example, not an approved endpoint or class signature**. Protect the actual business predicate during finalization: a Form Request having passed does not mean stock, membership, customer agreement or authorization can never change.

### 5.4 Core business invariants

- **Online COD Manual Confirmation** does not finalize stock reservation at order placement.
- **Manual Pending Stock** remains unconfirmed/unreserved; authorized staff performs confirmation after fresh review.
- **Single eligible Location** must satisfy the whole physical order; never fabricate capacity by summing different branches.
- **Direct Sale** requires full verified collection and actual immediate handover, not an unverified Pending terminal result.
- **Dispatch/Pickup** consumes corresponding stock only once; later Delivery does not consume it twice.
- **Delivered/Completed is not COD Settled.**
- **Return Approved does not mean Restocked; Refund Approved does not prove actual money returned.**
- **Email/outbox failure after commit does not undo commercial history.**

See CW Architecture and approved D-series for all precise conditions; no new business status machine is invented here.

---

## 6. DTOs, Value Objects and Type-Safe Module Communication — CS-04

### 6.1 Public Contract types

Each provider Module owns the **Public Contract and its DTOs / immutable value types**. These contracts may carry scalar IDs, currency-aware amounts, typed collections and stable business operation references. They must describe validation/authorization, transaction expectations, possible conflicts and idempotency assumptions.

Do not export raw HTTP Request/Response/FormRequest, an internal Eloquent Model, a private DB Builder, a third-party payload or a mutable service object across a Public Contract boundary.

~~~php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\Data;

final readonly class ReserveStockLineData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
    ) {}
}
~~~

~~~php
<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts\Data;

final readonly class ReserveStockData
{
    /** @param list<ReserveStockLineData> $lines */
    public function __construct(
        public int $orderId,
        public int $locationId,
        public array $lines,
        public string $operationId,
    ) {}
}
~~~

**Illustrative only:** exact reservation fields, inventory unit semantics, IDs and public signatures are **TO SPECIFY** in the Inventory Feature. The reference does not grant permission or replace domain idempotency.

### 6.2 When to use each type

| Representation | Preferred use | Avoid |
| --- | --- | --- |
| **DTO** | Nontrivial typed inputs/results across App/Module boundaries | Unvalidated deeply nested contract Arrays |
| **Value Object** | Meaningful invariants/behavior such as Money or a validated reference | Wrapper for every scalar without a rule |
| **Integer-backed Enum** | Stable closed project state/code values | Magic status strings/numbers scattered in Actions |
| **Local Array** | Small, obvious temporary data strictly within a Feature | Critical stock/money public contract schema |
| **Eloquent Model** | Owning Module persistence | Exposing internal Model across Public Contract boundary |
| **API Resource** | Explicit camelCase JSON mapping | Owning financial/stock business decisions |

Prefer first-party PHP readonly DTOs; a third-party DTO package is **not mandatory**. Add PHPDoc shape/list generics where PHP native typing lacks collection generics.

### 6.3 Money, time and context

- Store **finalized Money** as integer **minor units in BIGINT**, with explicit currency; do not calculate money using float.
- Do not assume all future currencies have the same decimal exponent.
- Use accurate decimal operations for intermediate tax/discount/pricing and approved rounding before persistence.
- Store authoritative timestamps in UTC; apply Merchant-local calendar day for features explicitly requiring it (for example refund authorization daily caps).
- Resource identifiers are scoped to the validated Tenant/Central context; IDs alone do not authorize access.
- DTO immutability is preferred for published input snapshots, but does not by itself prove correctness of underlying domain values.

---

## 7. Integer Enums and PostgreSQL Types — CS-05

### 7.1 Mandatory owner preference

**Every project-defined PHP Enum used for application statuses/domain codes MUST be backed by int.** Never choose string-backed or non-backed Enums as an alternative project style. All case integers are explicit and stable; never repurpose an old persisted number.

~~~php
<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Enums;

// Example only; not the finalized Orders state machine.
enum ExampleOrderStatus: int
{
    case Pending = 1;
    case Confirmed = 2;
    case Cancelled = 3;
}
~~~

The actual state names/values are **Feature-owned decisions**. The numbers above are illustrative and must not be treated as production assignments.

### 7.2 PostgreSQL has no TINYINT

PostgreSQL has **no native TINYINT** type. Therefore for the approved compact representation:

- **Enumerated project state:** PostgreSQL **SMALLINT**, created explicitly with Laravel **smallInteger()**. Standard 2-byte signed range: **-32768 to 32767**.
- **Real yes/no flag:** PostgreSQL **BOOLEAN**, created with Laravel **boolean()**.
- A meaningful multi-state lifecycle is **not** a boolean; an ordinary true/false flag need not be a fabricated Enum.
- A type genuinely unable to fit SMALLINT range requires explicit schema/contract review before change, rather than an unnoticed incompatible type.

~~~php
// Illustrative Laravel migration
$table->smallInteger('status')->default(1);
$table->boolean('is_active')->default(true);
~~~

~~~php
// Illustrative Eloquent casting
protected function casts(): array
{
    return [
        'status' => ExampleOrderStatus::class,
        'is_active' => 'boolean',
    ];
}
~~~

### 7.3 Safe evolution rules

1. Explicit case values, no reliance on declaration order or arithmetic increments.
2. Never renumber existing cases or reuse retired numbers for different meanings.
3. Use appropriate DB CHECK/other enforced validation for approved state sets and validate inputs at domain/API boundaries.
4. Case changes need migrations and compatibility checks for existing data, filters, reports, events, cache, API mappings, workers, fixtures and older application releases.
5. No silent new enum state at DB level without permitted transition semantics or migration plan.
6. No magic numbers in business code; reference Enum cases or reviewed mapper values.
7. If the API contract or an external provider uses **string status values**, map them explicitly at the adapter/Resource boundary; the **internal Enum remains int-backed**. Do not break approved API wire contracts just to enforce internal storage style.
8. Handle unknown provider enum-like values intentionally, not by guessing a success state.

**CS-05 applies to project domain Enums, not to literal third-party strings or standard HTTP header names.** The API Architecture's approved HttpStatusCode-style enum is naturally an integer status code.

---

## 8. Exceptions, Validation and Unified Responses — CS-06

### 8.1 Framework-native handling

Use Laravel **Form Requests** and built-in validation, authentication and authorization handling. Meaningful business/domain errors may have precise typed Exceptions or explicit outcome types when justified. A **centralized exception mapper** translates them into the approved API response envelope, rather than Controller-specific repeated catch-and-return blocks.

The approved response pattern is **App\Helpers\ApiResponse** and the **App\Enums\HttpStatusCode** direction from API Architecture. Do not introduce a competing global JSON response framework, speculative error catalog or a different Exception handler for every Module.

### 8.2 Error classes

| Error | Handling |
| --- | --- |
| Validation/data shape | Laravel Form Request, localized field errors (normally HTTP 422) |
| Authentication | Native guard errors and safe HTTP status |
| Authorization/Tenant/Location | Deny without resource existence disclosure when necessary |
| Business conflict | Stable public errorCode, correct HTTP conflict/state response, no blind retry |
| Proven rolled-back transient failure | Bounded retry only when safe under TC/QJ |
| Unknown external/possibly committed effect | Reconcile actual authoritative evidence before replay |
| Unexpected system failure | Generic safe client response, structured diagnostics; never leak secrets or stack traces |

The API contract uses **camelCase** request/response/error keys, localized **Arabic/English** messages, **X-Request-ID in HTTP headers**, and correct HTTP statuses. Ordinary success: success/message/data, optional meta. Failure: success=false/message/errorCode/errors. HTTP 204 has **no JSON body**; file/download responses are not wrapped.

### 8.3 Exception code hygiene

Do not create a class per simple validation error. A precise InsufficientStockException may be useful if reused and contractually meaningful. Do not catch Throwable to conceal a failed payment/stock effect, return false, issue success or publish a success notification.

Local try/catch is justified for context, cleanup, documented safe recovery or translation before a rethrow. The owning application must still recheck Tenant scope, actual financial/stock state and permission at the sensitive operation boundary.

---

## 9. Dependency Injection, Facades and Runtime Lifecycles — CS-07

### 9.1 Default

Use **Constructor Injection** for stable required dependencies and **Method Injection** for call-specific dependencies. Rely on Laravel Service Container for reviewed bindings. Prefer narrow provider-owned interfaces at Module boundaries and adapter seams; avoid an Interface for every trivial service.

~~~php
<?php

declare(strict_types=1);

// Conceptual only; final imports and signatures are Feature-owned.
final class ConfirmOrderAction
{
    public function __construct(
        private readonly InventoryReservationContract $inventory,
        private readonly OrderPricingContract $pricing,
    ) {}

    public function execute(ConfirmOrderData $data): void
    {
        // Perform verified checks and a short validated Tenant transaction.
        // Call owner contracts; do not write another Module's tables.
    }
}
~~~

Using the **DB**, **Log**, **Cache** or **Storage** Laravel Facades is acceptable where they clearly represent infrastructure and remain correctly scoped. Pure static utility operations may be used when truly stateless. Avoid hidden service-locator calls scattered through domain code, business state in globals, arbitrary static mutable registries or a custom dependency/container framework.

### 9.2 PHP-FPM first, Octane-ready and Horizon-safe

BA-19 selects **PHP-FPM initially** and an **Octane-ready** design, **not** Octane activation in the first Pilot. Horizon queue workers are long-lived.

**Never** store mutable **Tenant context, actor, role/permission, Location scope, request-specific locale or DB connection** in static properties or a long-lived Singleton. Each worker attempt must re-resolve and validate its trusted context and clean mutable state/connection. Failed/killed attempts require fresh context initialization.

Stancl/Laravel 13 compatibility must be proven with reused workers covering **Tenant A → Tenant B → Central**, retries, failed jobs and appropriate queue transport isolation. Never silently fall back to Central or the previous Tenant.

---

## 10. Naming, Type Discipline and PHP Formatting — CS-08

### 10.1 Naming table

| Element | Standard | Example |
| --- | --- | --- |
| Class/Interface/Enum | PascalCase | ConfirmOrderAction, OrderStatus |
| Feature Action | Verb + business object + Action | ConfirmOrderAction |
| Focused Domain Service | Meaningful domain term + Service | StockEligibilityService |
| Public Contract | Descriptive PascalCase | InventoryReservationContract |
| DTO | PascalCase, normally ending Data | ReserveStockData |
| Enum Cases | PascalCase with **explicit int values** | Pending = 1 |
| Methods | camelCase, verb-oriented | calculateTotal() |
| Variables/Properties | camelCase | $orderTotal |
| Constants | UPPER_SNAKE_CASE | MAX_RETRY_ATTEMPTS |
| File/class path | PSR-4 and matching PHP class filename | ConfirmOrderAction.php |
| Database table/column | snake_case | order_items, created_at |
| API JSON and query | camelCase | customerName, locationId |
| HTTP header | Standard header spelling | X-Request-ID, Idempotency-Key |
| Error code | Stable documented identifier | INSUFFICIENT_STOCK (illustrative) |

Avoid processSomething(), handleData() or giant vaguely named Managers for distinct business operations.

### 10.2 Coding rules

- **declare(strict_types=1)** in new first-party PHP files.
- Explicit parameter/property/return types when meaningful; avoid unnecessary mixed and broad untyped arrays.
- Use **final/readonly** when their semantics fit; do not force them on a class intentionally designed to extend or requiring a supported framework pattern.
- Small single-purpose methods, clear naming, minimal mutable state and no unnecessary inheritance.
- Use PHPDoc list/array shapes and generics where PHP native syntax lacks generics; verify them with supported static analysis.
- Never hard-code business status values or invent a string-backed Enum.
- Keep internal PHP naming, PostgreSQL snake_case and API camelCase separated through explicit mappings.
- Don't add significant new dependencies or generative frameworks only to avoid a few lines of native Laravel.

### 10.3 Tools

Use a **PSR-12-compatible Laravel Pint** configuration as the intended formatting baseline. Use supported **PHPStan / Larastan** to catch type and risky code issues. Establish versions and analysis level **after verifying actual PHP/Laravel package compatibility**, and tighten incrementally without hiding new findings.

The repo is documentation-only; this file **does not claim Pint, PHPStan or tests are currently installed/configured**.

---

## 11. Quality Gates and Pull Request Reviews — CS-09

### 11.1 Intended PR checks

| Check | Purpose | When relevant |
| --- | --- | --- |
| Laravel Pint | Consistent formatting | Every PHP code PR |
| PHPStan/Larastan | Static type and code safety | Every applicable PHP PR |
| PHPUnit or Pest | Unit/Feature behavior and regression | Every affected Feature PR |
| OpenAPI/contract tests | API response, camelCase, status and backward compatibility | API-changing PRs |
| Architecture boundary checks | Dependency direction, module owners and authorized contracts | Module/feature PRs |
| Actual PostgreSQL Integration | Stock, financial, concurrency and locking correctness | Critical DB/commerce PRs |
| Security/dependency checks | Composer audit and authentication/privacy risk | Dependency or security-sensitive PRs |
| Extended suites | Tenant A/B, outbox crash, migration/release, load and recovery evidence | High-risk integration / pre-Pilot gates |

Exact test runner, tool versions, static analysis level, GitHub Actions YAML, branch protection, reviewer counts and thresholds are **TO SPECIFY and NOT VERIFIED as active**. These gates are intended future engineering requirements, not assertions of current CI success.

### 11.2 Critical blockers

Cross-Tenant data leaks, authorization/Location bypass, revocation failure, stock overselling, duplicate physical movement, duplicated actual Collection/Refund, false Confirmed without Reservation, unhandled critical Outbox durability loss and destructive silent financial history rewrites are **blocking defects** when applicable.

Use **real PostgreSQL** for concurrency/ACID/constraint tests (not just mocks) and test independent transactions on last-unit stock. Tenancy tests should cover **Central plus at least two isolated Tenant databases** and the actual queue context as applicable. Do not require heavy load/chaos tests for every tiny PR; apply progressive risk-based gates.

### 11.3 Pull Request evidence

A PR should document:

1. Approved Feature Spec/decision, scope and changed files.
2. Owning Modules, changed Public Contracts and dependent consumers.
3. Schema/Enum/API/authorization/financial effects and compatibility risk.
4. **Actual** commands/tests executed and outcomes; explicitly say **not run** when appropriate.
5. Negative, regression, concurrency and Tenant/Location permission cases relevant to the change.
6. Security/privacy checks, release/rollback/backfill implications and unresolved items.
7. Whether merge/deployment was **authorized and actually performed**.

**No automatic merge or deployment without explicit authority and required checks.** A formatter success or AI claim is not equivalent to real test evidence.

---

## 12. Logging, Correlation and Business Audit — CS-10

### 12.1 Structured logs

Use Laravel Log with contextual, searchable fields. Relevant context may include verified Tenant ID or Central scope, Order/Business Operation ID, trusted actor/Location reference where appropriate, Correlation ID, event/job ID, attempt count and safe error identifier.

~~~php
// Illustrative only: scope values must come from verified context.
Log::warning('order.confirmation_conflict', [
    'tenant_id' => $trustedTenantId,
    'order_id' => $orderId,
    'operation_id' => $operationId,
    'correlation_id' => $correlationId,
    'error_code' => 'INSUFFICIENT_STOCK',
]);
~~~

Expected business conflicts are **not automatically Error-level system failures**. Avoid logging the same exception in several layers, flooding logs with normal validation and treating a technical retry as a second business event.

### 12.2 Identifier meanings

| Identifier | Purpose |
| --- | --- |
| **X-Request-ID** | Server-generated identifier for an individual HTTP attempt, returned in the header |
| **Correlation ID** | Trace across request, Application Action, Outbox and Horizon job |
| **Operation/Business Reference** | Stable identity for actual business effect and domain idempotency |
| **Idempotency-Key** | Targeted client retry key per API-05; scoped and not the same as X-Request-ID |
| **Event/Job ID** | Publication or processing reference, not authorization to apply a stock/payment effect |

A caller-provided ID/tenant selector does not become trusted simply because it is logged.

### 12.3 Sensitive data

Never log passwords, tokens, cookies/sessions, signing secrets, SMTP/database credentials, full card data/CVV, unnecessary customer identifiers/contact data, private attachments, raw sensitive third-party payloads or unredacted stack traces sent to clients. Scope access/retention to logs containing operational Tenant references.

**APP_DEBUG must be disabled in Production.** No ELK, Datadog, OpenTelemetry or other vendor is mandated from day one; structured output and threshold definitions must be validated in focused Observability Standards.

### 12.4 Logs are not business Audit

| Technical Logs | Business Audit |
| --- | --- |
| Diagnose performance, retries, exceptions and infrastructure | Prove sensitive authorized action, reason and actual outcome |
| May have bounded operational retention | Retained by legal/financial/business policy |
| Secured aggregatable telemetry | Tenant business Audit in Tenant DB; platform administration Audit in Central |
| Not an authoritative finance/stock record | Linked original records, reversible/corrective entries, actual evidence |

For material sensitive operations, capture **actor, Tenant/Location, operation ID, time, old/new state when relevant, reason, permission/approval, outcome and correction lineage**. Do not silently delete/overwrite real sale, Collection, Refund or stock history.

Track Outbox backlog/age, failed Horizon retries, unknown external money outcomes and database contention as actionable signals, without inventing numeric thresholds in this file.

---

## 13. AI Agent Rules and Spec-Driven Workflow — CS-11

### 13.1 Required AI sequence

~~~text
READ
  Current repository source + approved D/OV decisions + relevant Architecture
  and Standards + concrete Feature Spec.

PLAN
  Owning Module, files, Contracts, dependencies, authorized context,
  DB/API/Enum risks and tests. Resolve design within approved scope.

IMPLEMENT
  Small coherent Feature change. Follow Public Contracts and One Writer.
  Do not include unrelated refactors.

VALIDATE
  Run the actual available formatter, static checks, tests and diff review;
  report real outcomes, limitations and unexecuted checks.

REVIEW
  Submit PR evidence for human/authorized review.

MERGE / DEPLOY
  Only when specifically authorized with the required real validation.
~~~

The Agent may choose ordinary private implementations without asking about each helper Method/Class. It **must not** redefine product policies, multi-Tenant boundaries or unapproved Scope.

### 13.2 Always / never

- Always read **existing actual files**; do not hallucinate an installed package, class, endpoint, migration, status check or working service.
- Follow binding D-series, OV and Architecture decisions, including **D-201 OPEN**. Do not silently select an unresolved option.
- Use explicit Public Contracts for cross-module change; do not directly edit another Module's internals.
- Preserve stable int-backed Enum values, actual historical evidence, approved currency logic, authentication and Location permissions.
- Prefer focused changes/test cases. Do not turn a narrow bug fix into an unrelated global refactor.
- Respect customer privacy and secrets in code, debug dumps, test fixtures, PR notes and logs.
- Never report a migration, CI check, PostgreSQL race test, payment/refund, deployment or merge as successful unless actually verified.
- For a potentially committed external charge/refund/stock operation, reconcile outcome before a risky retry.
- Never assume rights to change Production data, merge to main or deploy.
- Do not invent online gateway, carrier API, full POS, universal Saga engine, marketing automation or unauthorized other-MVP scope.

### 13.3 Changes requiring approval or escalation

| Change | Why |
| --- | --- |
| Business/customer agreement/refund/stock rule | Alters binding decisions |
| Public Module Contract / ownership | Can break dependents or domain invariants |
| API/OpenAPI wire fields, response schema, new guard | Client compatibility/security |
| Migration, persisted Enum value, financial historical record | Data integrity/rollout |
| Queue event schema, idempotency policy, retry of external money | Duplicate-effects risk |
| Tenant/Location permission or privileged actor state | Security/isolation |
| New provider/framework/dependency beyond scope | Complexity, operations and cost |
| Large unrelated refactor, new feature, PR merge or Deployment | Beyond routine approved Feature execution |

### 13.4 Minimum handoff

~~~text
Approved Feature / source:
Changed files and why:
Owner Modules / Public Contract impact:
Database / Enum / API / permission changes:
Tests actually run and evidence:
Results: pass / fail / not run:
Tenant/Location + financial/inventory safety review:
Open risks and decisions:
PR status, merge status and deployment status:
~~~

An AI Agent completion message must distinguish **what was implemented**, **what was tested**, **what failed**, and **what was not attempted**.

---

## 14. Concrete Good/Bad Examples

### 14.1 Correct conceptual Manual Order Confirmation

~~~text
Orders ConfirmOrderAction
  -> Check verified Tenant, staff permission and Location scope
  -> Revalidate current Order state and customer agreement
  -> Obtain current authoritative Pricing/Discount information
  -> Start short validated Tenant PostgreSQL transaction
  -> Protect mutable Order/Stock state
  -> Call Inventory Public Contract to reserve all eligible lines
     in one eligible Location
  -> Persist Orders-owned Confirmed state and historical snapshot
  -> Persist same-Tenant Outbox event intent atomically
  -> Commit; Notifications process later with safe retries
~~~

When stock is insufficient, confirmation and reservation fail together. Manual Pending Stock is not an automatically reserved backorder.

### 14.2 Forbidden direct cross-module write

~~~php
// ANTI-PATTERN — Orders must not modify Inventory internals.
InventoryStock::where('location_id', $locationId)
    ->decrement('on_hand', $quantity);
~~~

This bypasses ownership, available/reserved safeguards and may duplicate a physical movement.

### 14.3 Forbidden request-global mutable state

~~~php
// ANTI-PATTERN — leaks scope across Horizon / future Octane lifecycles.
final class GlobalTenantState
{
    public static ?int $currentTenantId = null;
}
~~~

Context must be re-resolved and verified for each request/job.

### 14.4 Forbidden false business success

~~~text
Incorrect: Delivered -> automatically COD Settled.
Incorrect: Refund API timeout -> issue the same refund a second time.
Incorrect: Return Approved -> stock instantly sellable.
Incorrect: Outbox Job succeeded -> inventory confirmation valid.

Correct: Keep independent authoritative business facts and evidence,
use once-only business references, reconcile ambiguity and audit correction.
~~~

### 14.5 Avoid ceremonial plumbing

~~~text
Avoid for each trivial CRUD:
  Controller -> Service -> AbstractBaseService -> InterfaceRepository
             -> BaseRepository -> ConcreteRepository -> Eloquent

Prefer when appropriate:
  Controller -> focused Feature Action -> own Eloquent
or:
  Controller -> Feature Action -> provider-owned Public Contract
~~~

No example above authorizes a final Laravel class signature or actual API endpoint; a Feature Specification provides that detail.

---

## 15. Final Review, Approval and Future Decisions — CS-12

### 15.1 Approved publication

The owner approved CS-01…CS-11 and requested the comprehensive **CS-12 Option A** standards file to be written on GitHub **development**. This is a **documentation-only commit**. It does not authorize PHP implementation, CI setup, dependency installation, schema changes, deployment, main promotion or Pilot activation.

### 15.2 Cross-check with Architecture

| Reviewed rule | Result |
| --- | --- |
| MA-02 Feature-first Lightweight Clean Architecture | **Compatible**: pragmatic layers and no mandatory boilerplate |
| MA-03/04 Public Contracts + One Writer | **Compatible**: Eloquent permitted only inside owning Module, contract-only foreign writes |
| BA-07/CW-01 and TC-01 transactions | **Compatible**: use-case-owned atomic local critical work |
| API-02/04 response and validation | **Compatible**: Form Requests, ApiResponse, HttpStatusCode-style enum and camelCase wire |
| DA-04 money | **Compatible**: BIGINT integer minor units, explicit currency |
| Owner CS-05 / PostgreSQL | **Clarified**: int-backed Enums, SMALLINT for enum states, BOOLEAN for true/false; no PostgreSQL TINYINT |
| BA-19/QJ-02 long-lived worker safety | **Compatible**: explicit DI and no shared mutable Tenant/User globals |
| TC-03/04 and CW-08 idempotency | **Compatible**: stable business refs, Outbox at-least-once, no fake exactly-once external effect |
| BA-23/25 and MA-08 quality gates | **Compatible**: risk-based required future checks; no claim CI exists |
| BA-14/24 and TC-06 observability | **Compatible**: structured diagnostics plus separate Tenant/Central Audit |
| D-201, BA-16, BA-30 | **Unchanged**: OPEN / DEFERRED / G3 BLOCKED |

### 15.3 Pending implementation details

**TO SPECIFY separately, without inventing approvals:**

- Actual Composer/PHP/Laravel versions and runtime/plugin compatibility.
- Precise PSR-4 namespace map and Module Service Provider registration.
- Pint config, PHPStan/Larastan compatible release/level, selected PHPUnit/Pest runner.
- Actual CI YAML, protected-branch rules, status checks and PR review counts.
- Exact Action/DTO/Error code contracts and approved endpoint schemas.
- Exact enum case numbers for each Feature and schema CHECK constraints.
- Database index/lock/isolation design for individual stock/payment features.
- Money rounding, taxes, currency support and precision rules.
- Numeric log/error retention, SLOs, alert thresholds and monitoring platform.
- Legal obligations for applicable Egypt merchant cohorts.
- **D-201** Alternative-Location follow-up marker versus notes — **OPEN**.
- **BA-16** Backup & Restore / recovery strategy — **DEFERRED**.
- **BA-30 G3** live Pilot restore readiness — **BLOCKED**.

---

## 16. Developer and AI Pre-PR Checklist

- [ ] Approved Feature Spec and relevant D/OV/CW/MA/API/TC decisions consulted.
- [ ] Correct owning Module and no direct writes/imports into another Module's internals.
- [ ] Trusted Central/Tenant/Location/actor/entitlement/resource scope enforced.
- [ ] Thin HTTP adapter, focused Action and selective Service/Repository only where justified.
- [ ] Typed DTOs for public boundaries; no foreign Eloquent Model or HTTP Request leakage.
- [ ] All project Enums int-backed; persisted states SMALLINT, genuine flags BOOLEAN; no changed historical enum values.
- [ ] Strong types/strict_types and appropriate final/readonly with naming standards.
- [ ] Accurate integer minor-unit financial calculations; original financial history preserved.
- [ ] Correct one-Location reservation, PostgreSQL stock concurrency and once-only physical effects.
- [ ] Critical local transaction, database constraints, business idempotency and durable Outbox.
- [ ] Correct Form Request, centralized exception mapper, ApiResponse, HTTP status and API camelCase.
- [ ] Secure meaningful Log context and independent material Business Audit.
- [ ] Actual formatting/static/test commands and results recorded (or explicitly not run).
- [ ] Negative authorization, cross-Tenant, duplicate retry and stock/money tests covered when applicable.
- [ ] Schema/API/Enum/Public Contract and deployment effects surfaced for approval.
- [ ] PR reviewed and no unauthorized merge to main or Coolify/Production deployment.

---

## 17. Final Engineering Position

**The code standard is Laravel-native, Feature-first and pragmatic.** Business workflows belong to narrow **Application Actions**; reusable rules to focused **Domain Services**; authoritative persistence to the owning Module via **Eloquent first**, with a Repository only when it earns its cost. **Public Contracts and typed DTOs** protect cross-module boundaries; real-world stock and money are protected by PostgreSQL transactions, domain idempotency and audit, not cross-module table writes or queue assumptions.

All project PHP **Enums are int-backed**, using PostgreSQL **SMALLINT** for state and **BOOLEAN** for true/false flags. Strong PHP types, explicit dependencies, consistent API serialization, structured logs, risk-based testing, genuine PR review and Spec-driven AI Agents are expected in all future implementation work.

**Architecture is documented, not deployed.** Future Foundation and Feature Specs must implement and prove these rules. **D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.**

**End of approved Code Standards baseline.**
