# E-commerce SaaS V2 — Identity & Access Architecture

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Target Path:** `docs/01-architecture/identity-access-architecture.md`  
**Version:** 1.0 — Approved Identity & Access Architecture Baseline  
**Prepared:** 2026-11-17  
**Branch:** `development`  
**Architecture Decision Status:** **APPROVED — IA-09 (A)**  
**Implementation Status:** **Not implemented or validated**

> **Reading rule:** This document records the approved MVP-first direction for IA-01 through IA-09. It is an architecture baseline, **not** an implemented authentication system, database schema, API contract, security certification, or permission to launch. Integration and release gates remain mandatory; no MFA is included in the MVP.

---

## 1. Purpose, Authority and Boundaries

Define a small, secure and testable Identity & Access design for the Laravel 13 API-first Modular Monolith and Next.js web surfaces, using PostgreSQL database-per-tenant isolation, Laravel Sanctum browser sessions and the conditionally selected `stancl/tenancy v3.10.1`.

This document should be read with:

- [Backend Architecture](./backend-architecture.md) — BA-02/03, BA-04/12/13/14, BA-25 and system-wide security requirements.
- [Tenancy Architecture](./tenancy-architecture.md) — trusted tenant resolution, Central/Tenant connections, session and worker isolation, conditional Stancl validation.
- [Module Architecture](./module-architecture.md) — Identity & Access module ownership, route surfaces, authorization and cross-context orchestration.
- [Data Architecture](./data-architecture.md) — Central/Tenant record placement, identifiers and cross-database constraints.
- [Project Overview](../00-overview/project-overview.md) and [Business Discovery Handoff](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) — approved MVP customer, staff, discount and refund rules.
- [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) — outstanding approvals and go-live requirements.

Previously approved business rules govern the underlying commercial behavior. This baseline does not redefine coupon eligibility, discount/refund approval thresholds, tenant subscription rules, or the scope of the initial Pilot. If a future specification exposes a conflict, surface it for review rather than silently overriding an approved decision.

**Non-goals:** a custom identity platform, enterprise IAM, SSO, JWT browser authentication, MFA, dynamic authorization policy engine, global cross-merchant shopper directory, detailed table definitions or final permission keys.

## 2. Approved IA Decisions

| Decision | Approved option | Architectural direction |
| --- | --- | --- |
| **IA-01** | **A — Unified Central Workforce Identity** | One Central identity model for Platform Admin and merchant workforce; separate Platform authorization, Central tenant memberships and Tenant-local permissions. Storefront customers remain Tenant-local. |
| **IA-02** | **A — Host-Scoped First-Party Sessions** | Laravel Sanctum session cookies and CSRF; explicit staff/customer guards and providers; same-origin Next.js-to-API routing via reverse proxy; host-only cookies and verified Tenant session isolation. |
| **IA-03** | **A — Explicit Tenant-Scoped Requests** | Central membership and tenant state verified per request; active tenant identified explicitly, with multi-tab safety. Last selected tenant is a UI preference only. |
| **IA-04** | **A — Tenant-Local RBAC + Location Scope** | Defined Permission Registry; default and custom roles; Laravel Gates/Policies and business rules; separate Location, entitlement and sensitive approval checks. |
| **IA-05** | **A — Secure Invitations and Membership Lifecycle** | Explicit invitation acceptance; safe local access initialization before activation; tenant-specific suspension/revocation without deleting Central identity. |
| **IA-06** | **A — Simplified Laravel Native Security, amended** | Email/password, hashing, scoped password recovery, login throttling, session management and invalidation. **No MFA for any account type, including Platform Admin, in MVP.** |
| **IA-07** | **A — Independent Customers + Lightweight Guests** | Isolated Tenant customer accounts, permitted guest checkout without mandatory Customer Profile, no automatic account/order linking, registered-only coupon redemption. |
| **IA-08** | **A — Focused Audit + Risk-Based Validation** | Central/Tenant audit of material actions and release-blocking isolation, authorization and revocation tests. No custom security framework. |
| **IA-09** | **A — Approved Lightweight Baseline** | IA-01…IA-08 are the approved MVP architectural direction; integration results, implementation details and Pilot gates remain subject to proof. |

## 3. Identity Ownership and Separation — IA-01

