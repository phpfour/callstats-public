<?php

declare(strict_types=1);

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;

it('assigns the admin role and inherits its seeded permissions', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasRole(UserRole::ADMIN->value))->toBeTrue()
        ->and($user->hasPermissionTo(UserPermission::MANAGE_LEADS->value))->toBeTrue()
        ->and($user->hasPermissionTo(UserPermission::VIEW_REPORTS->value))->toBeTrue();
});

it('rejects permissions not granted to the agent role', function () {
    $user = User::factory()->agent()->create();

    expect($user->hasRole(UserRole::AGENT->value))->toBeTrue()
        ->and($user->hasPermissionTo(UserPermission::DELETE_USERS->value))->toBeFalse()
        ->and($user->hasPermissionTo(UserPermission::MANAGE_LEADS->value))->toBeFalse();
});
