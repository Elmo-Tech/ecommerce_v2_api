# E-commerce SaaS V2 — Project Overview

**Repository target:** `docs/00-overview/project-overview.md`  
**Product:** Multi-Tenant E-commerce SaaS — Unified Commerce Operations  
**Status:** **DOCUMENT DRAFT FOR REVIEW** — Product/MVP baseline approved through OV-25 (A); this document's final wording has not yet been signed off  
**Document version:** Review Draft 1.0  
**Prepared:** 2026-11-16  
**Phase:** Product Scope Baseline → Architecture Discovery → Feature Specification  
**First market:** Egypt — Closed Pilot  
**Source of truth for detailed business rules:** approved D-series decision history; D-201 remains OPEN  
**Authorization:** Documentation draft publication to `development` was explicitly requested by the project owner on 2026-11-16. No application coding, deployment, or promotion to `main` is authorized by this overview.

> **Read this first.** This file defines **what the product is**, **who it serves**, **which capabilities belong to the first MVP**, and **what must be proven before launching**. The project owner explicitly approved the 24 OV scope decisions and chose **OV-25 (A)** to freeze that product baseline and prepare this draft for review. This is **not** an approval of an architecture, database schema, API contract, implementation plan, deployment, or the final text of this document. Previously approved business decisions remain binding. A business rule's existence does not by itself place every future integration or workflow into the first Pilot.

---

## 1. Purpose and document authority

### 1.1 Purpose

Provide a common product and release-scope reference for the platform owner, product analysts, architects, Laravel API engineers, Next.js engineers, QA, and future GitHub Spec Kit contributors. The overview should be understandable without reading hundreds of detailed decisions, while explicitly linking those decisions instead of recreating them or changing their meaning.

### 1.2 Decision hierarchy and source provenance

1. **Explicitly approved D-series business decisions** govern business behavior, safeguards, and exceptions for the features to which they apply. The newest repository decision register covers D-152 through D-200; **D-201 is open**.
2. **OV-01 through OV-24, plus OV-25 (A)** govern the overall MVP scope, initial rollout approach, and approval workflow; they do not silently override specific D-series safeguards.
3. The consolidated discovery handoff provides the context and earlier approved D-series decisions. Historical handoffs, examples, and proposals are **not current approvals** if newer decisions supersede them.
4. Architecture records, standards, and individual feature specifications will refine implementation *without inventing new business approvals*.

**Relevant repository sources, read at the time of drafting:**

- `docs/decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md` — current detailed register for D-152…D-200 and the D-201 proposal.
- `docs/handoff/ecommerce_saas_handoff_v5_48_2026-11-16.md` — consolidated discovery, including earlier D-series decisions and financial representation rules.
- `docs/decisions/OPEN_DECISIONS_AND_MVP_GATES.md` — unresolved business questions and launch gates.
- `docs/handoff/NEXT_CHAT_START_HERE.md` — handoff state and reading order.
- `docs/WORKFLOW.md` — proposed repository branch workflow, not proof that automation or branch protection is configured.
- Historical supplied source: `ecommerce_saas_business_discovery_handoff_ar.md` and the earlier `ecommerce_saas_handoff_v0_3_2026-11-15(1)(1).md` — background only, not substitutes for later decisions.

### 1.3 Status vocabulary

| Label | Meaning |
| --- | --- |
| **APPROVED — Business** | Explicitly chosen commercial/operational rule in the D-series; binding when the relevant functionality is present. |
| **APPROVED — OV Scope** | Explicitly chosen top-level MVP/Pilot scope or process decision (OV-01…OV-25). |
| **MVP IN SCOPE** | Capability committed to the first MVP, potentially activated in a later *controlled Pilot phase*. |
| **PILOT GATE** | Requirement that must be verified before a particular merchant, workflow, or feature is activated. |
| **OPEN / TO SPECIFY** | Design, rule, threshold, or detail not explicitly approved; must not be guessed. |
| **DEFERRED** | Not included in the first MVP unless re-approved through change control. |
| **SUPERSEDED / HISTORICAL** | Earlier wording retained for traceability; not the current effective rule. |

---

## 2. Product vision and definition

Build an **independent-store, multi-tenant commerce SaaS** for small and medium merchants. Each merchant operates a separately scoped storefront and management workspace for products, inventory by physical location, orders, customers, staff, and permitted SaaS capabilities.

The product unifies three channels under shared catalog, pricing, stock availability, order history, and authorization rules:

1. **Online Orders:** customer-originated orders via the merchant storefront.
2. **Manual Orders:** staff-entered orders originating from WhatsApp, Facebook, Instagram, telephone, or another manual channel; their source is recorded.
3. **Direct Sales:** simplified, online-only, in-store immediate-sale flow, **not** a full POS product.

This is **not a multi-vendor marketplace**: customers shop in individual merchant stores, not one shared marketplace catalog. The product is also **not** an ERP, a full accounting suite, or an advanced warehouse management system.

**Product principle:** *Simple to start; consistent and safe as a merchant grows.* Small merchants should not have to understand advanced warehouse or ERP terminology just to sell, while multi-location merchants should still have correct stock and operational controls.

---

## 3. Business problem, users and business goals

### 3.1 Problems addressed

Retailers commonly handle website checkout, social-message orders, phone requests, and counter sales in separate tools. Without a single operational record they can:

