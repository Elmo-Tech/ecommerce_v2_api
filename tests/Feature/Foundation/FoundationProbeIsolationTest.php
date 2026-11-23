<?php

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

it('never puts the testing-only probe into a production-generated route cache', function (): void {
    // Use a unique scratch location OUTSIDE the repo. Never move a testing
    // route cache into a production application or commit the cache artifact.
    $cachePath = tempnam(sys_get_temp_dir(), 'f0-01-prod-routes-');

    expect($cachePath)->not->toBeFalse();

    unlink($cachePath);

    $environment = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_ROUTES_CACHE' => $cachePath,
    ];

    try {
        $cache = new Process([PHP_BINARY, base_path('artisan'), 'route:cache', '--no-ansi'], base_path(), $environment);
        $cache->setTimeout(90);
        $cache->mustRun();

        expect(is_file($cachePath))->toBeTrue();

        $cacheSource = file_get_contents($cachePath);

        expect($cacheSource)->not->toContain('__foundation/module-probe')
            ->not->toContain('foundation.demo.probe');

        $routeList = new Process([PHP_BINARY, base_path('artisan'), 'route:list', '--json'], base_path(), $environment);
        $routeList->setTimeout(90);
        $routeList->mustRun();

        $routes = json_decode($routeList->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        expect($routes)->toBe([]);
    } finally {
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }
})->group('foundation');
