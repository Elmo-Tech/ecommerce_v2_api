# E-commerce SaaS V2 — Architecture & Next-Chat Handoff

**تاريخ التسليم:** 18 نوفمبر 2026  
**الإصدار:** 1.0 — Continuation Handoff after CR-08  
**Repository:** `Elmo-Tech/ecommerce_v2_api`  
**GitHub:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Working branch:** `development`؛ `main` مخصص للنسخ المستقرة وفق `docs/WORKFLOW.md`  
**الحالة:** Product/Business Discovery + Approved Architecture Baselines؛ **ليس تنفيذًا مكتملًا**  
**آخر قرار معماري معتمد:** **CR-08 (A) — Caching & Redis Architecture Baseline**  
**نقطة البداية التالية:** **FS-01 — File Ownership & Storage Classification** ضمن مرحلة File Storage, Media & Uploads Architecture  
**تعليمات المستخدم:** MVP-first، قرار واحد في كل مرة، بدون Overengineering، و**لا تكتب أو تعدّل كودًا أو ملفات أو ترفع مستندًا إلا بإذن صريح**. اعتماد Architecture ≠ تنفيذ أو اختبارات ناجحة أو إذن بإطلاق منتج.

> **مهم للشات الجديد:** هذه الوثيقة **أحدث handoff معماري حتى 2026-11-18**. بعض ملفات handoff القديمة تقول «Architecture لم تُعتمد بعد»، لأن كُتبت قبل اعتماد MA/TA/DA/IA/API/TC/QJ/CR؛ هذه العبارة أصبحت **تاريخية** ولا تعبّر عن الوضع الحالي. عند الاختلاف، راجع ملفات الـArchitecture والـD-series المرجعية فعليًا؛ لا تحوّل اقتراحًا إلى قرار معتمد.

---

## 1. المطلوب مباشرةً في المحادثة الجديدة

**ابدأ من FS-01، ولا تعد إلى CR أو Project Kickoff إلا لو طلب المستخدم.** اقرأ المرجع الخاص بالتخزين BA-15 والملفات ذات الصلة، ثم ناقش قرارًا معماريًا واحدًا:

- **FS-01 — File Ownership & Storage Classification:** ما أنواع الملفات في المنصة، وما الـModule المالكة للـMetadata والـLifecycle، وأيها Public Media مقابل Private Documents/Uploads/Exports/Support files، وأين تقع Central مقابل Tenant، وما حدود الوصول.
- اقترح بديلين بسيطين على الأقل مع **A (MVP-first)** كترشيح إذا كان مناسبًا، واشرح المخاطر والأمثلة، واطلب اعتماد القرار قبل FS-02.
- **لا تعتبر أي قرار FS معتمدًا بعد**. BA-15 تحدد اتجاه التخزين فقط، ولا تحسم تفاصيل FS-01…FS-08.
- لا إنشاء كود أو مستندات أو Git commits إلا بطلب صريح. المستخدم طلب بالفعل في المحادثة السابقة رفع ملف CR أولًا، **وتم رفعه والتحقق منه**، ثم إنشاء هذا الـHandoff.

**نص جاهز لبدء الشات:**

> نحن نكمل E-commerce SaaS V2 في `Elmo-Tech/ecommerce_v2_api` على `development`. اقرأ `docs/handoff/ecommerce_saas_architecture_handoff_2026-11-18.md` والمراجع المشار إليها، واعتبر CR-01…CR-08 معتمدة معماريًا ومستند CR مرفوعًا. ابدأ FS-01 — File Ownership & Storage Classification، بعرض خيارات Architecture بنمط MVP-first، قرار واحد في كل مرة، بدون كتابة كود أو رفع ملفات قبل إذني.

---

## 2. أحدث ملفات مرجعية — اقرأها بالترتيب المناسب

**منتج وقرارات الأعمال:**

