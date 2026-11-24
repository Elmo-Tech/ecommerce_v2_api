<?php

declare(strict_types=1);

use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('captures real FormRequest errors with stable declared nested fields and safe localized messages', function (): void {
    $payload = ['items' => [['quantity' => 0]]];
    $en = $this->postJson(ApiHttpFixtures::PREFIX.'/validate', $payload, ['Accept-Language' => 'en']);
    $ar = $this->postJson(ApiHttpFixtures::PREFIX.'/validate', $payload, ['Accept-Language' => 'ar']);
    foreach ([$en, $ar] as $response) {
        $response->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('errorCode', 'VALIDATION_FAILED')
            ->assertHeader('X-Request-ID');
        $body = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
        expect($body->errors)->toBeInstanceOf(stdClass::class)
            ->and(array_keys(get_object_vars($body->errors)))->toEqualCanonicalizing(['customerName', 'items.0.quantity'])
            ->and($body->errors->customerName)->toBeArray()->not->toBeEmpty()
            ->and($body->errors->{'items.0.quantity'})->toBeArray()->not->toBeEmpty()
            ->and($response->getContent())->not->toContain('fixture-private-validation-value');
    }
    expect($en->json('message'))->not->toBe($ar->json('message'))
        ->and($en->json('errors')['customerName'])->not->toBe($ar->json('errors')['customerName']);
})->group('api-foundation');

