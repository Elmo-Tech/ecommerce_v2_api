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
            'manifest' => 'sha256:3f9531ee43e9099389008518aab5b3a967a127ef573138c952645848d259f3ec',
            'image_id' => 'sha256:3185a9b85c3c84d67acfef7ee6e583ffbd92e87d45de676f181a7a26c026bef7',
        ],
        'redis' => [
            'manifest' => 'sha256:0b642fe44cd4a510d07ee35c48f803b0fd51dbdfc1920fe6027d35a4c269beed',
            'image_id' => 'sha256:e6cdb4e394c6058305c6c73d4a09e97554aa28edb875212e33f32c597e09a94a',
        ],
    ];

    /**
     * @param array<string, array{host: string, port: int, slots: array<string, array<string, string>>}> $runtime
     */
    private function __construct(
        private readonly string $runToken,
        private readonly string $ownedDirectory,
        #[SensitiveParameter] private readonly array $runtime,
    ) {
    }

    /**
     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $descriptor
     * @param array<array-key, mixed> $observed
     */
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

     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed> $observed
     */
    public static function validateRecord(array $job, array $record, array $observed, string $runnerTemp, bool $partial): void
    {
        self::checkRecord($job, $record, $observed, $runnerTemp, $partial);
    }

    /**
     * Structural precheck before inspecting IDs; never a live ownership proof.
     *
     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     */
    public static function validateCapturedRecord(array $job, array $record, string $runnerTemp, bool $partial): void
    {
        self::checkRecord($job, $record, null, $runnerTemp, $partial);
    }

    /**
     * @param array<array-key, mixed> $job
     * @param array<array-key, mixed> $record
     * @param array<array-key, mixed>|null $observed
     */
    private static function checkRecord(array $job, array $record, ?array $observed, string $runnerTemp, bool $partial): void
    {
        self::shape($job, ['repository', 'run_id', 'run_attempt', 'job', 'runner_environment', 'runner_os', 'runner_arch', 'docker_endpoint']);
        if ($job['repository'] !== 'Elmo-Tech/ecommerce_v2_api'
            || !self::matches($job['run_id'], '/^[1-9][0-9]{0,19}$/D')
            || !self::matches($job['run_attempt'], '/^[1-9][0-9]{0,8}$/D')
            || $job['job'] !== 'native-integration'
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
        if ($observed !== null) {
            self::shape($observed, $keys);
        }
        $expectedLabels = [
            'f03.token' => $token,
            'f03.repository' => $job['repository'],
            'f03.run_id' => $job['run_id'],
            'f03.run_attempt' => $job['run_attempt'],
            'f03.job' => $job['job'],
        ];
        $ids = $ports = $volumes = $observedVolumes = $observedPorts = [];
        foreach ($containers as $service => $container) {
            self::shape($container, ['id', 'image_id', 'manifest', 'labels', 'host', 'port', 'volumes']);
            if (!self::matches($container['id'], '/^[0-9a-f]{64}$/D')
                || $container['image_id'] !== self::IMAGES[$service]['image_id']
                || $container['manifest'] !== self::IMAGES[$service]['manifest']
                || !self::same($container['labels'], $expectedLabels)
                || $container['host'] !== '127.0.0.1'
                || !self::validPort($container['port'], $partial)
                || in_array($container['id'], $ids, true)
                || ($container['port'] !== null && in_array($container['port'], $ports, true))
                || !is_array($container['volumes']) || !array_is_list($container['volumes'])
                || count($container['volumes']) > 1
                || (!$partial && count($container['volumes']) !== 1)) {
                self::reject();
            }
            $ids[] = $container['id'];
            if ($container['port'] !== null) {
                $ports[] = $container['port'];
            }
            foreach ($container['volumes'] as $volume) {
                if (!self::matches($volume, '/^[0-9a-f]{64}$/D') || in_array($volume, $volumes, true)) {
                    self::reject();
                }
                $volumes[] = $volume;
            }
            if ($observed === null) {
                continue;
            }
            if ($observed[$service] === null && $partial) {
                // A captured ID with unknown volume and no live observation
                // cannot establish complete cleanup evidence.
                if ($container['volumes'] === []) {
                    self::reject();
                }
                continue;
            }
            $actual = self::shape($observed[$service], array_keys($container));
            $expected = $container;
            if ($partial) {
                // Capture can precede Docker's dynamic port allocation and
                // volume inspection. A stopped container can lose its live
                // port. Permit only those lifecycle transitions, never an
                // alternative known port or known volume.
                if (!self::validPort($actual['port'], true)
                    || ($container['port'] !== null && $actual['port'] !== null && $container['port'] !== $actual['port'])
                    || !is_array($actual['volumes']) || !array_is_list($actual['volumes']) || count($actual['volumes']) !== 1
                    || !self::matches($actual['volumes'][0], '/^[0-9a-f]{64}$/D')
                    || ($container['volumes'] !== [] && $container['volumes'] !== $actual['volumes'])) {
                    self::reject();
                }
                $expected['port'] = $actual['port'];
                $expected['volumes'] = $actual['volumes'];
            }
            // Even if another recorded container is already absent, its
            // previously captured volume is not available to a different
            // service's incomplete record.
            foreach ($containers as $otherService => $other) {
                if ($otherService !== $service && is_array($other['volumes'] ?? null)
                    && in_array($actual['volumes'][0], $other['volumes'], true)) {
                    self::reject();
                }
            }
            if (!self::same($actual, $expected)
                || in_array($actual['volumes'][0], $observedVolumes, true)
                || ($actual['port'] !== null && in_array($actual['port'], $observedPorts, true))) {
                self::reject();
            }
            $observedVolumes[] = $actual['volumes'][0];
            if ($actual['port'] !== null) {
                $observedPorts[] = $actual['port'];
            }
        }
    }

    /**
     * @return array{manifest: string, image_id: string}
     */
    public static function image(string $service): array
    {
        if (!array_key_exists($service, self::IMAGES)) {
            self::reject();
        }

        return self::IMAGES[$service];
    }

    public function token(): string
    {
        return $this->runToken;
    }

    public function directory(): string
    {
        return $this->ownedDirectory;
    }

    /**
     * @return array<string, string>
     */
    public function postgresSlot(string $slot): array
    {
        return $this->slot('postgres', $slot);
    }

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<array-key, mixed>
     */
    public function __serialize(): array
    {
        throw new RuntimeException('Owned runtime credentials cannot be serialized.');
    }

    /**
     * @param list<array-key> $keys
     * @return array<array-key, mixed>
     */
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

    /**
     * Compare independently obtained observations using exact recursive
     * value/type equality, ignoring associative key insertion order only.
     * The complete structural and current-job validation is still required.

     * @param array<array-key, mixed>|null $expected
     * @param array<array-key, mixed>|null $actual
     */
    public static function sameObservation(?array $expected, ?array $actual): bool
    {
        return self::same($expected, $actual);
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

    private static function validPort(mixed $port, bool $partial): bool
    {
        return ($partial && $port === null) || (is_int($port) && $port >= 1024 && $port <= 65535);
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
