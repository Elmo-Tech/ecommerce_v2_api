<?php

declare(strict_types=1);

namespace App\Core\Http;

/** Shared HTTP foundation codes only; domain codes belong to their owning Feature. */
final class ApiErrorCodes
{
    public const string BAD_REQUEST = 'BAD_REQUEST';
    public const string UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const string ACCESS_DENIED = 'ACCESS_DENIED';
    public const string RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const string METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    public const string RESOURCE_CONFLICT = 'RESOURCE_CONFLICT';
    public const string VALIDATION_FAILED = 'VALIDATION_FAILED';
    public const string RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    public const string INTERNAL_ERROR = 'INTERNAL_ERROR';
    public const string SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    public const string HTTP_ERROR = 'HTTP_ERROR';

    private function __construct()
    {
    }
}
