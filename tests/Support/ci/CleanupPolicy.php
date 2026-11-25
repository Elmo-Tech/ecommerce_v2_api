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
    public static function targets(array $job, array $record, array $observed, string $runnerTemp): array
    {
        OwnedTestRun::validateRecord($job, $record, $observed, $runnerTemp, true);

        $targets = [];
        foreach (['postgres', 'redis'] as $service) {
            if (!array_key_exists($service, $record['containers']) || $observed[$service] === null) {
                continue;
            }
            $container = $record['containers'][$service];
            $targets[] = ['id' => $container['id'], 'volumes' => $container['volumes']];
        }

        return ['containers' => $targets, 'directory' => $record['directory']];
    }
}
