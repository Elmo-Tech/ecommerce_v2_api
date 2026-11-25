<?php

declare(strict_types=1);

use Tests\Support\IntegrationConnections;

require_once __DIR__.'/TestEnvironmentGuard.php';
require_once __DIR__.'/OwnedTestRun.php';
require_once __DIR__.'/LocalDocker.php';
require_once __DIR__.'/IntegrationContext.php';
require_once __DIR__.'/IntegrationConnections.php';

try {
    IntegrationConnections::preflight(dirname(__DIR__, 2));
} catch (Throwable) {
    fwrite(STDERR, "Owned integration prerequisites rejected.\n");
    exit(2);
}

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
