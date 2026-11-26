<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use RuntimeException;
use SensitiveParameter;
use Tests\Support\OwnedTestRun;

/** Fixed test-only resource plan; no daemon, service or filesystem I/O. */
final class OwnedServicePlan
{
    /**
     * @param array<array-key, mixed> $job
     * @return array<string, mixed>
     */
    public static function labels(array $job, string $token): array
    {
        self::token($token);
        return [
            'f03.token' => $token, 'f03.repository' => $job['repository'],
            'f03.run_id' => $job['run_id'], 'f03.run_attempt' => $job['run_attempt'],
            'f03.job' => $job['job'],
        ];
    }

    public static function token(string $token): void
    {
        if (preg_match('/^[0-9a-f]{16}$/D', $token) !== 1) {
            throw new RuntimeException('Invalid owned service run token.');
        }
    }

    /**
     * @return array{token: string, postgres_admin: string, redis_admin: string, postgres: array<string, array{database: string, role: string, password: string, marker: string}>, redis: array<string, array{role: string, password: string, prefix: string}>}
     */
    public static function credentials(string $token): array
    {
        self::token($token);
        $password = static fn (): string => bin2hex(random_bytes(32));
        $credentials = [
            'token' => $token,
            'postgres_admin' => $password(),
            'redis_admin' => $password(),
            'postgres' => [], 'redis' => [],
        ];
        foreach (['central', 'a', 'b'] as $slot) {
            $name = 'f03_'.$token.'_'.$slot;
            $credentials['postgres'][$slot] = [
                'database' => $name, 'role' => $name.'_rt',
                'password' => $password(), 'marker' => 'f03:'.$token.':'.$slot,
            ];
        }
        foreach (['a', 'b'] as $slot) {
            $credentials['redis'][$slot] = [
                'role' => 'f03_'.$token.'_'.$slot.'_rt',
                'password' => $password(), 'prefix' => 'f03:'.$token.':'.$slot.':',
            ];
        }
        return $credentials;
    }

    /**
     * @param array<array-key, mixed> $credentials
     * @return list<string>
     */
    public static function passwords(#[SensitiveParameter] array $credentials): array
    {
        $values = [$credentials['postgres_admin'], $credentials['redis_admin']];
        foreach (['postgres', 'redis'] as $service) {
            foreach ($credentials[$service] as $slot) {
                $values[] = $slot['password'];
            }
        }
        if (count($values) !== 7 || count(array_unique($values)) !== 7) {
            throw new RuntimeException('Invalid generated credential set.');
        }
        foreach ($values as $value) {
            if (!is_string($value) || !preg_match('/^[0-9a-f]{64}$/D', $value)) {
                throw new RuntimeException('Invalid generated credential set.');
            }
        }
        return $values;
    }

    public static function redisConfig(): string
    {
        return "bind 0.0.0.0\nport 6379\nprotected-mode yes\nsave \"\"\nappendonly no\ndaemonize no\nlogfile \"\"\naclfile /run/f03/users.acl\n";
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    public static function redisAcl(#[SensitiveParameter] array $credentials): string
    {
        self::passwords($credentials);
        self::token($credentials['token']);
        $token = $credentials['token'];
        $lines = [
            'user default reset off',
            'user f03_setup reset on #'.hash('sha256', $credentials['redis_admin']).' ~* &* +@all',
        ];
        foreach (['a', 'b'] as $slot) {
            $expectedRole = 'f03_'.$token.'_'.$slot.'_rt';
            $expectedPrefix = 'f03:'.$token.':'.$slot.':';
            if (($credentials['redis'][$slot]['role'] ?? null) !== $expectedRole
                || ($credentials['redis'][$slot]['prefix'] ?? null) !== $expectedPrefix) {
                throw new RuntimeException('Invalid Redis fixture identity.');
            }
            $lines[] = 'user '.$expectedRole.' reset on #'.hash('sha256', $credentials['redis'][$slot]['password'])
                .' ~'.$expectedPrefix.'* +ping +get +set +del +acl|whoami';
        }
        return implode("\n", $lines)."\n";
    }

    public static function imageRef(string $service): string
    {
        return OwnedTestRun::imageRef($service);
    }

    /**
     * Arguments contain no credentials; only exact owned private mounts.
     *
     * @param array<array-key, mixed> $context
     * @return list<string>
     */
    public static function createArguments(array $context, string $service, int $uid, int $gid): array
    {
        self::token($context['token']);
        $directory = $context['directory'];
        if (!is_string($directory) || !preg_match('#^/[A-Za-z0-9_./-]+$#D', $directory)
            || basename($directory) !== 'f03-'.$context['token'] || $uid <= 0 || $gid <= 0) {
            throw new RuntimeException('Invalid owned container creation context.');
        }
        $port = match ($service) {
            'postgres' => 5432, 'redis' => 6379,
            default => throw new RuntimeException('Unknown owned service.'),
        };
        $args = ['container', 'create', '--pull=never', '--platform=linux/amd64',
            '--network=bridge', '--publish=127.0.0.1::'.$port,
            '--name=f03-'.$context['token'].'-'.$service];
        foreach (self::labels($context['job'], $context['token']) as $name => $value) {
            $args[] = '--label='.$name.'='.$value;
        }
        $mount = static fn (string $file, string $destination): string =>
            '--mount=type=bind,src='.$directory.'/'.$file.',dst='.$destination.',readonly';
        if ($service === 'postgres') {
            return [...$args,
                $mount('pg-password', '/run/f03/pg-password'),
                '--env=POSTGRES_USER=postgres',
                '--env=POSTGRES_DB=postgres',
                '--env=POSTGRES_PASSWORD_FILE=/run/f03/pg-password',
                '--env=POSTGRES_HOST_AUTH_METHOD=scram-sha-256',
                self::imageRef($service), 'postgres',
                '-c', 'password_encryption=scram-sha-256',
                '-c', 'log_error_verbosity=verbose',
                '-c', 'log_line_prefix=%m [%p] %u@%d ',
                '-c', 'lc_messages=C',
                '-c', 'logging_collector=off',
                '-c', 'log_statement=none',
                '-c', 'log_min_error_statement=panic',
            ];
        }
        // Run Redis with the hosted file owner's UID, so protected 0600
        // config/ACL mounts need not be made world-readable. No persistence.
        return [...$args, '--user='.$uid.':'.$gid,
            $mount('redis.conf', '/run/f03/redis.conf'),
            $mount('users.acl', '/run/f03/users.acl'),
            self::imageRef($service), 'redis-server', '/run/f03/redis.conf'];
    }

    /**
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $credentials
     * @return array<string, mixed>
     */
    public static function runtime(array $record, #[SensitiveParameter] array $credentials): array
    {
        self::passwords($credentials);
        if ($credentials['token'] !== $record['token']) {
            throw new RuntimeException('Owned credential run mismatch.');
        }
        $runtime = ['job' => $record['job'], 'token' => $record['token'], 'directory' => $record['directory']];
        foreach (['postgres', 'redis'] as $service) {
            $runtime[$service] = [
                'host' => '127.0.0.1', 'port' => $record['containers'][$service]['port'],
                'slots' => $credentials[$service],
            ];
        }
        return $runtime;
    }
}
