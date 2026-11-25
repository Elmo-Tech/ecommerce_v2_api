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

**Status:** 24/40 complete; T021–T024 scoped source tests verified; T025 final reviewed workflow staged, actual fast/native run pending. No real proof or merge approval.


- [x] T004 actual integrated baseline verified (CI #45591182691, 98/911, 31/134, 5/52).
- [x] T005 exact image and native driver/CLI compatibility preflight (actual CI #45591605943).


- [x] T006–T014 approved risk-selected test sources authored.
- [x] T015 actual safe RED verified (CI #45593286201, 205 expected failures /101 passed /1153 assertions); all tracked PHP lint including native Integration passed. Native tests not run.
- [ ] T016–T025 minimal test-only implementation.
- [ ] T026–T033 full actual fast and Integration GREEN, owned resources, reports and scope checks.


- [x] T016/T017 fast guard/launcher targeted verification (CI #45597723864 and #45598005372).
- [x] T018 pure owned record validation and fixed slots (40/40 and slot-resolution test, CI #45598005372).
- [x] T019 guarded explicit integration entry and native driver policy (scoped CI #45600204166; actual native service verification pending).
- [x] T020 exact owned cleanup adapter and pure/negative/record-persistence verification (33/33, CI #45600204166; actual deletion/teardown T030 NOT RUN).


- [ ] T021–T025 remaining owned startup/setup/report/workflow implementation.
- [ ] T027–T030 actual service and cleanup verification; not satisfied by T019/T020 safe policy tests.
