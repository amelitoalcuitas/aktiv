<?php

namespace App\Enums;

enum UserRole: string
{
    case User       = 'user';
    case Owner      = 'owner';
    case SuperAdmin = 'super_admin';

    public function isOwner(): bool
    {
        return $this === self::Owner || $this === self::SuperAdmin;
    }
}
