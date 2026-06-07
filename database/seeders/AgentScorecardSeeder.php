<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AgentScorecard;
use App\Models\AgentScorecardOutcome;
use App\Models\CallLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentScorecardSeeder extends Seeder
{
    /**
     * Outcomes that count as a conversion.
     *
     * @var list<string>
     */
    private array $conversionOutcomes;

    /**
     * Outcomes that count as "not connected".
     *
     * @var list<string>
     */
    private array $notConnected = ['Missed', 'No Answer', 'Number Switched Off', 'Unreachable'];

    public function run(): void
    {
        $this->conversionOutcomes = config('call.conversion_outcomes', []);

        $agentLookup = User::query()
            ->role(UserRole::AGENT->value)
            ->get()
            ->keyBy('id');

        if ($agentLookup->isEmpty()) {
            $this->command?->warn('No agent users found — skipping scorecard seeding.');

            return;
        }

        // Idempotent: wipe existing scorecards so re-running doesn't stack rows.
        // The FK cascade clears agent_scorecard_outcomes too.
        AgentScorecard::query()->delete();

        // Per agent, per day, per outcome counts — the raw material for each
        // daily snapshot.
        $aggregates = CallLog::query()
            ->selectRaw('user_id, DATE(called_at) as day, outcome, COUNT(*) as calls, COALESCE(SUM(duration), 0) as talk')
            ->whereNotNull('called_at')
            ->groupBy('user_id', 'day', 'outcome')
            ->get();

        // Group the rows into [user_id][day] buckets.
        $buckets = [];
        foreach ($aggregates as $row) {
            $buckets[$row->user_id][$row->day][] = $row;
        }

        $scorecards = [];
        // Parallel map: "user_id|day" => [outcome => count] for the top outcomes,
        // used to fill the child table once the scorecards have ids.
        $outcomeSets = [];

        foreach ($buckets as $userId => $days) {
            $agent = $agentLookup->get($userId);

            if ($agent === null) {
                continue;
            }

            foreach ($days as $day => $rows) {
                [$scorecard, $topOutcomes] = $this->buildScorecard($agent, (string) $day, $rows);
                $scorecards[] = $scorecard;
                $outcomeSets[$userId.'|'.$day] = $topOutcomes;
            }
        }

        foreach (array_chunk($scorecards, 500) as $chunk) {
            AgentScorecard::query()->insert($chunk);
        }

        $this->seedOutcomes($outcomeSets);

        $this->command?->info(sprintf(
            'Seeded %d daily scorecards across %d agents.',
            count($scorecards),
            $agentLookup->count(),
        ));
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{0: array<string, mixed>, 1: array<string, int>}
     */
    private function buildScorecard(User $agent, string $day, array $rows): array
    {
        $totalCalls = 0;
        $connected = 0;
        $conversions = 0;
        $talk = 0;
        $outcomeCounts = [];

        foreach ($rows as $row) {
            $calls = (int) $row->calls;
            $totalCalls += $calls;
            $talk += (int) $row->talk;

            $outcome = $row->outcome;

            if ($outcome === null) {
                continue;
            }

            $outcomeCounts[$outcome] = ($outcomeCounts[$outcome] ?? 0) + $calls;

            if (! in_array($outcome, $this->notConnected, true)) {
                $connected += $calls;
            }

            if (in_array($outcome, $this->conversionOutcomes, true)) {
                $conversions += $calls;
            }
        }

        arsort($outcomeCounts);
        $topOutcomes = array_slice($outcomeCounts, 0, 3, preserve_keys: true);

        $flagged = $totalCalls >= 10 && $conversions === 0;

        $scorecard = [
            'user_id' => $agent->id,
            'scorecard_date' => $day,
            'status' => $flagged ? 'flagged' : 'final',
            'total_calls' => $totalCalls,
            'connected_calls' => $connected,
            'conversions' => $conversions,
            'talk_time_seconds' => $talk,
            'conversion_rate' => $totalCalls > 0 ? round($conversions / $totalCalls * 100, 2) : 0.0,
            'review' => $flagged,
            'raw_payload' => json_encode([
                'review' => $flagged,
                'connected' => $connected,
            ]),
            'created_at' => $day.' 00:00:00',
            'updated_at' => $day.' 00:00:00',
        ];

        return [$scorecard, $topOutcomes];
    }

    /**
     * Insert the top outcomes for every freshly inserted scorecard.
     *
     * @param  array<string, array<string, int>>  $outcomeSets  "user_id|day" => [outcome => count]
     */
    private function seedOutcomes(array $outcomeSets): void
    {
        $ids = AgentScorecard::query()
            ->get(['id', 'user_id', 'scorecard_date'])
            ->keyBy(fn (AgentScorecard $card): string => $card->user_id.'|'.$card->scorecard_date->format('Y-m-d'));

        $outcomeRows = [];

        foreach ($outcomeSets as $key => $outcomes) {
            $card = $ids->get($key);

            if ($card === null) {
                continue;
            }

            foreach ($outcomes as $outcome => $count) {
                $outcomeRows[] = [
                    'agent_scorecard_id' => $card->id,
                    'outcome' => $outcome,
                    'count' => $count,
                ];
            }
        }

        foreach (array_chunk($outcomeRows, 500) as $chunk) {
            AgentScorecardOutcome::query()->insert($chunk);
        }
    }
}
