<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Read-only bridge from the current hosted job to the independently recorded
 * resource identity. Files are read only from the exact private run directory.
 * The record is not evidence of a live container until the daemon confirms it.
 */
final class IntegrationContext
{
    /**
     * @param array<array-key, mixed> $process
     * @param array<array-key, mixed> $env
     * @param array<array-key, mixed> $server
     * @return array{job: array<string, string>, token: string, temp: string, directory: string}
     */
    public static function environment(string $root, array $process, array $env, array $server, bool $allowAbsentDirectory = false): array
    {
        TestEnvironmentGuard::validateIntegration($root, $process, $env, $server);
        $required = [
            'GITHUB_ACTIONS' => 'true',
            'GITHUB_REPOSITORY' => 'Elmo-Tech/ecommerce_v2_api',
            'GITHUB_JOB' => 'native-integration',
            'RUNNER_ENVIRONMENT' => 'github-hosted',
            'RUNNER_OS' => 'Linux',
            'RUNNER_ARCH' => 'X64',
        ];
        foreach ($required as $name => $value) {
            if (($process[$name] ?? null) !== $value) {
                self::reject();
            }
        }
        foreach (array_merge(array_keys($required), ['GITHUB_RUN_ID', 'GITHUB_RUN_ATTEMPT', 'RUNNER_TEMP', 'F03_RUN_TOKEN', 'F03_RUN_DESCRIPTOR']) as $name) {
            if (!is_string($process[$name] ?? null) || $process[$name] === '') {
                self::reject();
            }
            foreach ([$env, $server] as $source) {
                if (array_key_exists($name, $source) && $source[$name] !== $process[$name]) {
                    self::reject();
                }
            }
        }
        // Never interpret a remote Docker context or external client config.
        foreach ([$process, $env, $server] as $source) {
            foreach (['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH', 'DOCKER_CONFIG', 'DOCKER_API_VERSION'] as $name) {
                if (array_key_exists($name, $source) && $source[$name] !== '') {
                    self::reject();
                }
            }
        }
        $token = $process['F03_RUN_TOKEN'];
        $temp = $process['RUNNER_TEMP'];
        if (!preg_match('/^[0-9a-f]{16}$/D', $token)
            || !preg_match('/^[1-9][0-9]{0,19}$/D', $process['GITHUB_RUN_ID'])
            || !preg_match('/^[1-9][0-9]{0,8}$/D', $process['GITHUB_RUN_ATTEMPT'])
            || realpath($temp) !== $temp || !is_dir($temp) || is_link($temp)) {
            self::reject();
        }
        $directory = $temp.'/f03-'.$token;
        if ($process['F03_RUN_DESCRIPTOR'] !== $directory.'/runtime.json' || is_link($directory)) {
            self::reject();
        }
        if (!$allowAbsentDirectory || file_exists($directory)) {
            if (realpath($directory) !== $directory || !is_dir($directory)
                || ((@fileperms($directory) ?: 0) & 0777) !== 0700
                || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
                self::reject();
            }
        }

        return [
            'job' => [
                'repository' => $process['GITHUB_REPOSITORY'],
                'run_id' => $process['GITHUB_RUN_ID'],
                'run_attempt' => $process['GITHUB_RUN_ATTEMPT'],
                'job' => $process['GITHUB_JOB'],
                'runner_environment' => $process['RUNNER_ENVIRONMENT'],
                'runner_os' => $process['RUNNER_OS'],
                'runner_arch' => $process['RUNNER_ARCH'],
                'docker_endpoint' => 'unix:///var/run/docker.sock',
            ],
            'token' => $token,
            'temp' => $temp,
            'directory' => $directory,
        ];
    }

    /** @return array{run: OwnedTestRun, record: array<array-key, mixed>, docker: LocalDocker} */
    public static function current(string $root): array
    {
        $context = self::environment($root, getenv(), $_ENV, $_SERVER);
        if (PHP_OS_FAMILY !== 'Linux' || php_uname('m') !== 'x86_64') {
            self::reject();
        }
        $record = self::read($context['directory'], 'ownership.json');
        $descriptor = self::read($context['directory'], 'runtime.json');
        // Structural precheck ONLY: validates exact ID grammar, pinned
        // manifests and the independent current job before any Docker I/O.
        // Comparing the record to itself here is NOT provenance evidence.
        if (($record['token'] ?? null) !== $context['token'] || !is_array($record['containers'] ?? null)) {
            self::reject();
        }
        OwnedTestRun::validate($context['job'], $record, $descriptor, $record['containers'], $context['temp']);

        $docker = LocalDocker::verified();
        $observed = [];
        foreach ($record['containers'] as $service => $container) {
            $observed[$service] = $docker->observe($service, $container['id'], $context['directory']);
        }
        $run = OwnedTestRun::validate($context['job'], $record, $descriptor, $observed, $context['temp']);

        return ['run' => $run, 'record' => $record, 'docker' => $docker];
    }

