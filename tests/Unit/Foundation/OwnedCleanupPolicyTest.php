<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\ci\CleanupPolicy;

function f03CleanupTargets(FoundationOwnedRunFixture $fixture): array
{
    expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();

    // Pure target selection. Do not pass synthetic targets to actual removal.
    return CleanupPolicy::targets(
        $fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root,
    );
}

it('selects only exact captured owned container IDs and anonymous volumes', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $targets = f03CleanupTargets($fixture);
        expect($targets)->toBe([
            'containers' => [
                ['id' => $fixture->record['containers']['postgres']['id'], 'volumes' => $fixture->record['containers']['postgres']['volumes']],
                ['id' => $fixture->record['containers']['redis']['id'], 'volumes' => $fixture->record['containers']['redis']['volumes']],
            ],
            'directory' => $fixture->record['directory'],
        ]);
        expect(is_dir($fixture->record['directory']))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('handles partial setup without inventing cleanup targets for a missing service', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        unset($fixture->record['containers']['redis'], $fixture->observed['redis']);
        $targets = f03CleanupTargets($fixture);
        expect($targets['containers'])->toBe([
            ['id' => $fixture->record['containers']['postgres']['id'], 'volumes' => $fixture->record['containers']['postgres']['volumes']],
        ]);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('treats positively verified absent containers as already removed without selecting other targets', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        // Null means a successful local inspection established the exact ID
        // is absent, not an inspection error. Missing observation must fail.
        $fixture->observed = ['postgres' => null, 'redis' => null];
        expect(f03CleanupTargets($fixture)['containers'])->toBe([]);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('refuses ambiguous or foreign cleanup identities without touching even the owned scratch directory', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        match ($case) {
            'wrong run' => $fixture->job['run_id'] = '999999',
            'different label' => $fixture->observed['postgres']['labels']['f03.token'] = 'fedcba9876543210',
            'different image' => $fixture->observed['redis']['image_id'] = 'sha256:'.str_repeat('e', 64),
            'different id' => $fixture->observed['redis']['id'] = str_repeat('e', 64),
            'different volume' => $fixture->observed['postgres']['volumes'] = [str_repeat('e', 64)],
            'unexpected container' => $fixture->observed['external'] = $fixture->observed['postgres'],
            'noncanonical path' => $fixture->record['directory'] = $fixture->checkout->root.'/../unowned',
            'remote daemon' => $fixture->job['docker_endpoint'] = 'tcp://f03-synthetic.invalid:2375',
        };
        expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();
        expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
        expect(is_dir($fixture->checkout->root))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->with(['wrong run', 'different label', 'different image', 'different id', 'different volume', 'unexpected container', 'noncanonical path', 'remote daemon'])->group('testing-foundation');

it('refuses cleanup when a local inspection did not return an explicit result for a captured ID', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        unset($fixture->observed['postgres']);
        expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();
        expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('refuses to select a symlink directory or touch its synthetic target', function (): void {
    $fixture = new FoundationOwnedRunFixture;
    try {
        $path = $fixture->record['directory'];
        rmdir($path);
        mkdir($fixture->checkout->root.'/foreign-target', 0700);
        expect(symlink($fixture->checkout->root.'/foreign-target', $path))->toBeTrue();
        expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();
        expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
        expect(is_dir($fixture->checkout->root.'/foreign-target'))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('cannot use a different run record to select cleanup targets for the current job', function (): void {
    $current = new FoundationOwnedRunFixture;
    $other = new FoundationOwnedRunFixture('fedcba9876543210', '999999', '2');
    try {
        expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();
        expect(fn () => CleanupPolicy::targets($current->job, $other->record, $other->observed, $other->checkout->root))->toThrow(RuntimeException::class);
        expect(is_dir($other->record['directory']))->toBeTrue();
    } finally {
        $other->close();
        $current->close();
    }
})->group('testing-foundation');
