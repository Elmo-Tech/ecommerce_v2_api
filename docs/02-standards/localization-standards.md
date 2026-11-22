# E-commerce SaaS V2 — Localization Standards

**Repository:** Elmo-Tech/ecommerce_v2_api  
**Target path:** docs/02-standards/localization-standards.md  
**Branch:** development  
**Version:** 1.0 — Approved Localization Engineering Standards  
**Prepared:** 2026-11-22  
**Decision status:** **APPROVED — LOC-ST-01 through LOC-ST-08, Option A**; LOC-ST-01 includes the owner's explicit reduced-MVP localization scope. The owner expressly authorized this comprehensive documentation and publication on development.  
**Implementation status:** **DOCUMENTATION ONLY — NOT IMPLEMENTED / NOT TESTED / NOT DEPLOYED.**

> **Mandatory reading rule:** This document records engineering and MVP-scope decisions. It is **not** evidence that Laravel translations, Next.js internationalization, locale preferences, product schemas, workers, test suites, CI or Production exist. **D-201 remains OPEN, BA-16 Backup & Restore remains DEFERRED, BA-30 G3 remains BLOCKED for any live Merchant Pilot, and Production is NOT PROVISIONED.**
>
> **Explicit product-scope amendment:** The owner decided during LOC-ST-01 and confirmed at LOC-ST-08 that the first MVP localizes **the system user interfaces, validation/errors, system messages, notifications and transactional email templates**, but **DOES NOT** add multilingual Merchant-owned Product/Variant/Category/Brand data or translated Merchant-authored Content Pages/Entity fields. Basic storefront content pages still exist, but use one authored content value in MVP. This **narrows the previously approved bilingual-content portion of OV-19 B**, and the related wording in Project Overview, Backend and Module Architecture requires a separately authorized cross-document update. **OV-08 B remains effective for bilingual interfaces and system email.** No prior source document is silently rewritten by this file. Any applicable statutory content/language duty still requires independent legal approval under BA-30 G7.

---

## 1. Purpose, Governing Authority, Scope and Status

### 1.1 Purpose

Provide consistent, testable conventions for **Arabic and English** across:

- Laravel 13 API-first Modular Monolith, including Form Requests, API error/success messages and server-originated text.
- Independent Next.js Storefront, Merchant Dashboard and Platform Dashboard user interface surfaces.
- User-selected, securely resolved locale and deterministic missing-translation fallback.
- True RTL/LTR behavior, mixed-script text, numbers, EGP amounts, canonical identifiers and Date/Time displays.
- Background jobs, recipient-aware transactional emails and Tenant-local In-App notifications.
- Automated localization, accessibility, security, cross-Tenant and release tests.

The first Pilot targets **Egypt**, is **EGP-only within each store**, and supports **ar and en as system locales**. These Standards are intentionally lightweight: framework-native localization and source-controlled translation files, not an external translation server or database translation catalog.

### 1.2 Binding sources

| Source | Established authority and fit |
| --- | --- |
| [Project Overview](../00-overview/project-overview.md) | OV-08 B bilingual UI/emails and EGP Pilot; OV-19 B originally described bilingual Merchant Content Pages; OV-22 B controlled scope changes; OV-24 B Pilot gates |
| [Backend Architecture](../01-architecture/backend-architecture.md) | Laravel 13, API-first Modular Monolith, separate Next.js, financial integrity, BA-30; historical bilingual-content references need reconciliation |
| [Module Architecture](../01-architecture/module-architecture.md) | Merchant Settings & Content owner, One Writer/Public Contracts; bilingual Merchant content references require future scope alignment |
| [Data Architecture](../01-architecture/data-architecture.md) | DA-04: finalized BIGINT minor-unit Money; UTC TIMESTAMPTZ instants; DATE calendar values; historical snapshots |
| [Tenancy Architecture](../01-architecture/tenancy-architecture.md) | Trusted Central/Tenant resolution and independent Tenant DBs; host/client headers cannot grant scope |
| [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) | Independent Central workforce/tenant-local customer identity; membership, Role, Location and resource authority |
| [API Standards](./api-standards.md) | AS-02/03/08: ApiResponse, camelCase wire keys, stable errorCode/HTTP semantics, Laravel Form Request and localized ar/en messages |
| [Code Standards](./code-standards.md) | CS-05/06/07: stable int-backed application Enums, central exception mapping, correct request/job context lifecycle |
| [Database Standards](./database-standards.md) | PostgreSQL integrity, measured search/indexes and safe authorized Tenant-local query behavior |
| [Authentication Standards](./authentication-standards.md) | Sanctum first-party sessions/CSRF, no identity or Tenant authority from a locale preference |
| [Authorization Standards](./authorization-standards.md) | Current permissions, revocation, Tenant/Location/resource-scoped reads and actions |
| [Integration & Notifications Architecture](../01-architecture/integration-architecture.md) | INT-03 system-managed Arabic/English versioned email templates; recipient locale precedence remains Feature-owned |
| [Integration Standards](./integration-standards.md) | INT-ST-06/07: durable logical email intent with selected locale/version and current-authorized In-App rendering |
| [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) | QJ-02 and safe long-running Central/Tenant Horizon context initialization and cleanup |
| [Infrastructure Architecture](../01-architecture/infrastructure-architecture.md) | Separate Development and future Production and compatible versioned Blue-Green deployments |
| [Security Standards](./security-standards.md) | SEC-03/08/09: safe untrusted input/escaping, abuse protection, privacy and scoped data access |
| [Testing Standards](./testing-standards.md) | TEST-05/07/09: stable API contract, ar/en browser E2E, progressive risk-based required checks |
| [Logging, Audit & Observability Standards](./logging-audit-observability-standards.md) | LOG-02/04/06: scoped correlation, safe error diagnostics, no PII/secret leakage |
| [Open Decisions & MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) | D-201 OPEN; unresolved business decisions are not inferred from translations |
| [Repository Workflow](../WORKFLOW.md) | Documentation on development; separate review/promotion to main |

**Decision precedence:** Approved detailed D-series business rules determine business effects; the more recent **owner-approved LOC-ST MVP scope amendment** explicitly narrows only Merchant-managed bilingual content previously stated in OV-19 and related architectural descriptions. OV-08 bilingual UI/email remains approved. Architecture controls Module/Tenant/security boundaries; this Standard refines implementation. Do not silently replace any other product rule, Domain Event, API or legal obligation. Any new conflict needs a specific owner decision.

### 1.3 Approved versus deferred scope

| Area | First MVP position | Owner |
| --- | --- | --- |
| Storefront navigation/buttons/forms/labels/help text owned by the platform | **ar + en** | Next.js UI |
| Merchant Dashboard and Platform Dashboard user interface | **ar + en** | Next.js UI |
| API validation, auth, public business errors, success messages | **ar + en** | Laravel/owning Module |
| System-owned visible status labels and menu/permission descriptions | **ar + en** | UI/Locale catalog, based on stable business codes |
| Tenant-staff In-App notification text | **ar + en** | Notifications + approved Next.js rendering |
| Approved transactional customer email templates | **ar + en** | Notifications Module, platform-owned/versioned |
| Merchant-owned Product/Variant names/descriptions | **one authored value; no localized variants** | Catalog / owning Tenant DB |
| Categories/Brands and other Merchant-authored Entity names | **one authored value; no localized variants** | Owning Module |
| About/Contact/FAQ, basic Merchant content and authored policies | **basic pages remain; one authored content value** | Merchant Settings & Content |
| Translation tables/JSON language maps and per-Entity translation rows | **NOT REQUIRED / NOT SELECTED in MVP** | Future product decision |
| Merchant-editable system error/email translations, HTML editor | **NOT SELECTED** | Deferred |
| AI/automatic translation, translation SaaS, external CMS, dynamic translation DB service | **DEFERRED / NOT SELECTED** | Future scoped approval |
| Additional currencies/FX or Merchant currency conversion when language changes | **DEFERRED** | Money/Product decision |
| Additional system locales beyond ar/en | **NOT IN FIRST MVP** | Future scoped approval |

**Content truth versus language:** Product names, policies and other Merchant-authored text are **shown as stored**, even if the user's application UI is in the other language. This is not an automatic-transliteration or machine-translation promise. Basic pages/menus/footer stay part of the approved MVP **as features**. If legally required notices need particular wording/language or accessibility, the responsible Feature and BA-30 G7 must address them **before** relevant Pilot activation even if generic entity translation is deferred.

