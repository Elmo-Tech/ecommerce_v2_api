<?php

declare(strict_types=1);

namespace App\Core\Http;

use Illuminate\Support\Facades\Log;
use Illuminate\Translation\Translator;
use Throwable;

final class ApiMessages
{
    public function __construct(private readonly Translator $translator) {}

    public function get(string $key, string $locale, ?string $requestId = null): string
    {
        $locale = in_array($locale, ApiLocaleResolver::SUPPORTED, true) ? $locale : ApiLocaleResolver::FALLBACK;
        $translationKey = 'api.'.$key;
        $message = $this->translated($translationKey, $locale);
        if ($message !== null) {
            return $message;
        }

        // Missing translations are defects; never expose the raw key or input.
        if (app()->environment('local', 'development', 'testing')) {
            $context = ['locale' => $locale];
            // Only known internal catalog codes are safe diagnostic labels.
            $constant = ApiErrorCodes::class.'::'.$key;
            if ($key === 'INVALID_FIELD'
                || (preg_match('/\A[A-Z][A-Z0-9_]*\z/D', $key) === 1
                    && defined($constant) && constant($constant) === $key)) {
                $context['message_code'] = $key;
            }
            if ($requestId !== null) {
                $context['request_id'] = $requestId;
            }
            try {
                Log::warning('api.translation_missing', $context);
            } catch (Throwable) {
                // A failed technical diagnostic must not change the HTTP result.
            }
        }

        if ($locale !== ApiLocaleResolver::FALLBACK) {
            $fallback = $this->translated($translationKey, ApiLocaleResolver::FALLBACK);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return $locale === 'en'
            ? 'The request could not be completed.'
            : 'تعذر إكمال الطلب.';
    }

    private function translated(string $key, string $locale): ?string
    {
        if (! $this->translator->hasForLocale($key, $locale)) {
            return null;
        }
        $message = $this->translator->get($key, [], $locale, false);

        return is_string($message) && trim($message) !== '' && $message !== $key ? $message : null;
    }
}

