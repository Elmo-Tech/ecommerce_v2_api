<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use Tests\Support\OwnedTestRun;

/**
 * Pure cleanup target policy. It does not execute a deletion command.
 * Observations must come from fresh local inspections of exact recorded IDs.
 */
final class CleanupPolicy
{
    /**
     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $observed
     * @return array{containers: list<array{id: string, volumes: list<string>}>, directory: string}
     */
    public static function targets(array $job, array $record, array $observed, string $runnerTemp): array
    {
        OwnedTestRun::validateRecord($job, $record, $observed, $runnerTemp, true);

        $targets = [];
        foreach (['postgres', 'redis'] as $service) {
            if (!array_key_exists($service, $record['containers']) || $observed[$service] === null) {
                continue;
            }
            $container = $record['containers'][$service];
            $targets[] = ['id' => $container['id'], 'volumes' => $observed[$service]['volumes']];
        }

        return ['containers' => $targets, 'directory' => $record['directory']];
    }

    /**
     * Complete only missing capture fields from already validated live
     * observations before removing anything. This retains volume provenance
     * if a later cleanup step fails and the container is now absent.
     *
     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $observed
     * @return array<array-key, mixed>
     */
    public static function completeCapture(array $job, array $record, array $observed, string $runnerTemp): array
    {
        OwnedTestRun::validateRecord($job, $record, $observed, $runnerTemp, true);
        foreach ($record['containers'] as $service => &$container) {
            $actual = $observed[$service];
            if ($actual !== null) {
                if ($container['volumes'] === []) {
                    $container['volumes'] = $actual['volumes'];
                }
                if ($container['port'] === null) {
                    $container['port'] = $actual['port'];
                }
            }
        }
        unset($container);
        OwnedTestRun::validateRecord($job, $record, $observed, $runnerTemp, true);

        return $record;
    }

    /**
     * Pure filesystem target selection; only known protected artifacts.
     *
     * @return list<string>
     */
    public static function files(string $directory): array
    {
        $allowed = [
            'ownership.json', 'ownership.next.json', 'runtime.json', 'runtime.next.json',
            'redis-evidence.json', 'redis-evidence.next.json', 'pg-password',
            'redis.conf', 'users.acl', 'setup.json', 'setup.next.json', 'credentials-hashes.json',
        ];
        if (realpath($directory) !== $directory || is_link($directory) || !is_dir($directory)
            || !preg_match('/^f03-[0-9a-f]{16}$/D', basename($directory))
            || ((@fileperms($directory) ?: 0) & 0777) !== 0700
            || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
            throw new \RuntimeException('Owned cleanup directory rejected.');
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException('Owned cleanup directory rejected.');
        }
        $files = [];
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory.'/'.$name;
            if (!in_array($name, $allowed, true) || is_link($path) || !is_file($path) || realpath($path) !== $path) {
                throw new \RuntimeException('Unexpected owned cleanup file.');
            }
            $stat = lstat($path);
            if ($stat === false || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1
                || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
                throw new \RuntimeException('Unexpected owned cleanup file.');
            }
            $files[] = $path;
        }
        sort($files);

        return $files;
    }
}
