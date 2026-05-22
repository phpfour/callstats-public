<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\StoreUserData;
use App\Enums\UserRole;
use App\Models\AgentKpiTarget;
use App\Models\User;

class SyncAgentKpiTargetAction
{
    public function execute(User $user, StoreUserData $data): void
    {
        if ($data->role !== UserRole::AGENT->value) {
            AgentKpiTarget::query()->where('user_id', $user->id)->delete();

            return;
        }

        AgentKpiTarget::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'daily_call_target' => $data->dailyCallTarget,
                'conversion_rate_target' => $data->conversionRateTarget,
            ],
        );
    }
}
