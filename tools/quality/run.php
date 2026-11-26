<?php

declare(strict_types=1);

use Tests\Support\TestEnvironmentGuard;
use Tools\Quality\AnalysisResult;
use Tools\Quality\PhpQualityPolicy;
use Tools\Quality\PhpQualityScratch;
use Tools\Quality\PintResult;

// No Composer autoload or application bootstrap in this parent.
$root = dirname(__DIR__, 2);
$exit = 2;
$scratch = null;
$summary = null;
$umask = umask(0077);

try {
    if (PHP_OS_FAMILY !== 'Linux' || getcwd() !== $root || realpath($root) !== $root
        || realpath(__FILE__) !== __FILE__ || count($argv) !== 2
        || !in_array($argv[1], ['format', 'analyse'], true)) {
        throw new RuntimeException('Unsupported quality command or checkout.');
    }
    $mode = $argv[1];
    foreach (['tests/Support/TestEnvironmentGuard.php', 'tools/quality/php-policy.php'] as $file) {
        $path = $root.'/'.$file;
        if (!is_file($path) || is_link($path) || realpath($path) !== $path) {
            throw new RuntimeException('Missing canonical quality policy.');
        }
        require_once $path;
    }

    // The existing guard validates all inherited service/path sources before
    // any tool subprocess. Additional tool/Git overrides cannot select a
    // different index, configuration, vendor directory or private cache.
    PhpQualityPolicy::assertNoOverrides();
    TestEnvironmentGuard::applyFast($root);
    $defaults = TestEnvironmentGuard::validateFast($root, getenv(), $_ENV, $_SERVER);
    $tool = PhpQualityPolicy::approvedTool($root, $mode);
    if ($mode === 'format') {
        PhpQualityPolicy::assertPintConfig($root);
    } else {
        PhpQualityPolicy::assertAnalysisConfig($root);
    }
    $files = PhpQualityPolicy::inventory($root);
    $before = PhpQualityPolicy::fingerprint($root);
    $lineBounds = $mode === 'analyse' ? PhpQualityPolicy::analysisLineBounds($root) : [];
    if ($mode === 'analyse' && array_keys($lineBounds) !== $files) {
        throw new RuntimeException('Analysis inventory changed before execution.');
    }

    $scratch = new PhpQualityScratch();
    // Fixed allowlist only; no inherited HOME, Composer credentials or service
    // settings. The parent guard has already rejected unsafe overrides.
    $environment = array_merge($defaults, [
        'PATH' => '/usr/bin:/bin',
        'HOME' => $scratch->path('home'),
        'TMPDIR' => $scratch->path('tmp'),
        'F04_QUALITY_CACHE' => $scratch->path('cache'),
        'CI' => 'true',
        'NO_COLOR' => '1',
        'COMPOSER_HOME' => $scratch->path('home'),
        'COMPOSER_NO_INTERACTION' => '1',
        'APP_PACKAGES_CACHE' => $scratch->path('cache').'/packages.php',
        'APP_SERVICES_CACHE' => $scratch->path('cache').'/services.php',
        'APP_CONFIG_CACHE' => $scratch->path('cache').'/config.php',
        'APP_ROUTES_CACHE' => $scratch->path('cache').'/routes.php',
        'APP_EVENTS_CACHE' => $scratch->path('cache').'/events.php',
    ]);
    $arguments = $mode === 'format'
        ? ['--test', '--format=json', '--config='.$root.'/pint.json', '--cache-file='.$scratch->path('cache').'/pint.cache']
        : ['analyse', '--level=6', '--configuration='.$root.'/phpstan.neon', '--no-progress', '--error-format=json'];
    $command = [
        PHP_BINARY, '-d', 'zend.exception_ignore_args=1', $tool,
        ...$arguments, '--no-interaction', '--no-ansi', '--',
        ...array_map(static fn (string $path): string => $root.'/'.$path, $files),
    ];
    $exit = $scratch->execute($command, $root, $environment, $mode === 'format' ? 600 : 900);
    if (PhpQualityPolicy::fingerprint($root) !== $before) {
        throw new RuntimeException('Tracked source changed during quality tool execution.');
    }
    // An unexpected analyzer process failure is not a validated finding.
    // Preserve its nonzero exit and never publish a misleading summary.
    if ($mode === 'analyse' && !in_array($exit, [0, 1], true)) {
        fwrite(STDERR, "Guarded analysis tool failed without a verified result.\n");
    } else {
        $summary = [
            'mode' => $mode,
            'selectedCount' => count($files),
            'toolExit' => $exit,
            'resultValidation' => 'VERIFIED',
            'inventorySha256' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
        if ($mode === 'format') {
            $formatFindings = PintResult::validate($scratch->readOutput('stdout'), $scratch->readOutput('stderr'), $exit, $root, $files);
            $summary['findingCount'] = count($formatFindings);
            $summary['files'] = $formatFindings;
        } else {
            $analysisFindings = AnalysisResult::validate($scratch->readOutput('stdout'), $scratch->readOutput('stderr'), $exit, $root, $lineBounds);
            $summary['findingCount'] = count($analysisFindings);
            $summary['findings'] = $analysisFindings;
        }
    }
} catch (Throwable) {
    $summary = null;
    $exit = 2;
    fwrite(STDERR, "Guarded PHP quality command rejected or incomplete.\n");
} finally {
    try {
        if ($scratch !== null) {
            $scratch->cleanup();
        }
    } catch (Throwable) {
        $summary = null;
        $exit = 2;
        fwrite(STDERR, "Owned PHP quality scratch cleanup rejected or incomplete.\n");
    } finally {
        umask($umask);
    }
}

if ($summary !== null) {
    echo json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
}

exit($exit);
