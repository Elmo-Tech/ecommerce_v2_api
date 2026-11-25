<?php

declare(strict_types=1);

it('contains a structurally reviewable shared OpenAPI 3.1 contract with no invented public endpoints', function (): void {
    $path = dirname(__DIR__, 3).'/specs/002-api-foundation/contracts/openapi.json';
    expect(is_file($path))->toBeTrue();
    $document = json_decode(file_get_contents($path), flags: JSON_THROW_ON_ERROR);
    expect($document->openapi)->toStartWith('3.1.')
        ->and($document->paths)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($document->paths))->toBe([]);
    $schemas = $document->components->schemas;
    expect($schemas->SuccessResponse->required)->toEqualCanonicalizing(['success', 'message', 'data'])
        ->and($schemas->SuccessResponse->properties->success->const)->toBeTrue()
        ->and($schemas->SuccessResponse->properties->meta->type)->toBe('object')
        ->and($schemas->ErrorResponse->properties->success->const)->toBeFalse()
        ->and($schemas->ErrorResponse->properties->errors->type)->toBe('array')
        ->and($schemas->ErrorResponse->properties->errors->maxItems)->toBe(0)
        ->and($schemas->FieldErrors->type)->toBe('object')
        ->and($schemas->FieldErrors->minProperties)->toBe(1)
        ->and($schemas->FieldErrors->propertyNames->maxLength)->toBe(256)
        ->and($schemas->FieldErrors->propertyNames->pattern)->toBeString()
        ->and($schemas->FieldErrors->additionalProperties->items->type)->toBe('string')
        ->and($schemas->ValidationErrorResponse->properties->errors->{'$ref'})->toBe('#/components/schemas/FieldErrors')
        ->and($schemas->MoneyMinorUnits->type)->toBe('string')
        ->and($schemas->BigIntIdentifier->type)->toBe('string')
        ->and($schemas->CurrencyCode->type)->toBe('string')
        ->and($document->components->headers->XRequestId->schema->format)->toBe('uuid');
    $noContent = $document->components->responses->NoContent;
    expect(property_exists($noContent, 'content'))->toBeFalse()
        ->and($noContent->headers->{'X-Request-ID'}->{'$ref'})->toBe('#/components/headers/XRequestId');

    $walk = function (mixed $node) use (&$walk, $document): void {
        if (is_object($node)) {
            if (isset($node->{'$ref'})) {
                $reference = $node->{'$ref'};
                expect($reference)->toStartWith('#/');
                $target = $document;
                foreach (explode('/', substr($reference, 2)) as $segment) {
                    $key = str_replace(['~1', '~0'], ['/', '~'], $segment);
                    expect(is_object($target) && property_exists($target, $key))->toBeTrue();
                    $target = $target->{$key};
                }
            }
            foreach (get_object_vars($node) as $child) {
                $walk($child);
            }
        } elseif (is_array($node)) {
            foreach ($node as $child) {
                $walk($child);
            }
        }
    };
    $walk($document);
})->group('api-foundation');
