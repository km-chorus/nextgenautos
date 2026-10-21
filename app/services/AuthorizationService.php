<?php

declare(strict_types=1);

/**
 * Authorization foundation: "what is this administrator allowed to do?"
 * (authentication, "who is this?", lives in AuthService).
 *
 * Roles map to an explicit list of permissions. Unknown roles have NO
 * permissions, and a new role never inherits anything by default: it must be
 * added here deliberately. Permission names follow "area.action"
 * ("admin.access" is the permission every protected admin page requires);
 * later specifications add finer ones (for example "vehicles.manage") and
 * grant them to roles here.
 */
final class AuthorizationService
{
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const PERMISSION_ADMIN_ACCESS = 'admin.access';

    /**
     * Explicit rule: the administrator role holds every permission ("*").
     * Future roles (for example a sales role) list only what they may do.
     *
     * @var array<string,list<string>>
     */
    private const ROLE_PERMISSIONS = [
        self::ROLE_ADMINISTRATOR => ['*'],
    ];

    public static function can(string $role, string $permission): bool
    {
        $granted = self::ROLE_PERMISSIONS[$role] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    public static function isKnownRole(string $role): bool
    {
        return isset(self::ROLE_PERMISSIONS[$role]);
    }

    public static function roleLabel(string $role): string
    {
        return $role === self::ROLE_ADMINISTRATOR ? 'Administrator' : 'Staff';
    }
}
