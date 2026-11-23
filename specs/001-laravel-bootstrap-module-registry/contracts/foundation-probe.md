# F0-01 — FoundationDemo Probe Contract (TEST ONLY)

> **Status:** PROPOSED TEST CONTRACT — NO PRODUCTION API, NOT EXECUTED.  
> **Owner:** Technical Engineering Foundation (`app/Modules/FoundationDemo`)  
> **Reference:** [F0-01 spec](../spec.md), [plan](../plan.md), Constitution Principle V.

## Goal

Prove that one explicitly enabled, non-business Module Provider can register a container binding and a test HTTP route through the **same** Laravel lifecycle expected of future modules. This probe **must not** be installed in any normal merchant, storefront, Platform or customer production API.

## Proposed test-only behavior

| Property | Proposed contract |
| --- | --- |
| Test environment | `APP_ENV=testing` or genuinely equivalent isolated framework test environment, not a client-controlled value |
| Method/path | `GET /api/v1/__foundation/module-probe` |
| Response | HTTP `200` plus a minimal deterministic JSON marker (e.g. `{"module":"foundation-demo","source":"explicit-registry"}`) |
| Authentication/data | None. No identity, Tenant context, database operations or private data: this is strictly a test fixture |
| Outside testing | Route **absent from routing table**, not merely a public `403` handler |
| Distribution | No production OpenAPI, no public swagger contract, no deployment as a merchant-visible endpoint |
| Route cache | Route caches built for production must not include the test probe; testing cache files must never be shipped |

The exact marker payload is an **internal assertion proposal** and is not a user-facing standard envelope. The future F0-02 API Foundation will separately approve production error/resource contracts.

## Negative contracts

1. Module entry omitted from `config/modules.php` => no loaded binding or test route after isolated app rebuild.
2. Module directory exists but entry absent => no implicit discovery/activation.
3. Duplicate Module ID/Provider or missing/wrong-type Provider => predictable startup diagnostic, not silent ignore or unsafe fallback.
4. Production/non-testing boot => test route absent even with registered demo Provider and route caching.
5. Bootstrap/route probe => no DB, Tenant or queue effects; no storage writes outside ordinary isolated framework test fixtures.

**Stop condition:** If environment guard/route caching cannot reliably guarantee production exclusion under Laravel 13, redesign the test fixture and update this proposed plan before implementation.
