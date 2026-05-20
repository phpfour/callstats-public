<?php

declare(strict_types=1);

namespace App\Data\CallLogs;

use Illuminate\Http\Request;

final readonly class CallLogFilters
{
    public function __construct(
        public ?int $leadId = null,
        public ?int $userId = null,
        public ?string $outcome = null,
        public ?string $calledFrom = null,
        public ?string $calledTo = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            leadId: $request->integer('lead_id') ?: null,
            userId: $request->integer('user_id') ?: null,
            outcome: $request->string('outcome')->trim()->toString() ?: null,
            calledFrom: $request->date('called_from')?->toDateString(),
            calledTo: $request->date('called_to')?->toDateString(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lead_id' => $this->leadId,
            'user_id' => $this->userId,
            'outcome' => $this->outcome,
            'called_from' => $this->calledFrom,
            'called_to' => $this->calledTo,
        ];
    }
}
