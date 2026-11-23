# F0-01 — Phase 0 Research and Compatibility Evidence

> **Status: T002 CODESPACES RUNTIME PREREQUISITES VERIFIED; T003 COMPATIBILITY RESOLUTION PENDING.** PHP/Composer/extension outputs were supplied by the owner on 2026-11-23. No Composer dependency graph/lock, Laravel installation, framework tests, route checks or CI validation have been run.

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

## Approved F0-01 Option A design choices (NOT IMPLEMENTED OR RUNTIME-TESTED)

- Integrate the official Laravel 13 skeleton into the existing repository root using a temporary work directory/controlled file selection; never run a destructive scaffold command in the repo root.
- Minimal module list in `config/modules.php`, a tiny `ModuleRegistry` and Laravel `ModuleRegistryServiceProvider` in `bootstrap/providers.php`. Alternative provider wiring can be considered if the real framework startup tests show a safer/simpler fit.
- One `FoundationDemo` test route guarded by app environment and excluded from all production routes and route caches; test both true/false cases.
- PHPUnit/Pest targeted framework tests with no database migrations or dependency on Redis, Sanctum, Stancl, frontend or real merchant data.

## Implementation-time probes (T002 completed; T003–T026 remain)

1. **T002 COMPLETE:** Codespaces PHP version, Composer availability and 13/13 Laravel-required extensions were validated (details below). **NOT EXECUTED:** `composer check-platform-reqs` because no project `composer.lock` exists; belongs to later lock-dependent checks.
2. `composer validate --strict`, `composer install` in isolated, safe development context; record any runtime incompatibilities.
3. `php artisan about` / `php artisan route:list`: validate boot and exclude private/unintended test route from non-testing environments.
4. Targeted `php artisan test --filter=Foundation` (or verified supported runner): no DB I/O and negative registration test outcomes.
5. `vendor/bin/pint --test` and applicable static checks **only when tool versions/config have been validated**.
6. If CI runner remains blocked, record independently verified Codespaces commands/results, but do **not** claim a green required CI gate.

## T002 user-provided Codespaces evidence — 2026-11-23

| Check | Actual owner-provided output | Conclusion |
| --- | --- | --- |
| Working directory / branch | `/workspaces/ecommerce_v2_api`, `feature/f0-01-laravel-bootstrap`; clean `git status --short` at local `accbe34` | Correct local execution context, but remote advanced with documentation commits |
| PHP | `PHP 8.4.27 (cli)`, Xdebug `3.5.3` | Passes Laravel 13 minimum PHP 8.3 |
| Composer | `Composer 2.10.3` using PHP `8.4.27` | CLI available; packages not resolved |
| Extensions | All 13 documented requirements returned `OK` in explicit `extension_loaded()` check | Passes framework minimum required extensions |
| Xdebug message | `Could not connect to debugging client` (`localhost:9003`) | Not a dependency incompatibility; disable debug mode for single Composer commands if desired |
| Composer dependency solve | **NOT RUN** | T003 still pending |

**Documentation source:** official Laravel 13 deployment requirements: https://laravel.com/docs/13.x/deployment. **Evidence source:** terminal transcript supplied by project owner; not independently executed within Codespaces by this assistant. The isolated assistant container observations recorded in earlier handoff are not representative of this project's environment.

### Remaining T003 checks

**Current published candidate (verified via upstream documentation/Packagist on 2026-11-23):** `laravel/framework:^13.0`, `pestphp/pest:^5.0`, `pestphp/pest-plugin-laravel:^5.0` on **PHP 8.4.27**. Pest 5 requires PHP 8.4 and is built on PHPUnit 13, while Pest Laravel Plugin 5.0.1 declares a Laravel 13 framework constraint beginning at `^13.23.0`. **These are external metadata facts, not proof of a Composer-resolvable set in this project.** A root PHPUnit pin is deliberately omitted from the temporary probe so Pest may select its compatible PHPUnit version. Pest 5.3.7 has an explicit conflict with PHPUnit `>13.4.7`, even though Packagist lists PHPUnit 13.4.8 separately; real resolution may choose an allowed patch or fail under security policy, and neither situation should be hidden.

**Historical candidate:** Older F0-01 research mentioned Pest 4 + PHPUnit 12 per Laravel upgrade guidance. That guidance remains historically accurate for upgrading certain older projects, but **does not fix the test tool versions for this new Laravel 13 project**. Do not automatically pin the old combination or treat the new combination as approved without Composer results.

**Safe T003 requirement:** run a real `composer update --dry-run --no-install --no-scripts --no-plugins` inside a **temporary directory outside the repository**, record actual exit status/solver output, do not install packages, execute plugins/scripts, skip extension checks, ignore security policy or silently downgrade. See `quickstart.md` for a reproducible probe.

External sources: [Pest 5 upgrade guidance](https://pestphp.com/docs/upgrade-guide), [Pest 5 release](https://pestphp.com/docs/pest5-now-available), [Pest Laravel Plugin on Packagist](https://packagist.org/packages/pestphp/pest-plugin-laravel), [Pest on Packagist](https://packagist.org/packages/pestphp/pest), [PHPUnit on Packagist](https://packagist.org/packages/phpunit/phpunit), and [Composer CLI dry-run](https://getcomposer.org/doc/03-cli.md).

## Open technical risks and stop conditions

- **PHP or Composer not available / unsupported:** stop and gather compatibility evidence; never silently downgrade Laravel.
- **Pest incompatible with actual PHP/Laravel:** record Composer failure and request approval for appropriate substitute; do not force install.
- **Demo route appears in production route list or cache:** blocking failure; do not merge.
- **Existing docs/specs overwritten by scaffold:** blocking failure; restore original and review diff.
- **Unexpected runtime DB/migration/auth package side effects:** blocking failure; separate into appropriate Feature.
- **Bootstrap provider lifecycle or module config cache mismatch:** revise plan and re-run isolated tests before approval/merge.
