<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Http\ApiErrorCodes;
use App\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

/** Stateless response formatting; the caller owns the actual outcome and data. */
final class ApiResponse
{
    public const int MAX_FIELD_PATH_BYTES = 256;

    /**
     * @param array<array-key, mixed>|null $meta Caller metadata; invalid keys are rejected here.
     */
    public static function success(
        string $message,
        mixed $data = null,
        HttpStatusCode $status = HttpStatusCode::Ok,
        ?array $meta = null,
    ): JsonResponse {
        if (! in_array($status, [HttpStatusCode::Ok, HttpStatusCode::Created], true)) {
            throw new InvalidArgumentException('Use a completed representation status or noContent().');
        }
        self::assertMessage($message);
        $body = ['success' => true, 'message' => $message, 'data' => $data];

        if ($meta !== null && $meta !== []) {
            foreach (array_keys($meta) as $key) {
                if (! is_string($key) || $key === '') {
                    throw new InvalidArgumentException('Response metadata must be an object.');
                }
            }
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status->value);
    }

    public static function noContent(): Response
    {
        return new Response('', HttpStatusCode::NoContent->value);
    }

    /**
     * @param array<array-key, mixed> $errors Candidate field errors; validated here before serialization.
     * @param array<string, string|list<string>> $headers Reviewed protocol headers.
     */
    public static function error(
        string $errorCode,
        string $message,
        HttpStatusCode|int $status,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $statusValue = $status instanceof HttpStatusCode ? $status->value : $status;
        if ($statusValue < 400 || $statusValue > 599) {
            throw new InvalidArgumentException('An error response requires a real HTTP error status.');
        }
        self::assertMessage($message);
        if (preg_match('/\A[A-Z][A-Z0-9_]*\z/D', $errorCode) !== 1) {
            throw new InvalidArgumentException('An error response requires a stable machine-readable code.');
        }
        if ($errorCode === ApiErrorCodes::VALIDATION_FAILED && $statusValue !== HttpStatusCode::UnprocessableContent->value) {
            throw new InvalidArgumentException('VALIDATION_FAILED requires HTTP 422.');
        }
        if ($errors !== [] && $errorCode !== ApiErrorCodes::VALIDATION_FAILED) {
            throw new InvalidArgumentException('Field errors require the shared validation error code.');
        }
        foreach ($errors as $field => $messages) {
            if (! is_string($field) || ! self::isWireFieldPath($field) || ! is_array($messages) || ! array_is_list($messages) || $messages === []) {
                throw new InvalidArgumentException('Validation errors must be keyed by field and contain message lists.');
            }
            foreach ($messages as $fieldMessage) {
                if (! is_string($fieldMessage) || trim($fieldMessage) === '') {
                    throw new InvalidArgumentException('Validation messages must be nonempty strings.');
                }
            }
        }

        $response = new JsonResponse([
            'success' => false,
            'message' => $message,
            'errorCode' => $errorCode,
            'errors' => $errors,
        ], $statusValue, $headers);

        // These describe a newly generated representation, not the old body.
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->remove('Content-Length');
        $response->headers->remove('Content-Encoding');
        $response->headers->remove('Transfer-Encoding');

        return $response;
    }

    /** Application-owned camelCase segments and numeric list indices only. */
    public static function isWireFieldPath(string $field): bool
    {
        return strlen($field) <= self::MAX_FIELD_PATH_BYTES
            && preg_match('/\A[a-z][a-zA-Z0-9]*(?:\.(?:[a-z][a-zA-Z0-9]*|[0-9]+))*\z/D', $field) === 1;
    }

    private static function assertMessage(string $message): void
    {
        if (trim($message) === '') {
            throw new InvalidArgumentException('An API message must not be empty.');
        }
    }

    private function __construct()
    {
    }
}
