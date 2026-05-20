<?php

declare(strict_types=1);

namespace App\Actions\CallLogs;

use App\Data\CallLogs\StoreCallLogData;
use App\Models\CallLog;
use App\Models\Reminder;
use App\Models\User;

class StoreCallLogAction
{
    public function execute(User $user, StoreCallLogData $data): CallLog
    {
        $callLog = new CallLog;
        $callLog->lead_id = $data->leadId;
        $callLog->user_id = $user->id;
        $callLog->called_at = $data->calledAt;
        $callLog->duration = $data->duration;
        $callLog->notes = $data->notes;
        $callLog->outcome = $data->outcome;
        $callLog->save();

        if (! empty($data->callbackAt)) {
            $reminder = new Reminder;
            $reminder->lead_id = $callLog->lead_id;
            $reminder->call_log_id = $callLog->id;
            $reminder->user_id = $user->id;
            $reminder->remind_at = $data->callbackAt;
            $reminder->notes = $data->notes;
            $reminder->type = 'callback';
            $reminder->save();
            $callLog->setRelation('callbackReminder', $reminder);
        }

        return $callLog;
    }
}
