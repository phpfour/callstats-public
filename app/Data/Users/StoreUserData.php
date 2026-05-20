<?php

declare(strict_types=1);

namespace App\Data\Users;

use App\Http\Requests\Backoffice\UserRequest;

final readonly class StoreUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
        public ?string $code = null,
        public ?string $password = null,
    ) {}

    public static function fromRequest(UserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: $request->validated('role'),
            code: $request->validated('code'),
            password: $request->validated('password'),
        );
    }
}