1. [Project Overview](../00-overview/project-overview.md) — قرارات OV-01…OV-25 الخاصة بالـMVP والمراحل؛ **النص نفسه Draft for Review**.
2. [D-152 onward Decision Register](../decisions/ecommerce_saas_decision_register_D152_onward_2026-11-15.md) — تفاصيل **D-152…D-200 معتمدة**؛ **D-201 OPEN**.
3. [Business Discovery Handoff v5.48](ecommerce_saas_handoff_v5_48_2026-11-16.md) — سياق وتفاصيل D-series الأقدم.
4. [Open Decisions and MVP Gates](../decisions/OPEN_DECISIONS_AND_MVP_GATES.md) — القرار التجاري المفتوح والمتطلبات التي لم تنته.

**ملفات المعمارية في `docs/01-architecture/`:**

1. [Backend Architecture](../01-architecture/backend-architecture.md) — BA-01…BA-30، **29 معتمد + BA-16 مؤجل**؛ BA-15 Local Persistent Storage First؛ BA-30 Pilot Gates.
2. [Module Architecture](../01-architecture/module-architecture.md) — **MA-01…MA-09 Approved**؛ One Writer, Public Contracts, Spec Kit modular features.
3. [Tenancy Architecture](../01-architecture/tenancy-architecture.md) — **TA-01…TA-08 Approved Conditionally**؛ Stancl v3.10.1 يحتاج اختبارات مع Laravel 13.
4. [Data Architecture](../01-architecture/data-architecture.md) — **DA-01…DA-07 Approved**.
5. [Identity & Access Architecture](../01-architecture/identity-access-architecture.md) — **IA-01…IA-09 Approved**؛ Sanctum, Central staff, Tenant customer/roles.
6. [API Architecture](../01-architecture/api-architecture.md) — **API-01…API-08 Approved**.
7. [Transactions & Consistency Architecture](../01-architecture/transaction-consistency-architecture.md) — **TC-01…TC-08 Approved**.
8. [Queues, Jobs & Event Processing Standards](../01-architecture/queue-jobs-event-processing-standards.md) — **QJ-01…QJ-08 Approved**؛ رفع في 17 نوفمبر.
9. [Caching & Redis Architecture](../01-architecture/caching-redis-architecture.md) — **CR-01…CR-08 Approved**؛ **رُفع في 18 نوفمبر 2026 وتمت قراءة محتواه من GitHub بعد الرفع للتحقق**.

ملف [NEXT_CHAT_START_HERE.md](NEXT_CHAT_START_HERE.md) أقدم من قرارات الـArchitecture؛ يمكن قراءته كتاريخ للمشروع فقط **ولا تستخدم جملته «architecture not yet approved» كحالة حالية**.

---

## 3. ملخص المنتج والحدود التجارية

- المنصة **Multi-Tenant SaaS لمتاجر مستقلة** (وليست Marketplace)، لقطاع SMB Retail، مع **Egypt-first Closed Pilot** محدود ومرحلي، لغة عربية/إنجليزية وعملة EGP.
- ثلاثة مسارات تشغيل أساسية: **Online Orders، Manual Orders، Direct Sales مبسطة وOnline-only**؛ كلها تستخدم Catalog/Inventory ومبادئ سعر وصلاحية مشتركة.
- متجر مستقل لكل Merchant على Subdomain مع Dashboard، Central Platform Admin، موظفين بصلاحيات وLocation Scope، وعملاء Storefront محليين لكل Tenant.
- Catalog يتضمن Simple/Variant Products وBasic Services وفق D-series؛ Discount/Coupon/Tax/Shipping/Refund قواعدها التفصيلية في سجل القرارات ولا يجوز تغييرها أثناء تصميم FS.
- **Inventory by Location**؛ كامل الطلب يُنفذ من **Location واحدة مؤهلة** دون Split Fulfillment؛ لا Overselling؛ الحجوزات والتحصيل والتسليم الفعلي محكومة بحالة المعاملة.
- MVP ليس Full ERP، ولا Full POS/Offline Checkout، ولا Split Fulfillment، ولا Marketplace، ولا Payment Gateway أو Shipping Carrier API أو automated WhatsApp/SMS كتطبيقات أولية بدون موافقة منفصلة.
- **D-200 (A) أحدث قرار تجاري معتمد**؛ **D-201 OPEN**: هل اقتراح Location بديلة أثناء Manual Pending Stock يحتاج Internal Follow-up Marker أم Notes فقط؟ **لا تختار A تلقائيًا**. لا يمنع العمل المعماري في File Storage.

