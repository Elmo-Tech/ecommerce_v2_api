<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Fixtures\FoundationTestCheckout;
use Tools\Quality\LaneEvidence;
use Tools\Quality\OwnedEvidenceDirectory;

/**
 * Synthetic inputs only. These tests do not stand in for actual CI reports.
 * The missing implementation must fail outside the rejection assertions.
 */
function f04LoadEvidencePolicy(): void
{
    $path = dirname(__DIR__, 3).'/tools/quality/evidence.php';
    Assert::assertTrue(is_file($path), 'F0-04 evidence policy must be implemented.');
    require_once $path;
    Assert::assertTrue(is_callable([LaneEvidence::class, 'validate']));
    Assert::assertTrue(is_callable([OwnedEvidenceDirectory::class, 'create']));
}

/**
 * @return array<string, mixed>
 */
function f04ReportFixture(string $status = 'PASSED'): array
{
    $report = [
        'schemaVersion' => 1,
        'repository' => 'Elmo-Tech/ecommerce_v2_api',
        'event' => 'pull_request',
        'pr' => 9,
        'sourceSha' => str_repeat('a', 40),
        'baseSha' => str_repeat('b', 40),
        'checkoutSha' => str_repeat('c', 40),
        'runId' => '123456',
        'runAttempt' => 1,
        'job' => 'format',
        'required' => true,
        'selectionReason' => 'php-change',
        'status' => $status,
        'runnerStarted' => true,
        'exitCode' => 0,
        'versions' => ['php' => '8.4.28', 'pint' => '1.32.3'],
        'durationSeconds' => 1.25,
        'selectedCount' => 2,
        'validatedCount' => 2,
        'artifacts' => ['format-result.json' => str_repeat('d', 64)],
        'findingCounts' => ['errors' => 0, 'warnings' => 0],
    ];
    if ($status === 'FAILED') {
        $report['exitCode'] = 1;
        $report['validatedCount'] = 1;
        $report['findingCounts']['errors'] = 1;
    } elseif ($status === 'BLOCKED' || $status === 'NOT_APPLICABLE') {
        $report['runnerStarted'] = false;
        $report['exitCode'] = null;
        $report['versions'] = [];
        $report['durationSeconds'] = 0;
        $report['selectedCount'] = null;
        $report['validatedCount'] = null;
        $report['artifacts'] = [];
        $report['findingCounts'] = [];
        if ($status === 'NOT_APPLICABLE') {
            $report['required'] = false;
            $report['selectionReason'] = 'docs-only';
        }
    }

    return $report;
}

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function f04ExpectedIdentity(array $report): array
{
    return array_intersect_key($report, array_flip([
        'schemaVersion', 'repository', 'event', 'pr', 'sourceSha', 'baseSha',
        'checkoutSha', 'runId', 'runAttempt', 'job', 'required', 'selectionReason',
    ]));
}

/**
 * @param array<string, mixed> $report
 */
