<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Data\Leads\StoreLeadData;
use App\Models\Lead;

class StoreLeadAction
{
    public function execute(StoreLeadData $data): Lead
    {
        return Lead::create($data->toArray());
    }
}
