# F0-01 — Private-repository GitHub Actions runner diagnosis (narrowed, not confirmed)

**Date:** 2026-11-23 (Africa/Cairo)  
**Repository:** [Elmo-Tech/ecommerce_v2_api](https://github.com/Elmo-Tech/ecommerce_v2_api)  
**Feature:** `feature/f0-01-laravel-bootstrap`, [Draft PR #2](https://github.com/Elmo-Tech/ecommerce_v2_api/pull/2) stacked on Draft PR #1  
**User instruction:** Continue autonomously; **no manual Codespaces instructions**. No authorization to change org billing, payment settings, repository visibility, merge or deploy.

## What was actually checked this turn

1. **GitHub connector capabilities:** Repository metadata confirms `ecommerce_v2_api` is **private** and the installed GitHub connection has repository `admin` permission. Connector has methods for GitHub Actions reruns, jobs, steps, logs and artifacts, but **none for organization billing/budgets/runner policies**. Its organization-membership endpoints returned an empty list, which cannot establish the user's actual org billing role.
2. **Failed F0-01 actions are real:** Retry of run [45217288046](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45217288046) created job `137850639683`, finished `failure` with **0 steps** and no artifacts; job-log request returned HTTP 404 `BlobNotFound`. Repeated previous jobs were likewise zero-step failures. Not a proven Laravel/Composer error because commands never started.
3. **Same-organization control:** [Elmo-Tech/restaurant-qr-platform](https://github.com/Elmo-Tech/restaurant-qr-platform) is **public**; workflow run [45189505578](https://github.com/Elmo-Tech/restaurant-qr-platform/actions/runs/45189505578) succeeded and job `137743820644` had **17 steps**. This shows GitHub-hosted Actions can run for **at least one public repo** in the organization, not that private Actions billing/quota is healthy.
4. **Other private repo checks:** `Elmo-Tech/monferroni-home` and `Elmo-Tech/elmotech_pos_desktop` are private; the inspected recent PR head SHAs showed no PR-associated workflow-run data via the available API. This is **inconclusive**; do not count it as pass/fail.
5. **GitHub service status:** [Official GitHub Status](https://www.githubstatus.com/) showed **Actions Operational**, with no reported incident on 2026-11-23 at review time. A global Actions outage is not supported by this available evidence.
6. **Relevant GitHub documentation:** [Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions) says standard public-repo runners are free while private-repo runs draw from included/billable compute; [budgets](https://docs.github.com/en/billing/how-tos/set-up-budgets) says exhausting a hard-stop budget can block hosted runners; [workflow troubleshooting](https://docs.github.com/en/actions/how-tos/troubleshoot-workflows) identifies billing and runner restrictions as checks.

## Evidence-based working hypothesis (NOT the root cause yet)

Because the private repo's jobs terminate **before any steps**, while a public repo's Actions executes, prioritize checking **private-repo Actions compute budget/quota/payment method and repo/org Actions runner policies**. Do **not** assert that billing has run out; both billing and policy are still **unverified**. Do not change any billing/settings, make repo public, rerun the same failing job repeatedly, or proceed to Laravel implementation tests without a working runner.

## Exact next non-Codespaces evidence to obtain

The GitHub connector **cannot view private Organization billing or runner-policy screens**. A user with owner/billing-manager access to `Elmo-Tech` can read the GitHub UI:

- [Affected workflow run](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45217288046): look at the run summary **red error banner / annotations**, not just job logs (logs are absent).
- Organization **Settings → Billing & licensing → Budgets and alerts / Actions usage**: look for hard-stop Actions budget, remaining included compute quota, billing lock/payment notices. **Read only**; redact any payment details.
- Repository **Settings → Actions → General** and organization **Settings → Actions**: confirm Actions are allowed and GitHub-hosted runners may be used by this private repository. **Read only**.

If the banner/policy/budget UI supplies a concrete error, update [Issue #3](https://github.com/Elmo-Tech/ecommerce_v2_api/issues/3), fix only with separate authorization when financially or organizationally sensitive, and rerun the existing F0-01 workflow to generate actual `composer.lock` and smoke-test logs.

## Unchanged feature gates

- T001–T004 completed. T005 **partial/blocked** (Laravel 13 scaffold staged; no `composer.lock`, installed `vendor/` or Laravel boot).
- T006–T008 completed only for scoped static review/docs/authored test source. T009–T026 pending; **7/26 tasks checked**.
- PR #1 and PR #2 remain **Draft/unmerged**, no Tenant/auth/db/migrations, CI success, deploy, production credentials or release.
- [Current task ledger](../../specs/001-laravel-bootstrap-module-registry/tasks.md), [previous runner handoff](F0-01_RUNNER_TRIAGE_AND_TEST_GUARDS_2026-11-23.md), [canonical entrypoint](NEXT_CHAT_START_HERE.md).

**No further Codespaces work requested from the owner. Root cause is still unconfirmed; no billing or private GitHub UI state was accessed.**
