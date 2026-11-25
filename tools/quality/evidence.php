<?php

declare(strict_types=1);

namespace Tools\Quality;

use JsonException;
use RuntimeException;
use SensitiveParameter;
use stdClass;

/**
 * Bounded F0-04 lane summary. The expected identity and file names come from
 * independently verified selection, not from the report being inspected.
 * A valid summary alone does not prove the job ran or its artifacts match.
 */
final class LaneEvidence
{
    private const LIMIT = 1048576;

    private const IDENTITY = [
        'schemaVersion', 'repository', 'event', 'pr', 'sourceSha', 'baseSha',
        'checkoutSha', 'runId', 'runAttempt', 'job', 'required', 'selectionReason',
    ];

    private const RESULT = [
        'status', 'runnerStarted', 'exitCode', 'versions', 'durationSeconds',
        'selectedCount', 'validatedCount', 'artifacts', 'findingCounts',
    ];

    private const LANES = [
        'docs', 'format', 'analysis', 'contracts', 'dependency-audit',
        'image-scan', 'bootstrap', 'native-integration', 'spec-kit',
    ];

    private const REASONS = [
        'docs-only', 'contract-only', 'php-change', 'shared-core',
        'ci-dependency-security', 'spec-kit', 'unknown-impact', 'diagnostic',
    ];

    private const TOOLS = [
        'php', 'composer', 'pint', 'phpstan', 'larastan', 'node', 'npm',
        'redocly', 'trivy', 'postgresql', 'redis', 'python', 'pyyaml',
        'pest', 'phpunit', 'pdo_pgsql', 'phpredis', 'git',
    ];

    private const FINDINGS = [
        'errors', 'warnings', 'violations', 'advisories', 'critical',
        'high', 'medium', 'low', 'unknown', 'info',
    ];

    /**
     * @param array<string, mixed> $identity
     */
    public static function assertIdentity(array $identity): void
    {
        self::sameKeys($identity, self::IDENTITY);
        if ($identity['schemaVersion'] !== 1
            || $identity['repository'] !== 'Elmo-Tech/ecommerce_v2_api'
            || !in_array($identity['event'], ['pull_request', 'push', 'workflow_dispatch'], true)
            || ($identity['event'] === 'pull_request'
                ? !self::positiveInteger($identity['pr'])
                : $identity['pr'] !== null)
            || !is_string($identity['runId'])
            || preg_match('/^[1-9][0-9]{0,19}$/D', $identity['runId']) !== 1
            || !self::positiveInteger($identity['runAttempt'])
            || !in_array($identity['job'], self::LANES, true)
            || !is_bool($identity['required'])
            || !in_array($identity['selectionReason'], self::REASONS, true)) {
            self::reject();
        }
        foreach (['sourceSha', 'baseSha', 'checkoutSha'] as $field) {
            if (!is_string($identity[$field]) || preg_match('/^[a-f0-9]{40}$/D', $identity[$field]) !== 1) {
                self::reject();
            }
        }
    }

