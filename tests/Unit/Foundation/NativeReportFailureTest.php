<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationTestCheckout;
use Tests\Support\ci\SafeTestReport;

/**
 * Actual installed Pest/PHPUnit subprocess diagnostics on one owned scratch
 * Unit probe. No application, service, real test discovery or new package.
 * Raw child output and synthetic credentials remain private.
 */
function f03NativeReportProbe(FoundationTestCheckout $checkout, string $case, string $canary = ''): \Symfony\Component\Process\Process
{
    $source = dirname(__DIR__, 3);
    foreach (['tests/Unit', 'tests/Feature', 'tests/Architecture', 'app', 'vendor/phpunit/phpunit'] as $dir) {
        if (!mkdir($checkout->root.'/'.$dir, 0700, true) && !is_dir($checkout->root.'/'.$dir)) {
            throw new RuntimeException('Cannot prepare owned diagnostic fixture.');
        }
    }
    foreach (['composer.json', 'vendor/phpunit/phpunit/phpunit.xsd'] as $path) {
        if (!copy($source.'/'.$path, $checkout->root.'/'.$path)) {
            throw new RuntimeException('Cannot prepare owned diagnostic config.');
        }
    }
    $checkout->write('tests/Pest.php', "<?php\n");
    $body = match ($case) {
        'pass', 'empty' => 'expect(true)->toBeTrue();',
        'skipped' => '$this->markTestSkipped("synthetic skipped probe");',
        'incomplete' => '$this->markTestIncomplete("synthetic incomplete probe");',
        'risky' => 'return;',
        'warning' => 'expect(true)->toBeTrue(); trigger_error("synthetic warning probe", E_USER_WARNING);',
        'failure' => 'expect(false)->toBeTrue("synthetic private diagnostic");',
        'canary' => '$this->fail('.var_export($canary, true).');',
        default => throw new RuntimeException('Unknown owned diagnostic case.'),
    };
    $checkout->write('tests/Unit/ProbeTest.php', "<?php\nit('native diagnostic probe', function (): void {\n".$body."\n});\n");

    $args = [
        '-d', 'zend.exception_ignore_args=1',
        $source.'/vendor/bin/pest', '--configuration', 'phpunit.xml',
        '--testsuite', 'Unit', '--fail-on-empty-test-suite', '--fail-on-warning',
        '--fail-on-risky', '--fail-on-skipped', '--fail-on-incomplete',
        '--log-junit', $checkout->root.'/reports/native.xml',
    ];
    if ($case === 'empty') {
        $args[] = '--filter';
        $args[] = 'no-such-native-diagnostic-probe';
    }

    return $checkout->run($args, ['APP_ENV' => 'testing']);
}

it('proves actual locked Pest failure flags using isolated native subprocesses', function (string $case): void {
    $checkout = new FoundationTestCheckout();
    try {
        $result = f03NativeReportProbe($checkout, $case);
        $output = $result->getOutput().$result->getErrorOutput();
        $reportPath = $checkout->root.'/reports/native.xml';
        expect($checkout->reachedAutoload())->toBeTrue();
        if ($case === 'pass') {
            expect($result->getExitCode())->toBe(0);
            $report = SafeTestReport::inspect($reportPath, $checkout->root.'/reports', [], []);
            expect($report)->toMatchArray(['tests' => 1, 'assertions' => 1, 'failures' => 0, 'errors' => 0, 'skipped' => 0]);
            expect(SafeTestReport::outcome(0, $report, 0, 0)['status'])->toBe('PASSED');

            return;
        }
        expect(is_int($result->getExitCode()) && $result->getExitCode() > 0)->toBeTrue();
        // Verify intended failure category, not an unrelated CLI/bootstrap error.
        $pattern = match ($case) {
            'empty' => '/no tests|0 tests|empty test suite/i',
            'skipped' => '/skipped/i',
            'incomplete' => '/incomplete/i',
            'risky' => '/risky|did not perform any assertions/i',
            'warning' => '/warning/i',
            'failure' => '/failed|failure/i',
        };
        expect(preg_match($pattern, $output))->toBe(1);
        if ($case === 'empty') {
            expect(fn () => SafeTestReport::inspect($reportPath, $checkout->root.'/reports', [], []))->toThrow(RuntimeException::class);
            expect(SafeTestReport::outcome($result->getExitCode(), null, 0, 0)['exit_code'])->toBe($result->getExitCode());

            return;
        }

        // A real native JUnit is expected even for these nonzero outcomes.
        $report = SafeTestReport::inspect($reportPath, $checkout->root.'/reports', [], []);
        expect($report['tests'])->toBe(1);
        expect(SafeTestReport::outcome($result->getExitCode(), $report, 0, 0))->toBe([
            'status' => 'FAILED', 'exit_code' => $result->getExitCode(),
        ]);
        $safe = SafeTestReport::safeXml($report, 'diagnostic');
        expect(str_contains($safe, 'synthetic private diagnostic'))->toBeFalse();
        expect(str_contains($safe, 'system-out') || str_contains($safe, 'system-err'))->toBeFalse();
        $checkout->write('reports/safe.xml', $safe);
        $derived = SafeTestReport::inspect($checkout->root.'/reports/safe.xml', $checkout->root.'/reports', [], []);
        expect(array_intersect_key($derived, array_flip(['tests', 'assertions', 'failures', 'errors', 'skipped'])))->toBe(
            array_intersect_key($report, array_flip(['tests', 'assertions', 'failures', 'errors', 'skipped']))
        );
    } finally {
        $checkout->close();
    }
})->with(['pass', 'empty', 'skipped', 'incomplete', 'risky', 'warning', 'failure'])->group('testing-foundation');

it('suppresses a real native failure JUnit containing a generated synthetic credential fingerprint', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $canary = bin2hex(random_bytes(32));
        $result = f03NativeReportProbe($checkout, 'canary', $canary);
        expect(is_int($result->getExitCode()) && $result->getExitCode() > 0)->toBeTrue();
        $path = $checkout->root.'/reports/native.xml';
        expect(is_file($path) && str_contains(file_get_contents($path), $canary))->toBeTrue();
        $rejected = false;
        try {
            SafeTestReport::inspect($path, $checkout->root.'/reports', [], [], [hash('sha256', $canary)]);
        } catch (RuntimeException $error) {
            $rejected = true;
            expect(str_contains($error->getMessage(), $canary))->toBeFalse();
        }
        expect($rejected)->toBeTrue();
        expect(is_file($checkout->root.'/reports/safe.xml'))->toBeFalse();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');
