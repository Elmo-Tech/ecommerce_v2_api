<?php

declare(strict_types=1);

use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('captures real FormRequest errors with stable declared nested fields and safe localized messages', function (): void {
    /** @var \Tests\TestCase $this */

    $payload = ['items' => [['quantity' => 0]]];
    $en = $this->postJson(ApiHttpFixtures::PREFIX.'/validate', $payload, ['Accept-Language' => 'en']);
    $ar = $this->postJson(ApiHttpFixtures::PREFIX.'/validate', $payload, ['Accept-Language' => 'ar']);
    foreach ([$en, $ar] as $response) {
        $response->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('errorCode', 'VALIDATION_FAILED')
            ->assertHeader('X-Request-ID');
        $body = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
        expect($body->errors)->toBeInstanceOf(stdClass::class)
            ->and(array_keys(get_object_vars($body->errors)))->toEqualCanonicalizing(['customerName', 'items.0.quantity'])
            ->and($body->errors->customerName)->toBeArray()->not()->toBeEmpty()
            ->and($body->errors->{'items.0.quantity'})->toBeArray()->not()->toBeEmpty()
            ->and($response->getContent())->not()->toContain('fixture-private-validation-value');
    }
    expect($en->json('message'))->not->toBe($ar->json('message'))
        ->and($en->json('errors')['customerName'])->not->toBe($ar->json('errors')['customerName']);
})->group('api-foundation');


it('does not expose an untrusted associative key from an invalid declared list', function (): void {
    /** @var \Tests\TestCase $this */

    $response = $this->postJson(ApiHttpFixtures::PREFIX.'/validate', [
        'customerName' => 'Fixture',
        'items' => ['fixturePrivateMapKey' => ['quantity' => 0]],
    ], ['Accept-Language' => 'en']);
    $response->assertUnprocessable()->assertJsonPath('errorCode', 'VALIDATION_FAILED');
    $body = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
    expect(array_keys(get_object_vars($body->errors)))->toBe(['items'])
        ->and($response->getContent())->not->toContain('fixturePrivateMapKey');
})->group('api-foundation');

it('uses declared wildcard provenance even when a feature omitted the list rule', function (): void {
    /** @var \Tests\TestCase $this */

    $response = $this->postJson(ApiHttpFixtures::PREFIX.'/validate-wildcard', [
        'customer' => ['name' => ''],
        'items' => ['fixturePrivateMapKey' => ['quantity' => 0]],
    ])->assertUnprocessable();
    $body = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
    expect(array_keys(get_object_vars($body->errors)))->toEqualCanonicalizing(['customer.name', 'items'])
        ->and($response->getContent())->not->toContain('fixturePrivateMapKey');
    $validList = $this->postJson(ApiHttpFixtures::PREFIX.'/validate-wildcard', [
        'customer' => ['name' => 'Fixture'],
        'items' => [['quantity' => 0]],
    ])->assertUnprocessable();
    expect(array_keys(get_object_vars(json_decode($validList->getContent(), flags: JSON_THROW_ON_ERROR)->errors)))
        ->toBe(['items.0.quantity']);
})->group('api-foundation');

it('omits unprovable root wildcard and manual-bag paths without leaking supplied keys', function (): void {
    /** @var \Tests\TestCase $this */

    $root = $this->postJson(ApiHttpFixtures::PREFIX.'/validate-root-wildcard', [
        'fixturePrivateMapKey' => ['quantity' => 0],
    ])->assertUnprocessable()->assertJsonPath('errorCode', 'VALIDATION_FAILED');
    $manual = $this->postJson(ApiHttpFixtures::PREFIX.'/validate-manual', [
        'field' => 'fixturePrivateMapKey',
    ])->assertUnprocessable()->assertJsonPath('errorCode', 'VALIDATION_FAILED');
    foreach ([$root, $manual] as $response) {
        expect(json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->errors)->toBe([])
            ->and($response->getContent())->not->toContain('fixturePrivateMapKey', 'fixture-private-manual');
    }
})->group('api-foundation');

it('does not replace native validator behavior outside the active API attempt', function (): void {
    /** @var \Tests\TestCase $this */

    $this->getJson(ApiHttpFixtures::PREFIX.'/locale')->assertOk();
    expect(get_class(\Illuminate\Support\Facades\Validator::make([], ['name' => 'required'])))
        ->toBe(\Illuminate\Validation\Validator::class);
})->group('api-foundation');