    /**
     * @param array<string, mixed> $expectedIdentity
     * @param list<string> $artifactNames exact names for a passing lane; failed lanes may have a safe subset
     * @param list<string> $canaries test/known credential fingerprints, never written to reports
     * @return array<string, mixed>
     */
    public static function validate(
        #[SensitiveParameter] string $raw,
        array $expectedIdentity,
        array $artifactNames,
        #[SensitiveParameter] array $canaries = [],
    ): array {
        self::assertIdentity($expectedIdentity);
        self::assertNames($artifactNames);
        if ($raw === '' || strlen($raw) > self::LIMIT || !array_is_list($canaries) || count($canaries) > 64) {
            self::reject();
        }
        foreach ($canaries as $canary) {
            if (!is_string($canary) || $canary === '' || strlen($canary) > 1024 || str_contains($raw, $canary)) {
                self::reject();
            }
        }
        try {
            $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::reject();
        }
        if (!$decoded instanceof stdClass || self::propertyCount($decoded) !== self::unquotedColons($raw)) {
            self::reject();
        }
        // json_decode silently overwrites duplicate keys. For valid JSON, each
        // object member contributes exactly one colon outside strings.
        // Comparing this lexical count with the decoded tree detects duplicate
        // keys even when equivalent keys use different JSON escapes.
        self::scanDecoded($decoded, $canaries);
        $fields = get_object_vars($decoded);
        self::sameKeys($fields, [...self::IDENTITY, ...self::RESULT]);
        foreach (self::IDENTITY as $key) {
            if ($fields[$key] !== $expectedIdentity[$key]) {
                self::reject();
            }
        }

        $status = $fields['status'];
        $started = $fields['runnerStarted'];
        $exit = $fields['exitCode'];
        $duration = $fields['durationSeconds'];
        $selected = $fields['selectedCount'];
        $validated = $fields['validatedCount'];
        if (!in_array($status, ['PASSED', 'FAILED', 'BLOCKED', 'NOT_APPLICABLE'], true)
            || !is_bool($started)
            || ($exit !== null && (!is_int($exit) || $exit < 0 || $exit > 255))
            || (!is_int($duration) && !is_float($duration))
            || !is_finite((float) $duration) || $duration < 0 || $duration > 3600
            || (($selected === null) !== ($validated === null))
            || ($selected !== null && (!self::nonnegativeCount($selected)
                || !self::nonnegativeCount($validated) || $validated > $selected))
            || !$fields['versions'] instanceof stdClass
            || !$fields['artifacts'] instanceof stdClass
            || !$fields['findingCounts'] instanceof stdClass) {
            self::reject();
        }

        $versions = get_object_vars($fields['versions']);
        $artifacts = get_object_vars($fields['artifacts']);
        $findings = get_object_vars($fields['findingCounts']);
        foreach ($versions as $name => $version) {
            if (!in_array($name, self::TOOLS, true) || !is_string($version)
                || preg_match('/^v?[0-9]{1,8}(?:\.[0-9]{1,8}){1,3}(?:[-+][A-Za-z0-9.-]{1,32})?$/D', $version) !== 1) {
                self::reject();
            }
        }
        foreach ($artifacts as $name => $digest) {
            if (!in_array($name, $artifactNames, true) || !is_string($digest)
                || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
                self::reject();
            }
        }
        foreach ($findings as $name => $count) {
            if (!in_array($name, self::FINDINGS, true) || !self::nonnegativeCount($count)) {
                self::reject();
            }
        }

        if ($status === 'NOT_APPLICABLE') {
            if ($fields['required'] !== false || $started || $exit !== null
                || (float) $duration !== 0.0 || $selected !== null
                || $versions !== [] || $artifacts !== [] || $findings !== []) {
                self::reject();
            }
        } elseif ($fields['required'] !== true) {
            self::reject();
        } elseif ($status === 'PASSED') {
            if (!$started || $exit !== 0 || $versions === []
                || ($selected !== null && ($selected === 0 || $selected !== $validated))
                || ($findings['errors'] ?? 0) !== 0 || ($findings['violations'] ?? 0) !== 0) {
                self::reject();
            }
            self::sameKeys($artifacts, $artifactNames);
        } elseif ($status === 'FAILED') {
            if (!$started || $exit === null || $exit === 0) {
                self::reject();
            }
        } elseif ($exit === 0 || $selected !== null || $artifacts !== [] || $findings !== []) {
            // A blocked prerequisite may have a preflight exit/duration and
            // observed versions, but no fabricated selected/validated result.
            self::reject();
        }

        // Return the validated decoded value. No raw child output is accepted.
        /** @var array<string, mixed> $report */
        $report = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);