- promise the same stock to multiple customers or allocate a basket against stock split across unsuitable locations;
- lose the customer's agreed price, discounts, fulfillment method, payment evidence, or change history;
- treat physical delivery as proof of cash collection;
- misstate sales by mixing actual transaction time with later data entry;
- lose control of staff authority across locations and sensitive financial actions;
- spend excessive time manually reconciling product catalog and order information.

### 3.2 Primary actors

| Actor | Core responsibility | Boundaries |
| --- | --- | --- |
| **Platform Super Admin** | Onboard merchants/stores; administer plans, entitlements, collection/renewal records, merchant support and platform oversight. | Platform administration does not waive tenant privacy, security or audit requirements. |
| **Merchant Owner / Administrator** | Manage merchant store settings, catalog, locations, staff, and permitted business processes. | Subject to the tenant's SaaS entitlements and applicable rules. |
| **Merchant Staff** | Operate authorized orders, direct sales, inventory, transfers, fulfillment and refunds. | Custom/fixed roles, individual permissions and assigned locations are enforced. |
| **Registered Shopper** | Maintain an account with an individual merchant, check out, review relevant orders and use eligible coupons. | Coupon redemption requires registration in MVP (D-050/D-051). |
| **Guest Shopper / Walk-in Customer** | Check out or purchase without an unnecessary registered account where the channel permits it. | No forced profile for normal walk-in Direct Sale (D-169); legally required documents and evidence remain necessary. |
| **External carrier/payment actor** | Physically deliver or collect funds outside the platform's integration boundary. | No direct carrier API or integrated online gateway in the initial Pilot (OV-04/OV-05); manual evidence is not provider confirmation. |

### 3.3 Outcomes sought

- Independent branded stores with isolated merchant records and entitlements.
- One reliable operational view of catalog, stock, orders and sales across the three channels.
- No overselling and no implicit reservation of unavailable stock.
- Fulfillment of the whole eligible order from **one eligible Location** in MVP.
- Traceable staff actions, permissions, stock moves, commercial adjustments and payments.
- Fast merchant onboarding and adequate bilingual storefront/admin experience.
- A controlled, measurable Egypt-first Pilot and a path to future geographic/product expansion.

---

## 4. Initial market, commercial model and launch constraints

### 4.1 Market and merchant cohort

- **MVP launch market:** Egypt, following D-143 (A).
- **Pilot mode:** **Closed Pilot** with selected participating merchants — no public merchant self-registration (OV-01/OV-03).
- **Priority merchant profile:** Egyptian small/mid-sized retailers selling through a website, manually recorded social/phone orders, and simplified direct sales, consistent with D-144 (B).
- **Longer-term strategy:** other markets including Saudi Arabia/Gulf may follow, but are **not initial-Pilot country obligations**.
- **Unspecified:** pilot participant count, exact retail verticals, plan prices, trial durations, staff/transaction caps, rollout dates and commercial targets; none are set by this file.

### 4.2 SaaS onboarding and subscription control

- Only **Platform Super Admin** creates/onboards merchants and merchant stores in the first MVP (OV-01 A).
- Platform Super Admin manually establishes subscriptions/plans, records collections, and handles renewals (OV-02 A); **integrated automated subscription billing and renewal** are not required in the initial Pilot.
- The platform **automatically applies** configured plan limits and enabled features once set. Staff permissions and a plan's feature entitlements are distinct checks.
- This restriction applies to **merchant signup**, not to permitted **shopper registration**.
- Exact pricing, grace periods, expiration/suspension behavior and reinstatement policies remain for the SaaS module and business approval.

### 4.3 Regulatory readiness for Egypt

D-142/D-143 require evaluation of tax, sales documents, invoicing/e-invoicing and e-receipt obligations **for the specific Egyptian merchant types and transactions enabled**. A generic PDF, a manually recorded payment or an internal order number does **not** automatically satisfy statutory obligations.

**Pilot gate:** do not activate a merchant/channel until the applicable legal/document requirements have been reviewed and the obligatory behavior is supported, or that merchant/channel is excluded from the initial wave. A compliance claim must be based on verified obligations, not an assumed blanket rule.

---

## 5. Product surface, branding and localization

### 5.1 Storefront and Merchant Dashboard

- **Storefront:** independent merchant-facing customer store on a **platform-managed subdomain** in MVP; custom domains are deferred (OV-07 A).
- **Design:** a choice of **2–3 predefined themes**, with permitted logo, brand colors, banners and basic content configuration; **no visual page builder** (OV-06 B).
- **Store content:** basic bilingual content pages, such as About, Contact, FAQ and relevant policies; editable basic navigation and footer within a theme, without full CMS/page-builder capabilities (OV-19 B).
- **Responsive:** both Storefront and Merchant Dashboard work on desktop, tablet and mobile browsers. PWA installation and native mobile applications are deferred (OV-12 A).
- **Connectivity:** Responsive Web does not imply offline transactions. Direct Sale remains online-only under D-174.

### 5.2 Language and currency

- Arabic and English support for merchant administration, storefront user experience and system emails in MVP (OV-08 B). RTL/LTR, display formatting and translatable merchant content need explicit localization standards later.
- First-Pilot stores operate using **EGP**. Multi-currency display/pricing in a single store and exchange-rate conversion are deferred (OV-08 B).
- **Approved cross-currency financial principle:** finalized monetary amounts use **integer minor currency units** according to each currency's proper exponent, with accurate intermediate calculations. No hard-coded `×100` rule for all future currencies.
- Store Time Zone remains significant for merchant-local operational rules, including the previously approved refund authorization day (D-129/D-130). How and whether existing stores may change their time zone is not decided here.

