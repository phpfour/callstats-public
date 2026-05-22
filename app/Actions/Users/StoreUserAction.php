<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\StoreUserData;
use App\Models\User;

class StoreUserAction
{
    public function __construct(
        private SyncAgentKpiTargetAction $syncKpiTarget,
    ) {}

    public function execute(StoreUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'code' => $data->code,
            'password' => $data->password,
        ]);

        $user->syncRoles([$data->role]);
        $this->syncKpiTarget->execute($user, $data);

        return $user->fresh();
    }
}
