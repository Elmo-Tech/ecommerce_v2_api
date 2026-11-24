<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('returns a fresh trusted request ID on success, no content, unmatched routes, validation and native errors', function (): void {
    $header = ['X-Request-ID' => 'spoofed-fixture-id', 'Accept-Language' => 'en'];
    $responses = [
        $this->getJson(ApiHttpFixtures::PREFIX.'/ok', $header)->assertOk(),
        $this->postJson(ApiHttpFixtures::PREFIX.'/created', [], $header)->assertCreated(),
        $this->getJson(ApiHttpFixtures::PREFIX.'/no-content', $header)->assertNoContent(),
        $this->getJson('/api/v1/unknown-fixture', $header)->assertNotFound(),
        $this->postJson(ApiHttpFixtures::PREFIX.'/ok', [], $header)->assertStatus(405),
        $this->postJson(ApiHttpFixtures::PREFIX.'/validate', ['items' => [['quantity' => 0]]], $header)->assertUnprocessable(),
        $this->getJson(ApiHttpFixtures::PREFIX.'/fault', $header)->assertStatus(500),
    ];
    $ids = [];
    foreach ($responses as $response) {
        $id = $response->headers->get('X-Request-ID');
        expect(is_string($id) && Str::isUuid($id))->toBeTrue()
            ->and($id)->not->toBe('spoofed-fixture-id')
            ->and($response->getContent())->not->toContain('"requestId"', 'spoofed-fixture-id');
        $ids[] = $id;
    }
    expect(array_unique($ids))->toHaveCount(count($ids))
        ->and($responses[2]->getContent())->toBe('');
})->group('api-foundation');

it('correlates safe technical diagnostics and restores unrelated logging and locale state after failures', function (): void {
    config(['app.debug' => true]);
    $this->app->setLocale('en');
    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });
    Log::withContext(['fixtureUnrelatedContext' => 'retained']);
    try {
        $failure = $this->getJson(ApiHttpFixtures::PREFIX.'/fault', ['Accept-Language' => 'ar']);
        $failure->assertStatus(500)->assertJsonPath('errorCode', 'INTERNAL_ERROR')->assertHeader('X-Request-ID');
        $id = $failure->headers->get('X-Request-ID');
        $diagnostics = array_values(array_filter($events, static fn (MessageLogged $event): bool =>
            ($event->context['request_id'] ?? null) === $id && $event->level === 'error'));
        expect($diagnostics)->toHaveCount(1);
        foreach ($events as $event) {
            expect($event->message)->not->toContain(ApiHttpFixtures::PRIVATE_VALUE, 'SQL SELECT', '/private/source.php');
            expect($event->context)->not->toHaveKey('exception');
            expect(json_encode($event->context, JSON_THROW_ON_ERROR))->not->toContain(ApiHttpFixtures::PRIVATE_VALUE);
        }
        $this->getJson(ApiHttpFixtures::PREFIX.'/locale', ['Accept-Language' => 'en'])->assertJsonPath('locale', 'en');
        $this->getJson(ApiHttpFixtures::PREFIX.'/locale', ['Accept-Language' => 'fr'])->assertJsonPath('locale', 'ar');
        expect($this->app->getLocale())->toBe('en')->and($this->app['translator']->getLocale())->toBe('en');
        Log::info('fixture.after');
        $after = end($events);
        expect($after->context['fixtureUnrelatedContext'] ?? null)->toBe('retained')
            ->and($after->context)->not->toHaveKey('request_id');
    } finally {
        Log::withoutContext(['fixtureUnrelatedContext']);
    }
})->group('api-foundation');

