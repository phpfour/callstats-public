<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsersAction
{
    private const ALLOWED_SORTS = ['name', 'email', 'created_at'];

    public function execute(
        ?string $search = null,
        ?string $role = null,
        ?string $sort = null,
        string $direction = 'asc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $sortColumn = in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with('roles:id,name')
            ->when($search, function ($query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like): void {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->when($role, fn ($query, string $role) => $query->role($role))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }
}
