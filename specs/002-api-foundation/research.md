# F0-02 API Foundation — Source and Runtime Discovery (2026-11-24)

**Status:** DISCOVERY / DOCUMENT REVIEW ONLY; not implemented, not approved, and no F0-02 CI has run.  
**Branch:** `feature/f0-02-api-foundation` based on merged `development` after F0-01 documentation reconciliation.

## Verified actual code state

| Evidence | Source / outcome |
| --- | --- |
| F0-01 completion | [Actual merge handoff](../../docs/handoff/F0-01_MERGED_AND_CLOSED_2026-11-24.md) and [checked 26/26 tasks](../001-laravel-bootstrap-module-registry/tasks.md); [PR #1](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/1), [PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) merged into `development` |
| Runtime | `composer.json` PHP `^8.4`, Laravel `^13.0`, Pest `^5.0`, committed lock, real F0-01 CI on PHP 8.4/Laravel 13.43.0 |
| Bootstrap | `bootstrap/app.php` uses native `Application::configure`, `withRouting(commands: routes/console.php)`; `withExceptions()->shouldRenderJsonWhen()` for `api/*` and `expectsJson()`. No public API route file/production routes |
| Modules | `config/modules.php` explicit FoundationDemo provider only, its marker/route under `testing`; no Tenant/central database, Auth or domain modules |
| Existing tests | F0-01 pure registry/unit, fresh isolated boot, architecture, and production route/cache isolation; preserve all. F0-02 needs separate scoped HTTP contract tests after approval |
| Package boundary | No approved F0-02 Composer package modifications; don't install Sanctum/Spatie Query Builder/OpenAPI generators/Pint/PHPStan merely because API Standards mention future candidate capabilities |

## Approved source hierarchy and implications

1. [Constitution v1.0.0](../../.specify/memory/constitution.md) Principle I/V/VII/VIII: bounded approved Feature scope, versioned `/api/v1` REST JSON and accurate status/error envelope; tests and evidence must actually run, and approved plan precedes code.
2. [API Standards](../../docs/02-standards/api-standards.md):
   - **AS-01:** `/api/v1`, four logical surfaces, module-owned routes; Tenant URI selector never authorizes a request. No current business route should be opened for demonstration.
   - **AS-02:** shared `App\Helpers\ApiResponse`, `App\Enums\HttpStatusCode` direction; `success/message/data` and `success/message/errorCode/errors`, optional honest pagination `meta`; real statuses and empty 204; server-generated `X-Request-ID`.
   - **AS-03:** Form Requests for input, stable shared error-code strings, ar/en generic errors, **explicitly unspecified fallback locale**. Strict app-owned camelCase wire fields.
   - **AS-08:** lightweight contract-first OpenAPI, single version, compatibility analysis. No SDK/generator installation implied.
   - **AS-09:** real API contract/safety tests and actual PR evidence; PostgreSQL Tenant integration evidence only when applicable, not mocked as F0-02 success.
3. **FBR-02-05/06/07** in API Standards: real CSRF mismatch eventual 403/`CSRF_TOKEN_MISMATCH`, finalized Money decimal-string integer minor units + ISO currency, BIGINT IDs decimal strings. They supersede illustrative numeric examples in earlier AS-02 code blocks.
4. [Module Architecture](../../docs/01-architecture/module-architecture.md) §§8/13/16/17: narrow Core technical middleware/contracts, explicit Providers, no business data ownership, F0 Engineering Foundation before F1 Tenancy or domain modules.
5. [Code Standards](../../docs/02-standards/code-standards.md) CS-05/06/07/08: project-defined PHP Enums are **int-backed**; centralized typed exception mapping; native DI, no mutable identity state; PSR-12.
6. [Testing Standards](../../docs/02-standards/testing-standards.md) TEST-01/02/05/09: test risk-selected HTTP/validation/contract/correlation with real Laravel runner; do not claim 2-Tenant PostgreSQL isolation before F1 infrastructure exists.

## Decisions still requiring review before implementation

- **F0-02-DEC-01:** Default/fallback locale for unsupported or absent Accept-Language; ar/en messages are required, but choosing Arabic fallback is **not yet approved**.
- **F0-02-DEC-02:** Stable error-code storage and typed mapping without violating int-backed PHP Enum rule (candidate compact string constants).
- **F0-02-DEC-03:** Canonical OpenAPI artifact/tool selection (candidate `contracts/openapi.json` with no new parser dependencies).
- **F0-02-DEC-04:** Empty native API route file vs deferred route registration. Neither may expose a public test/probe/business route.

**Risk boundary:** Centralized exception/HTTP middleware touches every later domain, making accidental 200 errors, unsafe 500 payloads, incorrect 204, untrusted correlation, or production probe exposure material regressions. Use contract tests and real production route cache checks before merge.

## Actual execution statement

**NO F0-02 Laravel code, migrations, package installations, routes or tests were created/executed as part of this document.** This is engineering discovery based on real merged F0-01 code and approved standards. Real feature implementation is blocked on F0-02 spec/plan approval.
