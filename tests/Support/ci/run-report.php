<?php

declare(strict_types=1);

use Tests\Support\IntegrationContext;
use Tests\Support\TestEnvironmentGuard;
use Tests\Support\ci\SafeTestReport;

$root = dirname(__DIR__, 3);
require_once dirname(__DIR__).'/TestEnvironmentGuard.php';
require_once dirname(__DIR__).'/IntegrationContext.php';
require_once __DIR__.'/SafeTestReport.php';

// Fixed selections only. Raw child output stays private and is not uploaded.
$selection = $argv[1] ?? '';
$blocked = ($argv[2] ?? '') === '--blocked' && $argc === 3;
$directory = null;
$runner = 2;
$report = null;
$observedReport = null;
$rawOutput = [];
$started = false;
$start = hrtime(true);
try {
    $options = match ($selection) {
        'foundation' => ['fast', '--group=foundation'],
        'fast-all' => ['fast'],
        'architecture' => ['fast', '--suite=Architecture'],
        'integration' => ['integration'],
        default => throw new RuntimeException('Unknown selection.'),
    };
    if ((!$blocked && $argc !== 2) || getenv('GITHUB_ACTIONS') !== 'true'
        || getenv('RUNNER_ENVIRONMENT') !== 'github-hosted'
        || getenv('GITHUB_REPOSITORY') !== 'Elmo-Tech/ecommerce_v2_api') {
        throw new RuntimeException('Unowned report execution.');
    }
    $directory = TestEnvironmentGuard::reportDirectory(getenv(), $_ENV, $_SERVER);
    if ($directory === null) {
        throw new RuntimeException('Owned report directory missing.');
    }
    $hashes = [];
    if (!$blocked) {
        if ($selection === 'integration') {
            $context = IntegrationContext::environment($root, getenv(), $_ENV, $_SERVER);
            $data = IntegrationContext::read($context['directory'], 'credentials-hashes.json');
            if (($data['token'] ?? null) !== $context['token'] || !is_array($data['hashes'] ?? null)
                || count($data['hashes']) !== 7 || count(array_unique($data['hashes'])) !== 7) {
                throw new RuntimeException('Protected credential fingerprints missing.');
            }
            $hashes = $data['hashes'];
        } else {
            TestEnvironmentGuard::applyFast($root);
        }
        $rawName = $selection.'-raw.xml';
        $rawPath = TestEnvironmentGuard::reportPath($rawName);
        $previous = umask(0077);
        try {
            foreach (['stdout', 'stderr'] as $stream) {
                $path = $directory.'/'.$selection.'.'.$stream;
                if (file_exists($path) || is_link($path)) {
                    throw new RuntimeException('Unexpected report output path.');
                }
                $handle = fopen($path, 'xb');
                if ($handle === false) {
                    throw new RuntimeException('Cannot capture private test output.');
                }
                $rawOutput[] = ['path' => $path, 'handle' => $handle];
            }
            $process = proc_open(
                [PHP_BINARY, '-d', 'zend.exception_ignore_args=1', $root.'/tests/Support/run-tests.php', ...$options, '--report='.$rawName],
                [0 => ['file', '/dev/null', 'r'], 1 => $rawOutput[0]['handle'], 2 => $rawOutput[1]['handle']], $pipes, $root,
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start test selection.');
            }
            $started = true;
            $runner = proc_close($process);
        } finally {
            umask($previous);
            foreach ($rawOutput as $output) {
                fclose($output['handle']);
                if (is_file($output['path']) && !is_link($output['path'])) {
                    if (!unlink($output['path'])) {
                        throw new RuntimeException('Private test output cleanup incomplete.');
                    }
                }
            }
        }
        $report = SafeTestReport::inspect($rawPath, $directory, [], [], $hashes);
        $observedReport = $report;
        // A failed partial selection is still useful but cannot satisfy
        // required coverage. Publish the safe actual result if valid.
        $safe = SafeTestReport::safeXml($report, $selection, [], $hashes);
        $safePath = TestEnvironmentGuard::reportPath($selection.'-safe.xml');
        $stagedPath = TestEnvironmentGuard::reportPath($selection.'-safe-next.xml');
        $previous = umask(0077);
        try {
            $file = fopen($stagedPath, 'xb');
            if ($file === false) {
                throw new RuntimeException('Cannot publish safe report.');
            }
            try {
                if (fwrite($file, $safe) !== strlen($safe) || !fflush($file)) {
                    throw new RuntimeException('Cannot publish safe report.');
                }
            } finally {
                fclose($file);
            }
        } finally {
            umask($previous);
        }
        if (is_link($stagedPath) || file_exists($safePath) || is_link($safePath) || !rename($stagedPath, $safePath)) {
            throw new RuntimeException('Cannot publish safe report.');
        }
        SafeTestReport::verifySelection($selection, $report);
    }
} catch (Throwable) {
    // Do not print raw exception or child output. Preserve runner exit if
    // it actually failed; no report/selection validation means no pass.
    $report = null;
}

