<?php

namespace Core;

/**
 * CSRF token generation and validation.
 */
class CSRF {
    private const SESSION_KEY = '_csrf_token';

    /** Generate (or return existing) CSRF token for the session. */
    public static function token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Validate a submitted token against the session token. */
    public static function validate(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($token) || empty($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Render a hidden HTML input field with the CSRF token.
     * Usage: <?= CSRF::field() ?>
     */
    public static function field(): string {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Regenerate the CSRF token (call after successful form submission). */
    public static function regenerate(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
