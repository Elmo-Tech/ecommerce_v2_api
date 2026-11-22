# E-commerce SaaS V2 — Commerce Workflow Architecture

**Version:** 1.0 — Approved Architecture Baseline  
**Date:** 2026-11-19  
**Repository:** Elmo-Tech/ecommerce_v2_api  
**Branch:** development  
**Document:** docs/01-architecture/commerce-workflow-architecture.md  
**Approval:** **CW-01 through CW-08 — Option A APPROVED; CW-09 — Option A, final review and single-file publication APPROVED**  
**Implementation:** **NOT IMPLEMENTED / NOT TESTED / NOT DEPLOYED**

> **Architecture-only baseline.** The approval recorded here does not assert the existence of Laravel modules, database tables, API endpoints, payment integrations, production infrastructure, operational recovery evidence or passing acceptance tests. The current infrastructure is Development-only; Production has not been provisioned. **BA-16 Backup & Restore is deferred and BA-30 G3 remains BLOCKED for a live Pilot.** Actual implementation and Pilot enablement require separate authorization and validated evidence.

---

## 1. Purpose, Authority and Scope

### 1.1 Purpose

Define how the approved **Laravel 13 API-first Modular Monolith**, **Next.js**, and **PostgreSQL Central + isolated Tenant databases** coordinate the core commerce journeys without losing module ownership or violating money and stock invariants:

- Online Checkout and COD order confirmation.
- Manual Orders, Manual Pending Stock and employee-controlled confirmation.
- Simplified Direct Sales with full verified collection and actual immediate handover.
- Preparing, Ready, Delivery Dispatch, Store Pickup and operational completion.
- Actual Collections, COD remittance/settlement evidence and differences.
- Returns, Exchanges, inspection, Restock, authorization and successful Refund evidence.
- Cross-Module failure handling, reconciliation, idempotency and auditable corrections.

This document defines **cross-module orchestration and consistency boundaries**, not a final database schema, full lifecycle state dictionary, feature specification, payment/courier integration or independent workflow-engine product.

### 1.2 Authority and precedence

Architecture decisions **CW-01 through CW-09** supplement, and do not amend, previously approved business and technology directions:

| Existing source | Binding direction |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | OV product scope, three channels, one eligible Location, COD-first Pilot, excluded integrations, D-201 open and release gates |
| [Business Discovery Handoff v5.48](../handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md) | Approved D-028…D-151 details, coupon holds, financial approvals and historical business context |
| [Decision Register D-152 onward](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) | Detailed Orders, Fulfillment, Direct Sale and Manual Pending Stock behavior D-152…D-200 |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 remains open; unresolved Feature/Pilot details are not deemed approved |
| [Backend Architecture](./backend-architecture.md) | BA-07 B synchronous public contracts for critical work; BA-09 B Outbox/Horizon background work; domain ownership |
| [Module Architecture](./module-architecture.md) | One Writer Principle, logical modules, module public contracts and Central/Tenant scopes |
| [Transaction Consistency Architecture](./transaction-consistency-architecture.md) | TC-01…TC-08 Tenant-local ACID, locking, business idempotency, durable Outbox, reconciliation and tests |
| [Tenancy Architecture](./tenancy-architecture.md) | Trusted context, separate PostgreSQL Tenant databases and fail-closed access |
| [Identity & Access Architecture](./identity-access-architecture.md) | Central workforce identity/membership plus Tenant-local role, Location, approval and resource authorization |
| [API Architecture](./api-architecture.md) | API-side validation, idempotency, versioned contracts, tenant-safe response handling |
| [Queue/Jobs/Event Processing Standards](./queue-jobs-event-processing-standards.md) | Tenant-aware Horizon execution, durable event relay, at-least-once delivery, safe retry and cleanup |
| [Integration & Notifications Architecture](./integration-architecture.md) | INT-01…INT-08, approved transactional email/in-app follow-ups, not authoritative financial effects |
| [Infrastructure & Deployment Architecture](./infrastructure-architecture.md) | Development environment, runtime-only secrets, measured release checks, BA-30 gates |

**Precedence:** detailed approved D-series business rules govern what the application may do; CW/TC/Module/Backend govern safe cross-module execution. A proposal marked OPEN in a historical handoff is not re-opened when a later explicit decision has approved it. The exact wording of an unavailable historical source must not be invented.

### 1.3 MVP boundaries

**Included:** approved Online Orders, staff-entered Manual Orders, limited Direct Sales, single-Location physical fulfillment, operational COD/verified collection records, bounded inventory operations, Returns/Refunds and essential notification/reporting side effects.

**Excluded/deferred:** payment gateway integration in first Pilot (OV-04), carrier/shipping API (OV-05), full offline checkout, split tender for Direct Sale, Split Fulfillment, automated cross-location stock allocation, fully autonomous refunds, Full POS/ERP/general ledger, advanced marketing, WhatsApp/SMS APIs, and a cross-module Saga/workflow engine.

A future-facing interface, payment status or queued event does **not** activate a deferred integration.

---

## 2. Final Approved Decision Register — CW-01…CW-09

| ID | Approved selection | Architectural implication |
| --- | --- | --- |
| **CW-01** | **A — Use-Case-Owned Application Orchestrators + Module Public Contracts + Tenant-Local ACID** | Owning use case coordinates critical contracts; each Module writes its own data; no central general Commerce Engine. |
| **CW-02** | **A — State-Aware Online Checkout + Atomic Reservation at Actual Confirmation** | Backend validates prices/eligibility; COD automatic/manual confirmation differ; stock reserve and Confirmed must succeed together. |
| **CW-03** | **A — Explicit Manual Pending Stock + Staff-Controlled Confirmation + Atomic Reservation** | Unconfirmed/unreserved manual queue, staff review and single-Location revalidation; FIFO is follow-up, not priority reservation. |
| **CW-04** | **A — Synchronous Direct Sale Orchestration + Tenant-Local Atomic Effects + External Reality Recovery** | Full verified payment + actual immediate handover; once-only stock and payment records; external physical/payment effects require reconciliation. |
| **CW-05** | **A — Fulfillment-Owned Orchestration + Atomic Stock Consumption + Manual Handover Verification** | Preparing/Ready/Hold separately tracked; once-only stock consumption at actual Dispatch/Pickup, not later delivery. |
| **CW-06** | **A — Payments-Owned Financial Records + Verified Collections + Explicit COD Settlement** | Orders/Delivery and collection/remittance remain separate; no invented Paid/Settled on operational completion. |
| **CW-07** | **A — Return-Owned Orchestration + Independent Inventory/Refund States + Idempotent Effects** | Return approval, physical receipt, eligibility for resale, refund authorization and real payment execution are distinct. |
| **CW-08** | **A — Module-Owned Recovery + Durable Reconciliation + Business Idempotency + Auditable Corrections** | Classify failure; retry only safe work; reconcile unknown external effects; preserve original records and explicit correction links. |
| **CW-09** | **A — One consolidated Commerce Workflow Architecture file** | User approved final review and upload on development; remaining Feature details and Pilot gates remain explicitly open. |

