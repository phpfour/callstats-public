<?php

declare(strict_types=1);

namespace App\Data\Agents;

final readonly class AgentDetail
{
    /**
     * @param  array<int, array<string, mixed>>  $recentCalls
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?int $dailyCallTarget,
        public ?int $conversionRateTarget,
        public int $callsToday,
        public int $conversionsToday,
        public ?int $conversionRateToday,
        public int $callsLast30Days,
        public int $talkTimeLast30Days,
        public int $conversionsLast30Days,
        public ?int $conversionRateLast30Days,
        public int $avgDurationLast30Days,
        public array $recentCalls,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'targets' => [
                'dailyCalls' => $this->dailyCallTarget,
                'conversionRate' => $this->conversionRateTarget,
            ],
            'today' => [
                'calls' => $this->callsToday,
                'conversions' => $this->conversionsToday,
                'conversionRate' => $this->conversionRateToday,
            ],
            'last30Days' => [
                'calls' => $this->callsLast30Days,
                'talkTime' => $this->talkTimeLast30Days,
                'conversions' => $this->conversionsLast30Days,
                'conversionRate' => $this->conversionRateLast30Days,
                'avgDuration' => $this->avgDurationLast30Days,
            ],
            'recentCalls' => $this->recentCalls,
        ];
    }
}
