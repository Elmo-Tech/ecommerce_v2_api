# E-commerce SaaS V2 — Tenancy Architecture

**Version:** 1.0 — Approved Document / Conditional Technical Adoption  
**Date:** 2026-11-17  
**Target Path:** `docs/01-architecture/tenancy-architecture.md`  
**Status:** APPROVED CONTENT — CONDITIONAL ARCHITECTURE BASELINE (TA-08 A)  
**Implementation Status:** Not implemented or validated

---

## 1. Purpose and Scope

Define the minimum Multi-Tenancy architecture required for the E-commerce SaaS V2 MVP.

The backend uses Laravel 13, PostgreSQL, Redis and Laravel Horizon within a Modular Monolith.

The approved data isolation model is:

- One Central PostgreSQL Database.
- One independent PostgreSQL Database per merchant.
- Logical tenant isolation across APIs, jobs, cache, files and exports.

**Technology selection:** `stancl/tenancy v3.10.1`, conditionally approved.

Final adoption requires successful integration testing against the project's security, authentication, database and worker requirements.

This document complements [Backend Architecture](./backend-architecture.md) and [Module Architecture](./module-architecture.md). Their approved business and technical boundaries remain authoritative.

## 2. Architecture Decisions

| ID | Approved Direction |
|---|---|
| TA-01 | Stancl-Based Lightweight Tenancy |
| TA-02 | Trusted Tenant Identification |
| TA-03 | Stancl-Managed Tenant Connections |
| TA-04 | Simple Asynchronous Provisioning |
| TA-05 | Stancl + Laravel Native Migrations |
| TA-06 | Tenant-Aware Horizon Jobs |
| TA-07 | Lean Isolation and Focused Automated Tests |
| TA-08 | Conditional Stancl-Based Tenancy Baseline |

TA-01 through TA-07 reflect the conditionally approved Stancl-based implementation, replacing the earlier custom Laravel-native implementation direction.

The underlying isolation and authorization requirements are unchanged.

## 3. Tenant Identification and Authorization

Tenant resolution depends on the execution context.

**Storefront requests**

- Identify the tenant using an approved platform subdomain mapped through the Central Tenant Directory.
- Use Stancl's supported subdomain identification capabilities.
- Validate tenant state and applicable entitlement before accessing tenant resources.
- Reject unknown, unavailable or unauthorized tenants.

**Merchant Dashboard / API requests**

- Authenticate merchant staff through Central Identity.
- Verify explicit tenant membership and active-tenant selection.
- Initialize the authorized tenant context using Stancl.
- Reject any mismatch between a trusted host and the selected tenant.

**Platform operations**

- Remain Central-Only unless an authorized Cross-Context workflow explicitly requires tenant access.
- Platform Super Admin status does not automatically grant unrestricted access to merchant business data.

A client-provided tenant identifier, hostname header or request parameter is never sufficient authorization.

Staff roles, Location scopes and resource permissions remain tenant-local. Storefront customer identities remain independent within each Tenant Database.

Browser sessions use the approved Sanctum session and CSRF model, with tenant-safe cookie and session isolation.

## 4. Database Connections and Security

Stancl manages the dynamic `tenant` connection.

- Central Models and platform-owned operations use an explicit `central` connection.
- Tenant operations run only inside an authorized tenant context.
- Stancl may switch Laravel's default connection within that validated tenant execution context.
- Tenant connections must not fall back to Central or another tenant on failure.
- Tenant switching during an active transaction is prohibited.
- Context and connection state must be cleaned after requests and jobs.

The tenant connection remains lazy where supported by the selected integration.

### PostgreSQL permissions

Each tenant requires a dedicated least-privilege PostgreSQL runtime role.

Provisioning and migration credentials must remain separate from application runtime credentials.

Stancl's PostgreSQL database management will be extended minimally where necessary to create and configure restricted PostgreSQL roles and grants.

Secrets must use protected storage or configuration. Central records contain opaque credential references rather than plaintext passwords.

The exact secret-management provider remains an Infrastructure implementation detail.

**Connection pooling:** PgBouncer and custom pooling are excluded from the first Pilot. Connections, worker concurrency and timeouts will be bounded and measured.

## 5. Tenant Provisioning

Only Platform Super Admin may initiate merchant provisioning during the MVP.

A single asynchronous Horizon provisioning workflow coordinates the required operations:

1. Register the tenant in Central DB with `Pending` status.
2. Transition to `Provisioning` under an execution lock.
3. Create or verify the tenant database and restricted PostgreSQL roles.
4. Apply tenant migrations and essential initial data.
5. Validate connections, schema and required initialization.
6. Mark the tenant `Ready` on success or `Failed` on failure.

Use supported Stancl provisioning operations rather than rebuilding database management.

Automatic tenant-creation listeners must be configured so they do not duplicate the approved provisioning workflow.

Provisioning must be **idempotent and retry-safe**:

