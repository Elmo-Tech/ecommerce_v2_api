<?php

declare(strict_types=1);

use App\Core\Http\ApiMessages;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\ApiHttpFixtures;

beforeEach(fn () => ApiHttpFixtures::register());

it('applies the selected locale inside the real HTTP request and restores previous application and translator locales', function (): void {
    $this->app->setLocale('en');
    $this->app['translator']->setLocale('en');
    foreach ([
        ['ar-EG', 'ar'], ['en-US', 'en'], ['en;q=0.8,ar;q=0.2', 'en'], ['fr', 'ar'],
        ['ar;q=0,*;q=1', 'en'], ['en;q=bad', 'ar'],
    ] as [$header, $locale]) {
        $this->getJson(ApiHttpFixtures::PREFIX.'/locale', ['Accept-Language' => $header])
            ->assertOk()->assertJsonPath('locale', $locale)->assertJsonPath('translatorLocale', $locale);
        expect($this->app->getLocale())->toBe('en')
            ->and($this->app['translator']->getLocale())->toBe('en');
    }
    $ar = $this->getJson('/api/v1/not-registered', ['Accept-Language' => 'ar-EG']);
    $en = $this->getJson('/api/v1/not-registered', ['Accept-Language' => 'en-US']);
    $fallback = $this->getJson('/api/v1/not-registered', ['Accept-Language' => 'fr']);
    foreach ([$ar, $en, $fallback] as $response) {
        $response->assertNotFound()->assertJsonPath('errorCode', 'RESOURCE_NOT_FOUND');
    }
    expect($ar->json('message'))->not->toBe($en->json('message'))
        ->and($fallback->json('message'))->toBe($ar->json('message'));
})->group('api-foundation');

it('falls back to Arabic and then safe human-readable wording without a raw translation key', function (): void {
    expect(class_exists(ApiMessages::class))->toBeTrue();
    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        $events[] = $event;
    });
    $translator = $this->app['translator'];
    $translator->addLines(['api.fixtureArabicOnly' => 'رسالة آمنة'], 'ar');
    $messages = new ApiMessages($translator);
    expect($messages->get('fixtureArabicOnly', 'en'))->toBe('رسالة آمنة');
    $missing = $messages->get('fixtureMissingBoth', 'en');
    expect($missing)->toBeString()->not->toBeEmpty()
        ->not->toContain('fixtureMissingBoth', 'api.');
    expect(array_filter($events, static fn (MessageLogged $event): bool => $event->level === 'warning'))->not->toBeEmpty();
})->group('api-foundation');

