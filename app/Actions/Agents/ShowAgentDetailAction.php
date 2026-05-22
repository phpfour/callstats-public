<?php

declare(strict_types=1);

namespace App\Actions\Agents;

use App\Data\Agents\AgentDetail;
use App\Models\CallLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class ShowAgentDetailAction
{
    private const RECENT_CALLS_LIMIT = 25;

    public function execute(User $agent): AgentDetail
    {
        $agent->load('kpiTarget');

        $today = $this->todayStats($agent->id);
        $thirtyDays = $this->last30DaysStats($agent->id);
        $recentCalls = $this->recentCalls($agent->id);

        return new AgentDetail(
            id: $agent->id,
            name: $agent->name,
            code: $agent->code,
            dailyCallTarget: $agent->kpiTarget?->daily_call_target,
            conversionRateTarget: $agent->kpiTarget?->conversion_rate_target,
            callsToday: $today['calls'],
            conversionsToday: $today['conversions'],
            conversionRateToday: $this->percentage($today['conversions'], $today['calls']),
            callsLast30Days: $thirtyDays['calls'],
            talkTimeLast30Days: $thirtyDays['talk_time'],
            conversionsLast30Days: $thirtyDays['conversions'],
            conversionRateLast30Days: $this->percentage($thirtyDays['conversions'], $thirtyDays['calls']),
            avgDurationLast30Days: $thirtyDays['avg_duration'],
            recentCalls: $recentCalls,
        );
    }

    /**
     * @return array{calls: int, conversions: int}
     */
    private function todayStats(int $userId): array
    {
        $row = CallLog::query()
            ->where('user_id', $userId)
            ->whereDate('called_at', Carbon::today())
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw($this->conversionCaseSql().' as conversions')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'conversions' => (int) ($row->conversions ?? 0),
        ];
    }

    /**
     * @return array{calls: int, talk_time: int, conversions: int, avg_duration: int}
     */
    private function last30DaysStats(int $userId): array
    {
        $start = Carbon::today()->subDays(29);

        $row = CallLog::query()
            ->where('user_id', $userId)
            ->whereDate('called_at', '>=', $start)
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(duration), 0) as talk_time')
            ->selectRaw('COALESCE(AVG(duration), 0) as avg_duration')
            ->selectRaw($this->conversionCaseSql().' as conversions')
            ->first();

        return [
            'calls' => (int) ($row->calls ?? 0),
            'talk_time' => (int) ($row->talk_time ?? 0),
            'conversions' => (int) ($row->conversions ?? 0),
            'avg_duration' => (int) round((float) ($row->avg_duration ?? 0)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentCalls(int $userId): array
    {
        return CallLog::query()
            ->where('user_id', $userId)
            ->with('lead:id,name,phone_number')
            ->latest('called_at')
            ->limit(self::RECENT_CALLS_LIMIT)
            ->get()
            ->map(static fn (CallLog $call): array => [
                'id' => $call->id,
                'called_at' => $call->called_at?->toIso8601String(),
                'duration' => $call->duration,
                'duration_hms' => $call->duration_hms,
                'outcome' => $call->outcome,
                'notes' => $call->notes,
                'lead' => $call->lead === null ? null : [
                    'id' => $call->lead->id,
                    'name' => $call->lead->name,
                    'phone_number' => $call->lead->phone_number,
                ],
            ])
            ->all();
    }

    private function conversionCaseSql(): string
    {
        /** @var array<int, string> $outcomes */
        $outcomes = config('call.conversion_outcomes', []);

        $list = collect($outcomes)
            ->map(static fn (string $o): string => "'".str_replace("'", "''", $o)."'")
            ->implode(', ');

        return sprintf('SUM(CASE WHEN outcome IN (%s) THEN 1 ELSE 0 END)', $list);
    }

    private function percentage(int $part, int $whole): ?int
    {
        if ($whole === 0) {
            return null;
        }

        return (int) round(($part / $whole) * 100);
    }
}