**No additional option is implied** by technical examples. The eight approved workflow choices are compatible with the existing BA-07 and TC-01…TC-08 decisions.

---

## 3. Ownership, Synchronous Contracts and Transaction Scope — CW-01

### 3.1 Module responsibility matrix

| Module | Sole authoritative ownership | Role in cross-module workflow |
| --- | --- | --- |
| **Orders** | Online/Manual order record, commercial lifecycle, pending manual review, confirmed snapshots | Coordinates customer orders and confirmation; consumes Pricing and Inventory public contracts |
| **Catalog** | Product/Variant/Basic Service definition, eligibility and publication status | Supplies approved item eligibility; does not reserve stock or record collection |
| **Pricing & Promotions** | Price, promotion, coupon hold/redemption and calculation policies according to feature design | Validates and returns authoritative totals, taxes, entitlement rules and promotion outcomes |
| **Inventory & Locations** | On-hand, Reserved, Available, physical movements, adjustment evidence, Location eligibility | Provides atomic reservation/consumption/eligible Restock and protected availability checks |
| **Direct Sales** | Immediate sale operation, sale state and source reference | Coordinates current in-person sale with Pricing, Inventory and Collections |
| **Fulfillment** | Preparing/Ready/On Hold, dispatch, delivery and pickup evidence | Coordinates physical handover and calls Inventory/Orders public contracts |
| **Payments & Collections** | Actual financial collections, verification, authorized refunds where applicable, COD settlement evidence | Owns financial truth and reference-level duplicate prevention |
| **Returns & Refunds** | Return/exchange case, approval coordination, linked refund workflow and status | Coordinates Inventory inspection/restock and authorized Payments effect |
| **Notifications** | System-approved customer email intentions and authorized staff alerts | Receives committed events; no business-order/financial authority |
| **Reporting & Exports** | Authorized read models, business metric presentation and exports | Consumes safe committed state; never decides whether stock/money facts are true |
| **Audit & Activity** | Cross-cutting trace (Central vs Tenant separately) | Records sensitive state changes, actor, outcome, reason and correction links |

The specific class/package breakdown and detailed interface signatures are **Feature Spec decisions**, not a mandate for a single hard-coded orchestration framework.

### 3.2 Logical flow

~~~mermaid
flowchart TD
    U["Authorized storefront / staff request"]
    A["Owning Module — Application Use Case"]
    P["Catalog / Pricing public contract"]
    I["Inventory public contract"]
    C["Collections public contract"]
    T["One validated Tenant PostgreSQL transaction"]
    D["Module-owned facts + unique effects"]
    O["Same-DB Transactional Outbox"]
    R["Post-commit relay to Redis / Horizon"]
    N["Notifications / Reports / Recoverable side effects"]
    U --> A
    A --> P
    A --> T
    T --> I
    T --> C
    T --> D
    T --> O
    O --> R --> N
~~~

This is a **logical** ownership/commit diagram, not a requirement to acquire every displayed dependency in each workflow. Read-only price previews can run before a transaction, but business predicates affecting a dependent write must be **rechecked/protected at finalization**.

### 3.3 Transaction design

A critical use case executes with an explicit validated **Tenant PostgreSQL connection**. Where multiple module-owned records need one atomic local outcome, the owning orchestrator coordinates calls to the corresponding public contracts inside a **single appropriate local DB transaction**. Each contract guards/writes its own data; an orchestrator does not bypass Module boundaries by writing foreign tables.

Use the **least complex correct** PostgreSQL guard: row-level locking, conditional atomic updates, a unique/check/foreign-key constraint or optimistic version check. Multi-line stock must be checked for **all relevant items from one eligible Location** and locked/updated with consistent ordering and short transactions.

**Do not** hold a DB transaction across cashier interaction, physical handing over, external payment terminals, email/network requests or long queue jobs. An external/physical effect has no automatic database rollback; record trustworthy evidence and reconcile it explicitly if finalization fails.

### 3.4 Central vs Tenant

All ordinary commerce records belong to the **owning Tenant DB**. Identity, membership and entitlement resolution may consult Central before an authorized operation, but do not assume a cross-DB foreign key or one atomic Central + Tenant transaction. If a genuinely coupled cross-context process is required, follow **TC-05** durable guarded steps and reconciliation; do not introduce a generic distributed Saga engine.

A failed Tenant resolution, unavailable Tenant DB or forbidden Location fails closed, never using the previous or default Tenant connection.

---

## 4. Non-Negotiable Cross-Workflow Invariants

| Invariant | Required system behavior |
| --- | --- |
| **Tenant isolation** | Merchant A cannot view/write Merchant B orders, stock, customer profiles, payment evidence or audit |
| **Feature entitlement and authorization** | Entitlement, Tenant membership, local permission, Location and resource-specific restrictions are independent |
| **One eligible fulfillment Location** | A physical order is fulfilled entirely by one eligible Location; no Split Fulfillment or sum-of-fragments promise |
| **No Overselling** | Last-unit races resolved authoritatively in PostgreSQL; Reserved stock protected from unrelated operations |
| **No implicit confirmation** | Pending manual review, coupon hold, alternative Location suggestion, FIFO priority and alerts do not authorize confirmation |
| **Truthful state** | Order status, fulfillment progress, physical stock, actual collection, COD remittance and Refund execution remain distinct |
| **Once-only physical effect** | Confirming Dispatch/Pickup twice cannot consume stock again |
| **Once-only financial execution** | Repeated HTTP keys or worker events cannot duplicate the same collection or refund; legitimate distinct partial events remain possible |
| **Historic commercial terms** | Approved original prices/taxes/discounts and customer agreement remain reconstructible; no silent rewrite after confirmation |
| **Money units** | Finalized amounts use integer minor currency units with appropriate currency exponent; no universal ×100 assumption |
| **Auditability** | Keep authorization, actor, reason, before/after where material, evidence and original/correction linkage |
| **Post-commit only** | Notifications/report projections cannot invent or retroactively determine critical order, stock or money facts |
| **Legal customer rights** | Mandatory documents, applicable Egypt Pilot compliance and law override convenience timing/defaults |

No global Eventual Consistency rule overrides an immediate order/stock/collection invariant.

---

### FBR-02-16 — Store Tax Input Mode Cannot Change After Confirmed Sales

The approved D-089/D-090 **single Tenant Tax-Inclusive or Tax-Exclusive merchandise price-input mode** may be changed during setup with a review of affected Product/Variant entered prices. After the first **confirmed sale**, normal Merchant Settings edits must lock this input mode. A rare separately authorized, audited correction requires explicit price-impact review, controlled conversion and outstanding Draft/Manual Pending Stock re-evaluation/consent; never silently rewrite historical Confirmed Orders/invoices. This input-mode lock does **not** prohibit D-094 rate changes or override D-097 gross-price preservation when a Tax-Inclusive rate changes. Exact UI/default selection, legally valid profiles and workflow details remain Merchant Settings/Pricing/Tax Feature responsibilities.