### 1.4 Status vocabulary

| Status | Meaning |
| --- | --- |
| APPROVED | Engineering/scope choice accepted; not evidence of running software |
| DEFERRED / NOT SELECTED | Capability excluded from the first MVP unless separately approved |
| TO SPECIFY | Particular library, preference storage, key/route, input grammar, SQL/API contract remains Feature/Foundation detail |
| PENDING VALIDATION | Requires real browser, framework, provider, legal or measured runtime proof |
| PASSED / FAILED | Actually executed test result for a recorded SHA and environment |
| NOT RUN / BLOCKED | Never count as Passed; required failing/missing test can block an affected release |

---

## 2. Approved Decision Register — LOC-ST-01…LOC-ST-08

| Decision | Owner-approved Option A | Binding implementation direction |
| --- | --- | --- |
| **LOC-ST-01** | Native Laravel + Next.js i18n; system UI/messages only | Exclude per-Entity multilingual Merchant data and basic page translations in MVP; no generic Translation Engine |
| **LOC-ST-02** | Arabic default, explicit preference, browser-language negotiation, deterministic fallback, Tenant-safe context | Support ar/en; validate and scope language selection; email-specific recipient fallback remains Feature-owned |
| **LOC-ST-03** | Native RTL/LTR, bidirectional isolation, locale-aware formatting, canonical data | Correct direction without modifying stored values, references, phone/SKU IDs, money or business states |
| **LOC-ST-04** | Canonical Money/Time, strict numeric input normalization, trusted Merchant timezone | Exact minor units/Decimal calculations, UTC instants, DATE, local business-day rules, no ambiguous parsing |
| **LOC-ST-05** | Source-controlled namespaced keys, Laravel native validation, stable API errorCode, automated key checks | UI-owned text in Next.js; server-owned errors in Laravel; no translation DB catalog |
| **LOC-ST-06** | Recipient-aware outbound language, durable email locale/version, render-time In-App text, safe Horizon context | Same logical email uses same approved locale/template on retry; no cross-Tenant job leakage |
| **LOC-ST-07** | Automated catalog/API/RTL/accessibility/money/time/isolation tests + risk-based release checks | Prove actual behavior; critical financial/security/commerce meaning regressions block affected release |
| **LOC-ST-08** | Complete cross-document reconciliation, explicit amended scope, practical checklists and controlled publication | Documentation-only new Markdown file on development; future cross-doc source cleanup separately authorized |

**Architecture register INT-01…INT-08 and integration standards INT-ST-01…INT-ST-10 are distinct from LOC-ST.** This document does not redefine their notification, webhook, security or provider semantics.

---

## 3. LOC-ST-01 — Responsibility Boundaries and Framework-Native Localization

### 3.1 Which layer translates which text?

| Surface | Responsible layer | Examples | Must not |
| --- | --- | --- | --- |
| Navigation, buttons, headings, placeholders, local dialog/toast text | Next.js i18n | Common action, Storefront header, Merchant Orders page | Store all system labels as per-Tenant DB rows |
| Laravel field validation/auth errors | Laravel native translation files and Form Requests | required, invalid, credentials, 401/422 | Reimplement all Laravel validation in Next.js as business authority |
| Business conflict/success response message created by Backend | Owning Module error registry + central exception/response mapper | stock conflict; approval not yet executed | Vary errorCode, status, state or data by display locale |
| System-owned status labels displayed in UI | Next.js stable-key localization of state representation | pending, confirmed, failed | Compare Arabic/English words as the domain state |
| Customer email content | Notifications-owned reviewed versioned templates | actual Order event, payment/refund semantics | Give Merchant HTML templates or provider SDK ownership |
| Tenant staff In-App text | Notifications type/key/version + authorized Next.js render | pending review reminder | Leak protected content from cached text after access revocation |
| Merchant-authored names/descriptions/pages | Owning Tenant Data/Feature | Product name, About/FAQ, store policies | Invent translated fields/tables or pretend text exists in two languages |
| Financial/date display | Layer producing visible output, using canonical source values | EGP totals, createdAt, merchant-local day | Derive authoritative money/date/business rules from formatted strings |

All Business Modules remain **locale-independent** for decisions, persistence constraints, stock/money, authorization and state transitions. Only approved message/label creation and display adapt language.

### 3.2 Source-controlled system translations

Use **Laravel native translation resources** for Backend-owned texts and **maintained Next.js i18n files** for frontend-owned UI text. Select an ordinary, compatible Next.js i18n implementation during Frontend Foundation; do not prescribe a dependency before checking actual Next.js routing/SSR features. No custom database translation infrastructure or microservice.

- Adopt namespaced keys reflecting owning domain or shared common patterns.
- Keep one explicitly owned translation source per visible message; **avoid contradictory copies** of server error/success wording in both repositories.
- Common frontend labels may be reused through an intentional common namespace; avoid copying the same text between ten pages.
- Use reviewed key renames/removals and deprecation compatibility when old/new releases overlap.
- Merchant-authored content is data, not a platform translation key.
- Translation files are versioned with their respective application; changing UI copy is a reviewed code change rather than unrestricted Tenant runtime editing.

### 3.3 Merchant content remains real content, not translated structure

For the first MVP, a Product, Variant, Brand, Category or basic content page maintains its owning-Feature one authored name/body/description where approved. Exact table columns/types are Feature/DB-owned, not prescribed here. **Do not** automatically create name_ar/name_en, translation tables, per-locale JSON maps or two Product identities.

- The UI may be English while the stored Product name is Arabic or mixed Arabic/English.
- Search supports actual stored values and their scripts as applicable, but a cross-language semantic search/translation guarantee is not added.
- Existing historic Order snapshots preserve the original authorized content/value required by Domain/DA; changing UI locale does not rewrite those snapshots.
- Tenant data remains in its own DB, with normal scope and authorization, including for pages and exports.
- A later multilingual content capability needs separate D/OV product approval, API/schema transition strategy and migration/testing plan.
- Legal policy/terms text cannot be silently omitted where law requires an approved language/content; address case-specific Pilot requirements under G7.

### 3.4 Not an implicit redesign

This decision does not change Laravel 13, PHP-FPM initial deployment, API routes, Next.js repository, session/CSRF, PostgreSQL database-per-Tenant, file storage or integration architecture. Localization should use the existing framework extension points, not global mutable service locators.

---

## 4. LOC-ST-02 — Supported Locales, Resolution, Preferences and Fallback

### 4.1 Supported identifiers and default

- Public MVP application locales: **ar**, **en**.
- Egypt-first **system default locale: ar**.
- Recognize compatible regional forms (e.g. ar-EG → ar; en-US → en) **only for choosing a supported application language**; do not strip region from an unrelated business currency/timezone decision.
- Unsupported, empty, malformed, rejected or absent locale does not cause a Tenant/Identity switch, broad failure or arbitrary language loading. Use the approved ar fallback with safe diagnostics when relevant.
- Do not treat Accept-Language, query parameters, locale Cookies or browser headers as trusted **authorization** or source of Merchant/Tenant scope.

### 4.2 Preference priority for interactive UI

| Priority | Valid source | Preconditions |
| --- | --- | --- |
| 1 | **Current explicit user choice** | User deliberately changes ar/en through the appropriate UI |
| 2 | **Saved preference of the same actor/surface** (if available) | Stored with scoped authority; no new mandatory DB field in Foundation |
| 3 | **Supported browser language preference** | Validate parsed Accept-Language and quality weights; do not select q=0 |
| 4 | **System fallback ar** | Safe default when no supported preference exists |

**UI, session and API context:** When Next.js has resolved an explicit UI choice, it should send a validated matching **Accept-Language** to Laravel for API human-readable messages. Laravel must resolve the current request independently and return stable API contracts. Server-side rendering and hydration must agree on the same locale rather than momentarily showing a different page and flipping it client-side.

The precise Next.js route prefix, cookie name, persistence method, profile preference fields and synchronization design are **TO SPECIFY** after verifying compatible framework behavior. A lightweight nonsecret scoped Cookie is a candidate, not a requirement to create a global preference service or store locale in every Customer row.

### 4.3 Separation of user contexts

