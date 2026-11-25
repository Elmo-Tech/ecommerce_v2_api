<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use SensitiveParameter;

/**
 * Pure validation of a job-created resource record against independently
 * obtained job identity and fresh local Docker observations. This class
 * performs no Docker or service I/O and creates/deletes nothing.
 */
final class OwnedTestRun
{
    private const IMAGES = [
        'postgres' => [
            'manifest' => 'sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03',
            'image_id' => 'sha256:850a2f036953dbe09006295666ee46737e4a15f9ede370bf3bf5718ec0a9e087',
        ],
        'redis' => [
            'manifest' => 'sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87',
            'image_id' => 'sha256:443f407d5d5a3ab81be78ebb136791c5d90f4e469231bc922dd187f55660fac6',
        ],
    ];

    private function __construct(
        private readonly string $runToken,
        private readonly string $ownedDirectory,
        #[SensitiveParameter] private readonly array $runtime,
    ) {
    }

    public static function validate(
        array $job,
        array $record,
        #[SensitiveParameter] array $descriptor,
        array $observed,
        string $runnerTemp,
    ): self {
        self::validateRecord($job, $record, $observed, $runnerTemp, false);
        self::shape($descriptor, ['job', 'token', 'directory', 'postgres', 'redis']);
        if (!self::same($descriptor['job'], $job)
            || $descriptor['token'] !== $record['token']
            || $descriptor['directory'] !== $record['directory']) {
            self::reject();
        }

        $passwords = [];
        $token = $record['token'];
        foreach (['postgres' => ['central', 'a', 'b'], 'redis' => ['a', 'b']] as $service => $slots) {
            $runtime = self::shape($descriptor[$service], ['host', 'port', 'slots']);
            if ($runtime['host'] !== '127.0.0.1' || $runtime['port'] !== $record['containers'][$service]['port']) {
                self::reject();
            }
            $targets = self::shape($runtime['slots'], $slots);
            foreach ($slots as $slot) {
                $target = self::shape($targets[$slot], $service === 'postgres'
                    ? ['database', 'role', 'password', 'marker']
                    : ['role', 'password', 'prefix']);
                $name = 'f03_'.$token.'_'.$slot;
                if ($target['role'] !== $name.'_rt'
                    || !self::matches($target['password'], '/^[0-9a-f]{64}$/D')
                    || in_array($target['password'], $passwords, true)) {
                    self::reject();
                }
                $passwords[] = $target['password'];
                if ($service === 'postgres') {
                    if ($target['database'] !== $name || $target['marker'] !== 'f03:'.$token.':'.$slot) {
                        self::reject();
                    }
                } elseif ($target['prefix'] !== 'f03:'.$token.':'.$slot.':') {
                    self::reject();
                }
            }
        }

        return new self($token, $record['directory'], [
            'postgres' => $descriptor['postgres'],
            'redis' => $descriptor['redis'],
        ]);
    }