## 5. Online Checkout and Confirmation — CW-02

### 5.1 Backend-authoritative preflight

The Storefront constructs an order request using eligible Catalog Products/Variants and supported Basic Services; the server verifies current Tenant feature entitlement, publication eligibility, selected shipping/pickup service, suitable Location, quantities, taxes, promotion eligibility, shipping fee and actual business agreement.

The **client-submitted total is not authoritative**. Revalidation must consider changes in stock, customer selections, discounts, shipping eligibility and any previously shown price. Changes that materially affect the agreed amount require explicit customer acceptance in the cases established by **D-108** and related rules; do not silently choose an invalid alternative service or more expensive checkout.

**Basic Service** has no user-facing quantity under D-029 and must not be treated as physical inventory. Mixed physical/service cart lifecycle, internal quantity representation and service fulfillment are **Feature Details pending approval**; do not silently impose normal-stock or shipping rules on it.

### 5.2 Automatic vs Manual COD confirmation

| Stage | Automatic confirmation configured | Merchant manual confirmation configured |
| --- | --- | --- |
| Cart | Calculation and preview only; no confirmed reservation | Same |
| Order placement | Request creates/validates real order intent and attempts confirmation under current rules | Order placed for real employee confirmation; **not a confirmed stock reservation** |
| Actual confirmation | Current eligible stock + prices/coupon rules protected; Confirmed and full reservation commit together | Employee action re-checks live eligibility, full-order Location availability, financial/customer rules, then commits Confirmed + full reservation |
| Insufficient stock | Do **not** commit Confirmed or a partial reservation | Do **not** confirm or auto-convert to **Manual Pending Stock** |
| External mailing | Post-commit approved event only; failed email cannot affect order truth | Same |

The exact statuses and response behavior for failed automatic placement, customer cancellation or ineligible manually pending Online Order are **Orders Feature Specs**. CW-02 does not invent an Online Pending Stock backorder feature or a new rejection/cancellation policy.

### 5.3 Protect reservation and confirmation together

The selected Orders use case coordinates Pricing and Inventory public contracts inside the **same Tenant transaction**, protecting all required stock rows/aggregate predicates, the confirmed order transition, the authorized price/discount/financial snapshot and the corresponding Outbox intent.

**Atomic property:** if Inventory cannot reserve the **whole physical order at one eligible Location**, the Confirmed state and the reservation must **both fail**. No partial confirmation or combining available stock from two Locations.

Reserving stock when merely adding to cart is **not approved**. In particular, pending manual confirmation for Online COD does not grant a confirmed stock reservation.

### 5.4 Coupon capacity is not stock

Existing approved coupon decisions govern a **separate** resource:

- **D-050/D-051:** registered customers only for coupon redemption in MVP; one successful redemption per registered customer per coupon, with optional Tenant-scoped overall coupon cap.
- **D-052:** a coupon redemption is **finalized at Order Confirmation**, including COD; do not wait for cash settlement.
- **D-054:** hold only the **actually applied eligible coupon** at **order placement**, not merely when entered in cart; reserve coupon-use capacity, **not physical stock**.
- **D-055/D-058/D-059:** store-selectable hold presets are **30 minutes, 2 hours, 12 hours or 24 hours**, maximum 24 hours; new store default is **2 hours**.
- **D-060:** changed preset affects **future placed orders only**; existing holds keep their original expiry.
- **D-056/D-057:** expired hold with now-valid coupon/capacity is revalidated at manual confirmation; unavailable coupon requires merchant review, not silent repricing or cancellation.
- **D-053:** pre-Preparing cancellation of a Confirmed order restores an eligible finalized redemption once; no automatic post-Preparing restoration is inferred.
- **D-061/D-064:** only one coupon code per order, and an unused losing coupon is not temporarily held or redeemed.

Coupon hold and stock reservation have **different lifecycles, identities, uniqueness and rules**. The Feature Plan must test concurrent use of the last permitted coupon redemption as well as the last available stock unit; neither constraint may be skipped during atomic confirmation.

### 5.5 Online promotion and Pilot payment boundary

Under D-151, **eligible automatic Online promotions** apply according to their approved rules. Manual Orders/Direct Sales do not silently adopt identical automatic application behavior. Detailed stacking and calculation ordering must follow the complete approved D-series decisions, not a new generic “best discount” shortcut.

The first Pilot is **COD-first, no integrated Payment Gateway** (OV-04). A payment state model does not authorize online card capture or unverified external success. Payment authorization/expiry from a future provider is not a current MVP workflow.

---

## 6. Manual Orders and Pending Stock — CW-03

### 6.1 Manual order creation

Authorized staff creates a Tenant-local order with a recorded source channel (phone, WhatsApp/social message handled by staff, etc.), correct Location/actor context, eligible Catalog items and approved pricing/shipping/tax/discount terms.

Under **D-180/D-181**, Manual Orders are **Catalog-only** with no arbitrary custom item or unit-price override. A price difference is handled by a valid approved discount/consent route, not silently rewriting the Catalog price. Under **D-151**, eligible promotions in the Manual channel are applied only upon explicit authorized selection.

### 6.2 Explicit unconfirmed Pending Stock

If the full Manual Order cannot be validly confirmed from one eligible Location, it **may remain Manual Pending Stock**:

- Commercial status remains unconfirmed.
- No stock reservation is created.
- No automatic Backorder/remainder order is manufactured.
- No entitlement to stock arises from age, reminder or candidate Location.
- Existing customer payments/agreements, if any, remain truthfully documented and separately governed.
- No automatic customer communication, refund or cancellation arises solely from the pending state.

**D-183:** even if stock later appears, authorized staff must revalidate all current items, complete Location stock, applicable prices, coupons/discounts, tax, shipping, payments and customer agreement, then perform **explicit manual confirmation**. The actual reservation and order state commit atomically.

### 6.3 FIFO, timing and edits

**D-184:** FIFO organizes **review/follow-up** for competing eligible Pending Stock orders; it never automatically allocates units or confirms orders. Authorized exceptions require traceable reasons.

The complete D-185…D-200 rules remain binding, notably:

- D-185: Merchant-defined follow-up interval leads to an internal reminder and authorized review, not automatic cancellation/refund.
- D-186: after lawful closure, a returning customer requires **new linked Manual Order**, not silent Reopen of the closed original.
- D-187…D-190/D-192: permitted reductions, replacements, additions and increases require staff authority, customer agreement, repricing checks and audit; no automatic reservation.
- D-191: effective FIFO date for a multi-line order with substantive addition/replacement follows the latest material event; preserve creation/item history.
- D-193: a reduction does not restore an earlier FIFO priority after a previously material change.
- D-194…D-200: alternative eligible full-order Location proposals and fulfillment-method changes are **staff-visible and customer-consent controlled**, never automatic reallocation/shipment or fake full-stock availability.

### 6.4 Alternative Location proposals and OPEN D-201

A candidate must genuinely be eligible for the **entire** order and actual chosen delivery/pickup method. A suggestion is not a committed transfer, Location change, Reservation, Confirmed state, price adjustment, communication or customer agreement.