**مصدر تفاصيل D-series النهائي:** سجل D-152…D-200 وHandoff v5.48؛ لا تعتمد على الملخص وحده إذا ناقشت قواعد تجارية حساسة.

---

## 4. الـBackend Architecture المعتمد

- **Laravel 13**, API-first **Modular Monolith**؛ **Next.js** Frontend منفصل، مع Same-Origin API Reverse Proxy.
- **PostgreSQL:** Central DB واحدة + **Tenant DB مستقلة لكل Merchant**، مع least-privilege runtime DB role per Tenant. اتصال Tenant ديناميكي ومفعل وفق Trusted Tenant Context؛ لا تسرب أو fallback إلى Tenant أخرى.
- **`stancl/tenancy v3.10.1`** معتمد **شرطيًا**: يتطلب اختبارات توافق فعلية مع Laravel 13 وPostgreSQL/Redis/Horizon/Sanctum؛ لا تفترض أن التركيب اكتمل.
- **Data:** `public` schema واحدة لكل DB، BIGINT ID افتراضيًا، UUIDv7 فقط للهوية عبر DB عند الحاجة، قيود PostgreSQL، Minor Units للأموال، توقيت UTC وحفظ دليل العمليات الهامة.
- **Modules:** One Writer Principle وPublic Contracts، Application-Orchestrator لكل use case مناسب، Features مستقلة بملفات `spec.md` / `plan.md` / `tasks.md` واختبارات وPR عند التنفيذ.
- **Auth:** Laravel Sanctum first-party Host-Scoped Sessions وCSRF؛ Workforce Identity/Membership في Central، Tenant-local roles/Location scopes، Tenant-local Customer Account/Guard، Gates/Policies؛ **لا MFA في MVP**. Revocation تمنع الطلبات الجديدة فورًا.
- **Transactions:** PostgreSQL ACID داخل DB المعنية، Targeted Locks/Conditional Updates/Constraints، HTTP Idempotency-Key مع Business Idempotency مستقل؛ لا distributed ACID ولا Redis Locks كحقيقة مخزنية/مالية.
- **Queues:** Shared Redis + Laravel Horizon، منطقيًا platform/critical/default/notifications/bulk عند الحاجة؛ PostgreSQL Transactional Outbox + Scheduler-based bounded relay؛ at-least-once consumers مع dedup وOrdering انتقائي، فشل/retry/reconciliation.
- **Tests/Release:** Central + Tenant A/B، اختبارات PostgreSQL/Redis/Stancl فعلية، فشل والتزامن وBlue-Green وأمان العزل؛ BA-30 G1…G10 تظل واجبة.

---

## 5. حالة آخر مرحلتين بالتفصيل — QJ ثم CR

### 5.1 QJ-01…QJ-08 — APPROVED AND UPLOADED

**Source:** `docs/01-architecture/queue-jobs-event-processing-standards.md`  
**Prior upload commit:** `d393077e9d568eebc98bc7478d44469385f1e89c`

القرارات: Shared Redis/Horizon؛ Tenant-scoped trusted job context وcleanup؛ durable DB-local Outbox؛ Laravel Scheduler relay scoped/bounded، at-least-once؛ job-specific retries؛ consumer idempotency؛ native Horizon monitoring/recovery؛ measured capacity/fairness. **ليس كودًا مُنفذًا ولا اختبارات ناجحة**.

### 5.2 CR-01…CR-08 — APPROVED AND UPLOADED

**Source:** `docs/01-architecture/caching-redis-architecture.md`  
**Upload commit:** `97813df83a61a5c8f80b4a4ba872e77f98bd8760`  
**Verified:** 15 sections, 395 lines, read-back **exactly matched** uploaded content.

