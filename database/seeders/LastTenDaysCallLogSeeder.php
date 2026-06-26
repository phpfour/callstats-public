<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CallLog;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class LastTenDaysCallLogSeeder extends Seeder
{
    /**
     * Number of days to back-fill, today inclusive.
     */
    private const int DAYS = 10;

    /**
     * Agents who handle the bulk of the calling (user_id).
     *
     * @var list<int>
     */
    private array $coreAgents = [9, 11, 12, 13, 16, 18];

    /**
     * Lower-volume agents. They get smaller daily bars so every agent on the
     * dashboard's daily chart is represented instead of showing empty columns.
     *
     * @var list<int>
     */
    private array $supportAgents = [17, 19, 20];

    /**
     * Outcome weights. "Successful Contact" is intentionally the plurality
     * so the period skews toward successful outcomes.
     *
     * @var array<string, int>
     */
    private array $outcomeWeights = [
        'Successful Contact' => 48,
        'No Answer' => 17,
        'Interested' => 8,
        'Call Back Requested' => 6,
        'Busy' => 5,
        'Not Interested' => 5,
        'Follow-up' => 4,
        'Number Switched Off' => 2,
        'Unreachable' => 2,
        'Cut the Call' => 2,
        'Other' => 1,
    ];

    public function run(): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $windowStart = $today->subDays(self::DAYS - 1);

        // Idempotent: clear any previously seeded rows in this window so the
        // seeder can be re-run without stacking duplicate days.
        CallLog::query()->where('called_at', '>=', $windowStart)->delete();

        $rows = [];

        for ($dayOffset = self::DAYS - 1; $dayOffset >= 0; $dayOffset--) {
            $day = $today->subDays($dayOffset);

            foreach ($this->coreAgents as $agentId) {
                $rows = [...$rows, ...$this->callsForAgent($agentId, $day, fake()->numberBetween(24, 34))];
            }

            foreach ($this->supportAgents as $agentId) {
                $rows = [...$rows, ...$this->callsForAgent($agentId, $day, fake()->numberBetween(5, 12))];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            CallLog::query()->insert($chunk);
        }

        $this->command?->info(sprintf('Seeded %d call logs across the last %d days.', count($rows), self::DAYS));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function callsForAgent(int $agentId, CarbonImmutable $day, int $callCount): array
    {
        $leadIds = Lead::query()
            ->where('assigned_to_id', $agentId)
            ->pluck('id')
            ->all();

        if ($leadIds === []) {
            return [];
        }

        $rows = [];
        for ($i = 0; $i < $callCount; $i++) {
            $outcome = $this->weightedOutcome();
            $calledAt = $day->setTime(
                fake()->numberBetween(9, 18),
                fake()->numberBetween(0, 59),
                fake()->numberBetween(0, 59),
            );

            $rows[] = [
                'lead_id' => fake()->randomElement($leadIds),
                'user_id' => $agentId,
                'called_at' => $calledAt,
                'duration' => $this->durationFor($outcome),
                'notes' => fake()->optional(0.4)->sentence(),
                'outcome' => $outcome,
                'created_at' => $calledAt,
                'updated_at' => $calledAt,
            ];
        }

        return $rows;
    }

    private function weightedOutcome(): string
    {
        $total = array_sum($this->outcomeWeights);
        $roll = fake()->numberBetween(1, $total);

        $cumulative = 0;
        foreach ($this->outcomeWeights as $outcome => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $outcome;
            }
        }

        return array_key_first($this->outcomeWeights);
    }

    /**
     * Durations are deliberately capped so a busy agent's weekly talk time
     * stays under 24h — the dashboard formats it with gmdate('H:i:s'), which
     * wraps past the 24h mark.
     */
    private function durationFor(string $outcome): int
    {
        return match ($outcome) {
            'Successful Contact', 'Interested' => fake()->numberBetween(60, 480),
            'Call Back Requested', 'Follow-up', 'Not Interested' => fake()->numberBetween(30, 180),
            default => fake()->numberBetween(0, 30),
        };
    }
}
