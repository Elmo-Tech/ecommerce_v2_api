<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('omits the testing-only probe from an independently booted non-testing application', function (string $environment): void {
    $process = new Process(
        [PHP_BINARY, base_path('tests/Fixtures/isolated-module-bootstrap.php'), 'listed'],
        base_path(),
        ['APP_ENV' => $environment, 'APP_DEBUG' => 'false'],
    );
    $process->mustRun();

    $state = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($state)->toMatchArray([
        'ok' => true,
        'provider_loaded' => true,
        'marker_bound' => true,
        'probe_route' => false,
        'database_connections' => 0,
    ]);
})->with(['production', 'local'])->group('foundation');

it('cannot emit a production route-cache artifact when the actual app has no production routes', function (): void {
    $cachePath = tempnam(sys_get_temp_dir(), 'f0-01-empty-routes-');
    expect($cachePath)->not->toBeFalse();
    unlink($cachePath);

    try {
        $env = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_ROUTES_CACHE' => $cachePath];

        $command = new Process([PHP_BINARY, base_path('artisan'), 'route:cache', '--no-ansi'], base_path(), $env);
        $command->setTimeout(90);
        $command->mustRun();

        // Laravel 13 intentionally declines route:cache when the collection is empty.
        expect($command->getOutput())->toContain("Your application doesn't have any routes.")
            ->and(is_file($cachePath))->toBeFalse();

        $list = new Process([PHP_BINARY, base_path('artisan'), 'route:list', '--json'], base_path(), $env);
        $list->mustRun();

        expect(json_decode($list->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([]);
    } finally {
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }
})->group('foundation');

it('excludes the probe from a genuine production-generated route cache in a scratch application', function (): void {
    // The real app has zero production routes, so Laravel cannot generate its
    // route cache. This isolated COPY adds only one harmless redirect control
    // route OUTSIDE the repository, while using the very same Module Registry
    // providers/config and real artisan route:cache command.
    $fixtureRoot = sys_get_temp_dir().'/f0-01-production-route-fixture-'.bin2hex(random_bytes(8));
    $filesystem = new Filesystem;

    $filesystem->mkdir([
        $fixtureRoot.'/bootstrap/cache',
        $fixtureRoot.'/routes',
        $fixtureRoot.'/config',
    ]);

    try {
        copy(base_path('artisan'), $fixtureRoot.'/artisan');

        if (! symlink(base_path('vendor'), $fixtureRoot.'/vendor')) {
            throw new RuntimeException('Cannot link the installed test vendor tree into scratch fixture.');
        }

        file_put_contents(
            $fixtureRoot.'/bootstrap/providers.php',
            '<?php return require '.var_export(base_path('bootstrap/providers.php'), true).';',
        );

        copy(base_path('config/modules.php'), $fixtureRoot.'/config/modules.php');

        file_put_contents($fixtureRoot.'/bootstrap/app.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->create();
PHP);

        file_put_contents($fixtureRoot.'/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// Control route exists ONLY in this disposable scratch fixture.
Route::redirect('/__f0-cache-control', '/__f0-cache-target');
PHP);

        $cachePath = $fixtureRoot.'/bootstrap/cache/routes.php';

        $env = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_ROUTES_CACHE' => $cachePath,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];

        $cache = new Process([PHP_BINARY, $fixtureRoot.'/artisan', 'route:cache', '--no-ansi'], $fixtureRoot, $env);
        $cache->setTimeout(90);
        $cache->mustRun();

        expect(is_file($cachePath))->toBeTrue();

        $content = file_get_contents($cachePath);

        expect($content)->toContain('__f0-cache-control')
            ->not->toContain('__foundation/module-probe')
            ->not->toContain('foundation.demo.probe');

        $list = new Process([PHP_BINARY, $fixtureRoot.'/artisan', 'route:list', '--json'], $fixtureRoot, $env);
        $list->setTimeout(90);
        $list->mustRun();

        $routes = json_decode($list->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $uris = array_column($routes, 'uri');

        expect($uris)->toContain('__f0-cache-control')
            ->not->toContain('api/v1/__foundation/module-probe');
    } finally {
        // Delete only the uniquely named test scratch directory, never the
        // actual GitHub checkout, project vendor/ or any production data.
        $filesystem->remove($fixtureRoot);
    }
})->group('foundation');
