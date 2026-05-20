<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
