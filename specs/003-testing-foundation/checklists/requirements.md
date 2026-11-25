# F0-03 — Requirements review checklist (final review candidate)

- [x] Reopened current development, verified F0-02 30/30 and prior actual CI.
- [x] Reopened TEST-01…10, DB/Architecture/Constitution/Workflow and actual test bootstrap/config/CI source.
- [x] Distinguish current fast tests from unverified real database/Redis integration.
- [x] Drafted independently testable fast isolation, real-service, scoped resource and evidence scenarios.
- [x] Defined fail-closed boundaries and explicit non-goals; no invented business schemas/decisions or production readiness.
- [x] Owner approved DEC-01 A, DEC-02 A, DEC-03 A, DEC-04 A on 2026-11-25.
- [x] Researched supported releases, exact official linux/amd64 image manifests, PHP native driver published requirements, test CLI and service/security docs; specified concrete safety/role/ACL/report design. Actual pulls, extensions and compatibility remain unrun execution gates.
- [ ] Owner approves complete revised spec.md and plan.md.
- [ ] Generate tasks.md and consistency analysis, then obtain separate implementation approval.
- [ ] Actual baseline, negative/positive regression/real service CI and scope review after authorization.
- [ ] ChatGPT technical review, owner human review/qualified independent reviewer as applicable, separate merge authorization and verified closeout.

**Status:** final specification/plan review candidate; four decisions approved, full owner sign-off pending. Planning only, no executable changes or new test results. Do not mark future tasks complete.
