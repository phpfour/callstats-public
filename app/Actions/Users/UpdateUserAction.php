<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\StoreUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateUserAction
{
    public function execute(User $user, StoreUserData $data): User
    {
        $this->guardAgainstDemotingLastAdmin($user, $data);

        $attributes = [
            'name' => $data->name,
            'email' => $data->email,
            'code' => $data->code,
        ];

        if ($data->password !== null && $data->password !== '') {
            $attributes['password'] = $data->password;
        }

        $user->update($attributes);
        $user->syncRoles([$data->role]);

        return $user->refresh();
    }

    private function guardAgainstDemotingLastAdmin(User $user, StoreUserData $data): void
    {
        $isCurrentlyAdmin = $user->hasRole(UserRole::ADMIN->value);
        $remainsAdmin = $data->role === UserRole::ADMIN->value;

        if (! $isCurrentlyAdmin || $remainsAdmin) {
            return;
        }

        $remainingAdmins = User::role(UserRole::ADMIN->value)
            ->where('id', '!=', $user->id)
            ->count();

        if ($remainingAdmins === 0) {
            throw ValidationException::withMessages([
                'role' => 'Cannot demote the last admin. Promote another user first.',
            ]);
        }
    }
}