        return $report;
    }

    /**
     * @param list<string> $names
     */
    public static function assertNames(array $names): void
    {
        if (!array_is_list($names) || count($names) > 32) {
            self::reject();
        }
        foreach ($names as $name) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,100}$/D', $name) !== 1) {
                self::reject();
            }
        }
        if (count(array_unique($names)) !== count($names)) {
            self::reject();
        }
    }

    /**
     * @param array<array-key, mixed> $actual
     * @param list<string> $expected
     */
    private static function sameKeys(array $actual, array $expected): void
    {
        $keys = array_keys($actual);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            self::reject();
        }
    }

    private static function positiveInteger(mixed $value): bool
    {
        return is_int($value) && $value > 0 && $value <= 1000000000;
    }

    private static function nonnegativeCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 1000000000;
    }

    private static function propertyCount(mixed $value): int
    {
        if ($value instanceof stdClass) {
            $members = get_object_vars($value);
            $count = count($members);
        } elseif (is_array($value)) {
            $members = $value;
            $count = 0;
        } else {
            return 0;
        }
        foreach ($members as $member) {
            $count += self::propertyCount($member);
        }

        return $count;
    }

    private static function unquotedColons(#[SensitiveParameter] string $raw): int
    {
        $quoted = false;
        $escaped = false;
        $count = 0;
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($quoted) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $quoted = false;
                }
            } elseif ($char === '"') {
                $quoted = true;
            } elseif ($char === ':') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $canaries
     */
    private static function scanDecoded(#[SensitiveParameter] mixed $value, #[SensitiveParameter] array $canaries): void
    {
        if (is_string($value)) {
            foreach ($canaries as $canary) {
                if (str_contains($value, $canary)) {
                    self::reject();
                }
            }
        } elseif ($value instanceof stdClass || is_array($value)) {
            foreach ($value as $key => $member) {
                if (is_string($key)) {
                    self::scanDecoded($key, $canaries);
                }
                self::scanDecoded($member, $canaries);
            }
        }
    }

    private static function reject(): never
    {
        throw new RuntimeException('Quality evidence contract rejected.');
    }
}

/**
 * Sequential, private file-only scratch ownership. Does not clean arbitrary
 * cache trees or follow symlinks. This is an accidental-target barrier, not a
 * sandbox against another process with the same uid.
 *
 * @phpstan-type Node array{dev: int, ino: int, mode: int, uid: int, size: int, nlink: int}
 */
final class OwnedEvidenceDirectory
{
    private const MARKER = '.owner';

    private const FILE_LIMIT = 16777216;

    /** @var array<string, Node> */
    private array $written = [];

    private bool $closed = false;

    /**
     * @param list<string> $allowed
     * @param Node $parentNode
     * @param Node $directoryNode
     * @param Node $markerNode
     */
    private function __construct(
        private readonly string $parent,
        private readonly string $directory,
        private readonly array $allowed,
        private readonly array $parentNode,
        private readonly array $directoryNode,
        private readonly array $markerNode,
        private readonly string $marker,
    ) {
    }

