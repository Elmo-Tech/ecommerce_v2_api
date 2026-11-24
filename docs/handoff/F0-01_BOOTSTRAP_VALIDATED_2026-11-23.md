# F0-01 — Laravel 13 Bootstrap + Locked CI Validation PASSED

**Verified:** 2026-11-23 (Africa/Cairo)  
**Repository:** https://github.com/Elmo-Tech/ecommerce_v2_api  
**Branch:** `feature/f0-01-laravel-bootstrap` ([Draft PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2), stacked on Draft PR #1 → `development`).  
**Owner:** Approved F0-01 Option A specification/plan and independently authorized starting T005. Owner requires **no manual Codespaces commands**; no merge/deploy approval.

## 1. Milestone result

**T001–T009 are COMPLETE (9/26). T010–T026 are NOT STARTED.**

The previously blocked PHP/Composer GitHub Actions runner now **actually executed**. According to the owner, the prior runner failures were caused by an Actions limit affecting private repositories; the connector independently verifies that the runs now execute, **not the former billing settings**.

The minimal API-first Laravel 13 source is in GitHub with a **real, committed and validated `composer.lock`**. The framework booted, its production-irrelevant default Storage HTTP routes were disabled, and the Foundation smoke test passed without warnings. This does **not** prove the unimplemented Module Registry / FoundationDemo, negative/provider validation, non-testing test-route isolation or production cache safety.

## 2. Actual GitHub Actions evidence

| CI Run | Commit/workflow stage | Observed result |
| --- | --- | --- |
| [45217288046](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45217288046), rerun | First runner after limit addressed | PHP 8.4 setup, Composer strict validation, dependency **resolution (118 packages)**, lock artifact, installed dependencies, platform check, `artisan about` and `route:list` **passed**. Pest stopped at missing `tests/Unit` directory; actual failure, fixed below. |
| [45238110052](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45238110052) | Fixed missing Unit directory + Storage endpoints | **SUCCESS**: Laravel 13.43.0 boot, zero route entries, Pest foundation 9 assertions (one warning about missing empty `.env`). |
| [45238885531](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45238885531) | Empty ephemeral test `.env` warning fix | **SUCCESS**: Pest **1 passed, 9 assertions, no warnings**; `artisan about`, `route:list`, platform and install passed. |
| [45239051524](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45239051524) | `composer.lock` committed as project source | **SUCCESS** with installed packages and Foundation smoke. |
| **[45239265994](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45239265994)** | **Reproducible CI from pinned lock** at `a446e0be` | **SUCCESS**. `composer validate --strict --no-check-publish`; `composer check-platform-reqs --lock`; `composer install --no-scripts --no-plugins`; **`composer audit --locked`: no advisories found**; PHP 8.4.28/Composer 2.11.2/Laravel 13.43.0; `artisan about` passed; `route:list --json` output `[]`; Pest **1 passed / 9 assertions**; tracked-file diff check passed. |

All claims above are from **actual GitHub job step results and logs**, not speculative tool output. No CI job performed migrations, seeding, Tenant provisioning, production database access or deployment.

### Actual lock provenance

- First successful GitHub Actions lock artifact: `f0-01-reviewed-candidate-composer-lock` from run 45217288046 (artifact `14848429519`), downloaded via GitHub connector and inspected as a ZIP containing exactly `composer.lock`.
- Lockfile has **74 production packages + 44 dev packages = 118 total**, with Composer `content-hash: e1e42035550a8407ed60e95dd6673e77`; original artifact `composer.lock` UTF-8 byte count **341,767**, SHA-256 `79f4d782af3693b3e666b025afee805beef1d363561d83b825fb5d47bef7f051` measured from the actual artifact.
- Selected versions: `laravel/framework v13.43.0`, `pestphp/pest v5.3.7`, `pestphp/pest-plugin-laravel v5.0.1`, `phpunit/phpunit 13.4.7`. Lock content reconstructed from the mounted artifact (full text), validated as JSON, verified matching original UTF-8 size/content-hash and committed to the F0-01 feature branch in **`5d334ecdc67aef388f37ecf88aaff0370996e773`**. The full lock was then fetched successfully from GitHub.
- The final successful CI run uses the **committed lock**, **does not run `composer update`**, and enforces a tracked-file diff check including `composer.lock`. `composer audit --locked --no-interaction` reported **`No security vulnerability advisories found.`** This is a time-bounded advisory result, not a permanent security certification.

## 3. Bugs found and fixed through real test runs

1. Initial Pest test-runner failure: `phpunit.xml` declared `tests/Unit` but the directory was not tracked. Added `tests/Unit/.gitkeep`; rerun proceeded.
2. The stock Laravel 13 Filesystem Service Provider registered HTTP GET/PUT `storage/{path}` routes because `config/filesystems.php` had local `serve => true`. Upstream Laravel 13 provider's `shouldServeFiles()` confirms the flag controls these routes. Set **`serve => false`** because F0-01 excludes file serving and requires a minimal API-first foundation. The Pest smoke now asserts `storage.local` and `storage.local.upload` do **not** exist. `route:list --json` returned **`[]`**.
3. PHPUnit/Pest originally issued a warning from Dotenv attempting to read nonexistent `.env`. The CI runner creates a **private empty, ignored `.env`** only for its ephemeral testing worktree using `umask 077; : > .env`. It contains **zero secrets**, is verified empty/ignored and is **not committed**. Final smoke run has **no warning**.
4. The workflow originally solved dependencies using `composer update` on each CI run; once the authentic `composer.lock` was committed, CI was switched to **deterministic locked installation**, lock/platform checks and an explicit security advisory audit.
5. PHPUnit and Pest test selection still enforce `failOnEmptyTestSuite` / `--fail-on-empty-test-suite` and `--group=foundation`, so an empty selection cannot pass unnoticed.

## 4. Scope and remaining tasks

- **Completed**: T001–T004 approvals/preflight, T005 actual Laravel scaffold + lock, T006 static config/credential review, T007 install procedure, T008 authored no-DB smoke, T009 **successful locked CI boot/smoke**.
- **Pending T010–T026**: explicit `ModuleRegistry` config/Provider and FoundationDemo lifecycle; negative/duplicate/missing provider tests; testing-only probe; module ownership tests; production route/cache absence; architecture constraints; code formatting, final CI/security/diff review, upstream stacked PR review and eventual merge authorization.
- No default User/Auth/Sanctum/Tenancy, Central/Tenant migrations or business APIs. Nothing deployed or merged. PR #1 and #2 remain **Draft and unmerged**, and D-201 OPEN / BA-16 DEFERRED / BA-30 G3 BLOCKED remain unchanged.
- [Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3) tracked the historical private-runner blocker; it can now be closed with actual green CI proof, noting the reported limit cause is owner-supplied and no billing amount/payment details were observed.

## 5. Next exact step without Codespaces

Continue **T010**, starting with negative configuration tests for the explicit Provider Registry, then the minimal native Laravel provider registry and one test-only demo following [the approved F0-01 plan](../../specs/001-laravel-bootstrap-module-registry/plan.md) and [tasks](../../specs/001-laravel-bootstrap-module-registry/tasks.md). Maintain genuine GitHub Actions checks and test-only route constraints; avoid any authentication/Tenant/database implementation or production route. Keep all PRs Draft/unmerged until T010–T026 and required reviews are complete.

Canonical [next-chat entrypoint](NEXT_CHAT_START_HERE.md); permanent handoff snapshot for this milestone. The owner performs **no Codespaces commands**.