---

## 6. Sales channels and core user journeys

### 6.1 Online Order

A shopper selects eligible products/variants or supported Basic Services in a merchant store, chooses a supported delivery/pickup method and completes the relevant checkout flow. Backend rules determine pricing, entitlement, stock availability, suitable fulfillment location and customer agreement. **Online Orders do not enter Manual Pending Stock** when stock is insufficient.

In the initial Pilot, **COD** is supported within the approved policies; integrated online payment gateways are deferred (OV-04 A). Any approved manual/external collection record must respect the actual payment channel and evidence, without pretending an integrated provider exists. Existing prepaid business decisions remain historical/future capability rules, not authorization to enable a gateway now.

### 6.2 Manual Order

Authorized staff records an externally received order with its **source channel** (OV-16 A). Items must come from eligible Catalog products/variants/Basic Services; unrestricted custom items and arbitrary unit-price edits are excluded in MVP (D-180/181).

If an order cannot be confirmed from one eligible Location, it may be kept as **Manual Pending Stock**: no final confirmation and **no stock reservation**. An authorized employee later reviews current full-order availability, customer agreement, totals, taxes, discounts, shipping and payments before a manual confirmation (D-183). FIFO governs **follow-up priority**, not guaranteed stock allocation (D-184 and related decisions). Alternative-location proposals do **not** automatically switch Locations, reserve stock, notify the customer or change finances (D-194…D-200).

**D-201 remains OPEN:** dedicated internal marker for an alternative-location proposal awaiting a customer's response versus ordinary communication notes. No chosen behavior may be inferred from this overview.

### 6.3 Simplified Direct Sale

Catalog-based, online-connected immediate sale with **full verified collection and actual handover**. One payment method per sale; no split tender, credit sale or offline checkout in the first MVP (D-167/168/174/179). A guest/walk-in sale does not require a fabricated Customer Profile, but traceable sale and lawful documentation remain required (D-169).

When external electronic collection is used, a properly authorized person verifies and records successful collection; an unknown/pending payment result is not success (D-170). A real sale transacted outside the platform during connectivity loss may later be recorded with evidence and correct original-sale/entry timestamps; this is **not** permission to build offline checkout or silently double-count stock or receipts (D-175…D-178).

### 6.4 Fulfillment, delivery and pickup

- **Single-location fulfillment:** the entire order is handled from one eligible Location, with appropriate priority/availability; no Split Fulfillment.
- **Preparing** covers picking and packing, with the approved verification before Ready (D-158).
- Authorized edits to Confirmed orders can continue through Preparing/Ready **before Dispatch**, only under required stock, financial, customer-consent and audit controls (D-152).
- Actual shortage/damage during Preparing requires controlled hold and revalidation; valid unrelated reservations are not silently released (D-153…D-157).
- **Dispatch** follows authorized confirmation of actual handover to a carrier and consumes stock exactly once (D-159).
- Delivery confirmation is authorized and evidence-aware; operational completion does **not** prove that COD has been settled (D-160/161).
- **Store Pickup** follows actual pickup/recipient verification; merchant-defined collection deadline produces review, not automatic cancellation (D-162/163).
- Delivery/Pickup mode and pickup branch changes require the approvals, rechecks and records established in D-164…D-166, not automatic fulfillment or reallocation.
- Pilot delivery/pickup operations and any courier/tracking reference are managed **manually**, without a Shipping API (OV-05 A).

### 6.5 Returns, exchanges and refunds

Post-sale handling must preserve the original transaction, customer rights, inspection and stock reality, correct financial exposure, authorization and anti-duplicate-refund protections. An accepted return does not put stock back into Available until actual receipt/inspection where required. Refund approval, submission/processing and successful receipt are **not interchangeable**. Applicable D-series refund limits, merchant-local dates, exposure and escalation rules remain authoritative.

**Deferred:** automatic recovery of a previously granted free-shipping benefit after a partial return under D-141 (B), even where conditional rules for a future version exist in D-138…D-140.

---

## 7. MVP functional capability baseline

This table reflects the **approved OV scope**, bounded by applicable D-series decisions. A capability can be **in MVP scope** even if activated in a later controlled Pilot phase. The individual module specification will determine detailed behavior and acceptance cases; this file does not invent every subfeature.

