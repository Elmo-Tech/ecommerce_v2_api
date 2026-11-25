<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Bounded, read-only local Docker observations. No arbitrary Docker command
 * is exposed by this class. Never print inspect or container log output.
 */
final class LocalDocker
{
    private const BINARY = '/usr/bin/docker';
    private const ENDPOINT = 'unix:///var/run/docker.sock';

    private function __construct()
    {
    }

    public static function verified(): self
    {
        if (PHP_OS_FAMILY !== 'Linux' || php_uname('m') !== 'x86_64'
            || !is_executable(self::BINARY) || @filetype('/var/run/docker.sock') !== 'socket') {
            self::reject();
        }
        foreach ([getenv(), $_ENV, $_SERVER] as $source) {
            foreach (['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH', 'DOCKER_CONFIG', 'DOCKER_API_VERSION'] as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== '') {
                    self::reject();
                }
            }
        }
        $docker = new self;
        if (trim($docker->command(['context', 'show'], false)[0]) !== 'default'
            || trim($docker->command(['context', 'inspect', 'default', '--format', '{{.Endpoints.docker.Host}}'], false)[0]) !== self::ENDPOINT
            || trim($docker->command(['version', '--format', '{{.Server.Os}}/{{.Server.Arch}}'])[0]) !== 'linux/amd64') {
            self::reject();
        }

