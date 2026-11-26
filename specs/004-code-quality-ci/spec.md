# Feature Specification: F0-04 — Code Quality & CI Foundation

**Feature Branch:** `feature/f0-04-code-quality-ci`
**Created:** 2026-11-25 (Africa/Cairo)
**Status:** Owner-approved complete Spec/Plan (DEC-01…06 A); Tasks and Consistency Analysis prepared/reviewed, 2/56 planning tasks complete. **No implementation authorization; T003 pending.** No tool or project code executed, packages installed, CI or repository settings changed.
**Baseline:** development `24ce00231dec35a089bb70c081b257f9b5d234e7`; F0-01 26/26, F0-02 30/30, F0-03 40/40, verified PR #7/#8 merge and publication.
**Input:** Owner: DEC-01 A (Laravel Pint — PSR-12), DEC-02 A (PHPStan/Larastan — Level 6), DEC-03 A (Redocly CLI), DEC-04 A (Separate CI Jobs), DEC-05 A (Trivy + Dependency Audit), DEC-06 A (Branch Protection Plan). Prepare and review Spec and Technical Plan without executing code or installing packages.

## 1. Purpose, authority, scope

Make quality failures visible and enforce applicable checks on the actual integrated PR candidate before development merge. Keep native Laravel, simple reviewed engineering tooling and existing test safety boundaries. This Feature has no domain-data ownership and no new production endpoint.

Binding sources: [Constitution v1.0.0](../../.specify/memory/constitution.md) I–VIII (especially VII/VIII); [CS-08/09/11](../../docs/02-standards/code-standards.md); [TEST-01/05/09/10](../../docs/02-standards/testing-standards.md); [AS-08/09](../../docs/02-standards/api-standards.md); [SEC-05/06](../../docs/02-standards/security-standards.md); [Backend Architecture](../../docs/01-architecture/backend-architecture.md) BA-25/BA-30; [Workflow](../../docs/WORKFLOW.md); [F0-03 closure](../../docs/handoff/F0-03_MERGED_AND_CLOSEOUT_2026-11-25.md). [Discovery decisions](discovery.md) are approved; technical details in [plan](plan.md) are owner-approved; actual compatibility and verification remain pending.

## 2. User scenarios and independently testable acceptance

### US1 — Consistent PHP formatting and real type checking (P1)

A developer/agent can run the project's locked quality tools in a clean isolated checkout and obtain a deterministic, actionable result without editing tracked source.

- Given all tracked first-party PHP matches the approved PSR-12-compatible rules and level 6, formatting and analysis pass; an intentionally malformed scratch fixture or a real level-6 violation fails the respective command.
- Given an unsafe inherited service variable, nonempty/symlinked dotenv, cached config, or missing/unsupported analyzer, reject before application bootstrap; never inspect a live database to infer types.
- Formatting CI is read-only. Existing strict_types and executable semantics are preserved; new first-party PHP follows CS-08. Missing type information is addressed with meaningful types/docblocks and small justified corrections, not mass suppression.
- Test infrastructure is included. If a Pest dynamic construct genuinely cannot be analyzed, demonstrate the narrow limitation, prove alternative coverage, and obtain explicit review before a localized exception. No blanket exclude/baseline/ignoreErrors, or downgrade of level 6.

### US2 — Valid and reviewed API descriptions (P1)

A reviewer can validate the canonical OpenAPI 3.1 shared document and later registered Feature contracts without changing the API.

- Given current components-only F0-02 JSON (empty paths), accept the valid document without fabricating endpoints or suppressing structural/reference errors.
- Given malformed structure, wrong types, duplicate JSON keys, unresolved references, missing registered source, or a reference escaping the tracked contract boundary, fail; never resolve network/private-file input.
- Validate each registered root using the locked tool and verify the generated temporary bundle; no committed duplicate canonical contract, cloud upload, generated SDK or runtime route.
- Preserve existing HTTP and envelope contract tests. Changes to existing contract values, types, required fields, statuses, identifiers, money, authorization or visibility need explicit compatibility review. Structural validity is not automatic compatibility proof.

### US3 — Reproducible dependencies, immutable CI and image security (P1)

A reviewer can determine exact dependency and action identities and security scan evidence for approved test resources.

