<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Bounded local Docker observations and a current-job-only cleanup entry.
 * No arbitrary Docker command is exposed. Never print inspect or log output.
 */
final class LocalDocker
{
    private const BINARY = '/usr/bin/docker';
    private const ENDPOINT = 'unix:///var/run/docker.sock';

    private function __construct()
    {
    }

    public static function verified(bool $diagnostic = false): self
    {
        if (PHP_OS_FAMILY !== 'Linux' || php_uname('m') !== 'x86_64'
            || !is_executable(self::BINARY) || @filetype('/var/run/docker.sock') !== 'socket') {
            self::reject();
        }
        if ($diagnostic) {
            echo "Owned startup: local executable and socket type verified.\n";
        }
        foreach ([getenv(), $_ENV, $_SERVER] as $source) {
            foreach (['DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH', 'DOCKER_CONFIG', 'DOCKER_API_VERSION'] as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== '') {
                    self::reject();
                }
            }
        }
        $docker = new self;
        if ($diagnostic) {
            echo "Owned startup: remote overrides absent.\n";
        }
        if (trim($docker->command(['context', 'show'], false)[0]) !== 'default') {
            self::reject();
        }
        if ($diagnostic) {
            echo "Owned startup: default context verified.\n";
        }
        if (trim($docker->command(['context', 'inspect', 'default', '--format', '{{.Endpoints.docker.Host}}'], false)[0]) !== self::ENDPOINT) {
            self::reject();
        }
        if ($diagnostic) {
            echo "Owned startup: exact local endpoint verified.\n";
        }
        if (trim($docker->command(['version', '--format', '{{.Server.Os}}/{{.Server.Arch}}'])[0]) !== 'linux/amd64') {
            self::reject();
        }

