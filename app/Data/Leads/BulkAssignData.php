<?php

declare(strict_types=1);

namespace App\Data\Leads;

use App\Http\Requests\Backoffice\BulkAssignRequest;

final readonly class BulkAssignData
{
    /**
     * @param  array<int, int>  $leadIds
     */
    public function __construct(
        public array $leadIds,
        public int $assignedToId,
    ) {}

    public static function fromRequest(BulkAssignRequest $request): self
    {
        /** @var array<int, int> $leadIds */
        $leadIds = array_map('intval', $request->validated('lead_ids'));

        return new self(
            leadIds: $leadIds,
            assignedToId: (int) $request->validated('assigned_to_id'),
        );
    }
}
