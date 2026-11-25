<?php

declare(strict_types=1);

use Tests\Support\TestEnvironmentGuard;

require_once __DIR__.'/TestEnvironmentGuard.php';

$root = dirname(__DIR__, 2);
try {
    TestEnvironmentGuard::applyFast($root);
} catch (Throwable $error) {
    // No environment value, path, stack trace or secret in diagnostics.
    fwrite(STDERR, "Fast test environment rejected: ".TestEnvironmentGuard::diagnostic($error)."\n");
    exit(2);
}

require_once $root.'/vendor/autoload.php';
