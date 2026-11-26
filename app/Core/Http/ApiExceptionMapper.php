<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WeakMap;

/** Native Laravel exception classification, scoped strictly to the v1 boundary. */
final class ApiExceptionMapper
{
    // Preserve protocol semantics, not the old body's representation headers
    // or arbitrary custom headers that may contain sensitive internal values.
    private const array PROTOCOL_HEADERS = [
        'allow', 'retry-after', 'www-authenticate', 'proxy-authenticate',
        'upgrade', 'content-range', 'accept', 'accept-patch', 'accept-post',
    ];

    public function __construct(
        private readonly ApiLocaleResolver $localeResolver,
        private readonly ApiMessages $messages,
    ) {
    }

    public function render(Throwable $exception, Request $request): ?Response
    {
        if (! ApiRequestContext::matches($request)) {
            return null;
        }

        $status = $this->status($exception);
        if ($status < 400) {
            // Preserve explicit native redirects or other non-error responses.
            return $exception instanceof HttpResponseException ? $exception->getResponse() : null;
        }

        $headers = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getHeaders(),
            $exception instanceof HttpResponseException => $exception->getResponse()->headers->all(),
            default => [],
        };
        $response = $this->errorResponse($exception, $request, $status, $headers);
        $request->attributes->set(ApiRequestContext::MAPPED_RESPONSE, $response);

