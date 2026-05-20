<?php

declare(strict_types=1);

namespace App\Enums;

enum UserPermission: string
{
    case VIEW_USERS = 'view users';
    case CREATE_USERS = 'create users';
    case UPDATE_USERS = 'update users';
    case DELETE_USERS = 'delete users';
    case BLOCK_USERS = 'block users';

    case MANAGE_LEADS = 'manage leads';
    case CREATE_LEADS = 'create leads';
    case EDIT_LEADS = 'edit leads';
    case DELETE_LEADS = 'delete leads';
    case ASSIGN_LEADS = 'assign leads';
    case UPLOAD_LEADS = 'upload leads';

    case VIEW_REPORTS = 'view reports';
}
