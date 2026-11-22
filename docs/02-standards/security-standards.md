# E-commerce SaaS V2 — Security Standards

**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**Branch:** `development`  
**Path:** `docs/02-standards/security-standards.md`  
**Version:** 1.0 — Approved Security Engineering Standards Baseline  
**Prepared:** 2026-11-21  
**Approval:** **SEC-01 through SEC-10 — Option A APPROVED**  
**Implementation:** **DOCUMENTATION ONLY — NOT A CLAIM OF CONFIGURED, TESTED OR DEPLOYED CONTROLS**

> **Non-negotiable reading rule:** This document records reviewed implementation standards for future Feature Specs, developers, AI agents and release reviewers. It is not a running security control, an audit certificate, a penetration-test result, a privacy-law opinion or authorization to deploy production. **BA-16 Backup & Restore remains DEFERRED; BA-30 G3 remains BLOCKED for live Pilot; D-201 remains OPEN.**

---

## 1. Purpose, Governing Authority and Scope

### 1.1 Purpose

Make security an explicit part of the **Laravel 13 API-first Modular Monolith + Next.js** delivery process. Establish actionable, risk-based safeguards for untrusted input, browser/transport boundaries, Tenant isolation, private files, secrets, dependencies, monitoring, privacy and incident response. Each owning Feature must translate relevant safeguards into a `spec.md`, `plan.md`, `tasks.md`, acceptance tests and reviewed PR evidence.

Security complements the system's business invariants: prevent cross-Tenant or cross-Location access; protect stock, collection, refund and order facts; maintain appropriate historical evidence; prevent unauthorized use of staff authority; and fail closed at critical trust boundaries.

### 1.2 Architecture baseline

- **Central PostgreSQL DB:** platform identity, shared workforce, Tenant directory and memberships, subscription/entitlement records and platform audit through their owning modules.
- **One separate PostgreSQL DB per merchant Tenant:** Tenant-local customer accounts, staff roles, Location scope, catalog, inventory, orders, collections, returns/refunds and Tenant-owned audit.
- One PostgreSQL `public` schema per DB; **dedicated least-privilege runtime DB role per Tenant**, separate Central role and privileged migration/provisioning roles.
- Four logical API surfaces: **Platform**, **Merchant Dashboard**, **Public Storefront**, **Tenant-local Authenticated Customer**.
- Browser: **Laravel Sanctum first-party host-scoped Session cookies + CSRF**, Next.js through the approved trusted **same-origin reverse-proxy route**.
- Redis/Horizon for bounded background work; authoritative business/permission/financial facts remain in PostgreSQL.
- Initial deployment architecture: separate Development and future Production environments, GitHub Actions → GHCR immutable Docker images → Coolify, local persistent file storage; **PHP-FPM** in initial Pilot (not Octane).
- **`stancl/tenancy v3.10.1` conditionally selected**, pending real Laravel 13/DB-role/Session/Queue/File integration tests.

### 1.3 Governing references

| Reference | Binding baseline |
| --- | --- |
| [Backend Architecture](../01-architecture/backend-architecture.md) | BA security invariants, BA-14 audit, BA-21 credentials, BA-25 tests, BA-30 mandatory Pilot gates |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | IA-01…IA-09 workforce/customer separation, Session/Host/Tenant boundaries, **no app MFA** |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | TA-01…TA-08 tenant verification, database roles, cleanup, conditional Stancl validation |
| [Infrastructure Architecture](../01-architecture/infrastructure-architecture.md) | INF-03 routing/TLS, INF-07 CI/CD, INF-08 secrets, INF-09 releases, INF-11 observability |
| [File Storage, Media & Uploads Architecture](../01-architecture/file-storage-media-uploads-architecture.md) | FS-01…FS-08 file lifecycle, private-by-default processing and public publication |
| [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) | CR-01…CR-08 scoped cache, current authorization, worker isolation, Redis failure discipline |
| [Transaction & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) | TC-01…TC-08 local ACID, idempotency, Outbox, uncertain-effect reconciliation |
| [API Standards](./api-standards.md) | AS-01…AS-10 route/validation conventions, ApiResponse, Rate Limit 429, Tenant scoping |
| [Code Standards](./code-standards.md) | CS-01…CS-12 DTO/Action, security PR gates, structured logs, agent rules |
| [Authentication Standards](./authentication-standards.md) | AUTH-01…AUTH-10 guards, lifetimes, 8-character password rule, Login thresholds, revocation |
| [Authorization Standards](./authorization-standards.md) | AUTHZ-01…AUTHZ-10 Tenant RBAC, Location/Resource, D-084 discount approval exception |
| [Database Standards](./database-standards.md) | DB-01…DB-11 PostgreSQL constraints, Tenant DB grants, migrations, historical data |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 OPEN; other outstanding matters |

**Precedence:** approved D-series business rules and agreed MVP scope determine business behavior; Architecture determines ownership, Tenant and release boundaries; these Standards determine security discipline; a reviewed Feature Spec determines final routes, fields, thresholds, permissions and tests. Do not silently revise a previously approved business rule or invent implementation evidence.

### 1.4 Explicit exclusions and status definitions

**Not selected by this standard:** paid SIEM/WAF/antivirus platform, mandatory CAPTCHA, custom IAM/security framework, JWT browser authentication, blanket MFA for app users, global account directory for shoppers, mandatory per-Tenant servers, an S3 rollout, universal encryption of every DB column, universal retention period, a new Backup/Restore strategy, arbitrary numeric SLOs, a security team of fixed size or automatic Production promotion.

- **APPROVED:** policy/engineering direction agreed, not necessarily implemented.
- **TO SPECIFY:** exact Feature/configuration/operations choice remains open.
- **PENDING VALIDATION:** real behavior, compatibility, measurable limit or legal premise has not been proven.
- **BLOCKED:** required live-Pilot gate cannot be passed until specified evidence exists.
- **IMPLEMENTED/VERIFIED:** may be used only with actual recorded technical evidence; do not infer from this file.

---

## 2. Approved Security Decision Register — SEC-01…SEC-10

| ID | Approved Option A | Required outcome |
| --- | --- | --- |
| **SEC-01** | Security-by-Design + Lightweight Threat Modeling | Document trust boundaries, feature threats, negative security tests and risk classification before implementation |
| **SEC-02** | Same-Origin HTTPS + Sanctum CSRF + Host-Only Cookies + Strict Browser Protections | Trusted Host/Proxy, bounded CORS, CSP/security headers and tenant-safe sessions |
| **SEC-03** | Layered Input Validation + Safe Data Handling | Laravel Form Requests, explicit typed DTO mapping, parameterized queries, output encoding, safe errors |
| **SEC-04** | Secure Native Uploads + Private-by-Default + Authorized Downloads | Validate and stage files, publish deliberately, reauthorize private access, safe cleanup |
| **SEC-05** | Protected Runtime Secrets + Least Privilege + Controlled Rotation | Coolify runtime/CI separation, per-Tenant credentials, secure keys, no secret leakage |
| **SEC-06** | Dependency/Container Scanning + Locked Dependencies + Secure CI/CD | Reproducible build inputs, least-privilege workflows, image digest traceability, risk-based blocking |
| **SEC-07** | Structured Security Events + Focused Audit + Incident Runbooks | Central/Tenant audit ownership, minimum safe events, alert triage, evidence-preserving response |
| **SEC-08** | Tenant-Aware Layered Limits + Resource Budgets + Safe Failure | AUTH-05 Login 5/20/100 over 15 minutes, API-specific budgets, no denial of legitimate operations by unsafe defaults |
| **SEC-09** | Privacy-by-Design + Data Minimization + Feature-Owned Retention | Authorized data access/export, deliberate erasure, preserved financial truth, legal-review gates |
| **SEC-10** | Final Cross-Document Review + Publish One Standard | Reconciled decision register, practical controls, negative testing matrix, PR checklist and open items on `development` |

**SEC-10 authorization** approves documentation publication in this repository/branch only; it does not authorize code changes, CI configuration, migrations, actual secrets, branch merges or live deployment.

---

## 3. SEC-01 — Security-by-Design, Trust Boundaries and Threat Modeling

### 3.1 Lightweight risk review per Feature