| Context | Selected locale must be based on | Must never inherit |
| --- | --- | --- |
| Anonymous Storefront | Verified Tenant host + own explicit guest choice/browser/default | Other Merchant's shopper preferences |
| Tenant-local registered shopper | Own verified Tenant customer/session + explicit/saved choice | Equal-email shopper in another Tenant |
| Merchant employee | Own Central workforce identity, current Merchant context, explicit/saved choice | Another employee, prior Tenant worker, Central admin default |
| Platform Dashboard | Authorized Central Platform identity + own preference | Merchant customer preference |
| Public Laravel API request | Validated Accept-Language, with appropriate trusted request context | Earlier worker/request mutable App Locale |
| Queue/job system processing | Durable scoped message/task locale where the work needs human-readable output | Last HTTP request or previous queued Job |
| Transactional email | Recipient-specific approved Feature policy | Automatic assumption that staff creator's locale equals customer's |

If a browser opens Tenant A and Tenant B in separate tabs, a shared cookie or session preference must not silently grant a Tenant switch, combine identities or overwrite one Tenant's state. Persist independent actor/surface preferences when necessary; define precise scope in relevant Feature design.

### 4.4 API Accept-Language negotiation

Implement **bounded, well-defined language negotiation** rather than substring matching. Example expected selection:

| Request preference | Selected ar/en | Notes |
| --- | --- | --- |
| ar | ar | Supported |
| en | en | Supported |
| ar-EG | ar | Supported parent language |
| en-US | en | Supported parent language |
| en;q=0.9,ar;q=0.8 | en | Respect actual preference quality |
| en;q=0,ar;q=0.5 | ar | Explicitly rejected en is not selected |
| fr or absent | ar | Safe approved fallback |
| Invalid/oversized header | ar or safe bounded validation outcome | No arbitrary code execution or exception text leakage |

The accepted wire behavior, explicit UI override, caching/variance strategy and optional route-locale handling are Feature/API/Foundation design details. Headers do not change HTTP codes or business effects.

### 4.5 Missing-key fallback

**Current supported locale → Arabic fallback → safe human-readable general message** (where no approved translation exists).

- English UI/API key present: display English.
- English key absent but Arabic exists: display Arabic and record privacy-safe diagnostic in Development/CI.
- Missing in both: safe generic wording appropriate to context; record and treat as a defect. Never leak raw internal technical keys, SQL/stack traces or protected resource details to users.
- Missing translations in critical business or privacy/security flows **block** the affected release until corrected.
- Fallback is resilience, **not** approval to ship mostly untranslated English flows.
- Email recipient-language selection and absence-of-preference order remain **Feature-owned** under INT-03; do not substitute this generic UI priority for a recipient-specific policy.

### 4.6 Cache discipline

Locale-specific **public** UI responses may vary on the appropriate verified locale/host settings, but must not be shared across Tenants or leak private session content. Do not use an unscoped app-level variable, singleton or cache entry for mutable current locale. Evaluate correct SSR and cache behavior in the future chosen Next.js architecture; no speculative Redis locale cache is required.

---

## 5. LOC-ST-03 — RTL/LTR and Bidirectional Text Standards

### 5.1 Document/layout directions

| Context | Required behavior |
| --- | --- |
| Arabic app view | HTML language ar and RTL document or appropriately scoped direction |
| English app view | HTML language en and LTR document or appropriately scoped direction |
| Navbar, panels, popovers, dropdowns, form labels | Logical alignment, keyboard/focus order and proper directional placement |
| Data tables and scroll | Predictable logical reading order without broken accessible navigation |
| Mobile/responsive | No invisible actions, clipped text or uncontrolled horizontal scroll |
| Messages and emails | Safe, tested ar/RTL and en/LTR variants with mixed identifiers |

Use CSS **logical properties** such as inline-start/end and logical border/padding positioning instead of duplicated left/right overrides. Mark actual document language. Apply direction-sensitive mirroring only to **directional navigation icons** when appropriate, not arbitrary product images, logos, QR/barcodes or phone text.

### 5.2 Mixed-script and isolated values

Merchant content remains **single-value** and may be Arabic, English or mixed regardless of UI locale. Render it with correct contextual direction, using HTML **dir=auto / bdi / equivalent directional isolation** where needed:

| Field | Display constraint |
| --- | --- |
| Merchant/Product/Customer free-form name | Determine actual content direction; isolate from surrounding punctuation and badges |
| SKU, Order/Refund reference, API ID | Preserve canonical order, punctuation and symbols, typically LTR-isolated |
| Email address, URL, code, barcode | Directionally isolate as LTR without reordering actual characters |
| Phone number | Preserve plus sign, country code and leading zeros as a string; avoid numeric parsing |
| Notes containing Arabic + Latin | Use appropriate isolation/Unicode bidirectional handling |
| Money/date expressions | Use locale-aware formatting while isolating neighboring references |

Never reverse characters, alter SKU/phone identifier bytes, insert direction-control characters into stored canonical data as a workaround, or use a global **text-align: right** rule as a complete RTL strategy. Treat suspicious control characters in untrusted content according to reviewed validation/display policy; do not blindly remove legitimate language characters.

### 5.3 Forms, accessibility and selection

- Layout direction and input direction are not always identical. Use LTR where proper for emails, codes, telephone references; use auto for names/notes where sensible.
- Keep semantic DOM order and correct labels, focus, keyboard movement, screen-reader output and validation association.
- Ensure right-to-left visual positioning does not invert mathematical signs, negative amounts, decimal separators, percent meaning, status text or button actions.
- Avoid destructive automatic conversion when users copy/paste mixed-script text, select a portion, export data or edit long values.
- Verify the actual CSS component library and browser behavior with Playwright, not screenshots alone.

### 5.4 Emails, exports and print output

Platform email templates follow INT-ST-06: Arabic/RTL and English/LTR, accessible markup and alternate text as approved; test real email rendering where applicable. CSV/Excel exports and PDF/printed documents are **independently specified outputs**: don't turn canonical identifiers/money/Date values into ambiguous localized strings for machine reuse. Human-readable reports may format according to approved locale, but must keep meaning, authorized scope and numeric precision. Prevent spreadsheet-formula injection in exported user-authored strings without corrupting legitimate canonical IDs or values.

---

## 6. LOC-ST-04 — Money, Numeric Input, Dates and Timezones

### 6.1 Canonical Money independent of language

**DA-04 remains authoritative:**
- Store finalized Money in PostgreSQL **BIGINT integer minor units** and preserve the associated currency.
- Use **precise Decimal arithmetic** for prices/discounts/taxes/intermediate calculations; apply approved per-Feature rounding order.
- Do **not** use FLOAT/DOUBLE for authoritative financial arithmetic or JavaScript floating point to validate large exact Money.
- First Pilot Merchant currency is **EGP**. Language choice does not convert EGP to USD or create FX support.
- Other future currency minor-unit exponents may differ; do not hard-code multiply/divide-by-100 as a universal currency rule.
- Financial snapshots, actual collection/refund state, approval limits and audit evidence cannot be rewritten by re-rendering or changing locale.

| Meaning | Illustrative stable representation | Arabic visible rendering | English visible rendering |
| --- | --- | --- | --- |
| 1,500 EGP | 150000 EGP minor units | ١٬٥٠٠٫٠٠ ج.م | EGP 1,500.00 |
| Order reference | ORD-105 | ORD-105 isolated LTR | ORD-105 |
| SKU | NK-AM-1025 | NK-AM-1025 isolated LTR | NK-AM-1025 |

Illustrative values are **not** final API field/schema contracts. In JS, represent values beyond Number.MAX_SAFE_INTEGER with compatible exact integer/decimal strings or other reviewed safe representation before formatting. Select wire representation in Feature OpenAPI; never silently downcast **BIGINT** to imprecise Number. Apply Intl/locale libraries for display **without first losing precision**.

### FBR-02-06 — Exact Public Money Representation (approved 2026-11-22)

Final financial Money uses PostgreSQL BIGINT minor units with its currency exponent, and API **decimal-string minor-unit values plus currency context**. Only parse validated canonical values into precise decimal/integer math; never use browser Number for amounts outside exact safe range or depend on localized separators to prove a financial amount. Display formatting must not change the authoritative amount.

### 6.2 Numeric input parsing and normalization

