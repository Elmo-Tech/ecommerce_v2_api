<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

/** Test-only validation: no authorization or business decision implied. */
final class ApiFixtureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customerName' => ['required', 'string'],
            'items' => ['required', 'array', 'list'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        // Deliberately unsafe synthetic message to verify the shared boundary
        // never exposes arbitrary validation text or confidential input.
        return ['customerName.required' => 'fixture-private-validation-value'];
    }
}
