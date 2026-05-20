<?php

declare(strict_types=1);

namespace App\Data\Leads;

final readonly class ImportLeadsResult
{
    /**
     * @param  array<int, array{row: int, reason: string}>  $skipped
     */
    public function __construct(
        public int $imported,
        public array $skipped,
    ) {}

    /** @return array{imported: int, skipped: array<int, array{row: int, reason: string}>} */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
        ];
    }
}
