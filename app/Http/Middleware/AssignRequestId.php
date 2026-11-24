<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ApiLocaleResolver;
use App\Core\Http\ApiRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Early v1-only boundary, including requests that never match a route. */
final class AssignRequestId
{
    public function __construct(private readonly ApiLocaleResolver $localeResolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiRequestContext::matches($request)) {
            return $next($request);
        }

        $id = ApiRequestContext::id($request);
        $locale = ApiRequestContext::locale($request, $this->localeResolver);
        $application = app();
        $translator = $application->make('translator');
        $previousLocale = $application->getLocale();
        $previousTranslatorLocale = $translator->getLocale();
        $request->attributes->set(ApiRequestContext::ACTIVE, true);

        try {
            $application->setLocale($locale);
            $response = $next($request);
            $response->headers->set('X-Request-ID', $id);

            return $response;
        } finally {
            // The ID and selected locale remain on this Request for Laravel's
            // exception finalizer, never in shared mutable application state.
            $application->setLocale($previousLocale);
            $translator->setLocale($previousTranslatorLocale);
            $request->attributes->remove(ApiRequestContext::ACTIVE);
            $request->attributes->remove(ApiRequestContext::REPORTED);
            $request->attributes->remove(ApiRequestContext::MAPPED_RESPONSE);
        }
    }
}

