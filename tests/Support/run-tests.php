<?php

declare(strict_types=1);

use Tests\Support\TestEnvironmentGuard;

require_once __DIR__.'/TestEnvironmentGuard.php';

$root = dirname(__DIR__, 2);

try {
    // The integration mode is deliberately not supported until the owned
    // service guard is implemented in T019.
    $arguments = array_slice($argv, 1);
    if (array_shift($arguments) !== 'fast') {
        throw new RuntimeException('Unsupported test mode.');
    }

    TestEnvironmentGuard::applyFast($root);

    $options = [];
    $selection = null;
    $report = false;
    foreach ($arguments as $argument) {
        if (preg_match('/^--suite=(Unit|Feature|Architecture)$/D', $argument, $match) && $selection === null) {
            $selection = 'suite';
            $options[] = '--testsuite';
            $options[] = $match[1];
        } elseif (preg_match('/^--group=(foundation|api-foundation|testing-foundation)$/D', $argument, $match) && $selection === null) {
            $selection = 'group';
            $options[] = '--group';
            $options[] = $match[1];
        } elseif (str_starts_with($argument, '--report=') && !$report) {
            $report = true;
            $options[] = '--log-junit';
            $options[] = TestEnvironmentGuard::reportPath(substr($argument, strlen('--report=')));
        } else {
            throw new RuntimeException('Unsupported test selector or option.');
        }
    }

    $command = [
        PHP_BINARY, $root.'/vendor/bin/pest', '--configuration', $root.'/phpunit.xml',
        '--fail-on-empty-test-suite', '--fail-on-warning', '--fail-on-risky',
        '--fail-on-skipped', '--fail-on-incomplete', ...$options,
    ];

    // Explicit argv array, no shell interpolation or arbitrary config flags.
    $child = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
    if (!is_resource($child)) {
        throw new RuntimeException('Cannot start guarded test runner.');
    }
    $status = proc_close($child);
    exit($status >= 0 ? $status : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "Guarded test command rejected: ".TestEnvironmentGuard::diagnostic($error)."\n");
    exit(2);
}
