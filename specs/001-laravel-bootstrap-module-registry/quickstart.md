# F0-01 — Planned Local Bootstrap & Verification Guide

> **Status:** PRE-IMPLEMENTATION GUIDE — T002 runtime prerequisite check **completed** in the authorized project Codespace on 2026-11-23 using owner-provided CLI output. Laravel dependency solving/installation, application boot, tests and deployment **NOT EXECUTED**.  
> **Scope:** Development/test only. Never copy credentials, use a Production database, run migrations, connect to Merchant data or trigger deployment while following this F0-01 plan.

## Preconditions and approval

- Read [spec](spec.md), [plan](plan.md), [research](research.md) and the ratified [Constitution](../../.specify/memory/constitution.md).
- **Approval recorded:** Owner approved `spec.md` + `plan.md` as F0-01 Option A on 2026-11-23. Basic PHP/Composer/extension runtime prerequisite check (T002) is now completed; **T003 real dependency compatibility, remaining T004 safety checks, separate implementation authorization, and this F0-01 PR's independent review** remain required. Keep upstream Spec Kit setup PR and this F0-01 PR separate.
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

## Verified T002 evidence — project Codespaces, 2026-11-23

The owner ran the commands in `/workspaces/ecommerce_v2_api` on `feature/f0-01-laravel-bootstrap`, with a clean `git status --short`, local HEAD `accbe34`, and the following actual output:

- `php -v` → `PHP 8.4.27 (cli) (built: Oct 28 2026)`, Xdebug `3.5.3`.
- `composer --version` → `Composer version 2.10.3 2026-08-27`, running under PHP `8.4.27`.
- `php -r '...extension_loaded...' ` → **OK**: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml` (13/13 official Laravel runtime requirements).
- Earlier owner-provided `php -m` tail showed `xml`, `xmlreader`, `xmlwriter`, `zlib`; the complete raw `php -m` text was not preserved, but the required extensions were explicitly checked.
- During Composer and the PHP extension check, Xdebug attempted to connect to `localhost:9003`, which did not respond. This is only the **step-debug client warning**, not Composer resolution failure. `XDEBUG_MODE=off` can suppress it per command when running T003.

This is **real user-supplied Codespaces output**, not a tool-mediated Codespaces shell execution. The earlier *assistant container* with missing extensions was a separate machine and must not be mistaken for this project runtime. This check **does not** validate a Laravel package lock or the Pest/PHPUnit dependency graph; both remain T003.

## T003 dependency-resolution probe (PENDING — run in Codespaces only)

The following performs a **dry-run**, with **no install and no Composer plugins/scripts**, inside a newly created `/tmp` directory, not the repository. Do not report T003 passing until the owner supplies real output and an exit code.

```bash
probe_dir="$(mktemp -d /tmp/f0-01-composer-XXXXXX)"
(
  cd "$probe_dir" || exit 1
  cat > composer.json <<'JSON'
{
  "name": "elmo-tech/f0-01-compatibility-probe",
  "description": "Temporary F0-01 dependency resolution only",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": "^8.4",
    "laravel/framework": "^13.0"
  },
  "require-dev": {
    "pestphp/pest": "^5.0",
    "pestphp/pest-plugin-laravel": "^5.0"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
JSON
  XDEBUG_MODE=off composer validate --strict --no-check-publish
  XDEBUG_MODE=off composer update --dry-run --no-install --no-scripts --no-plugins --no-interaction
)
probe_exit=$?
echo "T003_COMPOSER_EXIT=$probe_exit"
echo "T003_PROBE_DIR=$probe_dir"
```

- This changes only the new temporary probe directory, not `composer.json` or `composer.lock` in the repo. No `vendor` installation or Laravel bootstrap is authorized.
- A `T003_COMPOSER_EXIT=0` confirms only that this **candidate** dependency graph could resolve at that moment; verify the packages and version selection from the output, plus the eventual real lock/install in later tasks.
- If Composer reports conflicts, security-advisory blocking or network errors, keep T003 OPEN, share the exact log and do **not** use `--ignore-platform-reqs`, `--no-blocking`, `--no-cooldown` or forced downgrades.
- `probe_dir` is printed for manual cleanup after inspection; don't run destructive cleaning commands in the repository.
- Pest 5 / its Laravel plugin were published after the older Pest 4 / PHPUnit 12 project research. These are researched **candidates**, not an approved or installed test stack. See `research.md` for sources.

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
| PHP / Composer / extensions | **PASS — user-provided output from authorized Codespaces** | local `accbe34` when tested | `php -v`, `composer --version`, 13-extension `extension_loaded()` check; partial `php -m` | PHP **8.4.27** CLI; Composer **2.10.3**; all Laravel-required extensions **OK (13/13)**; Xdebug v3.5.3 debugger-client warning is non-blocking |
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