- Never create duplicate resources on retry.
- Preserve partial failure information for authorized recovery.
- Never activate an incomplete tenant.
- Never automatically delete a tenant database after failed provisioning or subscription expiry.

`Ready` means technically provisioned; it does not automatically mean the SaaS subscription or every feature is active.

Cross-database provisioning does not assume a distributed ACID transaction.

## 6. Central and Tenant Migrations

Central and Tenant migrations remain separate.

Tenant schema changes use Laravel Native Migrations with Stancl's tenant migration capabilities.

A lightweight controlled runner coordinates deployment across tenant databases.

Required behavior:

- Track migration versions in each Tenant Database.
- Record rollout status and failures centrally.
- Run updates in controlled batches.
- Prevent concurrent migrations against the same tenant.
- Retry safely without repeating completed migrations.
- Isolate failures without silently reporting a successful rollout.
- Check schema compatibility before release activation.

Production deployments follow expand-first migrations and compatible Blue-Green releases.

Destructive schema changes require a separately validated transition. Application rollback must not assume automatic database rollback.

Tenant provisioning uses the same tenant migration definitions as existing-tenant updates.

## 7. Tenant-Aware Jobs and Shared Resources

**Queues and workers**

Laravel Horizon and Redis remain the background-processing infrastructure.

Use Stancl's `QueueTenancyBootstrapper` for tenant-aware jobs.

- Tenant jobs carry a trusted tenant reference.
- Workers revalidate the relevant tenant context and execution authorization.
- Central and tenant jobs use explicitly separated queue execution contexts.
- Queue transport must not accidentally inherit tenant-prefixed Redis state.
- Connections and tenant context must be cleaned between jobs, including failed and retried jobs.
- Business-critical operations enforce idempotency.

No dedicated queue or worker process is required per tenant.

**Cache**

Cache entries must be environment- and tenant-scoped, with additional actor or Location scoping where necessary.

Use supported Stancl/Laravel mechanisms without introducing a custom cache framework.

**File storage and exports**

BA-15 remains **Local Persistent Storage First + S3-Ready**.

Tenant files and exports use Laravel Storage on persistent mounted volumes, outside disposable containers.

Production and Development storage remain separate. Private file access requires authorization beyond directory isolation.

Stancl filesystem configuration must be compatible with persistent storage and Blue-Green deployment.

## 8. Isolation and Failure Handling

Tenant safety follows a fail-closed policy.

An unknown tenant, invalid membership, context mismatch, unavailable database or unresolved credential must never result in access to a default or previous tenant.

Minimum integration coverage uses a Central PostgreSQL Database and at least two Tenant Databases.

| Validation Gate | Required Evidence |
|---|---|
| Laravel 13 compatibility | Stancl installs and initializes correctly with the selected Laravel version |
| PostgreSQL separation | Tenant A and B runtime roles cannot access each other's databases |
| Identity and connections | Subdomain, membership, Sanctum sessions and Central/Tenant boundaries are enforced |
| Horizon isolation | Sequential Tenant A, Tenant B and Central jobs do not leak context or connections |
| Provisioning and migrations | Creation, partial failure, safe retry, batch updates and readiness checks succeed |

Additional focused regression tests cover cross-tenant API reads and writes, cache isolation, private files, exports, Location permissions and failure handling.

**Cross-tenant access or leakage is a release blocker.**

Passing package compatibility checks does not replace feature-level authorization, PostgreSQL isolation tests or Pilot readiness verification.

## 9. MVP Boundaries and Open Items

The MVP does not require:

- A custom Multi-Tenancy Framework alongside Stancl.
- PgBouncer or custom connection pooling.
- Separate Redis or Horizon infrastructure per merchant.
- A separate provisioning microservice or workflow engine.
- A dedicated migration dashboard.
- Enterprise-scale orchestration or multi-region deployment.

**Open or deferred items**

- **Stancl integration:** PENDING VALIDATION against the five gates in Section 8.
- **Database secret provider:** Implementation mechanism not yet selected.
- **BA-15:** APPROVED — Local Persistent Storage First.
- **BA-16:** DEFERRED — verified off-server backup and restore remain mandatory before the live Pilot.
- **D-201:** OPEN — business decision outside this document's scope.

## 10. Approval and Implementation Boundary

**Architecture decision:** TA-08 A — Conditionally Approved.

The approved direction is to adopt `stancl/tenancy v3.10.1` using only the components necessary for MVP delivery.

**Final technical adoption requires passing the integration validation gates.**

If the package cannot satisfy the approved PostgreSQL, authentication or isolation requirements without disproportionate customization, the implementation choice must be reviewed again before proceeding.

This document does not authorize implementation, repository changes, deployment or activation of real merchants.

---

**Reference Documentation**

- [Backend Architecture](./backend-architecture.md)
- [Module Architecture](./module-architecture.md)
- [Stancl Tenancy v3 Documentation](https://tenancyforlaravel.com/docs/v3/)
- [Stancl Package on Packagist](https://packagist.org/packages/stancl/tenancy)