**Central PostgreSQL database owns:**
- Platform administrator identities and shared merchant staff login identities under the Central workforce identity model.
- Explicit merchant/tenant membership records and their lifecycle.
- Platform authorization state, administrative operations and Central audit.
- Tenant Directory and SaaS entitlements through their respective owning modules, not through Identity & Access.

**Each independent Tenant PostgreSQL database owns:**
- Tenant-local staff roles, permission assignments and Location scopes.
- Independent registered storefront customer accounts and allowed customer profiles.
- Guest checkout/order contact details and tenant commercial records under their owning modules.
- Tenant-local audit of authorization changes and business actions.

The same workforce identity can be a member of multiple merchants with different roles and Location scopes. Central identity alone never implies membership in a merchant; merchant membership never implies Platform Admin access.

The same shopper email may exist independently in two storefronts. There is **no** global shopper account, automatic sign-on, profile sharing or cross-tenant identity merge. Staff identity and shopper identity are distinct authentication contexts.

## 4. Authentication, Guards and Browser Session Isolation — IA-02

**Browser default:** Laravel Sanctum first-party session cookies with Laravel session authentication and CSRF protection. Do not use privileged bearer API tokens, JWT or custom browser identity protocols as substitutes.

**Logical route/authentication surfaces:**

| Surface | Identity source | Authorization/context |
| --- | --- | --- |
| Platform API | Central workforce identity | Explicit Platform permissions; Central-only by default |
| Merchant Dashboard API | Central workforce identity | Active Central membership, valid Tenant Context, local roles/Location and business permissions |
| Public Storefront API | Guest-eligible request | Trusted storefront-to-Tenant mapping; public operations only |
| Authenticated Customer API | Customer identity in the resolved Tenant DB | Tenant-specific customer guard/session and resource ownership |

Use explicit guards/providers and route protection so a customer session cannot resolve as a workforce session, and a workforce session alone cannot access Platform administration. Exact guard names, URL paths and middleware wiring are later implementation contracts.

**Next.js and cookies:**
- Prefer browser **same-origin API requests**, exposed through a trusted reverse proxy even when Next.js and Laravel deploy separately.
- Use host-only session cookies, `Secure` on HTTPS and `HttpOnly` for the authentication session cookie. Never enable a wildcard cross-merchant auth cookie (for example a shared parent-domain session).
- Use a suitable `SameSite` policy (Lax is the starting direction) and Laravel's CSRF token/cookie mechanism. The CSRF token must remain available to the client as required; the session identifier must not be exposed to JavaScript.
- Restrict trusted origins/hosts, proxy forwarding and stateful-domain settings explicitly. Do not accept arbitrary request headers, Referer or client-supplied hostnames as an identity/tenant authority.
- Isolate and bind Tenant customer session state beyond browser cookie scoping; **host-only cookies by themselves do not prevent manually replayed identifiers**. Evaluate Stancl `ScopeSessions` in the chosen Laravel/Redis integration and validate behavior before relying on it.
- Different dashboard/admin hosts may have distinct browser sessions despite sharing one Central workforce identity. This baseline does not promise cross-host SSO.
- Regenerate sessions at login, invalidate on logout, and verify expiry and session recovery behavior across Next.js, reverse proxies and Redis.

## 5. Tenant Membership and Explicit Active Tenant — IA-03

Merchant Dashboard requests carry an explicit tenant-scoped destination (for example a verified route context); tenant identifiers in URLs or request fields are **selectors, never authorization proof**.

A typical merchant request follows this fail-closed order:

1. Authenticate the Central workforce identity using the appropriate browser session.
2. Resolve and validate the requested Tenant against trusted Central Tenant Directory information.
3. Require an active, explicit Central membership for that workforce identity; reject host/context mismatches.
4. Validate the Tenant lifecycle/status and applicable SaaS feature entitlements.
5. Activate the authorized Tenant Context and its correct database connection.
6. Evaluate Tenant-local role/permission, assigned Location, resource ownership and business-specific approval/amount rules.
7. Execute the authorized operation and reliably clear Tenant Context and connections, including on failure.

