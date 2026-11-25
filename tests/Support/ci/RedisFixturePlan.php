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

    public static function denied(string $response, bool $foreignKey = false, ?string $expected = null, ?string $expectedUser = null): bool
    {
        // ACL DRYRUN returns a descriptive bulk string, not necessarily a
        // RESP error. Redis documents named-user command denials and generic
        // key denials. Match an entire known permission response only.
        $response = trim($response);
        $error = '(?:(?:ERR|NOPERM) )?';
        if ($foreignKey && preg_match('/^'.$error.'No permissions to access a key$/iD', $response) === 1) {
            return true;
        }
        if (preg_match('/^'.$error.'User (f03_[0-9a-f]{16}_[ab]_rt) has no permissions to (.+)$/iD', $response, $identity) === 1) {
            if ($expectedUser !== null && $identity[1] !== $expectedUser) {
                return false;
            }
            $response = 'This user has no permissions to '.$identity[2];
        }
        $prefix = $error.'This user has no permissions to ';
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