| User entry class | Rule |
| --- | --- |
| ASCII digits, e.g. 1500.50 | Accept only under field-specific numeric grammar |
| Arabic-Indic digits, e.g. ١٥٠٠٫٥٠ | Normalize digits/separators using explicit safe mapping where approved |
| Grouped numerals, e.g. 1,500.50 or ١٬٥٠٠٫٥٠ | Accept only with unambiguous, validated grouping/decimal rules |
| Mixed contradictory separators | **Reject** rather than guess |
| Invalid suffix or injection, e.g. 1500abc | **Reject** with localized safe error |
| Unsupported precision, overflow, negative where forbidden | **Reject** with field-specific validation |
| Quantity/percentage | Respect owning Feature's UOM/scale/range and Decimal precision; not necessarily integer |
| Phone / barcode / SKU / postal code | **String identifiers**, never treated as Money/Quantity or normalized by arithmetic |

The browser can provide friendly immediate validation, but the **owning Laravel Feature is the authoritative validator**. Normalize once using a well-tested input grammar, not by stripping every dot/comma. Never parse a formatted display string back into financial authority without validation.

### 6.3 Canonical time and calendar date

| Category | Authoritative behavior |
| --- | --- |
| Real event instant | PostgreSQL TIMESTAMPTZ in UTC; serialize an unambiguous instant/offset by reviewed API convention |
| Calendar-only date | PostgreSQL DATE; do not incorrectly shift a DATE by timezone |
| Displayed timestamp | Convert the actual instant into the appropriate trusted presentation timezone, then localize visible text |
| Merchant-local operational day | Derive from verified Merchant Timezone and the relevant approved business event |
| Browser timezone | Presentation context if separately approved; **not** authority for Merchant financial/refund day |
| Language/locale | Formats month names/order/digits only; does not modify actual instant, duration or business state |

Use a reliable **IANA timezone identifier**, e.g. Africa/Cairo for a store that has been approved with that zone, rather than assuming a fixed UTC+02 offset. Some regional offsets/DST rules change; a local calendar day is not universally 24 hours. Exact store timezone selection/change governance is **OPEN** beyond current approved business rules and belongs to Merchant Settings/Finance Feature Spec.

### FBR-02-11 — Financial Merchant Timezone Governance

An authorized Merchant may select a valid IANA financial timezone **before** finance-impacting activity starts. After the first relevant financial activity, the timezone becomes **locked for ordinary Merchant Settings changes**. Any exceptional correction requires independently authorized review, reason/audit, and explicit assessment of historical financial day limits, reports, refunds and regulatory records; never silently reinterpret past financial events. UTC/TIMESTAMPTZ remain authoritative instants; business-day boundaries are derived using the trusted IANA zone and date-specific DST rules, not browser timezone or a fixed offset. Exact configuration workflow, legal exceptions and UI belong to Merchant Settings/Finance Features.

### 6.4 Date filters, reporting and refund-day rules

For a Tenant-local day filter over TIMESTAMPTZ:

1. Validate a calendar **DATE** range and trusted Merchant Timezone, independent of current UI locale.
2. Compute the **local beginning of the selected date** and **local beginning of the next date**, each under the timezone rules.
3. Convert both instants to UTC.
4. Query **start inclusive, end exclusive** using an index-friendly timestamp range.
5. Test near midnight and applicable DST transitions; do not use a hardcoded 23:59:59.999 boundary or assume a 24-hour day.

Approved **D-129/D-130** define the optional daily Refund authorization day in **Merchant-local calendar time**, attributed to **final financial authorization**, not arbitrary UI rendering or provider payout timing. Preserve those rules, including subsequent D-series refinements, without defining new limits or statuses here. A displayed date string must never be used as the sole evidence of approved/refunded money.

### 6.5 Date/number output and export semantics

- Use Intl.NumberFormat / Intl.DateTimeFormat or equivalent verified server-side formatting for **visible text**, with explicit locale/timezone/currency inputs.
- API should preserve canonical exact quantities, currency-aware Money and timezone-aware timestamps per approved OpenAPI, not infer parser behavior from formatted prose.
- Human-readable exports/PDFs can localize labels/formatting, while machine-readable CSV/Excel contracts keep usable exact values, date types or explicit ISO data without misleading locale-driven reinterpretation.
- Guard user-authored CSV/Excel cells against formula injection and ensure Tenant/Location permissions still apply.
- Keep created-at, occurred-at and recorded-at semantics distinct only where approved by owning Feature.
- Test large but supported Money, boundary rounding, negative/zero cases, date-only input, mixed digits and currency formatting across ar/en.

---

## 7. LOC-ST-05 — Translation Catalogs, Ownership and Validation

### 7.1 Key design and namespaces

Use readable **namespaced translation keys** grouped by owning Feature/Module and common UI semantics; examples below are **illustrative**, not mandatory runtime keys:

| Owner | Illustrative key | Semantics |
| --- | --- | --- |
| Next.js common UI | common.actions.save | Save button |
| Next.js Orders UI | orders.actions.confirm | Confirm button |
| Next.js Customers UI | customers.fields.email | Accessible form label |
| Laravel validation | validation.required | Required field |
| Laravel auth | auth.failed | Authentication error |
| Laravel Orders domain | orders.errors.invalidState | Stable conflict meaning |
| Notifications | logical template ID + schema/version | Approved system email/in-app text |

Recommended conceptual Laravel files:

~~~text
lang/
  ar/
    validation.php
    auth.php
    common.php
    orders.php
  en/
    validation.php
    auth.php
    common.php
    orders.php
~~~

A Module may load its own translation resources through reviewed Laravel conventions; this illustration does not force a monolithic global catalog or precise final module filesystem layout. Next.js translation namespaces/files are selected in the independent frontend repository with compatible route/build/SSR behavior.

### 7.2 Translation source-of-truth and duplication rules

- Laravel owns server-created validation/business/security human messages; Next.js displays those messages when provided in the approved API response.
- Next.js owns frontend-static labels, local confirmations, placeholders and explanatory UI.
- The same domain meaning must be consistent between translated UI status labels, owning server errors and actual business operations; they need not share a runtime translation store.
- For stable business behavior, the frontend relies on **errorCode, HTTP status and documented DTO/Enum value**, **never** string matching messages in Arabic/English.
- Protect public translation keys from becoming an arbitrary client-controlled lookup into private internal error messages. Do not translate internal stack traces, SQL/secret values or protected Tenant resource existence into client-visible text.
- Use placeholder substitution, not hand-built concatenation of unescaped user text; review plural forms and interpolation where a UI message actually requires them.
- Keep translated content accurate for business states: **refund requested**, **approved**, and **actually executed** have distinct factual meanings.

### 7.3 API contract invariants

The existing API Standards remain authoritative:

| HTTP/API element | Locale-dependent? | Rule |
| --- | --- | --- |
| HTTP status | **No** | 200/201/204/401/403/404/409/422/429/500 etc. depend on actual outcome |
| errorCode | **No** | Stable machine-readable code |
| camelCase field names and nested validation paths | **No** | Same contract across ar/en |
| Resource data IDs/Enums/business states | **No** | Owning Feature decides canonical values |
| Success/error message text | **Yes** | Validated request locale and safe translation |
| Field validation message text | **Yes** | Laravel Form Request/language catalog |
| Money minor units, currency code and instants | **No** | Exact canonical representation |
| Metadata/count/authorized resources | **No** | Tenant/AuthZ scope unaffected |

Preserve approved **App\Helpers\ApiResponse** and **App\Enums\HttpStatusCode** direction with a central exception handler and Form Requests; do not add an alternative JSON response framework. 204 has **no body** and file/downloads are not forced into a JSON envelope. X-Request-ID is a technical header, not a translated business identifier.

Illustrative equivalent errors:

~~~json
{
  "success": false,
  "message": "يرجى مراجعة البيانات المدخلة",
  "errorCode": "VALIDATION_FAILED",
  "errors": {"customerName": ["اسم العميل مطلوب"]}
}
~~~

~~~json
{
  "success": false,
  "message": "Please check the submitted data",
  "errorCode": "VALIDATION_FAILED",
  "errors": {"customerName": ["Customer name is required"]}
}
~~~

The example does not approve a final Customer schema; only the **stable key/code/HTTP meaning** versus translated messages.

### 7.4 File and key quality gates

