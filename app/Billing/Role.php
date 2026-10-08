<?php

namespace App\Billing;

/**
 * What a user is allowed to do to the application itself.
 *
 * Separate from Plan on purpose. Billing decides what someone has bought;
 * this decides what someone may operate. Conflating them is how a cancelled
 * customer ends up still holding the admin panel, or a support engineer
 * accidentally inheriting a paying user's entitlements.
 */
enum Role: string
{
    case User = 'user';
    case Staff = 'staff';
    case Admin = 'admin';

    /**
     * Whether this role grants every other role's access.
     */
    public function includes(self $role): bool
    {
        return match ($this) {
            self::Admin => true,
            self::Staff => $role !== self::Admin,
            self::User => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Staff => 'Staff',
            self::Admin => 'Admin',
        };
    }
}