**D-201 remains OPEN:** whether a customer's pending answer to a proposed alternative Location requires a distinct internal follow-up marker versus ordinary communication notes. **This document chooses neither option.** Documenting this architecture does not resolve D-201 or give an implicit automatic timer/status.

### 6.5 Manual confirmation transaction

The Orders-owned confirm use case checks Tenant authorization, source order version, current approved item/financial terms, agreed Location and complete stock under PostgreSQL concurrency controls. Through Inventory and any relevant Pricing public contracts it records a single authorized order confirmation/reservation/eligible coupon outcome, plus Outbox event, on the **correct Tenant connection**.

Failed validation, insufficient full-order stock, expired incompatible coupon or missing required customer consent **must not** commit a successful reservation/confirmation.

---

### FBR-02-15 — Exact Discount Authority on Eligible Merchandise

Manual discount approval thresholds use exact monetary comparison, **before any display rounding**. A positive discount against zero eligible remaining Merchandise Base is invalid, while zero discount is a no-op. Preserve D-075…D-088 including tax/shipping exclusion, stacking, self-approval restrictions and reapproval after material financial change; do not treat display percentage or a manually overridden product unit price as authorization.

## 7. Simplified Direct Sales — CW-04

### 7.1 In-person business requirements

Per **D-167…D-174 and D-179**:

- Sale occurs **online-connected**, not through offline platform checkout.
- Products/Variants/Basic Services must be eligible Catalog items; no free-text Custom Sale.
- Full verified payable amount uses **one allowed payment method**, with **no split tender or credit/instalments**.
- Actual immediate handover of the **entire agreed sale** is mandatory.
- Guest/walk-in customer identity is optional for normal transactions; preserve mandatory lawful sale records, actual tax/document obligations and customer rights.
- External card/wallet terminal collection without platform integration is permitted only after **authorized human verification** of full settlement and safe evidence (D-170); Pending/Unknown is not Paid.
- Sales at the physical Location may only consume its real Available units, without stealing another Confirmed Order's reservations.

### 7.2 Owning use case

The **Direct Sales** application use case coordinates Catalog, Pricing, Inventory and Collections public contracts. It verifies the payable total, physical availability, approved offer selection (D-151), chosen single tender and actual payment/handover outcome.

Once the required physical and financial facts are verified, a **short Tenant-local transaction** protects:

- Correct Direct Sale final state and immutable historical commercial/Location snapshot.
- The actual verified collection's unique real-world identity and approved evidence reference.
- One physical Inventory movement reducing On-hand of the selling Location **once**, without a separate long-lived order reservation.
- Audit data and Outbox intent for downstream notification/reporting.

The DB transaction **cannot atomically create external money or physically hand over goods**. Never hold a transaction open while waiting for a terminal/customer. If the DB commit fails after outside effects occurred, preserve the attempt/evidence and reconcile without repeating an actual charge or stock movement. The precise draft/attempt lifecycle and crash-window recovery belong to the Direct Sales and Payments Feature Specs.

### 7.3 Exceptional results

| Condition | Approved boundary |
| --- | --- |
| Payment fails or full amount cannot be verified | Not Completed; do not finalize the sale or consume stock as a completed sale |
| External payment outcome Pending/Unknown | Verify provider/terminal evidence; never re-charge blindly |
| External collection succeeded, full immediate handover impossible | **D-171:** sale not Completed; allowed same-session full handover if legitimately resolved, otherwise authorized verified Void/Refund follow-up |
| Customer wishes to pay via two methods | **D-168:** not supported as one Direct Sale in MVP; no fabricated partial paid completion |
| Customer is a Guest | **D-169:** do not manufacture a Customer Profile; retain authentic sale and lawful documents |
| Network unavailable | **D-174:** no offline platform completion/stock movement |
| Genuine outside-platform sale happened during outage | **D-175:** later, separately authorized External Sale — Late Recorded reconciliation; not automatic sync |
| Completed sale returned later | **D-172:** separate linked Return/Refund, not Void of original Completed Direct Sale |

### 7.4 Late-recorded genuine external sales

D-175…D-178 require original actual-sale timestamp, distinct entry/approval timestamp, supporting evidence, authorization, correct cross-checked stock/financial effect and no duplicate collection/revenue/movement. Under **D-176**, operational reports attribute the sale to the **actual business-sale date**, visibly marked **Late Entry**; they must not silently backdate legally closed documents or invent cash received at entry.

An erroneous approved record is corrected via a **linked Correction/Reversal** of only the false effect (D-177), with special permission, evidence and audit (D-178). No blanket Maker–Checker is required for every ordinary correction; ambiguous or closed-period cases must escalate. A correction does not fabricate a physical Return, money Refund or additional sale.

---

## 8. Fulfillment, Dispatch and Store Pickup — CW-05

### 8.1 Independent order and fulfillment lifecycles

Orders retains commercial lifecycle; **Fulfillment** owns preparation/handover progress. **Payments** owns actual collection facts. These must not be collapsed into a single overly broad order-status enum.

~~~text
Confirmed (Order; reserved stock)
  -> Preparing (pick / pack)
  -> Explicit Manual Pack Verification (D-158)
  -> Ready / Ready for Pickup
     -> Delivery: actual carrier handover + staff Confirm Dispatch (D-159)
          -> verified evidence + staff Confirm Delivery (D-160)
          -> operational Completed (D-161)
     -> Store Pickup: verified recipient + actual handover + staff Mark Picked Up (D-162)
          -> operational Completed
~~~

Exact database status/transition enum names require the Fulfillment Feature Specs; this is the approved logical process. There is no automatic carrier integration in MVP.

### 8.2 Preparing and Hold

- **D-152:** authorized line/quantity edits are allowed through Preparing/Ready **before actual Dispatch**, with full Location stock, finance, tax, shipping and customer-consent review; never silently rewrite confirmed history.
- **D-153:** discovered physical shortage/damage sets **Fulfillment On Hold** for whole execution while commercial Order remains **Confirmed**.
- **D-154:** unaffected genuinely existing correct reservations remain reserved for that order. Do not silently release them, pretend missing goods exist or treat them as sellable.
- **D-155:** only authorized staff may **Release Hold** after the cause and entire order are reverified. Stock arrival does not auto-release Hold or Dispatch.
- **D-156:** Merchant-defined follow-up reminder is internal and does not cancel the order, release reservation or issue a refund.
- **D-157:** staff-driven customer communication when significant delay/change warrants it, recorded with actual response/consent; no automatic email merely because Hold began.
- **D-158:** packing check explicitly confirmed by authorized staff before Ready; no mandatory piece-by-piece scanner requirement.

### 8.3 Dispatch, delivery and stock consumption

For **Delivery**, actual handover to a carrier or authorized handover point and explicit staff **Confirm Dispatch** are required (D-159). Within the protected Tenant-local state transition, Fulfillment coordinates Inventory's **once-only** stock consumption:

