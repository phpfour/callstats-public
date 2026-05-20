<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteUserAction
{
    public function execute(User $user): void
    {
        if ($this->isLastAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last admin. Promote another user to admin first.',
            ]);
        }

        $user->delete();
    }

    private function isLastAdmin(User $user): bool
    {
        if (! $user->hasRole(UserRole::ADMIN->value)) {
            return false;
        }

        return User::role(UserRole::ADMIN->value)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }
}
