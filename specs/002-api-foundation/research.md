# F0-02 API Foundation — Source and Runtime Discovery (2026-11-24)

**Status:** DISCOVERY BASELINE RETAINED; DEC-01…04 and complete spec/plan approved; scoped implementation now verified in real CI on feature branch, independent review and merge pending.  
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
   - **AS-03:** Form Requests for input, stable shared error-code strings, ar/en generic errors and app-owned camelCase wire fields. AS-03's earlier unspecified fallback is resolved by later approved LOC-ST-02 and owner DEC-01 confirmation.
   - **AS-08:** lightweight contract-first OpenAPI, single version, compatibility analysis. No SDK/generator installation implied.
   - **AS-09:** real API contract/safety tests and actual PR evidence; PostgreSQL Tenant integration evidence only when applicable, not mocked as F0-02 success.
3. **FBR-02-05/06/07** in API Standards: real CSRF mismatch eventual 403/`CSRF_TOKEN_MISMATCH`, finalized Money decimal-string integer minor units + ISO currency, BIGINT IDs decimal strings. They supersede illustrative numeric examples in earlier AS-02 code blocks.
4. [Module Architecture](../../docs/01-architecture/module-architecture.md) §§8/13/16/17: narrow Core technical middleware/contracts, explicit Providers, no business data ownership, F0 Engineering Foundation before F1 Tenancy or domain modules.
5. [Code Standards](../../docs/02-standards/code-standards.md) CS-05/06/07/08: project-defined PHP Enums are **int-backed**; centralized typed exception mapping; native DI, no mutable identity state; PSR-12.
6. [Testing Standards](../../docs/02-standards/testing-standards.md) TEST-01/02/05/09: test risk-selected HTTP/validation/contract/correlation with real Laravel runner; do not claim 2-Tenant PostgreSQL isolation before F1 infrastructure exists.
7. [Localization Standards](../../docs/02-standards/localization-standards.md) LOC-ST-02, §§4.1–4.5: ar default/fallback, ar/en regional parent selection and quality weights (q=0 excluded), request-scoped locale and safe missing-key fallback. This was approved 2026-11-22; it supersedes the old AS-03 unspecified locale note. F0-02 owner explicitly reconfirmed ar.
8. [Existing validation workflow](../../.github/workflows/f0-01-laravel-bootstrap.yml) runs unfiltered Pest and architecture checks but its path filters omit `lang/**` and OpenAPI artifacts; propose minimal PR-trigger coverage in F0-02 plan. No new quality packages. Existing [production route isolation test](../../tests/Feature/Foundation/FoundationProbeIsolationTest.php) proves an empty real route collection cannot produce a route-cache artifact and uses an isolated scratch app with a control route for genuine cache evidence.

## Owner decisions confirmed 2026-11-24

- **F0-02-DEC-01:** approved A — Arabic fallback, consistent with LOC-ST-02 and supported ar/en negotiation.
- **F0-02-DEC-02:** approved compact string constants, int-backed HTTP Enum, central mapper; no global domain error catalog.
- **F0-02-DEC-03:** approved OpenAPI 3.1 JSON at `specs/002-api-foundation/contracts/openapi.json`, empty paths, no new packages and explicitly limited lightweight checks.
- **F0-02-DEC-04:** approved empty native `routes/api.php` under `/api/v1`, preserving explicit providers and zero public routes.

## Review findings and approved direction

- Route-group-only middleware cannot be assumed to execute for an unmatched route; early path-scoped handling and actual HTTP lifecycle tests are required. Scope the new v1 envelope to exact v1 path/descendants, retaining existing non-v1 and non-API behavior.
- Final plan includes `BAD_REQUEST`, `METHOD_NOT_ALLOWED`, `SERVICE_UNAVAILABLE` and a generic `HTTP_ERROR` fallback, preserving genuine HTTP statuses and protocol headers; approved with complete spec/plan on 2026-11-24, not implied by DEC-02 alone.
- Test successive requests including errors for ID/locale/diagnostic cleanup and no sensitive response/log leakage. No real Auth or rate-limiter behavior claimed.
- Full OpenAPI validator is not installed; native structural checks plus response contract assertions are the approved approach, not proof of complete OpenAPI compliance.
- At initial review PR #4 had 5 documentation-only files, 5 commits, no reviews/threads, and CodeRabbit explicitly skipped Draft auto-review. Feature was one documentation-only commit behind development (canonical handoff update `5155521f9574057d890f3f594f34f6f180af8a41`), not behind runtime code. No automatic synchronization or merge authorized.
- See [new dated owner decision handoff](../../docs/handoff/F0-02_OWNER_DECISIONS_AND_REVIEW_2026-11-24.md).

## Actual execution statement

Historical discovery findings above describe the pre-code baseline. T003 explicit implementation authorization was granted 2026-11-24. T004–T025 executed on the feature branch with real baseline/RED/GREEN and hardening evidence; T026 feature documentation/dated handoff complete, permanent development handoff update blocked; [quickstart](quickstart.md) records exact SHA/run/outcomes, [tasks](tasks.md) records progress. No new package/lock, migrations, actual Auth/Tenancy/Redis, production endpoints or deployment. T027 independent review and T028 separate merge authority remain required.
