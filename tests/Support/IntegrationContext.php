<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use SensitiveParameter;

/**
 * Read-only bridge from the current hosted job to the independently recorded
 * resource identity. Files are read only from the exact private run directory.
 * The record is not evidence of a live container until the daemon confirms it.
 */
final class IntegrationContext
{
    public static function environment(string $root, array $process, array $env, array $server): array
    {
        TestEnvironmentGuard::validateIntegration($root, $process, $env, $server);
        $required = [
            'GITHUB_ACTIONS' => 'true',
            'GITHUB_REPOSITORY' => 'Elmo-Tech/ecommerce_v2_api',
            'GITHUB_JOB' => 'f0-03-integration',
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
        if ($process['F03_RUN_DESCRIPTOR'] !== $directory.'/runtime.json'
            || realpath($directory) !== $directory || is_link($directory) || !is_dir($directory)
            || ((@fileperms($directory) ?: 0) & 0777) !== 0700
            || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
            self::reject();
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

    /** Only fixed job-owned files, bounded size, private regular files. */
    public static function read(string $directory, string $name): array
    {
        if (!in_array($name, ['ownership.json', 'runtime.json', 'redis-evidence.json'], true)
            || realpath($directory) !== $directory || is_link($directory) || !is_dir($directory)
            || ((@fileperms($directory) ?: 0) & 0777) !== 0700) {
            self::reject();
        }
        $path = $directory.'/'.$name;
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || realpath($path) !== $path || !is_file($path)
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

    private static function reject(): never
    {
        throw new RuntimeException('Owned integration context rejected.');
    }
}
