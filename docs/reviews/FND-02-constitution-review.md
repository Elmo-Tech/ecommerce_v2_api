# FND-02 — Project Constitution Review & Ratification Proposal

> **Status: REVIEW DRAFT / OWNER DECISION PENDING.** No binding Constitution has been ratified.  
> **Prepared:** 2026-11-23 | **Candidate:** [Constitution Review Draft 0.2.0](../foundation/constitution-proposal.md)  
> **Technology setup:** Official Spec Kit v1.4.3 initialized in GitHub Codespaces, `--integration none`, commit `c58a78f`. The canonical `.specify/memory/constitution.md` currently holds an untouched placeholder.

## Decision requested — not decided

**FND-02 (Proposed Option A):** Review and explicitly ratify the eight-principle Constitution Review Draft 0.2.0 as the project's canonical **Constitution v1.0.0**, with the actual approval date recorded on ratification. The existing approved source decisions remain authoritative within their respective domains, and ratification **does not** authorize implementation/deployment.

**Option B:** Return specific wording for revision; keep both the proposal and the generated canonical placeholder unratified until another explicit decision.

**No approval has been recorded for Option A or B.** In particular, initiating review is not approval to set a ratification date, copy the draft into the canonical file or merge the setup PR.

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

**Conclusion:** The review found no need to change the approved architecture direction; the proposed edits clarify boundaries already present in the cited documents. This is a review opinion, **not** formal approval or an assertion that implementation and compatibility have been verified.

## Boundaries deliberately not filled in

- **D-201 remains OPEN.** The pending customer-response follow-up marker has no selected Option A/B.
- **BA-16 Backup/Restore remains DEFERRED; BA-30 G3 BLOCKED**. No fabricated backup topology, legal compliance sign-off, retention limit, numeric SLO/RPO/RTO, Pilot date or eligibility.
- Exact Laravel 13/Stancl/Sanctum version interoperability, Central/Tenant schema, HTTP payloads, thresholds, resource budgets, queue retries and module contracts require Feature plans and real tests.
- The previous two GitHub Actions runner-startup failures remain unresolved; successful **Codespaces** CLI initialization does not equate to green CI.
- Ratification does not automatically approve F0-01 `spec.md` / `plan.md`, Laravel implementation, production access or a PR merge.

## Approval and follow-up sequence

1. Owner reviews [Constitution Draft 0.2.0](../foundation/constitution-proposal.md) and gives explicit approval of **FND-02 Option A**, or provides revisions under Option B.
2. **Only after approval:** replace canonical `.specify/memory/constitution.md` with the approved text, record formal version/date and source notes, and update the review status.
3. Review actual canonical diff and adjust PR #1; keep Draft/merge controls until owner authorization and applicable checks are satisfied.
4. Independently review F0-01 Feature `spec.md` and implementation plan before code. No business rules are inferred from Constitution ratification.

**References:** [FBR-02](FBR-02-architecture-gap-review.md), [Project Overview](../00-overview/project-overview.md), [Module Architecture](../01-architecture/module-architecture.md), [Tenancy Architecture](../01-architecture/tenancy-architecture.md), [API Standards](../02-standards/api-standards.md), [Authorization Standards](../02-standards/authorization-standards.md), [Database Standards](../02-standards/database-standards.md), [Logging/Audit Standards](../02-standards/logging-audit-observability-standards.md), [Testing Standards](../02-standards/testing-standards.md).