Before code, identify the protected **actor**, Tenant/Central context, Location scope, owned resource, data classification, critical business effect, external input/channel and applicable dependencies. Record meaningful attacker-controlled inputs, failure modes and defenses in the Feature Spec/Plan; do not create an enterprise threat-model bureaucracy for every safe read-only change.

| Scope | Trust boundary / expected control |
| --- | --- |
| Next.js browser | Untrusted UI, route params, headers and form values; never authority for price, paid state, Role or Tenant |
| Laravel HTTP middleware | Session guard, CSRF, trusted Host/route mapping, early request limits |
| Central Identity and Membership | Current workforce account, verified membership, Tenant lifecycle/entitlement |
| Tenant Authorization | Local Role/Permission, Location assignments, resource ownership, approval rules |
| PostgreSQL | Owns durable facts; constraints/ACID, separated roles and connection state |
| Redis/Horizon | Untrusted job payloads re-resolved under verified context; no stale authority |
| Files | Opaque storage keys are not access grants; check current Feature ownership and permission |
| External integrations | Webhook/API data must be verified and normalized; never inherited as local authority without validation |
| GitHub/Coolify/host | Privileged deployment/secret boundary; no ordinary app user privileges |

### 3.2 Risk classification

| Level | Illustrative issue | Required handling |
| --- | --- | --- |
| **Critical** | Proven cross-Tenant leakage, unauthorized financial execution, compromised privileged secret | Stop affected unsafe merge/release; urgent triage, evidence, owner and remediation |
| **High** | Material Role/Location bypass, insecure sensitive upload or export, exploitable high-impact dependency | Focused threat review, negative tests, scoped fix and security sign-off proportionate to risk |
| **Medium** | Product/profile input or permission edge case without established high impact | Baseline review, applicable negative tests and regression evidence |
| **Low** | Public non-sensitive presentation | Normal safe-output/security baseline and focused checks |

Classification is **impact and exploitability driven**, not a fixed module-name mapping. A failed 403/404 request is not inherently a proven breach.

### 3.3 Threat modeling questions for `spec.md` and `plan.md`

1. Who is permitted to invoke this action, and which guard/provider proves identity?
2. What verified Tenant/Location/resource is affected, and can an input switch the scope?
3. Can the API return an unauthorized row, count, search result, attachment or export?
4. Can user/provider input become SQL identifiers, HTML, redirects, shell arguments, paths, unsafe serialization or privileged state?
5. Does it cause money, inventory or approval state changes that require ACID and domain idempotency?
6. Will a Horizon worker, cached value or old Session outlive the original authorization?
7. What private data/secret enters payloads, logs, tracing, email and generated documents?
8. Which negative, concurrent, fault and real-system tests prove the boundary?

Review depth is proportional: simple public catalog change ≠ a refund execution or Tenant provisioning change.

### 3.4 SEC-01 acceptance

Feature's trust boundary and high-risk misuse cases are recorded; every relevant Critical/High risk has a mitigation, test owner and acceptance criterion. An unchecked Next.js button or “the ID is hard to guess” is never sufficient authorization.

---

## 4. SEC-02 — HTTPS, CORS, CSRF, Cookies and Browser Security

### 4.1 Same-origin HTTPS request architecture

For approved browser surfaces, use the trusted reverse proxy so Next.js and relevant Laravel API/CSRF routes are on the **same public origin** even when services run as separate containers. Only approved HTTPS endpoints are Internet-facing; PostgreSQL, Redis, Horizon, Scheduler and privileged management services remain off the public app network.