Membership and effective authorization must be checked for **each relevant request**. The UI may remember the last selected merchant, but it cannot use that preference as a security grant. A user with two authorized merchants can work in two browser tabs safely because each request identifies its intended merchant explicitly. Never switch implicitly to a default or previously accessed Tenant.

Central-only Platform operations do not automatically acquire unrestricted Tenant access. Support or cross-context workflows require their own scoped authorization.

## 6. Tenant-Local Roles, Permissions and Location Scope — IA-04

Use simple **RBAC**, with predefined Permission Registry entries provided through approved module contracts, and Laravel Gates/Policies for per-resource decisions.

- Provide practical default roles and merchant-defined custom roles assembled from allowed registry permissions. Role names and final permission keys belong to feature specifications.
- Assign roles/permissions within the relevant Tenant, linked to the validated Central membership identity. Do not duplicate workforce login accounts per merchant.
- Apply Location Scope independently from the role: access to an operation does not imply access to all branches. An empty/invalid scope must not silently become "all Locations"; broad access is explicit.
- Restrict list/read, write, export, private file download, background operations and cross-Location operations, not merely visible Next.js buttons.
- For stock transfers and other operations involving multiple Locations, validate every Location required by that operation under its owning business rules.
- Entitlements determine whether a merchant has a feature; employee permissions determine whether the actor can use it. These checks are not interchangeable.

**Sensitive actions:** manual discount permission and optional approval-free role thresholds, separate "Approve Manual Discounts" authority with no self-approval, role-specific approver ceilings, and independent refund authorization/amount limits follow approved D-series decisions. Changes to financial order context can invalidate earlier approval as prescribed. Inventory adjustments, financial corrections, customer data exports and privilege modifications receive their own appropriate checks and audit.

No general-purpose user-configurable ABAC/authorization engine is required. Do not impose universal multi-person approvals where the governing business rule does not require them.

### FBR-02-02/03 — Current Central Membership and Authoritative Tenant Grants

Each Merchant request checks the **current** Central workforce identity, current active Tenant Membership, Tenant lifecycle/entitlement and Tenant-local Role/Location/resource authority; high-risk effects recheck current relevant authority close to commit. Central and Tenant databases have **no shared ACID transaction**. PostgreSQL-backed versions/status checks govern any selectively cached permission hints; no Redis stale positive grant can override revocation. Fail closed on missing authoritative checks and clean Context after use.

## 7. Workforce Invitations and Access Lifecycle — IA-05

**Simple invited-membership process:**

1. An authorized merchant actor initiates an invitation for an email, permitted role and Location scope.
2. Create a tenant-specific, time-limited, one-use invitation that can be cancelled or reissued safely. It confers **no active access** by itself.
3. The recipient signs in with their existing Central workforce account or establishes and verifies control of a Central account using the approved email/password flow.
4. After explicit acceptance, initialize and validate Tenant-local authorization assignments.
5. Activate Central membership **only after** required local setup is complete. Failed or partial setup stays inaccessible and recoverable.

The first merchant owner's access is bootstrapped through the authorized merchant onboarding workflow, not a public self-assignment of owner permissions.

**Lifecycle:** invited/pending, active, suspended and revoked are useful conceptual states; invitation cancellation/expiration is handled separately from live membership status. These labels do not mandate a table-per-state or workflow engine.

**Revocation:** block new requests against the affected Tenant immediately through authoritative membership checks; invalidate or refresh stale permission/cache data and revalidate sensitive queued jobs before effects. Suspension/revocation of Merchant A must not delete a Central workforce identity or revoke its independent valid access to Merchant B. A security disablement of the **Central identity** can instead block all workforce access, including Platform access where applicable.

Central and Tenant PostgreSQL databases do not share one ACID transaction. Invitation acceptance, local role initialization and membership activation require an explicit retry-safe cross-context orchestration. Deny access while coordination is incomplete; do not treat an asynchronous cache cleanup as sufficient revocation enforcement.

### FBR-02-17 — Protected Owner Exceptional Recovery

No ordinary Role/Employee operation can remove the last active protected Owner. When normal password recovery is not enough, an explicitly authorized Platform-assisted process may verify independent Merchant ownership evidence and restore or transfer the Owner relationship under a bounded, auditable Central/Tenant workflow. No public self-registration as Owner, automatic Platform impersonation, unscoped private-data access or partial activation is authorized. Previously compromised credentials and sessions remain denied; duplicate/concurrent requests require safe durable reconciliation.

