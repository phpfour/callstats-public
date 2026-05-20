<?php

declare(strict_types=1);

namespace App\Data\Reports;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final readonly class ReportDateRange
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $from = $request->date('from') ?? Carbon::today();
        $to = $request->date('to') ?? Carbon::today();

        return new self(
            from: $from->startOfDay(),
            to: $to->endOfDay(),
        );
    }

    /** @return array{from: string, to: string} */
    public function toIsoArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }
}
