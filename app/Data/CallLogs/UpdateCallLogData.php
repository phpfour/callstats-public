<?php

declare(strict_types=1);

namespace App\Data\CallLogs;

use App\Http\Requests\Backoffice\CallLogRequest;

final readonly class UpdateCallLogData
{
    public function __construct(
        public ?string $outcome = null,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(CallLogRequest $request): self
    {
        return new self(
            outcome: $request->validated('outcome'),
            notes: $request->validated('notes'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'notes' => $this->notes,
        ];
    }
}