## 8. Passwords, Recovery and Session Management — IA-06

**MVP authentication is email + password for the applicable account type. No MFA/2FA/OTP at login is required for Platform Admin, merchant workforce or storefront customers.** Password-reset links are not a second login factor.

- Use Laravel-native secure password hashing; never store recoverable plaintext passwords.
- Apply rate limits to login, recovery and other abuse-sensitive endpoints; exact numeric limits require Security Standards validation.
- Use scoped Laravel password recovery brokers/stores: the workforce resets one Central identity password; shoppers reset only their account in the correctly resolved Tenant.
- Issue expiring, one-use recovery links and avoid user-enumeration responses. Recovery links must use trusted canonical hosts rather than arbitrary client-controlled Host headers.
- Reset/change of password invalidates relevant old sessions, subject to verified Laravel guard/session handling. Logout invalidates the current session; Central account disablement blocks workforce access across surfaces.
- A merchant membership revocation blocks just that merchant, even if the shared Central login remains valid for other merchants.
- Test global-session invalidation on Redis rather than assuming a password-field change alone invalidates every browser session.
- Do not expose passwords, recovery tokens, session identifiers or secrets in logs, email templates or error output.

The actual session lifetimes, recovery expiration, password policy and throttle limits are Security Standards and feature-level configuration decisions, not invented constants in this architecture.

## 9. Tenant Customers and Guest Access — IA-07

Registered customers authenticate within the approved storefront's **own Tenant Database and host-scoped session**. Their profile, addresses and accessible order history remain Tenant-local; all reads and updates enforce customer/resource ownership.

Guest Online Checkout remains available where the business flow permits it. Save required contact, fulfillment and order evidence with the order without forcing a customer login account or persistent guest profile. **Email is not universally mandatory for guests** solely because transactional order emails exist.

Do not auto-link an old guest order to a newly registered customer based on matching phone/email. Any future claim/link workflow requires explicit proof of ownership and feature approval. A guest order number or phone number alone is not sufficient to disclose private order details.

Coupon redemption in MVP is restricted to registered, signed-in customers of that Tenant and follows the approved one-use-per-customer-per-coupon rule and coupon hold/confirmation policies (D-050/D-051 and subsequent decisions). A guest is not automatically upgraded into a registered shopper to redeem a code. Normal walk-in Direct Sales require no fabricated Customer Profile (D-169).

### FBR-02-13 — Guest Checkout Without Private Guest Self-Service

Approved Guest Checkout does **not** introduce a Guest Customer account or private Guest order tracking, document download, refund-history or order-claim self-service in MVP. Order number, telephone, temporary checkout state or an old URL is not proof of ownership. Merchant staff may handle allowed customer communication/documents through an independently authorized operational process; legal delivery obligations and confirmed history are preserved. Registered customer access stays Tenant-local. Any future Guest claim route requires a separate scoped proof/consent/security decision.

## 10. Focused Security Audit and Data Ownership — IA-08

Keep audit proportional to risk and domain ownership rather than storing every UI click or duplicating whole business ledgers.

**Central audit** includes relevant Platform account/permission changes, workforce identity security events, invitation and membership activation/suspension/revocation, and platform administration.

**Tenant audit** includes local role/permission and Location-scope changes; business-owning modules record significant approvals, discount decisions, refunds, financial corrections, inventory changes and other sensitive effects.

Where appropriate, capture actor, target, Tenant/Location, event type and timestamp, old/new value or meaningful delta, reason, authorization result and correlation/request ID. Protect access, retention and integrity; minimize personal data. Never write passwords, raw session cookies, recovery/invitation tokens or unnecessary private customer details to audit/security logs.

An ordinary audit table does **not** imply cryptographic tamper-proofing against privileged database administrators. The Identity & Access module provides verified actor/authorization context; business modules retain ownership of their financial and operational facts.

## 11. Validation Matrix and Release Blockers — IA-08 / IA-09

Minimum meaningful integration coverage must exercise **one Central PostgreSQL database and at least two separate Tenant databases** with genuine Laravel/Stancl/Sanctum behavior, not mock-only isolation claims.

