<?php

namespace Core;

/**
 * Authentication helper — thin wrapper around Session.
 */
class Auth {

    /** Return the currently logged-in user array, or null. */
    public static function user(): ?array {
        $user = Application::$app->session->get('user');
        return $user ?: null;
    }

    /** Return true when a user is logged in. */
    public static function check(): bool {
        return (bool) self::user();
    }

    /** Return the UID of the current user, or null. */
    public static function id(): ?string {
        return self::user()['uid'] ?? null;
    }

    /** Return the role of the current user, or null. */
    public static function role(): ?string {
        return self::user()['role'] ?? null;
    }

    /**
     * Check whether the current user has at least the given role.
     * Role hierarchy: user < agent < admin < super_admin < ultra_super_admin
     */
    public static function hasRole(string $role): bool {
        $hierarchy = [
            'user'              => 1,
            'agent'             => 2,
            'admin'             => 3,
            'super_admin'       => 4,
            'ultra_super_admin' => 5,
        ];

        $currentLevel  = $hierarchy[self::role() ?? 'user'] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 999;

        return $currentLevel >= $requiredLevel;
    }

    /** Shorthand checks */
    public static function isAdmin(): bool   { return self::hasRole('admin'); }
    public static function isAgent(): bool   { return self::hasRole('agent'); }
    public static function isSuperAdmin(): bool { return self::hasRole('super_admin'); }

    /**
     * Log in a user: set session, regenerate session ID.
     */
    public static function login(array $user): void {
        // Never store the password hash in the session
        unset($user['password_hash']);
        Application::$app->session->set('user', $user);
        session_regenerate_id(true);
    }

    /** Log out the current user. */
    public static function logout(): void {
        Application::$app->session->remove('user');
        Application::$app->session->destroy();
    }
}
