<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Helpers\ApiResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Native Laravel validator; no change to rule execution or authorization.
 * Expose only the declared origin of failed fields at the API boundary.
 */
final class ApiFieldValidator extends Validator
{
    /** @param list<array-key> $fields @return list<string> */
    public function safeErrorPaths(array $fields): array
    {
        $rules = $this->getRulesWithoutPlaceholders();
        $data = $this->getData();
        $paths = [];

        foreach ($fields as $field) {
            if (! is_string($field) || ! array_key_exists($field, $rules)) {
                // Unknown/manual bag keys have no verifiable rule provenance.
                continue;
            }

            $path = $this->safePath($field, $this->getPrimaryAttribute($field), $data);
            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    private function safePath(string $field, string $declared, array $data): ?string
    {
        $actual = explode('.', $field);
        $pattern = explode('.', $declared);
        if (count($actual) !== count($pattern)) {
            return null;
        }

        $trusted = [];
        foreach ($pattern as $index => $part) {
            $segment = $actual[$index];
            if ($part === '*') {
                // A wildcard contributes a wire index only if the actual
                // parent is a native list and the index really belongs to it.
                $parent = $trusted === [] ? $data : Arr::get($data, implode('.', $trusted));
                if (! is_array($parent) || ! array_is_list($parent)
                    || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $segment) !== 1
                    || ! array_key_exists($segment, $parent)) {
                    // Do not expose the associative key. Report the nearest
                    // trusted declared parent, or generic 422 for root '*'.
                    break;
                }
            } elseif ($part !== $segment) {
                return null;
            }

            $trusted[] = $segment;
        }

        $path = implode('.', $trusted);

        return ApiResponse::isWireFieldPath($path) ? $path : null;
    }
}
