<?php

declare(strict_types=1);

it('keeps the fast XML on its three existing suites and changes only the configured bootstrap boundary', function (): void {
    $root = dirname(__DIR__, 2);
    $fast = simplexml_load_file($root.'/phpunit.xml', options: LIBXML_NONET);
    expect($fast)->not->toBeFalse();
    $names = [];
    $dirs = [];
    foreach ($fast->testsuites->testsuite as $suite) {
        $names[] = (string) $suite['name'];
        $dirs[] = (string) $suite->directory;
    }
    expect($names)->toBe(['Unit', 'Feature', 'Architecture']);
    expect($dirs)->toBe(['tests/Unit', 'tests/Feature', 'tests/Architecture']);
    expect((string) $fast['bootstrap'])->toBe('tests/Support/bootstrap.php');
})->group('testing-foundation');

it('has a distinct explicit integration XML with its own guard and no fast suite discovery', function (): void {
    $root = dirname(__DIR__, 2);
    expect(is_file($root.'/phpunit.integration.xml'))->toBeTrue();
    $integration = simplexml_load_file($root.'/phpunit.integration.xml', options: LIBXML_NONET);
    expect($integration)->not->toBeFalse();
    expect((string) $integration['bootstrap'])->toBe('tests/Support/integration-bootstrap.php');
    expect(count($integration->testsuites->testsuite))->toBe(1);
    expect((string) $integration->testsuites->testsuite['name'])->toBe('Integration');
    expect((string) $integration->testsuites->testsuite->directory)->toBe('tests/Integration');
    expect(file_get_contents($root.'/tests/Pest.php'))->not->toContain('RefreshDatabase::class', 'DatabaseTransactions::class');
    expect(file_get_contents($root.'/tests/TestCase.php'))->not->toContain('RefreshDatabase', 'DatabaseTransactions');
})->group('testing-foundation');

it('keeps the production bootstrap providers and routes free from testing support dependencies', function (): void {
    $root = dirname(__DIR__, 2);
    foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'routes/api.php', 'routes/console.php', 'app/Providers/AppServiceProvider.php'] as $file) {
        expect(file_get_contents($root.'/'.$file))->not->toContain('Tests\\Support', 'F03_RUN_DESCRIPTOR', 'f03_probe');
    }
    expect(file_get_contents($root.'/tests/Pest.php'))->toContain("->in('Feature')");
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(array_keys($manifest['require']))->toEqualCanonicalizing(['php', 'laravel/framework']);
    expect(array_keys($manifest['require-dev']))->toEqualCanonicalizing(['pestphp/pest', 'pestphp/pest-plugin-laravel']);
    expect(glob($root.'/database/migrations/*.php') ?: [])->toBe([]);
})->group('testing-foundation');

it('keeps narrow relevant workflow triggers, guarded required commands and no privileged or broad cleanup path', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = file_get_contents($root.'/.github/workflows/f0-01-laravel-bootstrap.yml');
    expect($workflow)->toContain('tests/**', 'app/**', 'bootstrap/**', 'config/**', 'composer.lock', 'contents: read');
    expect($workflow)->not->toContain('pull_request_target:', 'docker system prune', 'migrate:fresh', 'db:wipe', 'FLUSHALL', 'FLUSHDB');
    expect($workflow)->toContain('phpunit.integration.xml', 'run-report.php foundation', 'run-report.php fast-all', 'run-report.php architecture', 'run-report.php integration', 'needs: bootstrap');
    $reportRunner = file_get_contents($root.'/tests/Support/ci/run-report.php');
    expect($reportRunner)->toContain("'/tests/Support/run-tests.php'", "'foundation' => ['fast', '--group=foundation']", "'fast-all' => ['fast']", "'architecture' => ['fast', '--suite=Architecture']", "'integration' => ['integration']");
    $launcher = file_get_contents($root.'/tests/Support/run-tests.php');
    expect($launcher)->toContain("'/phpunit.xml' : '/phpunit.integration.xml'", 'IntegrationConnections::preflight');
    expect($workflow)->toContain('start-owned.php', 'setup-owned.php', 'cleanup-owned.php', 'always()', 'cleanup-reports.php', 'integration-safe.xml');
    expect($workflow)->not->toContain('continue-on-error: true', 'integration-raw.xml');
    expect($workflow)->toContain('retention-days: 7');
})->group('testing-foundation');
