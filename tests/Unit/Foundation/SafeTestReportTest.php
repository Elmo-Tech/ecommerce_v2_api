<?php

declare(strict_types=1);

use Tests\Fixtures\FoundationTestCheckout;
use Tests\Support\ci\SafeTestReport;

function f03ReportFixture(string $body): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="synthetic">'.$body.'</testsuite></testsuites>';
}

it('reads scoped synthetic JUnit cases and independently counts results without treating the fixture as a real run', function (): void {
    $checkout = new FoundationTestCheckout;
    try {
        $checkout->write('reports/result.xml', f03ReportFixture('<testcase name="case-one" assertions="2" time="0.10"/><testcase name="case-two" assertions="3" time="0.20"/>'));
        expect(is_callable([SafeTestReport::class, 'inspect']))->toBeTrue();
        $result = SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], ['case-one', 'case-two']);
        expect($result)->toMatchArray(['tests' => 2, 'assertions' => 5, 'failures' => 0, 'errors' => 0, 'skipped' => 0]);
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('rejects missing malformed empty or incomplete-selection reports instead of manufacturing a passing count', function (string $case): void {
    $checkout = new FoundationTestCheckout;
    try {
        match ($case) {
            'missing' => null,
            'malformed' => $checkout->write('reports/result.xml', '<broken>'),
            'empty' => $checkout->write('reports/result.xml', f03ReportFixture('')),
            'wrong selection' => $checkout->write('reports/result.xml', f03ReportFixture('<testcase name="other" assertions="1"/>')),
            'DTD' => $checkout->write('reports/result.xml', '<!DOCTYPE testsuites [<!ENTITY canary "local-only">]><testsuites><testsuite><testcase name="&canary;"/></testsuite></testsuites>'),
        };
        expect(is_callable([SafeTestReport::class, 'inspect']))->toBeTrue();
        expect(fn () => SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], ['required-case']))->toThrow(RuntimeException::class);
    } finally {
        $checkout->close();
    }
})->with(['missing', 'malformed', 'empty', 'wrong selection', 'DTD'])->group('testing-foundation');

it('rejects report paths outside the owned directory or through symlinks', function (string $case): void {
    $checkout = new FoundationTestCheckout;
    try {
        $checkout->write('outside.xml', f03ReportFixture('<testcase name="case-one" assertions="1"/>'));
        $path = $checkout->root.'/outside.xml';
        if ($case === 'symlink') {
            expect(symlink($path, $checkout->root.'/reports/result.xml'))->toBeTrue();
            $path = $checkout->root.'/reports/result.xml';
        }
        expect(is_callable([SafeTestReport::class, 'inspect']))->toBeTrue();
        expect(fn () => SafeTestReport::inspect($path, $checkout->root.'/reports', [], ['case-one']))->toThrow(RuntimeException::class);
        expect(is_file($checkout->root.'/outside.xml'))->toBeTrue();
    } finally {
        $checkout->close();
    }
})->with(['outside', 'symlink'])->group('testing-foundation');

it('blocks reports containing generated credential canaries without echoing them', function (bool $encoded): void {
    $checkout = new FoundationTestCheckout;
    try {
        $canary = 'f03-synthetic-credential<&>';
        $raw = $encoded ? htmlspecialchars($canary, ENT_QUOTES | ENT_XML1, 'UTF-8') : 'f03-synthetic-credential-hex';
        $secret = $encoded ? $canary : $raw;
        $checkout->write('reports/result.xml', f03ReportFixture('<testcase name="case-one"><failure>'.$raw.'</failure></testcase>'));
        expect(is_callable([SafeTestReport::class, 'inspect']))->toBeTrue();
        $failure = null;
        try {
            SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [$secret], ['case-one']);
        } catch (RuntimeException $error) {
            $failure = $error;
        }
        expect($failure)->toBeInstanceOf(RuntimeException::class);
        expect($failure->getMessage())->not->toContain($secret)->not->toContain($raw);
    } finally {
        $checkout->close();
    }
})->with([false, true])->group('testing-foundation');

it('counts failure error and skipped cases from real JUnit structure even when a caller exit is zero', function (): void {
    $checkout = new FoundationTestCheckout;
    try {
        $checkout->write('reports/result.xml', f03ReportFixture('<testcase name="failed" assertions="1"><failure>safe failure</failure></testcase><testcase name="error" assertions="0"><error>safe error</error></testcase><testcase name="skipped" assertions="0"><skipped/></testcase>'));
        expect(is_callable([SafeTestReport::class, 'inspect']))->toBeTrue();
        $result = SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], ['failed', 'error', 'skipped']);
        expect($result)->toMatchArray(['tests' => 3, 'failures' => 1, 'errors' => 1, 'skipped' => 1]);
        expect(SafeTestReport::outcome(0, $result, 0, 0)['exit_code'])->not->toBe(0);
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('preserves original runner failure, missing reports, cleanup and upload failures, and explicit blocked preflight', function (int $runner, ?array $report, int $cleanup, int $upload, string $status, int $exit): void {
    expect(is_callable([SafeTestReport::class, 'outcome']))->toBeTrue();
    $outcome = SafeTestReport::outcome($runner, $report, $cleanup, $upload);
    expect($outcome)->toMatchArray(['status' => $status, 'exit_code' => $exit]);
})->with([
    'runner failed' => [1, ['tests' => 1, 'failures' => 1, 'errors' => 0, 'skipped' => 0], 0, 0, 'FAILED', 1],
    'original nonzero retained' => [2, null, 3, 4, 'FAILED', 2],
    'required report missing' => [0, null, 0, 0, 'FAILED', 1],
    'cleanup failed' => [0, ['tests' => 1, 'failures' => 0, 'errors' => 0, 'skipped' => 0], 3, 0, 'FAILED', 3],
    'upload failed' => [0, ['tests' => 1, 'failures' => 0, 'errors' => 0, 'skipped' => 0], 0, 4, 'FAILED', 4],
    'warning or risky or incomplete exit retained' => [5, ['tests' => 1, 'failures' => 0, 'errors' => 0, 'skipped' => 0], 0, 0, 'FAILED', 5],
    'synthetic clean policy outcome' => [0, ['tests' => 1, 'failures' => 0, 'errors' => 0, 'skipped' => 0], 0, 0, 'PASSED', 0],
])->group('testing-foundation');

it('records preflight rejection as blocked and not a run with a passing result', function (): void {
    expect(is_callable([SafeTestReport::class, 'blocked']))->toBeTrue();
    expect(SafeTestReport::blocked(2))->toMatchArray(['status' => 'BLOCKED', 'exit_code' => 2, 'tests_executed' => false]);
})->group('testing-foundation');
