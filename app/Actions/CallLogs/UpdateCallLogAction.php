<?php

declare(strict_types=1);

namespace App\Actions\CallLogs;

use App\Data\CallLogs\UpdateCallLogData;
use App\Models\CallLog;

class UpdateCallLogAction
{
    public function execute(CallLog $callLog, UpdateCallLogData $data): CallLog
    {
        $callLog->update($data->toArray());

        return $callLog->refresh();
    }
}
