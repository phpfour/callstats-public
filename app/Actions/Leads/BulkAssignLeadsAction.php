<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Data\Leads\BulkAssignData;
use App\Models\Lead;

class BulkAssignLeadsAction
{
    /**
     * Iterate per-lead so the saving hook stamps assigned_at.
     * Mass update via whereIn->update() bypasses model events and
     * would leave assigned_at stale.
     */
    public function execute(BulkAssignData $data): int
    {
        $leads = Lead::query()->whereIn('id', $data->leadIds)->get();

        foreach ($leads as $lead) {
            $lead->assigned_to_id = $data->assignedToId;
            $lead->save();
        }

        return $leads->count();
    }
}
