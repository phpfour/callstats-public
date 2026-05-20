<?php

declare(strict_types=1);

namespace App\Data\Leads;

use Illuminate\Http\Request;

final readonly class LeadFilters
{
    public function __construct(
        public ?string $search = null,
        public ?int $assignedToId = null,
        public ?string $source = null,
        public ?string $assignedFrom = null,
        public ?string $assignedTo = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->trim()->toString() ?: null,
            assignedToId: $request->integer('assigned_to_id') ?: null,
            source: $request->string('source')->trim()->toString() ?: null,
            assignedFrom: $request->date('assigned_from')?->toDateString(),
            assignedTo: $request->date('assigned_to')?->toDateString(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'assigned_to_id' => $this->assignedToId,
            'source' => $this->source,
            'assigned_from' => $this->assignedFrom,
            'assigned_to' => $this->assignedTo,
        ];
    }
}
