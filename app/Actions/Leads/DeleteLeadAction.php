<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Models\Lead;

class DeleteLeadAction
{
    public function execute(Lead $lead): void
    {
        $lead->delete();
    }
}
