<?php

declare(strict_types=1);

use Tests\Support\TestEnvironmentGuard;

require_once dirname(__DIR__).'/TestEnvironmentGuard.php';

try {
    if ($argc !== 1 || getenv('GITHUB_ACTIONS') !== 'true'
        || getenv('RUNNER_ENVIRONMENT') !== 'github-hosted'
        || getenv('GITHUB_REPOSITORY') !== 'Elmo-Tech/ecommerce_v2_api') {
        throw new RuntimeException('Unowned report cleanup.');
    }
    $directory = TestEnvironmentGuard::reportDirectory(getenv(), $_ENV, $_SERVER);
    if ($directory === null) {
        throw new RuntimeException('Owned report directory missing.');
    }
    $allowed = [];
    foreach (['foundation', 'fast-all', 'architecture', 'integration'] as $selection) {
        foreach (['-raw.xml', '-safe.xml', '-safe-next.xml', '.summary.json', '.summary.next.json', '.stdout', '.stderr'] as $suffix) {
            $allowed[] = $selection.$suffix;
        }
    }
    $entries = scandir($directory);
    if ($entries === false) {
        throw new RuntimeException('Owned report cleanup rejected.');
    }
    $paths = [];
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $directory.'/'.$name;
        if (!in_array($name, $allowed, true) || is_link($path) || !is_file($path) || realpath($path) !== $path) {
            throw new RuntimeException('Unexpected report cleanup target.');
        }
        $stat = lstat($path);
        if ($stat === false || $stat['nlink'] !== 1 || ($stat['mode'] & 0777) !== 0600
            || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
            throw new RuntimeException('Unexpected report cleanup target.');
        }
        $paths[] = $path;
    }
    foreach ($paths as $path) {
        if (!is_file($path) || is_link($path) || realpath($path) !== $path || !unlink($path)) {
            throw new RuntimeException('Owned report cleanup incomplete.');
        }
    }
    if (!rmdir($directory)) {
        throw new RuntimeException('Owned report cleanup incomplete.');
    }
    echo "Owned report directory cleanup verified.\n";
} catch (Throwable) {
    fwrite(STDERR, "Owned report cleanup rejected or incomplete.\n");
    exit(2);
}