    /**
     * The cleanup policy may validate a partial recorded setup. Null is
     * allowed only for an independently confirmed absence, not a failed
     * inspection; callers must establish that distinction. Full runtime
     * validation never accepts missing or absent containers.
     */
    public static function validateRecord(array $job, array $record, array $observed, string $runnerTemp, bool $partial): void
    {
        self::shape($job, ['repository', 'run_id', 'run_attempt', 'job', 'runner_environment', 'runner_os', 'runner_arch', 'docker_endpoint']);
        if ($job['repository'] !== 'Elmo-Tech/ecommerce_v2_api'
            || !self::matches($job['run_id'], '/^[1-9][0-9]{0,19}$/D')
            || !self::matches($job['run_attempt'], '/^[1-9][0-9]{0,8}$/D')
            || $job['job'] !== 'f0-03-integration'
            || $job['runner_environment'] !== 'github-hosted'
            || $job['runner_os'] !== 'Linux'
            || $job['runner_arch'] !== 'X64'
            || $job['docker_endpoint'] !== 'unix:///var/run/docker.sock') {
            self::reject();
        }
        self::shape($record, ['job', 'token', 'directory', 'containers']);
        if (!self::same($record['job'], $job) || !self::matches($record['token'], '/^[0-9a-f]{16}$/D')) {
            self::reject();
        }
        $token = $record['token'];
        $directory = $runnerTemp.'/f03-'.$token;
        if ($runnerTemp === '' || realpath($runnerTemp) !== $runnerTemp || !is_dir($runnerTemp)
            || is_link($runnerTemp) || $record['directory'] !== $directory
            || is_link($directory) || realpath($directory) !== $directory || !is_dir($directory)
            || ((@fileperms($directory) ?: 0) & 0777) !== 0700) {
            self::reject();
        }

        $containers = $record['containers'];
        if (!is_array($containers)) {
            self::reject();
        }
        $keys = array_keys($containers);
        if (array_diff($keys, array_keys(self::IMAGES)) !== [] || (!$partial && count($keys) !== 2)) {
            self::reject();
        }
        self::shape($observed, $keys);
        $expectedLabels = [
            'f03.token' => $token,
            'f03.repository' => $job['repository'],
            'f03.run_id' => $job['run_id'],
            'f03.run_attempt' => $job['run_attempt'],
            'f03.job' => $job['job'],
        ];
        $ids = $ports = $volumes = [];
        foreach ($containers as $service => $container) {
            self::shape($container, ['id', 'image_id', 'manifest', 'labels', 'host', 'port', 'volumes']);
            if (!self::matches($container['id'], '/^[0-9a-f]{64}$/D')
                || $container['image_id'] !== self::IMAGES[$service]['image_id']
                || $container['manifest'] !== self::IMAGES[$service]['manifest']
                || !self::same($container['labels'], $expectedLabels)
                || $container['host'] !== '127.0.0.1'
                || !is_int($container['port']) || $container['port'] < 1024 || $container['port'] > 65535
                || in_array($container['id'], $ids, true) || in_array($container['port'], $ports, true)
                || !is_array($container['volumes']) || !array_is_list($container['volumes'])) {
                self::reject();
            }
            $ids[] = $container['id'];
            $ports[] = $container['port'];
            foreach ($container['volumes'] as $volume) {
                if (!self::matches($volume, '/^[0-9a-f]{64}$/D') || in_array($volume, $volumes, true)) {
                    self::reject();
                }
                $volumes[] = $volume;
            }
            if ($observed[$service] === null && $partial) {
                continue;
            }
            if (!self::same($observed[$service], $container)) {
                self::reject();
            }
        }
    }

    public function token(): string
    {
        return $this->runToken;
    }

    public function directory(): string
    {
        return $this->ownedDirectory;
    }

    public function postgresSlot(string $slot): array
    {
        return $this->slot('postgres', $slot);
    }

    public function redisSlot(string $slot): array
    {
        return $this->slot('redis', $slot);
    }

    public function port(string $service): int
    {
        if (!array_key_exists($service, $this->runtime)) {
            self::reject();
        }

        return $this->runtime[$service]['port'];
    }

    private function slot(string $service, string $slot): array
    {
        if (!array_key_exists($slot, $this->runtime[$service]['slots'])) {
            self::reject();
        }

        return $this->runtime[$service]['slots'][$slot];
    }

    public function __debugInfo(): array
    {
        return ['token' => $this->runToken, 'runtime_credentials' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new RuntimeException('Owned runtime credentials cannot be serialized.');
    }

    private static function shape(#[SensitiveParameter] mixed $value, array $keys): array
    {
        if (!is_array($value)) {
            self::reject();
        }
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            self::reject();
        }

        return $value;
    }

    private static function same(#[SensitiveParameter] mixed $a, #[SensitiveParameter] mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            if (array_is_list($a) !== array_is_list($b) || count($a) !== count($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!array_key_exists($key, $b) || !self::same($value, $b[$key])) {
                    return false;
                }
            }
            return true;
        }

        return $a === $b;
    }

    private static function matches(#[SensitiveParameter] mixed $value, string $pattern): bool
    {
        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    private static function reject(): never
    {
        throw new RuntimeException('Owned test run validation failed.');
    }
}