| Domain | MVP boundary | Source / dependency |
| --- | --- | --- |
| **Tenant & platform administration** | Independent merchant/store scopes; Super Admin-only onboarding; merchant status and enabled feature control. | D-001…D-006; OV-01/03; tenancy/identity decisions later. |
| **Subscriptions & entitlements** | Manual plan assignment, collections and renewal management by Super Admin; automatic entitlement/limit enforcement. | OV-02; billing/pricing/expiry policies to specify. |
| **Storefront & themes** | Independent subdomain, 2–3 themes, configurable identity/branding; bilingual pages, menu and footer. | OV-06/07/08/19; publishing and legal policies. |
| **Responsive surfaces** | Browser-based Storefront and Merchant Dashboard, including tablet/mobile operation. | OV-12; network required for controlled sales. |
| **Staff, roles and access** | Default roles plus merchant-created Custom Roles drawn from a defined Permission Registry; permission and Location scopes. | OV-18; D-078…D-088 and refund permissions remain binding. |
| **Catalog** | Simple/Variable products, eligible Variants, Basic Services, reusable attributes, SKU and variant pricing rules; basic publication/categories/search/media. | D-028…D-035; D-145…D-149. |
| **Catalog data onboarding** | Excel/CSV bulk import for products and Variants with validation and review preview before applying; manual stock input via authorized inventory operations. | OV-13; file mapping/dedupe/retry rules later. |
| **Pricing, promotions, coupons and taxes** | Backend-authoritative prices; Basic/Advanced feature-entitled discount capabilities; approved stacking/coupon limits and taxes. | D-036…D-151; money minor units and legal readiness. |
| **Inventory & Locations** | Per-Location On-hand/Reserved/Available, no overselling, appropriately authorized adjustments, optional multi-location business capability. | D-007…D-009 and later inventory/fulfillment rules. |
| **Stock Transfers** | Manual source-to-destination transfers with authorized dispatch and **actual receipt** at destination; stock in transit not sellable at destination. | OV-17; transfer exceptions to specify. |
| **Online Orders** | Eligible storefront orders with one-location full fulfillment, COD-first in Pilot and approved confirmation logic. | D-series; OV-04/05. |
| **Manual Orders** | Staff entry with source channel, permitted Pending Stock, price/revalidation, recorded approvals and internal follow-up. | D-180…D-200; OV-16; D-201 OPEN. |
| **Direct Sales** | Online-only, immediate full collection + handover, one payment method, optional guest identity, authorized reconciliation of genuine external sales. | D-167…D-179. |
| **Fulfillment / Delivery / Pickup** | Controlled Preparing/Ready/Hold, manual carrier dispatch, delivery and pickup confirmations; manual carrier records, no API. | D-152…D-166; OV-05. |
| **Payment records & post-sale** | COD and authorized verified external/manual collections as applicable; traceable returns/exchanges/refunds and financial controls. | D-series; OV-04; statutory requirements. |
| **Customer Management** | Registered customers and permitted guests; contact information, addresses, order history, basic search/filter. | OV-15; D-050/051, D-169. |
| **Notifications** | Internal operational alerts and transactional email for approved essential order events; delivery/retry correspondence within existing D-series rules. | OV-10; D-118/119; no marketing or automated WhatsApp/SMS. |
| **Dashboards & reports** | Essential merchant operational overview, sales/orders/stock/collections/returns, filters, Excel/CSV export; platform merchant/subscription metrics. | OV-09; D-176 actual sale date, financial measure definitions. |
| **Activity & Audit** | Critical auditable events plus important admin activity, secure actor/time/change trace and tenant isolation. | OV-14; D-094/D-178 and related safeguards. |
| **Merchant Help Center** | Basic help content and support requests from Merchant Dashboard with status/replies handled by Platform Super Admin. | OV-20; case lifecycle/attachment policy later. |
| **Pilot governance** | Closed cohort, functional acceptance, defined measurable KPIs, controlled change requests, phased capability activation. | OV-03/21/22/23/24. |

### 7.1 Catalog and discount boundaries

- Simple and Variable Products are supported. Basic Service is a bounded service offering, not a dynamic quote/form builder (D-028…D-030).
- Product publishing, category hierarchy, basic storefront search/filtering and media limits follow D-145…D-149; advanced AI ranking and smart recommendations are excluded from the first MVP.
- Basic and Advanced discount entitlements may both exist *within* MVP subject to merchant plan authorization. **Professional Promotions** and advanced campaign builders remain deferred (D-036).
- Registered Customer status is required for MVP coupon redemption; one successful redemption per registered account per coupon according to D-050/D-051. Guest Checkout is **not disabled** by that coupon restriction.
- Promotional discount eligibility and explicit stacking rules must be observed by the appropriate channel; do not apply all promotions indiscriminately to Manual/Direct sales (D-151).

### 7.2 Inventory safety boundaries

- Physical stock and reservations are tracked **by Location**. A total across locations is not a guarantee that a complete order can ship or be picked up.
- The system must never silently consume, release or steal a confirmed order's reservation when another order, transfer or adjustment occurs.
- Transfers represent **physical stock movement**; changing the Location assigned to an order is a **different** authorized operation and does not initiate an inventory transfer.
- Inventory bulk importing is not part of the first MVP (OV-13). Manual stock operations still require permissions, reasons, consistency checks and audits.

### 7.3 Operational notifications and email scope

- Internal alerts inform authorized employees of material follow-up actions, such as pending stock review, overdue pickup review and other expressly approved operational situations. They are **not** automatic cancel, confirm or refund commands.
- Essential **transactional** customer emails cover applicable real order events, such as order placement/receipt, confirmation, dispatch, ready for pickup, cancellation and refund-status updates, based on actual states and the finalized notification rules (OV-10 B).
- Email content and system messages are bilingual; no marketing campaign mail, bulk promotions, SMS, WhatsApp or social-platform messaging integrations are authorized in MVP.
- A missing/invalid email or sending failure must follow the approved exception and manually documented follow-up practices where required (D-118/119). Email is **not made universally mandatory for guest checkout** merely because transactional email is supported.

---

## 8. Explicitly deferred / excluded from the first MVP

The following exclusions are **product-scope decisions**, not claims that the future architecture is incapable of supporting them.

