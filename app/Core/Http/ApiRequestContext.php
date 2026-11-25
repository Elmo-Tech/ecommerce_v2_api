<?php

declare(strict_types=1);

namespace App\Core\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;
use WeakReference;

/** Request attributes only; no singleton stores mutable request or identity state. */
final class ApiRequestContext
{
    public const string ID = 'foundation.api.request_id';
    public const string LOCALE = 'foundation.api.locale';
    public const string ACTIVE = 'foundation.api.active';
    public const string REPORTED = 'foundation.api.reported';
    public const string MAPPED_RESPONSE = 'foundation.api.mapped_response';
    public const string ESCAPED_FAILURE = 'foundation.api.boundary_failure';

    public static function matches(Request $request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public static function id(Request $request): string
    {
        $id = $request->attributes->get(self::ID);
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set(self::ID, $id);
        }
        return $id;
    }

    public static function locale(Request $request, ApiLocaleResolver $resolver): string
    {
        $locale = $request->attributes->get(self::LOCALE);
        if (! is_string($locale) || ! in_array($locale, ApiLocaleResolver::SUPPORTED, true)) {
            $locale = $resolver->resolve(implode(',', $request->headers->all('accept-language')));
            $request->attributes->set(self::LOCALE, $locale);
        }
        return $locale;
    }

    public static function active(Request $request): bool
    {
        return self::matches($request) && $request->attributes->get(self::ACTIVE) === true;
    }

    /** An escaping middleware failure is reported after the native pipeline unwinds. */
    public static function canReport(Request $request, Throwable $exception): bool
    {
        if (! self::matches($request)) {
            return false;
        }
        if (self::active($request)) {
            return true;
        }
        $failure = $request->attributes->get(self::ESCAPED_FAILURE);

        return $failure instanceof WeakReference && $failure->get() === $exception;
    }

    private function __construct()
    {
    }
}
