# START HERE — E-commerce SaaS V2 API — next chat

**GitHub:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Organization:** Elmo-Tech  
**Default branch:** `main` (stable). **Development integration branch:** `development`.  
**Stage:** business decisions collected; code scaffolding and technical architecture **not yet approved**.

## Read these in order

1. [Latest canonical D-152 onward decision register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md).
2. [Full Business Discovery Handoff v5.48, approved through D-200](ecommerce_saas_handoff_v5_48_2026-11-16.md).
3. [Open decisions & MVP gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md).
4. [Original historical handoff](ecommerce_saas_business_discovery_handoff_ar.md).

## Decision status at transition

- The newest approved decision is **D-200, choice A**: show alternative fulfillment locations compatible with the customer's current Delivery/Pickup method first, then locations requiring a method change. Inside each group, prioritize lower **reliable and comparable indicative order totals**. Keep unknown costs visible as **“needs review”**, not ranked by imaginary numbers. This internal ordering is **not FIFO** and does not reserve stock or change the order.
- **D-201 remains OPEN**: A) internal “awaiting customer response” indicator for a proposed alternative location (previous recommendation); B) communication notes only. **No choice has been accepted yet**.
- Historical OPEN proposals in the long handoff are not active if a newer approved decision supersedes them.
- Business rules that are APPROVED are **not automatically MVP implementation commitments**.

## Product goals already agreed

Egypt-first pilot for small/medium omnichannel retailers; multi-tenant independent merchant storefronts; Online Orders, Manual Orders, basic Direct Sales; a catalog with variants, discounts, coupons, taxes/shipping, location-separated inventory, and single-location **full-order** fulfillment. **Not** a full ERP/POS; no Split Fulfillment in MVP.

## Technology direction, not an approved architecture

Laravel API + PostgreSQL + Next.js + GitHub Spec Kit were stated as the preferred direction. Precise API/module architecture, tenant isolation, CI, deployment and infrastructure need project kickoff decisions and design review.

## The instruction for the next conversation

> We created `Elmo-Tech/ecommerce_v2_api`, with `main` and `development`, and archived full Business Discovery handoffs. Read all linked repository documentation before responding. Start **Project Kickoff and MVP Scope v1**. Identify **only blocking decisions**, then discuss architecture and an incremental Laravel API implementation plan, modular features, tests, CI and branch workflow. Keep D-201 OPEN and discuss it when we reach Orders & Fulfillment. Do not silently approve any option, assume every business decision is mandatory in MVP, or start application coding without approving the plan.

## Non-negotiable checks

- Tenant isolation and staff permissions, auditability and payment/inventory consistency need rigorous implementation.
- Do not imply that the pilot is compliant with Egypt's statutory e-invoicing or e-receipt obligations without verifying actual merchant eligibility and compliance.
- Preserve the complete older decision history. The handoff notes a missing original historic v0.2 file for some early decision wording; ask for it only if it materially affects a rule.
