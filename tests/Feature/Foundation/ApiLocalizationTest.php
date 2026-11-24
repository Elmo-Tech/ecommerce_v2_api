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


it('has complete nonempty critical shared error translations for both supported locales', function (): void {
    $codes = array_values((new ReflectionClass(\App\Core\Http\ApiErrorCodes::class))->getConstants());
    $expected = [...$codes, 'INVALID_FIELD'];
    $translator = $this->app['translator'];
    foreach (['ar', 'en'] as $locale) {
        $catalog = require base_path('lang/'.$locale.'/api.php');
        expect(array_keys($catalog))->toEqualCanonicalizing($expected);
        foreach ($expected as $code) {
            expect($catalog[$code])->toBeString()->not->toBeEmpty()
                ->and(trim($catalog[$code]))->not->toBe('')
                ->and($translator->get('api.'.$code, [], $locale, false))->toBe($catalog[$code]);
        }
    }
})->group('api-foundation');

it('identifies known missing translation codes without logging an arbitrary supplied key', function (): void {
    $events = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$events): void {
        if ($event->message === 'api.translation_missing') {
            $events[] = $event;
        }
    });
    $translator = $this->app['translator'];
    $translator->addLines(['api.BAD_REQUEST' => ''], 'en');
    $messages = new ApiMessages($translator);
    expect($messages->get('BAD_REQUEST', 'en'))->toBe($translator->get('api.BAD_REQUEST', [], 'ar', false));
    $messages->get('fixturePrivateArbitraryKey', 'en');
    expect($events)->toHaveCount(2)
        ->and($events[0]->context['message_code'] ?? null)->toBe('BAD_REQUEST')
        ->and(json_encode($events[1]->context, JSON_THROW_ON_ERROR))->not->toContain('fixturePrivateArbitraryKey');
})->group('api-foundation');
