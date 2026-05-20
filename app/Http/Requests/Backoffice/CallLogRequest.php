<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var array<int, string> $outcomes */
        $outcomes = config('call.outcomes', []);

        return [
            'outcome' => ['nullable', 'string', 'max:255', Rule::in($outcomes)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
