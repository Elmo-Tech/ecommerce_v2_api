# F0-03 — Requirements review checklist (owner-approved Spec/Plan; implementation gate pending)

- [x] Reopened current development, verified F0-02 30/30 and prior actual CI.
- [x] Reopened TEST-01…10, DB/Architecture/Constitution/Workflow and actual test bootstrap/config/CI source.
- [x] Distinguish current fast tests from unverified real database/Redis integration.
- [x] Drafted independently testable fast isolation, real-service, scoped resource and evidence scenarios.
- [x] Defined fail-closed boundaries and explicit non-goals; no invented business schemas/decisions or production readiness.
- [x] Owner approved DEC-01 A, DEC-02 A, DEC-03 A, DEC-04 A on 2026-11-25.
- [x] Researched supported releases, exact official linux/amd64 image manifests, PHP native driver published requirements, test CLI and service/security docs; specified concrete safety/role/ACL/report design. Actual pulls, extensions and compatibility remain unrun execution gates.
- [x] Owner approved complete final spec.md and plan.md at `722dd440f36269c2e6ac6d052801b35d6cac876e` on 2026-11-25, including versions and technical design.
- [x] Generated [tasks.md](../tasks.md) and [consistency analysis](../consistency-analysis.md); documentary coverage/dependency validation complete.
- [x] T003 separate explicit implementation authorization recorded at approved task revision `74f45e00e289568aca85e071317100f04b20c8be`.
- [ ] Actual baseline, negative/positive regression/real service CI and scope review after authorization.
- [ ] ChatGPT technical review, owner human review/qualified independent reviewer as applicable, separate merge authorization and verified closeout.

**Status:** 14/40 complete (including authored RED tests T006–T014); actual full safe RED observed; T015 final lint and actual recheck pending. Native service tests authored, NOT RUN.


- [x] T004 actual integrated baseline verified (CI #45591182691, 98/911, 31/134, 5/52).
- [x] T005 exact image and native driver/CLI compatibility preflight (actual CI #45591605943).
