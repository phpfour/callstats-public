<?php

declare(strict_types=1);

namespace App\Actions\CallLogs;

use App\Data\CallLogs\CallLogFilters;
use App\Models\CallLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCallLogsAction
{
    private const ALLOWED_SORTS = [
        'called_at',
        'duration',
        'outcome',
        'created_at',
    ];

    public function execute(
        CallLogFilters $filters,
        ?string $sort = null,
        string $direction = 'desc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'called_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        return CallLog::query()
            ->with(['lead:id,name,phone_number', 'user:id,name'])
            ->when($filters->leadId, fn ($query, int $id) => $query->where('lead_id', $id))
            ->when($filters->userId, fn ($query, int $id) => $query->where('user_id', $id))
            ->when($filters->outcome, fn ($query, string $outcome) => $query->where('outcome', $outcome))
            ->when($filters->calledFrom, fn ($query, string $from) => $query->whereDate('called_at', '>=', $from))
            ->when($filters->calledTo, fn ($query, string $to) => $query->whereDate('called_at', '<=', $to))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }
}
