# F0-01 — Planned Local Bootstrap & Verification Guide

> **Status:** DRAFT PROCEDURE — NOT EXECUTED. No Laravel code or verified PHP runtime currently exists on the planning branch.  
> **Scope:** Development/test only. Never copy credentials, use a Production database, run migrations, connect to Merchant data or trigger deployment while following this F0-01 plan.

## Preconditions and approval

- Read [spec](spec.md), [plan](plan.md), [research](research.md) and the ratified [Constitution](../../.specify/memory/constitution.md).
- Obtain owner approval for `spec.md` and `plan.md` before installing application code. Keep upstream Spec Kit setup PR and this F0-01 PR separate.
- Choose an authorized **isolated** runtime environment with PHP >=8.3, Composer and required PHP extensions; record exact versions. A Codespaces Python version from the Spec Kit installation is **not PHP evidence**.
- Plan a non-destructive Laravel-skeleton integration: generate into an isolated **temporary directory**, inspect before selectively copying into the existing repo. Do not run scaffolding directly over `docs/`, `specs/`, `.specify/` or Git history.

## Preflight commands to run only after authorization

~~~bash
php -v
php -m
composer --version
git status --short
git branch --show-current
~~~

Check Laravel 13 PHP extension list against [official deployment requirements](https://laravel.com/docs/13.x/deployment). Record **actual** output in the implementation PR. Choose the exact runtime and dependency versions only after resolution.

## Planned implementation validation

Run only when the Laravel source and tested Composer lock actually exist.

~~~bash
composer validate --strict
composer install
composer check-platform-reqs
php artisan about
php artisan route:list
php artisan test --filter=Foundation
~~~

The test command is **illustrative**: confirm actual Pest/PHPUnit test file names and supported filters before treating it as an executable gate. Do not interpret a default Laravel welcome page as proof that the Module Registry works.

If installed and configured in the authorized implementation branch, run the appropriate formatting/static checks (e.g. `vendor/bin/pint --test`, configured PHPStan/Larastan) and capture results.

## Test-only demo and deployment safety

- The FoundationDemo test contract is defined only in [foundation-probe.md](contracts/foundation-probe.md), not as a production OpenAPI endpoint.
- Confirm the probe route is available **only** in the test application and is **absent** under the non-testing/production application, including production-built route caches.
- Verify unlisted module folders do not activate, and duplicate/missing/invalid provider entries fail predictably.
- Confirm no DB connections, migrations, queue workers, auth module dependencies, secrets or live Merchant data are required.

## Required evidence table (fill only after actual command execution)

| Check | Environment | Revision | Command | Result / log reference |
| --- | --- | --- | --- | --- |
| PHP / Composer / extensions | NOT RUN | — | `php -v`, `composer --version`, `php -m` | PENDING |
| Composer dependency graph | NOT RUN | — | `composer validate`, `check-platform-reqs` | PENDING |
| Laravel 13 boot | NOT RUN | — | `php artisan about` | PENDING |
| Module registered/unlisted/invalid | NOT RUN | — | Targeted tests | PENDING |
| Test probe production exclusion | NOT RUN | — | Separate env/route-cache checks | PENDING |
| Formatting / static analysis | NOT RUN | — | Approved commands | PENDING |
| GitHub Actions runner | NOT VERIFIED | — | Required workflows | Separate earlier startup failures |

No PASS status should be filled without actual command/revision evidence. CI status is separate from manual Codespaces tests.

## Forbidden shortcuts in F0-01

- Do not run `php artisan install:api`; it installs Sanctum, which belongs to a separately reviewed Feature.
- Do not run `migrate`, `migrate:fresh`, tenant provisioning, seeders or database destructive commands.
- Do not install `stancl/tenancy`, Redis/Horizon or unrelated infrastructure merely to satisfy this technical bootstrap.
- Do not deploy a testing route, use a shared Production credential, merge the PR or declare Pilot readiness.

[Official Laravel 13 installation](https://laravel.com/docs/13.x/installation) · [Service Providers](https://laravel.com/docs/13.x/providers) · [Routing](https://laravel.com/docs/13.x/routing).
