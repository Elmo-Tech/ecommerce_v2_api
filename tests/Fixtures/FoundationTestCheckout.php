<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * An owned scratch checkout. No actual Laravel application, real vendor tree,
 * database configuration or credentials are copied. The fake autoload and
 * Pest runner only create local sentinels. This lets negative tests exercise
 * the real entry scripts/configured bootstrap without touching services.
 */
final class FoundationTestCheckout
{
    public readonly string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir().'/f03-guard-'.bin2hex(random_bytes(16));
        if (!mkdir($this->root, 0700)) {
            throw new RuntimeException('Cannot create owned test checkout.');
        }

        foreach (['bootstrap/cache', 'tests/Support', 'vendor/bin', 'reports'] as $dir) {
            if (!mkdir($this->root.'/'.$dir, 0700, true)) {
                $this->close();
                throw new RuntimeException('Cannot create scratch directory.');
            }
        }

        $source = dirname(__DIR__, 2);
        $this->copy($source, 'phpunit.xml');
        foreach (['run-tests.php', 'bootstrap.php', 'TestEnvironmentGuard.php'] as $file) {
            $path = 'tests/Support/'.$file;
            if (is_file($source.'/'.$path)) {
                $this->copy($source, $path);
            }
        }

        $this->write('vendor/autoload.php', <<<'PHP'
<?php
file_put_contents(dirname(__DIR__).'/autoload-reached', 'reached');
PHP);
        $this->write('vendor/bin/pest', <<<'PHP'
<?php
file_put_contents(dirname(__DIR__, 2).'/pest-reached', 'reached');
exit(0);
PHP);
        $this->write('.env', '');
    }

    private function copy(string $source, string $relative): void
    {
        if (!copy($source.'/'.$relative, $this->root.'/'.$relative)) {
            $this->close();
            throw new RuntimeException('Cannot copy scratch test entry fixture.');
        }
    }

    public function write(string $relative, string $contents): void
    {
        if (file_put_contents($this->root.'/'.$relative, $contents) === false) {
            throw new RuntimeException('Cannot create test fixture file.');
        }
    }

    public function reachedAutoload(): bool
    {
        return is_file($this->root.'/autoload-reached');
    }

    public function reachedPest(): bool
    {
        return is_file($this->root.'/pest-reached');
    }

    public function run(array $arguments, array $overrides = []): Process
    {
        // Remove inherited process variables in the child; do not echo or
        // copy their values. Only the synthetic settings below are used.
        $names = array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER)));
        $environment = array_fill_keys($names, false);
        $environment = array_replace($environment, ['PATH' => '/usr/bin:/bin'], $overrides);
        $process = new Process([PHP_BINARY, ...$arguments], $this->root, $environment);
        $process->setTimeout(15);
        $process->run();

        return $process;
    }


    /**
     * Exercise the installed Pest CLI on a scratch config with exactly one
     * harmless Unit probe. No real application bootstrap or original test
     * discovery in the child. The fake configured autoload remains in place.
     */
    public function runPestProbe(array $overrides): Process
    {
        $source = dirname(__DIR__, 2);
        foreach (['tests/Unit', 'tests/Feature', 'tests/Architecture', 'app', 'vendor/phpunit/phpunit'] as $dir) {
            if (!is_dir($this->root.'/'.$dir) && !mkdir($this->root.'/'.$dir, 0700, true)) {
                throw new RuntimeException('Cannot prepare scratch Pest probe.');
            }
        }
        $this->copy($source, 'composer.json');
        $this->copy($source, 'vendor/phpunit/phpunit/phpunit.xsd');
        $this->write('tests/Pest.php', "<?php\n");
        $this->write('tests/Unit/ProbeTest.php', <<<'PHP'
<?php
it('reaches only the harmless scratch probe', function (): void {
    file_put_contents(dirname(__DIR__, 2).'/probe-reached', 'reached');
    expect(true)->toBeTrue();
});
PHP);

        return $this->run([
            $source.'/vendor/bin/pest', '--configuration', 'phpunit.xml',
            '--testsuite', 'Unit', '--fail-on-empty-test-suite',
        ], $overrides);
    }

    public function reachedProbe(): bool
    {
        return is_file($this->root.'/probe-reached');
    }

    public function configuredBootstrap(): string
    {
        $xml = simplexml_load_file($this->root.'/phpunit.xml', options: LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException('Invalid reviewed PHPUnit config.');
        }

        return (string) $xml['bootstrap'];
    }

    public function close(): void
    {
        // This exact randomly created root is owned by the fixture.
        // Never follow links or touch their targets.
        if (!is_dir($this->root) || is_link($this->root)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isLink() || !$item->isDir()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }
        rmdir($this->root);
    }
}
