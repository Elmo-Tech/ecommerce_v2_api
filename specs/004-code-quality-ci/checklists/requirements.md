# F0-04 — Specification / technical plan review checklist

**Status:** DEC-01…06 A and full Spec/Plan approved. Tasks/Consistency Analysis reviewed, 2/56 planning only. T003 implementation authorization and actual evidence pending.

- [x] Verified F0-03 40/40 closure and current development baseline; actual previous 451/2822 retained as baseline, not a fresh run.
- [x] Recorded all six explicit owner decisions; separated branch policy plan from admin activation.
- [x] Spec has five prioritized independently testable user scenarios, FR-001–013, AC-01–13, measurable SC-01–07 and exclusions.
- [x] Plan maps requirements to tooling, exact candidate versions, safety boundary, contract locality, CI matrix, security, evidence and review.
- [x] Inspected real composer lock, canonical OAS and workflow/guard source; identified obsolete exact dev-list and workflow assertions, require mapped equivalent/stronger tests.
- [x] Published package constraints, exact public action SHA/runtime metadata and Trivy release asset hashes recorded; no installed compatibility claim.
- [x] Constitution I–VIII and CS/TEST/AS/SEC reconciled; no business decision or exception invented.
- [x] Defined required failure/blocked/N/A, no broad suppressions, no auto-upgrades, no production/shared services, no CI/merge/deploy authority.
- [x] Owner review and approval of complete [Spec](../spec.md), [Technical Plan](../plan.md), [Research](../research.md), including proposed pins, freshness, timeout and branch policy conditions.
- [x] Tasks and full documentary requirement-to-task consistency analysis after owner approval (56 unique IDs, 13/13 FR/AC, 7/7 SC, 5/5 stories; C01 for owner task review).
- [ ] Separate explicit implementation authorization.
- [ ] Actual lock solver/install, safe launcher, positive/negative tools, complete audit/scan, action compatibility, docs-only/full CI and retained regression evidence.
- [ ] Technical review, actual human review, separate merge authorization, verified integration/closeout.
- [ ] Separate administrative activation only if approved and feasible; actual protection verification or explicit pending manual state.

No implementation checkbox completed. T001–T002 documentary complete, T003–T056 pending; no change to D-201/BA-16/BA-30 or next Feature.


[Tasks](../tasks.md) and [Consistency Analysis](../consistency-analysis.md). On 2026-11-25 the owner explicitly approved the complete F0-04 Spec and Technical Plan at source `effc624e5b019047782eb940a56e288cb9373c93`, including the six A decisions, proposed versions/action pins, CI matrix, security policy (24-hour DB freshness), safety boundaries, timeouts and stated limitations, and requested tasks.md and Consistency Analysis with review only, without executing project code or installing packages. This is not implementation, tool/scanner execution, workflow dispatch, branch administration, merge or deployment authorization.
