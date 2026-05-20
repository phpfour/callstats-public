<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case SUPERVISOR = 'supervisor';
    case AGENT = 'agent';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function (UserRole $role) {
            return [$role->value => $role->label()];
        })->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::SUPERVISOR => 'Supervisor',
            self::AGENT => 'Agent',
        };
    }

    public function admin(): array
    {
        return [
            UserPermission::VIEW_USERS,
            UserPermission::CREATE_USERS,
            UserPermission::UPDATE_USERS,
            UserPermission::DELETE_USERS,
            UserPermission::BLOCK_USERS,
            UserPermission::MANAGE_LEADS,
            UserPermission::CREATE_LEADS,
            UserPermission::EDIT_LEADS,
            UserPermission::DELETE_LEADS,
            UserPermission::ASSIGN_LEADS,
            UserPermission::UPLOAD_LEADS,
            UserPermission::VIEW_REPORTS,
        ];
    }

    public function supervisor(): array
    {
        return [
            UserPermission::MANAGE_LEADS,
            UserPermission::CREATE_LEADS,
            UserPermission::EDIT_LEADS,
            UserPermission::DELETE_LEADS,
            UserPermission::ASSIGN_LEADS,
            UserPermission::UPLOAD_LEADS,
        ];
    }

    public function permissions(): array
    {
        return match ($this) {
            self::ADMIN => $this->admin(),
            self::SUPERVISOR => $this->supervisor(),
            self::AGENT => [],
        };
    }
}
