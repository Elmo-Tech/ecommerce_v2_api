<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOException;
use Redis;
use RuntimeException;
use SensitiveParameter;

/** Native test-only connections; no application/tenant connection fallback. */
final class IntegrationConnections
{
    private function __construct(private readonly string $runToken)
    {
    }

    /** @param list<string> $drivers */
    public static function assertNativeDrivers(array $drivers, ?string $redisVersion): void
    {
        if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4
            || !in_array('pgsql', $drivers, true) || $redisVersion !== '6.3.0') {
            throw new RuntimeException('Approved native integration drivers unavailable.');
        }
    }

    /** @return array{run: OwnedTestRun, record: array<array-key, mixed>, docker: LocalDocker} */
    public static function preflight(string $root): array
    {
        // Validate job and protected paths before checking drivers or using
        // the local daemon. No application or Composer bootstrap here.
        IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER);
        self::assertNativeDrivers(PDO::getAvailableDrivers(), phpversion('redis') ?: null);

        return IntegrationContext::current($root);
    }

    public static function current(): self
    {
        $context = self::preflight(dirname(__DIR__, 2));

        return new self($context['run']->token());
    }

    public function token(): string
    {
        return $this->runToken;
    }

    /** @return array{run: OwnedTestRun, record: array<array-key, mixed>, docker: LocalDocker} */
    private function context(): array
    {
        $context = self::preflight(dirname(__DIR__, 2));
        if ($context['run']->token() !== $this->runToken) {
            throw new RuntimeException('Integration run changed.');
        }

        return $context;
    }

    public function postgres(string $slot): PDO
    {
        $context = $this->context();
        $target = $context['run']->postgresSlot($slot);
        try {
            $db = $this->openPostgres($context['run']->port('postgres'), $target, $target['database']);
            $identity = $db->query('SELECT current_database() AS db, current_user AS role, session_user AS session_role')->fetch(PDO::FETCH_ASSOC);
            if ($identity !== ['db' => $target['database'], 'role' => $target['role'], 'session_role' => $target['role']]
                || (int) $db->query('SHOW server_version_num')->fetchColumn() !== 170012
                || $db->query('SELECT marker FROM f03_probe.markers WHERE id = 100')->fetchColumn() !== $target['marker']) {
                throw new RuntimeException('Owned PostgreSQL identity mismatch.');
            }

            return $db;
        } catch (\Throwable) {
            throw new RuntimeException('Owned PostgreSQL connection or identity check failed.');
        }
    }

    /** @param array{role: string, password: string, ...} $target */
    private function openPostgres(int $port, #[SensitiveParameter] array $target, string $database): PDO
    {
        // Every interpolated field derives from validated fixed run slots.
        $dsn = 'pgsql:host=127.0.0.1;port='.$port.';dbname='.$database.';connect_timeout=5;sslmode=disable;options=\'-c statement_timeout=5000 -c lock_timeout=3000\'';
        return new PDO($dsn, $target['role'], $target['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @return array{source: string, target: string, sqlstate: '42501', reason: 'CONNECT_DENIED'} */
    public function postgresDenial(string $source, string $target): array
    {
        // Fixed known database pairs only. Reject before even obtaining
        // a new local daemon observation if the caller supplies unknown slots.
        if (!in_array($source, ['central', 'a', 'b'], true)
            || !in_array($target, ['central', 'a', 'b', 'postgres', 'template1'], true) || $source === $target) {
            throw new RuntimeException('Unsupported owned database denial pair.');
        }
        $own = $this->postgres($source);
        $context = $this->context();
        $run = $context['run'];
        $sourceSlot = $run->postgresSlot($source);
        $database = in_array($target, ['postgres', 'template1'], true) ? $target : $run->postgresSlot($target)['database'];
        $check = $own->prepare("SELECT CASE WHEN has_database_privilege(current_user, :database, 'CONNECT') THEN 1 ELSE 0 END");
        $check->execute(['database' => $database]);
        if ((int) $check->fetchColumn() !== 0) {
            throw new RuntimeException('Unexpected database CONNECT privilege.');
        }
        $own = null;
        $id = $context['record']['containers']['postgres']['id'];
        $before = $context['docker']->postgresDenialCount($id, $sourceSlot['role'], $database);
        $state = null;
        $connectDenied = false;
        try {
            $unexpected = $this->openPostgres($run->port('postgres'), $sourceSlot, $database);
            $unexpected = null;
        } catch (PDOException $error) {
            $state = (string) ($error->errorInfo[0] ?? $error->getCode());
            $message = $error->getMessage();
            $connectDenied = str_contains($message, 'permission denied for database "'.$database.'"')
                && str_contains($message, 'User does not have CONNECT privilege');
        }
        $fresh = $this->context();
        if ($fresh['record']['containers']['postgres']['id'] !== $id) {
            throw new RuntimeException('Owned PostgreSQL identity changed during denial test.');
        }
        $after = $fresh['docker']->postgresDenialCount($id, $sourceSlot['role'], $database);
        if (!$connectDenied || !in_array($state, ['42501', '08006', '08001', '7'], true) || $after !== $before + 1) {
            throw new RuntimeException('Actual PostgreSQL CONNECT denial was not established.');
        }

        return ['source' => $source, 'target' => $target, 'sqlstate' => '42501', 'reason' => 'CONNECT_DENIED'];
    }

    public function redis(string $slot): Redis
    {
        $context = $this->context();
        $target = $context['run']->redisSlot($slot);
        $this->readRedisEvidence($context['run']);
        $redis = new Redis();
        try {
            if (!$redis->connect('127.0.0.1', $context['run']->port('redis'), 5.0, null, 0, 5.0)
                || !$redis->auth([$target['role'], $target['password']])
                || $redis->rawCommand('ACL', 'WHOAMI') !== $target['role']
                || !in_array($redis->ping(), [true, 'PONG', '+PONG'], true)) {
                throw new RuntimeException('Owned Redis identity mismatch.');
            }

            return $redis;
        } catch (\Throwable) {
            if ($redis->isConnected()) {
                $redis->close();
            }
            throw new RuntimeException('Owned Redis connection or identity check failed.');
        }
    }

    /** @return array<array-key, mixed> */
    public function redisSetupEvidence(): array
    {
        return $this->readRedisEvidence($this->context()['run']);
    }

    /** @return array<array-key, mixed> Validated evidence retains its decoded JSON representation. */
    private function readRedisEvidence(OwnedTestRun $run): array
    {
        $evidence = IntegrationContext::read($run->directory(), 'redis-evidence.json');
        $commands = ['FLUSHALL', 'FLUSHDB', 'CONFIG GET', 'ACL SETUSER', 'SELECT', 'EVAL', 'SHUTDOWN'];
        if (count($evidence) !== 3 || ($evidence['token'] ?? null) !== $run->token()
            || ($evidence['server_version'] ?? null) !== '8.2.11'
            || !is_array($evidence['denials'] ?? null) || count($evidence['denials']) !== 2) {
            throw new RuntimeException('Owned Redis setup evidence missing.');
        }
        foreach (['a', 'b'] as $slot) {
            $denials = $evidence['denials'][$slot] ?? null;
            if (!is_array($denials) || count($denials) !== count($commands)) {
                throw new RuntimeException('Owned Redis ACL evidence incomplete.');
            }
            foreach ($commands as $command) {
                if (($denials[$command] ?? null) !== true) {
                    throw new RuntimeException('Owned Redis ACL evidence incomplete.');
                }
            }
        }

        return $evidence;
    }
}
