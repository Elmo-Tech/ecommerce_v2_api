<?php

declare(strict_types=1);

use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('matches representative real HTTP response shapes and status/error mappings to the shared contract', function (): void {
    $path = base_path('specs/002-api-foundation/contracts/openapi.json');
    expect(is_file($path))->toBeTrue();
    $document = json_decode(file_get_contents($path), flags: JSON_THROW_ON_ERROR);
    $codes = $document->{'x-http-status-error-codes'};
    $success = $this->getJson(ApiHttpFixtures::PREFIX.'/ok')->assertOk();
    $body = json_decode($success->getContent(), flags: JSON_THROW_ON_ERROR);
    expect(array_keys(get_object_vars($body)))->toEqualCanonicalizing($document->components->schemas->SuccessResponse->required);
    $this->postJson(ApiHttpFixtures::PREFIX.'/created')->assertCreated()->assertJsonPath('data.id', '9223372036854775807');
    $this->getJson(ApiHttpFixtures::PREFIX.'/no-content')->assertNoContent();
    foreach ([400, 401, 404, 409, 418, 422, 429, 500, 503] as $status) {
        $error = $this->getJson(ApiHttpFixtures::PREFIX.'/status/'.$status)->assertStatus($status);
        $body = json_decode($error->getContent(), flags: JSON_THROW_ON_ERROR);
        $expected = $codes->{(string) $status} ?? $codes->default;
        expect($body->errorCode)->toBe($expected)
            ->and($body->errors)->toBe([])
            ->and(array_keys(get_object_vars($body)))->toEqualCanonicalizing($document->components->schemas->ErrorResponse->required);
    }
})->group('api-foundation');

