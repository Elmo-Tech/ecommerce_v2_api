<?php

declare(strict_types=1);

use App\Core\Http\ApiExceptionMapper;
use App\Core\Http\ApiRequestContext;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Native v1 seam; no public business, web, debug or health routes.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Run before route matching and the remaining global middleware.
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Retain the preexisting JSON negotiation outside the new v1 boundary.
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api', 'api/*') || $request->expectsJson(),
        );

        // Laravel otherwise reports unexpected exception messages/objects
        // before rendering. Replace that reporting only during an active API
        // attempt; never suppress unrelated web, CLI or later worker reports.
        $exceptions->dontReportWhen(static function (Throwable $exception): bool {
            if (! app()->bound('request')) {
                return false;
            }
            $request = app('request');
            if (! $request instanceof Request || ! ApiRequestContext::active($request)) {
                return false;
            }
            app(ApiExceptionMapper::class)->reportSafely($exception, $request);

            return true;
        });

        $exceptions->render(
            static fn (Throwable $exception, Request $request): ?Response =>
                app(ApiExceptionMapper::class)->render($exception, $request),
        );
        $exceptions->respond(
            static fn (Response $response, Throwable $exception, Request $request): Response =>
                app(ApiExceptionMapper::class)->finalize($response, $exception, $request),
        );
    })->create();

