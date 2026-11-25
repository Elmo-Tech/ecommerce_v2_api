<?php

declare(strict_types=1);

namespace Tools\Quality;

use RuntimeException;
use SensitiveParameter;

/**
 * Fixed first-party PHP policy. Reads the canonical checkout and locked
 * metadata without requiring Composer or the framework.
 */
final class PhpQualityPolicy
{
    public const PINT = "{\n    \"preset\": \"psr12\"\n}\n";

    public const ANALYSIS = "includes:\n    - vendor/larastan/larastan/extension.neon\n\nparameters:\n    level: 6\n    tmpDir: %env.F04_QUALITY_CACHE%\n";

    private const LOCK_HASH = '4f86a8d3e61aeddae531a177d901a0aa6632713116d9f959537102282079473e';

    private const PINS = [
        'laravel/pint' => '1.32.3',
        'phpstan/phpstan' => '2.3.7',
        'larastan/larastan' => '3.15.0',
    ];

    /**
     * @return list<string>
     */
    public static function inventory(string $root): array
    {
        $files = [];
        foreach (self::index($root) as $path => $record) {
            if ($path !== 'artisan' && !str_ends_with($path, '.php')) {
                continue;
            }
            if (preg_match('~(?:^|/)(?:vendor|node_modules|storage|build|\\.git)(?:/|$)~D', $path)
                || str_starts_with($path, 'bootstrap/cache/')
                || in_array(basename($path), ['_ide_helper.php', '_ide_helper_models.php', '_ide_helper_actions.php', '.phpstorm.meta.php'], true)) {
                self::reject();
            }
            self::assertIndexedRegular($root, $path, $record);
            $files[] = $path;
        }
        if ($files === [] || count($files) > 10000 || strlen(implode("\0", $files)) > 524288) {
            self::reject();
        }
        sort($files, SORT_STRING);

        return $files;
    }

    public static function assertPintConfig(string $root): void
    {
        // Deliberately permit only the reviewed preset, not arbitrary rules,
        // extend, finder excludes, remote config or a silently ignored option.
        if (self::read($root.'/pint.json', 16384) !== self::PINT) {
            self::reject();
        }
    }

    public static function assertAnalysisConfig(string $root): void
    {
        if (self::read($root.'/phpstan.neon', 16384) !== self::ANALYSIS) {
            self::reject();
        }
        self::regular($root.'/vendor/larastan/larastan/extension.neon');
    }

