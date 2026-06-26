<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lead_id' => [
                'required',
                Rule::exists('leads', 'id')->where('assigned_to_id', $this->user()->id),
            ],
            'called_at' => 'required|date',
            'duration' => 'nullable|integer',
            'notes' => 'nullable|string',
            'outcome' => ['nullable', 'string', Rule::in(config('call.outcomes'))],
            'callback_at' => 'nullable|date',
        ];
    }
}
