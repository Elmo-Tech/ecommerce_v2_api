<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Fixtures\FoundationTestCheckout;
use Tests\Fixtures\QualityToolCheckout;
use Tools\Quality\PhpQualityPolicy;
use Tools\Quality\PintResult;

function f04RequireQualityPolicy(): void
{
    $path = dirname(__DIR__, 3).'/tools/quality/php-policy.php';
    Assert::assertTrue(is_file($path), 'F0-04 PHP quality policy must be implemented.');
    require_once $path;
}

function f04RequireQualityLauncher(): void
{
    Assert::assertTrue(is_file(dirname(__DIR__, 3).'/tools/quality/run.php'), 'F0-04 guarded quality launcher must be implemented.');
}

/**
 * Actual locked CLI control. The fixture contains no real application,
 * database, private credentials, or Larastan bootstrap. The latter is
 * intentionally verified later on the guarded real checkout (T018/T019).
 *
 * @return array{exit: int|null, output: string, stderr: string, root: string, unchanged: bool}
 */
function f04RealQualityProbe(string $tool, bool $valid, bool $malformed = false): array
{
    $fixture = new FoundationTestCheckout();
    try {
        $root = $fixture->root;
        foreach (['home', 'tmp'] as $directory) {
            Assert::assertTrue(mkdir($root.'/'.$directory, 0700));
        }
        $source = dirname(__DIR__, 3);
        if ($tool === 'pint') {
            $code = $valid
                ? "<?php\n\ndeclare(strict_types=1);\n\nfunction formatProbe(): int\n{\n    return 1;\n}\n"
                : "<?php\n\ndeclare(strict_types=1);\n\nfunction formatProbe( ):int{ return 1; }\n";
            $fixture->write('pint.json', QualityToolCheckout::PINT);
            $args = [
                $source.'/vendor/bin/pint', '--test', '--format=json',
                '--no-interaction', '--no-ansi', '--config='.$root.'/pint.json',
                '--cache-file='.$root.'/reports/pint.cache', $root.'/sample.php',
            ];
        } else {
            $code = "<?php\n\ndeclare(strict_types=1);\n\n"
                .($valid ? "/** @param list<string> \$items */\n" : '')
                ."function typeProbe(array \$items): int\n{\n    return count(\$items);\n}\n";
            $fixture->write('probe.neon', "parameters:\n    level: 6\n    tmpDir: ".json_encode($root.'/reports/phpstan', JSON_THROW_ON_ERROR)."\n    parallel:\n        maximumNumberOfProcesses: 1\n");
            $args = [
                $source.'/vendor/bin/phpstan', 'analyse', '--level=6',
                '--configuration='.$root.'/probe.neon', '--no-progress',
                '--error-format=json', '--no-interaction', '--no-ansi', $root.'/sample.php',
            ];
        }
        if ($malformed) {
            $code = "<?php\nfunction malformedProbe( {\n";
        }
        $fixture->write('sample.php', $code);
        $before = hash_file('sha256', $root.'/sample.php');
        Assert::assertTrue(is_file($args[0]));
        // Apply the existing guard in the child BEFORE the actual vendor proxy.
        $fixture->write('probe.php', "<?php\nrequire __DIR__.'/tests/Support/TestEnvironmentGuard.php';\n"
            .'\\Tests\\Support\\TestEnvironmentGuard::applyFast(__DIR__);'."\n"
            .'$process = proc_open('.var_export([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', ...$args], true)
            .", [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], \$pipes, __DIR__);\n"
            ."exit(is_resource(\$process) ? proc_close(\$process) : 2);\n");
        $result = $fixture->run(['-d', 'zend.exception_ignore_args=1', 'probe.php'], [
            'HOME' => $root.'/home', 'TMPDIR' => $root.'/tmp', 'CI' => 'true',
        ]);

        return [
            'exit' => $result->getExitCode(),
            'output' => $result->getOutput(),
            'stderr' => $result->getErrorOutput(),
            'root' => $root,
            'unchanged' => hash_file('sha256', $root.'/sample.php') === $before,
        ];
    } finally {
        $fixture->close();
    }
}

it('executes the locked Pint on isolated valid PHP and rejects actual style violations read-only', function (): void {
    $good = f04RealQualityProbe('pint', true);
    $bad = f04RealQualityProbe('pint', false);
    expect($good['exit'])->toBe(0);
    expect($bad['exit'])->toBe(1);
    expect($good['unchanged'] && $bad['unchanged'])->toBeTrue();
    $clean = json_decode($good['output'], true, flags: JSON_THROW_ON_ERROR);
    $findings = json_decode($bad['output'], true, flags: JSON_THROW_ON_ERROR);
    expect(isset($clean['files'], $findings['files']) && $clean['files'] === [])->toBeTrue();
    expect(is_array($findings['files']) && count($findings['files']) === 1)->toBeTrue();
})->group('testing-foundation');

it('executes locked PHPStan at actual level six and reports the intended iterable value type finding', function (): void {
    $good = f04RealQualityProbe('phpstan', true);
    $bad = f04RealQualityProbe('phpstan', false);
    expect($good['exit'])->toBe(0);
    expect($bad['exit'])->toBe(1);
    expect($good['unchanged'] && $bad['unchanged'])->toBeTrue();
    $positive = json_decode($good['output'], true, flags: JSON_THROW_ON_ERROR);
    $negative = json_decode($bad['output'], true, flags: JSON_THROW_ON_ERROR);
    expect($positive['totals'])->toBe(['errors' => 0, 'file_errors' => 0]);
    expect($negative['totals'])->toBe(['errors' => 0, 'file_errors' => 1]);
    $files = array_values($negative['files']);
    expect(count($files))->toBe(1);
    expect($files[0]['messages'][0]['identifier'] ?? null)->toBe('missingType.iterableValue');
})->group('testing-foundation');

it('runs the fixed quality launcher only with a safe inherited environment and private temporary cache', function (string $mode): void {
    f04RequireQualityLauncher();
    $fixture = new QualityToolCheckout();
    try {
        $result = $fixture->launch([$mode]);
        expect($result->getExitCode())->toBe(0);
        expect($fixture->checkout->reachedAutoload())->toBeTrue();
        $seen = $fixture->observation();
        expect($seen['environment'])->toBe([
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ]);
        expect($seen['privateTmp'])->toBeTrue();
        expect($seen['privateCache'])->toBeTrue();
        $argv = $seen['argv'];
        expect(in_array($fixture->root.'/artisan', $argv, true))->toBeTrue();
        expect(in_array($fixture->root.'/tests/Support/with space.php', $argv, true))->toBeTrue();
        if ($mode === 'format') {
            expect(in_array('--test', $argv, true))->toBeTrue();
            expect(in_array('--config='.$fixture->root.'/pint.json', $argv, true))->toBeTrue();
        } else {
            expect(in_array('--level=6', $argv, true))->toBeTrue();
            expect(in_array('--configuration='.$fixture->root.'/phpstan.neon', $argv, true))->toBeTrue();
        }
        clearstatcache();
        expect(file_exists($seen['tmp']) || is_link($seen['tmp']))->toBeFalse();
        expect(file_exists($seen['cache']) || is_link($seen['cache']))->toBeFalse();
        expect(is_dir($fixture->root.'/tmp'))->toBeTrue();
    } finally {
        $fixture->close();
    }
})->with(['format', 'analyse'])->group('testing-foundation');

it('rejects unsafe inherited settings before any synthetic vendor startup', function (string $key, string $value): void {
    f04RequireQualityLauncher();
    $fixture = new QualityToolCheckout();
    try {
        $result = $fixture->launch(['analyse'], [$key => $value]);
        expect($result->getExitCode())->toBe(2);
        expect($fixture->checkout->reachedAutoload())->toBeFalse();
        expect(file_exists($fixture->root.'/tool-reached'))->toBeFalse();
        expect(str_contains($result->getOutput().$result->getErrorOutput(), 'synthetic-canary'))->toBeFalse();
    } finally {
        $fixture->close();
    }
})->with([
    ['APP_ENV', 'production'],
    ['DB_HOST', 'synthetic-canary.invalid'],
    ['REDIS_HOST', 'synthetic-canary.invalid'],
    ['APP_CONFIG_CACHE', '/synthetic-canary'],
    ['APP_BASE_PATH', '/synthetic-canary'],
    ['F04_QUALITY_CACHE', '/synthetic-canary'],
    ['GIT_DIR', '/synthetic-canary'],
    ['GIT_WORK_TREE', '/synthetic-canary'],
])->group('testing-foundation');

it('rejects unsafe checkout configuration and missing or unsupported tools before bootstrap', function (string $case): void {
    f04RequireQualityLauncher();
    $fixture = new QualityToolCheckout();
    try {
        if ($case === 'nonempty dotenv') {
            $fixture->write('.env', 'DB_HOST=synthetic-canary.invalid');
        } elseif ($case === 'dotenv symlink') {
            unlink($fixture->root.'/.env');
            $fixture->write('outside-env', 'synthetic-canary');
            expect(symlink($fixture->root.'/outside-env', $fixture->root.'/.env'))->toBeTrue();
        } elseif ($case === 'alternate dotenv') {
            $fixture->write('.env.production', 'synthetic-canary');
        } elseif ($case === 'cached config') {
            $fixture->write('bootstrap/cache/config.php', '<?php return [];');
        } elseif ($case === 'missing tool') {
            unlink($fixture->root.'/vendor/bin/phpstan');
        } elseif ($case === 'wrong installed version') {
            $path = $fixture->root.'/vendor/composer/installed.json';
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['packages'] as &$record) {
                if ($record['name'] === 'phpstan/phpstan') {
                    $record['version'] = '1.0.0';
                }
            }
            unset($record);
            $fixture->write('vendor/composer/installed.json', json_encode($data, JSON_THROW_ON_ERROR));
        } elseif ($case === 'config symlink') {
            unlink($fixture->root.'/phpstan.neon');
            $fixture->write('foreign.neon', QualityToolCheckout::ANALYSIS);
            expect(symlink($fixture->root.'/foreign.neon', $fixture->root.'/phpstan.neon'))->toBeTrue();
        } elseif ($case === 'wrong config') {
            $fixture->write('phpstan.neon', str_replace('level: 6', 'level: 5', QualityToolCheckout::ANALYSIS));
        }
        if ($case === 'wrong root') {
            $fixture->write('wrong-root.php', "<?php\nchdir(__DIR__.'/reports');\nrequire __DIR__.'/tools/quality/run.php';\n");
            $result = $fixture->checkout->run(['wrong-root.php', 'analyse']);
        } else {
            $arguments = $case === 'unsupported command' ? ['fix'] : ($case === 'arbitrary flags' ? ['analyse', '--configuration=/synthetic-canary'] : ['analyse']);
            $result = $fixture->launch($arguments);
        }
        expect($result->getExitCode())->toBe(2);
        expect($fixture->checkout->reachedAutoload())->toBeFalse();
        expect(file_exists($fixture->root.'/tool-reached'))->toBeFalse();
        expect(str_contains($result->getOutput().$result->getErrorOutput(), 'synthetic-canary'))->toBeFalse();
    } finally {
        $fixture->close();
    }
})->with([
    'nonempty dotenv', 'dotenv symlink', 'alternate dotenv', 'cached config',
    'missing tool', 'wrong installed version', 'config symlink', 'wrong config',
    'wrong root', 'unsupported command', 'arbitrary flags',
])->group('testing-foundation');

it('preserves the actual nonzero quality tool exit and cleans scratch without publishing raw child output', function (): void {
    f04RequireQualityLauncher();
    $fixture = new QualityToolCheckout();
    try {
        $fixture->write('fail-tool', '');
        $result = $fixture->launch(['analyse']);
        expect($result->getExitCode())->toBe(7);
        expect($fixture->checkout->reachedAutoload())->toBeTrue();
        $seen = $fixture->observation();
        expect(file_exists($seen['tmp']) || file_exists($seen['cache']))->toBeFalse();
        expect(str_contains($result->getOutput().$result->getErrorOutput(), 'synthetic-private-child-output'))->toBeFalse();
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('inventories exact tracked PHP including artisan and whitespace paths without generated files', function (): void {
    f04RequireQualityPolicy();
    $fixture = new QualityToolCheckout();
    try {
        $fixture->write('bootstrap/cache/generated.php', '<?php return [];');
        $fixture->write('app/untracked.php', '<?php return [];');
        $expected = ['app/Legacy.php', 'artisan', 'tests/Support/with space.php'];
        foreach (['evidence.php', 'php-policy.php', 'run.php'] as $name) {
            if (is_file($fixture->root.'/tools/quality/'.$name)) {
                $expected[] = 'tools/quality/'.$name;
            }
        }
        sort($expected, SORT_STRING);
        expect(PhpQualityPolicy::inventory($fixture->root))->toBe($expected);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('rejects indexed symlinks tracked generated PHP and worktree escape', function (string $case): void {
    f04RequireQualityPolicy();
    $fixture = new QualityToolCheckout();
    try {
        if ($case === 'indexed symlink') {
            expect(symlink($fixture->root.'/artisan', $fixture->root.'/app/link.php'))->toBeTrue();
            $fixture->git(['add', '--', 'app/link.php']);
        } elseif ($case === 'tracked generated') {
            $fixture->write('vendor/generated.php', '<?php return [];');
            $fixture->git(['add', '--', 'vendor/generated.php']);
        } elseif ($case === 'worktree escape') {
            unlink($fixture->root.'/app/Legacy.php');
            expect(symlink($fixture->root.'/artisan', $fixture->root.'/app/Legacy.php'))->toBeTrue();
        } else {
            unlink($fixture->root.'/app/Legacy.php');
        }
        expect(fn () => PhpQualityPolicy::inventory($fixture->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->with(['indexed symlink', 'tracked generated', 'worktree escape', 'missing tracked'])->group('testing-foundation');

it('requires strict types only for new first party PHP without mass changing legacy semantics', function (): void {
    f04RequireQualityPolicy();
    $fixture = new QualityToolCheckout();
    try {
        PhpQualityPolicy::assertNewStrictTypes($fixture->root, ['tests/Support/with space.php']);
        expect(fn () => PhpQualityPolicy::assertNewStrictTypes($fixture->root, ['app/Legacy.php']))->toThrow(RuntimeException::class);
        $fixture->write('app/Legacy.php', "<?php\n// declare(strict_types=1);\nfunction legacyQualityProbe(): int { return 1; }\n");
        expect(fn () => PhpQualityPolicy::assertNewStrictTypes($fixture->root, ['app/Legacy.php']))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->group('testing-foundation');

it('accepts only the approved local PSR twelve preset and rejects config scope or risky rule changes', function (string $config): void {
    f04RequireQualityPolicy();
    $fixture = new QualityToolCheckout();
    try {
        PhpQualityPolicy::assertPintConfig($fixture->root);
        $fixture->write('pint.json', $config);
        expect(fn () => PhpQualityPolicy::assertPintConfig($fixture->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->with([
    '{"preset":"laravel"}',
    '{"preset":"psr12","rules":{"strict_comparison":true}}',
    '{"preset":"psr12","exclude":["tests"]}',
    '{"preset":"psr12","extend":"https://synthetic.invalid/pint.json"}',
    '{"preset":"psr12","preset":"psr12"}',
])->group('testing-foundation');

it('requires the reviewed Larastan include and level six config without suppression or external bootstrap', function (string $extra): void {
    f04RequireQualityPolicy();
    $fixture = new QualityToolCheckout();
    try {
        PhpQualityPolicy::assertAnalysisConfig($fixture->root);
        $invalid = $extra === 'downgrade'
            ? str_replace('level: 6', 'level: 5', QualityToolCheckout::ANALYSIS)
            : ($extra === 'missing include' ? "parameters:\n    level: 6\n" : QualityToolCheckout::ANALYSIS.$extra);
        $fixture->write('phpstan.neon', $invalid);
        expect(fn () => PhpQualityPolicy::assertAnalysisConfig($fixture->root))->toThrow(RuntimeException::class);
    } finally {
        $fixture->close();
    }
})->with([
    'downgrade', 'missing include',
    "    ignoreErrors:\n        - '#.*#'\n",
    "    excludePaths:\n        - tests\n",
    "    bootstrapFiles:\n        - /synthetic-canary.php\n",
    "    includes:\n        - phpstan-baseline.neon\n",
])->group('testing-foundation');

function f04RequirePintResultPolicy(): void
{
    f04RequireQualityPolicy();
    Assert::assertTrue(class_exists(PintResult::class, false), 'F0-04 validated Pint result adapter must be implemented.');
}

/**
 * @param list<array{name: string}> $files
 */
function f04PintJson(array $files): string
{
    return json_encode([
        'about' => 'synthetic Pint reporter',
        'files' => $files,
        'time' => ['total' => 0.25],
        'memory' => 4,
    ], JSON_THROW_ON_ERROR);
}

it('rejects malformed PHP through the actual locked Pint without repairing it', function (): void {
    $probe = f04RealQualityProbe('pint', false, true);
    expect($probe['exit'])->toBe(1);
    expect($probe['unchanged'])->toBeTrue();
    expect($probe['stderr'])->not->toBeEmpty();
})->group('testing-foundation');

it('validates actual Pint JSON for a clean file and a style finding without exposing raw diagnostics', function (): void {
    f04RequirePintResultPolicy();
    $good = f04RealQualityProbe('pint', true);
    $bad = f04RealQualityProbe('pint', false);
    expect(PintResult::validate($good['output'], $good['stderr'], $good['exit'], $good['root'], ['sample.php']))->toBe([]);
    expect(PintResult::validate($bad['output'], $bad['stderr'], $bad['exit'], $bad['root'], ['sample.php']))->toBe(['sample.php']);
})->group('testing-foundation');

it('returns only the exact normalized tracked finding names and no raw report values', function (): void {
    f04RequirePintResultPolicy();
    $inventory = ['app/A.php', 'artisan', 'tests/with space.php'];
    $raw = f04PintJson([['name' => '/owned/tests/with space.php'], ['name' => 'artisan']]);
    expect(PintResult::validate($raw, '', 1, '/owned', $inventory))->toBe(['artisan', 'tests/with space.php']);
    expect(PintResult::validate(f04PintJson([]), '', 0, '/owned', $inventory))->toBe([]);
})->group('testing-foundation');

it('rejects inconsistent incomplete or unsafe Pint output rather than reporting format success', function (string $case): void {
    f04RequirePintResultPolicy();
    $raw = f04PintJson([['name' => 'app/A.php']]);
    $stderr = '';
    $exit = 1;
    if ($case === 'malformed') {
        $raw = '{"files":';
    } elseif ($case === 'duplicate property') {
        $raw = str_replace('"name":', '"name":"app/A.php","n\u0061me":', $raw);
    } elseif ($case === 'unknown property') {
        $raw = str_replace('"memory":4', '"memory":4,"raw":"synthetic-private-output"', $raw);
    } elseif ($case === 'unknown file') {
        $raw = f04PintJson([['name' => 'app/Untracked.php']]);
    } elseif ($case === 'escaping file') {
        $raw = f04PintJson([['name' => '../app/A.php']]);
    } elseif ($case === 'duplicate file') {
        $raw = f04PintJson([['name' => 'app/A.php'], ['name' => '/owned/app/A.php']]);
    } elseif ($case === 'false green') {
        $exit = 0;
    } elseif ($case === 'empty failure') {
        $raw = f04PintJson([]);
    } elseif ($case === 'unexpected exit') {
        $exit = 7;
    } elseif ($case === 'tool error') {
        $stderr = 'synthetic-private-output';
    } elseif ($case === 'missing output') {
        $raw = '';
    } elseif ($case === 'diff payload') {
        $raw = str_replace('"name":"app\/A.php"', '"name":"app\/A.php","diff":"synthetic-private-output"', $raw);
    }
    $failure = null;
    try {
        PintResult::validate($raw, $stderr, $exit, '/owned', ['app/A.php']);
    } catch (RuntimeException $error) {
        $failure = $error;
    }
    expect($failure)->toBeInstanceOf(RuntimeException::class);
    expect($failure->getMessage())->not->toContain('synthetic-private-output');
})->with([
    'malformed', 'duplicate property', 'unknown property', 'unknown file',
    'escaping file', 'duplicate file', 'false green', 'empty failure',
    'unexpected exit', 'tool error', 'missing output', 'diff payload',
])->group('testing-foundation');
