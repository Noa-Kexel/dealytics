<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'superadmin';

    /**
     * Human-readable label (French, matching the app's UI language).
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Utilisateur',
            self::Admin => 'Administrateur',
            self::SuperAdmin => 'Super administrateur',
        };
    }

    /**
     * Whether this role grants access to the admin panel.
     */
    public function isAdminTier(): bool
    {
        return $this === self::Admin || $this === self::SuperAdmin;
    }
}
