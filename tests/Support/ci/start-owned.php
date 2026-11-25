<?php

declare(strict_types=1);

use Tests\Support\LocalDocker;

$root = dirname(__DIR__, 3);
foreach (['TestEnvironmentGuard.php', 'OwnedTestRun.php', 'IntegrationContext.php', 'LocalDocker.php', 'IntegrationConnections.php'] as $file) {
    require_once dirname(__DIR__).'/'.$file;
}
foreach (['CleanupPolicy.php', 'OwnedServicePlan.php', 'OwnedSetupFiles.php'] as $file) {
    require_once __DIR__.'/'.$file;
}

try {
    if ($argc !== 1) {
        throw new RuntimeException('Unexpected startup argument.');
    }
    LocalDocker::startCurrent($root);
} catch (Throwable) {
    fwrite(STDERR, "Owned startup rejected or incomplete.\n");
    exit(2);
}