| Capability | MVP status | Basis / note |
| --- | --- | --- |
| Merchant self-service registration | **DEFERRED** | Super Admin-assisted onboarding for first Pilot — OV-01. |
| Automatic SaaS payment collection/renewal | **DEFERRED** | Manual plan, collection and renewal management — OV-02. |
| Integrated online payment gateway | **DEFERRED** | COD-first Pilot, existing financial safeguards still apply — OV-04. |
| Shipping/carrier API integration | **DEFERRED** | Manual delivery/pickup/carrier reference management — OV-05. |
| Custom merchant domains | **DEFERRED** | Platform subdomains only — OV-07. |
| Custom visual page builder / full CMS | **DEFERRED** | Prebuilt themes + basic pages — OV-06/19. |
| In-store multi-currency display/checkout and FX | **DEFERRED** | EGP Pilot, bilingual UI — OV-08. |
| AI features (including AI content/operations) | **DEFERRED** | OV-11; existing D-148 excludes smart/AI search and product recommendations. |
| PWA and Native mobile apps | **DEFERRED** | Responsive Web only — OV-12. |
| Bulk Inventory Import | **DEFERRED** | Product/Variant bulk import only — OV-13. |
| CRM segmentation, automated marketing | **DEFERRED** | Essential customer management and non-marketing transactional email — OV-10/15. |
| Direct WhatsApp, Facebook and Instagram integrations | **DEFERRED** | Staff-entered Manual Orders only — OV-16. |
| Advanced WMS and auto replenishment/transfer proposals | **DEFERRED** | Basic Inventory and manually controlled transfers — OV-17. |
| Complex enterprise policy access control | **DEFERRED** | Defined roles/permissions, location restrictions and approved financial exceptions — OV-18. |
| Advanced helpdesk, live chat, AI chatbot | **DEFERRED** | Basic support requests — OV-20/11. |
| Advanced BI/report builder and scheduled analytics | **DEFERRED** | Essential dashboards/reports only — OV-09. |
| Full offline checkout, full hardware POS, split tender/credit Direct Sale | **EXCLUDED** | Direct Sale scope and D-167/168/174. |
| Split Fulfillment and fully automated inter-location allocation | **EXCLUDED** | Single eligible Location for whole order. |
| Full ERP/general ledger, complex manufacturing or advanced procurement | **EXCLUDED** | Bounded Unified Commerce product vision. |
| Advanced dynamic service forms/quote builder | **DEFERRED** | Basic Services only — D-029/030. |
| Professional Promotions, guest coupon redemption, multiple concurrent coupons | **DEFERRED** | D-036…D-088 as applicable. |
| Free-shipping-benefit clawback after partial returns | **DEFERRED** | D-141 (B). |

**Do not treat deferral as a waiver of statutory requirements or existing customer rights.** A deferred integration must not be simulated with unreliable "success" states or fake evidence.

---

## 9. Business invariants and cross-module safeguards

These constraints apply across modules, regardless of the rollout phase:

1. **Tenant isolation:** one merchant cannot access another's private records, files, customers, credentials, sales, inventory or audit trail.
2. **Entitlements ≠ Permissions:** enabled SaaS features, staff roles, Location access and independent financial approval limits are separate requirements; an employee cannot grant themselves unrestricted authority.
3. **No overselling:** stock reservation and physical movements remain consistent under concurrent orders and sales.
4. **One eligible fulfillment Location:** do not combine partial stock from multiple locations to present a fulfillable single order.
5. **No implicit confirmation:** Manual Pending Stock, alternative-location suggestion, a follow-up timer or an approved discount alone never creates a Confirmed order or reservation.
6. **Accurate lifecycle:** order status, preparing/dispatch/delivery, payment collection and refund processing remain distinguishable; historical events cannot be silently rewritten.
7. **Idempotent effects:** dispatch, collection, inventory movements, corrections and refunds must not create duplicate business effects on retries or repeated operator actions.
8. **Financial integrity:** currency minor units and approved tax/discount calculation rules govern final amounts; historical order/tax values and explicit customer agreements remain traceable.
9. **Permissions and audit:** especially for changes to taxes, prices, permissions, stock, transfers, manual discounts, corrections and refunds; sensitive data must not leak into logs.
10. **Transactional email ≠ consent:** sending or delivering email does not establish required customer financial consent; missing email must not silently turn an order into a prohibited flow.
11. **Receipts and law:** mandatory Egyptian compliance/customer rights are go-live gates for the chosen merchant activity, not future nice-to-have features.
12. **Channel parity where approved:** catalog eligibility, stock safety, historical financial rules and entitlements apply consistently to Online, Manual and Direct Sales, with explicit channel-specific differences preserved.

---

## 10. Non-functional requirements and production readiness

**OV-23 (B): Measurable Production Readiness** is approved. Numbers are **not** set in the Overview; architecture and test planning must establish measured targets **before the Pilot**.

| Area | Required direction | Numeric target/status |
| --- | --- | --- |
| **API and frontend performance** | Define measurable response-time objectives (including P95 where relevant) for representative business journeys. | **TO SPECIFY** after realistic workload definition. |
| **Load and concurrency** | Test competing checkout, manual confirmation, direct sale and inventory operations; prove no overselling or double-counting. | **TO SPECIFY** test profiles and acceptable limits. |
| **Availability & observability** | Establish monitoring, actionable alerts, incident visibility and a realistic availability objective. | **TO SPECIFY**, no invented SLA/SLO. |
| **Backup and recovery** | Implement tested backups; rehearse restoration and define RPO/RTO. | **TO SPECIFY** and verify before live merchant activation. |
| **Security & tenancy** | Authentication, authorization, rate limiting, privacy, tenant isolation, secrets protection and applicable security testing. | **PILOT GATE**, details in standards. |
| **Auditability & integrity** | Preserve critical events, change history and unique transaction effects. | **PILOT GATE** for affected workflows. |
| **Localization** | Verify Arabic RTL and English LTR across relevant dashboard, storefront, documents and transactional emails. | **ACCEPTANCE CASES** to specify. |
| **Data protection & lawful operations** | Protect customer/merchant data and satisfy applicable legal obligations for enabled merchants/channels. | **PILOT GATE** after review. |