    /**
     * @param list<string> $newPaths independently selected new tracked PHP
     */
    public static function assertNewStrictTypes(string $root, array $newPaths): void
    {
        $inventory = self::inventory($root);
        if (!array_is_list($newPaths) || count(array_unique($newPaths)) !== count($newPaths)) {
            self::reject();
        }
        foreach ($newPaths as $path) {
            if (!in_array($path, $inventory, true)) {
                self::reject();
            }
            $tokens = token_get_all(self::read($root.'/'.$path, 16777216), TOKEN_PARSE);
            $significant = [];
            $opened = false;
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    if ($token[0] === T_OPEN_TAG && !$opened) {
                        $opened = true;
                        continue;
                    }
                    if (!$opened && $token[0] === T_INLINE_HTML
                        && preg_match('~^#!/usr/bin/env php\r?\n$~D', $token[1]) === 1) {
                        continue;
                    }
                    if ($opened && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $significant[] = $token[1];
                } else {
                    $significant[] = $token;
                }
                if (count($significant) === 7) {
                    break;
                }
            }
            if (!$opened || $significant !== ['declare', '(', 'strict_types', '=', '1', ')', ';']) {
                self::reject();
            }
        }
    }

    /**
     * @return string exact approved vendor proxy, not a user-selected path
     */
    public static function approvedTool(string $root, string $mode): string
    {
        $lockRaw = self::read($root.'/composer.lock', 4194304);
        if (hash('sha256', $lockRaw) !== self::LOCK_HASH) {
            self::reject();
        }
        $lock = json_decode($lockRaw, true, 64, JSON_THROW_ON_ERROR);
        $manifest = json_decode(self::read($root.'/composer.json', 65536), true, 32, JSON_THROW_ON_ERROR);
        $installed = json_decode(self::read($root.'/vendor/composer/installed.json', 4194304), true, 64, JSON_THROW_ON_ERROR);
        if (($manifest['config']['allow-plugins'] ?? null) !== ['pestphp/pest-plugin' => true]
            || ($installed['dev'] ?? null) !== true
            || !is_array($installed['packages'] ?? null)
            || count($lock['packages']) !== 74 || count($lock['packages-dev']) !== 48) {
            self::reject();
        }
        foreach (self::PINS as $name => $version) {
            if (($manifest['require-dev'][$name] ?? null) !== $version) {
                self::reject();
            }
        }
        $expected = self::packageMap([...$lock['packages'], ...$lock['packages-dev']]);
        $observed = self::packageMap($installed['packages']);
        if (count($expected) !== 122 || array_keys($expected) !== array_keys($observed)) {
            self::reject();
        }
        foreach ($expected as $name => $package) {
            if (($observed[$name]['version'] ?? null) !== $package['version']) {
                self::reject();
            }
            foreach (['source', 'dist'] as $kind) {
                if (isset($package[$kind]['reference']) && ($observed[$name][$kind]['reference'] ?? null) !== $package[$kind]['reference']) {
                    self::reject();
                }
            }
        }
        self::regular($root.'/vendor/autoload.php');
        $tool = match ($mode) {
            'format' => $root.'/vendor/bin/pint',
            'analyse' => $root.'/vendor/bin/phpstan',
            default => self::reject(),
        };
        self::regular($tool);
        if (!is_executable($tool)) {
            self::reject();
        }

        return $tool;
    }

    /**
     * Hash the actual tracked working tree plus index identities. No raw diff
     * or file contents leave this process. Reject unsupported special entries.
     */
    public static function fingerprint(string $root): string
    {
        $hash = hash_init('sha256');
        foreach (self::index($root) as $path => $record) {
            self::assertIndexedRegular($root, $path, $record);
            $absolute = $root.'/'.$path;
            $stat = self::regular($absolute);
            $digest = hash_file('sha256', $absolute);
            if ($digest === false) {
                self::reject();
            }
            hash_update($hash, $path."\0".$record."\0".$stat['mode']."\0".$digest."\0");
        }

        return hash_final($hash);
    }

    public static function assertNoOverrides(): void
    {
        foreach ([getenv(), $_ENV, $_SERVER] as $source) {
            foreach ($source as $key => $value) {
                if (is_string($key) && $value !== '' && preg_match(
                    '/^(?:F04_|PHPSTAN_|PINT_|GIT_(?:DIR|WORK_TREE|INDEX_FILE|COMMON_DIR|OBJECT_DIRECTORY|ALTERNATE_OBJECT_DIRECTORIES|CONFIG(?:_|$)|NAMESPACE|PREFIX)|COMPOSER_(?:VENDOR_DIR|BIN_DIR)|PHPRC$|PHP_INI_SCAN_DIR$)/iD',
                    $key,
                )) {
                    self::reject();
                }
            }
        }
    }

    /**
     * @return array<string, string> sorted stage-zero records
     */
    private static function index(string $root): array
    {
        if ($root === '/' || !is_dir($root) || is_link($root) || realpath($root) !== $root
            || rtrim(self::git($root, ['rev-parse', '--show-toplevel']), "\r\n") !== $root) {
            self::reject();
        }
        $raw = self::git($root, ['ls-files', '--stage', '-z']);
        if ($raw === '' || !str_ends_with($raw, "\0")) {
            self::reject();
        }
        $records = explode("\0", substr($raw, 0, -1));
        if (count($records) > 20000) {
            self::reject();
        }
        $index = [];
        foreach ($records as $record) {
            $pair = explode("\t", $record, 2);
            if (count($pair) !== 2 || preg_match('/^[0-9]{6} [a-f0-9]{40,64} 0$/D', $pair[0]) !== 1
                || !self::safeRelative($pair[1]) || isset($index[$pair[1]])) {
                self::reject();
            }
            $index[$pair[1]] = $pair[0];
        }
        ksort($index, SORT_STRING);

        return $index;
    }

    private static function safeRelative(string $path): bool
    {
        return $path !== '' && strlen($path) <= 1024
            && !str_starts_with($path, '/') && !str_contains($path, '\\')
            && preg_match('/[\x00-\x1f\x7f]/', $path) !== 1
            && array_intersect(explode('/', $path), ['', '.', '..']) === [];
    }

    private static function assertIndexedRegular(string $root, string $path, string $record): void
    {
        if (!str_starts_with($record, '100644 ') && !str_starts_with($record, '100755 ')) {
            self::reject();
        }
        self::regular($root.'/'.$path);
    }

    /**
     * @param list<string> $arguments
     */
    private static function git(string $root, array $arguments): string
    {
        // Fixed hosted Linux Git; never trust an inherited Git index, global
        // config, alternate repository, or shell expression.
        $process = proc_open(
            ['/usr/bin/git', '-C', $root, ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root,
            ['PATH' => '/usr/bin:/bin', 'HOME' => '/nonexistent', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null', 'GIT_OPTIONAL_LOCKS' => '0', 'LC_ALL' => 'C'],
        );
        if (!is_resource($process)) {
            self::reject();
        }
        $raw = stream_get_contents($pipes[1], 4194305);
        fclose($pipes[1]);
        if (!is_string($raw) || strlen($raw) > 4194304) {
            proc_terminate($process);
            proc_close($process);
            self::reject();
        }
        if (proc_close($process) !== 0) {
            self::reject();
        }

        return $raw;
    }

    /**
     * @param array<array-key, mixed> $records
     * @return array<string, array<string, mixed>>
     */
    private static function packageMap(array $records): array
    {
        $map = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_string($record['name'] ?? null)
                || !is_string($record['version'] ?? null) || isset($map[$record['name']])) {
                self::reject();
            }
            $map[$record['name']] = $record;
        }
        ksort($map, SORT_STRING);

        return $map;
    }

    /**
     * @return array{mode: int, size: int, nlink: int}
     */
    private static function regular(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || realpath($path) !== $path
            || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1
            || $stat['size'] > 67108864) {
            self::reject();
        }

        return ['mode' => $stat['mode'], 'size' => $stat['size'], 'nlink' => $stat['nlink']];
    }

    private static function read(string $path, int $limit): string
    {
        $stat = self::regular($path);
        if ($stat['size'] > $limit) {
            self::reject();
        }
        $raw = @file_get_contents($path, false, null, 0, $limit + 1);
        if ($raw === false || strlen($raw) !== $stat['size']) {
            self::reject();
        }

        return $raw;
    }

    private static function reject(): never
    {
        throw new RuntimeException('Approved PHP quality policy rejected.');
    }
}

