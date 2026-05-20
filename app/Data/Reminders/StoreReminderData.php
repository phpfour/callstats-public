<?php

declare(strict_types=1);

namespace App\Data\Reminders;

use App\Http\Requests\Api\StoreReminderRequest;

final readonly class StoreReminderData
{
    public function __construct(
        public int $leadId,
        public string $remindAt,
        public ?int $callLogId = null,
        public ?string $notes = null,
        public ?string $type = null,
    ) {}

    public static function fromRequest(StoreReminderRequest $request): self
    {
        return new self(
            leadId: (int) $request->validated('lead_id'),
            remindAt: (string) $request->validated('remind_at'),
            callLogId: $request->validated('call_log_id') !== null ? (int) $request->validated('call_log_id') : null,
            notes: $request->validated('notes'),
            type: $request->validated('type'),
        );
    }
}
