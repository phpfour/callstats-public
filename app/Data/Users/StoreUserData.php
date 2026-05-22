<?php

declare(strict_types=1);

namespace App\Data\Users;

use App\Enums\UserRole;
use App\Http\Requests\Backoffice\UserRequest;

final readonly class StoreUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
        public ?string $code = null,
        public ?string $password = null,
        public ?int $dailyCallTarget = null,
        public ?int $conversionRateTarget = null,
    ) {}

    public static function fromRequest(UserRequest $request): self
    {
        $role = $request->validated('role');
        $isAgent = $role === UserRole::AGENT->value;

        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: $role,
            code: $request->validated('code'),
            password: $request->validated('password'),
            dailyCallTarget: $isAgent ? self::toInt($request->validated('daily_call_target')) : null,
            conversionRateTarget: $isAgent ? self::toInt($request->validated('conversion_rate_target')) : null,
        );
    }

    private static function toInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
