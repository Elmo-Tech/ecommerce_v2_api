<?php

declare(strict_types=1);

use Tests\Support\LocalDocker;

$root = dirname(__DIR__, 3);
require_once dirname(__DIR__).'/TestEnvironmentGuard.php';
require_once dirname(__DIR__).'/OwnedTestRun.php';
require_once dirname(__DIR__).'/IntegrationContext.php';
require_once dirname(__DIR__).'/LocalDocker.php';
require_once __DIR__.'/CleanupPolicy.php';

try {
    if ($argc !== 1) {
        throw new RuntimeException('Unexpected cleanup argument.');
    }
    LocalDocker::cleanupCurrent($root);
    echo "Owned cleanup completed or exact run directory already absent.\n";
} catch (Throwable) {
    // Never emit descriptor content, native diagnostics or arbitrary paths.
    fwrite(STDERR, "Owned cleanup rejected or incomplete.\n");
    exit(2);
}
