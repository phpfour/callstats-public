<?php

declare(strict_types=1);

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds the smecalls roles, permissions, and grants', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(count(UserRole::cases()))
        ->and(Permission::count())->toBe(count(UserPermission::cases()));

    $admin = Role::where('name', UserRole::ADMIN->value)->firstOrFail();
    $supervisor = Role::where('name', UserRole::SUPERVISOR->value)->firstOrFail();
    $agent = Role::where('name', UserRole::AGENT->value)->firstOrFail();

    expect($admin->permissions)->toHaveCount(count(UserRole::ADMIN->permissions()))
        ->and($supervisor->permissions)->toHaveCount(count(UserRole::SUPERVISOR->permissions()))
        ->and($agent->permissions)->toHaveCount(0);

    expect($admin->hasPermissionTo(UserPermission::VIEW_REPORTS->value))->toBeTrue()
        ->and($supervisor->hasPermissionTo(UserPermission::UPLOAD_LEADS->value))->toBeTrue()
        ->and($supervisor->hasPermissionTo(UserPermission::VIEW_REPORTS->value))->toBeFalse();
});

it('is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(count(UserRole::cases()))
        ->and(Permission::count())->toBe(count(UserPermission::cases()));
});
