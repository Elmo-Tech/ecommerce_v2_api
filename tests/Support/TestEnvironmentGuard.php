<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Test-only accidental-target barrier, not a sandbox for arbitrary PHP.
 * No framework bootstrap, network access, dotenv reads or destructive work.
 */
final class TestEnvironmentGuard
{
    private const DEFAULTS = [
        'APP_ENV' => 'testing',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'http://localhost',
        'APP_KEY' => '',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '4',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'FILESYSTEM_DISK' => 'local',
        'PULSE_ENABLED' => 'false',
        'TELESCOPE_ENABLED' => 'false',
        'NIGHTWATCH_ENABLED' => 'false',
    ];

    public static function validateFast(string $root, array $process, array $env, array $server): array
    {
        self::validateCheckout($root);
        foreach ([$process, $env, $server] as $source) {
            foreach ($source as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (array_key_exists($key, self::DEFAULTS)) {
                    if ($value !== self::DEFAULTS[$key]) {
                        throw new RuntimeException('Unsafe fast test environment setting.');
                    }
                    continue;
                }
                if ($key === 'F03_REPORT_DIR' || $key === 'F03_MODE') {
                    if ($key === 'F03_MODE' && $value !== 'fast') {
                        throw new RuntimeException('Unexpected integration mode in fast tests.');
                    }
                    continue;
                }
                // Explicit service endpoints, credentials, cache overrides
                // and inherited integration state are never needed here.
                if (preg_match('/^(?:DB_|DATABASE_|PG[A-Z_]|MYSQL_|REDIS_|CACHE_|SESSION_|QUEUE_|MAIL_|SMTP_|BROADCAST_|PUSHER_|REVERB_|FILESYSTEM_|AWS_|AZURE_|GOOGLE_|GCS_|S3_|SQS_|DYNAMODB_|MEMCACHED_|BEANSTALKD_|F03_|DOCKER_|APP_(?:.*_CACHE|BASE_PATH|STORAGE_PATH|ENV_PATH|PREVIOUS_KEYS|MAINTENANCE_STORE)$|LARAVEL_STORAGE_PATH$|VIEW_COMPILED_PATH$)/iD', $key)
                    && $value !== '') {
                    throw new RuntimeException('Unexpected service, path or credential configuration.');
                }
            }
        }
        self::reportDirectory($process, $env, $server);

        return self::DEFAULTS;
    }

    public static function applyFast(string $root): void
    {
        $process = getenv();
        $defaults = self::validateFast($root, $process, $_ENV, $_SERVER);

        foreach ($defaults as $key => $value) {
            // All existing values were independently checked, not overwritten.
            if (!array_key_exists($key, $process) && !putenv($key.'='.$value)) {
                throw new RuntimeException('Cannot establish isolated test defaults.');
            }
            $_ENV[$key] ??= $value;
            $_SERVER[$key] ??= $value;
        }
    }

    private static function validateCheckout(string $root): void
    {
        if ($root === '' || realpath($root) !== $root || !is_dir($root) || is_link($root)) {
            throw new RuntimeException('Invalid isolated test checkout.');
        }
        $entries = @scandir($root);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect isolated test checkout.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.env') {
                $file = $root.'/.env';
                if (is_link($file) || !is_file($file) || @filesize($file) !== 0) {
                    throw new RuntimeException('Unsafe dotenv file in test checkout.');
                }
            } elseif (str_starts_with($entry, '.env.') && $entry !== '.env.example') {
                throw new RuntimeException('Unexpected environment file in test checkout.');
            }
        }

        $bootstrap = $root.'/bootstrap';
        $cache = $bootstrap.'/cache';
        if (is_link($bootstrap) || is_link($cache) || !is_dir($cache) || realpath($cache) !== $cache) {
            throw new RuntimeException('Invalid test bootstrap cache directory.');
        }
        $entries = @scandir($cache);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect test bootstrap cache.');
        }
        foreach ($entries as $entry) {
            if (is_link($cache.'/'.$entry) || $entry === 'config.php' || $entry === 'events.php'
                || preg_match('/^routes.*\.php$/D', $entry)) {
                throw new RuntimeException('Unexpected cached application configuration.');
            }
        }
    }

    /**
     * Reports are optional locally. In CI the owner creates a private direct
     * child of RUNNER_TEMP; a supplied path alone is not ownership.
     */
    public static function reportDirectory(array $process, array $env, array $server): ?string
    {
        $values = [];
        foreach ([$process, $env, $server] as $source) {
            if (array_key_exists('F03_REPORT_DIR', $source)) {
                $values[] = $source['F03_REPORT_DIR'];
            }
        }
        if ($values === []) {
            return null;
        }
        $dir = $values[0];
        foreach ($values as $value) {
            if ($value !== $dir) {
                throw new RuntimeException('Conflicting test report directory.');
            }
        }
        $temp = $process['RUNNER_TEMP'] ?? null;
        if (!is_string($temp) || $temp === '' || realpath($temp) !== $temp || !is_dir($temp)
            || !is_string($dir) || $dir === '' || dirname($dir) !== $temp
            || !preg_match('/^f03-reports-[a-zA-Z0-9_-]{8,80}$/D', basename($dir))
            || is_link($dir) || realpath($dir) !== $dir || !is_dir($dir)
            || ((@fileperms($dir) ?: 0) & 0777) !== 0700) {
            throw new RuntimeException('Invalid owned test report directory.');
        }
        foreach ([$env, $server] as $source) {
            if (array_key_exists('RUNNER_TEMP', $source) && $source['RUNNER_TEMP'] !== $temp) {
                throw new RuntimeException('Conflicting test runner directory.');
            }
        }

        return $dir;
    }

    public static function reportPath(string $name): string
    {
        $dir = self::reportDirectory(getenv(), $_ENV, $_SERVER);
        if ($dir === null || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\.xml$/D', $name)) {
            throw new RuntimeException('Invalid test report destination.');
        }
        $path = $dir.'/'.$name;
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Test report destination already exists.');
        }

        return $path;
    }
}
