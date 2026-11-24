<?php

declare(strict_types=1);

// Spawned by Feature tests: each invocation has a fresh Laravel application,
// without modifying the already booted main test container or any config file.
use App\Modules\FoundationDemo\Contracts\FoundationMarker;
use App\Modules\FoundationDemo\FoundationDemoServiceProvider;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\AbstractModuleProvider;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $root.'/bootstrap/app.php';

$scenario = $argv[1] ?? 'listed';

$valid = [
    ['id' => 'foundation-demo', 'provider' => FoundationDemoServiceProvider::class],
];

$definitions = match ($scenario) {
    'listed' => $valid,
    'unlisted' => [],
    'duplicate-id' => [
        ['id' => 'collision', 'provider' => AppServiceProvider::class],
        ['id' => 'collision', 'provider' => FoundationDemoServiceProvider::class],
    ],
    'duplicate-provider' => [
        ['id' => 'one', 'provider' => FoundationDemoServiceProvider::class],
        ['id' => 'two', 'provider' => FoundationDemoServiceProvider::class],
    ],
    'duplicate-provider-by-case' => [
        ['id' => 'foundation-demo', 'provider' => FoundationDemoServiceProvider::class],
        ['id' => 'same-provider', 'provider' => strtolower(FoundationDemoServiceProvider::class)],
    ],
    'abstract-after-demo' => [
        ...$valid,
        ['id' => 'abstract', 'provider' => AbstractModuleProvider::class],
    ],
    'missing-provider' => [
        ['id' => 'invalid', 'provider' => 'App\\Missing\\UnknownModuleProvider'],
    ],
    'wrong-provider' => [
        ['id' => 'invalid', 'provider' => stdClass::class],
    ],
    'empty-id' => [
        ['id' => '   ', 'provider' => FoundationDemoServiceProvider::class],
    ],
    default => throw new InvalidArgumentException('Unknown isolated bootstrap scenario.'),
};

// Laravel reads config before RegisterProviders. This hook safely changes the
// isolated in-memory config *before* native provider registration and boot.
$app->beforeBootstrapping(
    RegisterProviders::class,
    static function ($application) use ($definitions): void {
        $application['config']->set('modules', $definitions);
    },
);

try {
    $app->make(Kernel::class)->bootstrap();

    $result = [
        'ok' => true,
        'provider_loaded' => $app->getProvider(FoundationDemoServiceProvider::class) !== null,
        'marker_bound' => $app->bound(FoundationMarker::class),
        'probe_route' => Route::has('foundation.demo.probe'),
        'database_connections' => count($app['db']->getConnections()),
    ];
} catch (Throwable $exception) {
    $result = [
        'ok' => false,
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        // Assert no earlier "valid" module was activated before full
        // validation rejected a later invalid or abstract definition.
        'provider_loaded' => $app->getProvider(FoundationDemoServiceProvider::class) !== null,
        'marker_bound' => $app->bound(FoundationMarker::class),
    ];
}

echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