/**
 * Private tool-generated cache tree, not a generic filesystem deletion API.
 * Every visited entry must be within the exact created tree, private,
 * same-owner and regular or directory. No symlink or hardlink traversal.
 *
 * @phpstan-type Node array{dev: int, ino: int, mode: int, uid: int, nlink: int, size: int}
 */
final class PhpQualityScratch
{
    private const FILE_LIMIT = 16777216;

    /** @var array<string, Node> */
    private array $fixed = [];

    private readonly string $directory;

    private readonly string $marker;

    private readonly int $uid;

    public function __construct()
    {
        if (!function_exists('posix_geteuid')) {
            self::reject();
        }
        $this->uid = posix_geteuid();
        $parent = sys_get_temp_dir();
        $stat = @lstat($parent);
        if ($parent === '/' || $stat === false || realpath($parent) !== $parent || is_link($parent)
            || ($stat['mode'] & 0170000) !== 0040000
            || !(($stat['uid'] === $this->uid && ($stat['mode'] & 0022) === 0)
                || ($parent === '/tmp' && $stat['uid'] === 0 && ($stat['mode'] & 01000) !== 0))) {
            self::reject();
        }
        $this->marker = bin2hex(random_bytes(24));
        $this->directory = $parent.'/f04-php-'.$this->marker;
        if (!@mkdir($this->directory, 0700)) {
            self::reject();
        }
        $this->fixed[''] = $this->node($this->directory);
        foreach (['tmp', 'cache', 'home'] as $name) {
            if (!@mkdir($this->directory.'/'.$name, 0700)) {
                self::reject();
            }
            $this->fixed[$name] = $this->node($this->directory.'/'.$name);
        }
        $this->openFile('.owner', $this->marker);
    }

