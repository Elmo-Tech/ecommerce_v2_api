# E-commerce SaaS V2 — Authentication Standards

**Version:** 1.0 — Approved Authentication Engineering Standards Baseline  
**Prepared:** 2026-11-20  
**Repository:** Elmo-Tech/ecommerce_v2_api  
**Branch:** development  
**Path:** docs/02-standards/authentication-standards.md  
**Approval:** **AUTH-01 through AUTH-09 — APPROVED; AUTH-10 — Final Review and authorized publication**  
**Implementation:** **DOCUMENTATION ONLY — NOT IMPLEMENTED / NOT INTEGRATION-TESTED / NOT DEPLOYED**

> **Reading rule:** This document records approved future Authentication behavior. It is **not** proof that Laravel 13, Sanctum, Stancl, Redis sessions, CSRF wiring, secure invitation/recovery mail, integrated PostgreSQL databases or CI are running. The owner explicitly amended AUTH-03: minimum password length is **8 characters**, **not 15**. No MFA/2FA is in MVP, including Platform Admin. **D-201 remains OPEN; BA-16 backup/recovery design remains DEFERRED; BA-30 G3 remains BLOCKED for live Pilot.**

---

## 1. Purpose, Scope, Boundaries and Authority

### 1.1 Purpose

Define implementable, Laravel-native Authentication rules for the approved **Laravel 13 API-first Modular Monolith** and **Next.js** interfaces with **Central PostgreSQL plus one physically separate PostgreSQL database per Tenant**.

The standard covers:
- Separation of Central workforce and Tenant-local Customer authentication.
- Host-scoped first-party Laravel Sanctum sessions and CSRF.
- Login, logout, idle/absolute expiration and session invalidation across devices.
- Password policy, hashing and isolated recovery.
- Layered Redis rate limiting, brute-force and enumeration defense.
- One-use workforce invitations and verified account activation.
- Customer and Guest identity boundaries.
- Account/membership suspension, revocation and authorization freshness.
- Focused audit, real integration testing and blocking release gates.

**Four logical API surfaces in the same Laravel deployment:**

| Surface | Authentication / boundary |
| --- | --- |
| Platform | Central workforce session plus explicit Platform permissions |
| Merchant Dashboard | Central workforce session; explicit Tenant URI, current membership and tenant-local authorization |
| Public Storefront | Trusted subdomain-to-Tenant mapping; approved guest operations |
| Authenticated Customer | Tenant-local customer session and resource ownership inside that Tenant |

### 1.2 Authoritative repository references

