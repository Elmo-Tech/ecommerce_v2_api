# FND-02 — Project Constitution Approval and Review Record

> **Status: APPROVED — FND-02 Option A.** The owner approved the reviewed eight-principle draft on 2026-11-23; the canonical Constitution is now **v1.0.0**.  
> **Reviewed source:** [Constitution Review Draft 0.2.0](../foundation/constitution-proposal.md). **Ratified canonical file:** [E-commerce SaaS V2 Constitution](../../.specify/memory/constitution.md).  
> **Technology setup:** Official Spec Kit v1.4.3 initialized in GitHub Codespaces with `--integration none`, commit `c58a78f`. The earlier placeholder has been replaced by owner-approved text in the setup branch.

## Approved owner decision — FND-02 Option A

**FND-02 — Option A: APPROVED on 2026-11-23.** The owner explicitly selected `A` in this conversation, authorizing ratification of the reviewed eight-principle Draft 0.2.0 as **Project Constitution v1.0.0**. The resulting canonical document uses the approved principle text; only document status, approval metadata, relative source links and versioning tense were normalized.

**Option B:** Not selected. It would have returned the draft for further revisions.

Existing D/OV/BA/MA/Standards and FBR-02 decisions retain their authoritative scope. **This approval does not authorize Laravel implementation, deployment, production activation, automatic PR merge or resolution of any open business decision.**

## Review findings and wording refinements

| Principle / section | Review concern | Refinement in Draft 0.2.0 | Supporting existing authority |
| --- | --- | --- | --- |
| I — source authority | Broad documentation citations could obscure binding D/OV decisions | State domain-specific precedence; preserve superseded history and block unresolved material conflicts | Project Overview §1.2; Backend Architecture §1.2; FBR-02 §1 |
| II — modular monolith | Avoid 1:1 physical modules for 16 logical domains or cross-module writes | Preserve explicit registry, One Writer, Public Contracts and minimal structure | Module Architecture MA-01…09; Code Standards |
| III — tenancy & actors | A single workforce membership rule should not be applied to Guests or Tenant-local Customers | Distinguish Platform Central-only, Merchant Workforce, public Storefront and Customer guards; current permissions and context cleanup | Tenancy Architecture §3–4; API Standards AS-01; Authorization Standards AUTHZ-05 |
| IV — stock & finance | One DB transaction and one eligible fulfillment Location require explicit language | Preserve guarded local ACID/idempotency, business snapshots, exact Money/IDs and actual collection proof | Backend Architecture §2; TC Architecture §3–4; FBR-02-06/07/15/16 |
| V — API & security | Route identifiers are selectors; list results must be scoped before filtering | Add four API surfaces, current scope checks, tested CSRF 403 and private cache/Guest boundaries | API Standards AS-01…08; FBR-02-05/10/12/13 |
| VI — jobs, files, audit | Operational logs and successful Jobs cannot prove business effect | Differentiate domain-owned Audit/records from correlated redacted technical logs; preserve durable Outbox | LOG-01…08; TC-04; Queue/Jobs Standards; FBR-02-04/18/19 |
| VII — testing | Every change does not require heavy integration tests, but Critical/High merges do | Risk-based suites; real Central + 2 Tenant DBs/Redis/worker evidence for relevant risk before affected merge | Testing Standards TEST-01/02; FBR-02 acceptance |
| VIII — launch & governance | Conditional tooling does not prove runtime compatibility; release gates must remain blocked | PHP-FPM first; conditional Laravel/Stancl tests; BA-16 deferred / BA-30 G3 blocked; no live pilot without required restore/legal/gates | Backend Architecture BA-30; DB Standards; Security Standards; FBR-02 |

**Conclusion:** The review found no need to change the approved architecture direction; the wording clarifies boundaries already present in the cited documents. **Owner approval of Option A is now recorded and the canonical Constitution is ratified.** This is not evidence that Laravel compatibility, CI, deployment or runtime behavior has been verified.

## Boundaries deliberately not filled in

- **D-201 remains OPEN.** The pending customer-response follow-up marker has no selected Option A/B.
- **BA-16 Backup/Restore remains DEFERRED; BA-30 G3 BLOCKED**. No fabricated backup topology, legal compliance sign-off, retention limit, numeric SLO/RPO/RTO, Pilot date or eligibility.
- Exact Laravel 13/Stancl/Sanctum version interoperability, Central/Tenant schema, HTTP payloads, thresholds, resource budgets, queue retries and module contracts require Feature plans and real tests.
- The previous two GitHub Actions runner-startup failures remain unresolved; successful **Codespaces** CLI initialization does not equate to green CI.
- Ratification does not automatically approve F0-01 `spec.md` / `plan.md`, Laravel implementation, production access or a PR merge.

## Ratification results and next approval boundaries

1. **Completed:** Owner approved **FND-02 Option A** on 2026-11-23, ratifying [Constitution v1.0.0](../../.specify/memory/constitution.md).
2. **Completed:** Canonical Spec Kit Constitution replaced the default placeholder; current version, source approval and ratification date were recorded, while Draft 0.2.0 remains a traceable historical artifact.
3. **Separate pending gate:** PR #1 stays Draft, targeting `development`. Constitution approval does **not** grant PR merge approval or prove GitHub Actions/CI.
4. **Separate pending gate:** Review and approve the existing F0-01 Feature `spec.md`, then its `plan.md` and `tasks.md`, before implementing Laravel code. No new business rules are inferred from ratification.

**References:** [FBR-02](FBR-02-architecture-gap-review.md), [Project Overview](../00-overview/project-overview.md), [Module Architecture](../01-architecture/module-architecture.md), [Tenancy Architecture](../01-architecture/tenancy-architecture.md), [API Standards](../02-standards/api-standards.md), [Authorization Standards](../02-standards/authorization-standards.md), [Database Standards](../02-standards/database-standards.md), [Logging/Audit Standards](../02-standards/logging-audit-observability-standards.md), [Testing Standards](../02-standards/testing-standards.md).
