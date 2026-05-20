<?php

declare(strict_types=1);

namespace App\Data\Leads;

use App\Http\Requests\Backoffice\LeadRequest;

final readonly class StoreLeadData
{
    public function __construct(
        public string $name,
        public string $phoneNumber,
        public ?string $email = null,
        public ?string $studyDestination = null,
        public ?string $source = null,
        public ?string $ieltsScore = null,
        public ?int $assignedToId = null,
    ) {}

    public static function fromRequest(LeadRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            phoneNumber: $request->validated('phone_number'),
            email: $request->validated('email'),
            studyDestination: $request->validated('study_destination'),
            source: $request->validated('source'),
            ieltsScore: $request->validated('ielts_score'),
            assignedToId: $request->validated('assigned_to_id'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone_number' => $this->phoneNumber,
            'email' => $this->email,
            'study_destination' => $this->studyDestination,
            'source' => $this->source,
            'ielts_score' => $this->ieltsScore,
            'assigned_to_id' => $this->assignedToId,
        ];
    }
}