function f04EvidenceJson(array $report): string
{
    foreach (['versions', 'artifacts', 'findingCounts'] as $field) {
        if (($report[$field] ?? null) === []) {
            $report[$field] = (object) [];
        }
    }

    return json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

it('has independently parseable synthetic evidence inputs and distinct trusted identities', function (): void {
    $pass = f04ReportFixture();
    $na = f04ReportFixture('NOT_APPLICABLE');
    expect(json_decode(f04EvidenceJson($pass), true, flags: JSON_THROW_ON_ERROR))->toBe($pass);
    expect($pass['required'])->toBeTrue();
    expect($na['required'])->toBeFalse();
    expect(f04ExpectedIdentity($pass))->not->toBe(f04ExpectedIdentity($na));
    expect($pass['artifacts']['format-result.json'])->toHaveLength(64);
})->group('testing-foundation');

it('validates a bounded lane report with the exact trusted candidate and honest status', function (string $status): void {
    f04LoadEvidencePolicy();
    $report = f04ReportFixture($status);
    $json = f04EvidenceJson($report);

    expect(LaneEvidence::validate($json, f04ExpectedIdentity($report), ['format-result.json']))
        ->toBe(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
})->with(['PASSED', 'FAILED', 'BLOCKED', 'NOT_APPLICABLE'])->group('testing-foundation');

dataset('f04 invalid evidence field', [
    'schema' => ['schemaVersion', 2],
    'repository' => ['repository', 'other/example'],
    'event' => ['event', 'pull_request_target'],
    'pr' => ['pr', 10],
    'wrong source' => ['sourceSha', str_repeat('e', 40)],
    'wrong base' => ['baseSha', str_repeat('e', 40)],
    'wrong checkout' => ['checkoutSha', str_repeat('e', 40)],
    'malformed sha' => ['sourceSha', 'not-a-sha'],
    'wrong run' => ['runId', '123457'],
    'wrong attempt' => ['runAttempt', 2],
    'wrong lane' => ['job', 'contracts'],
    'required mismatch' => ['required', false],
    'untrusted selection reason' => ['selectionReason', 'docs-only'],
    'unknown status' => ['status', 'SUCCESS'],
    'required not applicable' => ['status', 'NOT_APPLICABLE'],
    'missing runner' => ['runnerStarted', false],
    'false green exit' => ['exitCode', 1],
    'exit string' => ['exitCode', '0'],
    'negative duration' => ['durationSeconds', -1],
    'excessive duration' => ['durationSeconds', 86401],
    'duration string' => ['durationSeconds', '1.25'],
    'negative count' => ['selectedCount', -1],
    'incomplete selection' => ['validatedCount', 1],
    'excess validated count' => ['validatedCount', 3],
    'one missing count' => ['selectedCount', null],
    'unknown version key' => ['versions', ['unapproved' => '1.0.0']],
    'unsafe version' => ['versions', ['php' => 'https://synthetic.invalid']],
    'missing artifact' => ['artifacts', []],
    'wrong artifact name' => ['artifacts', ['unapproved.json' => str_repeat('d', 64)]],
    'traversal artifact' => ['artifacts', ['../format-result.json' => str_repeat('d', 64)]],
    'bad digest' => ['artifacts', ['format-result.json' => 'not-a-digest']],
    'negative findings' => ['findingCounts', ['errors' => -1]],
    'unknown findings' => ['findingCounts', ['raw' => 1]],
    'unknown field' => ['rawOutput', 'synthetic-unapproved-text'],
]);

it('rejects an invalid field without trusting the claimed report identity or success', function (string $field, mixed $value): void {
    f04LoadEvidencePolicy();
    $report = f04ReportFixture();
    $identity = f04ExpectedIdentity($report);
    $report[$field] = $value;

    expect(fn () => LaneEvidence::validate(f04EvidenceJson($report), $identity, ['format-result.json']))
        ->toThrow(RuntimeException::class);
})->with('f04 invalid evidence field')->group('testing-foundation');

it('rejects missing fields duplicate keys malformed JSON and excessive report size', function (string $case): void {
    f04LoadEvidencePolicy();
    $report = f04ReportFixture();
    $identity = f04ExpectedIdentity($report);
    $json = f04EvidenceJson($report);
    if ($case === 'missing field') {
        unset($report['runnerStarted']);
        $json = f04EvidenceJson($report);
    } elseif ($case === 'duplicate top-level') {
        $json = str_replace('"schemaVersion":1', '"schemaVersion":1,"schemaVersion":1', $json);
    } elseif ($case === 'duplicate nested') {
        $json = str_replace('"php":"8.4.28"', '"php":"8.4.28","php":"8.4.28"', $json);
    } elseif ($case === 'escaped duplicate') {
        $json = str_replace('"php":"8.4.28"', '"php":"8.4.28","p\u0068p":"8.4.28"', $json);
    } elseif ($case === 'malformed') {
        $json = '{"schemaVersion":';
    } elseif ($case === 'oversize') {
        $json = $json.str_repeat(' ', 1048577);
    }

    expect(fn () => LaneEvidence::validate($json, $identity, ['format-result.json']))
        ->toThrow(RuntimeException::class);
})->with(['missing field', 'duplicate top-level', 'duplicate nested', 'escaped duplicate', 'malformed', 'oversize'])->group('testing-foundation');

it('rejects inconsistent blocked failed and not-applicable evidence without inventing execution', function (string $status, string $field, mixed $value): void {
    f04LoadEvidencePolicy();
    $report = f04ReportFixture($status);
    $identity = f04ExpectedIdentity($report);
    $report[$field] = $value;

    expect(fn () => LaneEvidence::validate(f04EvidenceJson($report), $identity, ['format-result.json']))
        ->toThrow(RuntimeException::class);
})->with([
    ['FAILED', 'exitCode', 0],
    ['FAILED', 'runnerStarted', false],
    ['BLOCKED', 'selectedCount', 2],
    ['BLOCKED', 'exitCode', 0],
    ['NOT_APPLICABLE', 'runnerStarted', true],
    ['NOT_APPLICABLE', 'artifacts', ['format-result.json' => str_repeat('d', 64)]],
    ['NOT_APPLICABLE', 'durationSeconds', 1],
])->group('testing-foundation');

it('rejects generated credential canaries even in a syntactically valid version without exposing them', function (bool $escaped): void {
    f04LoadEvidencePolicy();
    $report = f04ReportFixture();
    $canary = '9.'.random_int(10000, 99999).'.'.random_int(10000, 99999);
    $report['versions']['php'] = $canary;
    $json = f04EvidenceJson($report);
    if ($escaped) {
        $json = str_replace('"'.$canary.'"', '"\u0039'.substr($canary, 1).'"', $json);
    }
    $failure = null;
    try {
        LaneEvidence::validate($json, f04ExpectedIdentity($report), ['format-result.json'], [$canary]);
    } catch (RuntimeException $error) {
        $failure = $error;
    }
    expect($failure)->toBeInstanceOf(RuntimeException::class);
    expect($failure->getMessage())->not()->toContain($canary)->not()->toBeEmpty();
})->with([false, true])->group('testing-foundation');

it('creates and cleans only the exact private owned evidence directory', function (): void {
    f04LoadEvidencePolicy();
    $checkout = new FoundationTestCheckout();
    try {
        $parent = $checkout->root.'/reports';
        $token = bin2hex(random_bytes(16));
        $owned = OwnedEvidenceDirectory::create($parent, $token, f04ExpectedIdentity(f04ReportFixture()), ['format-result.json']);
        $path = $owned->path();
        expect($path)->toBe($parent.'/f04-quality-'.$token);
        expect(fileperms($path) & 0777)->toBe(0700);
        $owned->write('format-result.json', '{"safe":true}');
        expect(file_get_contents($path.'/format-result.json'))->toBe('{"safe":true}');
        expect(fileperms($path.'/format-result.json') & 0777)->toBe(0600);
        $owned->cleanup();
        clearstatcache(true, $path);
        expect(file_exists($path))->toBeFalse();
        expect(is_dir($parent))->toBeTrue();
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('refuses evidence directory collisions and invalid file destinations without overwriting or following links', function (string $case): void {
    f04LoadEvidencePolicy();
    $checkout = new FoundationTestCheckout();
    try {
        $parent = $checkout->root.'/reports';
        $token = bin2hex(random_bytes(16));
        $identity = f04ExpectedIdentity(f04ReportFixture());
        $owned = OwnedEvidenceDirectory::create($parent, $token, $identity, ['format-result.json']);
        $path = $owned->path();
        $outside = $parent.'/outside.json';
        $checkout->write('reports/outside.json', 'untouched');
        if ($case === 'directory collision') {
            expect(fn () => OwnedEvidenceDirectory::create($parent, $token, $identity, ['format-result.json']))
                ->toThrow(RuntimeException::class);
        } elseif ($case === 'file collision') {
            $owned->write('format-result.json', 'first');
            expect(fn () => $owned->write('format-result.json', 'second'))->toThrow(RuntimeException::class);
            expect(file_get_contents($path.'/format-result.json'))->toBe('first');
        } elseif ($case === 'symlink') {
            expect(symlink($outside, $path.'/format-result.json'))->toBeTrue();
            expect(fn () => $owned->write('format-result.json', 'overwrite'))->toThrow(RuntimeException::class);
        } else {
            $name = $case === 'traversal' ? '../outside.json' : ($case === 'absolute' ? $outside : 'unapproved.json');
            expect(fn () => $owned->write($name, 'overwrite'))->toThrow(RuntimeException::class);
        }
        expect(file_get_contents($outside))->toBe('untouched');
        expect(is_dir($path))->toBeTrue();
    } finally {
        $checkout->close();
    }
})->with(['directory collision', 'file collision', 'symlink', 'traversal', 'absolute', 'unknown'])->group('testing-foundation');

it('refuses ambiguous evidence cleanup before deleting any allowed file', function (string $case): void {
    f04LoadEvidencePolicy();
    $checkout = new FoundationTestCheckout();
    try {
        $parent = $checkout->root.'/reports';
        $owned = OwnedEvidenceDirectory::create($parent, bin2hex(random_bytes(16)), f04ExpectedIdentity(f04ReportFixture()), ['format-result.json', 'second.json']);
        $path = $owned->path();
        $owned->write('format-result.json', 'retain');
        $outside = $parent.'/outside.json';
        $checkout->write('reports/outside.json', 'untouched');
        if ($case === 'unknown entry') {
            file_put_contents($path.'/unknown', 'sentinel');
        } elseif ($case === 'symlink') {
            expect(symlink($outside, $path.'/second.json'))->toBeTrue();
        } elseif ($case === 'hard link') {
            expect(link($outside, $path.'/second.json'))->toBeTrue();
        } elseif ($case === 'foreign marker') {
            file_put_contents($path.'/.owner', 'foreign');
        } elseif ($case === 'missing marker') {
            expect(unlink($path.'/.owner'))->toBeTrue();
        } elseif ($case === 'unsafe mode') {
            expect(chmod($path.'/format-result.json', 0644))->toBeTrue();
        }

        expect(fn () => $owned->cleanup())->toThrow(RuntimeException::class);
        expect(file_get_contents($path.'/format-result.json'))->toBe('retain');
        expect(file_get_contents($outside))->toBe('untouched');
    } finally {
        $checkout->close();
    }
})->with(['unknown entry', 'symlink', 'hard link', 'foreign marker', 'missing marker', 'unsafe mode'])->group('testing-foundation');
