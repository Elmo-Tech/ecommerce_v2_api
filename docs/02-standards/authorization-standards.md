# E-commerce SaaS V2 — Authorization Standards

**Repository:** Elmo-Tech/ecommerce_v2_api  
**Path:** docs/02-standards/authorization-standards.md  
**Branch:** development  
**Version:** 1.0 — Approved Authorization Engineering Standards Baseline  
**Prepared:** 2026-11-20  
**Decision status:** AUTHZ-01…AUTHZ-09 Option A APPROVED; AUTHZ-10 Option A amendment and publication APPROVED  
**Implementation status:** DOCUMENTATION ONLY — NOT IMPLEMENTED / INTEGRATION-TESTED / DEPLOYED

> This document records approved implementation standards, **not** code, configured Redis, tested PostgreSQL isolation, working Laravel/Next.js integration, or a passed live-Pilot gate. **D-201 remains OPEN; BA-16 Backup/Restore remains DEFERRED; BA-30 G3 remains BLOCKED.** Authentication standards (including 8-character minimum password and no MFA in MVP) remain unchanged.

---

## 1. Purpose, Governance and Boundaries

Authorization determines whether the already identified actor may execute the requested operation on a specific Tenant, Location and Resource, under the current business and security rules. Authentication identifies the actor; a valid login alone never grants business access.

The approved deployment direction is Laravel 13 Modular Monolith / Next.js, one Central PostgreSQL DB and physically separate PostgreSQL databases per Tenant. Laravel Sanctum provides first-party browser sessions; stancl/tenancy v3.10.1 remains conditionally selected pending actual Laravel 13 integration validation.

### 1.1 Governing documents

