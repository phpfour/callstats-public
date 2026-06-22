<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    public function getTotalCalls(): int
    {
        return CallLog::query()->count();
    }

    public function getAvailableLeads(): int
    {
        return Lead::query()->whereNotNull('assigned_to_id')->count();
    }

    public function getAvailableAgents(): int
    {
        return User::query()->role(UserRole::AGENT->value)->count();
    }

    public function getAverageCallDuration(): string
    {
        $avg = (int) CallLog::query()->avg('duration');

        if ($avg === 0) {
            return '00:00:00';
        }

        return gmdate('H:i:s', $avg);
    }

    public function getCallsToday(): int
    {
        return CallLog::query()->whereDate('called_at', Carbon::today())->count();
    }

    /**
     * Calls logged today, grouped by agent. Includes agents with zero
     * calls today so the chart's X-axis stays stable across reloads.
     *
     * @return array<int, array{agent: string, calls: int}>
     */
    public function getDailyCallVolume(): array
    {
        $agents = User::query()
            ->role(UserRole::AGENT->value)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $callsByAgent = CallLog::query()
            ->join('users', 'call_logs.user_id', '=', 'users.id')
            ->whereDate('call_logs.called_at', Carbon::today())
            ->groupBy('users.name')
            ->selectRaw('users.name as name, COUNT(*) as total')
            ->pluck('total', 'name')
            ->all();

        return array_map(
            static fn (string $agent): array => [
                'agent' => $agent,
                'calls' => (int) ($callsByAgent[$agent] ?? 0),
            ],
            $agents,
        );
    }

    /**
     * Total calls per day for the last 7 days, oldest first.
     *
     * @return array<int, array{label: string, calls: int}>
     */
    public function getWeeklyCallVolume(): array
    {
        $today = Carbon::today();

        $points = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = $today->copy()->subDays($daysAgo);

            $points[] = [
                'label' => $this->labelForDaysAgo($daysAgo),
                'calls' => CallLog::query()
                    ->whereDate('called_at', $date)
                    ->count(),
            ];
        }

        return $points;
    }

    /**
     * Today's calls grouped by outcome, biggest slice first. Null outcomes
     * are excluded — they represent unrecorded results, not a category.
     *
     * @return array<int, array{outcome: string, calls: int}>
     */
    public function getTodayOutcomeBreakdown(): array
    {
        return CallLog::query()
            ->whereDate('called_at', Carbon::today())
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->selectRaw('outcome, COUNT(*) as total')
            ->orderByDesc('total')
            ->get()
            ->map(static fn ($row): array => [
                'outcome' => (string) $row->outcome,
                'calls' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Top agents by call count over the last 7 days (today inclusive),
     * including total talk time formatted as H:i:s.
     *
     * @return array<int, array{name: string, calls: int, talkTime: string}>
     */
    public function getTopAgentsThisWeek(int $limit = 5): array
    {
        $start = Carbon::today()->subDays(6);

        return CallLog::query()
            ->join('users', 'call_logs.user_id', '=', 'users.id')
            ->whereDate('call_logs.called_at', '>=', $start)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_calls')
            ->orderBy('users.name')
            ->limit($limit)
            ->select('users.name')
            ->selectRaw('COUNT(call_logs.id) as total_calls')
            ->selectRaw('COALESCE(SUM(call_logs.duration), 0) as talk_time')
            ->get()
            ->map(static fn ($row): array => [
                'name' => (string) $row->name,
                'calls' => (int) $row->total_calls,
                'talkTime' => gmdate('H:i:s', (int) $row->talk_time),
            ])
            ->all();
    }

    private function labelForDaysAgo(int $daysAgo): string
    {
        return match ($daysAgo) {
            0 => 'Today',
            1 => 'Yesterday',
            default => sprintf('%d days ago', $daysAgo),
        };
    }
}
