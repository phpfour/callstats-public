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

    private function labelForDaysAgo(int $daysAgo): string
    {
        return match ($daysAgo) {
            0 => 'Today',
            1 => 'Yesterday',
            default => sprintf('%d days ago', $daysAgo),
        };
    }
}