- Translation keys required for affected feature screens/forms/flows must exist for **both ar and en**.
- Parameter/placeholder counts and names must match intended meaning and type across languages; detect missing/extra variables.
- Detect duplicate or conflicting keys, syntax/parse failures and unsafe raw content.
- Record outdated/unused keys for controlled cleanup; do not delete keys still consumed by older compatible release.
- Missing key fallback is logged safely in Development/testing and repaired; a missing critical security/business/checkout/refund translation is not dismissed as cosmetic.
- Preview actual truncation, plurals, currency, mixed text, accessibility labels and mobile layouts; source-file parity alone is not proof of correct user meaning.
- Internal translation text cannot become a bypass for access control or conceal business failure under a success message.

---

## 8. LOC-ST-02/05 — API, Next.js and Preference Contract Details

### 8.1 The browser/API handshake

~~~text
Verified Storefront/Merchant/Platform request
  -> Resolve trusted Tenant/Identity first
  -> Resolve ar/en UI locale from explicit or saved choice, then browser, then ar
  -> Next.js SSR and client hydrate consistently with correct lang/dir
  -> Browser/SSR requests Laravel API with validated Accept-Language
  -> Laravel initializes request-locale scope and approved Tenant/AuthZ
  -> Central Exception Handler and Resources return stable contract + localized text
  -> Clear mutable request context before serving next scope
~~~

**Important ordering nuance:** Tenant/Identity authorization does **not** come from locale selection. Locale preference may be read after the appropriate current identity/scope is known; no locale cookie acts as a Tenant selector or authentication proof.

### 8.2 Header, state and route safety

- Prefer a single explicit language switch per application surface with clear persistence/reset semantics.
- Validate supported ar/en inputs; never interpolate arbitrary locale strings into filesystem paths, SQL statements, template IDs, class names or modules.
- A nonsecret cookie may be a convenient preference; correctly scope Domain/Path/SameSite and secure attributes based on actual authenticated routes and Tenant host policy. It must not alter session/CSRF security or leak another Tenant's private state.
- API caching and Next.js SSR cache keys must consider locale as relevant **only after** the relevant Tenant/session boundary; never share private translated responses between Merchants or users.
- For role-restricted UI, translated labels do not grant menu or action permissions. Laravel remains authoritative even if an older translated label/button is visible.
- Do not make Locale part of HTTP Idempotency-Key, authorization, Order identity, or Unique business effect unless a very specific Feature contract explicitly requires it and is reviewed.
- Locale changes cannot reset a pending Checkout, grant a discount, trigger refund, mark an In-App alert read or rerun business transitions.

### 8.3 Dynamic data vs static copy

- App-owned labels are translated according to current UI language.
- Merchant-authored Product/Category/Brand/Page text is returned as one authoritative value and shown unchanged except correct direction/escaping.
- User-entered prices and dates are normalized/validated to canonical data before authoritative handling, not stored as localized display strings.
- Do not add automatic machine translation, translated search synonyms, duplicated per-language entity API fields or unseen content fallback layers in first MVP.

---

## 9. LOC-ST-06 — Transactional Emails, In-App and Horizon Localization

### 9.1 Source event and message lifecycle

Integration Standards control delivery, trust and idempotency. This document controls only the **language aspect** of approved notifications:

~~~text
Owning Business Module commits genuine business fact + Outbox in its own DB
  -> Durable relay / Redis / Horizon
  -> Notifications Module verifies recipient/channel and selects eligible locale
  -> Create/find stable Tenant-owned logical message intent
  -> Persist selected locale and compatible template identity/version
  -> Narrow platform Email Provider adapter delivers outside commerce transaction
  -> Verified provider evidence updates message delivery status only
~~~

Only approved source Events may trigger a message. D-201 is OPEN and is **not** grounds to introduce automatic alternative-location customer emails. A language choice cannot convert a Pending Stock Order into confirmed, a Refund approval into executed money, or provider acceptance into Delivered.

### 9.2 Recipient-specific email locale policy

| Condition | Correct policy |
| --- | --- |
| Recipient has known approved ar/en preference | Prefer recipient locale, subject to governing Feature rule |
| Unknown recipient preference | **Feature-specific approved fallback precedence**, not silently the staff sender's current UI locale |
| Unsupported recipient preference | Apply the Feature's reviewed supported-language fallback |
| System-owned template | Match selected locale and approved template version |
| Merchants' products appear in email | Render original one-language authored product strings with safe bidi isolation |
| Retry same logical message | **Reuse its selected locale/template identity/version** |
| Authorized new business message or explicit resend | New distinct approved intent may select current applicable recipient policy |
| Provider callback/delivery state | Machine-readable stable outcome, not a translated status as authority |

The general application fallback **ar** approved in LOC-ST-02 applies to interactive UI/API errors. The **email recipient absence-of-preference resolution remains Feature-specific**, as expressly stated by INT-03 and INT-ST-06. If the relevant Feature chooses ar for a particular unknown-recipient class, document that choice there.

### 9.3 Template safety and version compatibility

- System-managed ar RTL and en LTR email templates, controlled subjects and HTML/plain text alternatives where appropriate.
- Safe allowlisted variables with required type, escaping, trusted store URLs/images and independent current resource authorization at link open time.
- Template ID/version/selected locale recorded with the logical message; historical evidence of sent communications is not retroactively rewritten by changing translation files.
- Queued messages and Blue-Green overlap must preserve compatible template versions or block unsafe release.
- Do not store full rendered bodies indefinitely or create per-Tenant global plaintext archives without approved security/retention/Feature purpose.
- Email sender/DNS/Provider/consent settings remain Integration/Legal matters; Localization does not select a provider or authorize live sending.

### 9.4 In-App render-time localization

| Information | Durable role |
| --- | --- |
| Notification Event/Type or stable template key/version | Remains stable; no locale-dependent business identity |
| Owning Tenant/recipient/resource references | Current authorized scope |
| Minimal reviewed safe parameters | Enough to render without leaking protected data |
| Read/unread per recipient | Remains independent of language |
| Visible title/body | Render in **current** staff interface locale when requested |
| Business Resolved state | **Separate** owning Feature action; read ≠ resolved |

When a Merchant employee switches from English to Arabic, eligible previously stored In-App notifications may render in Arabic without duplicate per-language DB rows. Verify current Central membership, Tenant Role/Location and resource permissions for list/count/read/open; old notifications are not permanent authorization. Retained template versions/params must continue to render during compatible releases. Central-only platform alerts retain Central authority, not Merchant data access.

### 9.5 Long-running job and Tenant locale hygiene

For every Horizon attempt:

1. Resolve verified Central/Tenant scope, trusted job identity and relevant current authority under QJ-02.
2. Load a durable intended locale or resolve current allowed recipient policy according to message stage; **do not borrow prior request/Job App Locale**.
3. Apply locale inside the scoped execution lifetime; use an explicit locale context or verified framework mechanism.
4. Perform locale-specific rendering only after verifying correct event/message/recipient and permission scope.
5. Keep stable Event, logical message identity, selected template version and approved retry semantics.
6. In finally/error/retry paths, clear mutable **Tenant, Locale, Actor, Location, DB and cache** execution state.
7. A killed process cannot rely on finally; the next attempt/worker **must reinitialize cleanly** and recover durable work safely.

Mandatory test: **Tenant A ar → Tenant B en → Tenant A en → Central ar**, including failure/retry and wrong-tenant rejection. Do not introduce long-lived static Locale variables or global Singleton context. A user-initiated delayed export revalidates the person's **current authorization**; a system-owned post-commit email does not fail solely because its original staff actor later lost membership, subject to approved Business/Privacy constraints.

---

## 10. LOC-ST-03/04/06 — Isolation, Privacy, Security and Operational Controls

### 10.1 Threat and privacy matrix

| Threat | Mandatory safeguard |
| --- | --- |
| Tenant A's locale/copy/details leaking into Tenant B | Trustworthy Tenant context, request/worker cleanup, correctly scoped caches and current resource checks |
| Shared browser cookie alters active Tenant or identity | Cookie is preference only, scoped; never Tenant/auth authority |
| Forged Accept-Language leads to path or template injection | Allowlisted ar/en with bounded safe parsing and reviewed interpolation |
| Untrusted Merchant HTML/URL appears in email/UI | Contextual escaping, trusted links, safe asset authorization and Security controls |
| Translation parameter exposes private ID/email/order | Minimize and reauthorize data; privacy-safe logs and cache |
| Error wording reveals another Tenant's protected record | Preserve safe 404/403 semantics; never leak existence via language |
| Missing translation reveals internal enum/source key/SQL | Safe fallback and diagnostics without raw secrets |
| Bad numeric normalization changes amount | Reject ambiguity; authoritative decimal validation; financial regression tests |
| Wrong timezone changes daily financial authority | Trusted Merchant timezone, date-boundary tests, never client clock for business day |
| Locale changes trigger duplicate send/business action | Stable Event/Intent/Idempotency identity independent of translated text |
| Source-controlled translations edited without review | Version control, CODEOWNERS/review as configured, test/check evidence |
| CSV/Excel export of Merchant-authored text | Formula-injection protections and authorization checks |

