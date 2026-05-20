<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $userId === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'code')->ignore($userId),
            ],
            'role' => [
                'required',
                'string',
                Rule::in(array_column(UserRole::cases(), 'value')),
            ],
            'password' => [
                $isCreate ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }
}
