<?php

namespace App\Modules\FoundationDemo\Contracts;

/**
 * Non-business, deterministic public contract for F0-01 provider validation.
 */
final class FoundationMarker
{
    /**
     * @return array{module: string, source: string}
     */
    public function payload(): array
    {
        return [
            'module' => 'foundation-demo',
            'source' => 'explicit-registry',
        ];
    }
}
