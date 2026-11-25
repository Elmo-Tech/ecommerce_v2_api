<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationTestCheckout;
use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\OwnedTestRun;

it('does not allow integration mode to start the test runner without the owned job prerequisites', function (array $environment): void {
    $checkout = new FoundationTestCheckout;
    try {
        expect(is_file($checkout->root.'/tests/Support/run-tests.php'))->toBeTrue();
        $process = $checkout->run(['tests/Support/run-tests.php', 'integration'], $environment);
        expect($process->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
        expect($process->getOutput().$process->getErrorOutput())->not->toContain('f03-synthetic-canary');
    } finally {
        $checkout->close();
    }
})->with([
    'no owned descriptor' => [['APP_ENV' => 'testing']],
    'production environment' => [['APP_ENV' => 'production']],
    'wrong mode' => [['APP_ENV' => 'testing', 'F03_MODE' => 'fast']],
    'unknown descriptor' => [['APP_ENV' => 'testing', 'F03_RUN_DESCRIPTOR' => 'f03-synthetic-canary']],
    'remote daemon' => [['APP_ENV' => 'testing', 'DOCKER_HOST' => 'tcp://f03-synthetic-canary.invalid:2375']],
])->group('testing-foundation');

it('fails closed when the dedicated integration bootstrap is invoked without a verified job and descriptor', function (): void {
    $checkout = new FoundationTestCheckout;
    try {
        expect(is_file($checkout->root.'/tests/Support/integration-bootstrap.php'))->toBeTrue();
        $process = $checkout->run(['tests/Support/integration-bootstrap.php'], ['APP_ENV' => 'testing', 'F03_MODE' => 'integration']);
        expect($process->getExitCode())->not->toBe(0);
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('resolves only approved fixed postgres and redis slots, never an arbitrary target or fallback', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        $run = OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root);

        // These are pure validated target resolutions, no connector I/O.
        expect($run->postgresSlot('central')['database'])->toBe('f03_0123456789abcdef_central');
        expect($run->postgresSlot('a')['database'])->toBe('f03_0123456789abcdef_a');
        expect($run->postgresSlot('b')['database'])->toBe('f03_0123456789abcdef_b');
        expect($run->redisSlot('a')['prefix'])->toBe('f03:0123456789abcdef:a:');
        expect($run->redisSlot('b')['prefix'])->toBe('f03:0123456789abcdef:b:');
        foreach (['', 'default', 'external', 'postgres', 'template1', 'a;host=outside'] as $unknown) {
            expect(fn () => $run->postgresSlot($unknown))->toThrow(RuntimeException::class);
            expect(fn () => $run->redisSlot($unknown))->toThrow(RuntimeException::class);
        }
        expect(fn () => $run->redisSlot('central'))->toThrow(RuntimeException::class);
        expect($run->postgresSlot('a')['database'])->toBe('f03_0123456789abcdef_a');
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');