There is **no approved merchant count, transaction-per-second target, uptime percentage, hosting topology or exact recovery window** in this Overview. Capacity planning must not guess or borrow numbers from unrelated projects.

---

## 11. Closed Pilot launch, release phases and measurable success

### 11.1 Two different acceptance moments

**A. Ready to enter the Pilot** requires a complete, safe, legally eligible selling workflow for the first participating merchant(s), including their actually enabled product capabilities. Data isolation, order/payment/inventory consistency, authorized operations, operational monitoring, tested backups/recovery and required legal documents cannot be knowingly deferred past activation of the affected workflow.

**B. Successful Pilot / readiness to expand** comes after real operation: review merchant feedback and operationally measurable KPIs against objectives set **in the Pilot Launch Plan before the experiment**. No invented merchant count, revenue goal, conversion target or error rate is claimed here (OV-21 B).

### 11.2 Controlled Phased Pilot (OV-24 B)

The whole agreed MVP stays in scope, but different **approved** capabilities can be activated in **controlled phases**. Each activation requires its own readiness/acceptance evidence and must not expose a merchant to an incomplete or unsafe customer journey. Any merchant who needs a capability cannot be onboarded into a wave that does not yet safely support that capability.

The exact feature-to-wave assignment, delivery order and participating merchant cohort must be recorded in the **Pilot Launch Plan / Implementation Plan** and separately approved. This overview intentionally **does not declare a final phase schedule**.

**Illustrative release gates (not approved phase dates or a fixed module sequence):**

1. **Foundation gate:** tenant boundaries, roles, essential store configuration, monitoring/backups, compliance assessment and critical cross-module integrity verified.
2. **First commerce journey gate:** a complete supported storefront/manual/direct sales subset, with correct stock/payment/fulfillment and post-sale obligations for those merchants.
3. **Subsequent capability gates:** remaining approved MVP features enabled only when dependent journeys and feature tests pass.
4. **Expansion gate:** Pilot metrics, material support issues, functional coverage, security/legal risks and recovery readiness reviewed.

### 11.3 Example Pilot KPI families (definitions pending)

- End-to-end success/failure rate of enabled core commerce journeys.
- Stock consistency and number/severity of prevented or observed overselling incidents.
- Reliability and performance across the actual pilot load.
- Merchant usage/adoption of the enabled sales and inventory workflows.
- Support-request resolution and frequency/severity of blocking operational incidents.

These are **candidate metric families**, not approved equations or numeric pass/fail thresholds. Sales, net sales, collected receipts and refunds must be distinguished when defining financial KPIs. Late-recorded genuine external sales belong to the original business sale date in operational reports with transparent late-entry evidence (D-176).

---

## 12. Scope change governance (OV-22 B)

Once the Baseline is approved, all newly requested capabilities are classified and handled as follows:

| Request class | Required handling |
| --- | --- |
| **Critical production defect / security / compliance / data integrity** | Prioritize according to severity; fix with appropriate review, regression testing and incident documentation. |
| **Feature outside approved MVP** | Capture in Backlog; assess user value, dependencies, schedule, cost, legal/security risk and testing impact; **explicit product-owner approval** required to change scope. |
| **Nonbreaking UX improvement inside approved behavior** | Review, prioritize and test; must not silently change approved business rules. |
| **Change to an approved D-series rule** | Make the conflict explicit; obtain a separate recorded business decision rather than overwriting history. |
| **Deferring an already approved MVP feature beyond MVP** | Requires explicit scope approval; it cannot disappear merely because an early Pilot wave did not include it. |

The Backlog, specification and delivery plan must distinguish **approved MVP**, **later Pilot phase**, **future/deferred**, **open decision** and **critical fix**.

---

## 13. OV-01…OV-25 approval register

The following list documents the **explicit approvals provided in the Project Overview discovery**. These are OV IDs, **not replacements or renumberings of the D-series**.

