<?php

declare(strict_types=1);

use Tests\Fixtures\ApiHttpFixtures;

beforeEach(function (): void {
    ApiHttpFixtures::register();
    config(['app.debug' => true]);
});

it('maps genuine native API exceptions to the approved status and safe envelope', function (string $path, int $status, string $code): void {
    $response = $this->get(ApiHttpFixtures::PREFIX.$path, ['Accept-Language' => 'en']);
    $response->assertStatus($status)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errorCode', $code)
        ->assertJsonPath('errors', [])
        ->assertJsonStructure(['success', 'message', 'errorCode', 'errors'])
        ->assertHeader('Content-Type', 'application/json');
    $body = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
    expect(array_keys(get_object_vars($body)))->toBe(['success', 'message', 'errorCode', 'errors'])
        ->and($body->errors)->toBe([])
        ->and($body->message)->toBeString()->not->toBeEmpty()
        ->and($response->getContent())->not->toContain(ApiHttpFixtures::PRIVATE_VALUE, 'SQL SELECT', '/private/source.php', '"trace"', '"exception"');
    $response->assertHeader('X-Request-ID');
})->with([
    ['/status/400', 400, 'BAD_REQUEST'],
    ['/authentication', 401, 'UNAUTHENTICATED'],
    ['/authorization', 403, 'ACCESS_DENIED'],
    ['/not-registered', 404, 'RESOURCE_NOT_FOUND'],
    ['/status/404', 404, 'RESOURCE_NOT_FOUND'],
    ['/status/409', 409, 'RESOURCE_CONFLICT'],
    ['/status/422', 422, 'VALIDATION_FAILED'],
    ['/status/429', 429, 'RATE_LIMIT_EXCEEDED'],
    ['/fault', 500, 'INTERNAL_ERROR'],
    ['/status/500', 500, 'INTERNAL_ERROR'],
    ['/status/503', 503, 'SERVICE_UNAVAILABLE'],
    ['/status/418', 418, 'HTTP_ERROR'],
])->group('api-foundation');

it('preserves protocol headers and true method mismatch without a catch-all route', function (): void {
    $response = $this->postJson(ApiHttpFixtures::PREFIX.'/ok');
    $response->assertStatus(405)->assertJsonPath('errorCode', 'METHOD_NOT_ALLOWED')->assertHeader('X-Request-ID');
    expect($response->headers->get('Allow'))->toContain('GET', 'HEAD');
    $this->getJson(ApiHttpFixtures::PREFIX.'/status/401')
        ->assertStatus(401)->assertHeader('WWW-Authenticate', 'Bearer realm="foundation-fixture"');
    $this->getJson(ApiHttpFixtures::PREFIX.'/status/429')->assertStatus(429)->assertHeader('Retry-After', '7');
    $this->getJson(ApiHttpFixtures::PREFIX.'/status/503')->assertStatus(503)->assertHeader('Retry-After', '17');
})->group('api-foundation');

it('keeps redirects downloads and non-v1 or unrelated native responses intact', function (): void {
    $this->get(ApiHttpFixtures::PREFIX.'/redirect')->assertRedirect('/fixture-native-target')->assertHeader('X-Request-ID');
    $this->get(ApiHttpFixtures::PREFIX.'/download')->assertOk()->assertDownload('fixture.txt')->assertHeader('X-Request-ID');
    $this->getJson('/api/v10/__f0-api/legacy')->assertNotFound()
        ->assertJsonMissingPath('errorCode')->assertHeaderMissing('X-Request-ID');
    $this->get('/__f0-api-unregistered-html')->assertNotFound()->assertHeaderMissing('X-Request-ID');
})->group('api-foundation');

