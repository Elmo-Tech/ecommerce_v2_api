<?php

use App\Core\Modules\ModuleRegistry;
use App\Modules\FoundationDemo\Contracts\FoundationMarker;
use App\Modules\FoundationDemo\FoundationDemoServiceProvider;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

it('loads the listed FoundationDemo through the native Laravel provider lifecycle', function (): void {
    /** @var ModuleRegistry $registry */
    $registry = app(ModuleRegistry::class);

    expect($registry->providers())->toBe([FoundationDemoServiceProvider::class])
        ->and(app()->getProvider(FoundationDemoServiceProvider::class))->not->toBeNull()
        ->and(app()->bound(FoundationMarker::class))->toBeTrue()
        ->and(Route::has('foundation.demo.probe'))->toBeTrue()
        ->and(app('db')->getConnections())->toBeEmpty();

    $this->getJson('/api/v1/__foundation/module-probe')
        ->assertOk()
        ->assertExactJson([
            'module' => 'foundation-demo',
            'source' => 'explicit-registry',
        ]);

    expect(app('db')->getConnections())->toBeEmpty();
})->group('foundation');

it('does not discover or activate an unlisted module in an isolated application', function (): void {
    // The actual provider class is on disk and autoloadable, but not in the
    // fresh application registry. Its binding and route must stay absent.
    expect(class_exists(FoundationDemoServiceProvider::class))->toBeTrue();

    $process = new Process([PHP_BINARY, base_path('tests/Fixtures/isolated-module-bootstrap.php'), 'unlisted'], base_path());
    $process->mustRun();

    $state = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($state)->toMatchArray([
        'ok' => true,
        'provider_loaded' => false,
        'marker_bound' => false,
        'probe_route' => false,
        'database_connections' => 0,
    ]);
})->group('foundation');