| Decision | Final approval |
| --- | --- |
| **CR-01 A** | PostgreSQL authoritative; selective module-owned Redis Read Cache؛ لا مصدر نهائي للمخزون/السعر النهائي/الطلبات/الأموال/Idempotency. |
| **CR-02 A** | Shared Redis مع Trusted Tenant وCentral/Environment/Module Scoping؛ Actor/Location/Locale/Visibility عند الحاجة؛ conditional Stancl CacheTenancyBootstrapper؛ لا Queue prefix leakage ولا global flush. |
| **CR-03 A** | Module-owned Targeted Post-Commit Invalidation + bounded TTL؛ إقرار بمخاطر stale refill؛ Outbox فقط عند حاجة durable projection؛ لا Strong Consistency claim. |
| **CR-04 A** | Current authoritative Identity/Membership/Entitlement/Role/Location/Resource checks؛ منع الوصول بعد Revocation؛ Cache للـUI Hints/Registry فقط حسب الحاجة؛ Session/ScopeSessions اختبار معلّق. |
| **CR-05 A** | Feature-specific TTL، Laravel cache-aside، selective short cache locks للـHot Keys، controlled DB fallback، fail-closed sensitive paths؛ jitter/SWR اختياري فقط. |
| **CR-06 A** | Shared Redis Connections/Stores لـCache، Horizon، optional Sessions، rate limits؛ noeviction اتجاه أولي إذا اشتركت التشغيلات الحساسة في Process واحدة مع **خطر OOM write failures**؛ فاصل workload قبل Pilot لو اختبار الذاكرة فشل. |
| **CR-07 A** | Performance ROI against no-cache baseline؛ Hit/Miss, P95/P99, PostgreSQL/Redis/Horizon؛ targeted A/B Central/Tenant tests، race/outage/session/Blue-Green tests، لا enterprise metrics platform. |
| **CR-08 A** | مستند معماري واحد 15 قسمًا، MVP-first، **Implementation + Validation Pending**. |

**Still pending and not approved as configured:** Laravel 13/Stancl `CacheTenancyBootstrapper`/`ScopeSessions` compatibility; Redis vs DB Session Driver; TTL numeric values; Redis connections/`maxmemory`/`noeviction` verified settings; OOM/fallback/stampede tests; production budgets and Pilot Gates. **Architecture approval does not implement or validate any of these.**

---

## 6. المرحلة القادمة المعتمدة كجدول نقاش فقط — FS

**Proposed phase:** **FS — File Storage, Media & Uploads Architecture**. ليس معناها أن FS-01…FS-08 معتمدة؛ مجرد خطة مراجعة متسلسلة.

| ID | Topic | Status |
| --- | --- | --- |
| FS-01 | File Ownership & Storage Classification | **NEXT — NOT YET APPROVED** |
| FS-02 | Multi-Tenant File Isolation & Path Strategy | Proposed |
| FS-03 | Secure Upload Validation & File Processing | Proposed |
| FS-04 | Public Media vs Private File Access | Proposed |
| FS-05 | File Lifecycle, Retention & Cleanup | Proposed |
| FS-06 | Async Exports & Blue-Green Storage Compatibility | Proposed |
| FS-07 | Storage Performance, Security & Testing | Proposed |
| FS-08 | Final Architecture Review & Documentation Plan | Proposed |

### 6.1 BA-15 is already approved; do not reopen casually

- **Local Persistent Storage First + S3-Ready Architecture**. Laravel Native Storage abstraction, files on **VPS-mounted persistent storage outside ephemeral Docker layers**.
- **Production** and **Development** files and secrets separated; Blue and Green deployments must safely access required live persistent files.
- Public product media vs private orders/customer attachments/support/exports **must be classified and authorized**. Tenant identifier/path alone is not sufficient permission to read private files.
- Secure file types/sizes, names, content delivery, abuse/file scanning where appropriate, and lifecycle/retention need Feature-level discussion.
- **S3-compatible storage is future-capable, not the initial backend**. Preserve portable relative/domain references, not app-internal absolute filesystem paths.
- Local files on one VPS are a failure domain; verified recoverability requirements and distinct backup decision are not resolved here.

### 6.2 FS-01 expected discussion boundaries

Examples to classify **without prematurely approving semantics**: product images/media; merchant branding assets; merchant/customer private attachments; order documents; generated PDFs/invoices/receipts; private exports; support uploads; relevant platform-owned administrative files. Decide module ownership of **metadata**, public/private visibility, central vs tenant placement, tenant/access scope and justified retention ownership.