- Given a lock mismatch, unexpected package/plugin/runtime upgrade, unapproved action ref/permission, advisory-tool failure or incomplete security evidence, fail or block instead of reporting green.
- Given approved exact PostgreSQL and Redis test-image manifest digests, scan those linux/amd64 images with verified Trivy and a fresh advisory DB; record digest, DB age, coverage and findings. Do not run a container for scanning or touch a live service.
- Critical and material High findings block under SEC-06; unknown reachability of High is blocking until reviewed. Report fixed/unfixed and lower severities. No silent ignore-unfixed, blanket suppressions, automatic upgrades or image pin replacement.
- Any permitted noncritical exception has narrow identity/scope, owner, independent review when required, rationale, evidence, mitigation, expiry and follow-up. Expired/mismatched entries fail. Material blockers are remediated or escalated to an expressly authorized risk decision under the governing standards, never hidden by this Feature.

### US4 — Risk-selected checks and stable final gate (P1)

A reviewer can distinguish a real pass from a failed, blocked, skipped or non-applicable check for each exact candidate.

- Given a PHP/core/dependency/workflow/unknown or sensitive change, select all relevant checks, including guarded Fast and real owned native PostgreSQL/Redis Integration when required. Uncertain impact broadens selection.
- Given a demonstrably low-risk prose-only change, require relevant documentation checks and explicitly classify other checks NOT_APPLICABLE; no meaningless full DB suite.
- Given missing/duplicate/mismatched evidence, failed required check, required skip, cancellation, unsupported input or unverified candidate/base, the final gate cannot report success. Successful process exit alone is insufficient where validated evidence is required.
- Preserve both F0-03 lanes, actual denial tests, environment guards, immutable service pins, exact cleanup and safe report/retention semantics. Retain previous 451 case identities; only explicitly obsolete architecture expectations may be updated with stronger equivalent/new checks and a before/after mapping. No removed/weakened substantive test or assertion hidden in formatting/type fixes.
- Establish a stable final PR check name for development PRs; workflow-level path filtering cannot suppress its creation. No auto-merge or privileged untrusted PR execution.

### US5 — Traceable human review and branch policy (P2)

A maintainer can review exact quality evidence and a branch-protection activation plan separately from code implementation.

- Document required stable status, PR-only/no force push/no bypass policy, strict current base, applicable human review and separate merge consent.
- Actual repository settings are not changed until stable checks are verified and owner separately authorizes administrative activation; assess administrative permission/plan availability first. Preserve existing stronger rules and do not lock the team out. A manual admin runbook is acceptable only as a documented pending enforcement state, not proof protection is active.
- Technical review → actual owner human review (qualified independent review when applicable) → distinct development-only merge authorization → verified integration and closeout. Never fabricate GitHub approval.

## 3. Functional requirements

| ID | Requirement | Primary acceptance |
| --- | --- | --- |
| FR-001 | Locked dev-only Pint with PSR-12-compatible baseline; all tracked first-party PHP and nonmutating CI; strict_types for new PHP | US1, AC-01 |
| FR-002 | Locked PHPStan/Larastan level 6; meaningful types; explicit scope includes app/config/bootstrap/routes and first-party tests/support | US1, AC-02 |
| FR-003 | Apply existing accidental-target guard before analyzer autoload/bootstrap, safe scratch/cache; reject unsafe targets and missing tools | US1, AC-03 |
| FR-004 | Locked Redocly validates complete registered OpenAPI 3.1 roots and references, bundles safely, accepts valid components-only source | US2, AC-04 |
| FR-005 | Preserve reviewed contract meaning and HTTP checks; explicit base/current compatibility review for changes | US2, AC-05 |
| FR-006 | Pin third-party Actions to reviewed full commit SHAs and supported runtime; least privilege; reproducible dev tooling and scoped lock diff | US3, AC-06 |
| FR-007 | Actual locked Composer audit and isolated npm audit when present; safe outcome and dependency provenance; failures/missing evidence block | US3, AC-07 |
| FR-008 | Actual Trivy scan of two approved test images, verified scanner and fresh DB, complete findings and reviewed exceptions policy | US3, AC-08 |
| FR-009 | Deterministic risk/impact classification with conservative fallback and docs-only relevant checks | US4, AC-09 |
| FR-010 | Stable fail-closed aggregate check with complete expected check set and exact revision identity | US4, AC-10 |
| FR-011 | Preserve all existing regression cases and F0-03 guard/native/cleanup/report semantics; map and review necessary obsolete expectation updates | US4, AC-11 |
| FR-012 | Bounded safe reports, exact commands/tool versions/base/head/checkout/run/duration/result; seven-day CI artifacts; no raw secrets | US4, AC-12 |
| FR-013 | Verified branch protection plan, separately authorized activation if possible; review, merge and closeout gates | US5, AC-13 |

## 4. Acceptance criteria and measurable success

