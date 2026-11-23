# F0-01 — Planned Local Bootstrap & Verification Guide

> **Status:** PRE-IMPLEMENTATION GUIDE — T002 runtime check and T003 temporary Composer **dry-run solver** both **completed successfully** in the authorized project Codespace on 2026-11-23 using owner-provided CLI output. Actual Composer package installation, project lockfile, application boot, framework tests and deployment **NOT EXECUTED**.  
> **Scope:** Development/test only. Never copy credentials, use a Production database, run migrations, connect to Merchant data or trigger deployment while following this F0-01 plan.

## Preconditions and approval

- Read [spec](spec.md), [plan](plan.md), [research](research.md) and the ratified [Constitution](../../.specify/memory/constitution.md).
- **Approval recorded:** Owner approved `spec.md` + `plan.md` as F0-01 Option A on 2026-11-23. Basic runtime prerequisite check (T002) and isolated Composer resolver compatibility check (T003) are completed; **T004 safety checks, separate implementation authorization, eventual project lock/install/runtime tests, and this F0-01 PR's independent review** remain required. Keep upstream Spec Kit setup PR and this F0-01 PR separate.
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

This is **real user-supplied Codespaces output**, not a tool-mediated Codespaces shell execution. The earlier *assistant container* with missing extensions was a separate machine and must not be mistaken for this project runtime. The prerequisite check alone did not prove package compatibility; this was subsequently established for a candidate package set via the independent T003 **dry-run**, recorded immediately below. There is still **no installed project package tree, actual project lock or Laravel boot evidence**.

## T003 dependency-resolution probe (COMPLETED — reproducible command, historical evidence)

The following is the **actual procedure** used by the owner on 2026-11-23. It is preserved for reproducibility, **not a command that must be repeated**. It performs a dry-run with no install/Composer plugins/scripts, in a new `/tmp` directory outside the repository.

```bash
probe_dir="$(mktemp -d /tmp/f0-01-composer-XXXXXX)"
(
  set -e
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

- This writes the probe `composer.json` in a newly created `/tmp` directory (and Composer may refresh its normal user cache); it does **not** modify the repository's `composer.json` or `composer.lock`. No `vendor` installation or Laravel bootstrap is authorized.
- The observed `T003_COMPOSER_EXIT=0` confirmed this candidate graph could resolve at that moment; exact package versions are recorded below. The final project lock/install and runtime checks remain later tasks.
- If Composer reports conflicts, security-advisory blocking or network errors, keep T003 OPEN, share the exact log and do **not** use `--ignore-platform-reqs`, `--no-blocking`, `--no-cooldown` or forced downgrades.
- `probe_dir` is printed for manual cleanup after inspection; don't run destructive cleaning commands in the repository.
- Pest 5 / its Laravel plugin were published after the older Pest 4 / PHPUnit 12 project research. These are researched **candidates**, not an approved or installed test stack. See `research.md` for sources.

### Actual T003 result (owner-provided Codespaces output)

- `./composer.json is valid` from strict validation.
- `T003_COMPOSER_EXIT=0` from the Composer update dry-run (not a real update or install).
- `T003_PROBE_DIR=/tmp/f0-01-composer-qwmppK`.
- Simulated lock operations: **118 installs / 0 updates / 0 removals**.
- Resolved: `laravel/framework v13.43.0`, `pestphp/pest v5.3.7`, `pestphp/pest-plugin-laravel v5.0.1`, `phpunit/phpunit 13.4.7`.
- Composer printed `No security vulnerability advisories found.` for this run.
- **Do not call this a Laravel installation, a project `composer.lock`, a runtime smoke-test, or CI pass.** The project remains documentation-only.

## T004 non-destructive Git / credential filename preflight (PENDING — no Codespaces)

**Workflow supersession, 2026-11-23:** The owner explicitly does not want to run any additional commands in Codespaces. **Do not send the following historical terminal commands as an action item.** Continue this gate through GitHub connector inspections and, when independently available, a verified assistant-managed isolated checkout or approved CI runner. Current assistant container has PHP 8.4.24 but no Composer or complete Laravel extensions; it is not a validated Laravel execution environment. The commands below are kept strictly for traceability/reference and are **not required from the owner**. Never display secret contents.

```bash
git status --short
git branch --show-current
git fetch origin
git pull --ff-only
git status --short
git log -1 --oneline
git diff --check
git diff --name-only origin/feature/f0-spec-kit-setup...HEAD
printf '\nTracked credential-like filenames (names only):\n'
git ls-files | grep -Ei '(^|/)(\.env($|\.)|id_rsa$|id_ed25519$|[^/]+\.(pem|p12|pfx|key)$|service[-_]account[^/]*\.json$)' || true
printf '\nTracked Laravel / migration paths (names only):\n'
git ls-files | grep -E '^(artisan$|composer\.(json|lock)$|app/|bootstrap/|database/migrations/|vendor/)' || true
```

**Current GitHub-side evidence (2026-11-23):** remote PR #1 / #2 are Draft and unmerged, and the changes since F0-01 Option A approval touch only `docs/` and `specs/`; prior lookups for root `artisan`, `composer.json` and `.gitignore` returned 404. This supports branch/scope review only. It does **not** prove a full credential scan or safe local runtime. Preserve branch SHA, use isolated reviewed commits and `git revert` (not forced resets) as rollback strategy. T004 stays pending until all gates have suitable evidence.

**Evaluate the evidence before marking T004 done.** A path named `.env.example` is only a candidate and may be an intentional safe template; any actual tracked secret file or destructive migration blocks the gate. An empty credential-like filename list **does not prove** no secrets are embedded elsewhere. The team must separately confirm only isolated development/test credentials and a reviewed safe rollback route. The planned bootstrap must occur **outside the existing repository**, followed by selective integration on the feature branch, with no overwrite of `docs/`, `specs/`, `.specify/` or Git history. No merging or code scaffolding is authorized by this check.

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
| Composer **temporary dry-run** dependency graph | **PASS — owner-provided Codespaces output** | local worktree on F0-01; original SHA at T002 was `accbe34` (no fresh T003 SHA provided) | `composer validate --strict --no-check-publish`, `composer update --dry-run --no-install --no-scripts --no-plugins --no-interaction` | Exit `0`; 118 simulated installs; Laravel 13.43.0 / Pest 5.3.7 / Plugin 5.0.1 / PHPUnit 13.4.7; no advisories found. **Project `composer.lock`, `composer install`, and `composer check-platform-reqs` still pending.** |
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
