# FBR-02 — Architecture Gap Review: Approved Decision Register

> **Status:** DECISIONS APPROVED (20/20, all Option A) — DOCUMENTATION INTEGRATION ONLY.  
> **Approval date:** 2026-11-22.  
> **Repository:** `Elmo-Tech/ecommerce_v2_api`, `development`.  
> **Scope:** Refinement of existing E-commerce SaaS MVP Architecture/Standards, without application code, migration or deployment changes.

## 1. Authority and reading rules

This register records the **20 explicit FBR-02 architectural-gap decisions approved by the product owner during the review**. The implementation rules belong in the **existing owning Architecture/Standards documents**; this document provides stable traceability, practical short summaries and module follow-up owners. If a summary and its detailed integrated rule appear different, reconcile the documentation instead of inventing a new policy. No FBR approval silently replaces earlier D-series business rules, approved BA/MA/OV decisions, or separate requirements for legally valid taxation and consumer rights.

- **Approved** denotes an owner-selected future design rule, **not** deployed Laravel/Next.js code, tests, installed packages or verified infrastructure.
- **TO SPECIFY** still applies to final Feature schema, UI, exact retention and resource numbers, provider capability, legal obligations and per-business-domain approvals unless expressly settled in an FBR decision.
- **Conditional** Laravel 13 / Stancl / Sanctum / Redis/Horizon interoperability must be verified with real PostgreSQL (Central + at least 2 Tenants), real workers and browser flows.
- **D-201** remains **OPEN**; **BA-16 Backup/Restore** remains **DEFERRED**, with **BA-30 G3 BLOCKED** until proven off-VPS restoration; live Pilot requires independent BA-30 G1–G10 acceptance.
- FBR-02-18 does not set pricing, trial length, grace periods, suspension activation dates or legal exceptions: those are separately owned by SaaS/Legal Feature decisions.
- This review does **not** assert that the original 20 discussions were saved in the repository prior to this documentation integration.

## 2. Approved decision matrix

