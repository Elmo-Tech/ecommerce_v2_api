<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\FoundationDependencyPolicy;

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
    FoundationDependencyPolicy::assertApproved($manifest);
    expect(glob($root.'/database/migrations/*.php') ?: [])->toBe([]);
})->group('testing-foundation');

it('keeps narrow relevant workflow triggers, guarded required commands and no privileged or broad cleanup path', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = file_get_contents($root.'/.github/workflows/f0-01-laravel-bootstrap.yml');
    expect($workflow)->toContain('tests/**', 'app/**', 'bootstrap/**', 'config/**', 'composer.lock', 'contents: read');
    expect(preg_match('/^  pull_request:\\n(?:(?!^  \\S).*[\\n])*/m', $workflow, $prTrigger))->toBe(1);
    expect($prTrigger[0])->toContain('development')->not()->toContain('paths:', 'paths-ignore:');
    expect($workflow)->not->toContain('pull_request_target:', 'docker system prune', 'migrate:fresh', 'db:wipe', 'FLUSHALL', 'FLUSHDB');
    expect($workflow)->toContain('phpunit.integration.xml', 'run-report.php foundation', 'run-report.php fast-all', 'run-report.php architecture', 'run-report.php integration', 'needs: [classify, bootstrap]', 'F0-04 / Quality Gate', 'verify-gate.py', 'digest-mismatch: error');
    $reportRunner = file_get_contents($root.'/tests/Support/ci/run-report.php');
    expect($reportRunner)->toContain("'/tests/Support/run-tests.php'", "'foundation' => ['fast', '--group=foundation']", "'fast-all' => ['fast']", "'architecture' => ['fast', '--suite=Architecture']", "'integration' => ['integration']");
    $launcher = file_get_contents($root.'/tests/Support/run-tests.php');
    expect($launcher)->toContain("'/phpunit.xml' : '/phpunit.integration.xml'", 'IntegrationConnections::preflight');
    expect($workflow)->toContain('start-owned.php', 'setup-owned.php', 'cleanup-owned.php', 'always()', 'cleanup-reports.php', 'integration-safe.xml');
    expect($workflow)->not->toContain('continue-on-error: true', 'integration-raw.xml');
    expect($workflow)->toContain('retention-days: 7');
    // The three fast selections must retain the pre-bootstrap environment guard.
    expect(substr_count($workflow, "if: \${{ !cancelled() && steps.fast_ready.outcome == 'success' }}"))->toBeGreaterThanOrEqual(3);
})->group('testing-foundation');

it('rejects unapproved runtime or development packages, Composer plugins and quality pins', function (): void {
    $approved = [
        'require' => ['php' => '^8.4', 'laravel/framework' => '^13.0'],
        'require-dev' => [
            'pestphp/pest' => '^5.0',
            'pestphp/pest-plugin-laravel' => '^5.0',
            'laravel/pint' => '1.32.3',
            'phpstan/phpstan' => '2.3.7',
            'larastan/larastan' => '3.15.0',
        ],
        'config' => ['allow-plugins' => ['pestphp/pest-plugin' => true]],
    ];
    FoundationDependencyPolicy::assertApproved($approved);

    $runtime = $approved;
    $runtime['require']['example/unapproved'] = '1.0.0';
    $development = $approved;
    $development['require-dev']['example/unapproved'] = '1.0.0';
    $missing = $approved;
    unset($missing['require-dev']['laravel/pint']);
    $pin = $approved;
    $pin['require-dev']['phpstan/phpstan'] = '^2.3.7';
    $plugin = $approved;
    $plugin['config']['allow-plugins']['example/unapproved'] = true;
    $wildcard = $approved;
    $wildcard['config']['allow-plugins'] = ['*' => true];
    $disabled = $approved;
    $disabled['config']['allow-plugins'] = ['pestphp/pest-plugin' => false];

    foreach ([
        [$runtime, 'Unexpected runtime dependency keys'],
        [$development, 'Unexpected development dependency keys'],
        [$missing, 'Unexpected development dependency keys'],
        [$pin, 'Unapproved quality package constraint: phpstan/phpstan'],
        [$plugin, 'Unexpected Composer plugin allowlist'],
        [$wildcard, 'Unexpected Composer plugin allowlist'],
        [$disabled, 'Unexpected Composer plugin allowlist'],
    ] as [$candidate, $message]) {
        expect(fn () => FoundationDependencyPolicy::assertApproved($candidate))
            ->toThrow(AssertionFailedError::class, $message);
    }
})->group('testing-foundation');