    /**
     * Only fixed job-owned files, bounded size, private regular files.
     *
     * @return array<array-key, mixed> Decoded JSON, validated structurally by the consumer.
     */
    public static function read(string $directory, string $name): array
    {
        if (!in_array($name, ['ownership.json', 'runtime.json', 'redis-evidence.json', 'setup.json', 'credentials-hashes.json'], true)
            || realpath($directory) !== $directory || is_link($directory) || !is_dir($directory)
            || ((@fileperms($directory) ?: 0) & 0777) !== 0700) {
            self::reject();
        }
        $path = $directory.'/'.$name;
        clearstatcache(true, $path);
        if (!is_file($path) || is_link($path) || realpath($path) !== $path) {
            self::reject();
        }
        $stat = lstat($path);
        if ($stat === false || !self::currentCanonicalFile($path)
            || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1
            || $stat['size'] <= 0 || $stat['size'] > 65536
            || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
            self::reject();
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            self::reject();
        }
        try {
            $opened = fstat($handle);
            if ($opened === false || $opened['ino'] !== $stat['ino'] || $opened['dev'] !== $stat['dev']
                || $opened['mode'] !== $stat['mode'] || $opened['size'] !== $stat['size']) {
                self::reject();
            }
            $raw = stream_get_contents($handle, 65537);
        } finally {
            fclose($handle);
        }
        if (!is_string($raw) || strlen($raw) !== $stat['size']) {
            self::reject();
        }
        try {
            $value = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::reject();
        }
        if (!is_array($value) || array_is_list($value)) {
            self::reject();
        }

        return $value;
    }

    /**
     * Replace only the exact expected captured record with its validated
     * lifecycle completion. No record creation or unrelated file overwrite.
     * The reviewed job is sequential; this is not a concurrent-writer API.
     *
     * @param array{job: array<array-key, mixed>, token: string, temp: string, directory: string} $context
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $observed
     * @return array<array-key, mixed>
     */
    public static function completeOwnership(array $context, array $expected, array $observed): array
    {
        $directory = $context['directory'];
        if (($expected['token'] ?? null) !== $context['token'] || ($expected['directory'] ?? null) !== $directory
            || self::read($directory, 'ownership.json') !== $expected) {
            self::reject();
        }
        $replacement = \Tests\Support\ci\CleanupPolicy::completeCapture(
            $context['job'],
            $expected,
            $observed,
            $context['temp'],
        );
        if ($replacement === $expected) {
            return $expected;
        }
        $path = $directory.'/ownership.json';
        $next = $directory.'/ownership.next.json';
        if (file_exists($next) || is_link($next)) {
            self::reject();
        }
        $raw = json_encode($replacement, JSON_THROW_ON_ERROR)."\n";
        $previousUmask = umask(0077);
        try {
            $handle = @fopen($next, 'xb');
        } finally {
            umask($previousUmask);
        }
        if ($handle === false) {
            self::reject();
        }
        try {
            $stat = fstat($handle);
            if ($stat === false || ($stat['mode'] & 0777) !== 0600 || $stat['nlink'] !== 1
                || fwrite($handle, $raw) !== strlen($raw) || !fflush($handle) || !fsync($handle)) {
                self::reject();
            }
        } finally {
            fclose($handle);
        }
        if (!self::currentCanonicalNonLink($next) || self::read($directory, 'ownership.json') !== $expected
            || !rename($next, $path) || self::read($directory, 'ownership.json') !== $replacement) {
            self::reject();
        }

        return $replacement;
    }

    /**
     * Filesystem identity may change between observations.
     *
     * @phpstan-impure
     */
    private static function currentCanonicalNonLink(string $path): bool
    {
        return !is_link($path) && realpath($path) === $path;
    }

    /**
     * Recheck the current filesystem state after lstat, without removing any test.
     *
     * @phpstan-impure
     */
    private static function currentCanonicalFile(string $path): bool
    {
        return self::currentCanonicalNonLink($path) && is_file($path);
    }

    private static function reject(): never
    {
        throw new RuntimeException('Owned integration context rejected.');
    }
}
