<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;

it('keeps the API foundation technical and the project HTTP enum int-backed', function (): void {
    $root = dirname(__DIR__, 2);
    expect(enum_exists(HttpStatusCode::class))->toBeTrue();
    expect((string) (new ReflectionEnum(HttpStatusCode::class))->getBackingType())->toBe('int');
    $files = [
        'app/Helpers/ApiResponse.php', 'app/Enums/HttpStatusCode.php',
        'app/Core/Http/ApiErrorCodes.php', 'app/Core/Http/ApiExceptionMapper.php',
        'app/Http/Middleware/AssignRequestId.php',
    ];
    foreach ($files as $file) {
        expect(is_file($root.'/'.$file))->toBeTrue();
        $source = file_get_contents($root.'/'.$file);
        expect($source)->not->toContain('App\\Modules\\', 'Tests\\Fixtures\\', 'DB::', 'Schema::', 'Redis::', 'tenant()');
        expect(preg_match('/\benum\s+\w+\s*:\s*string\b/', $source))->toBe(0);
    }
    expect(file_get_contents($root.'/bootstrap/providers.php'))->toContain('ModuleRegistryServiceProvider::class');
    expect(file_get_contents($root.'/bootstrap/app.php'))->not->toContain('Tests\\Fixtures\\', '__f0-api');
})->group('api-foundation');

it('has an empty native API seam and no fixture dependency or unapproved packages at this foundation milestone', function (): void {
    $root = dirname(__DIR__, 2);
    $manifest = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(array_keys($manifest['require']))->toEqualCanonicalizing(['php', 'laravel/framework'])
        ->and(array_keys($manifest['require-dev']))->toEqualCanonicalizing(['pestphp/pest', 'pestphp/pest-plugin-laravel']);
    expect(is_file($root.'/routes/api.php'))->toBeTrue();
    foreach (token_get_all(file_get_contents($root.'/routes/api.php')) as $token) {
        expect(is_array($token) && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))->toBeTrue();
    }
    expect(glob($root.'/database/migrations/*.php') ?: [])->toBe([]);
})->group('api-foundation');