| Reference | Binding authority |
| --- | --- |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | IA-01…IA-09, Central workforce identity, Tenant-local permission and Location scope, no global bypass |
| [Authentication Standards](./authentication-standards.md) | AUTH-01…AUTH-10, guards, sessions, secure revocation and email/password |
| [API Standards](./api-standards.md) | AS-01…AS-10, explicit Merchant URI, scoped list/search, errors and pagination |
| [Code Standards](./code-standards.md) | CS-01…CS-12, Modules, typed Actions, Gates/Policies, audit, tests, int-backed project Enums |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | TA decisions; trusted Tenant selection, independent connections and cleanup |
| [Data Architecture](../01-architecture/data-architecture.md) | DA, identifiers, single public PostgreSQL schema per DB, constraints and time/money |
| [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) | CR, PostgreSQL authority, scoped Cache, revocation safety and Redis workload isolation |
| [Module Architecture](../01-architecture/module-architecture.md) | Identity & Access ownership; business module ownership; Public Contracts |
| [Queue, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | Tenant-aware Horizon, current actor authorization for user-driven jobs |
| [Transaction Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | Protected transaction boundaries, idempotency and cross-DB orchestration |
| [Business Discovery Handoff](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) | D-078…D-088 manual discount and D-126…D-135 Refund decisions, D-178 corrections |
| [D-152 onward Decision Register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) | Approved later commercial/fulfillment behavior |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | Unapproved D-201 and release blockers |
| [Repository Workflow](../WORKFLOW.md) | Proposed development/feature/main process, not proof CI is active |

**Hierarchy:** D-series governs business rules; approved architecture governs data/system ownership; AS/CS/AUTH/AUTHZ govern engineering practice; each Feature Spec fixes the specific endpoint, final Permission key, data fields and any still-open behavior. Do not silently replace a D-series decision with a generic authorization pattern.

### 1.2 Scope and exclusions

**Included:** Module-owned Permission Registry; default/custom Tenant Roles; grant delegation; separate Location Scope; sensitive approval/Refund rules; middleware/Policies/Actions; Redis grant-cache invalidation; Platform and scoped Support authority; Next.js capability UX; audit, testing and release gates.

**Excluded:** new IAM/ABAC framework, global Permission bypass, global Customer directory, JWT replacement of Sanctum Sessions, arbitrary User permission overrides, new commercial limits, generic mandatory Maker–Checker, new Support impersonation feature, ERD/schema approval and deployment authority.

---

## 2. Approved AUTHZ Decision Register

| ID | Approved choice | Binding effect |
| --- | --- | --- |
| **AUTHZ-01** | **A — Module-Owned Permission Registry + Tenant RBAC** | Defined module-owned permissions, Tenant-local Roles, Laravel Gates/Policies and Deny by Default |
| **AUTHZ-02** | **A — Single Tenant Role + Default/Custom Roles + Controlled Delegation** | Exactly one active Role per Workforce account per Tenant in MVP; protected Owner and bounded grant authority |
| **AUTHZ-03** | **A — Explicit Location Assignments + Resource-Level Enforcement** | Location Scope separate from Role, Specific/explicit All, every relevant Location checked |
| **AUTHZ-04** | **A — Sensitive Permissions + Business-Owned Approval Rules** | Separate apply/approve Refund/discount authority and business-specific limits; no universal double approval |
| **AUTHZ-05** | **A — Layered Middleware + Gates/Policies + Scoped Actions/Queries** | Verified Identity, Tenant, membership, entitlement, Role, Location and Resource before business mutation |
| **AUTHZ-06** | **A — Selective Redis Cache + Trusted Version-Based Invalidation** | PostgreSQL authoritative; no stale cached positive grants after revocation |
| **AUTHZ-07** | **A — Central Platform Permissions + Explicit Scoped Support** | No global Tenant bypass; future support workflow must be separately authorized |
| **AUTHZ-08** | **A — Backend-Driven Effective Capabilities for Next.js** | Laravel final authority; frontend uses Tenant-scoped capabilities for UX only |
| **AUTHZ-09** | **A — Focused Audit + Actual Integration Tests + Blocking Gates** | Central + 2 real Tenant PostgreSQL DBs and Redis, risk-based Critical security tests |
| **AUTHZ-10** | **A — Final Review + Narrow D-084 Amendment + Authorized Publication** | Single Role remains; only individually delegated Approve Manual Discounts permitted as a controlled exception |

### 2.1 Special AUTHZ-10 interpretation

During final review, the approved **AUTHZ-02 no general direct User Permissions** choice required a narrow reconciliation with **D-084**, which allows **Approve Manual Discounts** to be assigned to an authorized Role or individual User.

The owner approved **Option A**: permit an **explicitly delegated, audited individual Approve Manual Discounts authority only**, while retaining the **one active Tenant Role per employee** rule and excluding all other general per-user direct permission overrides.

This is a **small business-specific exception**, not a second Role, a generic direct allow/deny framework, or permission to assign Refund authority. It is subject to all D-084/D-085 conditions: different approver from requester when approval is required; valid approval permission; correct Tenant/Location and current Membership; role/individual ceiling behavior as explicitly specified; hard discount stacking ceiling and Order rules; authorized delegation and revocation.

**Important unapproved detail:** D-085 defines **optional Role approval percentage ceilings**, but does not specify how a directly delegated individual's cap should be inherited, configured or limited. This must be resolved in the Pricing/Authorization Feature Spec before activating the individual exception. Do not interpret a missing cap as an unintended unlimited new permission.

---

## 3. Permission Registry and Tenant RBAC — AUTHZ-01

### 3.1 Module-owned definitions

Each Module defines and documents its own allowed Permissions through reviewed Feature contracts, with a common discoverable Permission Registry. The Registry defines **which capabilities may be assigned**; it does not itself grant them.

| Module | Illustrative Permission | Meaning |
| --- | --- | --- |
| Catalog | products.view, products.create, products.update | Read/create/update products |
| Orders | orders.view, orders.confirm | Read/confirm orders |
| Inventory | inventory.adjust, inventory.transfer | Adjust/transfer stock |
| Pricing | discounts.applyManual, discounts.approveManual | Request/apply vs approve manual discounts |
| Refunds | refunds.issue, refunds.approveException | Refund issue vs exception authorization |
| Identity | roles.manage, roles.assign | Manage vs assign Roles |
| Reporting | reports.export | Authorized report export |

**These Permission keys are illustrative only; they are not the final approved public/wire keys.** Their canonical names, namespaces and versioning belong to Feature Specs.

A code-defined Registry is sufficient when appropriate; this decision does **not** require a Central database table for every Permission, a dynamic policy language or a custom IAM engine. It must prevent the UI/clients from creating arbitrary unknown backend powers. New Registry entries are **not automatically granted** to existing Roles at application update.

### 3.2 Identity, Role and Location data placement

**Central PostgreSQL** owns:
- Platform/Workforce login identity and current account state.
- Membership to each Tenant, invitation activation/suspension/revocation.
- Platform-specific administration authorization.
- Central identity/membership/platform audit.

**Tenant PostgreSQL** owns:
- Role definitions and grants.
- One Role assignment per active Workforce identity in that Tenant.
- Explicit Specific/All Location assignment.
- Narrow individual delegated discount-approval authorization, where approved and feature-specified.
- Tenant-local Role and Location security audit.

No duplicated login credential record per merchant. A Role in Store A never conveys rights in Store B. Merchant Owner/Employee both use Central Workforce authentication and distinct local authorization.

**Tenant Customers** use a separate Tenant-local Customer guard/resource ownership policy, not Merchant Employee Roles. Guest operations remain unauthenticated only where the business flow expressly permits them.

### 3.3 Distinct authorization controls

A Merchant API action can require all of:
1. Valid matching authentication guard and active Central account.
2. Active Central membership in the requested Tenant.
3. Trusted available Tenant state and eligible SaaS Feature.
4. Tenant-local Role grants, optional narrow delegated discount authority.
5. Explicit Location Scope and Resource ownership.
6. Approval amount, business status and legal/stock/payment conditions.
7. Protected recheck before high-risk commit.

**Entitlement is not employee permission.** **Permission is not Location access.** **Permission to request a refund is not proof that the proposed amount may be executed.** Never grant access merely by matching a role display name.

### 3.4 Default deny and failure behavior

Unknown Permission, invalid Tenant/membership, missing Role, unverified Location, inactive account, unavailable authoritative database, unauthorized Resource, failed entitlement check or invalid support context must result in **Deny by Default**. Do not fall back to a previous Tenant, a cached grant or a global Platform admin shortcut.

---

## 4. Default Roles, Custom Roles and Delegation — AUTHZ-02

### 4.1 Role model

| Type | Standard |
| --- | --- |
| Default Role template | System-provided stable baseline; copy to Custom Role before tailoring; no surprise post-release permission expansion |
| Custom Role | Tenant-owned Role made from permitted Registry entries |
| Protected Owner | Ownership authority established by secure merchant onboarding, not public self-assignment |

Actual Role names and counts are **TO SPECIFY**. "Manager", "Sales" and "Inventory" are examples only.

**One active Role per Central Workforce user per Tenant in MVP.** If the same employee needs Sales plus Inventory, the merchant creates one authorized Custom Role containing those grants. Do not silently union multiple Roles or permit generic per-user allow/deny overrides.

### 4.2 Permission delegation boundaries

Distinguish:
- Permission to manage Role definitions.
- Permission to assign existing Roles.
- Permission to manage Central Tenant memberships.
- Authority to **delegate particular Permissions**.
- Authority to delegate a particular **Location**.
- Protected Owner-specific management workflow.

Possessing a Permission does **not** automatically allow granting it to another actor. Before creating, editing or assigning a Role, validate the grantor's current membership, Role, delegable permissions and Locations; validate target Role/membership/Tenant and every effective granted permission. Unauthorized extra grants are rejected, even if the grantor can create Roles.

No general self-escalation through creating a powerful Role and assigning it to oneself. Any authorized exception would need an explicit independent management policy, not an implicit Manager shortcut.

### 4.3 Last active Owner protection

Deleting/demoting/revoking the **last active protected Owner** through ordinary Employee management is forbidden. Check effective active membership and ownership, not merely whether a Role called Owner exists. Protect concurrent last-owner mutations using appropriate PostgreSQL constraints/locks and cross-context orchestration.

First Owner's access is bootstrapped under approved Platform/Merchant onboarding. Protected Owner is **not** a universal Platform-to-Tenant access bypass.

### 4.4 Role updates and deletion

A Role edit changes effective grants for **all current assignees**. New requests must reflect the change, including when Redis still holds a prior permission set. Impact Preview is helpful UX but is not authorization.

Deleting an assigned Role is denied until safe reassignment/audit. Avoid direct mutation of built-in templates; create a Custom Role copy. Updating Registry with new Permissions must not implicitly grant them to assigned Roles.

### 4.5 AUTHZ-10 narrowly delegated approver

Only **Approve Manual Discounts** may be delegated directly to one named member as a narrowly approved exception. Conditions:
- Verified authorized grantor and target, correct Tenant and membership.
- Recorded grant/revocation, explicit scope and appropriate audit.
- No self-approval of the recipient's own over-limit request.
- Approval maximum and optional Role ceiling remain subject to **D-085** and unresolved per-user inheritance semantics.
- Cannot bypass D-075…D-077 hard stacking ceilings or other Pricing rules.
- Does not automatically confer ability to create discounts, Refunds, manage other Roles or gain All Locations.
- Must be revoked/fail closed when relevant current membership or scope becomes invalid.

Do not create a general "direct_permissions" editable screen as a pretext for this one exception without further approval.

### 4.6 Non-atomic Central/Tenant changes

Central and each Tenant PostgreSQL DB do not share an ACID transaction. Employee invitation/Role initialization must finish the correct Tenant-local grants before activating Central membership. Partial failure stays inaccessible and retry-safe. Do not treat independent database writes as atomic.

---

## 5. Explicit Location Scope — AUTHZ-03

### 5.1 Role says what; Location says where

Location Scope is distinct from the Role:

| Scope | Semantics |
| --- | --- |
| Specific Locations | Explicit authorized set of IDs within the correct Tenant |
| Explicit All Locations | Separately granted all-Location scope, subject to current business availability |
| Empty or invalid scope | **No** Location-bound authorization, never inferred All Locations |

Not every Tenant-wide operation is Location-bound. A Feature can be Tenant-wide where its contract defines that scope, but a Location-bound action requires matching Location permissions.

### 5.2 Newly created Locations and delegated scope

- New Branch C does **not** become part of an employee's Specific {A,B} Location assignment.
- Explicit All Locations covers new **eligible** Locations, subject to Role, entitlement and operation rules.
- A manager assigned/delegable in A/B cannot grant C unless separately authorized to delegate C.
- Changing Location Scope must invalidate effective permissions for following Requests, exports and user-initiated Jobs.
- An API-supplied locationId only selects a target; it is never proof of permission.

### 5.3 Query and resource enforcement

Apply authorized Tenant/Location/Customer ownership predicates at SQL query time **before filters/pagination/search/counts/aggregations**, including related records. Do not fetch everything and filter in PHP or Next.js.

For a directly addressed Order/File/Customer record, check a Laravel Policy (or equivalent verified Resource authorization), not only an unscoped Find by ID.

~~~php
// Illustrative only; assumes a verified Specific Locations scope.
Order::query()
    ->whereIn('location_id', $allowedLocationIds)
    ->paginate(20);
~~~

All Locations requires **explicit separate handling**; an empty array must not remove the filter by accident. Some Resources have indirect Location links and need owner-defined joins or other business rules.

### 5.4 Transfers and multi-Location operations

For inventory transfer A → C, verify the source, destination and any other affected Location plus the operation permission and business constraints. A-only permission does not grant access to C. A future request/accept transfer workflow with separate delegates requires its own approved Feature policy, not an implied exception.

### 5.5 Exports and private file access

Scope exports/reports/private files and asynchronous Jobs the same as list/read endpoints. Verify the initiating actor's current Role, Location and permission **before performing** a delayed sensitive Job and again before disclosing private export results as appropriate. A revoked Branch must not remain accessible via a previously generated download URL without the required current access proof.

---

## 6. Sensitive Permissions & Business-Owned Approvals — AUTHZ-04

### 6.1 Separate approval from execution

The Identity/Authorization layer reports which actor is entitled to request or approve a class of operation. The owning business Module decides the specific eligible amount/status/approval requirement, and its transaction layer enforces safe effects.

| Area | Dedicated authority and rules |
| --- | --- |
| Manual Discount apply | Permission and optional approval-free percentage by Role |
| Manual Discount approval | Separate Approve Manual Discounts, non-self exception decision, optional ceiling |
| Issue Refund | Separate Refund permission and optional financial amount limit |
| Approve Refund above limit | Proper independent approver and sufficiently high authority |
| Inventory adjustment | Specific adjustment permission, Location, reason/evidence as Feature |
| External Sale correction | Specialized correction authority, evidence/audit; D-178 does not force second actor always |
| Sensitive exports | Separate permissions plus ownership/privacy/Location scope |
| Role/Location change | Role/Scope management and bounded delegation; security audit |

**No universal Maker–Checker for every high-risk business operation.** Apply independent approvals exactly where the approved D-series requires them; an within-limit action may be executable without a second person under its business rules.

### 6.2 Manual Order Discounts — D-078…D-088

- **D-078:** Manual Discount requires the correct Permission; a Role may have an optional approval-free percentage. Over-limit must be approved by an appropriately authorized actor.
- **D-079:** Approval is an in-app request and decision on the approver's **own logged-in account**, including remote approval. No same-device Manager PIN requirement.
- **D-080:** Pending over-limit discount keeps an unfinalized draft from confirmation and collection at the proposed discounted total.
- **D-081:** Rejection keeps the draft; employee can remove, reduce or resubmit and must get customer acceptance of final amount.
- **D-082:** Approval permits the particular draft discount but does **not** finalize/confirm/collect automatically.
- **D-083:** Subsequent financial changes to total or effective discount invalidate prior exception approval; recalculate and obtain new approval when still above no-approval limit. Non-financial-only changes do not automatically revoke approval.
- **D-084:** Separate **Approve Manual Discounts** permission (Role or approved individual delegation), not "Manager" Role name; **the requester cannot approve their own exceptional request**.
- **D-085:** Optional Role maximum approval percentage for others, independently of one's own approval-free granting percentage; no invented defaults.
- **D-086:** Fixed Discount effective % = fixed monetary discount ÷ remaining eligible merchandise amount immediately before Manual Discount × 100; prior eligible stacked discounts reduce the denominator.
- **D-087/D-088:** Exclude shipping and tax from the relevant Manual Discount limit base.

No approval can bypass a hard combined discount ceiling under D-075…D-077 or approved stacking/status rules. No platform-default numeric 10%/20%/30% cap is approved merely because a document uses it as an example. Paid/confirmed Order changes require separate Feature rules; this standard does not silently approve them.

### 6.3 Refund Authorization — D-126…D-135

Refund authorization is separate from Order management, Discounts and final payment-provider execution.

| Decision | Approved treatment |
| --- | --- |
| D-126 | Optional Role monetary issue/approval limits, proper independent approval on exceedance |
| D-127 | Per-Order **cumulative** exposure, including prior and pending/unresolved proposed refunds, without double counting |
| D-128 | Optional daily per-actor/Role cap across separate Orders |
| D-129 | Daily cap evaluated by **Store-local calendar day**, not rolling 24 hours |
| D-130 | Day attribution at **final financial authorization**, not provider settlement |
| D-131 | Revalidate material financial/state/permission changes or exceeded validity window; not auto-expired at midnight |
| D-132 | Merchant-configurable optional pre-provider approval validity window, **no default numeric duration** |
| D-133 | Later local-day reauthorization rechecks that day's limit, without a second Refund or rewriting original approval day |
| D-134 | Active Refund Exposure for unresolved Approved/Pending/Processing across days, separate from daily cap |
| D-135 | Optional exposure **alert** threshold; not automatic refund block |

A pending/unknown provider operation is **not** evidence of failure, and reapproving must not send another Refund until the original state is properly resolved. Money/limits use approved currency minor units, not unsafe float or imagined cross-currency equality. Legal rights remain binding.

### 6.4 Corrections and other sensitive operations

D-178 allows an appropriately authorized actor to approve certain External Sale corrections **themselves**, with proper evidence and audit. Forcing every such correction through two persons would contradict approved business policy. Conversely, a discount exception requiring a separate approver cannot be self-approved.

Inventory Stock Adjustment/Transfers, shipping fee changes, customer data exports and financial corrections have explicit specialized permission/Location/business rules and owning Module audit. Do not build a single catch-all Manager override that bypasses them.

### 6.5 Approval authority vs actual effect

A User holding a permission may still lack authority for a particular Resource, amount, store, stage, Location or prior approval state. Approval of a discount is **not** Order confirmation; approval of a Refund is **not** payment sent or received; an authorized stock operation is **not** permission to skip availability/reservation invariants.

---

## 7. Enforcement via Middleware, Gates/Policies, Queries and Actions — AUTHZ-05

### 7.1 Merchant API request order

The mandatory fail-closed Merchant Dashboard path is:

1. Authenticate the correct **Central Workforce** Sanctum session.
2. Resolve Tenant URI using **trusted Central Tenant Directory**, verify Host agreement.
3. Check current Central account and **active membership in the requested Tenant**.
4. Check Tenant lifecycle status and relevant SaaS entitlement.
5. Initialize the verified Tenant PostgreSQL context.
6. Evaluate Tenant-local Role/Permission and Location/Resource ownership.
7. Run owning Module Action; recheck sensitive authority and business constraints at protected effect time.
8. Clear Tenant Context, connection and transient auth state, including on errors.

No global shared Session "active tenant" decides Request authority. Tab A (Store A) and Tab B (Store B) must stay isolated by their explicit Tenant URI. No fallback to Central DB or previous Tenant when verification fails.

For the **Storefront/Customer** surface, use the correct Tenant-local Customer guard and verified storefront subdomain/session binding. Do not apply a workforce membership flow to Customers, and do not allow Customer Sessions to resolve as Workforce.

### 7.2 Responsibilities

| Layer | Owns |
| --- | --- |
| Authentication Middleware | Correct account/guard/session |
| Tenant Resolution & Membership Middleware | Verified target Tenant, membership, status and Context |
| Entitlement check | Feature availability to Tenant |
| Permission Gate/Policy | Tenant-local Permission, Location and Resource access |
| Scoped Eloquent Query | Authorized subsets before pagination/search/aggregation |
| Application Action | Verified use case and business/transaction orchestration |
| Domain Rule / owning contract | Financial, stock, Order and other domain invariants |

Do not place the whole permission engine into a single giant Middleware, or repeat the entire verification chain in every Controller. Do not implement a new user-configurable ABAC framework. The actual Laravel 13 / Stancl / Sanctum Middleware ordering remains TO TEST.

### 7.3 IDOR/BOLA and error contracts

A guessed Order ID, Customer ID, File ID or Tenant slug does not authorize its read or update. Direct Resource actions must verify correct Tenant, guard/actor, Permission, Location, ownership and business eligibility.

Use stable ApiResponse error mapping. **401** for unauthenticated, **403** for known forbidden operations, **404** when hiding unauthorized Resource existence is appropriate, **422** for validation failures. Never return HTTP 200 for a rejected protected operation.

For list endpoints, apply authorization scope before user filters, sorting, aggregate counts or pagination. User-provided filter[locationId] can **narrow** permitted scope but cannot expand it.

### 7.4 TOCTOU and sensitive writes

A permission can be revoked between an initial read and the final stock/Refund/discount operation. For sensitive Actions, revalidate relevant current authority at a protected commit/transaction boundary as needed, with approved locks/conditional updates for mutable business facts.

Ordinary small GETs need not create heavy transactions. User-initiated deferred Jobs must re-evaluate authorization at execution; system-owned follow-up to already committed events can continue under verified system authority per QJ standard.

---

## 8. Safe Redis Authorization Cache — AUTHZ-06

### 8.1 Source of truth

Central membership/account status lives in **Central PostgreSQL**. Tenant Roles, Location grants and narrow individual discount approval authority live in the **correct Tenant PostgreSQL**. Redis may cache compiled permission snapshots, but **never decides whether stale permissions still grant access**.

Laravel-native Cache APIs and suitable Stancl isolation should be used where tested; no custom Cache Framework is mandated. Some Role checks may be faster/safer as indexed PostgreSQL queries without Redis. Use measured need.

### 8.2 Version-based snapshots

A cached permission set is valid only when matched against **current authoritative authorization state/version**. Changes to Role, Role assignments, Location scope or narrow delegated approval must update the appropriate durable version(s) in the same owning PostgreSQL transaction.

Illustrative logical cache keys (not a fixed raw naming contract):

~~~text
authz:tenant:A:actor:15:role:7:version:12
authz:tenant:B:actor:15:role:4:version:6
authz:central:platform:actor:15:version:4
~~~

A safe design may use Role-level Version plus member assignment and Location-specific versions. Exact schema, key, TTL and version math remain TO DESIGN/TEST. A version only in Redis cannot be guaranteed to update atomically with a committed PostgreSQL change.

### 8.3 Mandatory invalidation behavior

- A Role permission removal denies new requests from **all** affected employees.
- Location grant removal denies new requests/exports for that Location.
- Membership A revocation denies A and does not automatically log the actor out of independent B membership.
- Newly registered Module Permissions are not silently granted to old Roles.
- An old cache read/refill after version increment cannot become a current positive grant.
- If the current PostgreSQL-backed source/version check fails, **deny**; do not use "last-known-good" positive grants.
- TTL is useful for memory/freshness of read hints, **not** a substitute for immediate revocation.
- Redis workload namespaces separate environment/Central/Tenant/actor/Role/version; no global Cache::flush for a local permission change.
- Avoid contaminating shared Horizon queue transport with Tenant Cache prefixes.

### 8.4 Runtime considerations

Measure auth query counts, snapshot recomputation, actual Redis hits, memory and effect of invalidations. Verify outages/concurrency, worker reuse and recovery. Do not claim auth cache is always faster than PostgreSQL without benchmarks.

---

## 9. Platform and Scoped Support Access — AUTHZ-07

### 9.1 Platform is Central-only by default

A Platform Admin can act on explicitly approved **Central** Platform/Tenant Directory, provisioning, SaaS subscription, entitlement and support-management data. That **does not** grant access to a merchant's private Orders, Customers, Refunds or Inventory.

No universal rule of this form:

~~~php
// Forbidden conceptual authorization shortcut.
if ($user->isPlatformAdmin()) {
    return true;
}
~~~

Similarly, a Tenant Owner or Employee does not become a Platform Admin via their Tenant Role.

### 9.2 No support-ticket-as-tenant-login

A Support ticket gives authorized Platform Support access to the permitted ticket and safe diagnostic facts, **not** all Tenant DB records or the ability to impersonate Store Owner. No generic Login-as-Merchant, read-all-admin backdoor or arbitrary cross-Tenant SQL is approved.

### 9.3 Future exceptional Support workflow

If a later Support Feature requires real Tenant data access, it must get separate authorization and specify:
- Named verified Platform actor and current Support permission.
- One trusted Tenant/target and reason/ticket reference.
- Explicit least-privilege operation/Resource/Location scope.
- Read-only by default; any write separately authorized by the owning Business Module.
- Bounded duration, revocation, consent/legal/privacy basis as applicable.
- Audit of grant/use/revoke, trusted correlation and Tenant cleanup.
- Proper Module-owned Public Contract/Action instead of arbitrary direct DB mutations.

These are **future design conditions**, not authorization to create Support impersonation today. No default support-grant duration or blanket Customer export is selected.

### 9.4 Cross-context isolation

Any authorized Support workflow verifies Central platform authority, the exact intended Tenant and approved Module scope independently. Changing Tenant ID or Host cannot turn a Store A support grant into access to Store B. Tenant DB connections must be cleared following exceptions and reused workers.

---

## 10. Backend-Driven Next.js Capabilities — AUTHZ-08

### 10.1 Laravel remains the security boundary

Next.js can show/hide links, buttons, menu items, Location choices and helpful action states according to verified current effective capabilities supplied by Laravel. This is **UX**, not a replacement for Backend authorization on every protected API call.

No role-name hardcoding such as "manager always may refund". With Custom Roles and financial limits, a Manager label does not certify eligibility for a specific Refund.

### 10.2 Illustrative abilities API

Example only; **not** an approved endpoint or final contract:

~~~http
GET /api/v1/tenants/store-a/me/abilities
~~~

~~~json
{
  "success": true,
  "message": "Abilities loaded",
  "data": {
    "role": "Sales",
    "permissions": ["orders.view", "orders.create"],
    "locations": {"all": false, "ids": [1, 2]},
    "features": {"inventory": true, "advancedDiscounts": false}
  }
}
~~~

Final route, JSON fields, permissions and feature identifiers belong to the respective API/Feature contract and privacy review. Capabilities may be helpful in Next.js memory or per-request state, but cannot be stored in a shared CDN/SSR cache that leaks permissions between Users/Tenants.

### 10.3 Multi-tab and revocation UX

- Load/store effective capabilities separately for **each verified Tenant route/context**, never a global mutable session-wide activeTenant grant.
- Refetch on Session resume, Tenant change, privileged action, or stale/denied permission as appropriate.
- Frontend UI may display an old button briefly, but once authorization is revoked the **Backend denies the action**.
- Treat 401 as authentication issue, 403 as authorization failure, 404 as privacy-aware missing/unavailable Resource; don't Logout all devices on every 403.
- Use resource-specific action availability hints from owning Modules for sensitive operations rather than recalculating Refund amounts/approval in JavaScript.
- Customer/Guest UI follows Tenant-local Customer identity and Resource ownership, **not** Workforce Role capabilities.

---

## 11. Focused Authorization Audit — AUTHZ-09

### 11.1 Events and ownership

| Event | Owns audit |
| --- | --- |
| Central Membership activation/suspension/revocation | Central Identity |
| Central Platform role/permission change | Central Platform security |
| Tenant Default/Custom Role creation/edit/delete | Tenant Identity & Access |
| Tenant Role assignment and grant/revoke | Tenant Identity & Access |
| Tenant Location grant / All Locations change | Tenant Identity & Access |
| Individually delegated Approve Manual Discounts grant/revoke | Tenant Authorization |
| Security-relevant cross-Tenant denial/attempt | Appropriate rate-bounded security log/audit |
| Discount approval request/decision | Pricing & Promotions |
| Refund authorization and execution state | Returns & Refunds / Payments |
| Inventory adjustment/financial correction | Business-owning Module |
| Future Support access grant/use/revoke | Central Support audit, plus scoped Tenant records if Feature requires |

Record proportional actor/target, verified Tenant/Location, event and result, meaningful before/after, reason when appropriate, timestamp and request/correlation ID. Do **not** duplicate every UI click or copy whole financial ledgers into Authentication/Authorization.

**Never log:** Passwords, Session Cookies/IDs, Password Reset or Invitation tokens, raw API secrets, or unnecessary Customer PII. Secure audit access and retention. Ordinary database audit is not automatically cryptographically tamper-proof against privileged administrators.

### 11.2 Auditing access to audit

Reading/Exporting audit events is itself a privileged operation requiring Tenant/Platform scope, privacy rules and explicit authorization. Ability to edit Role configuration does not imply ability to alter past audit or business evidence.

---

## 12. Real Integration Testing Matrix — AUTHZ-09

### 12.1 Test environment requirements

Minimum meaningful environment:
- One real **Central PostgreSQL** database.
- **At least two separate Tenant PostgreSQL databases**.
- Real **Redis** Permission Cache and revoked-version tests.
- Laravel 13/Sanctum/conditionally verified Stancl Middleware and relevant guards.
- Tenant A and B with overlapping Local IDs, different roles, users and Location assignments.
- Multi-tab/representative Next.js behavior.
- Reused Horizon Worker Tenant A → Tenant B → Central and failure/retry variations.

Mocks and SQLite-only tests are useful locally but **not sufficient** to claim physical Tenant isolation, permission revocation or multi-connection correctness.

### 12.2 Required security cases

| Test | Expected |
| --- | --- |
| Unknown permission or unauthorized Role creation | Deny |
| Employee tries to grant more than delegable Permissions | Deny |
| Employee tries to expand delegated Location scope | Deny |
| Employee self-assigns a more privileged Role | Deny without independently approved process |
| Two simultaneous active Roles in same Tenant | Deny under MVP rule |
| Last active Owner removed by concurrent requests | Deny |
| New Registry Permission introduced | Old assigned Roles do not auto-gain it |
| Individual Approve Manual Discounts authorized and revoked | Correct named-user exceptional grant only; audited |
| Individual approver attempts self-approval | Deny |
| No specified individual approval ceiling/fallback | Never silently grant unlimited exceptional authority |
| Employee only belongs to Tenant A and uses URI of B | Deny |
| Employee has A and B, opens separate browser tabs | Correct per-Tenant Roles; no session-scoped Tenant switch |
| Tenant A positive Redis grant replayed in B | Deny |
| Membership A revoked but Membership B still valid | A deny; B unaffected |
| Tenant Role grant changed with Redis cache populated | New request uses current grants, not stale cache |
| Employee Location B revoked with Session active | B read/write/export deny |
| Specific Locations A/B and newly added Branch C | C not automatically granted |
| Employee explicitly granted All Locations | Subject to permission and resource/business eligibility |
| Order ID or File ID changed to another scope | Deny IDOR/BOLA |
| Orders list search/count/pagination with branch filter | Only authorized rows and totals |
| Stock transfer source A allowed, destination C denied | Deny unless separately approved workflow |
| Manual Discount above Role limit | Require independent approved in-app decision |
| Approved discount financial value changes before finalization | Revalidate; reapprove if necessary |
| Approval bypasses hard discount stacking ceiling | Deny |
| Refund exceeds cumulative per-Order limit | Require appropriate independent authority |
| Refund crosses optional daily limit/time-zone boundary | Enforce D-128…D-135 |
| Refund already sent or Pending then reapproved | No duplicate Refund effect |
| D-178 appropriately authorized self-approval correction | Permitted where business rule allows; audited |
| Platform Admin accesses arbitrary Tenant Order | Deny |
| Support grant unapproved/expired/wrong Tenant | Deny |
| Customer reads other Customer's Order | Deny |
| User-initiated export with authorization revoked before execution | Deny before disclosure |
| User-initiated Job with stale Tenant context | Re-resolve current trusted context, no leak |
| Redis outage with old cached positive grant | Fail closed when current version/authority unavailable |
| Tenant A → Tenant B → Central reused Worker | No cached connection/actor/Location crossover |
| Role and financial audit data contains secrets | Tests fail; no sensitive credential leakage |

Test actual concurrency, invalid Host/URI, wrong Resource ID, pagination totals, high-risk operation commit-time recheck and cache invalidation with actual Redis behavior. Do not claim test pass when they have not been run.

### 12.3 Security release blockers

Block relevant code changes/PRs for:
- Cross-Tenant data exposure, Session/Permission leakage or unauthorized connection.
- IDOR/BOLA for Orders, Customer details, private files, export or reports.
- Privilege Escalation through Role/Location/delegation/Owner handling.
- Positive stale cached grants usable after effective revocation.
- Approver acting on their own exceptional Discount request when separate approval required.
- Unauthorized Refund approval/execution, duplicate financial effect or bypassed approved limits.
- Universal Platform Admin Tenant bypass or unapproved Support impersonation.
- Sensitive user-initiated Job executed after revoked authority when a current check is required.
- Secret-bearing capabilities/error/audit leaks.

Ordinary UX-only stale button issues can be classified separately when Backend is secure. Passing static analysis and mocked happy-path tests does not make a Critical security bypass acceptable.

### 12.4 Intended CI / PR evidence

For relevant Feature PRs:
- Relevant approved Specs, Permission Registry/Role/Location change review.
- Pint and targeted static analysis where configured.
- Applicable Gate/Policy/Action feature tests.
- Real PostgreSQL + Redis + Sanctum/Stancl integration evidence for changed critical boundaries.
- Discount/Refund rule tests and data/financial integrity tests where affected.
- Clear audit/PII/secret logging assessment and request correlation.
- Reviewer approval and explicit limitations.

**GitHub Actions / branch protection / actual application tests are not claimed already configured.**

---

## 13. Implementation Patterns and Forbidden Shortcuts

### 13.1 Example: Staff Tenant request

~~~http
GET /api/v1/tenants/store-a/orders?filter[locationId]=5&page=1&perPage=20
~~~

The URI chooses Store A as **selector**, not as authorization proof. Laravel first validates Central user, membership, trusted Tenant, entitlement and role; then applies the allowed Location filter and Resource visibility before pagination. If branch 5 is not allowed, supplying it via filter does not grant permission.

### 13.2 Illustrative Policy + Action

~~~php
// Assuming preverified correct Tenant and authenticated workforce context:
Gate::authorize('confirm', $order);

// The owning Orders Feature Action still checks Order state, pricing,
// permissions and Inventory/financial rules at the protected effect point.
$confirmOrderAction->execute($validatedInput, $authorizationContext);
~~~

The example is not a complete running Policy or API contract. A Gate does not replace atomic Reservation, correct Pricing or transaction-safe Refund idempotency.

### 13.3 Explicitly forbidden patterns

~~~text
BAD: isPlatformAdmin() returns true for every Tenant Order operation.
BAD: user.role === "manager" treated as Refund financial approval.
BAD: Same Global User permissions used regardless of requested Tenant.
BAD: Multiple assigned Roles or arbitrary User-level allow/deny enabled silently in MVP.
BAD: New Registry Permission auto-added to all old roles on deployment.
BAD: Employee grants a Permission merely because they can use it personally.
BAD: User creates a powerful Role then assigns it to self.
BAD: Last active Owner removed by ordinary staff management.
BAD: Empty Location grants interpreted as all Locations.
BAD: Frontend filters Orders after Backend sends unauthorized records.
BAD: User-supplied Location ID is assumed to be permission proof.
BAD: Direct Order ID or download path skips Resource ownership verification.
BAD: Source-only transfer check ignores destination Location scope.
BAD: Redis cached grants used because source version check failed.
BAD: TTL expiry used as the sole permission revocation mechanism.
BAD: Global Cache::flush() for Tenant A permissions.
BAD: Pending Refund counted twice or approved Refund automatically sent twice.
BAD: Manual Discount individual approval grant bypasses self-approval rule.
BAD: Individual approval ceiling absent therefore system assumes unlimited authority.
BAD: D-178 correction forced into universal two-person approval workflow.
BAD: Support ticket or Platform Super Admin implies unrestricted Tenant access.
BAD: The standard's publication claimed to prove running Laravel tests.
~~~

---

## 14. AUTHZ-10 Final Cross-Document Review

| Source | Review outcome |
| --- | --- |
| IA-01 / IA-04 | Central Workforce and Tenant-local RBAC/Location consistent |
| AUTH-01/08 | Correct guard/session; Central membership revocation and Tenant grants consistent |
| AS-01 / AS-04 | Explicit Merchant Tenant URI; authorized scope before pagination consistent |
| CS-03 / CS-09 | Laravel Actions, Gates/Policies, risk-based tests consistent |
| TA | Conditional Stancl and trusted Tenant boundaries remain unchanged |
| CR-04 | PostgreSQL authority; positive Permission Cache cannot outlast revocation |
| QJ | User-initiated delayed work rechecks current authority; committed system obligations distinct |
| D-078…D-088 | Discount approval, non-self, Role ceiling, hard combined limit preserved |
| D-126…D-135 | Refund issue/approval, cumulative/daily limits, reauthorization and active exposure preserved |
| D-178 | Self-authorized correction allowed where specifically approved, not blanket Maker–Checker |
| **AUTHZ-02 vs D-084** | **Resolved by AUTHZ-10 A:** one Role remains, only narrow audited named-person Approve Manual Discounts grant |
| D-201 | Remains OPEN; no silent decision |
| BA-16 | Remains DEFERRED |
| BA-30 G3 | Live Pilot gate remains BLOCKED |

### 14.1 Details still TO SPECIFY / TEST

| Item | State |
| --- | --- |
| Final Permission keys and Module Registry registration | Feature Spec |
| Exact Default Roles and ownership recovery workflows | Feature Spec |
| Exact Tenant DB Role/grant/Location/delegation tables and indexes | Feature Schema |
| Exact delegation matrix and safe Self-change rules | Identity & Access Feature |
| Named approver D-084 optional ceiling inheritance and fallback semantics | **Requires explicit Feature decision before enabling** |
| Default financial limits per Role, currency rules, approver workflows | No values invented; owning Feature / Merchant settings |
| Redis key composition, role versions, TTL and version reads | TO DESIGN/MEASURE/TEST |
| Support data access, grant duration, merchant consent and UX | Separate approval, NOT implemented |
| Final abilities API and response contract | API / Identity Feature |
| Postgres Central+2 Tenants + Redis integration and CI | NOT YET VERIFIED |
| D-201 alternative Location follow-up marker | OPEN |
| BA-16 backup and restore plan | DEFERRED |
| BA-30 G3 recovery/Pilot readiness | BLOCKED |

### 14.2 Publication authorization

The owner accepted AUTHZ-01 through AUTHZ-09 Option A, then approved AUTHZ-10 Option A as the explicit narrow D-084 exception and asked that this **document** be published to the existing development branch.

Publication **does not** install packages, implement application code, add migrations, alter the main branch, deploy services, execute security tests, or make live Pilot safe.

---

## 15. Pre-Implementation / PR Checklist

- [ ] Feature Spec and D-series rules checked for any final Permission/approval decision.
- [ ] Correct Central Workforce account and active Tenant Membership for Merchant API.
- [ ] Trusted Tenant from explicit URI / Directory with correct Host and status.
- [ ] Tenant-local single active Role; no generic direct User overrides.
- [ ] Default Role templates and Custom Role management preserve intended grants.
- [ ] Delegation authority and Location bounds validated for grants/assignments.
- [ ] Protected Owner and last active Owner preserved under concurrency.
- [ ] Individually delegated Approve Manual Discounts only under AUTHZ-10/D-084, with ceiling semantics resolved before use.
- [ ] Unknown/new Permission never auto-grants existing Role.
- [ ] Tenant/Location/resource ownership checked before API reads, search, exports and writes.
- [ ] Specific/explicit All/empty Location semantics handled safely.
- [ ] All affected Locations checked on transfers and other multi-site operations.
- [ ] Sensitive Actions revalidate current authority at the right commit point.
- [ ] Discount approvals obey D-078…D-088: non-self, scope, ceiling, financial changes and no auto-confirmation.
- [ ] Refund approvals obey D-126…D-135: cumulative, daily, revalidation and duplicate prevention.
- [ ] D-178 correction workflow preserves permitted self-authorization when applicable.
- [ ] Redis Cache uses verified current PostgreSQL version/status; stale grants denied.
- [ ] Platform support does not impersonate Tenant owners or bypass APIs.
- [ ] Next.js abilities are only UX hints, correctly scoped by Tenant/user.
- [ ] Central/Tenant security and business audit in owning modules with secret redaction.
- [ ] Real Central + two Tenant PostgreSQL DBs + Redis integration tests performed.
- [ ] Reused Horizon workers and failed Tenant Context cleanup tested.
- [ ] Critical Authorization/IDOR/financial bypasses block merge.
- [ ] No CI/Test/Deployment success claimed without evidence.
- [ ] D-201 stays OPEN; BA-16 remains DEFERRED; BA-30 G3 remains BLOCKED.
- [ ] No unauthorized code, schema, environment, production or main branch changes.

---

## 16. Final Approved Authorization Position

E-commerce SaaS V2 applies **Module-owned Permission Registry + Tenant-local one-Role RBAC + Custom Roles + explicit safe delegation + independent Location Scope**.

Authorization uses verified Central identity, current Tenant membership, Tenant state and feature entitlement, local Role/Permission/Location, resource ownership and business invariants. Laravel Middleware, Gates/Policies and owning Module Actions enforce these conditions; queries apply permitted scope **before pagination** and high-risk jobs/commits revalidate current authority.

**AUTHZ-10's sole controlled exception:** an authorized and audited direct named-person delegation of **Approve Manual Discounts**, preserving D-084/D-085 and no self-approval. It does not authorize arbitrary direct User Permissions.

Redis optimizes effective grants **only under trusted current PostgreSQL-backed status/version validation**. A stale positive grant never restores revoked authority. Platform Admin is **not** an all-Tenant Super Admin; any future Support cross-Tenant access requires a separately approved, minimal, expiring and auditable workflow. Next.js reflects Backend-calculated abilities for usability; Laravel always makes the final decision.

Audit, real PostgreSQL/Redis integration evidence and security-blocking PR gates remain required for implementation. **Approval of this document is not proof those controls are implemented or tested.**

**AUTHZ-01…AUTHZ-10 APPROVED — DOCUMENTATION ONLY. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.**

**End of Authorization Standards baseline.**
