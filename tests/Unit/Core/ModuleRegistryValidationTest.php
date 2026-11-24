<?php

use App\Core\Modules\ModuleRegistry;
use App\Providers\AppServiceProvider;

abstract class FoundationUninstantiableProvider extends Illuminate\Support\ServiceProvider
{
    // Abstract despite extending the correct Laravel ServiceProvider.
}

it('accepts a reviewed explicit provider list without loading providers', function (): void {
    $registry = new ModuleRegistry([
        ['id' => 'app', 'provider' => AppServiceProvider::class],
    ]);

    expect($registry->providers())->toBe([AppServiceProvider::class]);
})->group('foundation');

it('accepts an empty explicit list without discovering module directories', function (): void {
    expect((new ModuleRegistry([]))->providers())->toBe([]);
})->group('foundation');

it('rejects malformed, duplicate or unsafe module definitions', function (array $entries, string $error): void {
    expect(fn () => new ModuleRegistry($entries))
        ->toThrow(InvalidArgumentException::class, $error);
})->with([
    'duplicate id' => [
        [
            ['id' => 'shared', 'provider' => AppServiceProvider::class],
            ['id' => 'shared', 'provider' => Illuminate\Foundation\Providers\ArtisanServiceProvider::class],
        ],
        'Duplicate module id [shared].',
    ],
    'duplicate provider' => [
        [
            ['id' => 'first', 'provider' => AppServiceProvider::class],
            ['id' => 'second', 'provider' => AppServiceProvider::class],
        ],
        'Duplicate module provider ['.AppServiceProvider::class.'].',
    ],
    'case-insensitive duplicate provider' => [
        [
            ['id' => 'first', 'provider' => AppServiceProvider::class],
            ['id' => 'second', 'provider' => strtolower(AppServiceProvider::class)],
        ],
        'Duplicate module provider ['.AppServiceProvider::class.'].',
    ],
    'abstract provider following valid provider' => [
        [
            ['id' => 'valid', 'provider' => AppServiceProvider::class],
            ['id' => 'abstract', 'provider' => FoundationUninstantiableProvider::class],
        ],
        'Module provider ['.FoundationUninstantiableProvider::class.'] must be instantiable.',
    ],
    'empty id' => [
        [['id' => '   ', 'provider' => AppServiceProvider::class]],
        'Module id must be a non-empty string.',
    ],
    'missing id' => [
        [['provider' => AppServiceProvider::class]],
        'Module id must be a non-empty string.',
    ],
    'empty provider' => [
        [['id' => 'example', 'provider' => '']],
        'Module provider must be a non-empty class name.',
    ],
    'nonexistent provider' => [
        [['id' => 'example', 'provider' => 'App\\DoesNotExist\\MissingProvider']],
        'Module provider class [App\\DoesNotExist\\MissingProvider] does not exist.',
    ],
    'wrong type of provider' => [
        [['id' => 'example', 'provider' => stdClass::class]],
        'Module provider ['.stdClass::class.'] must extend Illuminate\\Support\\ServiceProvider.',
    ],
    'non-array entry' => [
        ['surprise'],
        'Each module definition must be an array.',
    ],
    'associative config instead of list' => [
        ['one' => ['id' => 'example', 'provider' => AppServiceProvider::class]],
        'Module definitions must be an explicit list.',
    ],
])->group('foundation');