- Decrease **On-hand** at the chosen Location once.
- Consume/decrease the corresponding **Reserved** once.
- Record carrier/service/reference if available, actor, time and evidence.
- Do not consume stock on label printing, Ready, future pickup schedule or a tracking-number creation alone.

Later **Confirm Delivery** requires authorized staff evaluation of genuine delivery evidence (D-160); no automatic carrier callback confirmation. It causes an operational **Completed** order (D-161) through the Orders public contract. **Do not subtract stock again** and **do not record COD as Paid/Settled** merely because delivery was confirmed.

### 8.4 Store Pickup

For **Store Pickup**, the customer chooses an eligible branch with complete order availability. Ready for Pickup is not a physical handover. Staff verifies reasonable identity/authorization of the actual recipient, confirms genuine handover using **Mark as Picked Up** and records proof/actor/Location under **D-162**.

That specific action consumes **On-hand and Reserved once** and results in operational completion, with no fictional carrier Dispatch. No blanket mandatory QR/OTP or specialized scanner is selected.

**D-163:** an exceeded Merchant-defined pickup deadline creates **internal review only**. It does not automatically cancel, release Reserved or refund the customer.

### 8.5 Delivery/Pickup/Location changes

**D-164…D-166** allow certain authorized pre-handover conversions **on the same Confirmed Order** with legitimate customer agreement and financial review:

- Pickup -> Delivery: validate real delivery service/address, full Location fulfillment, repack/reverify as needed; no fictitious pickup or dispatch.
- Delivery -> Pickup: only before actual carrier handover; if an external shipping label has already become valid, obey the **D-110** cancellation/verification path and escalate if cancellation cannot be proved.
- Pickup branch change: obtain explicit customer agreement; establish a safe full-order reservation at the new eligible Location **before releasing prior coverage**. An old branch's Ready does not transfer automatically.
- Preserve original order history and financial/customer agreements; location changes are **not stock transfers** and must not generate unapproved automatic transfer or split fulfillment.

Where a proposed reassignment cannot atomically protect the alternative Location's reservation, do not complete it or leave the Confirmed Order uncovered.

### 8.6 Failed delivery, RTO and inventory

A failed carrier delivery may require further attempts or Return to Origin. Do not consume the same stock twice. Goods physically in transit are **not automatically Available** at the store. Only trustworthy receipt/inspection and approved Inventory operations can restore eligible sellable units. Carrier failure does not automatically establish a customer refund or lawful shipping-charge outcome.

---

## 9. Payments, Collections and COD Settlement — CW-06

### 9.1 Independent financial facts

**Payments & Collections** owns the authoritative operational record of verified collections and remittance/settlement evidence; it is **not a full general ledger**. Orders owns the agreed sale/amount, and Fulfillment owns logistics evidence.

Never infer payment success or merchant remittance from **Confirmed, Dispatched, Delivered or Completed** order statuses. A delivered COD order can be operationally completed while the amount is unverified, still held by a carrier or not yet reconciled with the Merchant.

### 9.2 Conceptual COD evidence chain

~~~text
Order total / customer amount due
   -> Actual customer collection (when evidenced)
   -> Courier-held amount / amount pending remittance (if applicable)
   -> Verified merchant remittance or reconciliation
~~~

This is a **conceptual evidence chain**, not a mandatory one-way database status enum. Collection and remittance evidence may arrive in a different order and must be reconciled rather than assumed. Direct merchant collection at pickup differs from carrier-held COD and follows its actual verified payment method.

Example: an order totals **EGP 1,000**. “Delivered” establishes fulfillment. If reliable evidence shows a courier collected EGP 1,000, that records **customer collection**; it does not yet prove that the courier has remitted EGP 1,000 to the Merchant. Courier fees, deductions, cash discrepancies, transfers and settlement terms require a separate approved Payments/COD Feature Spec. Do not silently infer net settlement.

### 9.3 Collection uniqueness and partial events

- Preserve a trustworthy financial execution/source reference with actual amount, method, actor, timestamp and minimal evidence.
- Enforce once-only effect per **real-world collection event**. Neither an Order ID nor an HTTP Request ID alone is sufficient to deduplicate all financial events.
- Permit distinct legitimate partial collections where the applicable **specific business flow** allows them; guard authorized totals, state and evidence.
- Do not extend partial/credit/split-tender to **Direct Sale**, which expressly requires one method and full payment at immediate handover.
- A Pending/Unknown external terminal result cannot become a successful collection by assumption. Check the terminal/provider record before potentially repeating an actual debit.
- Corrections preserve the original verified event and an authorized linked adjustment/reversal, with no invented “cash actually moved” event.

### 9.4 Bounded financial module

This architecture approves **a module-owned operational collection/settlement journal**, not a universal accounting double-entry General Ledger, bank-feed reconciliation engine, automatic shipping-company settlement or hidden payment gateway.

Exact record types, cash-handling controls, reconciliation cadence, service fees, payment evidence, discrepancies and legal documents remain **Payments/COD Feature Specifications**. Staff permissions, Tenant and Location access, amount constraints and customer rights apply regardless of technical mode.

---

## 10. Returns, Restock, Refunds and Exchanges — CW-07

### 10.1 Separate state/authority boundaries

A **Return Request**, a merchant's **Return Decision**, a **physical received/inspected item**, **eligible Restock**, a **refund obligation**, its **authorization**, external **processing**, and a **verified successful payout** are **not the same fact** and do not share one automatic Completed switch.

The Returns & Refunds use case coordinates but does not bypass:

- **Orders/Direct Sales:** authentic original sale and immutable commercial history.
- **Inventory:** actual receipt, quality disposition, sellable status, unique Restock movement by Location.
- **Payments & Collections:** financial exposure, applicable authorization and genuine refund execution/outcome.
- **Pricing/Promotions:** original-order economics, eligible returns and shipping/tax adjustment boundaries.

### 10.2 Return and Restock flow

1. Identify original transaction/line/Variant, permitted quantities, eligibility and applicable legal/policy window.
2. Record an authorized Return case and any consent/evidence. This is **not** immediate Available stock.
3. Record **actual physical receipt** at a valid Location; evaluate quality and conditions.
4. Inventory determines whether units may re-enter **sellable Available** and records **one protected movement per actual event**. Damaged/missing/non-eligible units do not become sellable.
5. Preserve returned quantity limits against total sold and already returned units, even under concurrent staff updates.

Under **D-172/D-173**, a completed Direct Sale must never be retroactively Voided to simulate a return, including a Guest sale. A Guest without paper receipt may be reviewed through authorized bounded sale-record search and proof; ambiguous cases escalate instead of approving an arbitrary refund.

### 10.3 Refund flow and limits

Per **D-124…D-135**:

