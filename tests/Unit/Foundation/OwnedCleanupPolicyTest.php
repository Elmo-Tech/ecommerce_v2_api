<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationOwnedRunFixture;
use Tests\Support\ci\CleanupPolicy;

function f03CleanupTargets(FoundationOwnedRunFixture $fixture): array
{
    expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();

    // Pure target selection. Do not pass synthetic targets to actual removal.
    return CleanupPolicy::targets(
        $fixture->job,
        $fixture->record,
        $fixture->observed,
        $fixture->checkout->root,
    );
}

it('selects only exact captured owned container IDs and anonymous volumes', function (): void {
    $fixture = new FoundationOwnedRunFixture();
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
    $fixture = new FoundationOwnedRunFixture();
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
    $fixture = new FoundationOwnedRunFixture();
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
    $fixture = new FoundationOwnedRunFixture();
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
    $fixture = new FoundationOwnedRunFixture();
    try {
        unset($fixture->observed['postgres']);
        expect(is_callable([CleanupPolicy::class, 'targets']))->toBeTrue();
        expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('refuses to select a symlink directory or touch its synthetic target', function (): void {
    $fixture = new FoundationOwnedRunFixture();
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
    $current = new FoundationOwnedRunFixture();
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


it('allows cleanup-only lifecycle transitions but never a changed known port or volume', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $record = &$fixture->record['containers']['postgres'];
        $observed = &$fixture->observed['postgres'];
        if ($case === 'captured before allocation') {
            $record['port'] = null;
            $record['volumes'] = [];
        } elseif ($case === 'stopped after allocation') {
            $observed['port'] = null;
        } elseif ($case === 'known port changed') {
            $observed['port']++;
        } elseif ($case === 'known volume changed') {
            $observed['volumes'] = [str_repeat('e', 64)];
        } elseif ($case === 'unknown volume and absent container') {
            $record['port'] = null;
            $record['volumes'] = [];
            $fixture->observed['postgres'] = null;
        }
        unset($record, $observed);

        if (in_array($case, ['captured before allocation', 'stopped after allocation'], true)) {
            $targets = f03CleanupTargets($fixture);
            expect($targets['containers'][0])->toBe([
                'id' => str_repeat('a', 64),
                'volumes' => [str_repeat('c', 64)],
            ]);
            expect(fn () => \Tests\Support\OwnedTestRun::validate($fixture->job, $fixture->record, $fixture->descriptor, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
        } else {
            expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
        }
    } finally {
        $fixture->close();
    }
})->with(['captured before allocation', 'stopped after allocation', 'known port changed', 'known volume changed', 'unknown volume and absent container'])->group('testing-foundation');

it('selects only private known cleanup files and never deletes or follows a foreign path during selection', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $directory = $fixture->record['directory'];
        $path = $directory.'/ownership.json';
        file_put_contents($path, '{}');
        chmod($path, 0600);
        if ($case === 'foreign file') {
            file_put_contents($directory.'/unowned.txt', 'untouched');
            chmod($directory.'/unowned.txt', 0600);
        } elseif ($case === 'symlink') {
            $fixture->checkout->write('untouched', 'untouched');
            expect(symlink($fixture->checkout->root.'/untouched', $directory.'/runtime.json'))->toBeTrue();
        } elseif ($case === 'hardlink') {
            expect(link($path, $directory.'/runtime.json'))->toBeTrue();
        } elseif ($case === 'public file') {
            chmod($path, 0644);
        } elseif ($case === 'nested directory') {
            mkdir($directory.'/unowned', 0700);
        }
        if ($case === 'valid') {
            expect(CleanupPolicy::files($directory))->toBe([$path]);
        } else {
            expect(fn () => CleanupPolicy::files($directory))->toThrow(RuntimeException::class);
        }
        expect(file_get_contents($path))->toBe('{}');
    } finally {
        $fixture->close();
    }
})->with(['valid', 'foreign file', 'symlink', 'hardlink', 'public file', 'nested directory'])->group('testing-foundation');


it('persists the verified missing volume before removal and keeps a repeat cleanup verifiable after absence', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $fixture->record['containers']['postgres']['port'] = null;
        $fixture->record['containers']['postgres']['volumes'] = [];
        $directory = $fixture->record['directory'];
        $context = ['job' => $fixture->job, 'token' => $fixture->record['token'], 'temp' => $fixture->checkout->root, 'directory' => $directory];
        $path = $directory.'/ownership.json';
        file_put_contents($path, json_encode($fixture->record, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        $completed = \Tests\Support\IntegrationContext::completeOwnership($context, $fixture->record, $fixture->observed);
        expect($completed['containers']['postgres'])->toBe($fixture->observed['postgres']);
        expect(\Tests\Support\IntegrationContext::read($directory, 'ownership.json'))->toBe($completed);
        expect(file_exists($directory.'/ownership.next.json'))->toBeFalse();
        $fixture->observed['postgres'] = null;
        expect(CleanupPolicy::targets($fixture->job, $completed, $fixture->observed, $fixture->checkout->root)['containers'])->toBe([
            ['id' => $completed['containers']['redis']['id'], 'volumes' => $completed['containers']['redis']['volumes']],
        ]);
        expect(\Tests\Support\IntegrationContext::completeOwnership($context, $completed, $fixture->observed))->toBe($completed);
        expect(is_file($path))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('refuses a stale ownership record or existing staging file without replacing it', function (string $case): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $fixture->record['containers']['postgres']['port'] = null;
        $fixture->record['containers']['postgres']['volumes'] = [];
        $directory = $fixture->record['directory'];
        $context = ['job' => $fixture->job, 'token' => $fixture->record['token'], 'temp' => $fixture->checkout->root, 'directory' => $directory];
        $path = $directory.'/ownership.json';
        $stored = $fixture->record;
        if ($case === 'stale record') {
            $stored['containers']['postgres']['port'] = 45321;
        }
        $raw = json_encode($stored, JSON_THROW_ON_ERROR);
        file_put_contents($path, $raw);
        chmod($path, 0600);
        if ($case === 'existing staging') {
            file_put_contents($directory.'/ownership.next.json', 'untouched');
            chmod($directory.'/ownership.next.json', 0600);
        }
        expect(fn () => \Tests\Support\IntegrationContext::completeOwnership($context, $fixture->record, $fixture->observed))->toThrow(RuntimeException::class);
        expect(file_get_contents($path))->toBe($raw);
        if ($case === 'existing staging') {
            expect(file_get_contents($directory.'/ownership.next.json'))->toBe('untouched');
        }
    } finally {
        $fixture->close();
    }
})->with(['stale record', 'existing staging'])->group('testing-foundation');

it('rejects an incomplete capture claiming a different recorded service volume even when that service is absent', function (): void {
    $fixture = new FoundationOwnedRunFixture();
    try {
        $fixture->record['containers']['postgres']['volumes'] = [];
        $fixture->observed['postgres']['volumes'] = $fixture->record['containers']['redis']['volumes'];
        $fixture->observed['redis'] = null;
        expect(fn () => CleanupPolicy::targets($fixture->job, $fixture->record, $fixture->observed, $fixture->checkout->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('refuses direct cleanup CLI without verified job or with arbitrary arguments before any resource operation', function (array $args, array $env): void {
    $checkout = new \Tests\Fixtures\FoundationTestCheckout();
    try {
        expect(is_file($checkout->root.'/tests/Support/ci/cleanup-owned.php'))->toBeTrue();
        $result = $checkout->run(['tests/Support/ci/cleanup-owned.php', ...$args], $env);
        expect($result->getExitCode())->toBe(2);
        expect($result->getErrorOutput())->toContain('Owned cleanup rejected or incomplete.');
        expect($result->getErrorOutput())->not->toContain('f03-synthetic-canary', 'Warning', 'Fatal error');
        expect($checkout->reachedAutoload())->toBeFalse();
        expect($checkout->reachedPest())->toBeFalse();
        expect(is_dir($checkout->root))->toBeTrue();
    } finally {
        $checkout->close();
    }
})->with([
    [[], []],
    [[], ['APP_ENV' => 'production', 'F03_MODE' => 'integration']],
    [['f03-synthetic-canary'], ['APP_ENV' => 'testing', 'F03_MODE' => 'integration']],
    [[], ['APP_ENV' => 'testing', 'F03_MODE' => 'integration', 'DOCKER_HOST' => 'tcp://f03-synthetic-canary.invalid:2375']],
])->group('testing-foundation');
