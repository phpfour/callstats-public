<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Data\Reminders\StoreReminderData;
use App\Models\Reminder;
use App\Models\User;

class StoreReminderAction
{
    public function execute(User $user, StoreReminderData $data): Reminder
    {
        $reminder = new Reminder;
        $reminder->lead_id = $data->leadId;
        $reminder->call_log_id = $data->callLogId;
        $reminder->user_id = $user->id;
        $reminder->remind_at = $data->remindAt;
        $reminder->notes = $data->notes;
        $reminder->type = $data->type;
        $reminder->save();

        return $reminder;
    }
}