- Review actual source collection and previous/pending/refunded exposure.
- Refund authority is separate from Order or Discount permissions. Over-limit actions require separately authorized approval with **no self-approval**, subject to the governing role/amount rules.
- Enforce **per-order cumulative refund authority**, plus optional staff/role **daily financial authorization limits**; do not double-count pending/exposed effects.
- Daily limit uses the **Merchant store-local calendar day** (D-129) and attributes the refund to **final authorization day** (D-130), not request day or provider-completion day.
- Material amount/payment/refund/permission changes or an elapsed configured authorization validity window may require **reauthorization** (D-131/D-132), including rechecking applicable new-day capacity on reauthorization (D-133).
- **Active Refund Exposure** is tracked separately across local days (D-134); any merchant-set alert threshold (D-135) does not itself impose an automatic new refund veto or numeric default.
- For external Pending/Unknown/late result, **verify actual provider/bank outcome before any retry** (D-124/D-125). A requested or approved refund is not proof the customer received money.
- Preserve independent real refund operations and partial refunds where lawful, while preventing duplicate effect of the **same execution reference**.

**No payment-provider integration is activated** in the first COD-focused Pilot by this contract. A legitimate external manual Refund must be documented and verified through the permitted method, never represented as an automated gateway event.

### 10.4 Timing, shipping and exchange boundaries

**D-136/D-137:** eligibility and timing for refunding original customer-paid shipping depend on cause, physical return/inspection where required, actual customer obligations and law. A lawful earlier refund may be required for particular Merchant fault cases; do not impose a blanket rule that every refund waits for Restock.

**D-141:** automatic recovery/clawback of a previously enjoyed free-shipping benefit after a partial return is **deferred beyond MVP**, notwithstanding retained conditional future D-138…D-140 business rules.

For **Exchange**, treat original return, approved replacement allocation/reservation, price difference and financial settlement as linked but distinct facts. The replacement must not be dispatched on assumptions before required original receipt, inspection, payable difference review and customer/merchant authorization. Final Exchange-specific Reservation, split-item eligibility and service-only handling belong to Feature Specs.

### 10.5 Transaction consistency

When one Tenant-local Return/Restock/Refund state change involves multiple module-owned records, coordinate a **short correct local transaction** with stable operation IDs and guarded quantities/amounts. **External payout** and **physical inspection** take place outside the DB transaction; failures require explicit evidence-based reconciliation rather than a global rollback claim.

---

## 11. Failure Classification, Recovery and Reconciliation — CW-08

### 11.1 Required failure taxonomy

| Failure class | Examples | Decision |
| --- | --- | --- |
| **Known transient without committed effect** | Real DB rollback/deadlock, confirmed connection failure before effect | Bounded safe retry, same operation reference, revalidate business predicates |
| **Business or authorization conflict** | Stock insufficient, invalid state, coupon unavailable, wrong Location, revoked role | Stop/deny; no blind retry or automatic override |
| **Unknown outcome** | HTTP timeout after possible commit, external card terminal timeout, refund request whose response was lost | Check the **authoritative business/providing-party evidence first**; no second charge/refund by guess |
| **Durable async failure** | Redis/Horizon unavailable after PostgreSQL commit | Recover from committed Outbox/intent, idempotently process |
| **Permanent technical/config failure** | Invalid schema, expired secret, unsupported event version | Fail closed, alert; authorized correction required |
| **Partial external reality** | Money collected but goods not handed over; dispatch physically occurred but local acknowledgement failed | Preserve actual physical/financial proof, reconcile/correct safely; do not forge a completed action |

### 11.2 Business-level idempotency, not just HTTP keys

Apply two complementary layers:

- **HTTP/API idempotency** when selected high-risk routes require/support an Idempotency-Key, scoped to validated Tenant/operation and proper requester context. An identical replay cannot bypass current authorization.
- **Business effect uniqueness** independent of supplied HTTP keys: stable order confirmation/reservation reference, physical dispatch/pickup movement event, unique actual financial collection/refund execution, and return receipt/restock event.

Two genuine separate purchases/collections/refunds must **not** be collapsed solely because payloads resemble one another. A per-order unique financial row that bans legitimate partial events is equally incorrect. Use operation-specific uniqueness and guarded aggregate constraints as described by TC-03.

### 11.3 Durable Outbox and post-commit work

Same-DB business changes include the Outbox intent **in the same committing transaction**. The relay forwards to Redis/Horizon after commit; consumers are **at-least-once and idempotent**.

Email, In-App follow-up, reporting projections and non-critical read models may be asynchronous. **Real customer agreement, Order Confirmation, physical Inventory Reservation/Consumption, verified Collection and actual Refund eligibility/execution truth are never delegated to an eventually consistent queue alone.**

A crash after DB commit but before queue transport must not drop the intent. A crash after queue publication can legitimately cause a duplicate job without a duplicate business effect.

### 11.4 Reconciliation ownership

| Owner | Verifies and repairs |
| --- | --- |
| Orders | Confirmed snapshot, real reservation and customer-agreement evidence |
| Inventory | Stock ledger, actual stock/quality, location, reservation/consumption operation reference |
| Fulfillment | Physical handover/dispatched/picked-up evidence and status |
| Payments & Collections | Actual charged/collected/remitted/refunded evidence and duplicate external references |
| Returns & Refunds | Eligible quantity, original sale, authorization, actual return/refund state and lawful timing |
| Outbox/Horizon | Stale committed events, failed queue attempts and retry-safe backlog |
| Reporting | Faithful authorized projection of corrected business data, not hidden rewriting of truth |

Corrections must use the owning Module's public contract, with authorization, reason, links to original facts, amounts/quantities before/after and complete evidence. Do not delete historical entries or invent compensating financial effects just to make totals look consistent.

### 11.5 Distinguish retry, reconciliation and compensation

**Retry** repeats only a proven safe logical operation with original identity.  
**Reconciliation** determines what actually happened before deciding another action.  
**Compensation/correction** is a real authorized follow-up action with its own evidence (e.g. verified Void/Refund after a real collection without delivery), **not** automatic rollback of payment-provider activity or physical goods movement.

### 11.6 No giant Saga Framework

The approved design intentionally uses **module-owned records and ordinary short local transactions**. A purpose-built durable status for a genuinely prolonged process may be introduced by Feature Spec, but a universal commerce Workflow Engine, event-sourced all-state rebuild, cross-Tenant distributed transaction manager or automatic money compensation framework is **not approved**.

---

### FBR-02-13/18 — Guest Scope and Commercial Suspension

Guest Checkout remains a supported sales path, but a Guest is **not** granted MVP private self-service over historical orders/files using phone, order number or checkout cookie. Merchant-mediated lawful communication is separate from Customer Account access.

A **Commercial Subscription Suspension** blocks new Online Checkout, Manual Order confirmation and Direct Sale, while allowing only an explicitly listed and currently authorized set of operations necessary to complete **previously confirmed** commitments (e.g., permitted fulfillment, reconciliation, customer-rightful return/refund). Unconfirmed Manual Pending Stock is not a reserved or confirmed commitment. Security/legal suspension is separately fail-closed, and renewal does not auto-confirm/execute previously denied operations. Honor customer rights and no duplicate financial/stock effects.

## 12. Security, Authorization, Location and Audit

### 12.1 Trust boundary

