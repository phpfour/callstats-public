<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach (UserRole::cases() as $userRole) {
            $role = Role::findOrCreate($userRole->value);

            $permissionNames = array_map(
                static fn (UserPermission $permission): string => $permission->value,
                $userRole->permissions(),
            );

            $role->syncPermissions($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
