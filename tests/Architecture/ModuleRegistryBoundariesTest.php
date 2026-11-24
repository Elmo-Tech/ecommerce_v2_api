<?php

// F0-01 deliberately uses a small, explicit source-boundary check instead of
// installing a universal code-inspection or module-scanning framework.
// Resolve source paths from the test file's location, never from a Laravel
// application container: these tests must run as a standalone Architecture suite.

define('F0_01_ARCH_ROOT', dirname(__DIR__, 2));

it('does not dynamically discover providers from module directories', function (): void {
    $registrySource = file_get_contents(F0_01_ARCH_ROOT.'/app/Core/Modules/ModuleRegistry.php');
    $providerSource = file_get_contents(F0_01_ARCH_ROOT.'/app/Providers/ModuleRegistryServiceProvider.php');
    $bootstrapSource = file_get_contents(F0_01_ARCH_ROOT.'/bootstrap/providers.php');

    foreach ([$registrySource, $providerSource, $bootstrapSource] as $source) {
        expect(preg_match('/\b(?:glob|scandir|opendir)\s*\(/', $source))->toBe(0)
            ->and(str_contains($source, 'RecursiveDirectoryIterator'))->toBeFalse()
            ->and(str_contains($source, 'Finder::'))->toBeFalse()
            ->and(str_contains($source, 'File::directories('))->toBeFalse();
    }

    expect($registrySource)->toContain('array_is_list(')
        ->and($providerSource)->toContain('$this->app->register($provider)')
        ->and($bootstrapSource)->toContain('ModuleRegistryServiceProvider::class');
})->group('foundation');

it('recognizes forbidden cross-module internal imports but permits public contracts', function (): void {
    $isForbidden = static fn (string $source): bool => preg_match(
        '/\bApp\\\\Modules\\\\[A-Za-z][A-Za-z0-9_]*\\\\(?:Internal|Infrastructure|Models)\\\\/',
        $source,
    ) === 1;

    $forbidden = '<?php use App\\Modules\\Inventory\\Internal\\StockWriter;';
    $allowed = '<?php use App\\Modules\\Inventory\\Contracts\\StockReservationContract;';

    expect($isForbidden($forbidden))->toBeTrue()
        ->and($isForbidden($allowed))->toBeFalse();

    // F0-01 has no business-module dependencies or hidden internal writers.
    foreach ([
        F0_01_ARCH_ROOT.'/app/Core/Modules/ModuleRegistry.php',
        F0_01_ARCH_ROOT.'/app/Providers/ModuleRegistryServiceProvider.php',
        F0_01_ARCH_ROOT.'/app/Modules/FoundationDemo/FoundationDemoServiceProvider.php',
    ] as $path) {
        expect($isForbidden(file_get_contents($path)))->toBeFalse();
    }
})->group('foundation');

it('does not retain a global tenant or access business databases during module registration', function (): void {
    foreach ([
        F0_01_ARCH_ROOT.'/app/Core/Modules/ModuleRegistry.php',
        F0_01_ARCH_ROOT.'/app/Providers/ModuleRegistryServiceProvider.php',
        F0_01_ARCH_ROOT.'/app/Modules/FoundationDemo/FoundationDemoServiceProvider.php',
    ] as $path) {
        $code = file_get_contents($path);

        expect(preg_match('/\b(?:DB::|Schema::|Tenant::|tenant\s*\(|getActiveTenant\s*\()/', $code))->toBe(0);
    }
})->group('foundation');