| ID | Option | Approved rule (summary) | Primary current documentation | Feature owner |
| --- | --- | --- | --- | --- |
| FBR-02-01 | A | **Laravel 13/Stancl/Sanctum/Redis/PostgreSQL integration safety:** Conditional compatibility; prove guard/session/CSRF, Tenant A/B/Central and worker cleanup; no pre-claimed install. | `authentication-standards.md`, `testing-standards.md` | Foundation |
| FBR-02-02 | A | **Authoritative Central membership and revalidation:** Check current Central membership every Merchant request and recheck relevant high-risk authority at effect time; no cross-database ACID. | `authorization-standards.md`, `identity-access-architecture.md` | Identity & Access |
| FBR-02-03 | A | **PostgreSQL authorization truth, narrow Redis caching:** No stale cached grants; verified authoritative PG-backed membership/role/location version; fail closed. | `authorization-standards.md`, `caching-redis-architecture.md` | Identity & Access |
| FBR-02-04 | A | **Suspended Tenant Outbox durability:** Preserve pending durable Outbox events, hold unauthorized effects and resume only if Tenant/obligation policy allows. | `queue-jobs-event-processing-standards.md`, `transaction-consistency-architecture.md` | Queues / SaaS |
| FBR-02-05 | A | **CSRF mismatch 403 + CSRF_TOKEN_MISMATCH:** API boundary maps actual CSRF mismatch to 403/errorCode; distinguish 401 and other 403; prove Laravel 419 translation in real integration. | `api-standards.md`, `authentication-standards.md`, `security-standards.md` | API / Auth |
| FBR-02-06 | A | **Money exact minor-unit decimal strings:** JSON final money uses exact integer minor units serialized as decimal strings plus currency; PG uses BIGINT; calculations retain precision. | `api-standards.md`, `database-standards.md`, `localization-standards.md` | API / Money |
| FBR-02-07 | A | **BIGINT JSON identifiers as decimal strings:** Use canonical decimal-string BIGINT JSON IDs and relation refs; selective UUIDv7 justified by integration/cross-DB identity. | `api-standards.md`, `database-standards.md`, `data-architecture.md` | API / Data |
| FBR-02-08 | A | **Central scoped Support records:** Central owns authoritative ticket data scoped to verified Tenant/ticket; no unrestricted Platform impersonation or Tenant data access. | `module-architecture.md`, `authorization-standards.md`, `file-storage-media-uploads-architecture.md` | Support |
| FBR-02-09 | A | **Schema-compatible partial Tenant migration:** Gate each Tenant's schema against code compatibility; failed/incompatible scoped fail closed; global halt if compatibility cannot be proved. | `database-standards.md`, `tenancy-architecture.md`, `infrastructure-architecture.md` | Tenancy / Release |
| FBR-02-10 | A | **Redis limiter risk-aware fail-secure:** True exceeded limit =>429; missing limiter protection on sensitive routes =>503 (unless independently proven equivalent); bounded protected public fallback. | `security-standards.md`, `api-standards.md`, `caching-redis-architecture.md` | Security / API |
| FBR-02-11 | A | **Merchant financial timezone locked after activity:** Valid IANA zone, immutable in ordinary settings after financial activity; authorized audited exceptional correction; UTC instants preserved. | `localization-standards.md` | Merchant Settings / Finance |
| FBR-02-12 | A | **No shared private Next.js SSR cache:** Private SSR no shared fetch/route/CDN cache; public caching Tenant+locale+visibility scoped and safely invalidated. | `authentication-standards.md`, `caching-redis-architecture.md`, `security-standards.md` | Frontend / Security |
| FBR-02-13 | A | **No Guest private self-service MVP:** Approved Guest Checkout without direct guest access to order history/private files; merchant-mediated authorized workflows; no mandatory email. | `authentication-standards.md`, `identity-access-architecture.md`, `file-storage-media-uploads-architecture.md` | Customers / Orders |
| FBR-02-14 | A | **Individual discount approver explicit ceiling:** Narrow D-084 per-member grant needs explicit max%; effective min(personal, applicable Role cap); missing personal cap denies. | `authorization-standards.md` | Pricing / Authorization |
| FBR-02-15 | A | **Exact discount percent; zero-base rejection:** Compare unrounded exact amount/eligible merchandise base; reject positive discount on zero eligible base; zero discount no-op. | `authorization-standards.md`, `database-standards.md` | Pricing / Money |
| FBR-02-16 | A | **Tax input mode locked after first Confirmed sale:** Single Tenant inclusive/exclusive input mode; normal changes locked after first confirmed sale; exceptional audited conversion only; preserve history. | `commerce-workflow-architecture.md` | Merchant Settings / Tax |
| FBR-02-17 | A | **Exceptional last protected Owner recovery:** Independently verified, Platform-assisted, scoped audited and crash-safe cross-context recovery; no ordinary staff or Super Admin bypass. | `authorization-standards.md`, `identity-access-architecture.md` | Identity / Platform |
| FBR-02-18 | A | **Commercial suspension obligations-only access:** Stop new commerce when commercially suspended; allow narrowly enumerated prior confirmed obligations under current authority; security/legal distinct. | `module-architecture.md`, `queue-jobs-event-processing-standards.md`, `commerce-workflow-architecture.md` | SaaS / Commerce |
| FBR-02-19 | A | **Limited private Support screenshots:** Ticket-scoped Central metadata; limited JPG/PNG/WebP private attachments, current authorization and safe MIME/content checks; no blanket access. | `file-storage-media-uploads-architecture.md`, `security-standards.md` | Support / Storage |
| FBR-02-20 | A | **Execution-time consistent reporting with freshness:** Financial export starts data view at execution/read; consistent snapshot/equivalent, accurate as-of and totals, bounded resources; D-176 preserved. | `file-storage-media-uploads-architecture.md`, `database-standards.md` | Reporting / Exports |

## 3. Cross-decision interpretation and priority rules

### Authorization and restoration: FBR-02-02/03/14/17

Current Central membership and Tenant Role/Location/resource authorization remain **PostgreSQL-authoritative** across HTTP, background work, private files and privileged effects; Redis can speed selected verified reads but cannot grant revoked authority. The sole individual delegated business-permission exception is D-084 **Approve Manual Discounts** with its explicit ceiling under FBR-02-14. An exceptional last Owner recovery is **not** an ordinary Role self-assignment or blanket Platform Tenant access.

### Financial and wire accuracy: FBR-02-05/06/07/11/15/16

