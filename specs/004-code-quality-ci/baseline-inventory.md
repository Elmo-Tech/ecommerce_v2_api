# F0-04 — actual baseline and regression inventory (T004–T005)

**Date:** 2026-11-25. **Status:** verified before adding quality packages or changing test policy. Approved candidate `e37fb5fccd3dbba5d84ba3947af1516b7e0de090`; development `24ce00231dec35a089bb70c081b257f9b5d234e7`.

Actual [baseline CI #45619658963](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45619658963), attempt 1, exact source `e37fb5fccd3dbba5d84ba3947af1516b7e0de090`, development base `24ce00231dec35a089bb70c081b257f9b5d234e7`, tested integrated checkout `f21773de78506005c351b1d82e5f2d7d1af48b1b`, tree `3b59eff71be9f59f1cd5734966d6e93552dee98a`. Required jobs 139224104560 and 139224503746 completed success; 417/2518 fast (7.743 s), 34/304 native (23.907 s, 26 PostgreSQL and 8 Redis), 31/134 foundation (2.331 s), 9/101 Architecture (0.200 s) independent subsets. 451 unique cases / 2822 assertions, zero errors/failures/skips; fallback BLOCKED steps only skipped. Actual PHP 8.4.28, Composer 2.11.3, pdo_pgsql 8.4.28, PhpRedis 6.3.0, approved PostgreSQL 17.12/Redis 8.2.11 image digests/IDs, actual grants and ACL setup, native denial suite, cleanup/repeat, safe upload/cleanup passed. Locked install/platform/audit and tracked PHP lint passed; no Composer advisories reported. Node20 action deprecation observed, retained for F0-04. Independently downloaded [fast 15024355891](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45619658963/artifacts/15024355891), [native 15023878344](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45619658963/artifacts/15023878344), [lock 15022888824](https://github.com/Elmo-Tech/ecommerce_v2_api/actions/runs/45619658963/artifacts/15022888824). Verified exact six/two/one files, no duplicate test identities/raw output/properties, recomputed nested counts/duration and matched exact source/base/checkout/run/attempt; independently compared all four complete case-name/class/per-case assertion inventories to actual prior #45614086597 artifacts 15021308120 and 15021047836: identical. Lock ZIP bytes compute Git blob 74af82d8111ce6bb2947affbea58aea53ee09325, SHA256 79f4d782af3693b3e666b025afee805beef1d363561d83b825fb5d47bef7f051. Seven-day retention metadata verified (rounding to seconds). FND-01 unchanged and not selected by old path filters; no FND rerun claimed. Only Laravel workflow comment and documentation changed; original behavior unchanged. No quality packages installed.

## Tracked source inventory

Actual recursive Git tree at `3b59eff71be9f59f1cd5734966d6e93552dee98a`, nontruncated, 261 tracked blobs. 90 PHP/CLI blobs, all regular files, no tracked PHP symlink. Include every listed file and newly tracked first-party PHP in future formatting/analysis; no new directory exclusion. Counts by root: app 16; artisan 1; bootstrap 2; config 10; lang 2; public 1; routes 2; tests 56.

| Tracked PHP / CLI path | Exact Git blob |
| --- | --- |
| `app/Core/Http/ApiErrorCodes.php` | `fb2868a638bb7f8d045b1060715bebdaa46368fb` |
| `app/Core/Http/ApiExceptionMapper.php` | `56495f9abdb69254b2ee8db38946f3afab8ce7f8` |
| `app/Core/Http/ApiFieldValidator.php` | `a0931407d56ee4a1239ada10fc38b60849a205fc` |
| `app/Core/Http/ApiLocaleResolver.php` | `87cba15e994355fb6805f1d4813f23c0e2088891` |
| `app/Core/Http/ApiMessages.php` | `cb87cebafd926df854a6f531c0c5e6d46b61cf10` |
| `app/Core/Http/ApiRequestContext.php` | `4099d42e6bfacae35064dabc8c496d134b039f04` |
| `app/Core/Modules/ModuleRegistry.php` | `c6e5ea1c8c0d8abc756dd98230d06f9fbaed2228` |
| `app/Enums/HttpStatusCode.php` | `9d79a8e86d24e33f9e8bedf8fcee3f674815c6fe` |
| `app/Helpers/ApiResponse.php` | `da01ab26cb4441f39a0cbddbc4a433ad1b236cce` |
| `app/Http/Controllers/Controller.php` | `8677cd5cabb06901eac3d8f9a500094d2c1dab45` |
| `app/Http/Middleware/AssignRequestId.php` | `20e85f858359c9c3deea0a74b516440b9e8fba4e` |
| `app/Modules/FoundationDemo/Contracts/FoundationMarker.php` | `c13faa1876fecb0dd9b5c2906315db6e396ef785` |
| `app/Modules/FoundationDemo/FoundationDemoServiceProvider.php` | `27846f62b91bfa7b2eeda599b31873072acfb161` |
| `app/Modules/FoundationDemo/Routes/testing.php` | `e87e8bdf6fa617261db5a366e340fb78b79662d4` |
| `app/Providers/AppServiceProvider.php` | `d2ae0acbc428864279afe480fbb5930ab7ad2f53` |
| `app/Providers/ModuleRegistryServiceProvider.php` | `499e27747b534c12e0f6536551bfde0a600fc112` |
| `artisan` | `c35e31d6a29f62921a8621944eb20ad162f5b2bb` |
| `bootstrap/app.php` | `931f7f2aea190edcf3fcb292744b363a7dba3aae` |
| `bootstrap/providers.php` | `4c7b6ba5d91acb74a2115647611c5726c52b7e9f` |
| `config/app.php` | `dfa47ba05c1c5f460f788307bd56b75b546b4758` |
| `config/cache.php` | `4efdd3bf47a8b3171c965e68f0e205e1ef98bb1a` |
| `config/database.php` | `839911dfcdf94767cdc02d8146db57710a9b919f` |
| `config/filesystems.php` | `e8226a80b1415539675dfecac0a85f65a22037e1` |
| `config/logging.php` | `97bdc0390b3cb6e55231fa12bdf50d649d37b400` |
| `config/mail.php` | `e32e88da2cc82d4139c032c28b03005afc6008c6` |
| `config/modules.php` | `5dbdc3bee742dba99e82e8f43a6fbce94953f0a8` |
| `config/queue.php` | `d0e0f50e56e5916e7de6284ce828414342fa4cb2` |
| `config/services.php` | `ec226a406664750084d2e49b32fa3b7d03a31ff4` |
| `config/session.php` | `b2a3af8f38c9437e541ac4868e4d57f00d847061` |
| `lang/ar/api.php` | `7be47245bd7c113721b9f564e907a08df9d5ea57` |
| `lang/en/api.php` | `05d36a116e6a7366a794b952c071b1d6d797ca5e` |
| `public/index.php` | `ee8f07e993802c29e166435e55e302ebfff1c12b` |
| `routes/api.php` | `b3a4f05f9206b4cd83abfd83f66daeed4d2580f2` |
| `routes/console.php` | `10e5d2d6e8ff6bf3c7caa4b84d63956f397a96b5` |
| `tests/Architecture/ApiFoundationBoundariesTest.php` | `e22e9de2acabb213eafed4a011eca4c78a004aa5` |
| `tests/Architecture/ModuleRegistryBoundariesTest.php` | `53c39b6f648e304e3900ec2fd61b369f4540a339` |
| `tests/Architecture/TestingFoundationBoundariesTest.php` | `7c02caf186e49e4d65d6c17a36ce91b1362b59d1` |
| `tests/Feature/Foundation/ApiExceptionContractTest.php` | `77c3d40736c0ea045e59ec0f572a5a313f483a2c` |
| `tests/Feature/Foundation/ApiLocalizationTest.php` | `df5d160df668a9698574cfff099aaf39bf3f7008` |
| `tests/Feature/Foundation/ApiOpenApiContractTest.php` | `cabe8c08f980ca8570eb308c4e8a051e753ccfaf` |
| `tests/Feature/Foundation/ApiRequestLifecycleTest.php` | `3ef611b54986b3c43c99d0fc5f673f34c9be26b8` |
| `tests/Feature/Foundation/ApiRouteIsolationTest.php` | `0a3183d3a92b4e39ffd3f5a4e99aa1360ef1891c` |
| `tests/Feature/Foundation/ApiValidationContractTest.php` | `4b6f45e8ddb57e680e15162df27ca2a4598f1bba` |
| `tests/Feature/Foundation/BootstrapSmokeTest.php` | `ef2be3f2600d0693e279c728b003b26e2d171baf` |
| `tests/Feature/Foundation/ExplicitModuleRegistrationTest.php` | `fae5da8365e9964004e126178cacadd5ba4f23f5` |
| `tests/Feature/Foundation/FoundationProbeIsolationTest.php` | `4427838b0e45eb8dd9d1b19e1889db80cf1ddf6d` |
| `tests/Feature/Foundation/InvalidModuleRegistrationTest.php` | `75737d5d0cbc8497203592fd3e01835c08336a3e` |
| `tests/Feature/Foundation/TestEntrypointSafetyTest.php` | `888720cffc5ef3649b5d426fd3f35beb772907ab` |
| `tests/Fixtures/AbstractModuleProvider.php` | `6d48acf9a8e030e54a99b57fe212c09f42c49af4` |
| `tests/Fixtures/ApiFixtureRequest.php` | `54ce014d24a74461845e29bfe710ff9b0235bce4` |
| `tests/Fixtures/ApiHttpFixtures.php` | `4e2067fc85df786aa6832e66fe12e60c42c8569c` |
| `tests/Fixtures/FoundationOwnedRunFixture.php` | `2ca27ddcfe4ccbe8f109fdba50f99ef2bd8f5f2d` |
| `tests/Fixtures/FoundationTestCheckout.php` | `2c09dbe1dfe6d1a969f6eb5401dce0843e81dfd7` |
| `tests/Fixtures/isolated-module-bootstrap.php` | `c259a04f8753e9fb69a3e15747e042fcaf8473cb` |
| `tests/Integration/Foundation/PostgreSqlIsolationTest.php` | `8bf9ac8aaf28f7a5f969e32fd371e33397bc4542` |
| `tests/Integration/Foundation/RedisIsolationTest.php` | `015aeaf4a029b3a99673dd730c4ee512edc1d551` |
| `tests/Pest.php` | `cac7eb60c812967f42259e92b99f315d3236d2f3` |
| `tests/Support/IntegrationConnections.php` | `7e4530db621071e1580e35e6383d8764b7769de3` |
| `tests/Support/IntegrationContext.php` | `960a25604781e9e1debac51fc7d10b1378a6d8ce` |
| `tests/Support/LocalDocker.php` | `a750569fc1158e57dfc8c2296e01aa9cdedbf841` |
| `tests/Support/OwnedTestRun.php` | `bffce648f89e8e79964e3589d2d947c2b29e68d2` |
| `tests/Support/TestEnvironmentGuard.php` | `9ddee908f07cead8476ff886e253e812810c3563` |
| `tests/Support/bootstrap.php` | `a2696a2409b6ca92f34ac3d3a0a38000f0bf344a` |
| `tests/Support/ci/CleanupPolicy.php` | `337f34327ec4508af4588dcf79fc570f3e1b049e` |
| `tests/Support/ci/OwnedServicePlan.php` | `ee8f84579233674d2c5d30e28abcdb81bf8e83d6` |
| `tests/Support/ci/OwnedSetupFiles.php` | `bc7acf0aff7f312777f9e47764a4ff5f253866d1` |
| `tests/Support/ci/PostgreSqlFixturePlan.php` | `4450214b9638bcd2e297007efd6d1a9294e6b64f` |
| `tests/Support/ci/RedisFixturePlan.php` | `a351747db64d92453cbecb1a736ea9fc447237d6` |
| `tests/Support/ci/SafeTestReport.php` | `ec82dcd8f92238c649e0c13cb93f7d48f2c3c71f` |
| `tests/Support/ci/cleanup-owned.php` | `193e661b68c550a78da227017d0a5320999f4f9a` |
| `tests/Support/ci/cleanup-reports.php` | `a504501552bb4fbf5213c4127e60d37ef13a41e3` |
| `tests/Support/ci/run-report.php` | `42daa0a2eacf718a68a74383a2d7e7dc0cc69d54` |
| `tests/Support/ci/setup-owned.php` | `a41cb0fda4eb1f6bb7f1b1932e1c52660276e9e3` |
| `tests/Support/ci/start-owned.php` | `10ac4ffbb320fa4b347505cece7c09a10656003c` |
| `tests/Support/integration-bootstrap.php` | `1d9b5c568be7a6f0c841db5cfcfa085a206a97dc` |
| `tests/Support/run-tests.php` | `0b870dc98b8ac8695465c1898fb1dcd56f0c3a78` |
| `tests/TestCase.php` | `fe1ffc2ff1abc1c23522418994879623c5647859` |
| `tests/Unit/Core/ApiLocaleNegotiationTest.php` | `d7202724ff49530aa7c190d41a5a7be8b420b7e6` |
| `tests/Unit/Core/ApiOpenApiContractTest.php` | `4b63157b21bb16fcd89874b114162598031e949e` |
| `tests/Unit/Core/ApiResponseTest.php` | `266c62a39df73e5842aa6ac2b4a5c4027b7af1f1` |
| `tests/Unit/Core/ModuleRegistryValidationTest.php` | `75501ca59841b311c3c213fc0bc2e273e92e5af2` |
| `tests/Unit/Foundation/IntegrationEntrypointTest.php` | `9f8e6eefedbbaf185fbc532334ba77a34182a373` |
| `tests/Unit/Foundation/LocalDockerObservationTest.php` | `c58fb8010c8fcfd7a8b0bbc254b390154aaed852` |
| `tests/Unit/Foundation/NativeReportFailureTest.php` | `6549e73ce2dcda5515d6bfd1cd7a343d1741504a` |
| `tests/Unit/Foundation/OwnedCleanupPolicyTest.php` | `2f8cad4f611ed7d3c07f372a7d3baa230e0ed75e` |
| `tests/Unit/Foundation/OwnedFixturePlanTest.php` | `d25b710f752ffcac831effe4d6983ed95f8394c4` |
| `tests/Unit/Foundation/OwnedServicePlanTest.php` | `1d939fba1f9b9430e7ad5e4d5a20c4298feb03bc` |
| `tests/Unit/Foundation/OwnedTestRunTest.php` | `365f0c5a845c74137239ad5f4949cb391105141a` |
| `tests/Unit/Foundation/SafeTestReportTest.php` | `71c22468f313de7a59fa802b43f788eee9cffc91` |
| `tests/Unit/Foundation/TestEnvironmentGuardTest.php` | `be3a79fee7647dca230da05993eed46f80023c40` |

## Contract, workflow and lock boundaries

| Path | Exact Git blob |
| --- | --- |
| `.github/workflows/f0-01-laravel-bootstrap.yml` | `32ebce47f490f55c8639ba0a25795fa3b69c6fb1` |
| `.github/workflows/fnd-01-spec-kit-review.yml` | `d7c28c05bf63e15e83c945ce2443e04385c5ab32` |
| `composer.json` | `45ed7684ff7a779521d256bd78010093c78c7bcd` |
| `composer.lock` | `74af82d8111ce6bb2947affbea58aea53ee09325` |
| `phpunit.integration.xml` | `b656bf7bcdfcac196a1f884875a6b0e89c99f819` |
| `phpunit.xml` | `8e54c6cbb61b2bab70c99b6f1aca4cb953415f20` |
| `specs/001-laravel-bootstrap-module-registry/contracts/foundation-probe.md` | `2dc024227a4329be65724f379e29e9fc67d78187` |
| `specs/002-api-foundation/contracts/openapi.json` | `9259e81fd9e0676d0706acf66caa6e5cc53c615a` |

Only OpenAPI root is specs/002-api-foundation/contracts/openapi.json (3.1.0, empty paths, seven schemas, one header, five reusable responses). Foundation probe Markdown is historical documentation, not an OAS root. Both original workflows and all first-party test sources are identified above. F0-03 TestEnvironmentGuard.php guards before vendor startup; IntegrationContext/Connections, OwnedTestRun, LocalDocker and owned fixture/cleanup/report helpers must retain private job identities, pinned images, separate restricted roles, ACL, exact cleanup, fail-closed JUnit/JSON and safe artifact boundaries. Review these exact blobs on any affected change. FND-01 existing path filters and mutable actions are pending T026/T042; no behavior change in T005.

## Exact case inventory

Independently compared every tuple (classname, name, assertions) against prior artifact. Reproducible SHA256 of UTF-8 compact JSON sorted lexicographically by classname then name then integer assertions, using original classname, JSON ensure_ascii=false and separators comma/colon, no trailing newline. These digests preserve inventory comparison evidence beyond artifact expiry; source test blobs above remain available. Full case listings are in actual safe JUnit artifacts while retained.

| Selection | Cases | Assertions | Canonical tuple SHA256 |
| --- | ---: | ---: | --- |
| fast-all | 417 | 2518 | 2406a67b2a9d04931a9daada7ceb137a95c71b6c6942fdd57d2c4b0e2de4c19c |
| foundation | 31 | 134 | 7220964d0dbc54aa709d5433bb730a98e16dbcd86df657b81db19c5424f492ca |
| architecture | 9 | 101 | 45826e18a6978d2ffae08efd5a74f5a4f35999441e313dd49c204cc361007d20 |
| integration | 34 | 304 | cd007b304626ba697999c6729ae7af353b8039998b0a5b6d338ae6989e78af19 |

## Existing dependency inventory (no change authorized by T005)

Composer lock full-byte blob above; 74 production records and 44 dev records, content hash e1e42035550a8407ed60e95dd6673e77. Preserve every existing full package record and its production/dev classification, not just its version. The entire original lock remains available at approved source. Existing root require = php, laravel/framework; require-dev = pestphp/pest, pestphp/pest-plugin-laravel; plugin allowlist = pestphp/pest-plugin only. T006 actual solver/preflight must compare full JSON records and reject old record drift.

| Scope | Package | Locked version | Source reference |
| --- | --- | --- | --- |
| runtime | brick/math | 1.0.0 | `2effe05d2177c451b86c6a073196a4034c02f211` |
| runtime | carbonphp/carbon-doctrine-types | 3.2.1 | `5fa5eacafd9ef47c8c6ab9143fc901ba0194d3dc` |
| runtime | dflydev/dot-access-data | v3.0.3 | `a23a2bf4f31d3518f3ecb38660c95715dfead60f` |
| runtime | doctrine/inflector | 2.1.1 | `46351b6812b7b6d52b82c3efa5dfb786a198ec88` |
| runtime | doctrine/lexer | 3.0.3 | `71d305eaa6480d3d3460c7d053d1da7749fa9cca` |
| runtime | dragonmantank/cron-expression | v3.6.1 | `4189372ffeff09bf14f731f67a9de90c7b413ebe` |
| runtime | egulias/email-validator | 4.0.5 | `9bef884e12278a7b89b64adbfb9379c021d3c102` |
| runtime | fruitcake/php-cors | v1.4.0 | `38aaa6c3fd4c157ffe2a4d10aa8b9b16ba8de379` |
| runtime | graham-campbell/result-type | v1.2.0 | `adccca3324eece92ca35463648c12b9e6293c05b` |
| runtime | guzzlehttp/guzzle | 8.6.0 | `ba6574fd975ab16f87dc356729a36777c03851e8` |
| runtime | guzzlehttp/promises | 3.2.0 | `a201cc78db93065f891278f86eaeaa1c97c5a158` |
| runtime | guzzlehttp/psr7 | 3.6.0 | `1fa0f8319c046b7dec80030460254283d851388e` |
| runtime | guzzlehttp/uri-template | v2.0.1 | `7a466ad606491eb6528c717482f7cca77f1851f3` |
| runtime | laravel/framework | v13.43.0 | `72aa7e151062e218704d8660f407e6070040d8bc` |
| runtime | laravel/prompts | v0.3.27 | `abdfc75a1ae6c6f21522daed50da7124cbff09d2` |
| runtime | laravel/serializable-closure | v2.2.0 | `2bfb44e8af009b553ed2c060c94b0bc3c67b4769` |
| runtime | league/commonmark | 2.10.7 | `8eb53e906fd60c67038d22f1f1cab420ed58b8be` |
| runtime | league/config | v1.2.0 | `754b3604fb2984c71f4af4a9cbe7b57f346ec1f3` |
| runtime | league/flysystem | 3.36.0 | `f7fb152932f30072d573510cbd4dd657d6475b25` |
| runtime | league/flysystem-local | 3.35.3 | `a099b24dce160f3b2239043d13d47c4a1a214ea4` |
| runtime | league/mime-type-detection | 1.17.0 | `f5f47eff7c48ed1003069a2ca67f316fb4021c76` |
| runtime | league/uri | 7.10.0 | `be89e2382e37acdef232259071ade5180821397b` |
| runtime | league/uri-interfaces | 7.10.0 | `ea66240cff4ae9a56ce85d577a04d038b771e51c` |
| runtime | monolog/monolog | 3.13.0 | `1f5f55bb4a9a230ff6d8a4ceccea31d10b0142ac` |
| runtime | nesbot/carbon | 3.15.1 | `ce0e5636fae7d1e99856eb70cae4c719a284aa08` |
| runtime | nette/schema | v1.4.0 | `af9fa8a86bfca9b02b10346220db26e62ef942ec` |
| runtime | nette/utils | v4.1.6 | `6bf24518accf32cf845bca305e0b92078700fd01` |
| runtime | nunomaduro/termwind | v2.4.1 | `9b85ae4d802403bfc004ea633ce542bc262ee5c3` |
| runtime | phpoption/phpoption | 1.10.0 | `67b192b6a42ec03944b972d6e633ddec78ad2c6d` |
| runtime | psr/clock | 1.0.0 | `e41a24703d4560fd0acb709162f73b8adfc3aa0d` |
| runtime | psr/container | 2.0.2 | `c71ecc56dfe541dbd90c5360474fbc405f8d5963` |
| runtime | psr/event-dispatcher | 1.0.0 | `dbefd12671e8a14ec7f180cab83036ed26714bb0` |
| runtime | psr/http-client | 1.0.3 | `bb5906edc1c324c9a05aa0873d40117941e5fa90` |
| runtime | psr/http-factory | 1.1.0 | `2b4765fddfe3b508ac62f829e852b1501d3f6e8a` |
| runtime | psr/http-message | 2.0 | `402d35bcb92c70c026d1a6a9883f06b2ead23d71` |
| runtime | psr/log | 3.0.2 | `f16e1d5863e37f8d8c2a01719f5b34baa2b714d3` |
| runtime | psr/simple-cache | 3.0.0 | `764e0b3939f5ca87cb904f570ef9be2d78a07865` |
| runtime | ramsey/collection | 2.1.1 | `344572933ad0181accbf4ba763e85a0306a8c5e2` |
| runtime | ramsey/uuid | 4.10.0 | `f9aa3efc8015fcbc013d84d699583b268ccb593a` |
| runtime | symfony/clock | v8.1.9 | `945ea3b64d6da9c1c2c354fe5c07b27455df6442` |
| runtime | symfony/console | v8.1.11 | `5a315b092b81f3e920e9daea1a2ae4c66ed298b9` |
| runtime | symfony/css-selector | v8.1.10 | `9cfd588b8b07cf83e3426b030dbafd2291c132d9` |
| runtime | symfony/deprecation-contracts | v3.8.1 | `feb1edf4e546af3b85c5caebe8359a20db230ca3` |
| runtime | symfony/error-handler | v8.1.10 | `84c6d73f990ae22dbd45625c056d7ff99ca6810d` |
| runtime | symfony/event-dispatcher | v8.1.5 | `7458da64220376b2e0dc2d8451bf43382c1ad297` |
| runtime | symfony/event-dispatcher-contracts | v3.8.1 | `658e29751424da3ef4758c60f769e59d1b2981eb` |
| runtime | symfony/finder | v8.1.11 | `a2902e479ba5691d20d51d100a715ea19ad2c837` |
| runtime | symfony/http-foundation | v8.1.11 | `30fa3efe6e736b0d06f5331cc6abe813582c0a26` |
| runtime | symfony/http-kernel | v8.1.11 | `460658504e26a2794aa614b4fbe6cf9bac2076fe` |
| runtime | symfony/mailer | v8.1.10 | `4ba81a8f04fbf0bb1691f7bff760b301f1956fd7` |
| runtime | symfony/mime | v8.1.10 | `8d1232052c9cdef170ed9608b3147bca4f61ff69` |
| runtime | symfony/polyfill-ctype | v1.37.0 | `141046a8f9477948ff284fa65be2095baafb94f2` |
| runtime | symfony/polyfill-intl-grapheme | v1.43.3 | `5a8f33c87aeecffce24e721b5064fab05f4becf8` |
| runtime | symfony/polyfill-intl-idn | v1.43.0 | `533d68cf99c8f8ee2e8893c59ed59192e92cfd74` |
| runtime | symfony/polyfill-intl-normalizer | v1.43.0 | `ebd57ccc7edb93d632e184efbe7693d2517bab3b` |
| runtime | symfony/polyfill-mbstring | v1.43.3 | `122889ed24dc000165bcbfd77ec412f15cbdfc6a` |
| runtime | symfony/polyfill-php80 | v1.43.0 | `9c6a5d6b01ca51d486c813e9a9c0ed55f208bb74` |
| runtime | symfony/polyfill-php82 | v1.43.0 | `843bbe8b2a7934307b749751eede0f111f5d1cfc` |
| runtime | symfony/polyfill-php84 | v1.43.0 | `22c6548499cbe5c5bb072fc0c42312dff062a015` |
| runtime | symfony/polyfill-php85 | v1.44.0 | `94c794cb5ba51784bc259f11e9f128593297d8dc` |
| runtime | symfony/polyfill-php86 | v1.43.0 | `8840df45b24f33a49dc419fa18dc0057231ae1d8` |
| runtime | symfony/polyfill-time | v1.42.0 | `ac9f1ed802dd58f37e720388130c088434aa09fc` |
| runtime | symfony/polyfill-uuid | v1.43.0 | `b23ff8245cc5c4479ea2be3d0132d2e6dd433993` |
| runtime | symfony/process | v8.1.10 | `08aa69fa0998bb65a089f9e148a2c0f8b391398e` |
| runtime | symfony/routing | v8.1.11 | `f3fefc6cad3748d538be970ca81b71f06d7aa8b3` |
| runtime | symfony/service-contracts | v3.8.1 | `052852a96fd5e5ebdb617dba22031e41a3300fb5` |
| runtime | symfony/string | v8.1.10 | `6c6110979c0150393bbb336170e286d51ab4d76c` |
| runtime | symfony/translation | v8.1.11 | `d065adf56e5508293e80d7c140b0a9e043b63e9c` |
| runtime | symfony/translation-contracts | v3.8.1 | `0a3ae917402632fcba2aeddae9c98332f3d7dd05` |
| runtime | symfony/uid | v8.1.9 | `cb487cf30380f0b0b090973bb9c0588193c06685` |
| runtime | symfony/var-dumper | v8.1.10 | `3412a5af7d34a19515a77890fc804fe8e50d1a4f` |
| runtime | tijsverkoyen/css-to-inline-styles | v2.4.0 | `f0292ccf0ec75843d65027214426b6b163b48b41` |
| runtime | vlucas/phpdotenv | v5.8.1 | `ba37876e98122ca15151247ce1187d20ee8bf4e0` |
| runtime | voku/portable-ascii | 2.1.1 | `8e1051fe39379367aecf014f41744ce7539a856f` |
| dev | brianium/paratest | v7.29.0 | `2e4bbc8340be877d4f1a82f993a387e2c8c76272` |
| dev | doctrine/deprecations | 1.2.0 | `d2e16653a734c8784c1851ad68b02f997b53ca82` |
| dev | fidry/cpu-core-counter | 1.4.1 | `d384a7d0d6e67aa497327260ca95023e92dc2032` |
| dev | filp/whoops | 2.19.3 | `d5480d53f10069dc4620a39f87e38e8945d47322` |
| dev | jean85/pretty-package-versions | 2.1.1 | `4d7aa5dab42e2a76d99559706022885de0e18e1a` |
| dev | myclabs/deep-copy | 1.14.0 | `8680aa248f8e07bc8fb43f56f0f5fc77a0c96aae` |
| dev | nikic/php-parser | v5.9.0 | `9e33da9553fe7786f0962b35f4e4ecf01be89def` |
| dev | nunomaduro/collision | v8.9.6 | `68806f9257968f23775bee62970cb90925b79d51` |
| dev | pestphp/pest | v5.3.7 | `ff1292102caee679da7294b239b434e93db5cf42` |
| dev | pestphp/pest-plugin | v5.0.0 | `87283f41aafa2561b7618be53eab00f2d06f4140` |
| dev | pestphp/pest-plugin-arch | v5.0.0 | `244465d5dc9ea9fb5ac5c9badb7eb47d1594e4c1` |
| dev | pestphp/pest-plugin-laravel | v5.0.1 | `d1564646e3198f1b607e64a36501d6edff7d104e` |
| dev | pestphp/pest-plugin-mutate | v5.0.2 | `d0c87110416b699413cc073b8dcc92e440d81931` |
| dev | pestphp/pest-plugin-profanity | v5.0.1 | `2b5fc73ce561b835ba5bf9596c97a84333af2eb8` |
| dev | phar-io/manifest | 2.0.4 | `54750ef60c58e43759730615a392c31c80e23176` |
| dev | phar-io/version | 3.2.1 | `4f7fd7836c6f332bb2933569e566a0d6c4cbed74` |
| dev | phpdocumentor/reflection-common | 2.2.1 | `ee15a5a2022c8383906b225055e9496a6a5e6e3b` |
| dev | phpdocumentor/reflection-docblock | 6.0.3 | `7bae67520aa9f5ecc506d646810bd40d9da54582` |
| dev | phpdocumentor/type-resolver | 2.1.0 | `dbe2133bb23e2e33a6834f4ed417f24a1b167769` |
| dev | phpstan/phpdoc-parser | 2.4.1 | `af8e840551031d254cfdb3a18a62c3470a309c10` |
| dev | phpunit/php-code-coverage | 14.5.2 | `2f122b9b48af71ad9b4448368febc26af86534c9` |
| dev | phpunit/php-file-iterator | 7.0.2 | `9bb4e6c58b62c1e043be995c66abec7c97307aae` |
| dev | phpunit/php-invoker | 7.0.0 | `42e5c5cae0c65df12d1b1a3ab52bf3f50f244d88` |
| dev | phpunit/php-text-template | 6.0.1 | `97107691cb2b6c743b5e0c44be19e983cb337a02` |
| dev | phpunit/php-timer | 9.0.0 | `a0e12065831f6ab0d83120dc61513eb8d9a966f6` |
| dev | phpunit/phpunit | 13.4.7 | `e6458d44279e3b4fc492d4c41fe8e677efed7a2c` |
| dev | sebastian/cli-parser | 5.0.1 | `eeb759ad3146b7096fb59c3195d39e071cd409e3` |
| dev | sebastian/comparator | 8.4.0 | `3b070e608146cba00fd6fd1f0ffba89e5a8897fb` |
| dev | sebastian/complexity | 6.0.0 | `c5651c795c98093480df79350cb050813fc7a2f3` |
| dev | sebastian/diff | 9.0.1 | `a2df6626c1baf31d5a88674882a3072f151b5a26` |
| dev | sebastian/environment | 9.3.3 | `d4dd10267a11055066a2a78ae04583d8ddd4f595` |
| dev | sebastian/exporter | 8.2.1 | `24a3b69bba4a12ab615fca9d34680c5598d9ab7a` |
| dev | sebastian/file-filter | 1.0.0 | `33a26f394330f6faa7684bb9cc73afb7727aae93` |
| dev | sebastian/git-state | 1.1.0 | `31a565f463686f0eefa85f899549256bf5805985` |
| dev | sebastian/global-state | 9.0.1 | `ba68ba79da690cf7eddefd3ce5b78b20b9ba9945` |
| dev | sebastian/lines-of-code | 5.0.2 | `d1b6f8fce682505dbd048977f1abedf1b8ad3ff8` |
| dev | sebastian/object-enumerator | 8.1.0 | `511064ecde82bd747e2ba2fab3dda8d977b59576` |
| dev | sebastian/object-reflector | 6.1.0 | `f71bbcdc4f95456b4622810bec64eb06372e25b2` |
| dev | sebastian/recursion-context | 8.0.1 | `32dba72f2b4642d6a93db22d6c0a9280ff2e3ca0` |
| dev | sebastian/type | 7.0.2 | `bd1df467864cb95140414059a535b2d906173fcf` |
| dev | sebastian/version | 7.1.0 | `08e41d0c7e146bfc8cef7e63989b1294453a6d6a` |
| dev | staabm/side-effects-detector | 1.0.5 | `d8334211a140ce329c13726d4a715adbddd0a163` |
| dev | ta-tikoma/phpunit-architecture-test | 0.8.7 | `1248f3f506ca9641d4f68cebcd538fa489754db8` |
| dev | webmozart/assert | 2.5.1 | `e578132c01bd0c64108386ab20d8217cd175ddec` |

## Explicit before/after architecture policy mapping (planned, not edited)

| Existing file / line and exact case | Old policy | Required replacement / retained coverage |
| --- | --- | --- |
| ApiFoundationBoundariesTest.php:28–30; `has an empty native API seam and no fixture dependency or unapproved packages at this foundation milestone` | Exact two dev package keys | Exact five approved dev keys: Pest, Pest Laravel plugin, laravel/pint, phpstan/phpstan, larastan/larastan; exact candidate version and plugin policy separately asserted, reject unexpected runtime/dev/plugin. Preserve original case name, exact runtime php/laravel, empty routes and no migrations (lines 29,31–35). |
| TestingFoundationBoundariesTest.php:39–42; `keeps the production bootstrap providers and routes free from testing support dependencies` | Exact two dev keys | Same explicit five dev keys and rejection controls; preserve existing case, runtime allowlist, no testing support in production bootstrap/routes/providers, Pest Feature registration and no migrations (lines 35–40,42). |
| TestingFoundationBoundariesTest.php:47–57; `keeps narrow relevant workflow triggers, guarded required commands and no privileged or broad cleanup path` | Literal path filters and old workflow wiring | Replace obsolete literal path selection with parsed/verified all-development-PR stable quality gate, deterministic classifier and required outcomes; preserve least privilege, negative forbidden commands, both XML and all four guarded run-report selections, native needs bootstrap dependency, launcher preflight, start/setup/cleanup always, no continue-on-error/raw XML, seven-day retention. Add immutable action refs, evidence transport and no path-suppressed gate. Do not rename/drop case or reduce substantive protection. |

Other Architecture cases and assertions remain unchanged unless a specific further approved policy conflict is demonstrated. T007/T026 write RED before relevant implementation; T044 and T046 must compare per-case inventory and assertions, document only reviewed policy deltas. T005 does not authorize a blanket baseline, hidden exclusion, lowered level or weakened assertions. No tooling executed beyond actual existing baseline; T006 compatibility remains open.
