<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\OwnedTestRun;

function f03ValidateOwnedRun(FoundationOwnedRunFixture $fixture): OwnedTestRun
{
    expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();

    return OwnedTestRun::validate(
        $fixture->job,
        $fixture->record,
        $fixture->descriptor,
        $fixture->observed,
        $fixture->checkout->root,
    );
}

it('accepts a fully matched current job, pinned container observations and fixed runtime slots', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        expect(f03ValidateOwnedRun($fixture))->toBeInstanceOf(OwnedTestRun::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

dataset('f03 unsafe owned record', [
    'wrong repository' => ['job', ['repository'], 'other/synthetic'],
    'stale run id' => ['record', ['job', 'run_id'], '999999'],
    'stale attempt' => ['descriptor', ['job', 'run_attempt'], '2'],
    'wrong job' => ['descriptor', ['job', 'job'], 'unowned-job'],
    'self-hosted runner' => ['job', ['runner_environment'], 'self-hosted'],
    'wrong runner architecture' => ['job', ['runner_arch'], 'ARM64'],
    'remote Docker daemon' => ['job', ['docker_endpoint'], 'tcp://f03-synthetic-canary.invalid:2375'],
    'malformed token' => ['record', ['token'], 'not-a-run-token'],
    'different descriptor token' => ['descriptor', ['token'], 'fedcba9876543210'],
    'unowned path' => ['descriptor', ['directory'], '/f03-synthetic-canary'],
    'unexpected host' => ['descriptor', ['postgres', 'host'], 'f03-synthetic-canary.invalid'],
    'unexpected Redis host' => ['descriptor', ['redis', 'host'], '0.0.0.0'],
    'string instead of numeric port' => ['descriptor', ['postgres', 'port'], '45321'],
    'wrong dynamic port' => ['descriptor', ['redis', 'port'], 46380],
    'out of range port' => ['descriptor', ['postgres', 'port'], 65536],
    'wrong DB name' => ['descriptor', ['postgres', 'slots', 'a', 'database'], 'f03_synthetic_canary'],
    'wrong DB role' => ['descriptor', ['postgres', 'slots', 'b', 'role'], 'f03_synthetic_canary'],
    'wrong marker' => ['descriptor', ['postgres', 'slots', 'central', 'marker'], 'f03-synthetic-canary'],
    'wrong Redis prefix' => ['descriptor', ['redis', 'slots', 'b', 'prefix'], 'f03-synthetic-canary:'],
    'wrong Redis role' => ['descriptor', ['redis', 'slots', 'a', 'role'], 'f03_synthetic_canary'],
    'invalid password' => ['descriptor', ['redis', 'slots', 'a', 'password'], 'f03-synthetic-canary'],
    'supplied DSN' => ['descriptor', ['postgres', 'dsn'], 'f03-synthetic-canary'],
    'setup credential' => ['descriptor', ['admin_password'], 'f03-synthetic-canary'],
    'wrong observed container id' => ['observed', ['postgres', 'id'], str_repeat('e', 64)],
    'wrong observed manifest' => ['observed', ['redis', 'manifest'], 'sha256:'.str_repeat('e', 64)],
    'wrong observed image identity' => ['observed', ['postgres', 'image_id'], 'sha256:'.str_repeat('e', 64)],
    'wrong observed label' => ['observed', ['redis', 'labels', 'f03.token'], 'fedcba9876543210'],
    'wrong observed binding' => ['observed', ['postgres', 'host'], '0.0.0.0'],
    'wrong observed port' => ['observed', ['postgres', 'port'], 45322],
    'wrong observed volume' => ['observed', ['redis', 'volumes'], [str_repeat('e', 64)]],
]);

it('rejects mismatched identity or unsafe descriptor values before any service access', function (string $section, array $path, mixed $value): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $cursor = &$fixture->{$section};
        foreach ($path as $key) {
            $cursor = &$cursor[$key];
        }
        $cursor = $value;
        unset($cursor);

        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        $rejection = null;
        try {
            OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root);
        } catch (RuntimeException $error) {
            $rejection = $error;
        }
        expect($rejection)->toBeInstanceOf(RuntimeException::class);
        expect($rejection->getMessage())->not->toBeEmpty()->not->toContain('f03-synthetic-canary');
    } finally {
        $fixture->close();
    }
})->with('f03 unsafe owned record')->group('testing-foundation');