Keep CSRF failure classification distinct from authentication/authorization/throttle outcomes. Safely serialize BigInt/Money as canonical decimal strings in JSON and preserve currency context; database keys remain BIGINT unless selective UUIDv7 is justified. Exact financial authorization compares **before** display rounding. Timezone and Tax Input Mode locks protect historical confirmed financial facts while permitting separately authorized exceptional correction paths; neither changes lawful current Tax Profile rate updates.

### Tenant lifecycle and durable obligations: FBR-02-04/09/18

**Schema compatibility and current Tenant lifecycle apply together.** A commercial suspension disables new commerce, while eligible work on **previously confirmed obligations** may be permitted through a scoped list of reviewed actions. An unavailable/incompatible Tenant or security/legal suspension remains fail-closed. Outbox entries are durable and can remain pending; they cannot be force-executed, marked delivered or thrown away to bypass Tenant policy. Never auto-confirm Manual Pending Stock after a suspended Tenant resumes.

### Privacy and Support: FBR-02-08/12/13/19

No shared/private Next.js SSR caching, no private Guest self-service, no generic Platform-to-Tenant impersonation. Central Support may store its own scoped ticket and private screenshot metadata linked to trusted Tenant identity; that is not permission to read arbitrary customer/order/financial data. Uploads/downloads require current ticket-specific authority and isolated private Storage.

### Consistent reporting: FBR-02-20 and D-176

The report's execution-time consistent read point and data **as of** meaning are independent from the **original actual sale date** for backfilled legitimate external transactions under D-176. Financial totals and rows must have consistent read semantics within one Tenant database, without requiring unlimited MVCC transactions. No universal atomic snapshot across Central plus separate Tenant databases is presumed; delayed operational projections declare freshness and cannot substitute for current stock/collection truth.

## 4. Acceptance evidence (documentation-only)

Every implemented Feature and test plan should link affected FBR IDs. Required tests include: current membership and version rejection; Tenant A/B/Central guard/cache/worker isolation; CSRF normalized 403; lossless BIGINT and currency Money roundtrips; exact discount ceiling and zero-base cases; failed/partial migration gating; Redis limiter outage 429-vs-503; financial timezone/tax input-mode lock and permitted exception audit; SSR cache leak denial; Guest private denial; last-Owner recovery race/revocation; suspension/Outbox hold and lawful continuation; private screenshot content/permission checks; and concurrent financial export snapshot/data-as-of tests.

See `docs/02-standards/testing-standards.md` for the full **FBR-02-01…20 focused test matrix**; no test is claimed to have been run.

## 5. Relevant documentation updated by this review

- `docs/00-overview/project-overview.md`
- `docs/01-architecture/`: `api-architecture.md`, `backend-architecture.md`, `caching-redis-architecture.md`, `commerce-workflow-architecture.md`, `data-architecture.md`, `file-storage-media-uploads-architecture.md`, `identity-access-architecture.md`, `infrastructure-architecture.md`, `integration-architecture.md`, `module-architecture.md`, `queue-jobs-event-processing-standards.md`, `tenancy-architecture.md`, `transaction-consistency-architecture.md`
- `docs/02-standards/`: `api-standards.md`, `authentication-standards.md`, `authorization-standards.md`, `database-standards.md`, `integration-standards.md`, `localization-standards.md`, `logging-audit-observability-standards.md`, `security-standards.md`, `testing-standards.md`

The historical `docs/handoff/` documents and `docs/decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md` remain **historical/original decision sources**; they are not silently reapproved or rewritten. Older wording inside those files can describe an item as OPEN *at the time it was written*; use this review for FBR decisions and the D-series register for D decisions.

## 6. Next work (requires separate implementation authorization)

1. Foundation runtime compatibility/integration tests and accurate OpenAPI.
2. Spec Kit Feature specs for Identity, Pricing/Tax, SaaS lifecycle, Support, Reporting and required Commerce interactions.
3. Feature-driven DB schemas, operational/retention configuration, bounded monitoring and risk-selected automated tests.
4. **Separate** BA-16 selection and proven full/per-Tenant off-VPS backup restore, with G3 unblock evidence; verify other BA-30 gates and merchant/channel-specific legal compliance before live Pilot.
