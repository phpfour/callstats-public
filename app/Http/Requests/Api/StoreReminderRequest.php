<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
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
            'call_log_id' => [
                'nullable',
                Rule::exists('call_logs', 'id')->where('user_id', $this->user()->id),
            ],
            'remind_at' => 'required|date_format:Y-m-d H:i:s',
            'notes' => 'nullable|string',
            'type' => 'nullable|string',
        ];
    }
}
