<?php

declare(strict_types=1);

namespace App\Core\Http;

/** Bounded language negotiation for the two approved interactive API locales. */
final class ApiLocaleResolver
{
    public const string FALLBACK = 'ar';
    public const array SUPPORTED = ['ar', 'en'];

    private const int MAX_BYTES = 4096;
    private const int MAX_RANGES = 32;

    public function resolve(?string $header): string
    {
        if ($header === null || trim($header) === '' || strlen($header) > self::MAX_BYTES) {
            return self::FALLBACK;
        }

        $ranges = explode(',', $header);
        if (count($ranges) > self::MAX_RANGES) {
            return self::FALLBACK;
        }

        $seen = [];
        $explicit = [];
        $wildcard = null;

        foreach ($ranges as $index => $range) {
            // Validate the bounded grammar before interpreting weights. Never
            // use an arbitrary language range as a translation path.
            if (preg_match('/\A[ \t]*(\*|[a-z]{1,8}(?:-[a-z0-9]{1,8})*)(?:[ \t]*;[ \t]*q[ \t]*=[ \t]*(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?))?[ \t]*\z/iD', $range, $matches) !== 1) {
                return self::FALLBACK;
            }

            $name = strtolower($matches[1]);
            // Represent thousandths exactly; no floating-point tie comparisons.
            $weight = $this->weight($matches[2] ?? '1');

            if (array_key_exists($name, $seen)) {
                if ($seen[$name] !== $weight) {
                    return self::FALLBACK;
                }
                continue;
            }
            $seen[$name] = $weight;
            $preference = ['weight' => $weight, 'index' => $index];

            if ($name === '*') {
                $wildcard = $preference;
                continue;
            }

            $language = explode('-', $name, 2)[0];
            if (! in_array($language, self::SUPPORTED, true)) {
                continue;
            }

            // Compatible regional forms map to a supported parent. The best
            // explicit preference wins, retaining first occurrence on ties.
            if (! isset($explicit[$language]) || $weight > $explicit[$language]['weight']) {
                $explicit[$language] = $preference;
            }
        }

        $selected = self::FALLBACK;
        $bestWeight = 0;
        $bestIndex = PHP_INT_MAX;

        foreach (self::SUPPORTED as $language) {
            // An explicit zero-weight range must not be overridden by wildcard.
            $preference = $explicit[$language] ?? $wildcard;
            if ($preference === null || $preference['weight'] === 0) {
                continue;
            }
            if ($preference['weight'] > $bestWeight
                || ($preference['weight'] === $bestWeight && $preference['index'] < $bestIndex)) {
                $selected = $language;
                $bestWeight = $preference['weight'];
                $bestIndex = $preference['index'];
            }
        }

        // The approved system fallback is separate from a positive preference.
        return $selected;
    }

    private function weight(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }
}