The Laravel API verifies the target Merchant in the authoritative Central directory, applicable staff membership or Tenant-local customer identity, Tenant state/entitlement, and actor roles/resource/Location access. A tenant_id, order_id, Location selector or UI button is **never authorization proof by itself**.

Every cross-module call receives a verified execution context; public contracts re-enforce the domain's specific invariants. Never allow a manager's order permission alone to grant unrestricted refund approval, stock adjustment, dispatch correction or cross-Location reassignment.

### 12.2 Business action authority

| Sensitive action | Enforcement |
| --- | --- |
| Manual Order confirmation, pending edits and Location proposal/change | Appropriate staff rights, one Location, customer consent and documented reason when required |
| Discount exceptions | Exact approved financial context and manager approval rules; invalidate stale approval when material totals change |
| Dispatch/Pickup and Hold Release | Authorized staff and actual physical evidence |
| External collection verification | Appropriate financial role and trustworthy external payment proof |
| Refund approval/execution | Independent financial rights, amount limits, reauthorization and anti-self-approval safeguards |
| Late external sale correction | Special documented correction permission, linked original event and escalation if evidence conflicted |
| Reading audit and linked documents | Strict Tenant/Location/resource/privacy policy, not unrestricted staff access |

### 12.3 Audit shape (conceptual)

Meaningful sensitive operations need original business reference, Tenant, Location, actor/role, timestamp, operation/correlation ID, approved intent, actual outcome, reason and minimal evidence. Store appropriate before/after values for material changes and link any correction/reversal to the original instead of silently rewriting.

Do not log full payment card information, CVV, raw private documents, API secrets or unnecessary customer PII. Audit/retention/indexing details are to be defined by domain and Security/Database Standards.

---

## 13. API, Concurrency and Event Contract Guidance

### 13.1 API and public contracts

Feature Specs must define **request/response types, state transitions, validation, permission registry entries, explicit conflict/unknown statuses and idempotency requirements** for sensitive commerce endpoints. HTTP Controllers are adapters; they must not own the business invariants.

Public contracts should expose intentional verbs such as “verify full Location availability,” “reserve for confirmed order,” “consume original reservation on actual handover,” “record verified collection,” and “record eligible return receipt.” These are **illustrative responsibilities**, not an approved set of class/method names or endpoints.

### 13.2 PostgreSQL safety

- Short transactions and deterministic ordering for multi-row locks.
- Appropriate row locks or atomic conditional updates for available stock/one-time transitions.
- PostgreSQL-enforced uniqueness/check constraints for financial/stock effects.
- Concurrency protection for coupon global and per-customer holds/redemptions.
- Transaction-local authorization and resource state revalidation where correctness depends on mutable facts.
- Stable business IDs to reconcile a lost HTTP reply without retrying actual money or stock effects.
- Honest handling of deadlock/serialization retry and non-retryable business conflicts.

Do not assume Redis distributed locking, a globally SERIALIZABLE isolation level, or one generic unique key across every module is required.

### 13.3 Event contract

Post-commit events include minimal scoped references, correlation/event identity and schema versions as prescribed by QJ/TC/Integration documents. Jobs reinitialize Tenant context on every attempt and clean it even after errors; **Tenant A → Tenant B → Central** worker reuse must be tested.

An event is **not** permission for asynchronous code to overwrite current business truth or consume stock without its domain's normal restrictions.

---

## 14. Acceptance Test Matrix and Failure Injection

**These are future test cases; no implementation or passing result is asserted here.**

| Case | Required proof |
| --- | --- |
| Concurrent last-unit Online Checkouts | At most one successful full-order reservation from the eligible Location; no negative Available or two Confirmed reservations |
| Manual COD Online confirmation delay | No confirmed reservation merely on placement; live revalidation at authorized confirmation |
| Cart vs coupon capacity | Cart entry does not hold coupon; actual placed eligible order holds, Confirmed finalizes; last capped coupon use cannot be oversubscribed |
| Expired coupon hold | D-056 merchant review when unavailable, D-057 successful revalidation when eligible; no silent price increase |
| Guest coupon restriction | Guest checkout without coupon permitted; Guest coupon redemption rejected |
| Manual Pending Stock | New availability triggers review, not auto-confirm, reserve, customer message or guaranteed FIFO allocation |
| Candidate alternative Location | Must cover entire order/eligible service; no implicit Location/Reservation change; D-201 stays open |
| Duplicate manual confirmation | Same business reservation and single Confirmed effect, even with different API keys |
| Direct Sale external payment ambiguity | Pending/Unknown does not complete sale; terminal evidence checked before another debit |
| Paid but cannot hand over | D-171: not completed; safe verified Void/Refund follow-up and no completed-sale stock consumption |
| Guest Direct Sale | Valid independent sale record and lawful receipt without fabricated Customer Profile |
| Offline attempt / late entry | No platform offline checkout; genuine external sale reconciled once with original/event time and linked correction |
| Preparing physical shortage | Confirmed order + Fulfillment On Hold; unaffected Reserved retained; no automatic Release Hold |
| Ready verification | No Ready without authorized packing check of latest approved items |
| Duplicate Confirm Dispatch | Once-only On-hand reduction and Reserved consumption with actual carrier handover proof |
| Confirm Delivery | Operational Completed only after authorized delivery verification, no second stock debit and no assumed COD settlement |
| Duplicate Pickup | Correct recipient, actual pickup, once-only stock debit; no carrier Dispatch fiction |
| Pickup deadline | Internal review only; no auto-cancel, stock release or refund |
| Confirmed branch/mode change | Customer agreement, full eligible alternative Location, safe reservation reassignment and no automatic Ready inheritance |
| COD carrier collected vs remitted | Keep merchant remittance separate and evidence-backed; no Paid on Delivered |
| Two distinct real Collections | Both allowed only where business rules permit, while repeat of same source reference is rejected |
| Return receipt/restock | No Available increase before eligible receipt/inspection; no double Restock |
| Partial refund/duplicate refund | Actual distinct partial refunds possible when approved; same external refund execution cannot run twice |
| Refund authorization boundaries | Per-order + optional local-day limits, correct final authorization day, reauthorization when conditions change |
| Outbox after DB commit / Redis outage | Durable event resumes without a duplicated business effect |
| Malformed/replayed event | Rejected/isolated under verified Tenant and message schema |
| Crash after real provider effect | Unknown outcome reconciled before retry/compensation |
| Tenant A / Tenant B / Central jobs | Dedicated DB contexts, authorization and cleanup prove no data/connection leakage |
| Real PostgreSQL race tests | Use actual PostgreSQL locks/transactions and independent Tenant databases, not Mock-only tests |
| Localized/legal documents | Correct Arabic/English communication and legally applicable Egyptian sale/refund documentation |
| Restore/release checks | BA-16 independent off-VPS backup/restore verified; BA-30 G3 unblocked only with real evidence |

Performance, worker count, stock-contention latency, checkout P95, retry thresholds and target recovery windows **are not assigned numeric guesses**. Define representative workloads/targets and test before relevant Pilot stages.

---

## 15. Open Decisions, Feature-Spec Questions and Explicit Deferrals