        return $response;
    }

    /**
     * Laravel may use an exception's own render()/Responsable response before
     * render callbacks. Apply the same safe error boundary and correlation at
     * the final native exception response hook, without wrapping ordinary
     * successful responses, redirects or file responses.
     */
    public function finalize(Response $response, Throwable $exception, Request $request): Response
    {
        if (! ApiRequestContext::matches($request)) {
            return $response;
        }

        try {
            $status = $response->getStatusCode();

            if ($status < 400 && ! $this->isNativeControlResponse($exception, $response)) {
                // A custom renderer is not authority to turn an unexpected
                // exception into a successful response or arbitrary redirect.
                $status = $this->status($exception);
                $response = $this->errorResponse($exception, $request, $status >= 400 ? $status : 500, []);
            } elseif ($status >= 400
                && $request->attributes->get(ApiRequestContext::MAPPED_RESPONSE) !== $response) {
                $response = $this->errorResponse(
                    $exception,
                    $request,
                    $status,
                    $response->headers->all(),
                );
            }

            $response->headers->set('X-Request-ID', ApiRequestContext::id($request));
            $this->logRenderedFailure($exception, $request, $response->getStatusCode());

            return $response;
        } finally {
            $request->attributes->remove(ApiRequestContext::MAPPED_RESPONSE);
            $request->attributes->remove(ApiRequestContext::ESCAPED_FAILURE);
            if (! ApiRequestContext::active($request)) {
                // An outer pipeline can report after middleware cleanup.
                $request->attributes->remove(ApiRequestContext::REPORTED);
            }
        }
    }

    private function isNativeControlResponse(Throwable $exception, Response $response): bool
    {
        return $exception instanceof HttpResponseException
            || ($exception instanceof HttpExceptionInterface
                && $exception->getStatusCode() === $response->getStatusCode()
                && $response->isRedirection());
    }

    /**
     * Report the exception classification, not an HTTP status we have not
     * rendered yet. Manual report() calls need not terminate the request.
     */
    public function reportSafely(Throwable $exception, Request $request): void
    {
        $status = $this->status($exception);
        if ($status < 500) {
            return;
        }

        $reported = $request->attributes->get(ApiRequestContext::REPORTED);
        if (! $reported instanceof WeakMap) {
            $reported = new WeakMap();
            $request->attributes->set(ApiRequestContext::REPORTED, $reported);
        }
        if (isset($reported[$exception])) {
            return;
        }

        try {
            Log::error('api.exception_reported', [
                ...$this->safeDiagnosticContext($exception, $request),
                'exception_status' => $status,
                'exception_error_code' => $this->code($status),
            ]);
            $reported[$exception] = true;
        } catch (Throwable) {
            // A failed diagnostic must not change the HTTP outcome.
        }
    }

    /** @param array<string, string|list<string>> $headers */
    private function errorResponse(Throwable $exception, Request $request, int $status, array $headers): JsonResponse
    {
        if ($status < 400 || $status > 599) {
            $status = HttpStatusCode::InternalServerError->value;
        }
        $code = $this->code($status);
        $id = ApiRequestContext::id($request);
        $locale = ApiRequestContext::locale($request, $this->localeResolver);
        $errors = [];

        if ($exception instanceof ValidationException
            && $status === HttpStatusCode::UnprocessableContent->value
            && $exception->validator instanceof ApiFieldValidator) {
            // Native rule metadata distinguishes declared fields from keys
            // expanded out of untrusted data. Unknown validator provenance
            // safely yields the generic 422, never an arbitrary error bag.
            $fieldMessage = $this->messages->get('INVALID_FIELD', $locale, $id);
            foreach ($exception->validator->safeErrorPaths(array_keys($exception->errors())) as $field) {
                $errors[$field] = [$fieldMessage];
            }
        }

        return ApiResponse::error(
            errorCode: $code,
            message: $this->messages->get($code, $locale, $id),
            status: $status,
            errors: $errors,
            headers: array_filter(
                $headers,
                static fn (string $name): bool => in_array(strtolower($name), self::PROTOCOL_HEADERS, true),
                ARRAY_FILTER_USE_KEY,
            ),
        );
    }

    private function status(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => HttpStatusCode::UnprocessableContent->value,
            $exception instanceof AuthenticationException => HttpStatusCode::Unauthorized->value,
            $exception instanceof AuthorizationException => $exception->status() ?? HttpStatusCode::Forbidden->value,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            default => HttpStatusCode::InternalServerError->value,
        };
    }

    private function code(int $status): string
    {
        return match ($status) {
            400 => ApiErrorCodes::BAD_REQUEST,
            401 => ApiErrorCodes::UNAUTHENTICATED,
            403 => ApiErrorCodes::ACCESS_DENIED,
            404 => ApiErrorCodes::RESOURCE_NOT_FOUND,
            405 => ApiErrorCodes::METHOD_NOT_ALLOWED,
            409 => ApiErrorCodes::RESOURCE_CONFLICT,
            422 => ApiErrorCodes::VALIDATION_FAILED,
            429 => ApiErrorCodes::RATE_LIMIT_EXCEEDED,
            500 => ApiErrorCodes::INTERNAL_ERROR,
            503 => ApiErrorCodes::SERVICE_UNAVAILABLE,
            default => ApiErrorCodes::HTTP_ERROR,
        };
    }

    /**
     * Record the actual sanitized exception response separately from an early
     * report. Only one error-level report is emitted for the same exception.
     */
    private function logRenderedFailure(Throwable $exception, Request $request, int $status): void
    {
        $reported = $request->attributes->get(ApiRequestContext::REPORTED);
        $alreadyReported = $reported instanceof WeakMap && isset($reported[$exception]);
        if ($status < 500 && ! $alreadyReported) {
            return;
        }

        try {
            Log::log($alreadyReported ? 'info' : 'error', 'api.request_failed', [
                ...$this->safeDiagnosticContext($exception, $request),
                'http_status' => $status,
                'error_code' => $this->code($status),
            ]);
        } catch (Throwable) {
            // Do not expose original exception details if logging fails.
        }
    }

    /** @return array{request_id: string, exception_type: string} */
    private function safeDiagnosticContext(Throwable $exception, Request $request): array
    {
        $type = $exception::class;

        return [
            'request_id' => ApiRequestContext::id($request),
            'exception_type' => str_contains($type, '@anonymous') ? 'anonymous_throwable' : $type,
        ];
    }
}
