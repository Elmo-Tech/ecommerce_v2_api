<?php

declare(strict_types=1);

use App\Core\Http\ApiLocaleResolver;

it('negotiates bounded supported request languages', function (?string $header, string $expected): void {
    expect(class_exists(ApiLocaleResolver::class))->toBeTrue();
    expect((new ApiLocaleResolver)->resolve($header))->toBe($expected);
})->with([
    [null, 'ar'], ['', 'ar'], ['fr', 'ar'], ['ar', 'ar'], ['en', 'en'],
    ['ar-EG', 'ar'], ['EN-us', 'en'], ['en;q=0.9,ar;q=0.8', 'en'],
    ['en;q=0,ar;q=0.5', 'ar'], ['en;q=0.7,ar;q=0.7', 'en'],
    ['ar;q=0.7,en;q=0.7', 'ar'], ['ar;q=0,*;q=1', 'en'], ['en;q=0,*;q=1', 'ar'],
    ['ar;q=0,en;q=0', 'ar'], ['en;q=1.1', 'ar'], ['en;q=0.1234', 'ar'],
    ['en;q=0.8,en;q=0.7', 'ar'], ['en;q=0.8,en;q=0.8', 'en'],
    ['en,'.str_repeat('x', 4096), 'ar'], ['en,'.implode(',', array_fill(0, 32, 'fr')), 'ar'],
    ['en,../../private', 'ar'], ['fr;q=1,en-GB;q=0.3', 'en'],
])->group('api-foundation');