| Topic | Current status / next owner |
| --- | --- |
| **D-201 — Alternative-Location follow-up marker vs notes** | **OPEN**; neither option selected, not a blocker to documenting Architecture |
| Exact Orders/Direct Sale/Fulfillment state machine and API schemas | **Feature Specs pending**; no invented exhaustive status list |
| Basic Service combined carts, service fulfillment and internal quantity representation | **Feature Spec pending** under D-029 |
| Online COD manual-order pending rejection/cancellation UX and customer contact details | **Orders Feature Spec pending**; no fabricated backorder |
| Detailed coupon concurrent-hold DB mechanism, expiry processing and re-consent UX | **Pricing/Orders Feature Specs pending**, keep D-050…D-064 approved policies |
| Manual Pending Stock alternative-Location reviews and FIFO display | **Orders/Inventory Feature Specs pending**; follow D-183…D-200 |
| Transaction schema, lock targets, unique operation references and rollback windows | **Feature Plan + Database Standards pending** |
| External Direct Sale payment evidence, Unknown handling and cash/terminal reconciliation | **Direct Sales / Payments Feature Specs pending** |
| Carrier labels, actual handover evidence, delivery-failure handling and RTO | **Fulfillment Feature Specs pending**; no carrier API approved |
| Courier COD collection/remittance fees, discrepancies and reconciliation cadence | **Payments/COD Feature Spec pending**; no generic settlement automation |
| Return eligibility, verification for Guest receipt loss, exchange status and legal exceptions | **Returns & Refunds Feature Specs pending** |
| Refund amount role limits, optional per-day exposure policy and retention | **Financial/Privacy configuration + Feature Specs pending**, no numeric defaults |
| Egypt tax/e-invoicing/e-receipt and consumer-rights applicability | **Mandatory legal/compliance assessment before affected Pilot go-live** |
| Payment Gateway, courier provider API, marketing/social messaging, Split Fulfillment and Full POS | **DEFERRED/EXCLUDED** for first MVP/Pilot as already decided |
| **BA-16 Backup & Restore** | **DEFERRED**; off-VPS recovery and restore testing must be addressed separately |
| **BA-30 G3** | **BLOCKED** until BA-16 and recovery evidence |
| Production infra / deployment approval | **NOT PROVISIONED / NOT AUTHORIZED** by this document |

**No unresolved Feature detail is silently approved by this consolidated Architecture.** The open list is a scoping and design checklist, not a claim that every item blocks the documentation commit or should be decided before building unrelated Foundation work.

---

## 16. Implementation Sequence and Pilot Readiness

### 16.1 Future Spec Kit implementation plan (separately authorized)

1. Map each approved commerce action to a single owning Module, verified Tenant scope, public dependencies and approved business state transition.
2. Write per-Feature Spec Kit requirements, plan, tasks, acceptance tests and (where justified) isolated PR scope.
3. Establish PostgreSQL authoritative inventory, order, collection, return and financial-effect invariants with guarded constraints and dedicated tests.
4. Implement Online/Manual order confirmation paths and the correct coupon hold/reservation separation.
5. Implement Direct Sales with documented actual payment/handover evidence, once-only local effects and ambiguous-outcome recovery.
6. Implement Fulfillment transitions, physical handover proof, On Hold, Pickup and protected reservation consumption.
7. Implement Payments-owned collection/COD reconciliation and accurate financial exposure presentation.
8. Implement Return/Refund workflows with receipt/inspection, separate refund authorization/execution and linked correction history.
9. Attach existing Outbox/Horizon technical integration for noncritical post-commit side effects and scope-safe notifications.
10. Run real PostgreSQL/Tenant isolation, concurrency, fault/retry, financial, recovery, security and legally relevant acceptance tests.
11. Execute controlled Development release gates and separately resolve BA-16/BA-30 and Production readiness before any live Merchant cohort.

This sequence is **illustrative dependency-aware engineering planning**. It is not an authorized coding sprint, fixed delivery timeline or binding business rollout wave.

### 16.2 BA-30 release implications

| Gate | Commerce evidence |
| --- | --- |
| **G1 Tenant safety** | Tenant DB/Location authorization, staff access and cross-worker isolation |
| **G2 Core consistency** | Correct stock, coupon, order, money, dispatch, return and Refund invariants under concurrency |
| **G3 Backup/restore** | **BLOCKED while BA-16 deferred**; tested off-VPS recovery essential |
| **G4 Safe release** | Compatible migrations, no duplicate schedulers, safe worker drain, verified rollback strategy |
| **G5 NFR/capacity** | Real checkout contention and transaction latency, queue age and tested volume |
| **G6 Security/privacy** | Approved permissions, evidence protection, customer data handling and tenant controls |
| **G7 Legal readiness** | Appropriate Egypt Pilot documents, sales/tax/refund/customer-right requirements |
| **G8 Operational completeness** | Human follow-ups, truthful Pending/Hold/Delivered/Refund behavior and incident escalation |
| **G9 Environment isolation** | Development data, secrets and runtime separated from future Production |
| **G10 Explicit sign-off** | Approved evidence and responsible release owner for each enabled feature/cohort |

**No gate is marked passed by this document.** Controlled phased Pilot activation is governed by the Project Overview and separately approved Pilot Launch Plan, not automatic activation when a Markdown document is merged.

---

## 17. Final Approved Architecture Position

**CW-01…CW-08 are APPROVED, each Option A. CW-09 Option A is APPROVED for this consolidated document and upload to development.**

The MVP uses **owning-module Application Use Cases** to coordinate **narrow public contracts** and **short, correct Tenant PostgreSQL transactions**. Online COD confirmation, Manual Pending Stock, immediate Direct Sale, actual Dispatch/Pickup and eligible Restock follow their **distinct authoritative business milestones**. Collections, COD settlement, refund authorization, external payout and physical handover have **distinct proofs and lifecycles**.

**PostgreSQL protects authoritative local facts; Transactional Outbox + Redis/Horizon handles recoverable post-commit side effects.** Stable business references and reconciliation prevent duplicate financial/stock execution; no universal Saga engine, immediate external-effect rollback, microservice choreography or implied payment/courier provider integration is selected.

**Important gates remain:** D-201 is still **OPEN**; detailed Features/technical limits are unimplemented, BA-16 is **DEFERRED**, BA-30 G3 is **BLOCKED**, and Production is **NOT PROVISIONED**. The document approves architecture and publication only, not development implementation, deployment or live Pilot operation.

---

**Related architecture:** [Backend](./backend-architecture.md) · [Module](./module-architecture.md) · [Tenancy](./tenancy-architecture.md) · [Transaction Consistency](./transaction-consistency-architecture.md) · [Queue/Jobs/Event Processing](./queue-jobs-event-processing-standards.md) · [Identity & Access](./identity-access-architecture.md) · [Integration & Notifications](./integration-architecture.md) · [Infrastructure & Deployment](./infrastructure-architecture.md)

**End of approved Architecture baseline — no implementation or Pilot-readiness claim.**
