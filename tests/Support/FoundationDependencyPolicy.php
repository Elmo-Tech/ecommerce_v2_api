<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Test-only policy for the approved Foundation quality dependency boundary.
 */
final class FoundationDependencyPolicy
{
    /**
     * @param array<string, mixed> $manifest
     */
    public static function assertApproved(array $manifest): void
    {
        $runtime = $manifest['require'] ?? null;
        $development = $manifest['require-dev'] ?? null;
        $config = $manifest['config'] ?? null;
        Assert::assertIsArray($runtime, 'Missing runtime dependency map');
        Assert::assertIsArray($development, 'Missing development dependency map');
        Assert::assertIsArray($config, 'Missing Composer configuration');

        Assert::assertEqualsCanonicalizing(
            ['php', 'laravel/framework'],
            array_keys($runtime),
            'Unexpected runtime dependency keys',
        );
        Assert::assertEqualsCanonicalizing(
            ['pestphp/pest', 'pestphp/pest-plugin-laravel', 'laravel/pint', 'phpstan/phpstan', 'larastan/larastan'],
            array_keys($development),
            'Unexpected development dependency keys',
        );
        foreach ([
            'laravel/pint' => '1.32.3',
            'phpstan/phpstan' => '2.3.7',
            'larastan/larastan' => '3.15.0',
        ] as $package => $version) {
            Assert::assertSame($version, $development[$package] ?? null, 'Unapproved quality package constraint: '.$package);
        }
        Assert::assertSame(
            ['pestphp/pest-plugin' => true],
            $config['allow-plugins'] ?? null,
            'Unexpected Composer plugin allowlist',
        );
    }
}