Do **not** jump to final filesystem path templates, numeric upload size limits, CDN/S3 procurement, malware-scanner product, legal retention periods or exact signed URL design in FS-01; future FS decisions and relevant feature/legal checks cover them.

---

## 7. Open issues, risk gates and deferred details

1. **D-201 OPEN**: alternative-location proposal follow-up marker vs notes-only; do not infer approval, discuss under Orders/Fulfillment when reached.
2. **BA-16 Backup & Restore DEFERRED**: Verified off-VPS recovery remains mandatory before real Pilot. **BA-30 G3 stays blocked** until recovery is designed/tested; docs/architecture can progress separately.
3. **Stancl/Laravel13** conditional compatibility and Tenancy integration, including DB roles, QueueTenancyBootstrapper, CacheTenancyBootstrapper, ScopeSessions, long-running worker cleanup.
4. **Session Driver** not selected. Sanctum and guards approved, but actual Redis/DB driver, invalidation and recovery require validation.
5. **Redis memory/eviction/queue/session/fallback**, workers, CPU/RAM, throughput and DB connections require real load tests; 500 Merchants or other scale targets are planning, not proof.
6. **Security/Legal/Pilot:** actual Tenant isolation, final price/stock/financial transactional integrity, privacy, legal e-invoicing/e-receipt obligations for eligible Egyptian segments, release readiness and explicit launch sign-off must be proved.
7. **Project/Backend document wording:** Project Overview notes its own text is still a Review Draft; Backend Architecture is conditionally approved baseline with its own wording-review status. Do not falsely mark final wording approved because follow-up architecture documents are published.
8. **Git branch conventions:** `main` stable; `development` integration; `feature/...` or `fix/...` PR into development; release PR into main. Workflow is documented, not proof that branch protections or CI are already configured.

---

## 8. Communication and execution rules for the next chat

- الرد بالعربية المصرية مع English Technical Terms؛ واضح ومباشر، **قرار معماري واحد في المرة**.
- ابدأ بقراءة الملفات ذات الصلة من **GitHub branch `development`**؛ لا تعتمد على handoff قديم وحده. عُد إلى المصدر عند تفاصيل Business Rules.
- اعرض Option A MVP-first وOption B عند الحاجة، مع Tradeoffs واختبارات/حدود القرار. اطلب اعتماد المستخدم قبل الانتقال للقرار التالي.
- لا تختر أرقام Redis TTL أو file sizes أو locks أو budgets أو retention افتراضية باعتبارها Approved.
- لا تزعم أن Architecture approved = installed/tested/deployed. **Pending Validation** يظهر بوضوح.
- **لا تكتب كودًا ولا تنشئ أو تعدّل أو ترفع مستندات من نفسك**؛ المستخدم يصرّح لكل artifact أو تنفيذ صراحة. رفع CR تم بطلبه بالفعل، وليس تصريحًا مفتوحًا للرفع التلقائي.
- لا تفتح D-201 أو BA-16 كعوائق لمجرد بدء مناقشة FS-01؛ سجّلها فقط ضمن المخاطر، ولا تتجاهل شروط Pilot.
- لو ظهرت تعارضات بين D/OV/BA/MA/TA/DA/IA/API/TC/QJ/CR، وضّحها واطلب قرارًا بدل تغيير حكم سابق بصمت.

---

## 9. Current verified GitHub artifact snapshot (2026-11-18)

- `docs/01-architecture/caching-redis-architecture.md` **exists on `development`** — uploaded commit `97813df83a61a5c8f80b4a4ba872e77f98bd8760`، full content was read back and matched.
- `docs/01-architecture/queue-jobs-event-processing-standards.md` **exists on `development`** — previous approved upload.
- All prior architecture documents linked in Section 2 were fetched or listed in GitHub on `development` in the current work session.
- No application source code or other file was changed **as part of the caching document upload**.
- **This new handoff** is documentation for continuing in another chat; not a new architecture decision, build artifact or Pilot sign-off.

**Exact place to resume:** **FS-01 — File Ownership & Storage Classification**; review BA-15 and Module/Identity/Tenancy architecture first, then propose a small, secure MVP classification and ownership decision **without writing files or code**.
