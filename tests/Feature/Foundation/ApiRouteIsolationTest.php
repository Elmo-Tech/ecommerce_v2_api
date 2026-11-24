<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('uses the actual new bootstrap when building and loading an isolated production route cache', function (): void {
    // No route is added to the real application. The control route belongs
    // exclusively to a scratch copy of the actual native API route seam.
    expect(is_file(base_path('routes/api.php')))->toBeTrue();
    $root = sys_get_temp_dir().'/f0-02-route-cache-'.bin2hex(random_bytes(12));
    $fs = new Filesystem;
    foreach ([
        '/bootstrap/cache', '/routes', '/config', '/storage/framework/cache/data',
        '/storage/framework/sessions', '/storage/framework/views', '/storage/logs', '/storage/app/private',
    ] as $dir) {
        $fs->makeDirectory($root.$dir, recursive: true);
    }

    try {
        foreach (['artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'routes/console.php', 'routes/api.php'] as $file) {
            if (! copy(base_path($file), $root.'/'.$file)) {
                throw new RuntimeException('Cannot copy reviewed bootstrap fixture.');
            }
        }
        $fs->copyDirectory(base_path('config'), $root.'/config');
        if (is_dir(base_path('lang'))) {
            $fs->copyDirectory(base_path('lang'), $root.'/lang');
        }
        if (! symlink(base_path('vendor'), $root.'/vendor')) {
            throw new RuntimeException('Cannot link isolated fixture vendor.');
        }
        expect(hash_file('sha256', $root.'/bootstrap/app.php'))->toBe(hash_file('sha256', base_path('bootstrap/app.php')));
        file_put_contents($root.'/routes/api.php', "\n\\Illuminate\\Support\\Facades\\Route::redirect('/__f0-02-cache-control', '/__f0-cache-target');\n", FILE_APPEND);
        $cachePath = $root.'/bootstrap/cache/routes.php';
        $env = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_ROUTES_CACHE' => $cachePath, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $cache = new Process([PHP_BINARY, $root.'/artisan', 'route:cache', '--no-ansi'], $root, $env);
        $cache->setTimeout(90);
        $cache->mustRun();
        expect(is_file($cachePath))->toBeTrue();
        expect(file_get_contents($cachePath))->toContain('__f0-02-cache-control')
            ->not->toContain('__foundation/module-probe', '__f0-api');
        $list = new Process([PHP_BINARY, $root.'/artisan', 'route:list', '--json'], $root, $env);
        $list->setTimeout(90);
        $list->mustRun();
        $routes = json_decode($list->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect(array_column($routes, 'uri'))->toBe(['api/v1/__f0-02-cache-control']);
    } finally {
        $fs->deleteDirectory($root);
    }
})->group('api-foundation');

it('correlates even the exact v1 boundary and does not include lookalike versions', function (): void {
    $this->getJson('/api/v1')->assertNotFound()->assertJsonPath('errorCode', 'RESOURCE_NOT_FOUND')->assertHeader('X-Request-ID');
    $this->getJson('/api/v10')->assertNotFound()->assertJsonMissingPath('errorCode')->assertHeaderMissing('X-Request-ID');
    expect(app('db')->getConnections())->toBeEmpty();
})->group('api-foundation');