### 10.2 Least privilege, privacy and diagnostics

- Language preference is not proof of legal consent, customer marketing approval, staff Role/Location access, current Merchant membership or Tenant owner.
- Do not log full customer correspondence, free-form Merchant HTML, translation placeholders carrying personal data, private locale cookies or session tokens.
- If diagnostics need a language field, restrict to safe ar/en and technical correlation, without unbounded high-cardinality user data.
- Do not expose private API/notification information by rendering translated preview text before authorization.
- Required Merchant policies and lawful notices are separately reviewed for applicable Egypt market/channel; one-language merchant content in MVP does not waive G7.
- Exports are separately approved outputs, and language changes do not bypass current resource/Location checks or rewrite legal/financial snapshots.

### 10.3 Monitoring and operations

Track safe missing-key errors, failed language-negotiation cases, locale/render exceptions, template version compatibility, unexpected auth/404 differences, relevant RTL failures in testing and first-Pilot support complaints. Set alert thresholds based on observed risk rather than invented global numbers. On an incident, use the already approved LOG/SEC runbooks, preserve credible evidence, and verify real business state independently from the translated wording.

---

## 11. LOC-ST-07 — Risk-Based Test Layers and Acceptance Matrix

### 11.1 Strategy

Use source-file checks and Unit tests for keys/formatting, Laravel API/Feature tests for localization and error contracts, Next.js Component/Browser E2E for actual RTL/accessibility, real PostgreSQL tests for Money/Timezone behavior and Central/Tenant A/B tests for long-running job isolation.

- **Do not require expensive full browser/load suites on every typographical change.**
- Changes to shared locale resolution, tenant context, money/time formatting, API exceptions, email templates or global RTL layout require broader tests proportionate to potential impact.
- A real cross-Tenant/race bug is not proven fixed by mock-only Unit tests.
- Use sanitized isolated test data, explicit stable clocks, supported timezones and reproducible browser/test-runner versions.
- Human bilingual business review validates semantic correctness in addition to automated placeholder parity; success of a parser alone does not prove correct Arabic/English meaning.

### 11.2 Consolidated acceptance matrix (LOC-T01…LOC-T48)

| ID | Scenario | Required evidence/behavior | Layer |
| --- | --- | --- | --- |
| **LOC-T01** | Default app locale no preference | ar selected without Tenant/identity changes | Unit/API/browser |
| **LOC-T02** | Explicit English UI selection | en shown consistently across page, navigation and API human messages | Browser/API |
| **LOC-T03** | Saved actor-specific preference | Correct same actor/surface restore; no other-user leakage | Browser/integration |
| **LOC-T04** | Supported browser language | Correct ar/en negotiation and SSR/hydration | Browser |
| **LOC-T05** | ar-EG / en-US variants | Normalized to ar/en without implicit currency/timezone change | Unit/API |
| **LOC-T06** | Weighted Accept-Language, including q=0 | Supported permitted preference selected accurately | API |
| **LOC-T07** | Unsupported/malformed/absent locale | Safe ar fallback and bounded handling | API/security |
| **LOC-T08** | Missing English key | Arabic fallback and safe Development diagnostic | Translation unit |
| **LOC-T09** | Missing key in both languages | Safe message, flagged critical defect if material | Translation/UX |
| **LOC-T10** | Placeholder mismatch or duplicate key | Check reports correct failure on affected feature | Static check |
| **LOC-T11** | Arabic/English key coverage on enabled page | Relevant labels and accessible names complete | Static/component |
| **LOC-T12** | Arabic RTL document/navbar/form | Correct lang/dir, placement, keyboard reading order | Browser |
| **LOC-T13** | English LTR document/navbar/form | Correct lang/dir and accessible behavior | Browser |
| **LOC-T14** | RTL modals/dropdowns/tooltips/tables | No broken layout, focus trap, column order or overflow | Browser |
| **LOC-T15** | Mobile/tablet/desktop RTL responsive | Usable content, no critical clipping/hidden actions | Browser |
| **LOC-T16** | Product Arabic/English mixed name in opposite UI locale | Original single authored text preserved and directionally isolated | Browser |
| **LOC-T17** | SKU/barcode/order ref/email/phone in RTL | Correct canonical sequence, plus sign/leading zeros preserved | Unit/browser |
| **LOC-T18** | Mixed-script punctuation/negative money | No misleading bidi reordering of meaning | Browser |
| **LOC-T19** | Screen reader/form error and dialog focus | Correct labels, error association, keyboard focus and language | Accessibility |
| **LOC-T20** | Different ar/en visual status labels | Same canonical owning-Module state and permissions | API/domain |
| **LOC-T21** | 422 invalid form ar/en | Same HTTP 422, errorCode, camelCase paths; translated field text | Laravel API |
| **LOC-T22** | 401/403/404/409/429/500 ar/en | Same correct status/errorCode/privacy; only text varies | Laravel API |
| **LOC-T23** | 204/no body and file download by language | No invalid JSON wrapper or altered download authority | API |
| **LOC-T24** | Malicious interpolation parameter | No XSS/template injection; correct escaping | Security |
| **LOC-T25** | 1,500 EGP numeric presentation ar/en | Same exact minor-unit/currency value | Money/unit |
| **LOC-T26** | Large but supported BIGINT into JS | No precision loss through API/formatting | Money/contract |
| **LOC-T27** | Discount/tax/rounding tiny values | Same approved precise decimal business result in ar/en | Domain/money |
| **LOC-T28** | Arabic-Indic digits and separators | Correct normalization for supported field grammar | Unit/API |
| **LOC-T29** | Mixed/ambiguous numeric separators | Reject rather than silently reinterpret | Validation |
| **LOC-T30** | Quantity fractional UOM precision | Respect owning Feature UOM/scale, not Money rules | Domain/validation |
| **LOC-T31** | Phone, SKU, postal code with leading zero | Preserve exact string identity | Unit/API |
| **LOC-T32** | UTC instant displayed in merchant timezone | Correct instant and intended human date/time | Unit/browser |
| **LOC-T33** | DATE-only attribute | No timezone-based accidental day shift | Unit/API |
| **LOC-T34** | Merchant-local day report boundaries | Correct inclusive/exclusive UTC range under IANA zone | Real PostgreSQL |
| **LOC-T35** | DST offset change at a tested timezone | No fixed 24-hour day assumption or missing records | Time/unit |
| **LOC-T36** | Refund daily approval threshold near midnight | Apply D-129/D-130 trusted merchant-local day | Finance/integration |
| **LOC-T37** | Browser timezone differs from Merchant timezone | Browser does not redefine financial day | Security/domain |
| **LOC-T38** | CSV/Excel export localized labels and original data | Safe numeric/date semantics, formula-injection protection | Export |
| **LOC-T39** | Approved Arabic transactional email | Correct ar RTL subject/body, actual business wording | Email/contract |
| **LOC-T40** | Approved English transactional email | Correct en LTR subject/body, original Merchant product text | Email/contract |
| **LOC-T41** | Unknown recipient email language | Feature-approved fallback applied; not staff creator locale by accident | Feature/integration |
| **LOC-T42** | Email retry and later preference change | Original logical message locale/template version persists | Queue/integration |
| **LOC-T43** | Older queued template during Blue-Green | Compatible render or explicit release blocker | Release/contract |
| **LOC-T44** | Tenant-local In-App language switched | Current UI locale renders eligible alert, no read-state mutation | API/browser |
| **LOC-T45** | In-App user loses membership/Location | No unauthorized count, title, preview, read or open in either locale | AuthZ/real tenancy |
| **LOC-T46** | Horizon A ar → B en → A en → Central ar | No Tenant/locale/actor/DB context leakage | Real worker |
| **LOC-T47** | Failed/missing-Tenant queue job then retry | Fresh scope and locale; fail closed; no previous-Tenant fallback | Real worker/fault |
| **LOC-T48** | Shared frontend/backend locale change before release | Required tests executed, evidence recorded; critical failures block | CI/release |