| Source | Governing rule |
| --- | --- |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | IA-01…IA-09: Central staff identity, Tenant-local customers, sessions, invites, revocation and no MFA |
| [API Architecture](../01-architecture/api-architecture.md) | API-01…API-08: one /api/v1, four surfaces, ApiResponse, CSRF/security and rate limits |
| [API Standards](./api-standards.md) | AS-01…AS-10: explicit Tenant in Merchant URI, wire errors, scopes, CSRF and release gates |
| [Code Standards](./code-standards.md) | CS-01…CS-12: typed DTOs, module actions, int-backed Enums, exception mapping, audit and tests |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | TA: trusted resolution, conditional Stancl, PostgreSQL contexts and no silent fallback |
| [Backend Architecture](../01-architecture/backend-architecture.md) | BA: runtime/security/observability, Redis and Pilot blocking gates |
| [Module Architecture](../01-architecture/module-architecture.md) | Module/Feature ownership, one writer, Public Contracts and workflow boundaries |
| [Data Architecture](../01-architecture/data-architecture.md) | Central/Tenant data placement, time and sensitive data practices |
| [Queue, Jobs and Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | Tenant-aware Horizon, user-initiated job revocation and cleanup |
| [Transaction Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | Local ACID, durable retries and safe cross-context orchestration |
| [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | Pending decisions, recovery obligations and live-Pilot blockers |
| [Workflow](../WORKFLOW.md) | Intended branch and PR flow, not evidence CI or protection exists |

**Precedence:** approved D-series business rules remain authoritative for commercial behavior; OV defines MVP scope; IA/API/TA/BA architecture governs identity, Tenant and API boundaries; CS/AS/AUTH govern detailed engineering. Feature Specs decide actual guards, routes, fields and email templates. Contradictions must be surfaced for review, never silently overridden.

### 1.3 Definitions

- **Central workforce identity:** one platform-wide authenticated identity for Platform Admin, Merchant Owner and Merchant Employee, stored in Central PostgreSQL.
- **Tenant membership:** an explicit, current Central relationship authorizing consideration for one merchant; membership is **not** itself permission for every action.
- **Tenant-local customer:** an independently registered shopper identity stored inside one merchant's PostgreSQL database. Same email across two Tenants is not same account.
- **Guard:** Laravel authentication context used to resolve an identity from the proper provider/session. It is **not** the same as Role or Permission.
- **Session:** authenticated server-side login state with a browser cookie carrying only the session identifier; not a browser JWT or unrestricted API bearer token.
- **Identity disablement:** blocking the Central workforce identity or a particular Tenant-local customer account.
- **Membership revocation:** removing access to one Tenant without destroying the Central workforce identity or memberships in other Tenants.
- **Idle timeout:** expires access after inactivity under the applicable authentication surface.
- **Absolute timeout:** expires access after a fixed maximum age despite activity.
- **System-owned follow-up:** durable job arising from an already committed business event, not a new user-authorized action.

### 1.4 Explicit non-goals

No MFA/2FA/OTP at login for any account type in this MVP; no JWT browser auth, parent-domain cookie SSO, global shopper account, custom IAM, dynamic ABAC engine, public self-assignment of merchant owner, forced Customer Profiles for Guests or paid SIEM/WAF requirement.

This documentation **does not authorize** application implementation, schema migrations, Composer installation, CI setup, deploying, changing main or activating live merchants.

---

## 2. Approved Decision Register — AUTH-01 to AUTH-10

| Decision | Approved choice | Binding outcome |
| --- | --- | --- |
| **AUTH-01** | **A — Explicit Guards & Providers + Tenant-Safe Sessions** | Central Workforce guard/provider and independent Tenant-local Customer guard/provider; no Guard per Role or Tenant |
| **AUTH-02** | **A — Controlled Session Lifecycle + Risk-Based Timeouts** | Idle/absolute timeouts by surface; disabled Remember Me; secure logout/all-devices invalidation, password change/reset invalidation |
| **AUTH-03** | **A — Laravel Native Hashing + Password Recovery** **WITH OWNER AMENDMENT** | **Minimum 8 characters, NOT 15**; allow >=64; checked password strength; preferred tested Argon2id; scoped one-use recovery link valid **30 minutes** |
| **AUTH-04** | **A — Same-Origin Proxy + Sanctum CSRF + Host-Only Cookies** | Session Secure/HttpOnly; host-bound cookie, trusted hosts/proxies; tenant customer session binding and CSRF failure handling |
| **AUTH-05** | **A — Layered Redis Throttling + Progressive Protection** | Proposed baseline adopted: **5 same account+IP, 20 account across IPs, 100 IP across accounts**, each over **15 minutes**; temporary throttle, not permanent lockout |
| **AUTH-06** | **A — Secure Invitations + Email Verification + Controlled Activation** | **72-hour** one-use employee invitation; **24-hour** email verification link; verified acceptance and retry-safe Tenant-local authorization before Central membership activation |
| **AUTH-07** | **A — Tenant-Local Customer Auth + Secure Guest Checkout** | Independent customer accounts/sessions; Guest permitted; no automatic claim/link of past Guest orders; registered-only coupon eligibility |
| **AUTH-08** | **A — Scoped Revocation + Session Invalidation** | Central disablement blocks all workforce; Tenant membership revoked only for that merchant; customer suspension tenant-local; current state checks |
| **AUTH-09** | **A — Focused Audit + Automated Integration Testing + Blocking Gates** | Test Sanctum/Stancl/PostgreSQL/Redis, session and credential security, revocation, isolation; block material security failures |
| **AUTH-10** | **Final review and authorized publication** | Reconcile approved decisions with architecture/Code/API standards and publish on development |

**Amendment priority:** The user's later instruction **“الحد الادنى لكلمات المرور 8 فقط”** supersedes the earlier proposed 15-character minimum. No standard here may still call 15 the minimum. The explicit choice of **8 characters without MFA** carries greater password-guessing risk; layered controls and a breached/common-password screening remain important. This is an **approved project policy**, not a claim that it matches a stricter external recommendation.

**Status semantics:** APPROVED = target rule agreed; TO SPECIFY = a remaining feature/config detail; PENDING VALIDATION = an integration selection not verified; NOT IMPLEMENTED = no evidence of working code; DEFERRED = postponed architectural/business choice.

---

## 3. Identity Ownership, Guards and Session Separation — AUTH-01

### 3.1 Identity ownership

**Central PostgreSQL owns:** Central Workforce accounts for Platform Admin, Merchant Owner and Merchant Employee; Central platform authority; Tenant Memberships and identity security audit. SaaS Tenant Directory and entitlement data are used through their owning modules and must not be taken over by Identity.

**Tenant PostgreSQL owns:** registered storefront Customer identities, local Customer profiles, Tenant-local staff Roles/Permissions/Location scope and related tenant-local security audit. Guest checkout contact and order records are owned by the approved commercial modules, not automatically a Customer identity.

Do **not** create duplicate Central employee login accounts per merchant. One Central Workforce identity can hold different permissions/Locations in Store A and Store B. Same shopper email can exist in two completely independent stores without global account linking.

### 3.2 Explicit guard and provider boundaries

| User category | Identity provider | Auth context | Additional authorization |
| --- | --- | --- | --- |
| Platform Admin | Central Workforce | Central session guard | Explicit Platform permissions |
| Merchant Owner | Central Workforce | Same Central workforce guard | Active membership, Tenant roles/entitlements and approved owner business permissions |
| Merchant Employee | Central Workforce | Same Central workforce guard | Active membership, local role, Location and resource permissions |
| Registered Customer | Resolved Tenant Customer provider | Separate tenant-bound customer guard/session | Customer record ownership, Tenant state and eligible features |
| Guest | No authenticated Customer login by default | Guest-eligible public workflow | Approved Guest Checkout scope; secure proof for private order reads |

**No Guard per Role and no Guard per Tenant.** Roles and Plans are **authorization**, not separate login identity stores. The final guard names, providers, Laravel session middleware order and Stancl scope initialization must be defined/validated in the Foundation/Feature implementation.

Never allow a workforce session to resolve as Customer, or Customer as workforce; no Platform access merely because a Central login succeeded.

### 3.3 Trusted Merchant Tenant identification

For Merchant Dashboard requests, the **Tenant is explicitly addressed in the URI**, for example:

~~~http
GET /api/v1/tenants/store-a/orders
~~~

The Central Session identifies the workforce user; the Tenant route is only a selection. Each request must:

1. Authenticate the correct Central identity/session.
2. Resolve route Tenant using the **trusted Central Tenant Directory** and any verified Host agreement.
3. Check the identity's **current active Central Tenant membership**.
4. Validate Tenant lifecycle/entitlements.
5. Initialize only the authorized Tenant DB context.
6. Apply Tenant-local roles, permissions, Location/resource/business rules.
7. Clear context and connections after success or failure.

A user's last selected Tenant stored for UI convenience is **not an authoritative Session value**. A single workforce user can operate Store A and Store B in separate tabs without Tab B changing Tab A's API scope. Changing URI IDs or forging Host headers confers no access.

### 3.4 Storefront/customer scope

Customer/public requests resolve Tenant from the **approved trusted storefront subdomain**, not a free-form client-supplied tenantId. Customer authentication must be bound to that Tenant's DB and identity guard.

Host-only cookies help isolate browsers across different storefront hosts but **do not by themselves prevent replaying a copied session identifier**. Verify proper tenant-binding of Session/guard and, where appropriate, Stancl's ScopeSessions behavior with actual Redis/Laravel integration; reject cross-store replay even if someone sends the cookie manually.

### 3.5 Authenticated errors

Use the already approved **ApiResponse** envelope, correct HTTP status and stable errorCode; never report HTTP 200 for an unauthorized request. Use 401 for missing/invalid login state, 403 for forbidden access or 404 when hiding existence is appropriate. Feature/OpenAPI defines safe precise mappings; do not leak whether another Tenant's resource or account exists.

---

## 4. Login, Logout, Idle/Absolute Session Lifecycle — AUTH-02

### 4.1 Approved numeric session lifetimes

| Surface/account context | Idle timeout | Absolute timeout |
| --- | --- | --- |
| **Platform Admin access** | **30 minutes** | **8 hours** |
| **Merchant Owner / Employee access** | **2 hours** | **12 hours** |
| **Registered Customer access** | **24 hours** | **7 days** |
| **Guest Checkout** | No authenticated login Session required | Guest cart/checkout lifetime belongs to Feature |

**Idle** is time since last eligible activity for that authentication context. **Absolute** is maximum age from the authenticated session/reauthentication event, regardless of activity.

These are explicitly approved **AUTH-02 targets** and must be enforced on the server; they are not a claim Laravel's default single session lifetime automatically implements all of them.

**Platform and Merchant may share one Central workforce identity.** When one Central Session can access both surfaces, Platform's stronger 30-minute/8-hour access policy must **not** be silently weakened by the Merchant 2-hour/12-hour policy. Use separately tracked trusted surface authentication age/activity or an explicitly reviewed secure reauthentication strategy. Do not treat role labels or client-side timers as the only enforcement.

**Remember Me: disabled for Platform, Merchant and Customer in MVP.** The Customer's approved 24h/7d normal Session remains a bounded Session even without a persistent Remember Me token. Browser autofill/password managers are different and need not be disabled.

### 4.2 Login lifecycle

~~~text
Next.js request on trusted Host
  -> GET CSRF bootstrap endpoint
  -> POST scoped Login with Email/Password and CSRF header
  -> Resolve proper Central or verified Tenant-local provider
  -> Apply rate limits and verify current account eligibility
  -> Verify hash using Laravel
  -> Regenerate authenticated Session ID to resist fixation
  -> Save trusted session context/age/activity
  -> Set host-only Secure HttpOnly Session cookie
  -> Return approved ApiResponse with no raw Session/token secrets
~~~

Use the approved email/password scheme. No Login JWT, OAuth custom frontend token vault or storage of authentication tokens in localStorage. A correct password does not bypass account suspension, Tenant availability, email verification requirements, or current Membership authorization.

### 4.3 Logout and termination

| Event | Required behavior |
| --- | --- |
| Logout current device | Invalidate **current server-side** Session and regenerate CSRF token |
| Logout All Devices | Reliably invalidate all **relevant sessions** for that identity/security scope |
| Password change | Invalidate prior sessions; require fresh Login under approved UX |
| Successful password reset | Invalidate prior sessions; require fresh Login |
| Central workforce account disabled | Block new workforce access across Platform and **all** Tenants; terminate relevant sessions |
| Merchant membership suspended/revoked | Deny new requests to that Tenant, **without** global Logout from other valid merchants |
| Tenant Customer disabled | Deny that Customer within that Tenant only |
| Session idle/absolute limit exceeded | Deny/expire protected access and direct client toward safe reauthentication |

**Technical qualification:** Laravel Session storage in Redis does not automatically guarantee reliable logout-all-devices or absolute timeout. Implementation must select a tested scope-aware revocation method (such as version/epoch enforcement, trustworthy session indexing or proven framework behavior) and test multiple devices/processes. Deleting a browser cookie or merely changing a password hash is **not** evidence every old Session was invalidated.

### 4.4 Session duration under shared-origin deployments

One Next.js browser can reach Laravel via same-origin Reverse Proxy; Session Cookies are scoped to the **actual public Host**. Different Platform/Dashboard hosts may have separate browser sessions even if they authenticate the same Central identity. This baseline promises **no cross-host SSO**.

Use server-side expiration; Next.js may show user-friendly warnings, but client timeouts alone provide no security. Evaluate real Redis expiry, concurrent logins, CSRF, Laravel middleware and source-of-truth revocation with actual integration tests.

---

## 5. Password Policy, Hashing and Secure Recovery — AUTH-03

### 5.1 Approved password policy — amended to eight characters

| Rule | Approved value / behavior |
| --- | --- |
| **Minimum length** | **8 Unicode characters** |
| Maximum accepted length | Support **at least 64 characters** (apply a defensible resource-bound upper limit if needed) |
| Arabic/English text | Permit legitimate Unicode/password-manager passphrases |
| Spaces | Permit interior spaces and ordinary passphrases; do not silently change meaning |
| Forced case/digit/symbol mixture | **Not mandatory** as a composition rule |
| Known weak/compromised password | **Reject at creation/change** via privacy-safe approved screening mechanism |
| Forced periodic reset every 30/90 days | **Not required** absent compromise/security reason |
| Paste/password managers | Allowed |
| Account coverage | Central workforce and registered Tenant customers |

Count user-visible characters consistently and test Unicode normalization behavior; do not silently trim/change the secret. Establish an accepted byte limit compatible with chosen hasher and request sizes. A bcrypt fallback requires explicit awareness of its input-length/truncation constraints; do not silently accept two long distinct inputs as equivalent.

**Risk acceptance note:** A user-selected 8-character minimum and no MFA is less resistant to guessing than longer passphrases. Compromised-password blocking, scoped throttling and monitoring are approved compensating controls; do **not** claim full compliance with any stricter external recommendation.

### 5.2 Hash storage

Use Laravel's secure Hash abstraction. **Prefer Argon2id** after testing CPU/RAM impact against Login/registration/recovery throughput on the deployed infrastructure. Only choose an appropriately configured **bcrypt fallback** if needed after documented compatibility/performance/security review. Never store or email plaintext/recoverable passwords.

~~~php
<?php

use Illuminate\Support\Facades\Hash;

$hashed = Hash::make($submittedPassword);
$isValid = Hash::check($submittedPassword, $storedHash);
~~~

This is illustrative; the application must set the actual hasher/config before running. Account for safe algorithm parameter changes, rehash-on-auth where appropriate, trusted migration of existing hashes and concurrent reset state.

### 5.3 Password recovery sources

| Identity | Password broker and lookup scope |
| --- | --- |
| Central workforce | Dedicated Central identity provider/broker and trusted canonical Central Host |
| Tenant-local customer | Separate Tenant-aware Customer provider/broker/store after verified Tenant subdomain resolution |

A workforce password is recovered **once for the Central identity**, not separately for each merchant membership. Customer recovery must not resolve an account in another Tenant, even if email matches.

**Reset link:** expires after **30 minutes**, single use, securely generated random token, protected token persistence (not reusable plaintext record), throttled resend and exact trusted scope. Reset URLs are constructed using a verified canonical host, **never arbitrary Host/Forwarded headers**.

After a verified reset, **invalidate old relevant sessions** and require a new Login. Do not treat a password reset link as a second Login factor or turn it into an authenticated session without approved requirements.

### 5.4 Account enumeration and link safety

For forgotten password, return a **generic response** whether the submitted email exists, such as:

> If an account matches the email address, recovery instructions will be sent.

Use consistent status and practical response-timing controls to avoid obvious existence leaks. Rate limit issuance by **verified IP and normalized identity scope**, and send only to genuine accounts without exposing existence. Avoid secrets in logs/referrers/analytics; protect sensitive recovery pages with suitable referrer policy and sanitized navigation.

**Scope and replay tests:** expired after 30 minutes, already redeemed token, token from Tenant A on B, workforce token on customer broker, concurrent use, malicious Host, wrong intended account and recovery during account suspension.

---

## 6. Cookies, CSRF, Reverse Proxy and Host Trust — AUTH-04

### 6.1 Approved public topology

The browser talks to **one public Origin per frontend surface**. A trusted Reverse Proxy routes Next.js UI paths to Next.js and the approved Laravel API/CSRF/auth paths to Laravel; internal services/containers may remain separately deployed.

~~~text
Browser: https://dashboard.example.com
  /                       -> Next.js
  /api/v1/tenants/...     -> Laravel API through trusted proxy
  /sanctum/csrf-cookie    -> Laravel Sanctum CSRF bootstrap

Storefront A: https://store-a.example.com
  /                       -> Storefront Next.js
  /api/v1/...             -> Laravel API with trusted Storefront A Tenant mapping

Storefront B: https://store-b.example.com
  -> Separate host-scoped Customer Session / verified Tenant B context
~~~

Hosts and routes are **illustrative**. Exact domains and auth route paths are Foundation/Feature contracts. Different origins may have separate Central workforce browser sessions; no accidental wildcard-cookie SSO.

### 6.2 Cookie policy

| Attribute | Auth Session Cookie | CSRF/XSRF Cookie |
| --- | --- | --- |
| Secure | Yes on HTTPS | Yes on HTTPS |
| HttpOnly | **Yes** | **No if JS must read it for X-XSRF-TOKEN** |
| SameSite | Lax as initial setting; test actual origin risk | Lax as initial setting; test |
| Domain | **Host-only**: no wildcard parent-domain sharing | **Host-only** |
| Path | Appropriately scoped for actual public app, generally / | Generally / for approved CSRF flow |
| Meaning | Opaque session identifier, never exposed to JS | CSRF verification token; **not** user identity |

Avoid using SESSION_DOMAIN as a wildcard to share cookies among independent merchant storefront subdomains. Session cookie **name/path/domain**, guard state and Tenant isolation must be tested so copied cookies cannot cross Customer Tenants. Cookie boundary alone does not make stolen sessions safe.

### 6.3 Sanctum CSRF flow

~~~http
GET /sanctum/csrf-cookie

POST /api/v1/auth/login
X-XSRF-TOKEN: <decoded-csrf-cookie-value>

GET /api/v1/tenants/store-a/orders
~~~

The Login path is **illustrative**, not an approved deployed endpoint. Browser sends matching same-origin credentials/Cookies and the framework-required CSRF header on state-changing requests; verify correct URL decoding and secure CSRF issuance. Login regenerates Session ID.

For Next.js server-rendered requests, **server-side fetch does not magically inherit the browser's authenticated cookies**. Forward user session context only within reviewed per-request boundaries, disable unsafe shared caches for personalized responses, and never put tokens in public HTML or shared caches.

### 6.4 Proxy, CORS and error behavior

Explicitly configure and test Trusted Hosts, Trusted Proxies, canonical domain mapping, stateful origins, HTTPS forwarding and X-Forwarded-* handling. Reject arbitrary Host, Referer, Origin or client-provided Tenant mapping as authentication authority.

Prefer same-origin calls so browser credential CORS complexity is minimized. Do **not** enable wildcard credentialed CORS or disable CSRF for convenience.

Invalid CSRF may surface as Laravel 419 or a relevant origin refusal (such as 403) depending on verified Laravel configuration. Document and test how these map to **safe ApiResponse-compatible frontend behavior**. A 419 is not a standard HTTP status; maintain suitable client compatibility rather than pretending it is a normal JSON contract automatically.

Next.js may recover CSRF context according to the documented flow, but **never blindly retry a possibly committed financial/stock write**. Use the separately approved Idempotency-Key/business-state checks.

---

## 7. Login Abuse Protection and Redis Rate Limits — AUTH-05

### 7.1 Adopted initial thresholds

Layer rate limits using **Laravel native Named Limiters backed by Redis**, scoped to **verified IP** and an account key derived safely from the normalized email/identity, with trusted Tenant prefix for Customer.

| Scope | Approved initial threshold | Window |
| --- | --- | --- |
| **Same account + same IP** | **5 failed attempts** | **15 minutes** |
| **Same account across IPs** | **20 failed attempts** | **15 minutes** |
| **Same IP across accounts** | **100 failed attempts** | **15 minutes** |
| **Permanent lockout from failures alone** | **Not allowed** | — |

These are **approved baseline targets to validate and tune** under real traffic and abuse tests, not evidence Redis limiters are configured. Rate-limit identity must not leak whether an email exists.

**Central workforce scope:** global Central identity/normalized account identifier; include trusted IP and endpoint class. **Customer scope:** verified Tenant + that Tenant's normalized customer account key + IP. Same email in Store A/B must not be wrongly conflated.

### 7.2 Progressive handling

- Return **429 Too Many Requests**, stable **RATE_LIMIT_EXCEEDED**, X-Request-ID and meaningful Retry-After where available on throttle.
- Invalid credentials without throttle use a safe uniform failure shape (usually 401) and no detailed indication whether email exists.
- Do not permanently lock a legitimate employee/customer merely because someone guessed an email.
- Account-wide cross-IP throttling can itself be abused to deny access. Use **temporary/proportionate restriction, monitoring, bounded backoff and verified user recovery**, not a permanent attacker-triggerable lockout.
- Avoid excessive collateral damage to dozens of employees behind a single office NAT IP; measure and tune limits.
- Apply separate bounded policies to password recovery, invite resend/verification and other account-security actions; **numeric limits for those other endpoints remain to be specified**.
- Use proxy-trusted client IP only, not arbitrary spoofed forwarded headers. Test distributed source IPs and concurrent request bursts.
- Do not log passwords, recovery tokens or full sensitive identifiers in limiter/audit keys.

### 7.3 Security testing

Prove each 5/20/100 threshold and expiry, concurrent counters, host/IP trust, tenant partitioning, login/recovery enumeration behavior, fair treatment of shared offices and recovery from a restriction. Avoid claiming Redis TTL alone solves authorization/session revocation.

---

## 8. Invitations, Email Verification and Membership Activation — AUTH-06

### 8.1 Approved lifetimes and mechanics

| Item | Approved baseline |
| --- | --- |
| Workforce invitation | **72 hours** |
| Invitation use | **One-time** |
| Email verification link | **24 hours** |
| Invite cancel/reissue | Cancel or replace outstanding token; prior token invalid |
| Token | Secure random, expiring, protected in storage, bounded resend |
| Workforce invitation URL | Trusted canonical Central Host |
| Customer verification URL | Trusted verified Tenant Storefront Host |
| Unverified/partially initialized membership | **No Merchant access** |

Invitation link expiration is **not** the same as the **30-minute Password Reset** expiry. Use distinct semantics, storage, authorization and replay controls.

### 8.2 Workforce invitation workflow

1. An authorized Merchant actor selects email, permitted Tenant Role and Location scope. Verify the inviter's **current** permissions and ownership restrictions.
2. Create a **Tenant-specific one-use pending invitation** and send the bounded link. Creating the invitation grants **no active membership**.
3. Invitee authenticates the **correct Central workforce identity**, or creates a new account and proves email ownership through the approved Email/Password and verification process.
4. Invitee **explicitly accepts** the still-valid, not-cancelled invitation as the correct intended account; recheck protected authorization conditions and prevent wrong-user acceptance.
5. Initialize Tenant-local roles, permission assignments and Locations under the intended scope using a **retry-safe cross-context workflow**. Central DB and Tenant DB do **not** share ACID.
6. **Only when local setup is confirmed**, activate the Central Tenant membership. Otherwise keep it inactive/pending with recoverable error/compensation status.
7. Audit invite/accept/activate/suspend events. Reissue invalidates prior tokens.

An existing Central employee invited to Store B retains the same Central identity and gains a distinct Store B membership; do **not** create a second login. First owner bootstrapping follows authorized onboarding, **not public self-assignment**.

### 8.3 Account email verification

The **24-hour Email Verification Link** validates ownership of the intended account email and is not MFA at login.

For new Central accounts, verified email ownership is required before an invitation can activate a workforce membership. For a new Tenant-local registered Customer, email verification is required to activate **registered-account features** according to approved AUTH-06 behavior. Public browsing/eligible Guest Checkout remain available without forcing customer registration or Guest email.

A Customer verification token for Store A must not verify an independent Customer in Store B. Email verification is Tenant-bound, one-use as appropriate, resent under throttle, linked to a trusted canonical Host and never accepted based solely on a guessed email.

### 8.4 Race conditions and abuse

- Concurrent invite accepts cannot produce duplicate memberships or double-apply Roles.
- Revoked/cancelled/expired invites fail closed; recheck immediately before activation.
- A previously authorized inviter losing permissions requires the workflow to apply the reviewed current-authorization policy before final activation.
- Cross-DB partial failure must be **retry-safe** and must **never** expose an incomplete activated membership.
- Invitation tokens must not be stored/logged in cleartext or leaked via referrer/analytics; use proper link handling.
- No accidental grant of owner privileges, no auto-accept based on matching email, and no user-account enumeration through invite APIs.

---

## 9. Customer Authentication, Guests and Private Orders — AUTH-07

### 9.1 One Customer domain per Tenant

Each Tenant PostgreSQL has its own Customer Accounts and approved profile/address data. Same email/phone in Store A and Store B means separate accounts with separate password state, Customer guard, verification and authorization.

The logged-in Customer can access only their Tenant's authorized profile and customer-owned orders. A Customer Session for Store A cannot resolve as Store B Customer, even if the identifier is known or the Cookie is deliberately forwarded.

### 9.2 Registered vs Guest

| Behavior | Registered Customer | Guest |
| --- | --- | --- |
| Customer account required | Yes | **No** |
| Email+Password | Yes | No |
| Customer login/session | Tenant-local authenticated | No authenticated Customer login; optional short-lived cart/checkout state |
| Order checkout | As allowed by Feature | **Allowed in approved Guest paths** |
| Stored address/profile | Feature-approved account profile | **Do not create a mandatory Customer Profile** |
| Order history | Authenticated and ownership-scoped | Not a registered-account history |
| Coupons | Eligible when logged-in and under registered-only policy | **Not eligible in MVP** |
| Old Guest orders | Only separately claimed under approved proof workflow | **Never auto-linked** |

Guest Checkout may collect necessary order/fulfillment data. **Guest email is not universally mandatory** merely because transactional email exists. Ordinary walk-in Direct Sale needs no fabricated Customer Profile.

### 9.3 Guest private-order protection

A Guest's cart/session or possession of an **Order Number or phone number** does not automatically authorize opening a private Order, addresses, refunds or reports.

If a later Feature enables Guest order tracking, require a **separate reviewed proof mechanism** such as a time-bound signed capability with appropriate secret handling, limited scope and revocation/expiry, or other verified ownership proof. This standard does **not** automatically introduce such a route/token.

### 9.4 No automatic Guest account linking

If a Guest uses an email/phone, and later registers as a Customer with the same field, do **not** auto-transfer or show historical Guest orders. Any future Claim Order workflow needs independent approval and sufficient proof.

Coupon eligibility follows the approved registered/signed-in, once-per-customer-per-coupon and relevant confirmation/hold rules, not an inferred global shopper ID.

---

## 10. Account, Membership and Session Revocation — AUTH-08

### 10.1 Distinguish levels of disablement

| Event | Effective restriction |
| --- | --- |
| **Central Workforce identity disabled** | All Platform/Merchant workforce access blocked; relevant old Sessions invalidated |
| **Tenant Membership A suspended** | New requests to Tenant A blocked; unaffected Store B membership remains active |
| **Tenant Membership A revoked** | Store A no longer authorized; do not delete Central identity or B memberships |
| Tenant-local Role or Location changed | Next relevant request/action reflects current authority |
| Tenant Customer account suspended | Customer access blocked **only** within that Tenant |
| Password changed/reset | Old relevant sessions invalid; Login required again |
| Normal Logout | Current Session ends, other sessions not globally revoked absent separate action |

Suspension may be reversible; revocation semantics, recovery/last-owner protections and permission keys belong to owning Feature/Identity policies. Do not invent a permanent deletion policy, grant cross-store powers or block unaffected identities.

### 10.2 Authoritative revocation processing

~~~text
Authorized administrator/security operation
 -> Verify correct Central/Tenant scope and reason
 -> Change authoritative membership/account/permission state
 -> Commit and record focused security audit
 -> Invalidate relevant cache/session data appropriately
 -> Each following protected request verifies current effective state
 -> Deny revoked scope even if old Session/Cached role is still present
~~~

**Authoritative state must win over stale caches.** Choose and test cache invalidation, version/epoch checks and/or source-of-truth reads as required. Do not depend exclusively on asynchronous cache cleanup; new access must fail closed after effective revocation. Revalidate permission and mutable business conditions before sensitive writes.

For Central account disabled, block all workforce APIs. For Tenant A membership revoked, block A only and retain Store B's legitimate access under the Central Session, **not** global Logout unless a separate Central security event warrants it.

### 10.3 Deferred jobs and the distinction from committed obligations

- **User-initiated deferred work** (customer export, sensitive report, pending employee operation): recheck the initiating actor's **current** Central membership, Tenant-local role, Location, object scope and entitlement **before** performing/disclosing the protected effect. Revocation cancels or safely denies pending unauthorized work.
- **System-owned continuation of already committed work** (an Order-confirmation transactional email intent): does not become invalid **solely** because the original employee later lost access. Run under verified Tenant/system obligation and approved business/privacy rules.
- Horizon jobs must initialize trusted Tenant A/B/Central context on every attempt, clear connections and authorization after success/failure, never fall back to a previous Tenant, and never carry browser Session cookies in payloads.

### 10.4 Audit of security changes

Record Central account disablement, relevant sessions invalidated, invitation/membership changes and Tenant-local role/Location/customer security changes in the owning audit scope. Include authorized actor, target, Tenant/Location when appropriate, event/result, reason, timestamp and request correlation. Exclude raw tokens and unnecessary private data.

---

## 11. Authentication Audit, Tests and Release Gates — AUTH-09

### 11.1 Focused security audit ownership

| Event class | Owning audit |
| --- | --- |
| Workforce Login, aggregated failures, Central password changes/reset/security logout | **Central security audit** |
| Workforce invitation, acceptance, Tenant membership activation/suspension/revocation | **Central identity/membership audit** |
| Platform authorization/privileged account security changes | **Central audit** |
| Tenant Customer Login/verification/account suspension | **Tenant-local Customer security audit** |
| Tenant-local staff Roles, permissions and Location grants | **Tenant-local authorization audit** |
| Approved discounts/refunds/stock and financial correction facts | **Business-owning Module audit/ledger**, not replaced by auth log |

Record proportional event type, safe actor/target reference, scoped Tenant, time, security result, reason when necessary and X-Request-ID. Login failures may be aggregated/minimized to avoid massive logs, privacy harm or expensive write storms. Do not claim every UI click needs audit.

**Forbidden in any logs/audit:** plaintext passwords, password hashes where unnecessary, session IDs/cookies, invitation/recovery/verification tokens, private email payloads or excessive PII. An ordinary audit table is not cryptographically tamper-proof against DB admin; define access/retention requirements separately.

### 11.2 Required realistic integration environment

Minimum meaningful Authentication integration testing:
- **One real Central PostgreSQL database**.
- **At least two physically separate Tenant PostgreSQL databases**.
- **Real Redis-backed Sessions/rate limiter** for relevant tests.
- Actual **Laravel 13 + Sanctum + conditionally verified Stancl/tenancy** integration.
- Trusted host/reverse proxy and representative Next.js browser requests.
- Relevant concurrent devices, Sessions, roles and permission changes.

Do not claim a single shared SQLite in-memory database or mocked Tenant IDs prove physical Tenant isolation or cross-store Session protection.

### 11.3 Authentication acceptance matrix

| Area | Required negative and success cases |
| --- | --- |
| Guards/providers | Correct Central vs Tenant Customer identity; reject wrong guard |
| Login | Correct credentials, generic wrong-user/password failure, secure Session ID regeneration |
| Password policy | **7-character rejection; 8-character allowed if otherwise safe; >=64 supported; weak/compromised rejected** |
| Password hashing | Preferred tested Argon2id; safe reviewed fallback and verification/rehash |
| Session timeouts | Platform 30m idle/8h absolute; Merchant 2h/12h; Customer 24h/7d |
| Remember Me | Disabled for all MVP browser logins |
| Logout | Current Session invalidated on server and CSRF regenerated |
| Logout All Devices | Every relevant Session blocked across browsers/Redis |
| Password change/reset | Old relevant Sessions invalid, fresh Login needed |
| Reset tokens | **30-minute** expiry, one-use, replay/concurrent, correct account/broker/Tenant, forged Host rejection |
| Invite tokens | **72-hour** expiry, cancelled/reissued/replayed invitations denied |
| Email verification | **24-hour** expiry and Tenant/identity-specific acceptance |
| Tenant membership activation | Local roles/Locations initialized before Central active; partial failure remains inactive |
| Password/recovery enumeration | Same safe response semantics for account-present/missing; rate limits |
| Login throttling | **5 / 20 / 100 failed attempts over 15 min** in verified scopes; 429/Retry-After |
| CSRF | Valid flow, invalid/missing token rejection, safe 419/403 frontend handling |
| Cookie/proxy | Host-only Secure HttpOnly Session, proper CSRF cookie, spoofed Host/forwarded headers denied |
| Merchant multi-tab | Same Central user visiting A and B cannot cross-change scope |
| Tenant customer replay | Store A session/identifier rejected on Store B |
| Membership revocation | Revoked A access denied for new requests; active B unaffected |
| Central disablement | All Central workforce Platform/Merchant access blocked |
| Customer suspension | Suspended Customer blocked only inside intended Tenant |
| Guest Checkout | No mandatory Customer Profile, no private order access by order number/phone |
| Coupon access | Guests not permitted redemption; registered eligibility per approved rules |
| Jobs | User-initiated pending export denied after revocation; committed system email obligations not wrongly cancelled |
| Horizon cleanup | Reused Worker **Tenant A -> Tenant B -> Central**, success/failure/retries, no leaked authority |
| ApiResponse | Correct 401/403/404/422/429, localized message and X-Request-ID |
| Security audit | Correct owner, safe event details, no secret leakage |

For time-based tests use controlled application time where valid and real Redis expiry tests where necessary. Test concurrent one-use invite/reset token attempts: only one redeem may succeed.

### 11.4 Blocking gates

**Block relevant implementation PR merge/release** for:
- Cross-Tenant Customer Session, data, private-order or staff permission leakage.
- Authentication/CSRF bypass, or staff/customer guard confusion.
- Continued unauthorized access after Central account disablement or Tenant Membership revocation.
- Reusable Password Reset or Invitation token after successful redemption.
- Broken required logout/all-device/session invalidation and approved timeout enforcement.
- Privileged membership becoming active without correctly initialized Tenant-local permissions.
- Credential or Session Secret exposure.
- Critical queued-job execution under revoked authority where current authorization is required.

Security failures are not made acceptable by passing formatting tests, mocked happy-path tests or AI assertions. Full load/security/restore evidence and incident handling remain distinct pre-release/Pilot gates; do not force an entire production load suite on every trivial read-only PR.

### 11.5 Intended PR/CI evidence

For affected code changes require:
- Approved current Feature Spec, open-decision check and explicit API/guard/permission impact.
- Laravel Pint and targeted Static Analysis where configured.
- Authentication and policy Feature Tests plus actual integration coverage for modified boundaries.
- Changed API/CSRF/response contract review and Next.js compatibility.
- Evidence of true PostgreSQL/Redis tests for critical Session, role and Tenant isolation behavior.
- Brief description of sensitive audit events and no-secret logging assessment.
- Verified results, limitations and reviewer sign-off before merge. **Unrun tests must be labeled UNRUN.**

**GitHub Actions/branch protection are NOT represented as already configured.** Publication of this Markdown file is not a passing CI execution.

---

## 12. Example Laravel/Next.js Flows (Illustrative, Not Implemented)

### 12.1 Login request

~~~http
GET /sanctum/csrf-cookie

POST /api/v1/auth/login
Content-Type: application/json
X-XSRF-TOKEN: <decoded-csrf-cookie>

{
  "email": "employee@example.com",
  "password": "<secret-not-recorded-in-logs>"
}
~~~

**Example only:** exact login route, guard naming, return schema and errorCode are **TO SPECIFY** in Feature/OpenAPI. Do not send a fixed demo password into production samples. Login should regenerate Session ID, apply correct throttles and record safe security event without exposing secrets.

### 12.2 Two merchants, one workforce identity

~~~text
Central Workforce user is authorized in Store A and Store B.
Tab A requests /api/v1/tenants/store-a/orders -> checks A membership.
Tab B requests /api/v1/tenants/store-b/orders -> checks B membership.
If A is revoked, new A requests deny; new B requests still allowed.
No global active_tenant Session switch, no raw Tenant credentials in URL.
~~~

### 12.3 Secure reset

~~~text
User submits email to scoped Forgot Password endpoint.
Respond with same safe public text regardless account existence.
If existing eligible account, create one-use 30-minute token.
Send link using trusted canonical Central or verified Storefront Host.
User redeems only in matching identity/Tenant broker.
Commit password change, invalidate older relevant sessions.
Require fresh Login; do not automatically expose a Session in the link.
~~~

### 12.4 Cross-context invited membership

~~~text
Merchant actor creates permitted pending invitation (72 hours).
Recipient authenticates/verifies Central email (verification link: 24h).
Recipient explicitly accepts valid one-use invite.
Retry-safe orchestration initializes Tenant local Role/Locations.
Only after local success -> activate Central Membership.
If local setup fails -> pending/non-accessible, log and recover safely.
~~~

### 12.5 Failure patterns to prevent

~~~text
BAD: Same Guard/provider silently returns Customer for workforce endpoint.
BAD: Browser JWT used as substitute for current Central membership checks.
BAD: Host header / query tenantId alone chooses DB with no verification.
BAD: Host-only cookie assumed to prevent manually replayed Customer Session.
BAD: Two merchant tabs share one authoritative active_tenant Session field.
BAD: Platform Admin follows Merchant 12-hour policy and bypasses 30m/8h.
BAD: Only Laravel session.lifetime set, and absolute timeout claimed implemented.
BAD: Password length minimum still 15 after the owner's 8-character amendment.
BAD: Recovery email reveals whether identity exists.
BAD: Unverified staff invited membership activated before Tenant local setup.
BAD: Reset token valid across Customer Tenants or reusable twice.
BAD: Redis Session cookie deleted in one browser and Logout All Devices claimed.
BAD: Disabled Central Workforce identity still accepted with stale permission cache.
BAD: Guest Order Number or phone alone unlocks private order.
BAD: Revoked staff's queued private export delivered after access removed.
BAD: CSRF disabled because Same-Origin proxy config is incorrect.
BAD: CI/security tests said PASSED though no code/test suite exists.
~~~

---

## 13. Risks, Mitigations and Explicit Trade-Offs

| Risk | Impact | Required control |
| --- | --- | --- |
| 8-character minimum + no MFA | Greater account password guessing exposure | Compromised-password screening, scoped 5/20/100 throttling, safe hashing and audit; review future MFA separately |
| Workforce/Customer guard confusion | Unauthorized API use | Distinct providers, protected route guards and negative tests |
| Tenant A session replayed in B | Cross-store Customer leak | Host scoping **and** server-side Tenant/Session binding + test |
| Shared active Tenant in Session | Wrong store under multi-tab | Explicit Tenant URI and current membership every request |
| Host/proxy spoofing | Wrong Tenant or recovery URL | Trusted hosts/proxy forwarding/canonical URLs only |
| Incomplete invitation acceptance | Premature merchant access | One-time 72h invite; local authorization before Central active membership |
| Reset replay or brute force | Account takeover | 30-minute one-use broker token, scoped throttles and all-session invalidation |
| Weak session invalidation | Revoked users retain access | Tested Redis session revocation/version or equivalent, current authority checks |
| Overaggressive IP lock | Office employees denied service | Layered account/IP budgets, temporary restrictions and shared-IP tests |
| CSRF shortcut | Unauthorized browser write | Sanctum flow, CSRF headers and secure same-origin cookie policy |
| Guest order number enumeration | Private order data exposure | Never treat number/phone as proof of ownership |
| Horizon stale authority | Revoked actor's job leaks data | Recheck current permissions for user-initiated jobs; trusted context cleanup |
| Secret-filled audit | Credential or PII breach | Redact authentication inputs/tokens and limit audit scope |
| Unverified readiness | Unsafe live merchants | Keep integration/backup/restore/legal/Pilot gates blocking |

Some decisions intentionally trade friction for usability. **Do not reintroduce MFA in MVP** or silently change the approved numeric password, timeout, token or throttling choices without explicit owner approval.

---

## 14. AUTH-10 Final Cross-Document Consistency Review

| Prior authority | Check | Result |
| --- | --- | --- |
| IA-01/IA-02: Central workforce and Tenant Customer guards | AUTH-01 keeps separate providers; no per-Role Guard | **Compatible** |
| IA-03 and AS-01: explicit Merchant Tenant | AUTH-01/02 route Tenant independent of shared Session state | **Compatible** |
| IA-06: Laravel-native email/password, no MFA | AUTH-02/03/05 implement Sessions, recovery and throttles without MFA | **Compatible** |
| IA-05: Secure invitation and cross-DB activation | AUTH-06 requires verified acceptance and local init before active membership | **Compatible** |
| IA-07: independent Customer, Guest | AUTH-07 keeps profile optional for Guests and no auto-link/coupon | **Compatible** |
| IA-08: focused Central/Tenant audit | AUTH-08/09 specify ownership and minimized security events | **Compatible** |
| API-02/04 and AS-02/03: Json envelope and errors | AUTH uses ApiResponse and stable codes/statuses | **Compatible** |
| API-07 and AUTH-04/05: Sanctum/CSRF/rate limits | AUTH adds approved Session and login limiter numbers | **Compatible** |
| CS-05/06/09/10: typed Enums, errors, tests and audit | AUTH does not introduce a competing framework or silently overwrite wire Enums | **Compatible** |
| TA and QJ: conditional Stancl and Horizon | AUTH does not claim package compatibility; worker and Session boundaries testable | **Compatible, integration pending** |
| AUTH-03 original 15-char proposal | Owner amended to **8**, while retaining other strong-password rules | **Superseded by explicit later instruction** |
| AUTH-02/05/06 originally architecture TO SPECIFY | Approved durations, thresholds and token lifetimes now documented | **Newly resolved as standards only** |
| D-201, BA-16, BA-30 | D-201 OPEN; BA-16 DEFERRED; G3 BLOCKED | **Unchanged** |

**No documented disagreement authorizes implementation of a new business permission, account type, provider integration, OTP factor, cross-store login or Guest order claim mechanism.**

### 14.1 Details that still require implementation approval or real verification

| Topic | Status |
| --- | --- |
| Final Laravel guard/provider names and route/auth middleware order | TO SPECIFY in Foundation/Auth Feature |
| Redis Session key structure, safe all-device revocation and session-version enforcement | TO DESIGN and TEST, not automatic |
| Platform-vs-Merchant policy on shared Central Session | TO DESIGN/TEST without weakening 30m/8h |
| PHP Argon2id RAM/time settings and optional bcrypt fallback | PENDING BENCHMARK / SECURITY REVIEW |
| Password compromised-list method, Unicode semantics and max practical input bytes | TO SPECIFY |
| Token storage structures, verification/expiry implementation, URL host generation | TO SPECIFY and TEST |
| Other recovery/invitation resend rate limits | TO SPECIFY and TEST |
| Exact email sender/provider, templates, deliverability and lawful notices | Integration/Notifications Feature |
| Customer verification gating for specific registered functionality | AUTH-06 baseline approved; per-feature wiring TO SPECIFY |
| Actual Sanctum CSRF 419/403 behavior under Laravel 13/Next.js proxy | PENDING INTEGRATION TEST |
| Stancl v3.10.1 compatibility with Laravel 13, Redis ScopeSessions and Horizon | PENDING INTEGRATION VALIDATION |
| Postgres Central/Tenant access fixtures and CI runner | NOT IMPLEMENTED / NOT VERIFIED |
| Exact Global/Tenant account ID types, statuses and permission keys | Feature Specification |
| Security audit storage/retention and privacy review | Security/Audit Standards |
| Guest claim tracking/access proof workflow | NOT APPROVED; separate Feature decision |
| D-201 | OPEN; separate product/business decision |
| BA-16 off-VPS backup and recovery strategy | DEFERRED, with mandatory proven recovery pre-Pilot |
| BA-30 G3 Pilot Recovery Gate | BLOCKED |
| Real production readiness, tests, legal/privacy and deployment | NOT EVIDENCED BY THIS FILE |

### 14.2 Authorized publication scope

The owner approved AUTH-01…AUTH-09 individually or by an explicit continuation acknowledged as approval (AUTH-06), and selected **AUTH-03 minimum 8**. The requested final step is to consolidate these decisions and publish **this Markdown file** on **development** under **docs/02-standards/authentication-standards.md**.

This is **documentation-only**; no branch promotion, installation, code changes, migrations, operational changes, live traffic enablement or CI claim follows from publication.

---

## 15. Pre-Implementation / PR Checklist

- [ ] Confirm current Feature Spec, D-series and IA/API/AS/CS/TA rules; no silent business decisions.
- [ ] Correct Workforce vs Tenant Customer guard/provider; Platform/Merchant/Customer route protected.
- [ ] Merchant Tenant specified in URI; membership checked **now**, not from stale global Session.
- [ ] Storefront Tenant resolved from trusted Host; Customer Session bound server-side to Tenant.
- [ ] Central and Tenant identities stored in their respective PostgreSQL databases.
- [ ] Email + Password only, no MVP MFA/JWT browser token.
- [ ] Session cookie Secure/HttpOnly/host-only and CSRF cookie correct; reverse proxy and hosts tested.
- [ ] CSRF flow tested from real Next.js browser; safe 419/403 handling.
- [ ] Login regenerates Session; Logout revokes actual current Session.
- [ ] Platform 30m/8h, Merchant 2h/12h, Customer 24h/7d policies enforced server-side.
- [ ] Remember Me disabled and multi-device session revocation proven.
- [ ] Password minimum **8**, long passphrases allowed, compromised-password screening active.
- [ ] Argon2id decision based on measured memory/CPU; hash migration tested.
- [ ] Recovery scoped by Central/Tenant broker, **30-minute one-use** token, correct Host and safe response.
- [ ] Login throttle 5/20/100 over 15 minutes for approved scopes; real Redis tests.
- [ ] Invitation **72 hours**, verification **24 hours**, one-use and correct account/Host binding.
- [ ] Membership not active until verified local Roles/Location initialized.
- [ ] Customer accounts never shared between Tenants; Guest Checkout remains guest-eligible.
- [ ] Guest Order Number/phone never used alone as private-order access credential.
- [ ] Guest old Orders not automatically linked after signup; registered coupon restrictions honored.
- [ ] Central disablement revokes workforce everywhere; Tenant revocation affects only intended store.
- [ ] Revoked permission denied even with old Session/cache; deferred user jobs reauthorize.
- [ ] Audit limited to owning scope, with no tokens/passwords/session IDs/PII leakage.
- [ ] Actual Central PostgreSQL + two Tenant PostgreSQL + Redis/Sanctum/Stancl tests run.
- [ ] Critical auth/CSRF/revocation/replay/tenant tests **block merge if failing**.
- [ ] OpenAPI/API status/errorCode/headers consistent with published API standards.
- [ ] Actual tests and limitations reported honestly; unrun is not passed.
- [ ] No claim of D-201 resolution, BA-16 recovery approval or BA-30 Pilot readiness.
- [ ] No unauthorized code, package install, schema modification, main merge or deployment.

---

## 16. Final Approved Authentication Position

E-commerce SaaS V2 authentication uses **Laravel Sanctum first-party Sessions**, **separate Central Workforce and Tenant Customer guards**, trusted **same-origin Next.js-to-Laravel proxy**, **secure host-only Cookies**, CSRF, explicit Tenant authorization and correct Session lifecycle.

Numerical baseline: **Platform 30m idle / 8h absolute; Merchant 2h / 12h; Customer 24h / 7d**. **Remember Me disabled**. Passwords require **minimum 8 characters** (owner's explicit amendment), support 64+ and screen out compromised/common choices; **Argon2id is preferred after benchmarking**. Password recovery links expire after **30 minutes**; employee invitations after **72 hours** and verification links after **24 hours**. Login rate limits initially protect **5 same account+IP / 20 same account across IPs / 100 same IP across accounts**, each per **15-minute** window, subject to actual security validation.

A revoked Tenant membership blocks access to only that merchant; a disabled Central workforce identity blocks all its workforce access. Customer accounts and Sessions remain strictly Tenant-local; Guests can complete approved Checkout without accounts, have no automatic historical order linkage and cannot redeem registered-only coupons.

Auth audit, real PostgreSQL+Redis/Sanctum integration tests, actual all-device revocation, safe invitation activation and critical security PR gates are **mandatory before the relevant implementation can be considered verified**.

**DOCUMENTATION APPROVED; IMPLEMENTATION/INTEGRATION/CI/DEPLOYMENT NOT VERIFIED. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.**

**End of Authentication Standards baseline.**