| Required case | Expected result |
| --- | --- |
| Staff with Merchant A membership accesses Merchant B route, ID, private file or report | Denied; no Tenant B data returned |
| Staff changes URL Tenant ID, Location ID or supplied host/context | No elevation, implicit Tenant switch or unauthorized access |
| Staff working in Merchant A and B in separate tabs | Each request remains in its intended Tenant |
| Membership is suspended/revoked during active session | New Tenant requests denied; unaffected memberships remain usable |
| Tenant role/permission/Location scope changes with Redis cache populated | Effective authorization reflects the change; stale permission cannot grant access |
| Store A customer session/identifier is replayed against Store B | Denied; no account or order leakage |
| Guest attempts to read another customer's private order | Denied without appropriate proof/authorization |
| Workforce session is presented at customer API, or vice versa | Denied without a matching guard/identity |
| Unauthorized Platform actor accesses Platform or Tenant business data | Denied; Platform status alone does not bypass tenant policies |
| Employee approves their own exceptional discount or exceeds refund approval authority | Denied under the approved business rules |
| Password reset link expires, is replayed or is used across identity/Tenant contexts | Denied |
| Tenant A job followed by Tenant B/central job under Horizon | No leaked Tenant Context, database connection, cache or actor authority |
| Invite/local initialization fails mid-process | Membership remains inactive, with safe retry/reconciliation |
| Invalid/unknown Tenant, unavailable Tenant DB or host mismatch | Fail closed, without fallback to a default or prior Tenant |
| Login/recovery abuse and logout/password-reset session behavior | Correct rate limit, CSRF and session invalidation behavior |

**Release blockers:** cross-tenant data exposure, meaningful authorization bypass and continued unauthorized access after required revocation. Sensitive financial/stock integrity violations also remain release blockers under the Backend Architecture.

Validate host-only cookie separation, `ScopeSessions` suitability, trusted reverse-proxy headers, Redis session handling, Central/Tenant PostgreSQL least-privilege roles, Horizon context cleanup, files/exports and relevant Next.js request paths. Package installation or middleware presence alone is not sufficient evidence of correctness.

## 12. MVP Limits, Deferred Details and Pilot Gates

**Explicitly excluded from this MVP architecture:** MFA, SSO, JWT browser auth, global shopper accounts, mandatory guest profiles, shared customer cookies across merchants, custom IAM/ABAC framework, mandatory enterprise device management, full SIEM and generalized HR onboarding.

**Define later in Security/API Standards or Spec Kit features:** exact guard/provider and permission keys; route naming; database tables and indices; CSRF/proxy/session settings; invite and reset-token lifetimes; rate limits; password policy; last-owner recovery safeguards; session invalidation across devices; revocation cache strategy; customer/guest order-access proof; account linking proposals; data retention; and role-specific financial settings already bounded by D-series approvals.

**Conditional integrations and hard gates:**
- `stancl/tenancy v3.10.1` remains **conditionally selected**. Validate Laravel 13 compatibility, PostgreSQL separation, trusted tenant resolution, session isolation and Horizon behavior before final technical adoption.
- **BA-16 remains DEFERRED as an architecture choice but BLOCKING for live Pilot:** verified off-VPS backups and restore drills for Central/Tenant DBs and persistent files, with recovery ownership and justified RPO/RTO.
- Other release gates, including statutory/privacy requirements for real merchants and measured operational readiness, remain in force.
- **D-201 remains OPEN** in Orders/Fulfillment and is not resolved here.

## 13. Final Decision and Implementation Boundary — IA-09 (A)

**APPROVED: Lightweight Identity & Access Architecture Baseline.**

IA-01 through IA-08 are the accepted direction for MVP features and their later specifications. The architecture deliberately uses Laravel-native primitives, Laravel Sanctum, explicit Central/Tenant boundaries, Tenant-local RBAC and focused validation without overengineering or MFA.

This approval **does not** assert implemented functionality, passed tests, finalized table schemas or permission registry, completed security verification, deployment approval or production readiness.

**Next authorized planning step:** translate this baseline into relevant Identity & Access feature specifications, API/security conventions, and risk-based acceptance tests. Application coding, schema migrations, deployment, branch promotion and any additional repository edits require their own explicit authorization.
