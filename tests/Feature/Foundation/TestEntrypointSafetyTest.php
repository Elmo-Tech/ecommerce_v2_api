<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationTestCheckout;

it('allows the actual configured default bootstrap to reach a harmless autoload sentinel with safe settings', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $result = $checkout->run([$checkout->configuredBootstrap()], ['APP_ENV' => 'testing']);
        expect($result->getExitCode())->toBe(0);
        expect($checkout->reachedAutoload())->toBeTrue();
        expect($checkout->reachedPest())->toBeFalse();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('rejects unsafe settings in the actual configured default bootstrap before its autoload sentinel', function (string $key, string $value): void {
    $checkout = new FoundationTestCheckout();
    try {
        // This executes the bootstrap selected by the actual phpunit.xml in
        // an owned scratch checkout, not the real Laravel/vendor bootstrap.
        // At the pre-guard revision it exits 0 and reaches the sentinel.
        $result = $checkout->run([$checkout->configuredBootstrap()], ['APP_ENV' => 'testing', $key => $value]);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
        expect($result->getOutput().$result->getErrorOutput())->not->toContain('f03-synthetic-canary');
    } finally {
        $checkout->close();
    }
})->with([
    ['APP_ENV', 'production'],
    ['DB_URL', 'f03-synthetic-canary'],
    ['REDIS_HOST', 'f03-synthetic-canary.invalid'],
    ['QUEUE_CONNECTION', 'redis'],
    ['F03_RUN_DESCRIPTOR', 'f03-synthetic-canary'],
])->group('testing-foundation');

it('runs the supported fast launcher with safe missing defaults and only reaches the fake local test runner', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        expect(is_file($checkout->root.'/tests/Support/run-tests.php'))->toBeTrue();
        $result = $checkout->run(['tests/Support/run-tests.php', 'fast']);
        expect($result->getExitCode())->toBe(0);
        expect($checkout->reachedPest())->toBeTrue();
        expect(file_get_contents($checkout->root.'/.env'))->toBe('');
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('blocks the fast launcher before autoload or test execution when the environment is unsafe', function (string $key, string $value): void {
    $checkout = new FoundationTestCheckout();
    try {
        expect(is_file($checkout->root.'/tests/Support/run-tests.php'))->toBeTrue();
        $result = $checkout->run(['tests/Support/run-tests.php', 'fast'], [$key => $value]);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
        expect($result->getOutput().$result->getErrorOutput())->not->toContain('f03-synthetic-canary');
    } finally {
        $checkout->close();
    }
})->with([
    ['APP_ENV', 'production'],
    ['DB_DATABASE', 'f03-synthetic-canary'],
    ['DB_PASSWORD', 'f03-synthetic-canary'],
    ['REDIS_URL', 'f03-synthetic-canary'],
    ['MAIL_MAILER', 'smtp'],
])->group('testing-foundation');

it('rejects unsafe dotenv and cache files without touching their contents or symlink targets', function (string $case): void {
    $checkout = new FoundationTestCheckout();
    $canary = 'f03-synthetic-canary';
    try {
        $path = match ($case) {
            'nonempty dotenv' => '.env',
            'unexpected testing dotenv' => '.env.testing',
            'cached config' => 'bootstrap/cache/config.php',
            'symlink dotenv' => '.env',
        };
        if ($case === 'symlink dotenv') {
            unlink($checkout->root.'/.env');
            $checkout->write('dotenv-target', $canary);
            expect(symlink($checkout->root.'/dotenv-target', $checkout->root.'/.env'))->toBeTrue();
        } else {
            $checkout->write($path, $canary);
        }
        // Test actual configured bootstrap as well as the launcher once
        // implemented. The synthetic cached file is not PHP-executed.
        $result = $checkout->run([$checkout->configuredBootstrap()], ['APP_ENV' => 'testing']);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($result->getOutput().$result->getErrorOutput())->not->toContain($canary);
        expect(file_get_contents($checkout->root.'/'.$path))->toBe($canary);
        if ($case === 'symlink dotenv') {
            expect(is_link($checkout->root.'/.env'))->toBeTrue();
            expect(file_get_contents($checkout->root.'/dotenv-target'))->toBe($canary);
        }
    } finally {
        $checkout->close();
    }
})->with(['nonempty dotenv', 'unexpected testing dotenv', 'cached config', 'symlink dotenv'])->group('testing-foundation');

it('rejects unsupported modes, suites, config overrides, arbitrary flags and escaping report paths before runner execution', function (array $arguments): void {
    $checkout = new FoundationTestCheckout();
    try {
        expect(is_file($checkout->root.'/tests/Support/run-tests.php'))->toBeTrue();
        $result = $checkout->run(['tests/Support/run-tests.php', ...$arguments]);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
    } finally {
        $checkout->close();
    }
})->with([
    'unknown mode' => [['arbitrary']],
    'empty mode' => [[]],
    'integration in fast mode' => [['fast', '--suite=Integration']],
    'unknown group' => [['fast', '--group=arbitrary']],
    'config override' => [['fast', '--configuration=other.xml']],
    'disable config' => [['fast', '--no-configuration']],
    'arbitrary argument' => [['fast', '--arbitrary']],
    'escaping report' => [['fast', '--report=../unowned.xml']],
])->group('testing-foundation');

it('boots the real fast Laravel test case with isolated effective config and no open database connection', function (): void {
    expect(config('app.env'))->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(config('database.connections.sqlite.url'))->toBeIn([null, ''])
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('filesystems.default'))->toBe('local')
        ->and(app('db')->getConnections())->toBeEmpty();
})->group('testing-foundation');


it('lets the installed Pest CLI execute exactly one harmless scratch probe when the environment is safe', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $result = $checkout->runPestProbe(['APP_ENV' => 'testing']);
        expect($result->getExitCode())->toBe(0);
        expect($checkout->reachedAutoload())->toBeTrue();
        expect($checkout->reachedProbe())->toBeTrue();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('rejects inherited unsafe configuration via the installed direct Pest CLI before configured autoload and probe execution', function (string $key, string $value): void {
    $checkout = new FoundationTestCheckout();
    try {
        $result = $checkout->runPestProbe(['APP_ENV' => 'testing', $key => $value]);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedProbe())->toBeFalse();
        expect($result->getOutput().$result->getErrorOutput())->not->toContain('f03-synthetic-canary');
    } finally {
        $checkout->close();
    }
})->with([
    ['APP_ENV', 'production'],
    ['DB_URL', 'f03-synthetic-canary'],
])->group('testing-foundation');


it('passes only an approved suite or group and a fresh report filename in a private owned report directory', function (): void {
    $checkout = new FoundationTestCheckout();
    try {
        $dir = $checkout->root.'/f03-reports-0123456789abcdef';
        expect(mkdir($dir, 0700))->toBeTrue();
        $result = $checkout->run(['tests/Support/run-tests.php', 'fast', '--suite=Unit', '--report=unit.xml'], [
            'RUNNER_TEMP' => $checkout->root, 'F03_REPORT_DIR' => $dir,
        ]);
        expect($result->getExitCode())->toBe(0);
        $argv = json_decode(file_get_contents($checkout->root.'/pest-reached'), true, flags: JSON_THROW_ON_ERROR);
        expect($argv)->toContain('--testsuite', 'Unit', '--log-junit', $dir.'/unit.xml');
        foreach (['--fail-on-empty-test-suite', '--fail-on-warning', '--fail-on-risky', '--fail-on-skipped', '--fail-on-incomplete'] as $flag) {
            expect($argv)->toContain($flag);
        }
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('rejects unsafe report targets even when a private report directory exists', function (string $case): void {
    $checkout = new FoundationTestCheckout();
    try {
        $dir = $checkout->root.'/f03-reports-0123456789abcdef';
        expect(mkdir($dir, 0700))->toBeTrue();
        $name = 'result.xml';
        if ($case === 'existing') {
            $checkout->write('f03-reports-0123456789abcdef/result.xml', 'untouched');
        } elseif ($case === 'symlink') {
            $checkout->write('outside.xml', 'untouched');
            expect(symlink($checkout->root.'/outside.xml', $dir.'/'.$name))->toBeTrue();
        } elseif ($case === 'escape') {
            $name = '../outside.xml';
        } elseif ($case === 'world-readable') {
            expect(chmod($dir, 0755))->toBeTrue();
        }
        $result = $checkout->run(['tests/Support/run-tests.php', 'fast', '--report='.$name], [
            'RUNNER_TEMP' => $checkout->root, 'F03_REPORT_DIR' => $dir,
        ]);
        expect($result->getExitCode())->not->toBe(0);
        expect($checkout->reachedPest())->toBeFalse();
        expect($checkout->reachedAutoload())->toBeFalse();
        if ($case === 'existing' || $case === 'symlink') {
            expect(file_get_contents($dir.'/result.xml'))->toBe('untouched');
        }
    } finally {
        $checkout->close();
    }
})->with(['existing', 'symlink', 'escape', 'world-readable'])->group('testing-foundation');