**LOC-T01…48 are acceptance scenarios, not executed tests or final route/table definitions.** Actual Feature specs select their relevant scenarios and add domain-specific cases. Automated screenshot diff alone cannot prove semantics, keyboard accessibility or financial correctness.

### 11.3 Quality and human acceptance

- Translation review verifies correct commercial truth (Pending ≠ Confirmed, Refund Approved ≠ Executed), legal wording, missing-email exceptions and safe operational action descriptions.
- Accessibility: correct lang/dir, labels, alt text where applicable, keyboard/focus order, visibility, focus re-entry and screen-reader presentation; exact target guideline and browser/device matrix remain Feature/Release-owned.
- Use controlled test addresses, non-Production queues/providers and safe screenshots/logs; no live Merchant data in Development.
- Document actual tester/reviewer, source SHA, environment, commands, selected cases, results, failed/skipped tests and follow-up.
- Critical auth/Tenant/commerce/financial issues must block affected merge/release; do not record a skipped critical test as Passed.

---

## 12. LOC-ST-07 — CI/Release Gates and BA-30 Linkage

### 12.1 Progressive required checks

| Changed scope | Minimum relevant verification |
| --- | --- |
| ar/en translation file or namespaced keys | Syntax, key coverage, placeholders, relevant UI/message check |
| Laravel Form Requests/error mapping | HTTP status/errorCode/field path stable across ar/en, localized text |
| Next.js component/UI surface | Component ar/en and RTL/LTR behavior; targeted browser where meaningful |
| Shared layout or CSS direction logic | Broad representative Storefront/Merchant/Platform RTL responsive tests |
| Monetary parser/formatter | Exact Money, large values, Arabic digits, invalid ambiguity, price/rounding regression |
| Date/time business-day code | UTC/DATE, IANA/DST, merchant-day Refund/Report regression |
| Notification/email template/locale | Recipient policy, safe variables, correct template version, mixed content |
| Outbox/Horizon locale context | Real Tenant A/B/Central reused workers, failure/retry, no context leakage |
| API wire/locale behavior | OpenAPI compatibility and frontend consumer checks |
| Documentation-only edit | Link/scope/decision consistency checks; no fabricated runtime test pass |
| Shared Core / Release | Broaden relevant tests for risk, Blue-Green template/event compatibility and actual release evidence |

Do not invent blanket 90% coverage, universal visual screenshot baseline, arbitrary Accessibility score, paid test service or numeric load threshold as requirements without a separately approved Testing/Release decision.

### 12.2 Critical blockers

| Proven failure | Release rule |
| --- | --- |
| Locale changes amount/rounding, paid/confirmed/stock/refund financial state, business day | **BLOCK** affected merge/release |
| Translated error/status implies actual business effect that did not happen | **BLOCK** material affected flow |
| Tenant/Location permissions or private notifications exposed through locale/caching | **BLOCK** |
| Input direction/normalization corrupts canonical SKU/phone/order identity | **BLOCK** affected critical flow |
| Locale-specific injection, XSS, privacy leak or secret/error detail disclosure | **BLOCK** |
| Incompatible old/new template/event with queued messages during release overlap | **BLOCK** until safe plan |
| Required critical real test failed, absent or skipped | **BLOCK / UNVERIFIED** |
| Minor cosmetic spacing, noncritical unused key | Risk-prioritized fix; not automatically global release blocker |
| Missing translation in a critical user/checkout/Refund/legal workflow | **BLOCK** until truthful accessible user text exists |

### 12.3 BA-30 gate trace

| Gate | Localization-relevant requirement | Status from this document |
| --- | --- | --- |
| **G1 — Tenant safety** | No cross-Tenant locale/render/cached private notification leak | **NOT VERIFIED** |
| **G2 — Commerce integrity** | Money, quantity, Order/Refund state and Merchant business day unchanged by language | **NOT VERIFIED** |
| **G3 — Backup & restore** | Approved BA-16 off-VPS backup and tested full/individual-Tenant restore/RPO/RTO | **BLOCKED — BA-16 DEFERRED** |
| **G4 — Release safety** | Old/new locale/template contracts compatible, tested browser/API and workers | **NOT VERIFIED** |
| **G5 — Capacity** | Measured SSR/cache/formatting/polling effects where consequential | **PENDING MEASUREMENT** |
| **G6 — Security/privacy** | Escaping, header parsing, safe error strings, permission-scoped messaging | **NOT VERIFIED** |
| **G7 — Legal readiness** | Egypt merchant/channel-specific actual language, document, privacy/consumer obligations met despite content translation deferral | **LEGAL REVIEW REQUIRED** |
| **G8 — Operational completeness** | ar/en workflows, accessible forms, accurate notification/customer messages | **PENDING FEATURE ACCEPTANCE** |
| **G9 — Environments** | Development/Production data, test sending, releases and locale assets isolated | **NOT VERIFIED** |
| **G10 — Sign-off** | Named owner, real tests, reviewer, stop/recovery plan and live Pilot approval | **NOT GRANTED** |

**No gate is passed merely by writing this Standard.** G3 remains blocked until BA-16 is explicitly approved and verified; G7 cannot be waived by saying Merchant content is not localized.

---

## 13. LOC-ST-08 — Developer, AI Agent and Reviewer Checklists

### 13.1 Before a Feature Spec

- [ ] Identify the existing approved D/OV business capability and correct owning Module.
- [ ] Classify text as platform-owned UI, server-owned message, system notification/email or Merchant-owned authored data.
- [ ] Confirm the reduced MVP scope: **no per-Entity multilingual Product/Category/Brand/Page fields/tables**.
- [ ] For pages such as About/Contact/FAQ and Merchant policies, retain approved feature, but specify single authored content and legal/publishing review.
- [ ] Identify ar/en application surfaces and current actor/guest/recipient with trusted Tenant identity.
- [ ] Document any Feature-specific email recipient-language fallback, template variables and versioning.
- [ ] Define public errorCode, canonical state, no-locale business effects and response shape.
- [ ] Define safe exact monetary/numeric UOM/date/time input grammar and timezone authority.
- [ ] Classify privacy/authorization/Location scope for any text, status or In-App preview.
- [ ] Select targeted LOC-T cases and applicable TEST/SEC/API/INT cases.
- [ ] Identify whether the existing OV-19/BA/MA wording conflicts with scope and escalate to coordinated documentation update.

### 13.2 During implementation and PR

- [ ] UI/labels use namespaced source-controlled translations; no scattered permanent hard-coded ar/en copy.
- [ ] Laravel uses native Form Request validation/Exception mapping; stable errorCode/status and camelCase JSON.
- [ ] No logic branches on translated status text; Business/Enum/Permission stay stable.
- [ ] ar/en locale selection matches explicit preference/browser/fallback and SSR/client/API agreement.
- [ ] Tenant/actor/session/cookie/cache scoping is valid and does not confer authorization.
- [ ] HTML document lang/dir and bidirectional isolation work with mixed Merchant content, SKU and phone.
- [ ] Money uses exact integer minor units and decimal calculation; no unsafe JS Number conversion.
- [ ] Numeric normalization rejects ambiguous separators and respects quantity/UOM versus identifiers.
- [ ] UTC instants, DATE, IANA timezone and Merchant-local day logic tested at midnight/DST boundaries.
- [ ] Translation interpolation escaped and safe; Merchant HTML/URLs never injected unreviewed.
- [ ] Sender template selected locale/version retained for a logical email retry; In-App renders current eligible locale without business mutation.
- [ ] Horizon reused worker starts/cleans Locale/Tenant/Actor context, with wrong-tenant/failure tests.
- [ ] Exports/PDFs preserve canonical Money/date and authorized private data; formula-injection mitigations.
- [ ] Missing keys/placeholders, key parity, critical UX, accessibility and wrong-language error cases tested.
- [ ] Record executed commands/fixtures/environment/SHA/results; do not assert green if required tests not run.
- [ ] Any conflict with already published OV/BA/MA wording explicitly documented, not silently redefined.

### 13.3 Before authorized live Merchant activation

