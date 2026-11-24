<?php

declare(strict_types=1);

use App\Core\Http\ApiErrorCodes;
use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;

it('defines the approved int-backed HTTP enum and stable shared string codes', function (): void {
    expect(enum_exists(HttpStatusCode::class))->toBeTrue()
        ->and(class_exists(ApiErrorCodes::class))->toBeTrue();
    $enum = new ReflectionEnum(HttpStatusCode::class);
    expect($enum->isBacked())->toBeTrue()
        ->and((string) $enum->getBackingType())->toBe('int');
    expect(array_map(static fn ($case) => $case->value, HttpStatusCode::cases()))
        ->toContain(200, 201, 204, 400, 401, 403, 404, 405, 409, 422, 429, 500, 503);
    foreach ([
        'BAD_REQUEST', 'UNAUTHENTICATED', 'ACCESS_DENIED', 'RESOURCE_NOT_FOUND',
        'METHOD_NOT_ALLOWED', 'RESOURCE_CONFLICT', 'VALIDATION_FAILED',
        'RATE_LIMIT_EXCEEDED', 'INTERNAL_ERROR', 'SERVICE_UNAVAILABLE', 'HTTP_ERROR',
    ] as $code) {
        expect(constant(ApiErrorCodes::class.'::'.$code))->toBe($code);
    }
})->group('api-foundation');

it('returns exact success and created envelopes without invented metadata', function (): void {
    expect(class_exists(ApiResponse::class))->toBeTrue();
    $response = ApiResponse::success(data: ['example' => true], message: 'Done');
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['success' => true, 'message' => 'Done', 'data' => ['example' => true]]);
    $created = ApiResponse::success(
        data: ['id' => '9223372036854775807'],
        message: 'Created',
        status: HttpStatusCode::Created,
        meta: ['hasMore' => false],
    );
    expect($created->getStatusCode())->toBe(201)
        ->and(json_decode($created->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['success' => true, 'message' => 'Created', 'data' => ['id' => '9223372036854775807'], 'meta' => ['hasMore' => false]]);
})->group('api-foundation');

it('emits zero bytes for no-content success', function (): void {
    expect(class_exists(ApiResponse::class))->toBeTrue();
    $response = ApiResponse::noContent();
    expect($response->getStatusCode())->toBe(204)
        ->and($response->getContent())->toBe('');
})->group('api-foundation');

it('distinguishes a generic error array from a field-keyed validation object', function (): void {
    expect(class_exists(ApiResponse::class))->toBeTrue();
    $failure = ApiResponse::error(errorCode: 'RESOURCE_CONFLICT', message: 'Conflict', status: HttpStatusCode::Conflict);
    $body = json_decode($failure->getContent(), flags: JSON_THROW_ON_ERROR);
    expect($failure->getStatusCode())->toBe(409)
        ->and($body->success)->toBeFalse()
        ->and($body->errorCode)->toBe('RESOURCE_CONFLICT')
        ->and($body->errors)->toBe([]);
    $validation = ApiResponse::error(
        errorCode: 'VALIDATION_FAILED', message: 'Review the data',
        status: HttpStatusCode::UnprocessableContent,
        errors: ['items.0.quantity' => ['Invalid quantity']],
    );
    $body = json_decode($validation->getContent(), flags: JSON_THROW_ON_ERROR);
    expect($validation->getStatusCode())->toBe(422)
        ->and($body->errors)->toBeInstanceOf(stdClass::class)
        ->and($body->errors->{'items.0.quantity'})->toBe(['Invalid quantity']);
})->group('api-foundation');

it('rejects contradictory statuses and malformed field error or metadata shapes', function (): void {
    expect(class_exists(ApiResponse::class))->toBeTrue();
    expect(fn () => ApiResponse::success(message: 'Invalid', status: HttpStatusCode::Conflict))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ApiResponse::success(message: 'Invalid', status: HttpStatusCode::NoContent))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ApiResponse::error(errorCode: 'INTERNAL_ERROR', message: 'Invalid', status: HttpStatusCode::Ok))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ApiResponse::error(errorCode: 'VALIDATION_FAILED', message: 'Invalid', status: 422, errors: ['unkeyed']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ApiResponse::success(message: 'Invalid', meta: ['unkeyed']))->toThrow(InvalidArgumentException::class);
})->group('api-foundation');