    /**
     * @param array<string, mixed> $identity
     * @param list<string> $allowed
     */
    public static function create(string $parent, string $token, array $identity, array $allowed): self
    {
        LaneEvidence::assertIdentity($identity);
        LaneEvidence::assertNames($allowed);
        if (preg_match('/^[a-f0-9]{32}$/D', $token) !== 1 || $parent === '/') {
            self::reject();
        }
        $parentNode = self::node($parent, true, false);
        $directory = $parent.'/f04-quality-'.$token;
        clearstatcache(true, $directory);
        // Exclusive mkdir also rejects a dangling symlink or racing creation.
        $umask = umask(0077);
        try {
            if (!@mkdir($directory, 0700)) {
                self::reject();
            }
        } finally {
            umask($umask);
        }
        $directoryNode = self::node($directory, true, true);
        self::sameNode($parentNode, self::node($parent, true, false));
        $marker = json_encode(
            ['schemaVersion' => 1, 'token' => $token, 'identity' => $identity, 'allowed' => $allowed],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $markerNode = self::createFile($directory.'/'.self::MARKER, $marker);

        return new self($parent, $directory, $allowed, $parentNode, $directoryNode, $markerNode, $marker);
    }

    public function path(): string
    {
        return $this->directory;
    }

    public function write(string $name, #[SensitiveParameter] string $contents): void
    {
        $this->verifyOwnership();
        if (!in_array($name, $this->allowed, true) || isset($this->written[$name])) {
            self::reject();
        }
        $this->written[$name] = self::createFile($this->directory.'/'.$name, $contents);
    }

    public function cleanup(): void
    {
        if ($this->closed) {
            clearstatcache(true, $this->directory);
            if (@lstat($this->directory) !== false) {
                self::reject();
            }

            return;
        }
        $this->verifyOwnership();
        $entries = @scandir($this->directory);
        if ($entries === false) {
            self::reject();
        }
        $entries = array_values(array_diff($entries, ['.', '..']));
        $expected = [self::MARKER, ...array_keys($this->written)];
        sort($entries, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($entries !== $expected) {
            self::reject();
        }
        // Validate the entire inventory before deleting even one entry.
        foreach ($this->written as $name => $record) {
            self::sameFile($record, self::node($this->directory.'/'.$name, false, true));
        }
        foreach ($this->written as $name => $record) {
            $this->verifyOwnership();
            $path = $this->directory.'/'.$name;
            self::sameFile($record, self::node($path, false, true));
            if (!@unlink($path)) {
                self::reject();
            }
        }
        $this->verifyOwnership();
        if (!@unlink($this->directory.'/'.self::MARKER)) {
            self::reject();
        }
        self::sameNode($this->directoryNode, self::node($this->directory, true, true));
        if (!@rmdir($this->directory)) {
            self::reject();
        }
        clearstatcache(true, $this->directory);
        if (@lstat($this->directory) !== false) {
            self::reject();
        }
        $this->closed = true;
    }

    private function verifyOwnership(): void
    {
        if ($this->closed) {
            self::reject();
        }
        self::sameNode($this->parentNode, self::node($this->parent, true, false));
        self::sameNode($this->directoryNode, self::node($this->directory, true, true));
        $path = $this->directory.'/'.self::MARKER;
        self::sameFile($this->markerNode, self::node($path, false, true));
        $observed = @file_get_contents($path, false, null, 0, strlen($this->marker) + 1);
        if ($observed !== $this->marker) {
            self::reject();
        }
    }

    /**
     * @return Node
     */
    private static function createFile(string $path, #[SensitiveParameter] string $contents): array
    {
        $length = strlen($contents);
        if ($length === 0 || $length > self::FILE_LIMIT) {
            self::reject();
        }
        $umask = umask(0077);
        try {
            $handle = @fopen($path, 'xb');
        } finally {
            umask($umask);
        }
        if ($handle === false) {
            self::reject();
        }
        try {
            $offset = 0;
            while ($offset < $length) {
                $written = @fwrite($handle, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    self::reject();
                }
                $offset += $written;
            }
            if (!@fflush($handle)) {
                self::reject();
            }
            $opened = fstat($handle);
            if ($opened === false) {
                self::reject();
            }
            $observed = self::node($path, false, true);
            if ($opened['dev'] !== $observed['dev'] || $opened['ino'] !== $observed['ino']
                || $observed['size'] !== $length) {
                self::reject();
            }
        } finally {
            if (!@fclose($handle)) {
                self::reject();
            }
        }

        return $observed;
    }

    /**
     * @return Node
     */
    private static function node(string $path, bool $directory, bool $private): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || realpath($path) !== $path
            || ($stat['mode'] & 0170000) !== ($directory ? 0040000 : 0100000)
            || ($private && ($stat['mode'] & 0777) !== ($directory ? 0700 : 0600))
            || (!$directory && $stat['nlink'] !== 1)
            || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
            self::reject();
        }

        return [
            'dev' => $stat['dev'], 'ino' => $stat['ino'], 'mode' => $stat['mode'],
            'uid' => $stat['uid'], 'size' => $stat['size'], 'nlink' => $stat['nlink'],
        ];
    }

    /**
     * @param Node $expected
     * @param Node $observed
     */
    private static function sameNode(array $expected, array $observed): void
    {
        foreach (['dev', 'ino', 'mode', 'uid'] as $field) {
            if ($expected[$field] !== $observed[$field]) {
                self::reject();
            }
        }
    }

    /**
     * @param Node $expected
     * @param Node $observed
     */
    private static function sameFile(array $expected, array $observed): void
    {
        self::sameNode($expected, $observed);
        if ($expected['size'] !== $observed['size'] || $expected['nlink'] !== $observed['nlink']) {
            self::reject();
        }
    }

    private static function reject(): never
    {
        throw new RuntimeException('Owned quality evidence directory rejected or incomplete.');
    }
}
