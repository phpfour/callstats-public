<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Data\Leads\LeadFilters;
use App\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListLeadsAction
{
    private const ALLOWED_SORTS = [
        'name',
        'phone_number',
        'source',
        'study_destination',
        'ielts_score',
        'assigned_at',
        'created_at',
    ];

    public function execute(
        LeadFilters $filters,
        ?string $sort = null,
        string $direction = 'desc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'assigned_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        return Lead::query()
            ->with(['assignedTo:id,name', 'lastCall'])
            ->when($filters->search, function ($query, string $search): void {
                $term = '%'.$search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term);
                });
            })
            ->when($filters->assignedToId, fn ($query, int $id) => $query->where('assigned_to_id', $id))
            ->when($filters->source, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters->assignedFrom, fn ($query, string $from) => $query->whereDate('assigned_at', '>=', $from))
            ->when($filters->assignedTo, fn ($query, string $to) => $query->whereDate('assigned_at', '<=', $to))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }
}
