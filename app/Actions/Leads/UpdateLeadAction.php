<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Data\Leads\StoreLeadData;
use App\Models\Lead;

class UpdateLeadAction
{
    public function execute(Lead $lead, StoreLeadData $data): Lead
    {
        $lead->update($data->toArray());

        return $lead->refresh();
    }
}
