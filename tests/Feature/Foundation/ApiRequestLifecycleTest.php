<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('returns a fresh trusted request ID on success, no content, unmatched routes, validation and native errors', function (): void {
    /** @var \Tests\TestCase $this */

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
    /** @var \Tests\TestCase $this */

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


it('reports distinct short-lived exceptions independently and does not suppress the next unrelated native report', function (): void {
    /** @var \Tests\TestCase $this */

    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });
    $response = $this->getJson(ApiHttpFixtures::PREFIX.'/report-twice')->assertOk();
    $id = $response->headers->get('X-Request-ID');
    $apiReports = array_values(array_filter($events, static fn (MessageLogged $event): bool =>
        ($event->context['request_id'] ?? null) === $id && $event->level === 'error'));
    expect($apiReports)->toHaveCount(2);
    foreach ($apiReports as $event) {
        expect($event->message)->not->toContain('fixture-private-manual')
            ->and($event->context)->not->toHaveKey('exception');
    }
    $this->getJson('/api/v10/__f0-api/legacy-fault')->assertStatus(500)->assertHeaderMissing('X-Request-ID');
    $native = array_values(array_filter($events, static fn (MessageLogged $event): bool =>
        str_contains($event->message, 'legacy-fixture-native-error')));
    expect($native)->not->toBeEmpty()
        ->and($native[0]->context)->not->toHaveKey('request_id');
})->group('api-foundation');

it('sanitizes a failure inside locale selection or restoration and unconditionally clears attempt state', function (string $failureLocale): void {
    /** @var \Tests\TestCase $this */

    $this->app->setLocale('en');
    // Make application and translator locales deliberately distinct, so
    // restoration proves both prior values rather than one shared default.
    $this->app['translator']->setLocale('ar');
    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });
    $armed = true;
    Event::listen(\Illuminate\Foundation\Events\LocaleUpdated::class, static function ($event) use (&$armed, $failureLocale): void {
        if ($armed && $event->locale === $failureLocale) {
            $armed = false;
            throw new RuntimeException('fixture-private-locale-lifecycle');
        }
    });
    $failure = $this->getJson(ApiHttpFixtures::PREFIX.'/locale', ['Accept-Language' => 'ar']);
    $failure->assertStatus(500)->assertJsonPath('errorCode', 'INTERNAL_ERROR')->assertHeader('X-Request-ID');
    $id = $failure->headers->get('X-Request-ID');
    expect($failure->getContent())->not->toContain('fixture-private-locale-lifecycle')
        ->and($this->app->getLocale())->toBe('en')
        ->and($this->app['translator']->getLocale())->toBe('ar');
    $attributes = $this->app['request']->attributes;
    foreach ([
        \App\Core\Http\ApiRequestContext::ACTIVE,
        \App\Core\Http\ApiRequestContext::REPORTED,
        \App\Core\Http\ApiRequestContext::MAPPED_RESPONSE,
    ] as $key) {
        expect($attributes->has($key))->toBeFalse();
    }
    $reports = array_values(array_filter($events, static fn (MessageLogged $event): bool =>
        ($event->context['request_id'] ?? null) === $id && $event->level === 'error'));
    expect($reports)->toHaveCount(1);
    foreach ($events as $event) {
        expect($event->message)->not->toContain('fixture-private-locale-lifecycle');
        expect($event->context)->not->toHaveKey('exception');
    }
    $this->getJson(ApiHttpFixtures::PREFIX.'/locale', ['Accept-Language' => 'en'])
        ->assertOk()->assertJsonPath('locale', 'en');
    expect($this->app['translator']->getLocale())->toBe('ar');
    $this->getJson('/api/v10/__f0-api/legacy-fault')->assertStatus(500)->assertHeaderMissing('X-Request-ID');
    expect(array_filter($events, static fn (MessageLogged $event): bool =>
        str_contains($event->message, 'legacy-fixture-native-error')))->not->toBeEmpty();
})->with(['ar', 'en'])->group('api-foundation');

it('distinguishes exception classification from the actual rendered HTTP status without a second error report', function (): void {
    /** @var \Tests\TestCase $this */

    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });
    $response = $this->getJson(ApiHttpFixtures::PREFIX.'/custom-error')->assertStatus(502);
    $id = $response->headers->get('X-Request-ID');
    $matching = array_values(array_filter($events, static fn (MessageLogged $event): bool =>
        ($event->context['request_id'] ?? null) === $id));
    $reports = array_values(array_filter($matching, static fn (MessageLogged $event): bool => $event->message === 'api.exception_reported'));
    $outcomes = array_values(array_filter($matching, static fn (MessageLogged $event): bool => $event->message === 'api.request_failed'));
    expect($reports)->toHaveCount(1)->and($outcomes)->toHaveCount(1)
        ->and($reports[0]->context['exception_status'])->toBe(500)
        ->and($reports[0]->context)->not->toHaveKey('http_status')
        ->and($outcomes[0]->context['http_status'])->toBe(502)
        ->and($outcomes[0]->context['error_code'])->toBe('HTTP_ERROR')
        ->and(array_filter($matching, static fn (MessageLogged $event): bool => $event->level === 'error'))->toHaveCount(1);
    $response = $this->getJson(ApiHttpFixtures::PREFIX.'/report-twice')->assertOk();
    $manual = array_filter($events, static fn (MessageLogged $event): bool =>
        ($event->context['request_id'] ?? null) === $response->headers->get('X-Request-ID'));
    expect(array_filter($manual, static fn (MessageLogged $event): bool =>
        $event->message === 'api.request_failed'))->toBeEmpty();
})->group('api-foundation');