- [ ] Document release-scope content and **actual** Egyptian consumer/legal-language obligations for each selected Merchant/channel.
- [ ] Verify bilingual system UI, customer emails, relevant notices and all enabled business journeys.
- [ ] Verify no missing critical translations, security leaks, wrong monetary/date values or unsupported locale paths.
- [ ] Validate representative device/browser, keyboard/accessibility and mixed Arabic/English product content.
- [ ] Verify Email Provider/Template/Queues as required by INT standards, and test actual isolation.
- [ ] Have explicit named BA-30 gate owners/evidence, including **approved BA-16 with tested off-VPS restore**.
- [ ] Obtain independent release approval; publishing this Markdown is not approval to deploy or serve real customers.

---

## 14. Deferred Capabilities, Open Implementation Details and Owners

| Topic | Status now | Future owner / next action |
| --- | --- | --- |
| Product/Variant translations, descriptions by locale | **DEFERRED** | Catalog business approval + data/API migration spec |
| Category/Brand/other Merchant Entity translations | **DEFERRED** | Owning Modules + product scope review |
| Multi-language About/FAQ/Contact/basic page and policies | **DEFERRED as generic feature** | Merchant Settings & Content; preserve single-language pages; legal exceptions under G7 |
| Translation DB table/JSON object or dynamic translation runtime | **NOT SELECTED** | Separate future architecture if actual need |
| Automatic/AI translation, third-party CMS | **NOT SELECTED** | Future product/legal/privacy review |
| Additional system locales | **NOT IN FIRST MVP** | Later market/product decision |
| Next.js i18n library and route structure | **TO SPECIFY** | Frontend Architecture/Foundation, actual compatibility |
| Translation file placement per backend Module | **TO SPECIFY** | Laravel Foundation, PSR/Laravel module loading |
| Locale preference storage and cookie/route sync | **TO SPECIFY** | Authentication/UI Feature, secure SSR/cache design |
| Arabic numeral glyph choice per page/field | **TO SPECIFY AS DISPLAY DETAIL** | Design System/UI, not business representation |
| Exact accepted numeric separators per Form input | **TO SPECIFY** | Feature DTO/Input validation + QA |
| Money wire precision/schema at large values | **TO SPECIFY BY OPENAPI** | Money/API Feature, avoiding JS Number loss |
| Merchant timezone selection/change policy | **FBR-02-11 APPROVED:** normal change locked after financial activity; exceptional audited correction workflow and UI remain Merchant Settings/Finance Feature-owned. Preserve D-129/D-130. |
| Email recipient unknown-language fallback matrix | **FEATURE-OWNED** | Notifications/Orders etc.; do not presume Arabic in every case |
| Template history/rendered snapshot retention | **TO SPECIFY WITH PRIVACY REVIEW** | Notifications/SEC |
| Translation key registry, i18n extractor/checker | **TO IMPLEMENT/VERIFY** | Backend/Frontend Foundation |
| Accessibility target and browser/device matrix | **TO SPECIFY** | Frontend QA / Release owners |
| Test/CI tools and actually passing results | **NOT IMPLEMENTED BY THIS FILE** | Foundation/Testing pipeline |
| Future unified OV-19/BA/MA source wording | **REQUIRES SEPARATE DOC UPDATE** | Final Backend Architecture & Standards Review |
| Production and live Pilot configuration | **NOT PROVISIONED / NOT AUTHORIZED** | Release/Ops + BA-30 |
| BA-16 Backup & Restore | **DEFERRED** | Separate explicit architecture approval and recovery drill |
| BA-30 G3 | **BLOCKED** | Proven off-VPS recovery for Central/Tenant/files, RPO/RTO |
| D-201 | **OPEN** | Orders/Fulfillment; no new localization-triggered message decision |

No unknown implementation detail authorizes extra entities/translations, exchange rates, local payment/courier integration, a generalized Translation Engine or undocumented business-rule changes.

---

## 15. LOC-ST-08 — Final Cross-Document Reconciliation, Approval and Publication

### 15.1 Compatibility review

| Existing baseline | Result of this document |
| --- | --- |
| **OV-08 B** — Arabic/English Storefront, Dashboard, emails; EGP | **RETAINED.** System UI/messages/emails bilingual; Merchant EGP-only Pilot, no FX |
| **OV-19 B** — basic bilingual content pages | **EXPLICIT SCOPE AMENDMENT:** keep basic authored pages, menu/footer/settings and no Page Builder; **defer translated merchant-authored page bodies** |
| Project Overview §5.1/§7/OV register, source text | **NOT YET SYNCHRONIZED.** Older bilingual-content prose remains; separate owner-authorized consistency edit needed |
| Backend Architecture Merchant Settings & Content | **NOT YET SYNCHRONIZED.** Historical bilingual-content line needs alignment with this newly approved scope |
| Module Architecture §7.4 Merchant Settings & Content | **NOT YET SYNCHRONIZED.** Bilingual store-content responsibility must be narrowed for current MVP |
| DA-04 Data Architecture | **COMPATIBLE:** exact BIGINT minor units, UTC instants, DATE and history retained |
| AS-02/03 API Standards | **COMPATIBLE:** camelCase/errorCode/HTTP stable, ar/en messages and fallback now approved |
| CS-05/06/07 Code Standards | **COMPATIBLE:** domain state/Enums and long-lived request/job state independent of locale |
| INT-03/INT-ST-06 Integration | **COMPATIBLE:** system-owned bilingual templates, selected email locale/version, recipient Feature fallback |
| QJ-02 Queues and Tenant Architecture | **COMPATIBLE:** trust and cleanup of per-Job Tenant/Locale contexts |
| Security/Authorization | **COMPATIBLE:** language never grants scope, messages private and safely escaped |
| TEST-07/TEST-09 Testing | **COMPATIBLE:** ar/en browser/contract/integration and risk-based release checks |
| BA-30 Pilot gates | **UNCHANGED:** G3 blocked, other gates require real evidence |

### 15.2 Scope-change governance and remaining conflict

The owner approved **LOC-ST-01 A with reduced Merchant-authored translation scope**, subsequently approved LOC-ST-02 through LOC-ST-07 A, then selected **LOC-ST-08 A with explicit scope reconciliation and publication**. This is a **later intentional MVP scope adjustment**, not an inference from Implementation details.

For traceability:

1. Preserve the **exact prior** OV-19 B wording in historical registers until a separately authorized source-document update; do not pretend that text has already changed.
2. Record that the **current owner instruction** narrows only the bilingual **Merchant-owned contents/entities** portion, not the basic pages themselves, user-facing app localization, system emails, core store settings, theme/footer, or legal compliance.
3. List Project Overview §5 and §7, Backend Architecture merchant content responsibility and Module Architecture §7.4 for the next consolidated Architecture/Standards consistency review.
4. Any approved D-series rule, law or Feature that specifically mandates localized public content must be examined individually, not silently overridden by this general deferral.
5. Do not modify historical decisions, create migrations or assume a Merchant content-language schema from this documentation alone.

**The approved new standard and the old reference prose intentionally coexist temporarily with an explicit delta register. This is NOT a claim that all source files have already been reconciled.**

### 15.3 Publication and implementation boundary

This documentation was authorized for creation **as one new file on development**. It does **not** authorize:

- Laravel or Next.js application code changes, runtime libraries, queues, controllers, routes or UI implementation.
- Database migrations, multilingual product tables, content backfills, tenant schema changes or seeds.
- CI/CD, Coolify, environment variables, server setup, live transactions or email sending.
- Edits to Project Overview, Backend Architecture, Module Architecture or D-series registers without new explicit authorization.
- Merge into main, Production promotion or Pilot onboarding.
- Claims that localization/accessibility, numeric/timezone tests or any BA-30 gate have passed.

**Approved engineering position:** Use framework-native **ar/en UI and system-message translations only**, Arabic default with explicit user choice and deterministic fallback, correct RTL/LTR and bidi isolation of untranslated Merchant content, canonical Money/UTC/timezone representation, stable Laravel API error contracts, durable recipient-specific email locale and version, render-time staff notifications, Tenant-safe long-running worker lifecycle, risk-based real quality gates and an explicit **OV-19 multilingual-content scope delta**.

**Next independently authorized step:** Final Backend Architecture & Standards Review, including a controlled proposed update plan for older bilingual Merchant-content references and unresolved operational/Pilot gates. Any actual implementation follows separately approved Feature Specs and deployment/release authorization.

**Outstanding status: D-201 OPEN · BA-16 DEFERRED · BA-30 G3 BLOCKED · Production NOT PROVISIONED · Localization Tests NOT RUN.**
