# F0-02 — scoped hardening RED reproduced and fixed

Actual [Actions #45464973375](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45464973375), job 138697336509, head `e9247e64eea4e30f1bb873754f7fa47385a7c6e0`, actual merge checkout `76a5eba` against development `43f309749c544040385977a530e78fd378f43b91`: baseline group 31/134, full **4 failed / 83 passed / 667 assertions**. Failures reproduce missing field-name schema, noncanonical direct-helper field names, distinct short-lived exception deduplication (1 rather than 2 diagnostics), and invalid list associative-key exposure. Custom render finalization passes. No unrelated regressions observed; later checks skipped due to RED.

Narrow fixes: central canonical field path guard shared by mapper/helper, JSON property-name schema; suppress descendants of malformed declared native Laravel list rules; per-request WeakMap instead of recycled integer object IDs; do not resolve API mapper for unrelated non-v1 responses. Future Feature reviews must declare safe list rules and explicit dynamic-map field paths (Laravel wildcard expansion cannot itself establish trusted field names). Generic safe validation wording remains Feature-refinable, not raw validator strings.

Await actual final GREEN; no merge or production release authorized. No dependency/lock/migrations/business behavior changes.