| ID | Approved option | Decision summary |
| --- | --- | --- |
| **OV-01** | **A** | Only Platform Super Admin creates/onboards merchants and stores in MVP. |
| **OV-02** | **A** | SaaS plans, collection records and renewals managed manually by Super Admin; limits/entitlements enforced automatically. |
| **OV-03** | **A** | Closed Pilot for selected merchants in Egypt; not open public merchant registration. |
| **OV-04** | **A** | Defer integrated online payment gateways; enable COD and only appropriately verified/recorded manual/external collections. |
| **OV-05** | **A** | Manual Delivery/Pickup/courier operations and references; no Shipping API in first Pilot. |
| **OV-06** | **B** | 2–3 prebuilt themes; brand and basic content customization, no page builder. |
| **OV-07** | **A** | One independent platform subdomain per merchant; custom domains deferred. |
| **OV-08** | **B** | Arabic and English storefront/dashboard/system emails; EGP pilot; no in-store Multi-Currency. |
| **OV-09** | **A** | Essential operational dashboards/reports, filters and Excel/CSV export; platform overview metrics. |
| **OV-10** | **B** | In-app operational alerts and essential transactional order emails, no marketing messages. |
| **OV-11** | **A** | Defer all AI features past first MVP. |
| **OV-12** | **A** | Responsive Web for dashboard and storefront; PWA and native apps deferred. |
| **OV-13** | **B** | Product/Variant Excel/CSV import with validation/preview; no Bulk Inventory Import. |
| **OV-14** | **B** | Critical Audit Logs plus important General Activity History, with tenant isolation and privacy. |
| **OV-15** | **A** | Essential customer details, addresses, registered/guest workflows, order history and simple filters. |
| **OV-16** | **A** | Manual external-channel order entry with source tagging; no direct social/WhatsApp integration. |
| **OV-17** | **B** | Basic per-Location inventory plus authorized Manual Stock Transfers with actual source/receipt tracking. |
| **OV-18** | **B** | Default roles and merchant Custom Roles through a defined Permission Registry; staff Location scope. |
| **OV-19** | **B** | Essential settings plus basic bilingual content pages, simple menu/footer; no Page Builder. |
| **OV-20** | **B** | Basic Help Center and in-dashboard Support Requests managed by Platform Super Admin. |
| **OV-21** | **B** | Functional acceptance plus a few measurable Pilot KPIs, defined in Pilot Launch Plan before use. |
| **OV-22** | **B** | Controlled scope changes through Backlog, impact assessment and explicit approval; critical issues prioritized. |
| **OV-23** | **B** | Measurable performance/availability/recovery readiness and load/concurrency/restore tests; targets later in Architecture. |
| **OV-24** | **B** | Controlled Phased Pilot with independent gates; all approved MVP features stay in scope. |
| **OV-25** | **A** | APPROVE OV-01…OV-24 as product/MVP baseline; prepare `project-overview.md` draft for content review; publication of the draft to `development` was separately authorized afterward. |

**Approved baseline ≠ approved draft text.** The product owner must review and approve this Markdown document separately before any commit or PR.

---

### FBR-02 — Approved Architecture Refinements (2026-11-22)

The architecture-gap review **FBR-02-01 through FBR-02-20** has **20 owner-approved Option A decisions**. It clarifies existing MVP technical contracts: current membership/permission authority, CSRF and exact JSON Money/IDs, safe Tenant migration, caching/queues, locked financial timezone/tax input mode, private Guest/SSR access, discount ceilings, Central scoped Support/screenshots, exceptional protected Owner recovery, obligations-only commercial suspension and consistent execution-time reporting. The canonical review and owner decisions are recorded in [FBR-02 Architecture Gap Review](../reviews/FBR-02-architecture-gap-review.md), with relevant rules integrated into governing Architecture/Standards files.

This **does not** approve new unrelated commercial features, silently settle **D-201**, select **BA-16** backups, waive **BA-30 G3**, prove real Laravel/Stancl compatibility or authorize live merchant activation. Source D-series and OV decisions retain their own authority. Unspecified subscriptions, legally valid tax choices, legal retention, numeric resource limits, Feature schema/UX and deploy measurements remain separately reviewed.

## 14. Open decisions, module dependencies and launch gates

This document does not quietly settle the following questions:

| Area | Remaining decision / specification responsibility | Decision timing |
| --- | --- | --- |
| **D-201** | Whether proposed alternative fulfillment locations waiting on a customer response get a dedicated internal follow-up marker or existing communication notes only. | Orders / Fulfillment discovery. **OPEN.** |
| **Pilot eligibility/legal requirements** | Confirm retail cohorts, applicable Egyptian tax/sales-document/e-receipt obligations, consent/data obligations and legal wording before specific merchant activation. | Pilot Launch Plan; Tax & Documents module. |
| **SaaS plans** | Plan names/prices, entitlements, quotas, trial/grace/expiry/suspension and renewal behavior. | Merchant & SaaS module. |
| **Identity/permission details** | Role template list, permission registry, invite/account lifecycle, tenant boundary and Location checks, super-admin operational access. | Identity & Authorization specs. |
| **Catalog import** | Excel/CSV schema, size limits, validation, duplicate policy, updates vs creates, review, failure/retry and product image handling. | Catalog module. |
| **Stock and transfers** | Movement types, in-transit handling, cancellation/loss/partial receipt, counts/adjustments, access and reconciliation. | Inventory module. |
| **Orders/payment/refund** | Feature-specific checkout edge cases, accepted external collection evidence, manual reconciliation, return/refund authorization and provider-agnostic states. | Orders, Payments, Returns modules; existing D-series rules first. |
| **Delivery / email** | Eligible notification triggers, mail failure/retries, logs, templates, consent handling, carrier reference display, legal terms. | Shipping, Notifications and Orders modules. |
| **Store content / customer management** | Page schema, translation/content fallback, contact/address validation, account/guest linking, retention/privacy. | Storefront, Customers and Localization specs. |
| **Reports/Support** | Metric definitions, report exports, filters, support request states, attachments, retention and permitted visibility. | Reports and Support modules. |
| **Architecture and deployment** | Tenant-data isolation mechanism, API/auth architecture, persistence, job/message handling, storage, backup topology, capacity profiles, scaling strategy and numeric NFR targets. | Architecture/Standards review **before implementation and Pilot**. |
| **Phased rollout** | Exact waves, merchant activation requirements, feature dependencies, acceptance owners, Pilot KPI thresholds. | Pilot Launch Plan and Implementation Planning. |

Earlier historical proposals marked OPEN must be interpreted against later explicit approvals; do not reopen closed D decisions based on superseded handoff excerpts.

