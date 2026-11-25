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

    public static function denied(string $response, bool $foreignKey = false, ?string $expected = null): bool
    {
        // Exact Redis permission response only. The server can name the
        // denied key or use the documented generic key denial wording.
        // Never accept an unrelated error, success or authentication failure.
        $response = trim($response);
        $prefix = '(?:(?:ERR|NOPERM) )?This user has no permissions to ';
        if ($foreignKey) {
            if (preg_match('/^'.$prefix.'access (?:the key|one of the keys used as arguments)$/iD', $response) === 1) {
                return true;
            }
            if (preg_match("/^".$prefix."access the '([a-z0-9_:.-]+)' key$/iD", $response, $match) === 1) {
                return $expected === null || $match[1] === $expected;
            }
            return false;
        }
        if (preg_match("/^".$prefix."run the '([a-z0-9_|-]+)' command$/iD", $response, $match) !== 1) {
            return false;
        }
        return $expected === null || strtolower($match[1]) === strtolower(str_replace(' ', '|', $expected));
    }

    /** Fixed diagnostic categories; never echo Redis reply or key values. */
    public static function replyCategory(#[\SensitiveParameter] string $response): string
    {
        $response = trim($response);
        if (self::denied($response)) {
            return 'command-denied';
        }
        if (self::denied($response, true)) {
            return 'key-denied';
        }
        if (preg_match('/(?:NOAUTH|WRONGPASS|AUTH failed)/i', $response)) {
            return 'authentication-error';
        }
        if (preg_match('/unknown command|wrong number of arguments|syntax error/i', $response)) {
            return 'command-format-error';
        }
        if (str_starts_with($response, 'ERR') || str_starts_with($response, 'NOPERM')) {
            return 'other-server-error';
        }
        return $response === 'OK' ? 'ok' : 'unexpected-response';
    }
}