| ID | Required actual evidence at implementation review |
| --- | --- |
| AC-01 | Pint positive control and nonzero formatting failure; no tracked modifications after check; all inventoried PHP checked. |
| AC-02 | Level 6 actual clean run in scope; real typed negative fixture rejected; no hidden broad suppression; documented coverage. |
| AC-03 | Guard rejects unsafe environment before vendor/application bootstrap, no service connection; safe positive control, private cache cleanup. |
| AC-04 | Current canonical source and valid local-ref fixture accepted; invalid structure, duplicate key, missing/unresolved and escaping/remote refs rejected; safe temporary bundle validated. |
| AC-05 | Existing API tests pass; base/current contract comparison and signed-off compatibility review if meaning changed; no fake endpoint. |
| AC-06 | All applicable action refs and runtime verified, locked installs and dependency diff reviewed, negative unpinned/privileged workflow fixture rejected. |
| AC-07 | Real locked audits execute; nonzero tool/advisory and missing/malformed evidence fail closed, with controlled fixture/probe not adding a vulnerable runtime dependency. |
| AC-08 | Both actual approved image digests scanned, DB freshness and complete reports verified; controlled severity/expired exception/failure cases correctly classified; no unapproved pin change. |
| AC-09 | Real diff classifications for docs-only, contract, PHP, core/CI/dependency, unknown and deletion/rename cases; no unsafe docs-only classification. |
| AC-10 | Actual CI full required green; actual controlled failure/block/skip/missing-evidence checks remain non-green; exact-head/base/checkout association verified; no unconditional success. |
| AC-11 | All prior 417 fast + 34 native case identities retained; all previous substantive checks preserved, permitted expectation adaptations explicitly mapped and reviewed; actual 31 foundation/9 architecture rerun subsets and both native services checked. New tests may increase counts. |
| AC-12 | Safe reports independently checked for expected schema, exact identity, counts/status/durations and allowed artifacts; credential canary and ownership/collision negative tests. |
| AC-13 | Plan and exact prerequisites documented; owner review and separate merge approval recorded; actual settings status verified if activation separately authorized; no fake protection claim. |

**Success criteria:** SC-01 zero unaccepted Pint or level-6 findings within approved scope; SC-02 all required registered OpenAPI roots valid and no unresolved/unapproved refs; SC-03 complete current locked dependency/scan evidence with no unresolved material blocker; SC-04 every expected applicable lane represented with honest result and stable final aggregate; SC-05 retain 451 baseline test identities and all meaningful earlier assertions, with documented legitimate policy changes; SC-06 no production/shared target access, tracked mutations by checking, or credential leakage; SC-07 reviewed development-only integration and current handoff; technical branch enforcement described accurately. Measure actual runner duration and resource usage; no fabricated performance SLA.

## 5. Edge cases and exclusions

Empty suites/contract inventory, missing changed files, renamed/deleted files, shallow/inaccessible base, workflow-only changes, root-level or unknown files, dependency solver conflicts, Pest dynamic typing, unsafe analyzer bootstrap, Redocly resolver escapes, tool output malformed, registry/advisory DB unavailable/stale, vulnerability exceptions expired, artifact upload failure, cancelled job, stale base/head, and absent administrative permission all require explicit handling. Missing required proof is BLOCKED, never PASSED. Optional tool absence is no longer acceptable for an applicable F0-04 gate.

No production app feature, tenancy/auth/authorization, migrations, stock/order/financial behavior, Horizon/Octane, frontend, production Dockerfile/build/push/deploy, GHCR/Coolify, main promotion, automatic branch settings mutation or F0-05 implementation. Formatting and narrow type fixes only where reviewed and behavior-preserving; a real behavioral bug/scope expansion requires separate decision/review. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED.

## 6. Approval boundary

The complete Spec and Plan are owner-approved at the recorded revision below. [Tasks](tasks.md) and [Consistency Analysis](consistency-analysis.md) are prepared for owner review and separate implementation authorization. [Technical Plan](plan.md), [Research](research.md) and [requirements checklist](checklists/requirements.md) retain actual outstanding gates. No install, code/test execution, scanner, new CI, admin mutation or merge authorized at this stage.


## 7. Owner approval record (2026-11-25)

On 2026-11-25 the owner explicitly approved the complete F0-04 Spec and Technical Plan at source `effc624e5b019047782eb940a56e288cb9373c93`, including the six A decisions, proposed versions/action pins, CI matrix, security policy (24-hour DB freshness), safety boundaries, timeouts and stated limitations, and requested tasks.md and Consistency Analysis with review only, without executing project code or installing packages. This is not implementation, tool/scanner execution, workflow dispatch, branch administration, merge or deployment authorization.

[Tasks](tasks.md) and [Consistency Analysis](consistency-analysis.md): 2/56 documentary complete, T003 pending. No substantive approved requirement changed by this status reconciliation.
