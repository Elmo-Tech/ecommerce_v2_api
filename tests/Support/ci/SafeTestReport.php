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
        $result = ['tests' => 0, 'assertions' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0, 'duration' => 0.0, 'cases' => []];
        $names = [];
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
            $result['tests']++;
            $result['assertions'] += $assertions;
            $result['duration'] += (float) $duration;
            if ($status !== 'passed') {
                $result[$status === 'failure' ? 'failures' : ($status === 'error' ? 'errors' : 'skipped')]++;
            }
            $result['cases'][] = ['name' => $name, 'class' => $class, 'assertions' => $assertions, 'time' => (float) $duration, 'status' => $status];
            $names[] = $name;
            if ($result['tests'] > 100000) {
                self::reject();
            }
        }
        if ($result['tests'] === 0 || array_diff($required, $names) !== []) {
            self::reject();
        }
        foreach (self::KEYS as $key) {
            if ($root->hasAttribute($key) && self::integer($root->getAttribute($key)) !== $result[$key]) {
                self::reject();
            }
        }

        return $result;
    }

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
            $class = $case['class'];
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

    /** No stack traces, properties, system output, paths or raw failures. */
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

    public static function blocked(int $status): array
    {
        return ['status' => 'BLOCKED', 'exit_code' => $status > 0 ? $status : 1, 'tests_executed' => false];
    }

    /** Scan plaintext values and protected SHA256 fingerprints of hex secrets. */
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
