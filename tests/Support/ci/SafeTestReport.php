<?php

declare(strict_types=1);

namespace Tests\Support\ci;

use DOMDocument;
use DOMElement;
use RuntimeException;
use SensitiveParameter;

/** Independently inspect real JUnit and publish only a safe derived copy. */
final class SafeTestReport
{
    private const LIMIT = 16777216;
    private const KEYS = ['tests', 'assertions', 'failures', 'errors', 'skipped'];

    /**
     * @param array<array-key, mixed> $secrets
     * @param list<string> $required
     * @param array<array-key, mixed> $hashes
     * @return array{tests: int, assertions: int, failures: int, errors: int, skipped: int, duration: float, cases: list<array{name: string, class: string, assertions: int, time: float, status: string}>}
     */
    public static function inspect(string $path, string $directory, #[SensitiveParameter] array $secrets, array $required, #[SensitiveParameter] array $hashes = []): array
    {
        if (!is_dir($directory) || is_link($directory) || realpath($directory) !== $directory
            || dirname($path) !== $directory || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,100}\.xml$/D', basename($path))
            || !is_file($path) || is_link($path) || realpath($path) !== $path) {
            self::reject();
        }
        $stat = lstat($path);
        if ($stat === false || $stat['nlink'] !== 1 || $stat['size'] <= 0 || $stat['size'] > self::LIMIT) {
            self::reject();
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            self::reject();
        }
        try {
            $opened = fstat($handle);
            if ($opened === false || $opened['ino'] !== $stat['ino'] || $opened['dev'] !== $stat['dev'] || $opened['size'] !== $stat['size']) {
                self::reject();
            }
            $raw = stream_get_contents($handle, self::LIMIT + 1);
        } finally {
            fclose($handle);
        }
        if (!is_string($raw) || strlen($raw) !== $stat['size'] || str_contains($raw, "\0")
            || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $raw)) {
            self::reject();
        }
        self::scan($raw, $secrets, $hashes);
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $valid = $doc->loadXML($raw, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $doc->documentElement;
        if (!$valid || $doc->doctype !== null || !$root instanceof DOMElement
            || !in_array($root->tagName, ['testsuites', 'testsuite'], true) || $root->namespaceURI !== null) {
            self::reject();
        }
        // Decode actual XML text and attributes as well as the raw bytes.
        // This catches escaped/entity-encoded credential canaries.
        self::scan($root->textContent, $secrets, $hashes);
        foreach ($doc->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                self::scan($attribute->value, $secrets, $hashes);
            }
        }
        // Native JUnit uses nested suites; the outer testsuites element
        // need not declare counters. Validate every actual suite, including
        // empty ones, rather than accepting inconsistent inner metadata.
        $suites = [$root];
        foreach ($doc->getElementsByTagName('testsuite') as $suite) {
            if ($suite->isSameNode($root)) {
                continue;
            }
            $parent = $suite->parentNode;
            if ($suite->namespaceURI !== null || !$parent instanceof DOMElement
                || ($parent->tagName !== 'testsuite' && !$parent->isSameNode($root))) {
                self::reject();
            }
            $suites[] = $suite;
        }
        $suiteCounts = [];
        foreach ($suites as $suite) {
            $suiteCounts[$suite->getNodePath()] = array_fill_keys(self::KEYS, 0);
        }
        $result = ['tests' => 0, 'assertions' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0, 'duration' => 0.0, 'cases' => []];
        $names = [];
        $identities = [];
        foreach ($doc->getElementsByTagName('testcase') as $case) {
            if (!$case->parentNode instanceof DOMElement || $case->parentNode->tagName !== 'testsuite' || $case->namespaceURI !== null) {
                self::reject();
            }
            $name = $case->getAttribute('name');
            $class = $case->getAttribute('classname') ?: $case->getAttribute('class');
            if ($name === '' || strlen($name) > 2048 || strlen($class) > 512
                || preg_match('/[\x00-\x1f\x7f]/', $name.$class)) {
                self::reject();
            }
            // Dataset names are part of the identity. Equal names in
            // distinct classes are valid; repeated identities are not.
            $identity = str_replace('.', '\\', $class)."\0".$name;
            if (isset($identities[$identity])) {
                self::reject();
            }
            $identities[$identity] = true;
            $assertions = self::integer($case->getAttribute('assertions') ?: '0');
            $duration = $case->getAttribute('time') ?: '0';
            if (!is_numeric($duration) || !is_finite((float) $duration) || (float) $duration < 0 || (float) $duration > 86400) {
                self::reject();
            }
            $status = 'passed';
            foreach ($case->childNodes as $child) {
                if (!$child instanceof DOMElement) {
                    continue;
                }
                if (in_array($child->tagName, ['failure', 'error', 'skipped'], true)) {
                    if ($status !== 'passed') {
                        self::reject();
                    }
                    $status = $child->tagName;
                }
            }
            $counts = [
                'tests' => 1,
                'assertions' => $assertions,
                'failures' => (int) ($status === 'failure'),
                'errors' => (int) ($status === 'error'),
                'skipped' => (int) ($status === 'skipped'),
            ];
            foreach ($counts as $key => $count) {
                $result[$key] += $count;
            }
            // Each case contributes once to each enclosing suite. Reject a
            // testcase hidden under a non-suite branch of the document.
            for ($suite = $case->parentNode; $suite instanceof DOMElement; $suite = $suite->parentNode) {
                $path = $suite->getNodePath();
                if (!isset($suiteCounts[$path])) {
                    self::reject();
                }
                foreach ($counts as $key => $count) {
                    $suiteCounts[$path][$key] += $count;
                }
            }
            $result['duration'] += (float) $duration;
            $result['cases'][] = ['name' => $name, 'class' => $class, 'assertions' => $assertions, 'time' => (float) $duration, 'status' => $status];
            $names[] = $name;
            if ($result['tests'] > 100000) {
                self::reject();
            }
        }
        if ($result['tests'] === 0 || array_diff($required, $names) !== []) {
            self::reject();
        }
        foreach ($suites as $suite) {
            $counts = $suiteCounts[$suite->getNodePath()];
            foreach (self::KEYS as $key) {
                if ($suite->hasAttribute($key) && self::integer($suite->getAttribute($key)) !== $counts[$key]) {
                    self::reject();
                }
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $report
     */
    public static function verifySelection(string $selection, array $report): void
    {
        $minimum = match ($selection) {
            'foundation' => 31, 'fast-all' => 98, 'architecture' => 5, 'integration' => 34,
            default => throw new RuntimeException('Unknown test report selection.'),
        };
        if (($report['tests'] ?? 0) < $minimum || !is_array($report['cases'] ?? null)) {
            self::reject();
        }
        $postgres = $redis = 0;
        foreach ($report['cases'] as $case) {
            // Native PHPUnit JUnit classname uses dotted namespace names
            // (observed in the actual CI artifact), while class may use PHP
            // namespace separators. Normalize only for selection validation.
            $class = str_replace('.', '\\', $case['class']);
            $integration = str_contains($class, '\\Integration\\');
            if ($selection === 'integration') {
                if (!$integration) {
                    self::reject();
                }
                if (str_ends_with($class, '\\PostgreSqlIsolationTest')) {
                    $postgres++;
                } elseif (str_ends_with($class, '\\RedisIsolationTest')) {
                    $redis++;
                }
            } elseif ($integration || ($selection === 'architecture' && !str_contains($class, '\\Architecture\\'))) {
                self::reject();
            }
        }
        if ($selection === 'integration' && ($postgres < 26 || $redis < 8)) {
            self::reject();
        }
    }

    /**
     * No stack traces, properties, system output, paths or raw failures.
     *
     * @param array<array-key, mixed> $report
     * @param array<array-key, mixed> $secrets
     * @param array<array-key, mixed> $hashes
     */
    public static function safeXml(array $report, string $selection, #[SensitiveParameter] array $secrets = [], #[SensitiveParameter] array $hashes = []): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->appendChild($doc->createElement('testsuites'));
        $suite = $root->appendChild($doc->createElement('testsuite'));
        $suite->setAttribute('name', $selection);
        foreach (self::KEYS as $key) {
            $suite->setAttribute($key, (string) $report[$key]);
        }
        $suite->setAttribute('time', sprintf('%.6F', $report['duration']));
        foreach ($report['cases'] as $case) {
            $node = $suite->appendChild($doc->createElement('testcase'));
            foreach (['name' => $case['name'], 'classname' => $case['class'], 'assertions' => (string) $case['assertions'], 'time' => sprintf('%.6F', $case['time'])] as $key => $value) {
                self::scan($value, $secrets, $hashes);
                $node->setAttribute($key, $value);
            }
            if ($case['status'] !== 'passed') {
                $detail = $node->appendChild($doc->createElement($case['status']));
                if ($case['status'] !== 'skipped') {
                    $detail->setAttribute('message', 'Test did not pass; raw diagnostic withheld.');
                }
            }
        }
        $xml = $doc->saveXML();
        if (!is_string($xml)) {
            self::reject();
        }
        self::scan($xml, $secrets, $hashes);
        return $xml;
    }

    /**
     * @param array<array-key, mixed>|null $report
     * @return array{status: 'PASSED'|'FAILED', exit_code: int}
     */
    public static function outcome(int $runner, ?array $report, int $cleanup, int $upload): array
    {
        $exit = $runner !== 0 ? $runner : ($cleanup !== 0 ? $cleanup : ($upload !== 0 ? $upload : 0));
        $valid = $report !== null && isset($report['tests'], $report['failures'], $report['errors'], $report['skipped'])
            && is_int($report['tests']) && $report['tests'] > 0;
        if ($valid) {
            foreach (['failures', 'errors', 'skipped'] as $key) {
                $valid = $valid && $report[$key] === 0;
            }
        }
        if ($exit === 0 && !$valid) {
            $exit = 1;
        }
        return ['status' => $exit === 0 ? 'PASSED' : 'FAILED', 'exit_code' => $exit > 0 ? $exit : ($exit === 0 ? 0 : 1)];
    }

    /**
     * @return array{status: 'BLOCKED', exit_code: int, tests_executed: false}
     */
    public static function blocked(int $status): array
    {
        return ['status' => 'BLOCKED', 'exit_code' => $status > 0 ? $status : 1, 'tests_executed' => false];
    }

    /**
     * Scan plaintext values and protected SHA256 fingerprints of hex secrets.
     *
     * @param array<array-key, mixed> $secrets
     * @param array<array-key, mixed> $hashes
     */
    private static function scan(#[SensitiveParameter] string $text, #[SensitiveParameter] array $secrets, #[SensitiveParameter] array $hashes): void
    {
        foreach ($secrets as $secret) {
            if (!is_string($secret) || $secret === '' || str_contains($text, $secret)) {
                self::reject();
            }
        }
        if ($hashes === []) {
            return;
        }
        foreach ($hashes as $hash) {
            if (!is_string($hash) || !preg_match('/^[0-9a-f]{64}$/D', $hash)) {
                self::reject();
            }
        }
        // Generated passwords are exactly 64 lowercase hex characters.
        // Scan every 64-character window in hex runs, including embedded
        // values; reject excessively long hex runs rather than skip them.
        if (preg_match_all('/[0-9a-fA-F]{64,}/', $text, $runs) === false) {
            self::reject();
        }
        foreach ($runs[0] as $run) {
            if (strlen($run) > 4096) {
                self::reject();
            }
            $run = strtolower($run);
            for ($offset = 0; $offset <= strlen($run) - 64; $offset++) {
                if (in_array(hash('sha256', substr($run, $offset, 64)), $hashes, true)) {
                    self::reject();
                }
            }
        }
    }

    private static function integer(string $value): int
    {
        if (!preg_match('/^[0-9]{1,10}$/D', $value) || (int) $value > 1000000000) {
            self::reject();
        }
        return (int) $value;
    }

    private static function reject(): never
    {
        throw new RuntimeException('Required test report rejected or incomplete.');
    }
}
