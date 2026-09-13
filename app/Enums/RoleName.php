<?php

namespace App\Enums;

/**
 * The three role names seeded by RolePermissionSeeder.
 *
 * Authorization in this application compares role names directly; the
 * permissions/role_permission tables are seeded but never consulted at
 * runtime. This enum exists so those comparisons stop being raw strings
 * scattered across policies and controllers.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Rank within a single group. Super Admin outranks everything, but note
     * that it is a platform-wide role rather than a group membership - it is
     * granted through Gate::before, not through group_user.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 3,
            self::Admin => 2,
            self::Member => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Resolve a role name that may be null (a user with no membership in the
     * group) or unrecognised, without throwing.
     */
    public static function tryFromName(?string $name): ?self
    {
        return $name === null ? null : self::tryFrom($name);
    }
}
