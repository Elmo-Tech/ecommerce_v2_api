<?php

use Symfony\Component\Process\Process;

it('rejects invalid registry entries during isolated Laravel provider registration', function (string $scenario, string $message): void {
    $process = new Process([PHP_BINARY, base_path('tests/Fixtures/isolated-module-bootstrap.php'), $scenario], base_path());
    $process->mustRun();

    $state = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($state['ok'])->toBeFalse()
        ->and($state['type'])->toBe(InvalidArgumentException::class)
        ->and($state['message'])->toBe($message);
})->with([
    'duplicate id' => ['duplicate-id', 'Duplicate module id [collision].'],
    'duplicate provider' => ['duplicate-provider', 'Duplicate module provider [App\\Modules\\FoundationDemo\\FoundationDemoServiceProvider].'],
    'duplicate canonical identity' => ['duplicate-provider-by-case', 'Duplicate module provider [App\\Modules\\FoundationDemo\\FoundationDemoServiceProvider].'],
    'abstract provider after valid provider' => ['abstract-after-demo', 'Module provider [Tests\\Fixtures\\AbstractModuleProvider] must be instantiable.'],
    'unknown provider' => ['missing-provider', 'Module provider class [App\\Missing\\UnknownModuleProvider] does not exist.'],
    'invalid provider type' => ['wrong-provider', 'Module provider [stdClass] must extend Illuminate\\Support\\ServiceProvider.'],
    'empty id' => ['empty-id', 'Module id must be a non-empty string.'],
])->group('foundation');

it('refuses an abstract provider before activating an earlier valid module', function (): void {
    $process = new Process([PHP_BINARY, base_path('tests/Fixtures/isolated-module-bootstrap.php'), 'abstract-after-demo'], base_path());
    $process->mustRun();

    $state = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($state)->toMatchArray([
        'ok' => false,
        'type' => InvalidArgumentException::class,
        'provider_loaded' => false,
        'marker_bound' => false,
    ]);
})->group('foundation');