        return $docker;
    }

    public function observe(string $service, string $id, string $directory): ?array
    {
        $pin = OwnedTestRun::image($service);
        self::assertId($id);
        $list = trim($this->command(['ps', '--all', '--no-trunc', '--filter', 'id='.$id, '--format', '{{.ID}}'])[0]);
        if ($list === '') {
            // Successful exact-ID query establishes absence. An inspect
            // error or timeout must never be interpreted as absence.
            return null;
        }
        if ($list !== $id) {
            self::reject();
        }
        $fields = [
            '.Id', '.Image', '.Config.Image', '.Config.Labels',
            '.NetworkSettings.Ports', '.HostConfig.PortBindings',
            '.HostConfig.Privileged', '.HostConfig.NetworkMode', '.Mounts', '.State.Running',
        ];
        $format = implode("\n", array_map(static fn (string $field): string => '{{json '.$field.'}}', $fields));
        $container = $this->jsonLines($this->command(['container', 'inspect', '--format', $format, $id])[0], count($fields));
        $image = $this->jsonLines($this->command([
            'image', 'inspect', '--format', '{{json .Id}}'."\n".'{{json .Os}}'."\n".'{{json .Architecture}}'."\n".'{{json .RepoDigests}}',
            $pin['image_id'],
        ])[0], 4);

        return self::normalize($service, $id, $directory, $container, $image);
    }

    /** Pure parser also exercised with synthetic observations in fast tests. */
    public static function normalize(string $service, string $id, string $directory, array $container, array $image): array
    {
        self::assertId($id);
        $pin = OwnedTestRun::image($service);
        if (!array_is_list($container) || count($container) !== 10 || !array_is_list($image) || count($image) !== 4) {
            self::reject();
        }
        [$actualId, $imageId, $imageRef, $labels, $ports, $bindings, $privileged, $network, $mounts, $running] = $container;
        $tag = $service === 'postgres' ? 'postgres:17.12-bookworm' : 'redis:8.2.11-bookworm';
        $expectedRef = 'docker.io/library/'.$tag.'@'.$pin['manifest'];
        if ($actualId !== $id || $imageId !== $pin['image_id'] || $imageRef !== $expectedRef
            || $image[0] !== $imageId || $image[1] !== 'linux' || $image[2] !== 'amd64' || !is_array($image[3])
            || $privileged !== false || !in_array($network, ['default', 'bridge'], true)
            || $running !== true || !is_array($labels) || !is_array($mounts)) {
            self::reject();
        }
        $validDigest = false;
        foreach ($image[3] as $digest) {
            if (in_array($digest, [$service.'@'.$pin['manifest'], 'docker.io/library/'.$service.'@'.$pin['manifest']], true)) {
                $validDigest = true;
            }
        }
        if (!$validDigest) {
            self::reject();
        }
        $portKey = $service === 'postgres' ? '5432/tcp' : '6379/tcp';
        if (!is_array($ports) || array_keys($ports) !== [$portKey]
            || !is_array($bindings) || array_keys($bindings) !== [$portKey]
            || !is_array($ports[$portKey]) || count($ports[$portKey]) !== 1
            || !is_array($bindings[$portKey]) || count($bindings[$portKey]) !== 1) {
            self::reject();
        }
        $binding = $ports[$portKey][0] ?? [];
        $requested = $bindings[$portKey][0] ?? [];
        $port = $binding['HostPort'] ?? null;
        if (($binding['HostIp'] ?? null) !== '127.0.0.1' || ($requested['HostIp'] ?? null) !== '127.0.0.1'
            || !is_string($port) || !preg_match('/^[1-9][0-9]{3,4}$/D', $port)
            || (int) $port < 1024 || (int) $port > 65535
            || !in_array($requested['HostPort'] ?? null, ['', '0', $port], true)) {
            self::reject();
        }
        // Only the image's own disposable data volume and explicitly owned
        // read-only config mounts may be present; never the Docker socket.
        $volumes = [];
        $seenDestinations = [];
        $volumeDestination = $service === 'postgres' ? '/var/lib/postgresql/data' : '/data';
        $allowedBinds = $service === 'postgres'
            ? ['/run/f03/pg-password' => 'pg-password']
            : ['/run/f03/redis.conf' => 'redis.conf', '/run/f03/users.acl' => 'users.acl'];
        foreach ($mounts as $mount) {
            $destination = $mount['Destination'] ?? null;
            if (!is_string($destination) || in_array($destination, $seenDestinations, true)) {
                self::reject();
            }
            $seenDestinations[] = $destination;
            if (($mount['Type'] ?? null) === 'volume') {
                $name = $mount['Name'] ?? null;
                if ($destination !== $volumeDestination || !is_string($name) || !preg_match('/^[0-9a-f]{64}$/D', $name)
                    || ($mount['RW'] ?? null) !== true || $volumes !== []) {
                    self::reject();
                }
                $volumes[] = $name;
            } elseif (($mount['Type'] ?? null) === 'bind') {
                if (!isset($allowedBinds[$destination]) || ($mount['Source'] ?? null) !== $directory.'/'.$allowedBinds[$destination]
                    || ($mount['RW'] ?? null) !== false) {
                    self::reject();
                }
            } else {
                self::reject();
            }
        }
        if (count($volumes) !== 1) {
            self::reject();
        }
        $ownedLabels = array_filter($labels, static fn ($key): bool => is_string($key) && str_starts_with($key, 'f03.'), ARRAY_FILTER_USE_KEY);

        return [
            'id' => $id, 'image_id' => $imageId, 'manifest' => $pin['manifest'],
            'labels' => $ownedLabels, 'host' => '127.0.0.1', 'port' => (int) $port,
            'volumes' => $volumes,
        ];
    }

    public function postgresDenialCount(string $id, string $role, string $database): int
    {
        self::assertId($id);
        if (!preg_match('/^f03_[0-9a-f]{16}_(?:central|a|b)_rt$/D', $role)
            || !preg_match('/^(?:f03_[0-9a-f]{16}_(?:central|a|b)|postgres|template1)$/D', $database)) {
            self::reject();
        }
        [$stdout, $stderr] = $this->command(['logs', '--tail', '250', $id]);
        // T022 configures verbose server SQLSTATE and role@database prefix.
        // Require an additional actual matching log line for the specific
        // negative attempt, never count a generic connection failure.
        $pattern = '/'.preg_quote($role.'@'.$database, '/').'\s+FATAL:\s+42501:\s+permission denied for database "'.preg_quote($database, '/').'"/';
        $count = preg_match_all($pattern, $stdout."\n".$stderr);
        if ($count === false) {
            self::reject();
        }

        return $count;
    }

    private function jsonLines(string $raw, int $count): array
    {
        $lines = explode("\n", rtrim($raw, "\r\n"));
        if (count($lines) !== $count) {
            self::reject();
        }
        try {
            return array_map(static fn (string $line): mixed => json_decode($line, true, 24, JSON_THROW_ON_ERROR), $lines);
        } catch (\JsonException) {
            self::reject();
        }
    }

    private static function assertId(string $id): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $id)) {
            self::reject();
        }
    }

    private function command(array $args, bool $explicitEndpoint = true): array
    {
        $argv = [self::BINARY, ...($explicitEndpoint ? ['--host', self::ENDPOINT] : []), ...$args];
        $process = @proc_open($argv, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            self::reject();
        }
        $output = ['', ''];
        $deadline = hrtime(true) + 10_000_000_000;
        $exit = null;
        try {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            do {
                foreach ([1, 2] as $i) {
                    $chunk = fread($pipes[$i], 8192);
                    if ($chunk === false) {
                        self::reject();
                    }
                    $output[$i - 1] .= $chunk;
                }
                if (strlen($output[0]) + strlen($output[1]) > 524288 || hrtime(true) > $deadline) {
                    self::reject();
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit ??= $status['exitcode'];
                    if (feof($pipes[1]) && feof($pipes[2])) {
                        break;
                    }
                }
                usleep(10000);
            } while (true);
        } finally {
            if ($exit === null) {
                proc_terminate($process, 9);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
        }
        if (($exit ?? $closed) !== 0) {
            self::reject();
        }

        return $output;
    }

    private static function reject(): never
    {
        throw new RuntimeException('Local owned Docker observation failed.');
    }
}
