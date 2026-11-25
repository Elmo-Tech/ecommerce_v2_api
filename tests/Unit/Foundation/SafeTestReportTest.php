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


it('publishes only safe derived JUnit cases and omits raw stack traces, properties and test output', function (): void {
    $checkout = new FoundationTestCheckout;
    try {
        $xml = f03ReportFixture('<properties><property name="hidden" value="raw-property"/></properties><testcase name="case &amp; one" classname="Synthetic" assertions="2" time="0.15"><failure>raw-stack-trace</failure><system-out>raw-output</system-out></testcase>');
        $checkout->write('reports/result.xml', $xml);
        $result = SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], ['case & one']);
        $safe = SafeTestReport::safeXml($result, 'synthetic');
        expect($safe)->toContain('case &amp; one', 'Test did not pass', 'failures="1"');
        expect($safe)->not->toContain('raw-property', 'raw-stack-trace', 'raw-output', 'system-out', 'properties');
        $checkout->write('reports/safe.xml', $safe);
        $derived = SafeTestReport::inspect($checkout->root.'/reports/safe.xml', $checkout->root.'/reports', [], ['case & one']);
        expect($derived)->toMatchArray(['tests' => 1, 'assertions' => 2, 'failures' => 1]);
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');

it('rejects generated credential fingerprints even embedded in a longer hex value and never emits plaintext', function (bool $embedded): void {
    $checkout = new FoundationTestCheckout;
    try {
        $password = str_repeat('1a', 32);
        $value = $embedded ? 'ab'.$password.'cd' : $password;
        $checkout->write('reports/result.xml', f03ReportFixture('<testcase name="case"><failure>'.$value.'</failure></testcase>'));
        try {
            SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], ['case'], [hash('sha256', $password)]);
            expect(false)->toBeTrue();
        } catch (RuntimeException $error) {
            expect($error->getMessage())->not->toContain($password);
        }
    } finally {
        $checkout->close();
    }
})->with([false, true])->group('testing-foundation');

it('rejects inconsistent aggregate counts and contradictory per-case outcomes', function (string $xml): void {
    $checkout = new FoundationTestCheckout;
    try {
        $checkout->write('reports/result.xml', $xml);
        expect(fn () => SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], []))->toThrow(RuntimeException::class);
    } finally {
        $checkout->close();
    }
})->with([
    '<testsuites tests="5"><testsuite><testcase name="one"/></testsuite></testsuites>',
    '<testsuites><testsuite><testcase name="one"><failure/><skipped/></testcase></testsuite></testsuites>',
])->group('testing-foundation');


it('validates actual dotted PHPUnit classnames and PHP namespace names without confusing fast and integration suites', function (string $separator): void {
    $class = static fn (string $group, string $name): string => implode($separator, ['Tests', $group, $name]);
    $architecture = ['tests' => 9, 'cases' => array_fill(0, 9, ['class' => $class('Architecture', 'BoundaryTest')])];
    SafeTestReport::verifySelection('architecture', $architecture);
    expect(fn () => SafeTestReport::verifySelection('integration', $architecture))->toThrow(RuntimeException::class);
    $integration = ['tests' => 34, 'cases' => array_merge(
        array_fill(0, 26, ['class' => $class('Integration', 'PostgreSqlIsolationTest')]),
        array_fill(0, 8, ['class' => $class('Integration', 'RedisIsolationTest')]),
    )];
    SafeTestReport::verifySelection('integration', $integration);
    $mixed = ['tests' => 98, 'cases' => array_merge(array_fill(0, 97, ['class' => $class('Unit', 'FastTest')]), [$integration['cases'][0]])];
    expect(fn () => SafeTestReport::verifySelection('fast-all', $mixed))->toThrow(RuntimeException::class);
    expect(fn () => SafeTestReport::verifySelection('architecture', ['tests' => 5, 'cases' => array_fill(0, 5, ['class' => $class('Unit', 'WrongTest')])]))->toThrow(RuntimeException::class);
})->with(['.', '\\'])->group('testing-foundation');


it('rejects inconsistent nested JUnit aggregates and duplicate or misplaced case identities', function (string $xml): void {
    $checkout = new FoundationTestCheckout;
    try {
        $checkout->write('reports/result.xml', $xml);
        expect(fn () => SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], []))->toThrow(RuntimeException::class);
    } finally {
        $checkout->close();
    }
})->with([
    'nested test count' => '<testsuites tests="1"><testsuite tests="2"><testcase name="one"/></testsuite></testsuites>',
    'nested assertion count' => '<testsuites><testsuite assertions="1"><testsuite assertions="2"><testcase name="one" assertions="1"/></testsuite></testsuite></testsuites>',
    'nested failure count' => '<testsuites><testsuite failures="1"><testcase name="one"/></testsuite></testsuites>',
    'nested error count' => '<testsuites><testsuite errors="1"><testcase name="one"/></testsuite></testsuites>',
    'nested skipped count' => '<testsuites><testsuite skipped="1"><testcase name="one"/></testsuite></testsuites>',
    'empty nested suite with claimed cases' => '<testsuites><testsuite tests="1"><testcase name="one"/></testsuite><testsuite tests="1"/></testsuites>',
    'duplicate case across suites' => '<testsuites><testsuite><testcase name="same" classname="Tests.Unit.Probe"/></testsuite><testsuite><testcase name="same" classname="Tests.Unit.Probe"/></testsuite></testsuites>',
    'misplaced nested suite' => '<testsuites><properties><testsuite><testcase name="one"/></testsuite></properties></testsuites>',
])->group('testing-foundation');

it('accepts consistent nested JUnit counters with distinct class identities and genuine empty subsuites', function (): void {
    $checkout = new FoundationTestCheckout;
    try {
        $xml = '<testsuites tests="2" assertions="3" failures="0" errors="0" skipped="0"><testsuite tests="2" assertions="3"><testsuite tests="1" assertions="1"><testcase name="same" classname="Tests.Unit.One" assertions="1"/></testsuite><testsuite tests="1" assertions="2"><testcase name="same" classname="Tests.Unit.Two" assertions="2"/></testsuite><testsuite tests="0" assertions="0" failures="0" errors="0" skipped="0"/></testsuite></testsuites>';
        $checkout->write('reports/result.xml', $xml);
        $report = SafeTestReport::inspect($checkout->root.'/reports/result.xml', $checkout->root.'/reports', [], []);
        expect($report)->toMatchArray(['tests' => 2, 'assertions' => 3, 'failures' => 0, 'errors' => 0, 'skipped' => 0]);
        expect(SafeTestReport::outcome(0, $report, 0, 0)['status'])->toBe('PASSED');
    } finally {
        $checkout->close();
    }
})->group('testing-foundation');
