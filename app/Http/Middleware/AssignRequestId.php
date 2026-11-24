<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ApiLocaleResolver;
use App\Core\Http\ApiRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakReference;

/** Early v1-only boundary, including requests that never match a route. */
final class AssignRequestId
{
    public function __construct(private readonly ApiLocaleResolver $localeResolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiRequestContext::matches($request)) {
            return $next($request);
        }

        $request->attributes->set(ApiRequestContext::ACTIVE, true);

        try {
            $id = ApiRequestContext::id($request);
            $locale = ApiRequestContext::locale($request, $this->localeResolver);
            $application = app();
            $translator = $application->make('translator');
            $previousLocale = $application->getLocale();
            $previousTranslatorLocale = $translator->getLocale();

            try {
                $application->setLocale($locale);
                $response = $next($request);
                $response->headers->set('X-Request-ID', $id);

                return $response;
            } finally {
                try {
                    // setLocale dispatches LocaleUpdated after updating config
                    // and translator; a listener can throw during restoration.
                    $application->setLocale($previousLocale);
                } finally {
                    // The translator may have had its own prior locale.
                    $translator->setLocale($previousTranslatorLocale);
                }
            }
        } catch (Throwable $exception) {
            // Native Pipeline will report/render after this middleware
            // unwinds. Authorize safe reporting only for this exact failure,
            // not for unrelated work using a stale request binding.
            $request->attributes->set(ApiRequestContext::ESCAPED_FAILURE, WeakReference::create($exception));
            throw $exception;
        } finally {
            $request->attributes->remove(ApiRequestContext::ACTIVE);
            $request->attributes->remove(ApiRequestContext::REPORTED);
            $request->attributes->remove(ApiRequestContext::MAPPED_RESPONSE);
        }
    }
}

