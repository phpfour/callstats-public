<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'lead_id' => 'required|exists:leads,id',
            'call_log_id' => 'nullable|exists:call_logs,id',
            'remind_at' => 'required|date_format:Y-m-d H:i:s',
            'notes' => 'nullable|string',
            'type' => 'nullable|string',
        ];
    }
}
