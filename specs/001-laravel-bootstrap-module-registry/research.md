# F0-01 — Phase 0 Research and Compatibility Evidence

> **Status: PRE-IMPLEMENTATION RESEARCH DRAFT.** Document/web evidence only; no PHP commands, Composer resolution, real runtime, production route tests or CI job were run for this phase as of 2026-11-23.

## Verified externally from official Laravel documentation

| Question | Verified documentation finding | Project implication |
| --- | --- | --- |
| Laravel 13 PHP support | Laravel 13 requires **PHP >=8.3**; official support table lists supported PHP 8.3 through 8.5 at review time | Select supported PHP runtime and lock exact version during implementation; never assume Codespaces has PHP merely because Specify uses Python |
| Framework constraint | Official release notes recommend `^13.0` for Laravel framework dependency | Composer resolve with compatible current 13.x patch; record actual lock file |
| Provider registration | User-defined Providers are listed in `bootstrap/providers.php`; `register()` registers bindings, `boot()` initializes routes/listeners as needed | Keep module registry explicit and small; validate before provider registration; avoid I/O and business side effects on boot |
| API installation | `php artisan install:api` installs Sanctum and generates API scaffolding | **Do not run this in F0-01**; its automatic auth dependency would cross into separately planned F0-02/Identity work |
| Laravel structure | Default application has `app/Providers/` and `bootstrap/providers.php` | Add `app/Core/Modules/` and `app/Modules/FoundationDemo/` only as approved minimal extension |

**Official references** (consulted 2026-11-23): [Laravel 13 Release Notes](https://laravel.com/docs/13.x/releases), [Laravel 13 Service Providers](https://laravel.com/docs/13.x/providers), [Laravel 13 Routing](https://laravel.com/docs/13.x/routing), [Laravel 13 Deployment Requirements](https://laravel.com/docs/13.x/deployment), [Laravel 13 Directory Structure](https://laravel.com/docs/13.x/structure).

## Approved project sources, distinguished from external docs

- [Constitution v1.0.0](../../.specify/memory/constitution.md) — governing principles, approval gates and runtime compatibility caution.
- [Backend Architecture](../../docs/01-architecture/backend-architecture.md) — Laravel 13, API-first Modular Monolith, PHP-FPM initial runtime, no premature Tenant/auth scope.
- [Module Architecture `13 and `16](../../docs/01-architecture/module-architecture.md) — explicit module/provider registration, minimal F0 acceptance, no 16 physical modules requirement.
- [Code Standards CS-01 / CS-07](../../docs/02-standards/code-standards.md) — Eloquent/Actions/boundaries in their owning features, no global mutable Tenant state.
- [Testing Standards TEST-01 / TEST-02](../../docs/02-standards/testing-standards.md) — Pest preferred when compatible; PHPUnit foundation and risk-based target tests.

## Proposed choices (NOT YET VERIFIED OR OWNER APPROVED)

- Integrate the official Laravel 13 skeleton into the existing repository root using a temporary work directory/controlled file selection; never run a destructive scaffold command in the repo root.
- Minimal module list in `config/modules.php`, a tiny `ModuleRegistry` and Laravel `ModuleRegistryServiceProvider` in `bootstrap/providers.php`. Alternative provider wiring can be considered if the real framework startup tests show a safer/simpler fit.
- One `FoundationDemo` test route guarded by app environment and excluded from all production routes and route caches; test both true/false cases.
- PHPUnit/Pest targeted framework tests with no database migrations or dependency on Redis, Sanctum, Stancl, frontend or real merchant data.

## Implementation-time probes (not executed)

1. `php -v`, `php -m`, `composer --version`, `composer check-platform-reqs`: verify actual runtime/required extensions and lock compatibility.
2. `composer validate --strict`, `composer install` in isolated, safe development context; record any runtime incompatibilities.
3. `php artisan about` / `php artisan route:list`: validate boot and exclude private/unintended test route from non-testing environments.
4. Targeted `php artisan test --filter=Foundation` (or verified supported runner): no DB I/O and negative registration test outcomes.
5. `vendor/bin/pint --test` and applicable static checks **only when tool versions/config have been validated**.
6. If CI runner remains blocked, record independently verified Codespaces commands/results, but do **not** claim a green required CI gate.

## Open technical risks and stop conditions

- **PHP or Composer not available / unsupported:** stop and gather compatibility evidence; never silently downgrade Laravel.
- **Pest incompatible with actual PHP/Laravel:** record Composer failure and request approval for appropriate substitute; do not force install.
- **Demo route appears in production route list or cache:** blocking failure; do not merge.
- **Existing docs/specs overwritten by scaffold:** blocking failure; restore original and review diff.
- **Unexpected runtime DB/migration/auth package side effects:** blocking failure; separate into appropriate Feature.
- **Bootstrap provider lifecycle or module config cache mismatch:** revise plan and re-run isolated tests before approval/merge.
