<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $leadId = $this->route('lead')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('leads', 'phone_number')->ignore($leadId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'study_destination' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'ielts_score' => ['nullable', 'string', 'max:255'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
