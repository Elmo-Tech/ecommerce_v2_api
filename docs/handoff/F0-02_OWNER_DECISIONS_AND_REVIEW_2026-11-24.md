# F0-02 API Foundation — owner decisions confirmed; final Spec/Plan review pending

**Snapshot date:** 2026-11-24, Africa/Cairo  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration:** `development` | **Working branch:** `feature/f0-02-api-foundation`  
**[PR #4](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/4):** OPEN DRAFT, documentation only.  
**Status:** DEC-01…04 **4/4 APPROVED**; complete revised spec and plan **NOT YET APPROVED**; no execution authorization.

## Owner decisions and evidence

The owner answered the four options in the F0-02 review on 2026-11-24:
> 1- A
> 2- تمام موافق على ترشيحك
> 3- موافق
> 4- مظبوط موافق على كلامك

| ID | Approved decision |
| --- | --- |
| DEC-01 | Arabic (`ar`) fallback, supported ar/en language negotiation, compatible regional forms and quality weighting. Already consistent with approved LOC-ST-02 (2026-11-22); supersedes historical AS-03 unspecified fallback wording. |
| DEC-02 | Shared `ApiErrorCodes` string constants, int-backed `HttpStatusCode`, centralized mapping. No string-backed enum or global domain error catalog. |
| DEC-03 | OpenAPI 3.1 JSON canonical `specs/002-api-foundation/contracts/openapi.json`, shared components and empty paths, native lightweight structural/HTTP checks, no new dependency. No full OpenAPI conformance claim. |
| DEC-04 | Empty native `routes/api.php` registered at `/api/v1`; modules own future real routes through explicit Providers; no production endpoint or debug route added. |

These are approved Feature choices, not full Spec/Plan sign-off. No new business rule or override of Constitution/FBR-02.

## Revised review package

- [Specification](../../specs/002-api-foundation/spec.md), [Plan](../../specs/002-api-foundation/plan.md), [Research](../../specs/002-api-foundation/research.md), [Checklist](../../specs/002-api-foundation/checklists/requirements.md).
- [Original discovery/draft PR handoff](F0-02_DISCOVERY_AND_DRAFT_PR_2026-11-24.md) remains historical evidence of the initial open decisions.
- [Localization Standards LOC-ST-02](../02-standards/localization-standards.md), [API Standards](../02-standards/api-standards.md), [Constitution](../../.specify/memory/constitution.md), [Code Standards](../02-standards/code-standards.md), [Testing Standards](../02-standards/testing-standards.md).

## Findings / proposals still subject to final owner review

1. Early exact v1-path-scoped request lifecycle with server-generated request ID and locale, including route-not-found and method mismatch; preserve non-v1/non-API behavior and safe context cleanup.
2. Proposed additional generic wire codes: 400/`BAD_REQUEST`, 405/`METHOD_NOT_ALLOWED`, 503/`SERVICE_UNAVAILABLE`, and `HTTP_ERROR` for other genuine unclassified HTTP error statuses; preserve actual status and protocol headers. Existing AS-03 mappings remain authoritative. This exact vocabulary is not yet separately approved.
3. Real tests for 204 no body, validation object vs empty errors array, safe 500, weighted/fallback ar/en, ignored client Request-ID, repeated HTTP attempts and production cache isolation (real app has zero routes; scratch control route only).
4. Shared OpenAPI structural/ref/HTTP tests, no claim of full validation; FBR-02 string Money/ID rules preserved without creating domain Resource schemas.
5. Proposed minimal CI path coverage for translations and contract artifacts. Existing PHP 8.4 / Laravel 13 / Pest 5 runtime verified only for F0-01. Pint/PHPStan/Larastan still unavailable/unrun; do not claim passes or install without approval.

## GitHub baseline / boundaries

- Before this revision: feature HEAD `9719acc20841146de64a017b0d2b1484467db29d`; development HEAD `5155521f9574057d890f3f594f34f6f180af8a41` (canonical handoff-only commit). PR #4 was 5 docs, no executable changes; open draft, CodeRabbit skipped automated review, no substantive reviews or review threads.
- All F0-01 T001–T026 remain complete (26/26); PR #1 and #2 merged with recorded real CI. [Verified F0-01 handoff](F0-01_MERGED_AND_CLOSED_2026-11-24.md).
- No F0-02 runtime code, executable contract, task ledger, tests/CI execution, new dependency, migration, Auth, Tenant DB, Redis, deployment, merge, or Codespaces work authorized/performed in this decision phase. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.
- Exact next action: obtain owner approval of the complete revised Spec and separately the Plan (including the proposed generic error mappings and review findings). After both approvals, generate tasks, check consistency, and implement tests first and minimal code only under explicit authorization; real CI and substantive PR review precede separately authorized merge. Keep PR #4 Draft until appropriate gates. Refresh handoff after each milestone.
