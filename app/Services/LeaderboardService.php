<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgentScorecard;
use App\Models\User;

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
            ->get();

        $rows = [];

        foreach ($agents as $agent) {
            // Pull every daily scorecard for this agent in the window, then
            // tally the totals up in PHP.
            $cards = AgentScorecard::query()
                ->where('agent_name', $agent->name)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->get();

            $days = $cards->count();
            $totalCalls = 0;
            $connected = 0;
            $conversions = 0;
            $talkTime = 0.0;

            foreach ($cards as $card) {
                $totalCalls += $card->total_calls;
                $connected += $card->connected_calls;
                $conversions += $card->conversions;
                $talkTime += $card->talk_time_seconds;
            }

            // How many days this agent landed at least one "Interested" lead.
            $interestedDays = AgentScorecard::query()
                ->where('agent_name', $agent->name)
                ->where('top_outcomes', 'like', '%Interested%')
                ->count();

            // Days that were flagged for supervisor review.
            $flaggedDays = AgentScorecard::query()
                ->where('agent_name', $agent->name)
                ->where('status', 'flagged')
                ->orWhere('raw_payload', 'like', '%"review":true%')
                ->count();

            $rows[] = [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'days' => $days,
                'total_calls' => $totalCalls,
                'connected_calls' => $connected,
                'conversions' => $conversions,
                'conversion_rate' => $totalCalls > 0 ? round($conversions / $totalCalls * 100, 1) : 0.0,
                'talk_time_seconds' => (int) $talkTime,
                'interested_days' => $interestedDays,
                'flagged_days' => $flaggedDays,
            ];
        }

        // Sort the leaderboard by conversions, biggest first.
        usort($rows, static fn (array $a, array $b): int => $b['conversions'] <=> $a['conversions']);

        return $rows;
    }

    /**
     * Pick a random "agent of the day" to feature at the top of the board.
     *
     * @return array<string, mixed>|null
     */
    public function featuredAgent(): ?array
    {
        $card = AgentScorecard::query()
            ->orderByRaw('RAND()')
            ->first();

        if ($card === null) {
            return null;
        }

        return [
            'name' => $card->agent_name,
            'conversions' => $card->conversions,
        ];
    }
}
