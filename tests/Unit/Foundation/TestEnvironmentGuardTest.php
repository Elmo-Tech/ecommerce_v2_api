<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationTestCheckout;
use Tests\Support\TestEnvironmentGuard;

/**
 * @param array<array-key, mixed> $process
 * @param array<array-key, mixed> $env
 * @param array<array-key, mixed> $server
 * @return array<string, string>
 */
function f03ValidateFast(FoundationTestCheckout $checkout, array $process = [], array $env = [], array $server = []): array
{
    // An explicit assertion (not an undefined-class fatal) makes the
    // test-first missing behavior visible on the initial RED revision.
    expect(is_callable([TestEnvironmentGuard::class, 'validateFast']))->toBeTrue();

    return TestEnvironmentGuard::validateFast($checkout->root, $process, $env, $server);
}

it('accepts absent settings and returns the approved safe fast defaults', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $resolved = f03ValidateFast($checkout);
        expect($resolved)->toMatchArray([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'FILESYSTEM_DISK' => 'local',
        ]);
        expect(file_get_contents($checkout->root.'/.env'))->toBe('');
        expect($checkout->reachedAutoload())->toBeFalse();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('accepts consistent explicit safe values from each environment source without changing the input', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $safe = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array'];
        $snapshot = $safe;
        expect(f03ValidateFast($checkout, $safe, $safe, $safe))->toMatchArray($safe);
        expect($safe)->toBe($snapshot);
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

dataset('f03 unsafe fast settings', [
    'non-testing environment' => ['APP_ENV', 'production'],
    'development environment' => ['APP_ENV', 'local'],
    'real database driver' => ['DB_CONNECTION', 'pgsql'],
    'nonmemory database' => ['DB_DATABASE', 'f03-synthetic-do-not-use'],
    'database URL' => ['DB_URL', 'f03-synthetic-canary'],
    'database host' => ['DB_HOST', 'f03-synthetic-canary.invalid'],
    'database port' => ['DB_PORT', '5432'],
    'database username' => ['DB_USERNAME', 'f03-synthetic-canary'],
    'database password' => ['DB_PASSWORD', 'f03-synthetic-canary'],
    'database socket' => ['DB_SOCKET', 'f03-synthetic-canary'],
    'libpq service' => ['PGSERVICE', 'f03-synthetic-canary'],
    'libpq host' => ['PGHOST', 'f03-synthetic-canary.invalid'],
    'libpq password file' => ['PGPASSFILE', 'f03-synthetic-canary'],
    'redis URL' => ['REDIS_URL', 'f03-synthetic-canary'],
    'redis host' => ['REDIS_HOST', 'f03-synthetic-canary.invalid'],
    'redis username' => ['REDIS_USERNAME', 'f03-synthetic-canary'],
    'redis password' => ['REDIS_PASSWORD', 'f03-synthetic-canary'],
    'real cache' => ['CACHE_STORE', 'redis'],
    'real session' => ['SESSION_DRIVER', 'database'],
    'real queue' => ['QUEUE_CONNECTION', 'redis'],
    'mail transport' => ['MAIL_MAILER', 'smtp'],
    'broadcast transport' => ['BROADCAST_CONNECTION', 'pusher'],
    'remote file storage' => ['FILESYSTEM_DISK', 's3'],
    'ambient cloud key' => ['AWS_ACCESS_KEY_ID', 'f03-synthetic-canary'],
    'ambient cloud secret' => ['AWS_SECRET_ACCESS_KEY', 'f03-synthetic-canary'],
    'Azure credential' => ['AZURE_CLIENT_SECRET', 'f03-synthetic-canary'],
    'Azure storage connection' => ['AZURE_STORAGE_CONNECTION_STRING', 'f03-synthetic-canary'],
    'Azure config directory' => ['AZURE_CONFIG_DIR', 'f03-synthetic-canary'],
    'Google credentials' => ['GOOGLE_APPLICATION_CREDENTIALS', 'f03-synthetic-canary'],
    'libpq options' => ['PGOPTIONS', 'f03-synthetic-canary'],
    'cached config override' => ['APP_CONFIG_CACHE', 'f03-synthetic-canary'],
    'routes cache override' => ['APP_ROUTES_CACHE', 'f03-synthetic-canary'],
    'integration descriptor' => ['F03_RUN_DESCRIPTOR', 'f03-synthetic-canary'],
    'integration mode' => ['F03_MODE', 'integration'],
]);

it('rejects unsafe explicit settings independently of their source and does not echo values', function (string $key, string $value, int $source): void {
    $checkout = new FoundationTestCheckout();
    try {
        $sources = [[], [], []];
        $sources[$source][$key] = $value;

        // Check the entry contract before catching the expected exception:
        // a missing method must fail, not accidentally count as a rejection.
        expect(is_callable([TestEnvironmentGuard::class, 'validateFast']))->toBeTrue();
        $rejection = null;
        try {
            TestEnvironmentGuard::validateFast($checkout->root, ...$sources);
        } catch (RuntimeException $error) {
            $rejection = $error;
        }
        expect($rejection)->toBeInstanceOf(RuntimeException::class);
        expect($rejection->getMessage())->not->toBeEmpty()->not->toContain('f03-synthetic-canary');
        expect($checkout->reachedAutoload())->toBeFalse();
        expect(file_get_contents($checkout->root.'/.env'))->toBe('');
    } finally {
        $checkout->close();
    }
})->with('f03 unsafe fast settings')->with([0, 1, 2])->group('testing-foundation');

it('rejects conflicting sources instead of letting a safe value hide an unsafe one', function (int $source): void {
    $checkout = new FoundationTestCheckout();
    try {
        $sources = array_fill(0, 3, ['DB_DATABASE' => ':memory:', 'APP_ENV' => 'testing']);
        $sources[$source]['DB_DATABASE'] = 'f03-synthetic-canary';
        expect(is_callable([TestEnvironmentGuard::class, 'validateFast']))->toBeTrue();
        expect(fn () => TestEnvironmentGuard::validateFast($checkout->root, ...$sources))->toThrow(RuntimeException::class);
        expect($checkout->reachedAutoload())->toBeFalse();
    } finally {
        $checkout->close();
    }
})->with([0, 1, 2])->group('testing-foundation');


it('accepts only the explicit runner tool metadata without exempting cloud credentials', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $metadata = ['AZURE_HTTP_USER_AGENT' => 'synthetic-runner-metadata', 'AZURE_EXTENSION_DIR' => '/synthetic/tool-directory'];
        expect(f03ValidateFast($checkout, $metadata, $metadata, $metadata)['APP_ENV'])->toBe('testing');
        expect(fn () => TestEnvironmentGuard::validateFast($checkout->root, $metadata + ['AZURE_CLIENT_SECRET' => 'f03-synthetic-canary'], [], []))->toThrow(RuntimeException::class);
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');
