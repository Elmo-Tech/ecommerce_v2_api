<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use RuntimeException;

/** Fixed ACL DRYRUN probes; commands are data, never executed directly. */
final class RedisFixturePlan
{
    public static function probes(string $token, string $slot): array
    {
        OwnedServicePlan::token($token);
        if (!in_array($slot, ['a', 'b'], true)) {
            throw new RuntimeException('Unknown owned Redis fixture slot.');
        }
        $role = 'f03_'.$token.'_'.$slot.'_rt';
        $key = 'f03:'.$token.':'.$slot.':preflight';
        $other = 'f03:'.$token.':'.($slot === 'a' ? 'b' : 'a').':preflight';
        $prefix = ['ACL', 'DRYRUN', $role];
        return [
            'allowed' => [
                [...$prefix, 'PING'], [...$prefix, 'GET', $key],
                [...$prefix, 'SET', $key, 'synthetic'], [...$prefix, 'DEL', $key],
                [...$prefix, 'ACL', 'WHOAMI'],
            ],
            'denied' => [
                'FLUSHALL' => [...$prefix, 'FLUSHALL'],
                'FLUSHDB' => [...$prefix, 'FLUSHDB'],
                'CONFIG GET' => [...$prefix, 'CONFIG', 'GET', 'port'],
                'ACL SETUSER' => [...$prefix, 'ACL', 'SETUSER', 'f03_never_created', 'off'],
                'SELECT' => [...$prefix, 'SELECT', '0'],
                'EVAL' => [...$prefix, 'EVAL', 'return 1', '0'],
                'SHUTDOWN' => [...$prefix, 'SHUTDOWN', 'NOSAVE'],
            ],
            'foreign' => [
                [...$prefix, 'GET', $other], [...$prefix, 'SET', $other, 'synthetic'],
                [...$prefix, 'DEL', $other],
            ],
        ];
    }

    public static function denied(string $response, bool $foreignKey = false): bool
    {
        // Require the specific ACL DRYRUN permission classification, not
        // any arbitrary error (e.g. auth failure or unknown command).
        return $foreignKey
            ? preg_match('/(?:^|\s)This user has no permissions to access (?:the |one of the )?key/i', trim($response)) === 1
            : preg_match('/(?:^|\s)This user has no permissions to run the .* command/i', trim($response)) === 1;
    }
}
