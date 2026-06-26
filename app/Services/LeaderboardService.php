<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgentScorecard;
use App\Models\User;
use Illuminate\Support\Collection;

class LeaderboardService
{
    /**
     * Build the agent leaderboard for a date range, ranked by conversions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(string $from, string $to): array
    {
        $agents = User::query()
            ->role(UserRole::AGENT->value)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $totals = $this->totalsByAgent($from, $to);
        $interestedDays = $this->interestedDaysByAgent($from, $to);

        $rows = [];

        foreach ($agents as $agent) {
            $total = $totals->get($agent->id);

            $totalCalls = (int) ($total->total_calls ?? 0);
            $conversions = (int) ($total->conversions ?? 0);

            $rows[] = [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'days' => (int) ($total->days ?? 0),
                'total_calls' => $totalCalls,
                'connected_calls' => (int) ($total->connected_calls ?? 0),
                'conversions' => $conversions,
                'conversion_rate' => $totalCalls > 0 ? round($conversions / $totalCalls * 100, 1) : 0.0,
                'talk_time_seconds' => (int) ($total->talk_time_seconds ?? 0),
                'interested_days' => (int) ($interestedDays->get($agent->id)->interested_days ?? 0),
                'flagged_days' => (int) ($total->flagged_days ?? 0),
            ];
        }

        // Sort the leaderboard by conversions, biggest first.
        usort($rows, static fn (array $a, array $b): int => $b['conversions'] <=> $a['conversions']);

        return $rows;
    }

    /**
     * One aggregate query: per-agent totals over the window. Replaces the
     * previous per-agent loop (1 + 3N queries) and the PHP-side summing.
     *
     * @return Collection<int, object>
     */
    private function totalsByAgent(string $from, string $to): Collection
    {
        return AgentScorecard::query()
            ->whereBetween('scorecard_date', [$from, $to])
            ->groupBy('user_id')
            ->selectRaw('user_id')
            ->selectRaw('COUNT(*) as days')
            ->selectRaw('SUM(total_calls) as total_calls')
            ->selectRaw('SUM(connected_calls) as connected_calls')
            ->selectRaw('SUM(conversions) as conversions')
            ->selectRaw('SUM(talk_time_seconds) as talk_time_seconds')
            ->selectRaw("SUM(CASE WHEN status = 'flagged' OR review = 1 THEN 1 ELSE 0 END) as flagged_days")
            ->get()
            ->keyBy('user_id');
    }

    /**
     * One aggregate query: days each agent landed at least one "Interested"
     * outcome, read from the normalized child table instead of a LIKE scan.
     *
     * @return Collection<int, object>
     */
    private function interestedDaysByAgent(string $from, string $to): Collection
    {
        return AgentScorecard::query()
            ->join('agent_scorecard_outcomes', 'agent_scorecard_outcomes.agent_scorecard_id', '=', 'agent_scorecards.id')
            ->whereBetween('agent_scorecards.scorecard_date', [$from, $to])
            ->where('agent_scorecard_outcomes.outcome', 'Interested')
            ->groupBy('agent_scorecards.user_id')
            ->selectRaw('agent_scorecards.user_id as user_id')
            ->selectRaw('COUNT(DISTINCT agent_scorecards.id) as interested_days')
            ->get()
            ->keyBy('user_id');
    }

    /**
     * Pick a random "agent of the day" to feature at the top of the board.
     *
     * @return array<string, mixed>|null
     */
    public function featuredAgent(): ?array
    {
        $min = AgentScorecard::query()->min('id');
        $max = AgentScorecard::query()->max('id');

        if ($min === null) {
            return null;
        }

        // Seek to a random id rather than sorting the whole table with RAND().
        $card = AgentScorecard::query()
            ->with('user:id,name')
            ->where('id', '>=', random_int((int) $min, (int) $max))
            ->orderBy('id')
            ->first()
            ?? AgentScorecard::query()->with('user:id,name')->orderBy('id')->first();

        if ($card === null) {
            return null;
        }

        return [
            'name' => $card->user?->name ?? 'Unknown',
            'conversions' => $card->conversions,
        ];
    }
}
