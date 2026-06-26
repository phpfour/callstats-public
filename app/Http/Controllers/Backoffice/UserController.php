<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\ListUsersAction;
use App\Actions\Users\StoreUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Data\Users\StoreUserData;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, ListUsersAction $action): Response
    {
        $search = $request->string('search')->trim()->toString() ?: null;
        $role = $request->string('role')->trim()->toString() ?: null;

        $users = $action->execute(
            search: $search,
            role: $role,
        );

        return Inertia::render('backoffice/users/index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backoffice/users/create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(UserRequest $request, StoreUserAction $action): RedirectResponse
    {
        $action->execute(StoreUserData::fromRequest($request));

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $user->load('kpiTarget');

        return Inertia::render('backoffice/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'code' => $user->code,
                'role' => $user->getRoleNames()->first(),
                'daily_call_target' => $user->kpiTarget?->daily_call_target,
                'conversion_rate_target' => $user->kpiTarget?->conversion_rate_target,
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->execute($user, StoreUserData::fromRequest($request));

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User updated.');
    }

    public function destroy(User $user, DeleteUserAction $action): RedirectResponse
    {
        $action->execute($user);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User deleted.');
    }

    /** @return array<int, array{value: string, label: string}> */
    private function roleOptions(): array
    {
        return array_map(
            static fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );
    }
}
