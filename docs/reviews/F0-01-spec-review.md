# F0-01 — Feature Specification and Constitution Review

> **Status: REVIEW COMPLETE / OWNER APPROVAL PENDING** — 2026-11-23.  
> **Branch:** `feature/f0-01-laravel-bootstrap` (planning only; based on `feature/f0-spec-kit-setup`).  
> **Canonical specification:** [F0-01 spec](../../specs/001-laravel-bootstrap-module-registry/spec.md); [Constitution v1.0.0](../../.specify/memory/constitution.md).

## Review result

The existing three user stories, requirements FR-001…FR-011, edge cases and outcomes fit the approved **F0 Engineering Foundation** direction. No business-domain implementation, Tenant schema, authorization flow or live merchant behavior is within this Feature. The review adds precise non-destructive bootstrap, test-only diagnostics, package-boundary and negative registration criteria (FR-012…014; SC-006…007). **These are proposed clarifications, not new D/OV product decisions.**

| Constitutional principle | Assessment | F0-01 treatment / remaining evidence |
| --- | --- | --- |
| I. Approved sources and bounded scope | PASS — spec scope | MA F0, FND-01, FND-02 and existing CS/TEST govern; F0-02/F1 excluded; owner sign-off still pending |
| II. One modular monolith, One Writer | PASS — planned design | Explicit providers only, no directory discovery or 16 empty modules; lightweight `app/Core/Modules` and test probe proposed |
| III. Trusted tenancy and least privilege | N/A to business data / PASS on exclusions | No Central/Tenant connection, identity, permissions or untrusted Tenant selection in F0-01; future F1 separately reviewed |
| IV. Stock/money correctness | N/A, no affected business state | No DB tables, transactions, inventories, money or events introduced |
| V. API and security contracts | CONDITIONAL — verify test-only routing | No production feature/API envelope or `install:api`; test probe only within test environment; production route absence must be proven |
| VI. Outbox, files and audit | N/A to runtime effects | No queues, outbox, uploads or business logs; do not imply their delivery |
| VII. Spec and test evidence | CONDITIONAL — future runtime tests | Separate `spec.md` / `plan.md` / `tasks.md` drafts exist; automated smoke, duplicate/missing/unregistered and production exclusion tests require real PHP runtime |
| VIII. Simple, compatible and gated | CONDITIONAL — compatibility & owner gates | Laravel 13 requires PHP >=8.3 (official docs); exact environment/Pest/Composer and CI must be verified before implementation/merge |

## Verification against repository architecture

- MA `13 and CS-01 require an **explicit** Module Registry, module-owned Providers, route registration, no unrestricted directory scanning and no circular ownership.
- MA `16 F0 says a minimal registered module must expose a contract and pass Module/API boundary tests, **not** that tenant or other domain modules should be built immediately.
- TEST-01/02 require risk-based tests; PHPUnit/Pest compatibility and actual executable tests must not be guessed from an empty repository.
- Existing `spec.md` aligns with the Spec Kit v1.4.3 user stories/requirements/success outcomes template; agent-neutral `none` mode means no agent `/speckit.plan` invocation is claimed.
- FND-01 uses exactly one Feature folder under `specs/001-laravel-bootstrap-module-registry/`; technical Foundation Overview links to it but does not duplicate it.

## Current design proposals for owner review

1. **Repository layout:** Laravel skeleton at repository root, incrementally integrated without replacing existing documentation or history.
2. **Provider registry:** a tiny static config-list-driven Module Registry; validate duplicate/missing/invalid Providers; never auto-scan module folders.
3. **Probe boundary:** one non-business `FoundationDemo` provider; a route/contract visible in the **testing environment only**, and absent in production/route caches.
4. **Dependency ordering:** F0-01 installs only the minimum Laravel skeleton and chosen test dependencies; defer Sanctum installation, Tenant provisioning, DB migrations, Redis and normal production API envelopes.
5. **Testing:** real framework bootstrap, registration (positive and negative), no unwanted side effects and route-exclusion verification; record command/runtime output accurately.

These are **draft technical choices** in [plan.md](../../specs/001-laravel-bootstrap-module-registry/plan.md) and should be approved or revised before code. No CI/runners or PHP versions have been tested in this PR.

## Explicit open implementation gates

| Gate | Current status | Needed before action |
| --- | --- | --- |
| Spec approval | PENDING | Owner approves reviewed F0-01 `spec.md` |
| Technical plan approval | PENDING | Owner reviews Registry and probe design and `plan.md` |
| Runtime/framework compatibility | NOT RUN | Exact PHP/Composer versions; Laravel 13 `^13.0` and test tools resolve under real environment |
| Test runner reliability | NOT VERIFIED | Codespaces/manual runs can produce evidence; GitHub Actions #45083043107/#45083678045 previously failed before steps |
| Tasks authorization | DRAFT ONLY | Owner authorizes implementation after spec and plan approval |
| Parent Spec Kit setup PR | OPEN / DRAFT | PR #1 must be approved and integrated separately before direct F0-01 merge into `development` |
| Release/legal/recovery | UNCHANGED | D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED |

**Decision requested next:** Review the linked F0-01 `spec.md` and `plan.md`. Approving this review does not by itself authorize production deployment, Tenant DB operations, or a PR merge.

**Sources:** [Backend Architecture](../01-architecture/backend-architecture.md), [Module Architecture](../01-architecture/module-architecture.md), [Code Standards](../02-standards/code-standards.md), [Testing Standards](../02-standards/testing-standards.md), [FBR-02](FBR-02-architecture-gap-review.md), [Laravel 13 release notes](https://laravel.com/docs/13.x/releases), [Laravel 13 providers](https://laravel.com/docs/13.x/providers).
