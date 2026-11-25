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

    // Runner-installed Azure CLI metadata is not a service endpoint or
    // authentication setting. Do not exempt Azure configuration directories,
    // credentials, storage connection strings, or any arbitrary AZURE_* key.
    private const TOOL_METADATA = [
        'AZURE_EXTENSION_DIR',
        'AZURE_HTTP_USER_AGENT',
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
                        throw new RuntimeException('Unsafe fast test environment setting: '.$key.'.');
                    }
                    continue;
                }
                if (in_array($key, self::TOOL_METADATA, true)) {
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
                    // Report only fixed, known setting names/families, never the value
                    // or an arbitrary environment key.
                    $known = ['PGDATA', 'PGROOT', 'PG_CONFIG', 'PGHOST', 'PGPORT', 'PGUSER', 'PGPASSWORD', 'PGSERVICE', 'PGPASSFILE',
                        'DOCKER_CONFIG', 'DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH',
                        'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'GOOGLE_APPLICATION_CREDENTIALS'];
                    $category = 'service/path';
                    if (in_array($key, $known, true)) {
                        $category = $key;
                    } else {
                        foreach (['DB_', 'DATABASE_', 'PG', 'MYSQL_', 'REDIS_', 'CACHE_', 'SESSION_', 'QUEUE_', 'MAIL_', 'AWS_', 'AZURE_', 'GOOGLE_', 'DOCKER_', 'F03_', 'APP_'] as $prefix) {
                            if (str_starts_with($key, $prefix)) {
                                $category = $prefix;
                                break;
                            }
                        }
                    }
                    throw new RuntimeException('Unexpected service, path or credential configuration: '.$category.'.');
                }
            }
        }
        self::reportDirectory($process, $env, $server);

        return self::DEFAULTS;
    }

    public static function diagnostic(\Throwable $error): string
    {
        // Only messages authored by this guard are emitted. Do not expose
        // native exception messages or paths from arbitrary libraries.
        if ($error::class !== RuntimeException::class) {
            return 'Test environment check failed.';
        }
        $message = $error->getMessage();
        if (in_array($message, [
            'Invalid isolated test checkout.', 'Cannot inspect isolated test checkout.',
            'Unsafe dotenv file in test checkout.', 'Unexpected environment file in test checkout.',
            'Invalid test bootstrap cache directory.', 'Cannot inspect test bootstrap cache.',
            'Unexpected cached application configuration.', 'Unexpected integration mode in fast tests.',
            'Cannot establish isolated test defaults.', 'Conflicting test report directory.',
            'Invalid owned test report directory.', 'Conflicting test runner directory.',
            'Invalid test report destination.', 'Test report destination already exists.',
        ], true)) {
            return $message;
        }
        foreach (array_keys(self::DEFAULTS) as $key) {
            if ($message === 'Unsafe fast test environment setting: '.$key.'.') {
                return $message;
            }
        }
        // Fixed vocabulary only; no value or raw environment name is included.
        foreach (['service/path', 'PGDATA', 'PGROOT', 'PG_CONFIG', 'PGHOST', 'PGPORT', 'PGUSER', 'PGPASSWORD', 'PGSERVICE', 'PGPASSFILE',
            'DOCKER_CONFIG', 'DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH',
            'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'GOOGLE_APPLICATION_CREDENTIALS',
            'DB_', 'DATABASE_', 'PG', 'MYSQL_', 'REDIS_', 'CACHE_', 'SESSION_', 'QUEUE_', 'MAIL_', 'AWS_', 'AZURE_', 'GOOGLE_', 'DOCKER_', 'F03_', 'APP_'] as $category) {
            if ($message === 'Unexpected service, path or credential configuration: '.$category.'.') {
                return $message;
            }
        }
        return 'Test environment check failed.';
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