it('rejects missing, unexpected or duplicated fixed service slots and missing observations', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        match ($case) {
            'missing postgres slot' => $fixture->descriptor['postgres']['slots'] = ['a' => $fixture->descriptor['postgres']['slots']['a']],
            'extra postgres slot' => $fixture->descriptor['postgres']['slots']['external'] = $fixture->descriptor['postgres']['slots']['a'],
            'duplicate role' => $fixture->descriptor['postgres']['slots']['b']['role'] = $fixture->descriptor['postgres']['slots']['a']['role'],
            'extra redis slot' => $fixture->descriptor['redis']['slots']['central'] = $fixture->descriptor['redis']['slots']['a'],
            'missing container' => $fixture->observed = ['postgres' => $fixture->observed['postgres']],
            'duplicate container' => $fixture->observed['redis']['id'] = $fixture->observed['postgres']['id'],
        };
        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        expect(fn () => OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->with(['missing postgres slot', 'extra postgres slot', 'duplicate role', 'extra redis slot', 'missing container', 'duplicate container'])->group('testing-foundation');

it('rejects an otherwise consistent ownership record if the owned directory is a symlink', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $directory = $fixture->record['directory'];
        rmdir($directory);
        mkdir($fixture->checkout->root.'/synthetic-target', 0700);
        expect(symlink($fixture->checkout->root.'/synthetic-target', $directory))->toBeTrue();
        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        expect(fn () => OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
        expect(is_dir($fixture->checkout->root.'/synthetic-target'))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('does not allow a valid but different run record to authorize the current job', function (): void {
    $current = new FoundationOwnedRunFixture();
    $other = new FoundationOwnedRunFixture('fedcba9876543210', '999999', '2');
    try {
        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        expect(fn () => OwnedTestRun::validate($current->job, $other->record, $other->descriptor, $other->observed, $other->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $other->close();
        $current->close();
    }
})->group('testing-foundation');


it('rejects an owned run directory that is not private', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        expect(chmod($fixture->record['directory'], 0755))->toBeTrue();
        clearstatcache(true, $fixture->record['directory']);
        expect(is_callable([OwnedTestRun::class, 'validate']))->toBeTrue();
        expect(fn () => OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');


it('compares live captured identity without associative ordering artifacts while keeping all keys and scalar types exact', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $expected = $fixture->record['containers']['postgres'];
        $observed = array_reverse($expected, true);
        $observed['labels'] = array_reverse($expected['labels'], true);
        expect($observed === $expected)->toBeFalse();
        expect(OwnedTestRun::sameObservation($expected, $observed))->toBeTrue();
        $fixture->observed['postgres'] = $observed;
        expect(f03ValidateOwnedRun($fixture))->toBeInstanceOf(OwnedTestRun::class);
        foreach ([
            ['port' => (string) $expected['port']],
            ['port' => $expected['port'] + 1],
            ['port' => null],
            ['host' => '0.0.0.0'],
            ['volumes' => [str_repeat('e', 64)]],
            ['labels' => $expected['labels'] + ['f03.extra' => 'unexpected']],
            ['labels' => array_diff_key($expected['labels'], ['f03.token' => true])],
            ['id' => str_repeat('e', 64)],
        ] as $change) {
            expect(OwnedTestRun::sameObservation($expected, array_replace($observed, $change)))->toBeFalse();
        }
        expect(OwnedTestRun::sameObservation($expected, null))->toBeFalse();
        expect(OwnedTestRun::sameObservation(['volumes' => ['a', 'b']], ['volumes' => ['b', 'a']]))->toBeFalse();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');