    public function path(string $name): string
    {
        if (!in_array($name, ['tmp', 'cache', 'home', 'stdout', 'stderr'], true)) {
            self::reject();
        }

        return $this->directory.'/'.$name;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    public function execute(array $command, string $root, array $environment, int $timeout): int
    {
        $this->verify();
        $this->openFile('stdout', '');
        $this->openFile('stderr', '');
        $process = @proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->path('stdout'), 'w'], 2 => ['file', $this->path('stderr'), 'w']],
            $pipes,
            $root,
            $environment,
        );
        if (!is_resource($process)) {
            self::reject();
        }
        $start = hrtime(true);
        $exit = -1;
        try {
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                foreach (['stdout', 'stderr'] as $name) {
                    clearstatcache(true, $this->path($name));
                    $size = @filesize($this->path($name));
                    if ($size === false || $size > self::FILE_LIMIT) {
                        self::reject();
                    }
                }
                if ((hrtime(true) - $start) / 1000000000 > $timeout) {
                    self::reject();
                }
                usleep(20000);
            }
        } finally {
            if ($exit < 0 && proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            $closed = proc_close($process);
        }
        foreach (['stdout', 'stderr'] as $name) {
            $node = $this->node($this->path($name));
            if ($node['size'] > self::FILE_LIMIT) {
                self::reject();
            }
        }
        $result = $exit >= 0 ? $exit : $closed;

        return $result >= 0 && $result <= 255 ? $result : 2;
    }

    public function cleanup(): void
    {
        $this->verify();
        $entries = @scandir($this->directory);
        if ($entries === false) {
            self::reject();
        }
        $expected = array_keys($this->fixed);
        $expected = array_values(array_filter($expected, static fn (string $name): bool => $name !== ''));
        $actual = array_values(array_diff($entries, ['.', '..']));
        sort($expected, SORT_STRING);
        sort($actual, SORT_STRING);
        if ($actual !== $expected) {
            self::reject();
        }
        // Collect and validate the complete tree before deleting anything.
        $targets = [];
        $total = 0;
        foreach (['tmp', 'cache', 'home'] as $name) {
            $this->collect($this->directory.'/'.$name, $targets, $total, 0);
        }
        foreach (['stdout', 'stderr'] as $name) {
            if (isset($this->fixed[$name])) {
                $targets[$this->directory.'/'.$name] = $this->node($this->directory.'/'.$name);
            }
        }
        foreach ($targets as $path => $record) {
            $this->same($this->fixed[''], $this->node($this->directory));
            $this->same($record, $this->node($path));
            $directory = ($record['mode'] & 0170000) === 0040000;
            if (!($directory ? @rmdir($path) : @unlink($path))) {
                self::reject();
            }
        }
        $owner = $this->directory.'/.owner';
        $this->same($this->fixed['.owner'], $this->node($owner));
        if (@file_get_contents($owner, false, null, 0, 49) !== $this->marker
            || !@unlink($owner)) {
            self::reject();
        }
        $this->same($this->fixed[''], $this->node($this->directory));
        if (!@rmdir($this->directory)) {
            self::reject();
        }
        clearstatcache(true, $this->directory);
        if (@lstat($this->directory) !== false) {
            self::reject();
        }
    }

    /**
     * @param array<string, Node> $targets
     */
    private function collect(string $path, array &$targets, int &$total, int $depth): void
    {
        if ($depth > 32 || count($targets) > 20000) {
            self::reject();
        }
        $record = $this->node($path);
        if (($record['mode'] & 0170000) === 0040000) {
            $entries = @scandir($path);
            if ($entries === false) {
                self::reject();
            }
            foreach ($entries as $name) {
                if ($name !== '.' && $name !== '..') {
                    $this->collect($path.'/'.$name, $targets, $total, $depth + 1);
                }
            }
        } else {
            $total += $record['size'];
            if ($total > 536870912) {
                self::reject();
            }
        }
        $targets[$path] = $record;
    }

    private function openFile(string $name, #[SensitiveParameter] string $contents): void
    {
        $path = $this->directory.'/'.$name;
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            self::reject();
        }
        try {
            if ($contents !== '' && fwrite($handle, $contents) !== strlen($contents)) {
                self::reject();
            }
            if (!fflush($handle)) {
                self::reject();
            }
        } finally {
            fclose($handle);
        }
        $this->fixed[$name] = $this->node($path);
    }

    private function verify(): void
    {
        foreach ($this->fixed as $name => $record) {
            $this->same($record, $this->node($name === '' ? $this->directory : $this->directory.'/'.$name));
        }
        if (@file_get_contents($this->directory.'/.owner', false, null, 0, 49) !== $this->marker) {
            self::reject();
        }
    }

    /**
     * @return Node
     */
    private function node(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || realpath($path) !== $path
            || $stat['uid'] !== $this->uid || ($stat['mode'] & 0077) !== 0
            || !in_array($stat['mode'] & 0170000, [0040000, 0100000], true)
            || (($stat['mode'] & 0170000) === 0100000 && $stat['nlink'] !== 1)) {
            self::reject();
        }

        return [
            'dev' => $stat['dev'], 'ino' => $stat['ino'], 'mode' => $stat['mode'],
            'uid' => $stat['uid'], 'nlink' => $stat['nlink'], 'size' => $stat['size'],
        ];
    }

    /**
     * @param Node $expected
     * @param Node $observed
     */
    private function same(array $expected, array $observed): void
    {
        foreach (['dev', 'ino', 'mode', 'uid'] as $key) {
            if ($expected[$key] !== $observed[$key]) {
                self::reject();
            }
        }
    }

    private static function reject(): never
    {
        throw new RuntimeException('Owned PHP quality scratch rejected or incomplete.');
    }
}
