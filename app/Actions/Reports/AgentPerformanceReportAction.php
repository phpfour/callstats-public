<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Reports\ReportDateRange;
use App\Models\CallLog;
use Illuminate\Support\Collection;

class AgentPerformanceReportAction
{
    /**
     * Aggregate per-agent call counts and total duration over a date range.
     * Matches smecalls's ReportCrudController shape: missed, not_received,
     * received, total, duration. Only agents with at least one call in the
     * range appear (INNER JOIN semantics).
     *
     * @return Collection<int, object{
     *     user_id: int,
     *     name: string,
     *     missed: int,
     *     not_received: int,
     *     received: int,
     *     total: int,
     *     duration: ?int,
     * }>
     */
    public function execute(ReportDateRange $range): Collection
    {
        $rows = CallLog::query()
            ->join('users', 'call_logs.user_id', '=', 'users.id')
            ->whereBetween('call_logs.called_at', [$range->from, $range->to])
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->select('users.id as user_id', 'users.name')
            ->selectRaw("SUM(CASE WHEN call_logs.outcome = 'Missed' THEN 1 ELSE 0 END) as missed")
            ->selectRaw("SUM(CASE WHEN call_logs.outcome = 'No Answer' THEN 1 ELSE 0 END) as not_received")
            ->selectRaw("SUM(CASE WHEN call_logs.outcome NOT IN ('Missed', 'No Answer') THEN 1 ELSE 0 END) as received")
            ->selectRaw('COUNT(call_logs.id) as total')
            ->selectRaw('SUM(call_logs.duration) as duration')
            ->get();

        // MySQL returns SUM/COUNT as strings; cast to ints so the JSON
        // contract stays clean for the frontend and tests.
        return $rows->map(static function (object $row): object {
            $row->user_id = (int) $row->user_id;
            $row->missed = (int) $row->missed;
            $row->not_received = (int) $row->not_received;
            $row->received = (int) $row->received;
            $row->total = (int) $row->total;
            $row->duration = $row->duration === null ? null : (int) $row->duration;

            return $row;
        });
    }
}
