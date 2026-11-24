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
    ) {}

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

        if ($response->getStatusCode() >= 400
            && $request->attributes->get(ApiRequestContext::MAPPED_RESPONSE) !== $response) {
            $response = $this->errorResponse(
                $exception,
                $request,
                $response->getStatusCode(),
                $response->headers->all(),
            );
        }
        $request->attributes->remove(ApiRequestContext::MAPPED_RESPONSE);
        $response->headers->set('X-Request-ID', ApiRequestContext::id($request));

        return $response;
    }

    /**
     * Called only by the request-scoped native reporting hook. Never send the
     * exception object/message, SQL, request body, cookies or input to logs.
     */
    public function reportSafely(Throwable $exception, Request $request): void
    {
        $status = $this->status($exception);
        $this->logFailure($exception, $request, $status, $this->code($status));
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

        if ($exception instanceof ValidationException && $status === HttpStatusCode::UnprocessableContent->value) {
            $fieldMessage = $this->messages->get('INVALID_FIELD', $locale, $id);
            foreach (array_keys($exception->errors()) as $field) {
                // Form Requests own the declared wire paths. Never recase
                // arbitrary input, echo raw validator text, or emit malformed
                // paths/control characters. Safe generic per-field messages
                // suffice until a Feature defines reviewed specific wording.
                if (is_string($field) && strlen($field) <= 256
                    && preg_match('/\A[a-z][a-zA-Z0-9]*(?:\.(?:[a-z][a-zA-Z0-9]*|[0-9]+))*\z/D', $field) === 1) {
                    $errors[$field] = [$fieldMessage];
                }
            }
        }

        if ($status >= 500) {
            $this->logFailure($exception, $request, $status, $code);
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

    private function logFailure(Throwable $exception, Request $request, int $status, string $code): void
    {
        if ($status < 500) {
            return;
        }

        // The same exception can pass through the native report and render
        // hooks. Deduplicate per request, never with a static/global registry.
        $reported = $request->attributes->get(ApiRequestContext::REPORTED, []);
        $identity = spl_object_id($exception);
        if (isset($reported[$identity])) {
            return;
        }
        $reported[$identity] = true;
        $request->attributes->set(ApiRequestContext::REPORTED, $reported);
        $type = $exception::class;

        try {
            Log::error('api.request_failed', [
                'request_id' => ApiRequestContext::id($request),
                'http_status' => $status,
                'error_code' => $code,
                'exception_type' => str_contains($type, '@anonymous') ? 'anonymous_throwable' : $type,
            ]);
        } catch (Throwable) {
            // Technical logging failure is not authority to change the HTTP
            // outcome or disclose the original exception to the caller.
        }
    }
}