$outcome = !$started
    ? SafeTestReport::blocked(2)
    : SafeTestReport::outcome($runner, $report, 0, 0);
try {
    if ($directory === null) {
        throw new RuntimeException('Owned report directory missing.');
    }
    $checkout = getenv('GITHUB_SHA');
    $run = getenv('GITHUB_RUN_ID');
    $attempt = getenv('GITHUB_RUN_ATTEMPT');
    if (!is_string($checkout) || !preg_match('/^[0-9a-f]{40}$/D', $checkout)
        || !is_string($run) || !preg_match('/^[1-9][0-9]{0,19}$/D', $run)
        || !is_string($attempt) || !preg_match('/^[1-9][0-9]{0,8}$/D', $attempt)) {
        throw new RuntimeException('Missing report revision.');
    }
    // Fixed commands only; actual checkout and merge parents, not stale PR
    // metadata. A pull_request checkout must have exactly two parents.
    $lines = [];
    exec('/usr/bin/git rev-parse HEAD', $lines, $status);
    if ($status !== 0 || $lines !== [$checkout]) {
        throw new RuntimeException('Unexpected report checkout.');
    }
    // Read raw commit parent headers: a depth-1 checkout may hide parents
    // from git's history formatting even though the commit contains them.
    $commit = [];
    exec('/usr/bin/git cat-file -p HEAD', $commit, $status);
    $parents = [];
    foreach ($commit as $line) {
        if ($line === '') {
            break;
        }
        if (preg_match('/^parent ([0-9a-f]{40})$/D', $line, $match)) {
            $parents[] = $match[1];
        }
    }
    $base = null;
    $source = $checkout;
    if (getenv('GITHUB_EVENT_NAME') === 'pull_request') {
        if ($status !== 0 || count($parents) !== 2) {
            throw new RuntimeException('Unexpected integrated candidate.');
        }
        [$base, $source] = $parents;
    }
    $counts = $observedReport === null ? null : array_intersect_key($observedReport, array_flip(['tests', 'assertions', 'failures', 'errors', 'skipped', 'duration']));
    $summary = [
        'selection' => $selection, 'status' => $outcome['status'], 'exit_code' => $outcome['exit_code'],
        'runner_started' => $started, 'tests_executed' => $started ? ($observedReport === null ? null : true) : false,
        'selection_verified' => $report !== null,
        'runner_exit' => $started ? $runner : null,
        'source_sha' => $source, 'base_sha' => $base, 'checkout_sha' => $checkout,
        'run_id' => $run, 'run_attempt' => $attempt, 'php' => PHP_VERSION,
        'duration_seconds' => round((hrtime(true) - $start) / 1e9, 3), 'counts' => $counts,
    ];
    $path = $directory.'/'.$selection.'.summary.json';
    $staged = $directory.'/'.$selection.'.summary.next.json';
    if (file_exists($path) || is_link($path) || file_exists($staged) || is_link($staged)) {
        throw new RuntimeException('Unexpected report summary destination.');
    }
    $previous = umask(0077);
    try {
        $file = fopen($staged, 'xb');
        if ($file === false) {
            throw new RuntimeException('Cannot publish safe summary.');
        }
        try {
            $json = json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
            if (fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new RuntimeException('Cannot publish safe summary.');
            }
        } finally {
            fclose($file);
        }
    } finally {
        umask($previous);
    }
    if (is_link($staged) || file_exists($path) || is_link($path) || !rename($staged, $path)) {
        throw new RuntimeException('Cannot publish safe summary.');
    }
    echo $json;
} catch (Throwable) {
    fwrite(STDERR, "Required safe test report or summary incomplete.\n");
    exit($outcome['exit_code'] !== 0 ? $outcome['exit_code'] : 2);
}
exit($outcome['exit_code']);
