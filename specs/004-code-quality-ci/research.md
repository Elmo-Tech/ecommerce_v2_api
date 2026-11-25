# F0-04 — Technical compatibility research (read-only)

**Date:** 2026-11-25. Exact reviewed repository baseline `24ce00231dec35a089bb70c081b257f9b5d234e7`. Published package/release metadata and source inspection only. No solver, installation, execution, scan or workflow dispatch. Candidates below are proposed for Plan approval, not approved final runtime evidence.

| Tool | Candidate / published evidence | Remaining actual gate |
| --- | --- | --- |
| PHP/Laravel/Pest | Existing PHP ^8.4; lock Laravel 13.43.0, Pest 5.3.7, Pest Laravel plugin 5.0.1, PHPUnit 13.4.7; prior PHP 8.4.28 | Preserve existing package records; verify actual interpreter and extensions |
| Pint | 1.32.3; [Packagist](https://packagist.org/packages/laravel/pint) states PHP ^8.3 and json/mbstring/tokenizer/xml; [Laravel 13 Pint](https://laravel.com/docs/13.x/pint) PSR-12/read-only check | Real isolated solver, installation, positive/negative check |
| PHPStan | 2.3.7; [Packagist](https://packagist.org/packages/phpstan/phpstan), PHP ^7.4 or ^8.0; [levels](https://phpstan.org/user-guide/rule-levels) | Actual level 6 incl. first-party infrastructure and Pest constructs |
| Larastan | 3.15.0; [exact release composer.json](https://github.com/larastan/larastan/blob/v3.15.0/composer.json) requires PHP ^8.2, Illuminate ^13 accepted, PHPStan ^2.3.5, iamcal/sql-parser ^0.7 | Real lock and Laravel container compatibility in guarded scratch checkout |
| Redocly CLI | 2.83.0; [npm metadata](https://www.npmjs.com/package/@redocly/cli), [tagged manifest](https://github.com/Redocly/redocly-cli/blob/@redocly/cli@2.83.0/packages/cli/package.json); Node >=22.12 accepted, npm >=10 in manifest; published usage min npm 10.9.2 | npm lock integrity, no unsafe scripts, actual OAS 3.1 positive/negative and bundle validation; no cloud |
| Node | 24.22.3 candidate; [Node release](https://github.com/nodejs/node/releases/tag/v24.22.3), dated 2026-11-11; npm actual bundled version >=10.9.2 required | Record exact Node/npm and compatibility; if mismatch stop for revised pin |
| Trivy | 0.76.2; [immutable release](https://github.com/aquasecurity/trivy/releases/tag/v0.76.2) dated 2026-11-05; GitHub release API supplies SHA-256 below | Verify acquisition, signed/checksummed artifact identity, actual execution and updated DB; no current vulnerability result inferred |

Redocly sources: [struct](https://redocly.com/docs/cli/rules/common/struct), [no-unresolved-refs](https://redocly.com/docs/cli/rules/oas/no-unresolved-refs), [lint](https://redocly.com/docs/cli/commands/lint), [bundle](https://redocly.com/docs/cli/commands/bundle). Structural rules and ref resolution must be errors, including OAS 3.1. Use approved canonical documents; do not assert the CLI is a universal JSON Schema semantic validator or backward-compatibility detector. Public documentation is evidence of supported capabilities, not a tested match to our source.

Trivy release assets (linux/amd64 candidate; published digests retrieved from release API, bytes not downloaded):
- trivy_0.76.2_Linux-64bit.tar.gz: `sha256:bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf`
- trivy_0.76.2_checksums.txt: `sha256:802e4ac1418ed967cd3c4b901f4f71ca00b7363ede67778665a22d9f13f58c83`
- [Image scan docs](https://trivy.dev/docs/latest/target/container_image/), [filtering](https://trivy.dev/docs/latest/configuration/filtering/). Review trusted release/signature/checksum provenance at execution. No automatic scanner update, ignore-unfixed or stale DB fallback.

## Action commit candidates

Resolved public repo version refs to full commits and inspected action.yml at those exact revisions; all declare node24. This is source metadata, not an actual hosted runner result. Pin full SHAs rather than mutable tags.

| Action | Resolved version ref | Full proposed SHA |
| --- | --- | --- |
| actions/checkout | v6 | d23441a48e516b6c34aea4fa41551a30e30af803 |
| actions/upload-artifact | v7 | cf430e030ddbb5b0abf93d22962f4752f3646cd9 |
| actions/setup-node | v6 | 249970729cb0ef3589644e2896645e5dc5ba9c38 |
| actions/setup-python | v6 | ece7cb06caefa5fff74198d8649806c4678c61a1 |
| shivammathur/setup-php | v2 | eb7c497e18156a6bbabfef1d3a82760b9eda3962 |
| actions/download-artifact | v8 | 9000827ccba6bdab643e8b6fd33ac0654aef8333 |

Sources: exact GitHub commits and action.yml in the respective official repositories. Before executing: confirm provenance and runner version compatibility, action input/output behavior, artifact retention/download/read-only token, setup-php installed extensions and underlying download sources; pinning an action commit alone does not freeze all of its external inputs. [GitHub secure use](https://docs.github.com/en/actions/security-for-github-actions/security-guides/security-hardening-for-github-actions), [protected branches](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches). No admin settings changed.

## Repository-specific conflicts and decisions

1. Root composer.json currently has php/laravel only in require and Pest/Pest Laravel plugin only in require-dev. Lock inspected directly, no analyzer/formatter package. Preserve existing production and runner versions/records; added dev dependencies may need transitive packages; if solver requires changing an existing runtime or Pest record, stop for reviewed change rather than broadly update.
2. Existing `tests/Architecture/ApiFoundationBoundariesTest.php:28–30` and `TestingFoundationBoundariesTest.php:39–41` assert exactly the old dev dependency set. Lines 47–57 of the latter assert literal old workflow paths and needs relationship. These are genuine planned policy changes, not code defects to mask: update only those obsolete expectations to explicit new dev allowlist and equivalent/stronger structural CI policy assertions. Keep existing runtime dependency, route, guard, cleanup, reporting, no-privileged-workflow and no-migration rules. Map every changed assertion; preserve all old test identities.
3. F0-03 guard `tests/Support/TestEnvironmentGuard.php` validates before vendor autoload, rejects nonempty/symlinked dotenv and cached config. Larastan may bootstrap the app; a PHPStan bootstrapFiles setting alone may be too late for autoload. Require a fixed guarded launcher executing before Composer/PHPStan/Larastan startup, with inherited validated environment and tested early rejection.
4. Canonical `specs/002-api-foundation/contracts/openapi.json`: version 3.1.0, empty paths, seven schemas, one header, five responses and x-http-status-error-codes. Keep meaning intact, no fake endpoints or unused-component suppression of structural checks.
5. Existing Laravel workflow has path filters and optional tool notices; FND-01 Python workflow is separately path-filtered. Stable aggregate cannot depend on workflow-level paths skipping its run. Preserve existing relevant regression content, not necessarily legacy orchestration literals. Actual docs-only run must prove final gate is emitted.
6. No application Dockerfile or Node manifest on baseline. Isolate npm tooling from frontend; scan only exact test images. No production-image assessment claim.
7. Previous visible development branch protection disabled; detailed administrative inspection returned 403. Activation requires separately authorized owner/admin and actual verified status. No claim of permission, required reviewer count or active protection.

## Remaining verification gates

All candidate package versions, action SHAs and scanner identity above need owner Plan approval, then separate implementation authorization and actual locked execution. The exact npm/Composer transitive lock, analyzer findings, artifact compatibility, vulnerability DB freshness and actual image findings are unknown. If any compatibility/security blocker is material, stop affected work and present revised approved design rather than silently changing level, pins, scope or standards.


**Aggregate evidence transport refinement:** reviewed actions/download-artifact v8 candidate action.yml at 9000827ccba6bdab643e8b6fd33ac0654aef8333, Node 24, current-run download without cross-run token, digest mismatch default error. Proposed only; actual artifact compatibility, safe extraction/validation and minimal permission remain implementation checks.
