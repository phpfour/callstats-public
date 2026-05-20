<?php

declare(strict_types=1);

namespace App\Data\CallLogs;

use App\Http\Requests\Api\StoreCallLogRequest;

final readonly class StoreCallLogData
{
    public function __construct(
        public int $leadId,
        public string $calledAt,
        public ?int $duration = null,
        public ?string $notes = null,
        public ?string $outcome = null,
        public ?string $callbackAt = null,
    ) {}

    public static function fromRequest(StoreCallLogRequest $request): self
    {
        return new self(
            leadId: (int) $request->validated('lead_id'),
            calledAt: (string) $request->validated('called_at'),
            duration: $request->validated('duration') !== null ? (int) $request->validated('duration') : null,
            notes: $request->validated('notes'),
            outcome: $request->validated('outcome'),
            callbackAt: $request->validated('callback_at'),
        );
    }
}