        return $docker;
    }

    /**
     * Startup is callable only from the current explicit hosted job, with a
     * fresh protected record. Never accept a caller-supplied ID or endpoint.
     */
    public static function startCurrent(string $root): void
    {
        $context = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER, true);
        $directory = $context['directory'];
        echo "Owned startup: hosted job and fresh directory context verified.\n";
        if (file_exists($directory) || is_link($directory)
            || !function_exists('posix_geteuid') || !function_exists('posix_getegid')
            || posix_geteuid() === 0) {
            self::reject();
        }
        echo "Owned startup: non-root runner and absent directory verified.\n";
        IntegrationConnections::assertNativeDrivers(\PDO::getAvailableDrivers(), phpversion('redis') ?: null);
        echo "Owned startup: approved native drivers verified.\n";
        $docker = self::verified(true);
        foreach (['postgres', 'redis'] as $service) {
            $docker->verifyPinnedImage($service);
            echo 'Owned startup: '.$service." pinned image verified.\n";
        }
        if (!mkdir($directory, 0700)) {
            self::reject();
        }
        echo "Owned startup: private run directory created.\n";
        $record = [
            'job' => $context['job'], 'token' => $context['token'],
            'directory' => $directory, 'containers' => [],
        ];
        OwnedTestRun::validateCapturedRecord($context['job'], $record, $context['temp'], true);
        \Tests\Support\ci\OwnedSetupFiles::json($directory, 'ownership.json', $record);
        echo "Owned startup: initial ownership record persisted.\n";
        $credentials = \Tests\Support\ci\OwnedServicePlan::credentials($context['token']);
        foreach (\Tests\Support\ci\OwnedServicePlan::passwords($credentials) as $password) {
            // GitHub workflow command, emitted only after hosted-job preflight.
            echo '::add-mask::'.$password."\n";
        }
        \Tests\Support\ci\OwnedSetupFiles::json($directory, 'setup.json', $credentials);
        \Tests\Support\ci\OwnedSetupFiles::create($directory, 'pg-password', $credentials['postgres_admin']."\n");
        \Tests\Support\ci\OwnedSetupFiles::create($directory, 'redis.conf', \Tests\Support\ci\OwnedServicePlan::redisConfig());
        \Tests\Support\ci\OwnedSetupFiles::create($directory, 'users.acl', \Tests\Support\ci\OwnedServicePlan::redisAcl($credentials));
        foreach (['postgres', 'redis'] as $service) {
            $current = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER);
            if ($current !== $context || IntegrationContext::read($directory, 'ownership.json') !== $record) {
                self::reject();
            }
            $docker = self::verified();
            $docker->verifyPinnedImage($service);
            // No names or volume IDs from external inputs; no --rm (capture
            // must remain inspectable on failed start). Image volumes are
            // newly anonymous, with no --volume-from or reused data mount.
            $id = trim($docker->command(\Tests\Support\ci\OwnedServicePlan::createArguments(
                $context, $service, posix_geteuid(), posix_getegid(),
            ))[0]);
            self::assertId($id);
            $record = \Tests\Support\ci\OwnedSetupFiles::appendCapture($context, $record, $service, $id);
            echo 'Owned startup: '.$service." exact creation ID captured.\n";
            $observed = $docker->observeRecord($record, true);
            if (($observed[$service]['port'] ?? null) !== null || $observed[$service] === null) {
                self::reject();
            }
            $record = IntegrationContext::completeOwnership($context, $record, $observed);
            // Revalidate all captured resources before start; only the newly
            // created exact container ID can be started.
            if (!OwnedTestRun::sameObservation($observed, $docker->observeRecord($record, true))) {
                self::reject();
            }
            echo 'Owned startup: '.$service." volume and identity verified before start.\n";
            try {
                [$out, $err, $status] = $docker->command(['container', 'start', $id], true, true);
                if ($status !== 0) {
                    echo 'Owned startup: '.$service.' start command failed: '.self::startupCategory($err."\n".$out).".\n";
                    self::reject();
                }
                echo 'Owned startup: '.$service." start command returned success.\n";
                $observed = $docker->observeRecord($record, true);
                echo 'Owned startup: '.$service." post-start observation validated.\n";
                if ($observed[$service] === null || $observed[$service]['port'] === null) {
                    self::reject();
                }
                $record = IntegrationContext::completeOwnership($context, $record, $observed);
                echo 'Owned startup: '.$service." allocated port recorded.\n";
                $docker->waitReady($record, $service);
            } catch (\Throwable $error) {
                $docker->diagnoseStartup($record, $service);
                throw $error;
            }
            echo 'Owned '.$service." started and readiness probe passed.\n";
        }
        $runtime = \Tests\Support\ci\OwnedServicePlan::runtime($record, $credentials);
        OwnedTestRun::validate($context['job'], $record, $runtime, $docker->observeRecord($record, false), $context['temp']);
        // Runtime descriptor is published only by the later completed setup
        // step, after actual role/ACL fixtures and setup evidence are ready.
        echo "Owned containers captured and ready; fixture setup still required.\n";
    }

    /** Complete real fixture setup only for the current verified containers. */
    public static function setupCurrent(string $root): void
    {
        $context = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER);
        $directory = $context['directory'];
        $record = IntegrationContext::read($directory, 'ownership.json');
        $credentials = IntegrationContext::read($directory, 'setup.json');
        $runtime = \Tests\Support\ci\OwnedServicePlan::runtime($record, $credentials);
        $docker = self::verified();
        $observed = $docker->observeRecord($record, false);
        OwnedTestRun::validate($context['job'], $record, $runtime, $observed, $context['temp']);
        foreach (['runtime.json', 'redis-evidence.json', 'credentials-hashes.json'] as $name) {
            if (file_exists($directory.'/'.$name) || is_link($directory.'/'.$name)) {
                self::reject();
            }
        }

        // Rewrite only the fixed HBA file inside the exact fresh PostgreSQL
        // container. Do not expose the setup role over TCP. No host admin
        // connection or arbitrary exec target.
        $pgId = $docker->ownedRunningId($record, 'postgres');
        $hbaScript = 'set -eu; test "$PGDATA" = /var/lib/postgresql/data; test -f "$PGDATA/pg_hba.conf"; test ! -L "$PGDATA/pg_hba.conf"; umask 077; set -C; cat > "$PGDATA/pg_hba.f03.next"; set +C; mv "$PGDATA/pg_hba.f03.next" "$PGDATA/pg_hba.conf"';
        $docker->command(['exec', '-i', '--user', 'postgres', $pgId, 'sh', '-c', $hbaScript], true, false, \Tests\Support\ci\PostgreSqlFixturePlan::hba());
        if (trim($docker->psql($record, 'postgres', "SELECT pg_reload_conf();\n")) !== 't'
            || trim($docker->psql($record, 'postgres', \Tests\Support\ci\PostgreSqlFixturePlan::hbaCheck())) !== 'f03-hba-ok') {
            self::reject();
        }
        $docker->psql($record, 'postgres', \Tests\Support\ci\PostgreSqlFixturePlan::clusterSql($credentials));
        foreach (['central', 'a', 'b'] as $slot) {
            $target = \Tests\Support\ci\PostgreSqlFixturePlan::target($credentials, $slot);
            $docker->psql($record, $target['database'], \Tests\Support\ci\PostgreSqlFixturePlan::databaseSql($credentials, $slot));
            if (trim($docker->psql($record, $target['database'], 'SELECT marker FROM f03_probe.markers WHERE id = 100;')) !== $target['marker']) {
                self::reject();
            }
        }
        if (trim($docker->psql($record, 'postgres', \Tests\Support\ci\PostgreSqlFixturePlan::privilegeCheck($credentials))) !== 'f03-privileges-ok') {
            self::reject();
        }
        echo "Owned PostgreSQL 17.12 fixtures and restricted grant matrix verified by setup.\n";

        $admin = $credentials['redis_admin'];
        echo "Owned Redis setup: verifying admin identity and authentication.\n";
        if (trim($docker->redisAdmin($record, $admin, ['ACL', 'WHOAMI'])) !== 'f03_setup'
            || trim($docker->redisAdmin($record, $admin, ['PING'])) !== 'PONG') {
            self::reject();
        }
        echo "Owned Redis setup: admin identity and PING verified.\n";
        $version = $docker->redisAdmin($record, $admin, ['INFO', 'server']);
        if (!preg_match('/^redis_version:8\.2\.11\r?$/m', $version)) {
            self::reject();
        }
        echo "Owned Redis setup: pinned server version verified.\n";
        $evidence = ['token' => $context['token'], 'server_version' => '8.2.11', 'denials' => []];
        foreach (['a', 'b'] as $slot) {
            $probes = \Tests\Support\ci\RedisFixturePlan::probes($context['token'], $slot);
            foreach ($probes['allowed'] as $probe) {
                $reply = $docker->redisAdmin($record, $admin, $probe);
                if (trim($reply) !== 'OK') {
                    echo 'Owned Redis setup: slot '.$slot.' allowed '.$probe[3].' probe rejected: '.\Tests\Support\ci\RedisFixturePlan::replyCategory($reply).".\n";
                    self::reject();
                }
            }
            echo 'Owned Redis setup: slot '.$slot." five allowed dry-runs verified.\n";
            foreach ($probes['denied'] as $name => $probe) {
                $reply = $docker->redisAdmin($record, $admin, $probe);
                if (!\Tests\Support\ci\RedisFixturePlan::denied($reply, false, $name, $probe[2])) {
                    echo 'Owned Redis setup: slot '.$slot.' denied '.$name.' probe rejected: '.\Tests\Support\ci\RedisFixturePlan::replyCategory($reply).".\n";
                    self::reject();
                }
                $evidence['denials'][$slot][$name] = true;
            }
            echo 'Owned Redis setup: slot '.$slot." seven prohibited command dry-runs verified.\n";
            foreach ($probes['foreign'] as $probe) {
                $reply = $docker->redisAdmin($record, $admin, $probe);
                if (!\Tests\Support\ci\RedisFixturePlan::denied($reply, true, $probe[4], $probe[2])) {
                    echo 'Owned Redis setup: slot '.$slot.' foreign '.$probe[3].' probe rejected: '.\Tests\Support\ci\RedisFixturePlan::replyCategory($reply).".\n";
                    self::reject();
                }
            }
            echo 'Owned Redis setup: slot '.$slot." three foreign key dry-runs verified.\n";
        }
        echo "Owned Redis 8.2.11 version, ACL allowed/denied dry-runs verified by setup.\n";
        // Preserve only password hashes for later report scanning. Setup
        // admin credentials are not delivered to the native test process.
        \Tests\Support\ci\OwnedSetupFiles::json($directory, 'credentials-hashes.json', [
            'token' => $context['token'],
            'hashes' => array_map(static fn (string $password): string => hash('sha256', $password), \Tests\Support\ci\OwnedServicePlan::passwords($credentials)),
        ]);
        \Tests\Support\ci\OwnedSetupFiles::json($directory, 'redis-evidence.json', $evidence);
        // Check private file identities before removing the two setup-secret
        // files. The mounted pg-password inode is only needed at init;
        // the fixed mount record remains inspectable until container cleanup.
        $allowed = \Tests\Support\ci\CleanupPolicy::files($directory);
        foreach (['setup.json', 'pg-password'] as $name) {
            $path = $directory.'/'.$name;
            if (!in_array($path, $allowed, true) || !unlink($path)) {
                self::reject();
            }
        }
        \Tests\Support\ci\OwnedSetupFiles::json($directory, 'runtime.json', $runtime);
        IntegrationConnections::preflight($root);
        echo "Owned runtime descriptor published after setup; setup credential files removed.\n";
    }

    private function ownedRunningId(array $record, string $service): string
    {
        $observed = $this->observeRecord($record, false);
        if (!array_key_exists($service, $record['containers']) || !OwnedTestRun::sameObservation($record['containers'][$service], $observed[$service])) {
            self::reject();
        }
        return $record['containers'][$service]['id'];
    }

    private function psql(array $record, string $database, #[\SensitiveParameter] string $sql): string
    {
        if ($database !== 'postgres' && !in_array($database, array_map(static fn (string $slot): string => 'f03_'.$record['token'].'_'.$slot, ['central', 'a', 'b']), true)) {
            self::reject();
        }
        $id = $this->ownedRunningId($record, 'postgres');
        return $this->command(['exec', '-i', '--user', 'postgres', $id,
            'psql', '-X', '-qAt', '-w', '-v', 'ON_ERROR_STOP=1', '-h', '/var/run/postgresql',
            '-U', 'postgres', '-d', $database], true, false, $sql."\n")[0];
    }

    private function redisAdmin(array $record, #[\SensitiveParameter] string $password, array $args): string
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $password)) {
            self::reject();
        }
        // Closed setup operation set: the only commands reaching Redis
        // directly are WHOAMI, PING, INFO server and ACL DRYRUN.
        if (!in_array($args, [['ACL', 'WHOAMI'], ['PING'], ['INFO', 'server']], true)) {
            $allowed = [];
            foreach (['a', 'b'] as $slot) {
                foreach (\Tests\Support\ci\RedisFixturePlan::probes($record['token'], $slot) as $probes) {
                    foreach ($probes as $probe) {
                        $allowed[] = $probe;
                    }
                }
            }
            if (!in_array($args, $allowed, true)) {
                self::reject();
            }
        }
        $id = $this->ownedRunningId($record, 'redis');
        $script = 'set -eu; IFS= read -r REDISCLI_AUTH; export REDISCLI_AUTH; exec redis-cli --raw --user f03_setup "$@"';
        [$stdout, $stderr, $status] = $this->command(['exec', '-i', $id, 'sh', '-c', $script, 'f03', ...$args], true, true, $password."\n");
        if ($status !== 0 && !\Tests\Support\ci\RedisFixturePlan::denied($stdout."\n".$stderr)
            && !\Tests\Support\ci\RedisFixturePlan::denied($stdout."\n".$stderr, true)) {
            echo 'Owned Redis setup: CLI rejected: '.\Tests\Support\ci\RedisFixturePlan::replyCategory($stdout."\n".$stderr).".\n";
            self::reject();
        }
        return trim($stdout."\n".$stderr);
    }

    /** Fixed diagnostic vocabulary: never return raw Docker output. */
    public static function startupCategory(#[\SensitiveParameter] string $message): string
    {
        foreach ([
            'password-file-permission' => '/(?:pg-password|POSTGRES_PASSWORD_FILE)[^\r\n]*permission denied/i',
            'mount-permission' => '/(?:mount|permission denied|operation not permitted)[^\r\n]*(?:permission denied|operation not permitted)|permission denied/i',
            'port-allocation' => '/port is already allocated|address already in use|failed to bind host port/i',
            'invalid-server-option' => '/unrecognized configuration parameter|invalid value for parameter|unrecognized option/i',
            'data-directory-permission' => '/(?:data directory|initdb)[^\r\n]*permission/i',
            'authentication-config' => '/invalid authentication method|could not load pg_hba|invalid ACL|error loading ACL/i',
            'entrypoint-config' => '/fatal config file error|can.t open config file|could not open configuration file/i',
        ] as $category => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return $category;
            }
        }
        return 'unclassified';
    }

    private function diagnoseStartup(array $record, string $service): void
    {
        try {
            $context = IntegrationContext::environment(dirname(__DIR__, 2), getenv(), $_ENV, $_SERVER);
            if (IntegrationContext::read($context['directory'], 'ownership.json') !== $record) {
                self::reject();
            }
            OwnedTestRun::validateCapturedRecord($context['job'], $record, $context['temp'], true);
            $expected = $record['containers'][$service];
            $id = $expected['id'];
            self::assertId($id);
            $docker = self::verified();
            // Only inspect the exact current recorded ID. Validate identity
            // again before obtaining bounded service diagnostics.
            $fields = ['.Id', '.Image', '.Config.Labels', '.State.Status', '.State.ExitCode', '.State.OOMKilled', '.State.Error'];
            $format = implode("\n", array_map(static fn (string $field): string => '{{json '.$field.'}}', $fields));
            $state = $docker->jsonLines($docker->command(['container', 'inspect', '--format', $format, $id])[0], count($fields));
            $labels = is_array($state[2]) ? array_filter($state[2], static fn ($key): bool => is_string($key) && str_starts_with($key, 'f03.'), ARRAY_FILTER_USE_KEY) : [];
            ksort($labels);
            $expectedLabels = $expected['labels'];
            ksort($expectedLabels);
            if ($state[0] !== $id || $state[1] !== $expected['image_id'] || $labels !== $expectedLabels
                || !in_array($state[3], ['created', 'running', 'exited', 'dead', 'restarting', 'paused', 'removing'], true)
                || !is_int($state[4]) || !is_bool($state[5]) || !is_string($state[6])) {
                self::reject();
            }
            echo 'Owned startup diagnostic: '.$service.' state='.$state[3].'; exit='.$state[4].'; oom='.($state[5] ? 'yes' : 'no').";\n";
            [$stdout, $stderr] = $docker->command(['logs', '--tail', '60', $id], true, true);
            echo 'Owned startup diagnostic: '.$service.' category='.self::startupCategory($state[6]."\n".$stdout."\n".$stderr).".\n";
            // Selected port metadata is classified, not printed. Never
            // infer authorization from diagnostics or reuse it for I/O.
            $ports = $docker->jsonLines($docker->command(['container', 'inspect', '--format',
                '{{json .NetworkSettings.Ports}}'."\n".'{{json .HostConfig.PortBindings}}', $id])[0], 2);
            $key = $service === 'postgres' ? '5432/tcp' : '6379/tcp';
            foreach (['live' => $ports[0], 'requested' => $ports[1]] as $kind => $map) {
                $binding = is_array($map) ? ($map[$key] ?? null) : null;
                $classification = $binding === null ? 'absent' : 'unexpected';
                if (is_array($binding) && count($binding) === 1 && ($binding[0]['HostIp'] ?? null) === '127.0.0.1') {
                    $port = $binding[0]['HostPort'] ?? null;
                    $classification = in_array($port, ['', '0'], true) ? 'dynamic-request'
                        : (is_string($port) && ctype_digit($port) && (int) $port >= 1024 && (int) $port <= 65535 ? 'loopback-allocated' : 'unexpected');
                }
                echo 'Owned startup diagnostic: '.$service.' '.$kind.' port='.$classification.".\n";
            }
        } catch (\Throwable) {
            echo "Owned startup diagnostic: exact identity/state evidence unavailable.\n";
        }
    }

    private function verifyPinnedImage(string $service): void
    {
        $pin = OwnedTestRun::image($service);
        $image = $this->jsonLines($this->command([
            'image', 'inspect', '--format', '{{json .Id}}'."\n".'{{json .Os}}'."\n".'{{json .Architecture}}'."\n".'{{json .RepoDigests}}',
            \Tests\Support\ci\OwnedServicePlan::imageRef($service),
        ])[0], 4);
        if ($image[0] !== $pin['image_id'] || $image[1] !== 'linux' || $image[2] !== 'amd64'
            || !is_array($image[3]) || (!in_array($service.'@'.$pin['manifest'], $image[3], true)
                && !in_array('docker.io/library/'.$service.'@'.$pin['manifest'], $image[3], true))) {
            self::reject();
        }
    }

    private function observeRecord(array $record, bool $partial): array
    {
        $context = IntegrationContext::environment(dirname(__DIR__, 2), getenv(), $_ENV, $_SERVER);
        if (IntegrationContext::read($context['directory'], 'ownership.json') !== $record) {
            self::reject();
        }
        OwnedTestRun::validateCapturedRecord($context['job'], $record, $context['temp'], $partial);
        $observed = [];
        foreach ($record['containers'] as $service => $container) {
            $observed[$service] = $this->observe($service, $container['id'], $record['directory'], $partial);
        }
        OwnedTestRun::validateRecord($context['job'], $record, $observed, $context['temp'], $partial);
        return $observed;
    }

    private function waitReady(array $record, string $service): void
    {
        $deadline = hrtime(true) + 60_000_000_000;
        $orderReported = false;
        do {
            $actual = $this->observeRecord($record, true);
            if (!OwnedTestRun::sameObservation($record['containers'][$service], $actual[$service])) {
                self::reject();
            }
            if ($actual[$service] !== $record['containers'][$service] && !$orderReported) {
                echo 'Owned readiness: '.$service." exact values match; associative key order differs.\n";
                $orderReported = true;
            }
            $id = $record['containers'][$service]['id'];
            $args = $service === 'postgres'
                ? ['exec', $id, 'pg_isready', '-h', '127.0.0.1', '-p', '5432', '-U', 'postgres', '-d', 'postgres', '-t', '2']
                : ['exec', $id, 'redis-cli', '-h', '127.0.0.1', '-p', '6379', 'PING'];
            [$stdout, $stderr, $status] = $this->command($args, true, true);
            if (($service === 'postgres' && $status === 0 && str_contains($stdout, 'accepting connections'))
                || ($service === 'redis' && trim($stdout."\n".$stderr) === 'NOAUTH Authentication required.')) {
                return;
            }
            usleep(250000);
        } while (hrtime(true) < $deadline);
        self::reject();
    }

    public function observe(string $service, string $id, string $directory, bool $cleanup = false): ?array
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

        return self::normalize($service, $id, $directory, $container, $image, $cleanup);
    }

    /** Pure parser also exercised with synthetic observations in fast tests. */
    public static function normalize(string $service, string $id, string $directory, array $container, array $image, bool $cleanup = false): array
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
            || !is_bool($running) || (!$running && !$cleanup) || !is_array($labels) || !is_array($mounts)) {
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
        if (!is_array($bindings) || array_keys($bindings) !== [$portKey]
            || !is_array($bindings[$portKey]) || count($bindings[$portKey]) !== 1) {
            self::reject();
        }
        $requested = $bindings[$portKey][0] ?? [];
        if (($requested['HostIp'] ?? null) !== '127.0.0.1') {
            self::reject();
        }
        $port = null;
        if ($running) {
            if (!is_array($ports) || array_keys($ports) !== [$portKey]
                || !is_array($ports[$portKey]) || count($ports[$portKey]) !== 1) {
                self::reject();
            }
            $binding = $ports[$portKey][0] ?? [];
            $port = $binding['HostPort'] ?? null;
            if (($binding['HostIp'] ?? null) !== '127.0.0.1'
                || !is_string($port) || !preg_match('/^[1-9][0-9]{3,4}$/D', $port)
                || (int) $port < 1024 || (int) $port > 65535
                || !in_array($requested['HostPort'] ?? null, ['', '0', $port], true)) {
                self::reject();
            }
            $port = (int) $port;
        } else {
            // A captured created/stopped container has no active mapping.
            // No service connection is allowed via this cleanup-only branch.
            if (!in_array($ports, [null, [], [$portKey => null]], true)
                || !is_string($requested['HostPort'] ?? null)
                || !preg_match('/^(?:|0|[1-9][0-9]{3,4})$/D', $requested['HostPort'])) {
                self::reject();
            }
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
            'labels' => $ownedLabels, 'host' => '127.0.0.1', 'port' => $port,
            'volumes' => $volumes,
        ];
    }

    /**
     * Current-job-only cleanup, no supplied container ID or filesystem path.
     * Validate all resources before mutation; recheck exact identity before
     * each deletion. Never delete an independently named Docker volume.
     */
    public static function cleanupCurrent(string $root): void
    {
        $context = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER, true);
        $directory = $context['directory'];
        if (!file_exists($directory) && !is_link($directory)) {
            // Idempotent absence only. This is not independent proof that
            // a previous interrupted provisioner captured every resource.
            return;
        }
        $record = IntegrationContext::read($directory, 'ownership.json');
        if (($record['token'] ?? null) !== $context['token']) {
            self::reject();
        }
        OwnedTestRun::validateCapturedRecord($context['job'], $record, $context['temp'], true);
        $files = \Tests\Support\ci\CleanupPolicy::files($directory);
        $docker = self::verified();
        $observed = [];
        foreach ($record['containers'] as $service => $container) {
            $observed[$service] = $docker->observe($service, $container['id'], $directory, true);
        }
        \Tests\Support\ci\CleanupPolicy::targets($context['job'], $record, $observed, $context['temp']);
        // Persist discovered volume identity before the first removal, so
        // a later failure cannot leave an absent ID with unknown volume.
        $record = IntegrationContext::completeOwnership($context, $record, $observed);
        if (\Tests\Support\ci\CleanupPolicy::files($directory) !== $files) {
            self::reject();
        }

        foreach ($record['containers'] as $service => $container) {
            $current = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER, false);
            if ($current !== $context || IntegrationContext::read($directory, 'ownership.json') !== $record) {
                self::reject();
            }
            $docker = self::verified();
            $fresh = $docker->observe($service, $container['id'], $directory, true);
            $single = $record;
            $single['containers'] = [$service => $container];
            \Tests\Support\ci\CleanupPolicy::targets($context['job'], $single, [$service => $fresh], $context['temp']);
            if (!OwnedTestRun::sameObservation($observed[$service], $fresh)) {
                self::reject();
            }

            $volumes = $fresh === null ? $container['volumes'] : $fresh['volumes'];
            if ($fresh !== null) {
                foreach ($volumes as $volume) {
                    // A volume must belong only to the exact recorded
                    // container. Never remove a shared or named volume.
                    $users = trim($docker->command(['ps', '--all', '--no-trunc', '--filter', 'volume='.$volume, '--format', '{{.ID}}'])[0]);
                    if ($users !== $container['id']) {
                        self::reject();
                    }
                }
                // Docker removes only this container and its anonymous
                // volumes. There is no independent volume rm fallback.
                $docker->command(['container', 'rm', '--force', '--volumes', $container['id']]);
                if ($docker->observe($service, $container['id'], $directory, true) !== null) {
                    self::reject();
                }
            }
            foreach ($volumes as $volume) {
                $names = preg_split('/\R/', trim($docker->command(['volume', 'ls', '--filter', 'name='.$volume, '--format', '{{.Name}}'])[0])) ?: [];
                if (in_array($volume, $names, true)) {
                    self::reject();
                }
            }
        }

        // Only exact allowlisted protected files in the verified directory.
        // No recursive removal, glob, broad storage cleanup or symlink
        // traversal. Recheck before touching any file.
        if (\Tests\Support\ci\CleanupPolicy::files($directory) !== $files) {
            self::reject();
        }
        // Remove the ownership record last, so a partial file cleanup
        // failure retains the captured identity for the next attempt.
        usort($files, static fn (string $a, string $b): int => (basename($a) === 'ownership.json') <=> (basename($b) === 'ownership.json'));
        foreach ($files as $file) {
            if (!in_array($file, \Tests\Support\ci\CleanupPolicy::files($directory), true) || !unlink($file)) {
                self::reject();
            }
        }
        if (!rmdir($directory)) {
            self::reject();
        }
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

    private function command(array $args, bool $explicitEndpoint = true, bool $allowFailure = false, #[\SensitiveParameter] ?string $input = null): array
    {
        $argv = [self::BINARY, ...($explicitEndpoint ? ['--host', self::ENDPOINT] : []), ...$args];
        if ($input !== null && strlen($input) > 8192) {
            self::reject();
        }
        $process = @proc_open($argv, [0 => $input === null ? ['file', '/dev/null', 'r'] : ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            self::reject();
        }
        $output = ['', ''];
        $deadline = hrtime(true) + 10_000_000_000;
        $exit = null;
        try {
            if ($input !== null) {
                stream_set_blocking($pipes[0], false);
                $offset = 0;
                while ($offset < strlen($input)) {
                    $written = fwrite($pipes[0], substr($input, $offset));
                    if ($written === false || hrtime(true) > $deadline) {
                        self::reject();
                    }
                    $offset += $written;
                    if ($written === 0) {
                        usleep(10000);
                    }
                }
                fclose($pipes[0]);
            }
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
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
        }
        if (!$allowFailure && ($exit ?? $closed) !== 0) {
            self::reject();
        }

        return [...$output, $exit ?? $closed];
    }

    private static function reject(): never
    {
        throw new RuntimeException('Local owned Docker observation failed.');
    }
}