~~~text
Client Browser (HTTPS + host-scoped Session)
  -> approved Coolify-managed reverse proxy / TLS endpoint
     -> Next.js UI/SSR routes
     -> Laravel /api/v1/* and required Sanctum CSRF/Auth routes
  -> trusted Tenant Directory and scoped Laravel authorization
~~~

Hostnames, proxy product (Traefik/Caddy), routing config, final endpoint names and TLS policy must be validated in Infrastructure/Foundation. Redirect approved HTTP hosts to HTTPS; ensure cert issuance/renewal and proper proxy-trust configuration. Reject wrong or unknown hosts; a user-supplied `Host`, `X-Forwarded-Host` or `X-Forwarded-For` does not confer Tenant/identity authority.

### 4.2 Browser cookies and Sanctum

| Attribute | Authenticated Session cookie | CSRF/XSRF cookie |
| --- | --- | --- |
| `Secure` | Enabled for public HTTPS | Enabled for public HTTPS |
| `HttpOnly` | **Enabled**; JS cannot read Session ID | Disabled only where JS must read token for approved `X-XSRF-TOKEN` flow |
| `SameSite` | **Lax** starting position, validated with real browser flows | Same starting position |
| `Domain` | **Host-only**, no wildcard parent-domain sharing | **Host-only** |
| Scope | Correct public Host, guard and validated Tenant binding | Correct Origin and current CSRF Session |

Host-only cookies **do not** protect against someone manually forwarding/copied Session IDs. Test real Tenant customer Session binding and conditional Stancl `ScopeSessions` with Laravel 13/selected Session store. Customer Store A cannot authenticate as Customer Store B; workforce and Customer guards never resolve interchangeably.

Do not switch to JWT or privileged bearer tokens to bypass browser cookie/CSRF integration. App MFA remains excluded by approved IA/AUTH policies.

### 4.3 CSRF and error contract

~~~http
GET /sanctum/csrf-cookie
POST /api/v1/auth/login
X-XSRF-TOKEN: <verified-token>
~~~

**Illustrative login route**; final paths belong to Feature/OpenAPI. Require matching Sanctum CSRF on browser state-changing requests; do not exempt a broad route group for convenience. Session regeneration at Login, scope-aware Logout/revocation, and server-enforced timeouts remain AUTH-owned.

Define a safe frontend response for framework CSRF rejection (often Laravel 419 or reviewed 403). **419 is nonstandard**; do not assume the framework automatically produces approved JSON. Never blindly retry an uncertain committed order/payment/refund based on a token or network failure; reconcile business outcome and use approved idempotency mechanisms.

### FBR-02-05 — CSRF Failure Contract

An identified token-mismatch failure must be normalized at the Laravel API boundary to **403 / CSRF_TOKEN_MISMATCH**, with the approved safe response envelope and correlation header, while missing authentication remains 401 and ordinary denied authorization remains 403 / ACCESS_DENIED. Laravel's internal 419 requires actual framework integration testing and explicit exception translation; neither CORS nor disabling CSRF is a substitute. Do not automatically re-submit uncertain committed writes after a token refresh.

### 4.4 Trusted Hosts, Origins and CORS

- Use explicit trusted domain/Tenant mappings, correct proxy forwarded-header trust and Laravel stateful origins.
- Prefer same-origin so browser credentialed CORS is not normally needed.
- Reject unapproved cross-origin reads and do not use `Access-Control-Allow-Origin: *` together with credentials.
- If approved future integration genuinely requires cross-origin credentialed requests, use exact reviewed origin allowlists and test preflight, cookie behavior, CSRF and identity scope.
- CORS is **not** CSRF protection, authorization or proof that a mutation was blocked.

### 4.5 Security headers and safe browser behavior

| Control/header | Approved direction | Implementation caveat |
| --- | --- | --- |
| `Strict-Transport-Security` | HTTPS-only policy when all affected Hosts are verified | Do **not** set `includeSubDomains` or Preload blindly |
| `Content-Security-Policy` | Constrain executable scripts, loaded resources and `frame-ancestors` | Design per Next.js/SSR surface; report-only when useful, never silently break approved scripts |
| `X-Content-Type-Options` | `nosniff` | Validate relevant static/media MIME behavior |
| `Referrer-Policy` | `strict-origin-when-cross-origin` initial baseline | Check approved identity/payment/provider links |
| `Permissions-Policy` | Restrict unnecessary browser capabilities | Specify actually used capabilities |
| `Cache-Control` | No shared/public caching of personalized/private responses | Public published catalog media can use reviewed safe caching |
| Frame embedding policy | Deny unauthorized admin/dashboard framing, primarily via CSP `frame-ancestors` | Don't assume all storefront public embeddability requirements are identical |

Use contextual output escaping in Next.js and properly reviewed rendering; **CSP is defense-in-depth, not a substitute for eliminating XSS**. Server-side fetches do not automatically forward browser credentials; use narrowly scoped SSR fetches and prevent shared caching of personalized output.

### 4.6 SEC-02 required tests

Real browser → proxy → Next.js/Laravel validation for HTTPS redirect/certificate, correct public host, spoofed forwarding headers, hostile Origin, CSRF failure/recovery, login/logout cookies, Customer Store A Session manually replayed on B, mixed staff/customer guards, clickjacking, XSS/safe content behavior, private-response caching and compatible CSP/HSTS. Do not mark secure headers “done” based on PHP middleware unit tests alone.

---

## 5. SEC-03 — Input Validation, Injection Prevention and Safe Output

### 5.1 Layer ownership

| Layer | Trust and validation responsibility |
| --- | --- |
| Browser/Next.js | UX-only form checks; never final source of permission or commercial truth |
| Laravel Middleware | Guard/CSRF, verified Tenant Context, bounded request body, early abuse controls |
| `FormRequest` | Expected shape, types, field allowlists, size/count/depth and initial authorized action |
| Typed DTO / Application Action | Explicit accepted input mapping, current actor/resource scope, business orchestration |
| Domain + owning Module contracts | Authoritative prices, stock, discounts, financial limits, states and approval eligibility |
| PostgreSQL | Constraints, atomicity, concurrency and no unauthorized cross-DB privileges |
| API Resource / Next.js | Explicit returned fields, correct context-aware rendering, safe errors |

Use Laravel-native Form Requests and `$request->validated()` or similarly explicit allowed input; **never** trust full request input for sensitive Eloquent mass assignment. Reject unexpected write fields where contract strictness/privilege safety requires it. Backend-generated final prices, paid states, approval permissions and Tenant references are not user-controlled.

### 5.2 Example — illustration, not an approved Orders schema

~~~php
final class ExampleCreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:productId,quantity'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
~~~

Actual quantity may be fractional under approved UOM rules; data fields, row caps, DTO names and Order constraints belong to Feature Spec.

~~~php
// NEVER allow user-submitted status/payment/role/tenant changes implicitly:
Order::create($request->all()); // INSECURE ILLUSTRATION
~~~

An input such as `{"isPaid":true,"tenantId":8,"status":3}` cannot prove payment, establish scope or perform an authorized transition.

### 5.3 SQL injection, search and sort

- Bind all user values using Eloquent/Query Builder/parameterized SQL.
- Bind parameters **do not** make arbitrary column names, sort expressions, SQL fragments or table identifiers safe. Accept filter/sort aliases from a reviewed allowlist and map to fixed internal names.
- Never concatenate user input into SQL, shell command, paths, SQL `ORDER BY` or JSON path expressions without a reviewed safe selection approach.
- Apply verified Tenant/Location/customer ownership scope **before** search/filter/sort, counts, related data, exports and pagination.
- Follow AS-04 default `perPage=20` and ordinary max `100`; reject unsupported/unbounded filters and sorts with approved safe errors.
- No unrestricted raw SQL error, DB connection metadata or another Tenant's resource existence in API responses.

### 5.4 Output handling, XSS and redirects

React/Next.js normally escapes displayed text; preserve contextual HTML/attribute/URL encoding and do not pass untrusted content to `dangerouslySetInnerHTML`. If a Feature truly requires user-authored HTML, define a narrow reviewed sanitization and allowed-elements policy with tests; do not indiscriminately delete harmless symbols from legitimate names/descriptions.

Allow only approved redirect targets/protocols; validate links built from Host input. Prevent user content from determining script URLs, raw template directives or sensitive file responses.

### 5.5 Parser and deserialization safety

Do not use PHP `unserialize()` on untrusted input. Bound JSON depth, nested collections, import size/rows, payload counts and parser resources. Validate external webhook content with the provider's approved authenticity scheme and map it to typed internal values before any business effect; no new provider is approved here.

### 5.6 Error/data minimization

Return stable appropriate HTTP errors and approved `ApiResponse` without SQL, stack traces, credentials, tokens or unauthorized resource detail. **Production `APP_DEBUG=false`**; log safe diagnostic identifiers only. Defensive input validation alone does not prove actual stock, discount, approval or financial eligibility: revalidate critical business predicates under the transaction/locking policy.

### 5.7 SEC-03 tests

Unexpected status/paid/role/tenant fields, SQL injection in text and sort/filter names, oversized nested JSON, non-authorized IDs, cross-tenant lookup, XSS payloads and dangerous redirects, unsafe serialization, safe errors and valid Unicode/text handling. Critical effects require actual current authorization and PostgreSQL concurrency checks.

---

## 6. SEC-04 — File Upload Security, Isolation and Private Downloads

### 6.1 Architecture ownership

The approved [File Storage Architecture](../01-architecture/file-storage-media-uploads-architecture.md) governs **FS-01…FS-08**. Security Standards adds a mandatory control/acceptance layer; it does **not** authorize a new media product, scanning provider, S3 deployment, final storage paths, new Supporting Document feature or blanket retention duration.

Core technical Storage uses Laravel native filesystem on appropriate **VPS-mounted persistent** storage; owning Business Module controls file association, publication, authorization and retention. Keep Production and Development storage separate. Blue and Green versions must access compatible Production mounted files when applicable.

### 6.2 Safe pipeline

1. Authorize upload intent against verified Central/Tenant, actor, Permission, resource, Location and entitlement.
2. Apply Feature-approved file type, actual size/count, request-body and resource budgets.
3. Check extension/MIME **and detected content/structure**, never trust filename or browser `Content-Type` alone.
4. Stage under a generated opaque normalized key, in **non-public pending storage**.
5. Re-encode/resize supported images where justified; parse CSV/XLSX with bounded rows/columns/memory; inspect risky PDF/active content appropriately; add risk-required malware scanning before enabling such formats.
6. Only after successful, complete processing and owning Module approval, finalize the proper private association or publish an explicitly allowed public media copy.
7. Preserve previous good file on replacement failure; use bounded, idempotent cleanup/reconciliation for staging/orphans.

**Candidate MVP allowlist:** JPG/PNG/WebP images, CSV/XLSX catalog imports, PDFs only for approved document workflows; Support attachments need Feature approval. Unrestricted SVG, HTML, executable files and arbitrary archives are not generic MVP formats. Numeric file size/dimension/count limits and a scanner vendor remain TO SPECIFY by Feature and risk, not made up here.

### FBR-02-19 — Support Screenshot Allowlist and Isolation

Support-ticket attachment uploads are **limited to JPG, PNG and WebP raster screenshots** in MVP. Central Support owns ticket/attachment metadata under the prior FBR-02-08 decision; storage bytes are private with opaque, non-web-public references. Authorize the exact ticket, actor, Tenant and permitted purpose before accepting or serving; verify extension, detected image content, bounded size/count and safe re-encoding/processing. Revalidate authorization before every new download; neither Platform Super Admin status nor a Ticket/File ID confers unrestricted Merchant data access. Do not enable PDF/XLSX/ZIP/HTML/SVG as generic Support uploads without separate approval. Numeric limits, scanning risk evaluation and retention remain Feature/security decisions, not invented here.

### 6.3 Tenant namespaces and path safety

- Derive namespace from trusted Tenant/Central execution context, not customer filename, Tenant header, raw local ID or merchant display name.
- Reject traversal (`../`), absolute path, symlink escape, prefix collision and wrong-scope copy/move/delete.
- A private file must not be executable or reachable through public static-web routing.
- Tenant A and B may legitimately have identical local numeric File/Order IDs; context is required for lookup.
- Stancl filesystem bootstrapping, tenancy ending and mounted-volume behavior require actual validation.

### 6.4 Public versus private

| Class | Availability and access |
| --- | --- |
| Published public Catalog/branding media | Deliberately public, web-server delivery and approved cache policy; pending original remains private |
| Private Order/collection/refund document | Laravel-mediated **current** actor/Tenant/Location/owner/Feature authorization before each new delivery |
| Imports and generated CSV/XLSX/PDF reports | Non-public staging and private completed artifacts; recheck download permission and expiry |
| Customer/Guest private evidence | Specific ownership/proof workflow needed; order number/phone/file URL alone never a capability |

Use safe `Content-Type`/`Content-Disposition` and private cache controls. Stream large private downloads instead of loading entire files into PHP memory when a safe method exists. No automatic S3 presigned design or implicit permanent private URL access. Revoked staff access must block **new** downloads.

Public media withdrawn from the storefront can remain in browser/CDN caches; do not promise immediate revocation of previously cached public copies. Exact publication versioning/withdrawal policy is Feature-owned.

### 6.5 Excel/CSV and cleanup

- Bound spreadsheet/image parsing and protect against zip/decompression/parser bombs.
- Neutralize CSV/XLSX **formula injection** from untrusted values in generated spreadsheets using format-specific safe serialization, verified in real spreadsheet consumers.
- New artifact must be validated and made authoritative before old reference deletion; retain financial/legal documents as prescribed.
- PostgreSQL and filesystem writes are **not one ACID transaction**. Retry/reconcile partial failures, dangling metadata, orphan bytes and incomplete export files without declaring them ready.
- Horizon exports: verify authorization at request, user-initiated job execution and download; report and cleanup are Tenant-scoped. A valid committed system-owned business obligation is not automatically cancelled by staff revocation.

### 6.6 SEC-04 tests

Hostile filename, symlink/path escape, MIME spoof, unsafe active format, malformed/huge image or spreadsheet, stale permissions, direct static private URL, Tenant A→B file ID collision, Support privilege attempt, failed replacement, aborted upload/export, duplicate cleanup, formula injection, worker A→B→Central after exceptions, Production/Development storage isolation, Blue-Green compatibility, disk-full failure. Critical cross-Tenant exposure and unsafe private publication block release.

---

## 7. SEC-05 — Secrets, Encryption and Infrastructure Credentials

### 7.1 Credential custody

| Class | Approved custody / protection |
| --- | --- |
| Laravel `APP_KEY`, runtime Central DB/Redis/provider credentials | **Coolify runtime-only secret environment**, never build/public client output |
| Tenant DB runtime credential | Protected secret-resolution mechanism per Tenant; Central Directory stores **opaque references**, not plaintext |
| Migration/provisioning credentials | Separately restricted authorized execution context; not normal API/worker credentials |
| GitHub Actions build/GHCR push token | GitHub Actions scoped permissions/secrets; no ordinary Production DB access |
| GHCR pull token | Coolify/VPS protected, least read privileges |
| Next.js publicly exposed build vars | Public values only; no auth, database or integration secrets |
| Infrastructure admin/SSH/host | Privileged operator controls separate from application identities |

No plaintext secrets in source control, committed `.env`, Docker layers, image labels, build args, public Next.js variables, logs, exceptions, email templates, queue payloads or exported files. Coolify variables may be exposed at build time by configuration: **explicitly disable build-time exposure** for runtime secrets and verify the resulting image/build logs.

API/Horizon/Scheduler are separate Coolify runtime resources; each gets only necessary credentials, with environment agreement verified as appropriate. Production and Development have separate keys, service credentials, private networks and volumes.

### 7.2 Per-Tenant PostgreSQL security

- A dedicated runtime Role per Tenant can `CONNECT` only to its own DB, with reviewed `PUBLIC` defaults, schema/table/sequence grants, role inheritance and future object privileges.
- Central app credentials cannot casually read Tenant business databases; Tenant runtime credentials do not obtain Central or another Tenant DB access.
- Normal app roles are not PostgreSQL superusers and lack `CREATEDB`, `CREATEROLE` and unrestricted DDL.
- Separate privileged provision/migration roles from normal request/job execution.
- A missing, expired or invalid Tenant secret **fails closed**, never falling back to default/Central/previous Tenant.
- Resolve connections lazily, clean and recycle stale connections after credential change, and test actual role denial with distinct real DBs.

The final secret-manager product, secure reference resolution, caching, rotation automation and role grants are implementation decisions. **SEC-05 does not purchase/install Vault or a cloud service by implication.**

### 7.3 Hashing, encryption and keys

| Purpose | Approved direction |
| --- | --- |
| User passwords | Irreversible Laravel `Hash`, preferred Argon2id after testing; never reversible encryption |
| Wire transport | Trusted HTTPS public endpoints and appropriate secured internal paths |
| Sensitive persisted fields | Risk/Feature-specific encryption with documented key custody, search/index/restore implications |
| Media, export and backups | Private access and appropriate encryption policy subject to operations/legal review; BA-16 remains deferred |
| `APP_KEY` | Independent by environment; stable across active compatible release processes; controlled migration when rotating |

Do not re-generate `APP_KEY` every deployment: changing it can invalidate encrypted data/session behavior; rotation requires compatibility, verification and recovery plan. No universal arbitrary field encryption requirement, no unsupported claims of “all data encrypted at rest”.

**User login policy remains AUTH-owned:** **8 Unicode-character minimum**, strong/breached-password screening, no mandatory composition rules, no login MFA for Platform Admin, Merchant workforce or Tenant Customers in MVP. This does **not** waive infrastructure operator MFA for GitHub/Coolify where appropriate under INF security baseline.

### 7.4 Rotation and privileged access

Document role-based access to Coolify, GitHub, SSH, Docker Host, GHCR and PostgreSQL administrators; protect admin accounts with MFA where supported, access reviews and least-privilege keys. No broad Docker socket/host FS grant to app processes.

On leaked/stale credentials: identify scope, revoke/rotate safely, refresh active app/worker connections, avoid unplanned tenant fallback, audit event/owner/outcome and test continuity. Different credential classes need risk-specific rotation, not an invented uniform calendar interval.

### 7.5 SEC-05 tests

No secret in git/build/image/client bundle/log output; correct Coolify build/runtime-only controls; actual Tenant A role denied from B and Central; runtime role denied provisioning/DDL; failed secret lookup denied; tested rotation and old connection teardown; unique Development/Production `APP_KEY`; compatible Blue-Green key use; safe error and monitoring payloads; no unauthorized infrastructure operator access.

---

## 8. SEC-06 — Dependency, Supply Chain and CI/CD Security

### 8.1 Baseline dependency discipline

- Commit/review applicable lockfiles: `composer.lock` and chosen Next.js package-manager lockfile.
- Reproducible dependency installation: `composer install`, `npm ci` if npm is selected, or tested equivalent for approved manager.
- Run relevant advisory checks such as `composer audit` / `npm audit`, and use supported GitHub dependency advisory/review capabilities where enabled. The exact tool set and current CI installation are **not verified by this document**.
- Review new/updated direct and transitive dependencies, maintainers/sources, permissions and compatibility before merge.
- Do not automatically run destructive forced version upgrades such as `npm audit fix --force` or unreviewed major upgrades just to make a report green.
- Verify **Laravel 13 / selected Stancl v3.10.1** compatibility rather than assuming security upgrades can force incompatible versions.

### 8.2 Secure GitHub Actions

- Least-privilege `GITHUB_TOKEN`; only the publishing job needs suitable GHCR write scope.
- Keep untrusted Pull Request code away from privileged secrets or release credentials; do not create a dangerous `pull_request_target` workflow that executes attacker-controlled code.
- Review all `.github/workflows` changes, third-party Actions and release credential permissions.
- Pin third-party GitHub Actions to immutable commit SHAs or an equally reviewed approved immutability mechanism; avoid drifting mutable references for privileged workflows.
- Runtime Production secrets are never injected into ordinary CI/PR jobs. CI build cannot access Production DB by default.
- Treat generated image/signing/provenance metadata and build logs as potentially sensitive; avoid exporting secrets.

### 8.3 Build once, trace, verify and deploy

The approved Infrastructure plan is **GitHub Actions → GHCR immutable Laravel and Next.js images → Coolify**.

- One release-specific Laravel application image can serve API/Horizon/Scheduler with distinct runtime commands; one release-specific Next.js image.
- Track source commit, lockfile/dependency inventory, actual CI result, image digests, environment and reviewed deployment outcome.
- Prefer image-digest references over mutable `latest` tags; do not rebuild a different artifact silently for Production.
- Scan OS/runtime image packages, remove unnecessary tooling, use trustworthy versioned base images and non-root app users when supported by verified runtime behavior.
- Image vulnerabilities are evaluated by exploitability, location, current patch and business exposure; no blanket unreviewed fix that breaks live compatibility.

~~~text
Illustrative release identity:
source commit -> CI tests + dependency evidence -> GHCR digest
            -> environment-specific Coolify deploy -> smoke/readiness evidence
~~~

This is not evidence the workflows, signed provenance, scanners, branch protections or GHCR permissions already exist.

### 8.4 Risk-based blocking and exceptions

| Finding | Default decision |
| --- | --- |
| Malicious/deceptive dependency, proven sensitive secret leak, cross-Tenant vulnerable release path | **BLOCK** affected merge/release |
| Exploitable Critical dependency or workflow privilege escalation | **BLOCK** until fixed or independent explicit exceptional risk decision |
| High-impact reachable vulnerability | Focused review; block when material; documented accountable decision if not immediately fixable |
| Dev-only/unreachable advisory with lower impact | Assess and track fix; no automatic blanket outage |
| Low advisory/no proven route | Triage and planned upgrade |
| Vulnerability alert discovered after deploy | Named owner, exposure assessment, patch/test, targeted incident escalation if evidence warrants |

A risk exception must document reason, owner, scope, compensating control, next evaluation and authorized approval; it is not unlimited permission to retain a known compromise.

### 8.5 CI/Deployment boundaries

Feature/fix PR → `development` follows scoped checks, tests and review. Only acceptable checks permit approved Development integration/deploy flow. `development` → `main` / Production promotion requires separate review, explicit human approval, release gate evidence and compatible Blue-Green plan. **Do not silently auto-deploy Production.**

An image rollback must consider newly known vulnerabilities, schema compatibility, queue message schemas and persisted file changes; “old image” does not mean safe by default.

### 8.6 SEC-06 tests

Tampered/unexpected lockfile change, known relevant dependency advisory, workflow permission escalation, PR secret access, leaked build env, foreign artifact/source digest mismatch, privileged package-push attempt, unsafe mutable image reference, scan threshold response and approved exception/audit behavior. Document actual tool versions and executed results in PR.

---

## 9. SEC-07 — Logging, Focused Audit, Alerts and Incident Response

### 9.1 Three different records

| Record | Owner and purpose |
| --- | --- |
| **Technical structured log** | Laravel/Infrastructure troubleshooting, error/retry/latency and correlation; bounded operational retention |
| **Security audit/event** | Central workforce/platform or Tenant-local identity/permission/Host/secret/security changes under owning scope |
| **Business audit/evidence** | Owning Orders/Inventory/Pricing/Payments/Returns Module; actor, outcome, original reference, correction/reversal proof |

A Log/Redis queue message is not the authoritative proof a Refund was executed or stock moved. An audit table is **not automatically cryptographically tamper-proof against database administrators**. Avoid duplicating every financial ledger row into Security Audit.

### FBR-02-17 — Exceptional Owner Recovery Trust Boundary

Only separately authorized Platform operators may process an exceptional **last protected Owner recovery**, following independent merchant-ownership evidence review and detailed Central/Tenant audit. Ordinary staff cannot self-promote or remove the last active Owner. No Platform impersonation, temporary universal Tenant access, bypass of customer privacy or activation before valid Central membership and local Role coordination. If evidence is insufficient or cross-DB steps are incomplete, fail closed and support auditable recovery. A compromised Owner's old sessions/authorization remain denied.

### 9.2 High-value security events

| Category | Scope/ownership |
| --- | --- |
| Central Login, password change/reset, account disablement, Session/security revocation | Central Identity security audit (rate-bound failures) |
| Tenant membership invite/activate/suspend/revoke | Central membership audit |
| Tenant Role/Permission/Location assignments, special Manual Discount delegation | Tenant Authorization audit |
| Tenant Customer Login/verification/suspension | Tenant-local Customer security audit |
| Host/Tenant mismatch, potential cross-Tenant read, unauthorized private export attempt | Security event with **verified** references and bounded/sanitized detail |
| Privileged DB grants, credential rotation, GitHub/Coolify changes, deployments | Infrastructure/Platform audit |
| Approval/Refund/stock/financial correction | Business-owning Audit; optional relevant security link |

Capture event type, safe trusted actor/target identifiers, verified Tenant/Location if meaningful, result/reason, timestamp and request/correlation where appropriate. Do not log all UI clicks, password reset tokens, raw customer documents or complete request bodies.

### 9.3 Correlation semantics and safe Laravel logs

- `X-Request-ID`: server-generated identity for **one HTTP attempt**.
- **Correlation ID:** links use case, outbox and job attempts.
- **Business Operation ID:** stable real-world effect and domain idempotency.
- **Idempotency-Key:** client retry identity, not authorization or a logging replacement.
- **Job/Event ID:** processing reference, not a permission to apply money/stock effects.

~~~php
// Illustration: values must originate from verified context.
Log::warning('security.tenant_scope_denied', [
    'tenant_id' => $trustedTenantId,
    'actor_id' => $verifiedActorId,
    'request_id' => $trustedRequestId,
    'reason_code' => 'MEMBERSHIP_REQUIRED',
]);
~~~

Never log passwords/hashes where unnecessary, Session IDs/Cookies, recovery/invite/auth tokens, raw provider secrets, unredacted DSNs, full payment/card data, sensitive attachments or unnecessary PII. Disable `APP_DEBUG` in Production. Audit access/export itself requires scoped privileges.

### 9.4 Monitoring, anomaly interpretation and alerts

Prefer native Laravel/Horizon/PostgreSQL/Redis/Coolify signals first. Monitor auth failure spikes, cross-Tenant denial patterns, unexpected privilege changes, stale/failed jobs, outbox lag, secret/config changes and DB connection/role errors with scoped, sanitized context.

- **Critical:** confirmed unauthorized Tenant exposure, privileged credential compromise or unauthorized financial effect → immediate incident ownership and stop unsafe affected operation.
- **High:** material attempted privilege change, repeated Host/context mismatch or suspicious operator action → prompt review and verified escalation.
- **Medium/Low:** ordinary isolated failed Login, expected validation error or low-signal denial → proportionate aggregate and monitoring.

A blocked 403 is **not proof of successful compromise**. No arbitrary alert count, fixed response SLA, automated permanent ban or paid SIEM is selected without owner/measurement.

### 9.5 Incident runbook

~~~text
DETECT -> TRIAGE -> CONTAIN -> PRESERVE EVIDENCE
       -> REMEDIATE -> VERIFY RECOVERY -> REVIEW / REPORT
~~~

1. Assign a named Incident Owner; establish affected Central/Tenants, data types, operations and confidence in evidence.
2. Restrict affected credentials, Sessions, host routing, support access, exports or business actions as necessary; **avoid uncontrolled global outages** without justified risk.
3. Secure available logs, immutable release/digest evidence, relevant database business/audit state and incident timeline; preserve legal/privacy chain-of-custody considerations.
4. Rotate credentials and correct vulnerable code/config after targeted impact analysis.
5. Validate proper Tenant, Location, auth and financial/inventory invariants before resuming writes. A failed/uncertain external refund or collection is reconciled, never blindly retried.
6. Confirm restoration/rollback compatibility and required separately approved recovery plan if data/files lost; **do not reset or delete commercial history** merely to hide damage.
7. Document root cause, impact, mitigations, verification, remaining risk, responsible owner and legal/contractual notification review as applicable.

One person can hold more than one operational responsibility; do not invent a fixed-size security team or guarantee an on-call provider exists. Named operational roles must eventually cover incident ownership, backend impact, Infrastructure, Security review and Business/Legal consultation where relevant.

### 9.6 SEC-07 tests

Correct Central vs Tenant audit placement; role/location/approval delegation change history; no raw secrets/PII in error logs; rate-bounded denial events; worker A→B→Central correlation isolation; unauthorized audit export denied; simulated Critical alert reaches owner through actually configured channel; incident rehearsal protects financial history and demonstrates permitted rollback/recovery handling.

---

## 10. SEC-08 — Rate Limiting, Abuse and Resource Exhaustion

### 10.1 Laravel native named limiters on trusted Redis scope

Use appropriately scoped Laravel Named Rate Limiters; verified identity/IP/Tenant/endpoint action class determine keys, not client-provided arbitrary Tenant or `X-Forwarded-For`. Redis rate-limiter keys must be safe/pseudonymized where needed; no plaintext password/secret/email disclosure in key/telemetry.

| API class | Illustrative approved scoping direction |
| --- | --- |
| Central Login | Normalized safe account key + verified client IP; cross-IP account and IP-wide dimensions |
| Tenant Customer Login | Verified Tenant + normalized safe customer identifier + trusted IP |
| Platform API | Central authenticated actor + endpoint/action |
| Merchant Dashboard | Verified Tenant + actor + endpoint/action |
| Public Storefront | Verified Tenant + trusted IP + endpoint |
| Authenticated Customer | Verified Tenant + Customer identity + endpoint |
| Checkout, Upload, Import, Export, Refund, Search | Additional Feature risk/resource limits and concurrency budgets |

### 10.2 Numerically approved Login limits — do not override

| Scope | **Approved AUTH-05 baseline** | Window |
| --- | --- | --- |
| Same account + same IP | **5 failed attempts** | **15 minutes** |
| Same account across IPs | **20 failed attempts** | **15 minutes** |
| Same IP across accounts | **100 failed attempts** | **15 minutes** |
| Permanent lockout for incorrect guesses alone | **Not permitted** | — |

These AUTH-05 numbers were approved **after** the earlier general AS-07 note that numeric API limits were pending; **the AUTH-05 values therefore govern Login**, while other endpoint thresholds remain TO SPECIFY. Implement temporary, proportional protection, no account enumeration and careful shared-office/NAT fairness. Test concurrent counters, expiry, distributed IPs and IP trust.

### 10.3 Layered resource budgets

- Proxy and application request-body sizes, nested JSON depth, list bounds, file type/size/count.
- Search filters/sorts allowlist, `perPage` default 20/max 100, query cost and appropriate PostgreSQL timeouts.
- Bounded CSV/XLSX/PDF processing, Horizon worker concurrency, export rate, temporary storage and disk/IO budget.
- Database total connection/lock/transaction pressure across API/Horizon/Scheduler/provisioning/Blue-Green overlap.
- Redis memory/eviction/rejected writes, shared process resource contention and workload-specific isolation when measured.
- Tenant isolation and fair use: a Tenant-scoped limiter **alone does not physically isolate** shared CPU/RAM/PostgreSQL/Redis resources.

No new WAF, CAPTCHA, vendor quota product, per-Tenant dedicated workers or arbitrary numeric limits for non-Login routes by default. Defend real network-level threats with a separately justified Infrastructure decision.

### 10.4 HTTP response and failure handling

~~~http
HTTP/1.1 429 Too Many Requests
Retry-After: <actual-delay-if-known>
X-Request-ID: <generated-request-id>
~~~

Approved API error code is `RATE_LIMIT_EXCEEDED` with valid envelope/status. Do not infinite-loop retries in Next.js or assume a 429/timeout proves an earlier checkout/refund did not commit. Reconcile critical operation state and use API/business idempotency independently of rate limiting.

If Redis rate limiting fails, **do not silently fail open** for authentication, privileged operations or sensitive financial writes. Define risk-specific bounded fail-secure behavior; permit degraded public read-only traffic only with a proven alternate protection and budget. Authorization failure itself always fails closed.

### FBR-02-10 — Fail-Secure Limiter Outage

A **genuine exceeded quota** returns HTTP 429 with RATE_LIMIT_EXCEEDED. For authenticated security-critical, privileged or money/stock mutation routes, inability to enforce the required limiter **must not fail open**: use a validated equivalent protection or return **503** with an explicit unavailable-service contract, not false 429. Only separately bounded and proven-safe public read fallbacks may degrade. Verify Redis outage, reconnection, concurrency, trusted rate-limit keys and critical idempotency independently.

### 10.5 SEC-08 tests

Exact AUTH-05 5/20/100 counter dimensions, 15-min recovery, shared office NAT false-positive review, Host/forwarded-IP spoof denial, Tenant A/B key separation, atomic concurrent burst behavior, expensive single-request containment, bulk Tenant fairness, Redis outage behavior, duplicate checkout, safe 429/retry and no financial double effect.

---

## 11. SEC-09 — Privacy, Personal Data and Retention

### 11.1 Data classification

| Classification | Typical records | Default handling |
| --- | --- | --- |
| **Public** | Explicitly published product image/name | Approved publication and cache policy |
| **Internal** | Business dashboards, operating settings | Permission/Location-scoped |
| **Personal** | Customer name/phone/address/email, relevant staff contact | Minimum necessary, Tenant/source-ownership scope |
| **Sensitive/Restricted** | Protected financial documents, approved identity attachments, high-risk business evidence | Additional permission, private storage, audit when justified |
| **Secrets** | Passwords, tokens, service keys, DB credentials | Secret/Hash policy; never ordinary telemetry |

Classification helps select controls but **does not itself grant access**. Product visibility does not imply staff can export all customers; Platform Admin is not universal Tenant private-data reader.

### FBR-02-12/13 — Private SSR and Guest Access Boundaries

Private SSR/API responses cannot be placed in shared Next.js/CDN/edge caches; sessions, tokens, order/customer/employee records and financial data remain per verified actor/Tenant and private response scope. Public cached responses require correct Tenant/locale/visibility keys and state-aware invalidation. The MVP allows approved **Guest Checkout**, but **no Guest private self-service** for orders, documents, customer records or refunds. Order number, phone or checkout state never substitutes for a reviewed ownership proof; required guest communications/access are mediated by authorized Merchant workflows. Future private Guest claims require independent product/security approval.

### 11.2 Data minimization and independent identities

Collect/store/return only justified data for an approved Feature. Guest checkout is allowed without mandatory registered Customer Profile or universal Guest email. Tenant-local Customers are independent identities; equal email/phone across stores is not one global account and does not authorize automatic Guest order claiming. Central Workforce identity contains memberships, not a synchronized global list of shopper profiles.

Never include unrestricted personal datasets in Horizon job payloads, public caches, SSR shared caches, notifications/logs, URL parameters or debugging traces. Use explicit API Resources/filters and privacy-safe metrics.

### 11.3 Feature-owned retention and erasure

| Action | Meaning and constraint |
| --- | --- |
| Archive | Stop normal use while preserving needed records |
| Soft Delete | Logical visibility state, **not** privacy erasure |
| Hard Delete | Physical removal only where lawful and consistent with commercial obligations |
| Anonymization | Remove linkability only when the actual design achieves it; pseudonymization alone is not guaranteed anonymity |
| Correction/Reversal | Preserve original financial/stock evidence with authorized adjustment lineage |

Each owning Feature must define personal-data purpose, legal basis where applicable, permitted updates, retention, access/export, erasure/rectification handling, downstream copies, historical exceptions and tests. This document does **not** impose one global privacy workflow/portal or universal day count.

Confirmed Order snapshots, Collections, Refunds, stock movements, required invoices/receipts and audit retain legitimate factual/financial evidence as required. A Customer request to remove profile/address details cannot silently destroy a binding record or rewrite actual money, stock or tax history. Evaluate the **necessary and legally permitted** identity detail retained with those records and restrict access accordingly.

### 11.4 Data access and export

- Customer/Employee data require current correct Tenant, identity, Location/resource and specific permission.
- Heavy private reports run through bounded Horizon jobs; recheck requesting actor's authorization before user-initiated work and **again before private download**.
- Disallow broad data export by knowing an Order ID, possessing an old link, or having a Platform admin label.
- Protect files/private responses with scoped delivery and cache settings; review export availability/retention and cleanup.
- Treat audit access/export as privileged; avoid unnecessary private fields in audit.

### 11.5 Consent, third parties and market gates

Where the actual processing purpose requires consent, document its scope, evidence, withdrawal handling and consequences. Do not infer marketing permission from ordinary checkout acceptance. Document legitimate processing purpose and permitted sharing separately; do not invent a marketing automation feature.

The approved first **Egypt closed Pilot** requires merchant/channel-specific legal/privacy and consumer/tax/document review under **BA-30 G7**. Expansion to Saudi Arabia or Italy requires separate applicable legal/privacy/data-transfer review. These security standards are **not** legal compliance certification and do not prescribe one country's legal retention periods globally.

### 11.6 Data copies and backups

Consider PostgreSQL, Redis caches, queued jobs, private PDFs, CSV/XLSX, support attachments, logs/audit, generated exports, replicas if later approved and off-system copies in deletion/retention design. Old backup copies can contain data later lawfully erased; restored data must be reconciled with approved privacy/retention obligations.

**BA-16 stays DEFERRED.** SEC-09 cannot approve backup vendor, PITR, snapshot cadence, backup encryption, archival retention or a complete erase-from-all-backups mechanism. Those require separately reviewed recovery/privacy design and an actual off-VPS restore drill before live Pilot.

### 11.7 SEC-09 tests

Other Customer/Tenant cross-read denied; Location-limited customer list/export scope; permissions revoked before queued export or download; Guest checkout not forcing registered Profile; minimum API fields; approved erasure does not destroy required payment/stock evidence; removed personal data not left in unapproved active caches/files; correct orphan/file cleanup scope; no excessive PII in logs; retained lawful evidence controlled. Future backup restore/erasure reconciliation tests follow approved BA-16.

---

## 12. SEC-10 — Final Cross-Document Reconciliation and Publication

### 12.1 Decision fit and precedence

| SEC | Binding existing document | Verified interpretation |
| --- | --- | --- |
| SEC-01 | BA-25, CS-11, Module/Feature workflow | Risk-proportionate review and testable trust boundaries, not a custom security framework |
| SEC-02 | AUTH-04, IA-02, AS-07, INF-03 | Same-origin Sanctum session/CSRF; host-only cookies and trusted Proxy; no app JWT or cross-store SSO |
| SEC-03 | AS-03, CS-04/06, DB-02/05 | Form Request/DTO and authorized domain state; PostgreSQL integrity for critical effects |
| SEC-04 | FS-01…FS-08, BA-15, INF-05 | Local persistent files; publish deliberate public media and privately authorize sensitive downloads |
| SEC-05 | BA-21, INF-08, DB-09, AUTH-03 | Runtime-only secrets, dedicated Tenant roles, approved password policy; distinct infrastructure MFA |
| SEC-06 | INF-04/07, CS-09, BA-23 | GitHub Actions/GHCR immutable images, lockfiles, separate Production promotion |
| SEC-07 | BA-14/24, CS-10, AUTH-09, AUTHZ-09 | Central/Tenant focused audit, business vs security vs technical separation |
| SEC-08 | **AUTH-05** and AS-07, CR-01…08, INF-11 | Login 5/20/100 per 15min explicitly approved; other numeric endpoint limits remain open |
| SEC-09 | DA-06, DB-08, FS-05, AUTHZ-07 | Feature-specific lawful lifecycle, no broad Soft Delete/retention and no erased financial truth |
| SEC-10 | BA-30, current documents/decisions | Documentation only; unresolved recovery/validation/legal gates remain intact |

### 12.2 Specific reconciliation notes

**App MFA vs infrastructure operator MFA.** The owner explicitly approved **no MFA for Platform Admin, Merchant Workforce and Tenant Customer app logins in MVP** (IA-06/AUTH). Higher-privilege **GitHub/Coolify/host operator identities** are different access surfaces, and their stronger administrative MFA/least-privilege obligations remain consistent with INF.

**Password rule.** The **latest approved minimum is 8 Unicode characters, NOT 15**. Longer passphrases >=64 must be supported; known weak/compromised passwords rejected as AUTH-03 specifies. Neither this standard nor earlier generic guidance reverses the owner's amendment.

**Login limits vs generic API limit defaults.** AUTH-05 explicitly approved failed Login thresholds of 5 same account/IP, 20 account across IPs, 100 IP across accounts, each 15min. AS-07's earlier unspecific note about no numeric API limits does not nullify the later approved **Login-specific** values. All other budgets require Feature/performance review.

**Location-aware authorization vs PostgreSQL roles.** Per-Tenant DB runtime roles isolate databases; inside a Tenant, app-layer current membership, RBAC, Location and Resource rules still apply. No PostgreSQL user per employee and no global Platform bypass.

**Business approval exceptions.** AUTHZ-10 preserves **exactly one active Tenant Role per workforce user** and allows only the narrowly approved individually delegated `Approve Manual Discounts` permission under D-084. Individual approval ceiling semantics under D-085 still require the Feature Spec. Do not generalize direct user Permission overrides or impose universal two-person approval contrary to authorized correction rules.

**Retention vs real business evidence.** A Privacy request never implicitly destroys historical financial facts; legal/Feature policy defines necessary identity minimization and lawful retention. `softDeletes()` is not a complete erasure strategy.

**File/public/media distinction.** Public images deliberately published can be served directly, but private Order/financial/report files require current Laravel-authorized access; no S3 rollout or generic signed-token delivery automatically selected.

**Monitoring status.** A target log format, SQL test, antivirus idea, Docker scan or Incident Runbook here is **NOT evidence** the actual environment has it. No claim of measured SLO, delivered alert or secure release is made.

---

## 13. Required Security Test Matrix

### 13.1 Test environments and progressive proof

Use risk-based stages: **Feature PR** relevant unit/security/contract tests; **Integration** with real Laravel/Sanctum/PostgreSQL and Redis/Horizon for sensitive boundaries; **Pre-Pilot** full BA-30 evidence. Do not force extensive load/chaos tests for every ordinary CSS change.

Critical Tenant/Role security tests must use **one real Central PostgreSQL database and two physically independent Tenant DBs with real restricted PostgreSQL runtime roles**. Conditional Stancl v3.10.1 plus Laravel 13/Session/Queue/File tests remain pending actual execution.

### 13.2 Negative test coverage

| Area | Required test | Release impact |
| --- | --- | --- |
| Tenant/Authorization | Staff A attempts B Order/customer/report via guessed ID/Host/route; correct denial and no data leakage | **BLOCK** proven cross-Tenant leak |
| Location & resources | Correct Role but wrong Location/resource/export access | **BLOCK** real unauthorized effect |
| Auth sessions | Customer A Session replayed on B; guest private order lookup; wrong guard | **BLOCK** cross-context access |
| CSRF/Origin | Missing/invalid CSRF; Host/forwarded spoof; credentialed CORS wildcard | **BLOCK** exploitable auth boundary |
| Input & SQL | Mass assignment paid/status/role; malicious column sort, SQL and XSS payload | **BLOCK** exploitable business/secret issue |
| Files | Cross-Tenant private file, wrong scope, static URL, traversal and processing/publication race | **BLOCK** private disclosure or unsafe publication |
| Secret isolation | Tenant A DB Role denied B/Central; runtime DDL denied; secret absent fail closed | **BLOCK** broad unauthorized privilege |
| Dependency/CI | PR accesses secrets, malicious dependency, image mismatch and risky production workflow | **BLOCK** material exploitable risk |
| Business effects | No oversell, duplicate actual collection/refund, false confirmed/reserved; Outbox atomic | **BLOCK** material consistency failure |
| Revocation | Staff loses permission/Location before sensitive read/job/download; old session not authority | **BLOCK** unauthorized post-revocation effect |
| Rate limit | Login 5/20/100 and expiry, spoofed IP, Redis outage, concurrent burst, tenant fairness | Fix according to security/availability risk |
| Audit | Central/Tenant correct placement, no raw sensitive fields, audit export privilege | **BLOCK** severe privacy/evidence issue |
| Privacy/retention | Erasure minimizes allowed personal records without deleting financial evidence | Legal/security review before relevant release |
| Deploy | CSP, HSTS, HTTPS, Next.js SSR cookies, Blue-Green file/db compatibility, shared secrets | Verify actual deployment path |
| Incident | Evidence-preserving containment, key revocation, responsible alert delivery | Pre-Pilot operational acceptance |
| Recoverability | Tested off-VPS Central/Tenant/files full and per-Tenant restore, measured RPO/RTO | **G3 BLOCKED until BA-16 approval and evidence** |

Never report tests as passed without actual named environment, code revision, commands, expected result, observed result and artifact/log evidence. Security controls must not be waived solely because a mock/SQLite test was green.

### 13.3 High-risk PR blockers

Cross-Tenant data disclosure, unauthorized sensitive approvals or money/stock effects, secret exposure, vulnerable auth session isolation, severe private-file leak, destructive historic evidence change, unsafe financial retries and missing durable business-effect evidence block the affected change. Handle noncritical advisory findings with recorded accountable risk decisions. Human/authorized review and separate Production go/no-go remain mandatory.

---

## 14. Developer / AI Agent Security Acceptance Checklist

### Planning and trust boundaries

- [ ] Read current approved D/OV/Architecture/Standards and target Feature Spec; **D-201 OPEN** not silently resolved.
- [ ] Identify actor, guard/provider, trusted Tenant or Central, membership, entitlement, Location and Resource ownership.
- [ ] Identify untrusted inputs, side effects, secret/PII exposure and applicable Critical/High threats before code.
- [ ] Define Feature-specific negative authorization, failure/retry and concurrency tests proportional to risk.

### Web, inputs and authorization

- [ ] Use HTTPS/trusted proxy/Host mapping; same-origin Session/Sanctum/CSRF, host-only cookies and current Tenant binding.
- [ ] No JWT app browser login, global cross-store cookies, untrusted `Host`/`X-Forwarded-*` authority or silent CSRF exemptions.
- [ ] CORS exact/allowed scope where needed; no wildcard credentials; CSP and safe next/router/server rendering verified.
- [ ] Form Requests validate schema, sizes, types and allowed fields; typed DTO/Action maps accepted inputs.
- [ ] User input cannot set authoritative `tenantId`, `isPaid`, final price, permission, approval or arbitrary lifecycle transition.
- [ ] SQL bindings/allowed sort-field selection, safe output encoding/redirects, no untrusted PHP deserialization.
- [ ] Tenant/Location/resource query scope applied before filtering, joins, counts, search, pagination and exports.
- [ ] Sensitive business effect rechecks current approval/eligibility under protected PostgreSQL transaction.

### Files, sensitive data and privacy

- [ ] File type/size/content/counter checks; non-public Staging with generated scoped keys; no traversal/symlink/executable exposure.
- [ ] Published media is deliberately public; private document/API/exports require current authorization on each new download.
- [ ] Import/export parser budgets and formula injection defenses tested; safe replacement/cleanup/reconciliation.
- [ ] Customer/staff personal data minimized; no unrestricted dataset in logs, queues, SSR/public cache.
- [ ] Feature-owned retention/erasure never silently rewrites legitimate financial/stock/tax history; legal review where required.

### Runtime, CI and operational controls

- [ ] `APP_KEY` and service/DB Secrets runtime only, environment separated, not in Build/Git/Next.js/client bundles.
- [ ] Runtime per-Tenant PostgreSQL role restricted; no superuser/CREATEDB/CREATEROLE privileges; failed credential lookup denies.
- [ ] Privileged GitHub/Coolify/host actions protected; least-privilege CI/registry and reviewed workflow/action sources.
- [ ] Lockfiles reproducible, dependency/image scans appropriate, immutable image digest traceability for release.
- [ ] Laravel Named Rate Limiter trust scopes; AUTH-05 Login thresholds preserved; resource budgets and Redis fallback behavior.
- [ ] Focused Central/Tenant security audit, structured correlation, secret/PII-safe diagnostic content and incident owner/escalation.
- [ ] Actual tests done using real Central+Tenant A+B PostgreSQL, Redis/Horizon, Sanctum/Stancl when applicable, not mock-only.
- [ ] PR lists actual test commands/results, unrun checks, unresolved risk, permissions, migration/release implications.
- [ ] Never claim Production availability/security/Legal/G3 acceptance without separate BA-30 evidence.

---

## 15. Remaining Decisions and Pilot Readiness Gates

### 15.1 Intentionally open technical/operational items

| Item | Status / where to resolve |
| --- | --- |
| `stancl/tenancy v3.10.1` + Laravel 13 integration across Session/Redis/Horizon/Files/PostgreSQL roles | **PENDING VALIDATION**; Foundation integration and real tests |
| Actual Session Storage driver | **TO SELECT/TEST**, not presumed Redis |
| Final Trusted Proxies/Hosts, cert/TLS, Next.js CSP/SSR and cookie configurations | **TO IMPLEMENT/TEST** in Foundation/Infrastructure |
| All non-Login rate-limit numbers, file-size quotas, query/work budgets, alert thresholds | **TO MEASURE/SPECIFY** per Feature/runtime |
| Upload scanning requirement/vendor for specific riskier formats | **RISK-BASED, PENDING FEATURE REVIEW** |
| Tenant secret provider/resolution, rotation automation, grants, infrastructure operator roles | **TO IMPLEMENT/VERIFY** |
| Actual GitHub Actions workflows, secret scans, dependency/OS image tools and branch protections | **NOT VERIFIED AS CONFIGURED** |
| Final private guest-file proof, future Support scope/permission/consent | **TO SPECIFY**, no blanket support impersonation |
| D-085 individual Approve Manual Discounts ceiling rule | **TO SPECIFY in Feature**, do not assume unlimited approval |
| Monitoring provider/alert channel, numeric SLOs, incident roster/runbooks | **PENDING OPERATIONAL VALIDATION** |
| Privacy law application, actual consent/retention/erasure, Egyptian tax/e-receipt eligibility | **PENDING FEATURE/LEGAL REVIEW** |
| **BA-16 Backup & Restore** | **DEFERRED architecture decision**, not solved by security/Storage standard |
| **D-201** alternative-location communication decision | **OPEN business choice**, no Security Standards decision |
| Production setup/activation and all required release gates | **NOT VERIFIED; separate release approval required** |

### 15.2 BA-30 G1–G10 — not satisfied by documentation

| Gate | Required evidence and current state |
| --- | --- |
| **G1 — Tenant safety** | Real HTTP/DB/files/cache/workers negative isolation/privilege tests — **NOT VERIFIED** |
| **G2 — Commerce consistency** | Concurrent protected Order/Inventory/Money/Outbox integrity — **NOT VERIFIED** |
| **G3 — Backup & restore** | **BLOCKED**: explicitly approve BA-16, perform recoverable **off-VPS** Central/Tenant DB and file backups, full/individual-Tenant restore drills, approve measured RPO/RTO |
| **G4 — Release safety** | Compatible CI/CD, Tenant migrations, Blue-Green overlap, workers and rollback — **NOT VERIFIED** |
| **G5 — Capacity/NFRs** | Measured workload/resources, approved numerical SLOs/budgets and alerts — **PENDING VALIDATION** |
| **G6 — Security & privacy** | Authz/grants/secrets/TLS/CSRF/uploads/audit/incident/privacy controls — **NOT VERIFIED** |
| **G7 — Legal readiness** | Egyptian Pilot merchant/channel statutory/privacy review — **LEGAL REVIEW REQUIRED** |
| **G8 — Operational completeness** | Enabled commerce journeys and support obligations end-to-end — **PENDING FEATURE ACCEPTANCE** |
| **G9 — Environment isolation** | Development versus Production credentials, files, DBs, queues and deploy access — **NOT VERIFIED** |
| **G10 — Release sign-off** | Named authorized owner, exact merchant/features, go/no-go and stop criteria — **APPROVAL REQUIRED** |

**No live Pilot merchant/customer data activation while G3 is blocked**, regardless of green CI, healthy container, HTTPS certificate or successful Code Security review.

---

## 16. Final Approved Position

**SEC-01 through SEC-10 — Option A APPROVED as a single future engineering standard and authorized for publication on `development`.**

This standard establishes **lightweight, defense-in-depth, Tenant-aware, Laravel-native security**, preserving the already approved app authentication and business decisions. All concrete Feature schema/contracts, technology integrations, tests, secrets, operational thresholds, legal requirements and live-Pilot readiness require their own explicit implementation and verification.

**Publication is documentation only.** Do not infer application code was changed, security tooling installed, credentials configured, tests run, migrations executed, `main` merged, Production deployed or live onboarding authorized.

**End of Security Standards — Version 1.0.**
