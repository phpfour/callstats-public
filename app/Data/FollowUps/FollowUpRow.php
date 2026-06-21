<?php

declare(strict_types=1);

namespace App\Data\FollowUps;

use App\Models\Lead;

final readonly class FollowUpRow
{
    public function __construct(
        public int $leadId,
        public string $name,
        public string $phoneNumber,
        public ?string $assignedAgent,
        public ?string $lastOutcome,
        public ?string $lastCalledAt,
        public ?string $nextReminderAt,
        public ?int $lastCallId,
    ) {}

    public static function fromLead(Lead $lead): self
    {
        return new self(
            leadId: $lead->id,
            name: $lead->name,
            phoneNumber: $lead->phone_number,
            assignedAgent: $lead->assignedTo?->name,
            lastOutcome: $lead->lastCall?->outcome,
            lastCalledAt: $lead->lastCall?->called_at?->toIso8601String(),
            nextReminderAt: $lead->nextReminder?->remind_at?->toIso8601String(),
            lastCallId: $lead->lastCall?->id,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lead_id' => $this->leadId,
            'name' => $this->name,
            'phone_number' => $this->phoneNumber,
            'assigned_agent' => $this->assignedAgent,
            'last_outcome' => $this->lastOutcome,
            'last_called_at' => $this->lastCalledAt,
            'next_reminder_at' => $this->nextReminderAt,
            'last_call_id' => $this->lastCallId,
        ];
    }
}
