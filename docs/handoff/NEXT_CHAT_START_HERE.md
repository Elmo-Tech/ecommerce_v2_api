# START HERE — E-commerce SaaS V2 API — current authoritative handoff

**Last verified:** 2026-11-25 (Africa/Cairo)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Integration branch:** `development`  
**Current phase:** F0-03 Testing Foundation implementation MERGED and verified, T001–T039. T001–T039 verified (39/40); T040 documentation prepared in this closeout candidate. **40/40 becomes effective only after this separate reviewed docs-only PR is merged into development and publication is verified.** Before then T040 remains pending.

## MUST READ FIRST

1. [F0-03 verified merge and closeout](F0-03_MERGED_AND_CLOSEOUT_2026-11-25.md), [canonical tasks](../../specs/003-testing-foundation/tasks.md), [quickstart and evidence](../../specs/003-testing-foundation/quickstart.md), [technical review](../reviews/F0-03-technical-review-2026-11-25.md), and [approved workflow](../WORKFLOW.md).
2. [F0-01 closed](F0-01_MERGED_AND_CLOSED_2026-11-24.md) (26/26; PR #1 and #2 merged); [F0-02 verified closeout](F0-02_MERGED_AND_CLOSEOUT_2026-11-25.md) (30/30; PR #4, #5 and #6 merged; #6 verified at `97c5895bd5672261247b45f1b7eca5648afaf163`). Historical snapshots may state earlier pending stages; do not reopen completed gates.
3. F0-03 [PR #7](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/7) merged at `950c775ed1d964d7331e735b1202b0a9c091ddbc`; actual tested tree `917313c81f5e9e59ad88c00bc6d6354f1cad534d` equals merged tree. [Ready CI #45614086597](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45614086597) passed 417 fast + 34 real native cases, 2822 assertions; 31/134 and 9/101 independent subsets. R01 resolved; owner review and separate consent recorded. No separate post-merge CI claim.

## NEXT EXACT ACTION

Verify this separate T040 closeout PR, obtain its separate publication approval, merge only to development if approved and verify publication; only then mark F0-03 closed (40/40). If already published, verify actual merged state and files rather than opening another checkbox PR. **Do not start F0-04** (including discovery/planning) until new explicit owner authorization. F0-04 Code Quality/CI and F0-05 workflow/traceability remain separately specified. No main, deployment, Codespaces, live Pilot, new packages or migrations.

## Quality and scope boundaries

Pint, PHPStan/Larastan, full OpenAPI conformance and broader action/image supply-chain tooling are not installed/verified in F0-03; Node action deprecation warning retained for F0-04. Composer audit is not a comprehensive security or container-image audit. This test-only guard is an accidental-target boundary, not a malicious-code sandbox. Sequential hosted job isolation is not a concurrency/capacity claim; abrupt runner loss before durable capture is not guaranteed recoverable. No application tenancy, provisioning, auth/CSRF, Redis/Horizon workers, Octane, business transaction, inventory, financial or production readiness claim. D-201 OPEN; BA-16 DEFERRED; BA-30 G3 BLOCKED. F0-04 and F0-05 not started.

## Product and decision continuity

Egypt-first multi-tenant e-commerce SaaS for SMB merchants with independent stores, Online/Manual/Direct Sales, location-separated inventory and one-location full-order fulfillment in the MVP; **not** a full ERP/POS. Last historical approved decision D-200 A; **D-201 OPEN** (customer response marker for alternative stock location) to revisit during Orders/Fulfillment, not during F0-01.

## Permanent instruction for future sessions

Always refresh this pointer through the approved reviewed workflow when work advances. Keep dated snapshots, approvals, task status, actual Git SHA/PR/CI evidence, limitations and next exact action. Never infer approval or bypass actual review gates. Historical handoffs remain for traceability.
