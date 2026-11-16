# E-commerce SaaS V2 — Open Decisions and MVP Gates

**Snapshot:** 16 November 2026. **Latest approved:** D-200 (A). **Next open:** D-201.  
**Status:** Business Discovery handoff — open matters are **not approved**.

## D-201 — Alternative-location proposal awaiting the customer's response (OPEN)

An authorized employee proposed another fulfillment location for an open, unconfirmed Manual Pending Stock order. The customer wants time to decide or has not responded.

- **Option A — recommended previously, NOT APPROVED:** Keep the main order status `Pending Stock`, and display a separate **internal follow-up marker: “Alternative location proposed — awaiting customer response”**. Document the proposed location/fulfillment method, communication channel, time and responsible employee. A merchant-defined internal reminder under D-185 may apply. Close the marker when the customer explicitly responds.
- **Option B — NOT APPROVED:** Document the offer and response in the ordinary communication notes only, without a dedicated marker.

Neither option allows automatic customer messaging, a reservation, confirmation, shipment, Location/FIFO changes or financial transactions. Explicit customer acceptance and the checks in D-183, D-194, D-195 and D-196 remain necessary.

**D-201 does not block creating the repository or planning the foundation.** Do not assume A has been selected.

## Remaining work — discuss with the new chat / relevant module

These are **topic-level gaps**, not newly assigned numbered decisions. Do not manufacture approvals or numeric defaults.

| Area | Questions / checks remaining | Gate |
| --- | --- | --- |
| MVP boundary | Approved business rules vs features actually shipping in v1; phased roadmap and out-of-scope matrix | Before feature plan |
| Multi-tenancy & permissions | Tenant isolation model, feature entitlements, staff/location permissions, audit trails | Before foundation implementation |
| Architecture & operations | Exact versions, Laravel API boundaries, PostgreSQL tenancy schema, Next.js integration, queues, storage, CI/CD, backups, hosting, tests | Before architecture sign-off |
| Merchant onboarding & subscriptions | Registration, merchant verification and pilot onboarding, plans, trials, activation/suspension, billing | Before respective modules |
| Checkout & payments | Pending/success/failure transitions, payment hold/expiry, stock changes during asynchronous payment, provider reconciliation | Before payment integration |
| Inventory & fulfillment | Module-specific transfers and location processes; avoid overselling, preserve single-location full-order fulfillment | Before inventory advanced flows |
| Returns, refunds, shipping | Carrier/provider-specific reconciliation, dispute/evidence checks, numeric staff limits and merchant settings | Before relevant integrations |
| Reports | Date attribution, exports, operational vs legally closed periods | Before reports |
| Egypt regulatory compliance | Applicability and actual implementation of e-invoicing/e-receipt and required documents for the chosen merchant segments | **Mandatory before affected pilot go-live** |
| D-201 | Internal alternative-branch proposal follow-up marker vs ordinary notes | During Orders & Fulfillment detail planning |

## Deferred (not automatically part of MVP)

Full ERP, full POS, split fulfillment, intelligent routing, advanced service form builder, professional promotions, multiple coupon codes, guest coupon redemption, advanced per-market/variant tax exceptions, and the Free Shipping clawback on partial returns (explicitly deferred in D-141 B). Details live in the complete handoff.

## Source and decision authority

1. [Canonical decision register: D-152…D-200](ecommerce_saas_decision_register_D152_onward_2026-11-15.md).
2. [Comprehensive Business Discovery Handoff v5.48](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) — contains older decision history including D-028…D-151.
3. [Original Arabic discovery handoff](../handoff/ecommerce_saas_business_discovery_handoff_ar.md) — historical context, NOT a current-status override.

Earlier proposals labeled OPEN in preserved historical text do not reopen a decision later explicitly approved. An original historical v0.2 source was noted as unavailable when the handoff was prepared; do not invent its unverified wording.

**Next step:** Start MVP Scope v1 / Project Kickoff, then Architecture & Module Planning. Discuss the blockers for each module as that module starts rather than asking endless detailed questions in advance.
