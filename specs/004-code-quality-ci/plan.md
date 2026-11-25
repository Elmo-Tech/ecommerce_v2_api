# Technical Plan: F0-04 — Code Quality & CI Foundation

**Branch:** `feature/f0-04-code-quality-ci` | **Date:** 2026-11-25 | **Spec:** [spec.md](spec.md)
**Status:** Full proposed design for owner review; DEC-01…06 A approved. No implementation or packages authorized. [Research](research.md), [Discovery](discovery.md).
**Baseline:** development `24ce00231dec35a089bb70c081b257f9b5d234e7`; actual [CI #45614086597](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45614086597) 417 fast + 34 native = 451 unique cases / 2822 assertions; independent 31/134 and 9/101 subsets. PR #7 code and PR #8 closeout verified; no new CI run in this planning step.

## 1. Summary and technical context

Keep a Laravel 13 / PHP 8.4 modular monolith. Add only dev quality tooling: locked Pint PSR-12, PHPStan/Larastan level 6, isolated locked Redocly 3.1 validation, separate CI jobs plus a stable fail-closed gate, locked audits and exact approved test-image scans. Existing PHP/Pest native CI behavior and test-only service safety remain binding. No runtime service or database changes. GitHub-hosted Ubuntu 24.04 linux/amd64; no self-hosted runner, production credential, remote shell, Codespaces or deployment.

Proposed candidates: Pint **1.32.3**, PHPStan **2.3.7**, Larastan **3.15.0**, Redocly **2.83.0**, Node **24.22.3** (actual npm >=10.9.2), Trivy **0.76.2**, action SHAs and scanner artifact hashes in [research](research.md). Actual compatibility unverified. PHP 8.4.28 previously tested, Laravel 13.43.0/Pest 5.3.7/Pest plugin 5.0.1/PHPUnit 13.4.7 currently locked. Composer 2.11.3 previously tested; record actual supported version. Retain native drivers pdo_pgsql matching PHP 8.4, PhpRedis 6.3.0. Existing Python 3.12 / PyYAML 6.0.2 Spec Kit regression setup remains, with reviewed pinned setup action. No new product dependencies.

## 2. Constitution compliance check

| Principle | Design evidence |
| --- | --- |
| I | DEC-01…06 approved, CS/TEST/AS/SEC source traceability; no business decision invented. |
| II | Engineering-only tools, no new module/provider/writer/framework. |
| III | Reuse pre-autoload guard; no shared/prod database or Redis; owned Integration unchanged. |
| IV | No stock/money/transaction mutation; existing actual infrastructure proof retained, no new domain proof claim. |
| V | Preserve canonical OpenAPI JSON and wire contracts; review breaking changes; no fake endpoint. |
| VI | Least privilege, immutable inputs, safe artifacts, no credentials or untrusted privileged workflow. |
| VII | Actual positive and negative evidence, risk-selected checks, stable non-green on missing proof; no blanket suppressions. |
| VIII | Native thin tooling, locked compatible versions; separate approval, no main or deployment. |

No constitutional exception proposed. Any real material conflict blocks affected implementation and is escalated. No hidden weakening of F0-03.

## 3. Planned file scope (not created)

```text
composer.json / composer.lock                # dev-only dependencies, reviewed lock delta
pint.json                                    # PSR-12-compatible rules
phpstan.neon                                 # level 6, Larastan explicit include
tools/quality/
  run.php                                    # fixed guarded PHP entry/analysis
  classify.php                               # exact changed-file classification
  verify-gate.php                            # fixed expected-lane/status validation
  evidence.php                               # minimal safe report contract/validation
  check-docs.php                             # local changed Markdown links/consistency
  check-workflows.php                        # immutable action/permission policy
  openapi/
    package.json / package-lock.json         # isolated tooling only
    redocly.yaml                             # spec/struct/ref errors
    inventory.json                          # root registry (pointers, not copies)
    validate.mjs                            # bounded local references + lint/bundle
  security/
    scan-images.php                          # fixed image digest policy and scanner
    exceptions.json                          # empty initially; reviewed narrow exceptions
.github/workflows/
  f0-01-laravel-bootstrap.yml                # evolve to stable always-emitted PR gate
  fnd-01-spec-kit-review.yml                 # pinned actions; reusable regression lane
tests/Unit/Foundation/                       # targeted tool/classification/evidence negatives
tests/Fixtures/Quality/                       # synthetic non-runtime negative fixtures
tests/Architecture/
  ApiFoundationBoundariesTest.php             # necessary explicit dev policy adaptation
  TestingFoundationBoundariesTest.php         # same plus reviewed workflow policy
specs/004-code-quality-ci/
  spec.md, plan.md, research.md               # planning now
  checklists/requirements.md
  tasks.md, consistency-analysis.md          # after Spec/Plan approval
  quickstart.md                              # actual evidence after authorized execution
docs/reviews/ and docs/handoff/               # actual review / verified closeout later
```

Names are proposed implementation paths, not existing files. Prefer small native PHP/Node helpers over an internal framework; adapt names only with traceable reviewed justification. Only necessary formatting and narrow typing/docblock fixes in tracked first-party PHP are permitted; any behavioral correction must be separated and reviewed. No actual app/provider/route/schema business feature. Test-only dependencies remain require-dev. Ignore generated caches, node_modules and bundles; do not commit raw reports, vendor or scanner DB.

## 4. Dependency preflight and lock policy

After separate implementation authorization, use an isolated clean checkout. First reproduce baseline via existing required CI, then verify published candidates with actual targeted solver/dry-run; resolve only three exact dev additions in composer.json and lock (plus necessary new dev transitives). Compare every existing locked package record: no runtime or Pest/PHPUnit version/source drift; no unrestricted composer update, platform-requirement ignore, forced upgrades or plugin auto-allow. Existing Pest plugin allowlist only; use locked install without scripts/plugins where compatible with the current verified harness. Explicitly review any new plugin or unavoidable dependency change before proceeding. Verify PHP extensions and actual version, installed binaries, composer validate strict, platform checks, audit locked. Do not run dependency setup in this planning revision.

Node toolchain separate under tools/quality/openapi; exact Redocly candidate in package.json devDependencies, lock with registry/integrity review; use npm ci --ignore-scripts and audit locked dependency graph; no npx latest, root frontend package, global npm install, cloud credentials, automatic audit fix. Verify actual Node/npm versions, inspect lock URLs/integrity and direct/transitive changes; if required lifecycle scripts appear, review them separately rather than enable all. Disable automatic cache for install until trust isolation is verified; no shared cache keyed only by branch or untrusted restore of executable tooling.

## 5. Formatting and static analysis (FR-001–003)

### Pint
PSR-12 preset candidate, non-risky rules only unless individually reviewed. Inventory tracked first-party PHP via NUL-delimited git paths with exact file count and no generated/vendor/symlink escape. Include app, bootstrap, config, routes, public, lang, tests, tools, artisan and future tracked PHP migrations when present. Treat artisan as PHP explicitly. Negative fixture code is materialized in owned scratch rather than committed as malformed executable PHP. CI read-only, do not auto-format in runner. Proposed local commands after authorized installation: `php tools/quality/run.php format` (fixed equivalent to vendor/bin/pint --test for inventory); explicit developer fix command separate. Formatting-only commit first; review diff to preserve strings/comments with semantic impact, declare(strict_types) and behavior. Strict types are required for new files; do not mass-add strict_types to existing files just as formatting.

### PHPStan/Larastan
`phpstan.neon` includes Larastan explicitly and fixes level: 6. Analyze all tracked first-party PHP paths including tests, support and CLI, with verified framework/Pest handling. No global baseline, blanket ignoreErrors, unreviewed directory exclude or level downgrade. Correct findings through typed arrays/generics, narrow DTO/value types and accurate framework extension handling. A genuine dynamic Pest false positive needs exact file/identifier/reason, minimal scope, alternate meaningful test and owner review before any exception; no wildcard exemption of tests. Plan future strictness increases only after measurable zero findings at current scope.
Use the same safe checkout semantics as F0-03: call TestEnvironmentGuard::applyFast before Composer/vendor autoload or process startup, validate clean canonical root, reject service variables, unsafe .env variants/cache, initialize isolated defaults, then launch exact locked PHPStan executable with fixed config/level/paths, validated inherited environment and private cache/temp dir. Guard runs in parent before subprocess, not solely phpstan bootstrapFiles. No real DB/Redis/mail/queue service or external endpoint access. Do not analyze by loading arbitrary env/config from developer machine. Safe bounded diagnostics, raw child output private, no tracked changes, cleanup exact owned files. Proposed command `php tools/quality/run.php analyse`. Verify actual guard-before-autoload with positive and controlled negative subprocess probes, real level-6 finding, nonzero exit and tool unavailable paths.

## 6. OpenAPI pipeline (FR-004–005)

One canonical JSON root remains in owning spec: `specs/002-api-foundation/contracts/openapi.json`; current empty paths and shared components are valid scope. Registry lists reviewed roots (future `specs/*/contracts/openapi.json`) and local fragments; discover tracked roots independently, require exact inventory match, nonempty list, no silent missing/renamed root. No duplicated generated source or route generation. Strict JSON parse (duplicate keys must be detected, not silently overwritten), require approved OAS 3.1 roots; reference preflight resolves only allowed tracked local JSON files within approved contracts, rejects symlink, absolute paths, traversal outside boundary, remote URL, scheme/network-path, ambiguous encoded/backslash references and unresolved anchors. Evaluate $id, $dynamicRef and $schema rebasing/dialect: allow current safe internal/local supported semantics, reject unsupported/external rebasing before CLI; no arbitrary network or file dereference. Bound file size, traversal depth and count; cycles handled by visited graph, not unlimited recursion. Test real resolver rejection with synthetic remote and escaping refs without making a request. Future non-JSON or dynamic/remote schema design requires review; do not silently ignore.
Invoke locked local Redocly with explicit config, structural and no-unresolved-refs errors (spec ruleset verified), warnings policy explicit; components-only permitted without invented paths or disabling structural checks. Run lint on each root, bundle to owned temporary JSON, revalidate bundle and confirm no unresolved/unapproved refs or semantic change for current components. Do not use remove-unused-components on canonical shared contract; avoid dereferencing cyclic schemas. REDOCLY_TELEMETRY=off, REDOCLY_SUPPRESS_NOTIFICATIONS=true; no tokens, login, respect/live API calls, docs build or cloud publish. Enforce local-only resolver/egress boundary in actual test; offline validator after locked install. Existing HTTP contract tests remain required for contract changes. Compare base/current JSON and registered roots; classify all changed wire behavior, field/required/type/status/permission/money changes and request owner review of possible breaking changes. Redocly lint alone does not prove API compatibility or runtime authorization. No SDK or frontend work. Prove actual positive current root, invalid structure, bad/missing ref, missing inventory, duplicate keys, escaping and valid local fragments.

## 7. CI architecture and change classification (FR-006,009–011)

Evolve existing Laravel workflow into one stable entry for every relevant PR targeting development, with no workflow-level path filters for that event. Keep legacy needed push/manual triggers explicitly separate (no untrusted pull_request_target). Reuse FND-01 regression via workflow_call when selected by aggregate; retain its existing relevant legacy push/manual behavior, avoid duplicate required status names. Validate source/head/base and integrated checkout parents/tree with exact PR metadata and local Git; use read-only checkout with persist-credentials:false, adequate history and verified base. Compare exact base to integrated tree and inspect both old/new paths on renames/deletions. Reject ambiguous diff, missing base or unexpected identity; uncertain scope selects broad gates, missing identity blocks. Use case-sensitive exact path predicates and explicit allowed prose Markdown areas; a .md extension alone is insufficient. Contract directories, .github, .specify scripts/templates, governing policy/constitution/standards, manifest/lock, tooling, PHP, executable files, symlinks, unknown roots cannot receive docs-only shortcut. Changes affecting policy require broad checks and review even if Markdown. A policy decision in approved docs is not silently changed by a check.

| Change | Docs | Format | Analysis | Contracts | Dependency audit | Image scan | Fast | Native | Spec Kit |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Verified prose-only, no policy change | R | N | N | N | N | N | N | N | N |
| Canonical contract-only JSON | R | N | N | R | R | N | R | N* | N |
| First-party PHP / shared Core / test helpers | R | R | R | R | R | R | R | R | N* |
| CI / dependency / security / quality tooling | R | R | R | R | R | R | R | R | R |
| Spec Kit scripts/templates | R | R | R | R | R | R | R | R | R |
| Unknown/ambiguous impact or sensitive scope | R | R | R | R | R | R | R | R | R |

R required, N justified NOT_APPLICABLE; N* broaden to R when relevant or uncertain. This intentionally conservative proposed matrix suits small current Foundation, not a permanent optimization claim. Future selective suites need demonstrated impact coverage and reviewed change. Docs lane checks changed links within repository (no network fetch), known Markdown consistency and decision/ledger references; report external URLs as not verified, avoid unrelated historic link failures hiding current change. For docs-only PRs, no heavy DB load required. A code PR cannot be green solely because docs passed.

Separate jobs: `classify` → `docs`, `format`, `analysis`, `contracts`, `dependency-audit`, `image-scan`, `bootstrap` (existing Fast, lint/lock), `native-integration` (existing dependency on bootstrap), `spec-kit` (reusable). Preserve guarded commands run-report.php foundation / fast-all / architecture / integration, actual PostgreSQL/Redis fixtures and ACL grants/denials, image pins, exact cleanup, safe XML/JSON and seven-day retention. If prerequisite fails, mark downstream BLOCKED with real dependency reason, not N/A. Do not let image scanner access integration descriptors or secrets. No required continue-on-error, automatic retry-to-green or weakening skip/failure flags.
Final unique stable PR check **F0-04 / Quality Gate** (name to be verified in actual GitHub check listing before branch policy activation). Job runs after all required/optional dependency outcomes with always() semantics; if cancellation prevents final job, absence/cancelled status is not success. Use fixed list of known job IDs and trusted classification record; require exact identity/revision, each expected required job success AND validated evidence; missing/duplicate/unknown/mismatched result or cancelled/failed/skipped required job -> nonzero. Optional jobs may skip only with explicit proven N/A, not simply because skipped. If evidence upload/cleanup fails, job cannot pass. Fetch only fixed same-run, same-attempt required artifact names into separate private job-owned directories using pinned actions/download-artifact with digest-mismatch:error; no cross-run/repository token, arbitrary glob download, merge-multiple, or execution of artifact contents. Validate expected file inventories, safe paths, bounded sizes, parsed summaries, identity and digests, fail on missing/duplicate/unsafe inputs. If the tool requires additional permission, stop and review minimal read-only scope before use. Cross-check classification against candidate; aggregate must not trust arbitrary supplied success flag. Actual hosted positive and controlled negative diagnostics, not just pure simulation. No privileged execution, auto-merge or auto-deploy. A PR can alter workflow code itself; ordinary CI is not a malicious-code sandbox. Sensitive CI changes require human review of exact workflow and stronger applicable rules.

Pin six actions to exact candidates in [research](research.md), verify their Node 24 runtime and runner compatibility. contents:read only where required, no write/publish/id-token permission or production secrets. Reverify artifact behavior and FND suite. Keep current required tests, and explicitly adapt obsolete exact dev-list expectations in ApiFoundationBoundariesTest.php and TestingFoundationBoundariesTest.php, plus old workflow literal check in latter, to equivalent/stronger policy (structural parse or deterministic checks of event, final gate, required commands, owned cleanup, pins, least privilege and no dangerous commands). Do not delete cases or merely preserve counts while reducing coverage; document old/new assertion mapping, actual case inventory and any expected count deltas. Preserve runtime dependency list, no routes/migrations or test leakage, native denial and report protections. Formatting/type fixes reviewed separately.

## 8. Security policy (FR-006–008)

Composer locked audit and npm audit with actual exit/result/schema and registry reachability; no broad suppressions or auto upgrade. Nonzero/malformed/missing evidence fails closed. Record advisory identity and affected locked package/version, risk and disposition, no raw credential or registry token. Native scanner:
- Fixed scope exact approved manifests only:
  - postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03
  - redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87
- Verify Trivy 0.76.2 acquisition and exact published linux/amd64 archive SHA/checksum provenance from research before running; no mutable latest or pipe-to-shell, fail if verification fails.
- Owned hosted scanner job with no service credentials, no Docker container execution; verify image platform/manifest identity and scan exact digest from approved registry, not same-name local image fallback. Separate private scanner cache. Fetch current supported vulnerability database once per run, verify schema/metadata, record DB identity/digest when available, UpdatedAt/NextUpdate and UTC scan time. Proposed freshness bound: UpdatedAt no more than 24 hours old, not future beyond small measured clock tolerance; honor expired NextUpdate; unavailable, malformed, stale DB, missing OS package inventory/unsupported target or failed scanner is BLOCKED. No reuse stale cache or skip-db-update without same-run verified fresh data. Recheck freshness after scan.
- Actual vulnerability scan of OS/package ecosystem supported by scanner, JSON parsed against versioned output contract with expected image identity, target and result coverage; absence of findings is not proof of absence of all vulnerabilities. Full safe report retains severity/ID/package/version/fix status, no raw environment or confidential file contents. Record lower severity and unfixed; never blanket --ignore-unfixed. Default all Critical and High block pending materiality review; lower risk findings tracked. For permitted exceptions only, exact CVE/advisory + package/version + image digest + risk evidence + mitigation + owner + required independent reviewer + expiry/follow-up; no wildcard/expired entries. Critical/material High remain blocking unless remediated or explicitly escalated under governing SEC-06 exception authority, never inferred from plan. No approval for any specific exception now.
- If current test images have material findings, pause and propose separately reviewed patch/pin change with actual native regression. Do not silently swap image pins or change grants to make scanner green. No production images yet; future build/release scan remains separate.
- Actual positive and negative classifier fixtures (Critical/High, fixed/unfixed, missing DB, wrong digest, expired exception, tool failure), actual scans of both images and independently checked evidence. Simulated advisory fixture does not prove actual scanner reachability.

## 9. Evidence and diagnostics (FR-010–012)

Proposed versioned bounded JSON summary per lane: schemaVersion, repository, event, pr, sourceSha, baseSha, checkoutSha, runId, runAttempt, job, required, selectionReason, status (PASSED/FAILED/BLOCKED/NOT_APPLICABLE), runnerStarted, exitCode, versions, durationSeconds, selectedCount/validatedCount where meaningful, artifact names/digests, safe finding counts. N/A only when applicability predicate verified, not evidence of execution; blocked carries no fake result. Required true + absent runner/evidence/invalid schema cannot PASS. Keep F0-03 existing safe XML and summary schema, do not replace with generic output; adapt aggregate by validating those existing reports. Fixed allowlists, no arbitrary path publication, private per-job owned directories and exclusive creation, bounded data and report counts, exact cleanup after upload with failure visible, retention seven days. No env dump/raw stack traces/token/private URLs, use fixed error categories and canary negative tests. Preserve actual failure and any controlled retries with reason. Compare prior test identity and per-case assertions except explicitly reviewed adapted policy expectations. Record actual runtime/tool versions and measurements, no fabricated time SLA. Proposed timeouts for bounded hosted work: fast/format 10 min, analysis/contracts/audit 15 min, native and image scan 20 min, final gate/docs 5 min; review actual observed need, timeouts are limits not performance promises.

## 10. Branch protection plan (FR-013; not activated)

After implementation candidate is stable and merged under the existing approved human workflow, identify actual exact final check name and app source. Separately seek owner admin authorization before mutation. Inspect repository plan/permission and existing inherited rules; previous administrative API access limited. Proposed development rule: require PR before merge, require stable quality gate and up-to-date current base, block force pushes/deletion, no direct pushes/bypass (including admin where supported), resolve conversations, restrict bypass actors to none; formal required review settings must match team capability and qualified-independent-review requirement, not self-approval. Propose one independent approval for protected branches when eligible reviewer available; if owner is PR author and cannot approve, arrange eligible reviewer or separately agree on policy before activation; never fake approval or silently weaken existing rule. Keep main untouched. Plan safe activation order and rollback via separately authorized admin change, verify by controlled docs PR/status and rejected direct-push policy test without attempting a destructive real push. If access/plan blocks, document manual GitHub settings steps, required check exact name, and pending enforcement; do not claim active protection. DEC-06 approves plan, not actual administration. No bypass to merge the feature or any closeout.

## 11. Proposed execution sequence (not Tasks yet)

1. Review Spec/Plan/research and owner approval; then generate Tasks + consistency analysis and obtain separate implementation authorization.
2. Verify exact baseline and prepare actual locked tool preflight; review pin/lock compatibility.
3. Test-first failures for formatting, type checking, safe bootstrap, reference boundary, classifier, audit/scanner evidence and aggregate.
4. Install approved dev-only tools in isolated runner, format-only commit, narrow level-6 type corrections, OpenAPI validation and safe outputs.
5. Implement separate lanes, immutable actions, docs/risk classifier and final gate; adapt obsolete architecture assertions with traceability.
6. Verify actual scan/audit findings; resolve blockers or escalate; prove failure handling and all previous regressions; actual docs-only and full integrated candidate CI.
7. Review exact source, evidence and risk; owner/qualified independent human review as applicable; separate development-only merge consent and verified integration. Document branch policy/activation status; activation only if separately authorized. Closeout under approved workflow.
No Task IDs assigned yet. No implementation authorization inferred from Plan approval.

## 12. Explicit Spec-to-Plan traceability (documentary review)

| Requirement | Plan sections | Acceptance / planned proof |
| --- | --- | --- |
| FR-001 | 4, 5 | AC-01 format success/failure, exact inventory and read-only diff |
| FR-002 | 4, 5 | AC-02 level 6 actual findings and clean selected scope |
| FR-003 | 5, 9 | AC-03 early guard, subprocess negatives and exact cleanup |
| FR-004 | 6 | AC-04 OAS 3.1, inventory, local-only references and bundle |
| FR-005 | 6, 7 | AC-05 contract meaning/base comparison and HTTP regressions |
| FR-006 | 4, 7, 8; research | AC-06 exact dependency/action identity and workflow policy |
| FR-007 | 4, 8 | AC-07 locked audits and failure/missing-evidence handling |
| FR-008 | 8, 9; research | AC-08 actual pinned images, fresh DB, severity/exception proof |
| FR-009 | 7 | AC-09 conservative verified change classification |
| FR-010 | 7, 9 | AC-10 complete expected-lane gate and negative hosted evidence |
| FR-011 | 5, 7, 9 | AC-11 preserved cases and mapped obsolete policy changes |
| FR-012 | 7, 9 | AC-12 safe bounded reports, exact identity and artifacts |
| FR-013 | 10, 11 | AC-13 separate admin and reviewed merge/closeout plan |

All 13 requirements are covered at design level. Full task/dependency consistency review remains a separate next-stage deliverable. No actual quality gate is marked passed here.

## 13. Explicit outstanding gates

Published compatibility is not actual installation, actual analysis, API validation or security scan. Exact transitive lock diffs, real level-6 findings, scanner advisory DB/finding inventory, final GitHub check name, actual runner/artifact compatibility and branch admin access remain unverified. Proposed 24h DB freshness, timeouts, matrix, file layout, exact pins and review policy are technical decisions submitted for owner Plan approval, not previously approved numbers. If actual preflight contradicts any, stop affected work and ask for reviewed revision. No changes to main, production, F0-05, application Tenancy/Auth/financial/worker behavior; D-201 OPEN, BA-16 DEFERRED, BA-30 G3 BLOCKED.