---

## 15. Technology direction and repository boundaries

### 15.1 Selected technology direction

- **API backend:** Laravel **13**, as selected for the E-commerce V2 project.
- **Database:** PostgreSQL as the selected technology direction.
- **Storefront and dashboard:** Next.js as the selected frontend direction; frontend implementation is **outside this API repository**.
- **Specification workflow:** GitHub Spec Kit, with independent Feature specs, clarification, planning, tasks, verification and PR-level review as needed.

This is a **technology/product direction**, not an approval of shared-schema tenancy vs database-per-tenant, PostgreSQL RLS, auth/session strategy, modularization technique, queue/worker provider, object storage, caching, API response envelope, deployment host, CI configuration or zero-downtime deployment design. The team must separately review those decisions with their tradeoffs.

### 15.2 Proposed documentation map

```text
AGENTS.md
.specify/
docs/
  00-overview/
    project-overview.md
    progress-tracker.md
  01-architecture/
    backend-architecture.md
    tenancy-architecture.md
    module-architecture.md
    data-architecture.md
    identity-architecture.md
    commerce-workflow-architecture.md
    integration-architecture.md
    infrastructure-architecture.md
  02-standards/
    api-standards.md
    authentication-standards.md
    authorization-standards.md
    code-standards.md
    database-standards.md
    file-storage-standards.md
    localization-standards.md
    security-standards.md
    testing-standards.md
    tenancy-standards.md
    transaction-idempotency-standards.md
    money-calculation-standards.md
    queue-job-standards.md
    integration-webhook-standards.md
    audit-observability-standards.md
  03-modules/
    <business-module>/
      module-overview.md
      <feature>/
        ...
  decisions/
    architecture/
  handoff/
    ...
```

This is a **proposed documentation taxonomy**, not a claim that each file already exists. Product decisions live in the D-series registry and OV baseline, architectural rationale in architecture/ADR records, implementation rules in standards, and detailed behavior/tests in separate module/feature specifications. Do not duplicate a long business decision log into every module.

### 15.3 Development/release workflow

The repository's existing `docs/WORKFLOW.md` describes a **proposed** `development` integration branch, `feature/*` branches and review before promotion to `main`. This document does **not** imply that branch protection, CI, deployment, or a Laravel codebase has already been configured. Publication of this review draft to `development` was explicitly authorized; subsequent changes and promotion to `main` require separate approval.

---

## 16. Review checklist and next documentation steps

Before marking this published draft as **DOCUMENT APPROVED**, confirm:

- [ ] Product definition, Egypt Pilot cohort and independent-store boundaries are correct.
- [ ] OV-01…OV-25 choices accurately reflect the product owner's approvals.
- [ ] MVP features versus deferred capabilities are classified correctly, with no loss of an approved capability due to phased rollout.
- [ ] Business rules (including D-201 OPEN) are not silently re-decided or contradicted.
- [ ] Egyptian legal readiness and customer protections are represented as gates, not presumed compliance.
- [ ] Technology choices are described as direction, without inventing infrastructure or an architecture.
- [ ] Owners and targets for Pilot KPIs and NFRs will be specified in subsequent plans.
- [ ] Final text is explicitly approved **before** changing the document status to approved or promoting it to `main`.

**Next steps, subject to separate approvals:**

1. Product owner reviews this Markdown and requests any corrections; update until **DOCUMENT APPROVED**.
2. Publish this review draft to `docs/00-overview/project-overview.md` on `development` under the user's explicit authorization; after content sign-off, update its status through a separately approved change.
3. Draft and separately review `docs/01-architecture/backend-architecture.md` and the related tenancy/data/identity/integration/infrastructure decisions.
4. Define coding/security/API/database/localization/financial/observability standards only after related architecture choices are made.
5. Split each business domain into independent Spec Kit Features with requirements, plan, tasks, acceptance tests and review. Implement only after the applicable specifications and approvals are in place.

---

## Appendix A. Key terminology

| Term | Meaning in this product |
| --- | --- |
| **Tenant / Merchant** | Independent subscribing business whose operations and data must be isolated. |
| **Storefront** | Customer-facing web store belonging to one merchant, on its own platform subdomain in MVP. |
| **Location** | Merchant location (shop/stock site) with its own stock and operating eligibility. |
| **On-hand / Reserved / Available** | Physical stock, committed stock, and currently sellable stock per Location under approved inventory rules. |
| **Manual Pending Stock** | Unconfirmed Manual Order awaiting stock/review; not a reservation or fulfillment promise. |
| **Direct Sale** | Immediate, fully collected and handed-over, internet-connected simplified in-store sale; not full POS. |
| **Stock Transfer** | Physical movement between Locations with source issue and confirmed destination receipt. |
| **Feature Entitlement** | SaaS plan/Platform-controlled access to a feature; separate from an employee permission. |
| **Acceptance Gate** | Explicit pass/fail evidence required before enabling a merchant/capability or expanding a Pilot wave. |
| **D-series / OV-series** | Detailed business-rule decisions / top-level Project Overview scope decisions; neither silently supersedes the other. |

---

**Product/MVP scope decision:** **APPROVED — OV-25 (A), 2026-11-16.**  
**This Markdown document:** **DRAFT — awaiting content review and separate approval.**  
**D-201 business decision:** **OPEN.**  
**GitHub publication:** **REVIEW DRAFT on `development` — explicitly authorized by the user on 2026-11-16.**  
**Application implementation:** **NOT AUTHORIZED.**