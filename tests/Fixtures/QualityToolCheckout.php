<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Owned, synthetic Git checkout with fake tool proxies for launcher tests.
 * Real tool controls use the original locked binaries only after the
 * existing fast guard runs inside a separate scratch process.
 */
final class QualityToolCheckout
{
    public const PINT = "{\n    \"preset\": \"psr12\"\n}\n";

    public const ANALYSIS = "includes:\n    - vendor/larastan/larastan/extension.neon\n\nparameters:\n    level: 6\n    tmpDir: %env.F04_QUALITY_CACHE%\n";

    public readonly FoundationTestCheckout $checkout;

    public readonly string $root;

    public function __construct()
    {
        $this->checkout = new FoundationTestCheckout();
        $this->root = $this->checkout->root;
        try {
            foreach (['app', 'tools/quality', 'vendor/composer', 'vendor/larastan/larastan', 'home', 'tmp'] as $directory) {
                if (!mkdir($this->root.'/'.$directory, 0700, true)) {
                    throw new RuntimeException('Cannot prepare owned quality fixture.');
                }
            }
            $this->write('pint.json', self::PINT);
            $this->write('phpstan.neon', self::ANALYSIS);
            $this->write('artisan', "<?php\n\ndeclare(strict_types=1);\n");
            $this->write('app/Legacy.php', "<?php\n\nfunction legacyQualityProbe(): int { return 1; }\n");
            $this->write('tests/Support/with space.php', "<?php\n\ndeclare(strict_types=1);\n");
            $this->write('vendor/larastan/larastan/extension.neon', "parameters: []\n");
            $source = dirname(__DIR__, 2);
            foreach (['composer.json', 'composer.lock', 'tools/quality/evidence.php', 'tools/quality/php-policy.php', 'tools/quality/run.php'] as $name) {
                if (is_file($source.'/'.$name)) {
                    $contents = file_get_contents($source.'/'.$name);
                    if ($contents === false) {
                        throw new RuntimeException('Cannot copy approved fixture source.');
                    }
                    $this->write($name, $contents);
                }
            }
            $lock = json_decode((string) file_get_contents($this->root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
            $this->write('vendor/composer/installed.json', json_encode([
                'packages' => [...$lock['packages'], ...$lock['packages-dev']],
                'dev' => true,
            ], JSON_THROW_ON_ERROR));
            foreach (['pint', 'phpstan'] as $tool) {
                $this->write('vendor/bin/'.$tool, <<<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$tmp = getenv('TMPDIR');
$cache = getenv('F04_QUALITY_CACHE');
file_put_contents($root.'/tool-reached', json_encode([
    'argv' => $argv,
    'environment' => [
        'APP_ENV' => getenv('APP_ENV'),
        'DB_CONNECTION' => getenv('DB_CONNECTION'),
        'DB_DATABASE' => getenv('DB_DATABASE'),
        'CACHE_STORE' => getenv('CACHE_STORE'),
        'SESSION_DRIVER' => getenv('SESSION_DRIVER'),
        'QUEUE_CONNECTION' => getenv('QUEUE_CONNECTION'),
        'MAIL_MAILER' => getenv('MAIL_MAILER'),
    ],
    'tmp' => $tmp,
    'cache' => $cache,
    'privateTmp' => is_string($tmp) && is_dir($tmp) && (fileperms($tmp) & 0777) === 0700,
    'privateCache' => is_string($cache) && is_dir($cache) && (fileperms($cache) & 0777) === 0700,
], JSON_THROW_ON_ERROR));
if (is_string($cache) && is_dir($cache)) {
    file_put_contents($cache.'/synthetic-cache', 'owned');
}
if (is_file($root.'/fail-tool')) {
    fwrite(STDERR, "synthetic-private-child-output\n");
    exit(7);
}
if (basename($argv[0]) === 'pint') {
    echo json_encode([
        'about' => 'synthetic Pint reporter',
        'files' => [],
        'time' => ['total' => 0],
        'memory' => 0,
    ], JSON_THROW_ON_ERROR);
}
exit(0);
PHP);
                chmod($this->root.'/vendor/bin/'.$tool, 0700);
            }
            $this->git(['init', '--quiet']);
            $this->git(['add', '--', 'artisan', 'app/Legacy.php', 'tests/Support/with space.php', 'pint.json', 'phpstan.neon', 'composer.json', 'composer.lock', 'tools/quality']);
        } catch (\Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    public function write(string $name, string $contents): void
    {
        $this->checkout->write($name, $contents);
    }

    /**
     * @param list<string> $arguments
     */
    public function git(array $arguments): void
    {
        $environment = array_fill_keys(array_keys(getenv()), false);
        $environment = array_replace($environment, [
            'PATH' => '/usr/bin:/bin',
            'HOME' => $this->root.'/home',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
        ]);
        $process = new Process(['git', ...$arguments], $this->root, $environment);
        $process->setTimeout(15);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Synthetic Git fixture command failed.');
        }
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $overrides
     */
    public function launch(array $arguments, array $overrides = []): Process
    {
        return $this->checkout->run(
            ['tools/quality/run.php', ...$arguments],
            array_replace(['HOME' => $this->root.'/home', 'TMPDIR' => $this->root.'/tmp'], $overrides),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function observation(): array
    {
        $raw = file_get_contents($this->root.'/tool-reached');
        if ($raw === false) {
            throw new RuntimeException('Synthetic tool did not run.');
        }

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    public function close(): void
    {
        $this->checkout->close();
    }
}
