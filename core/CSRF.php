<?php
/**
 * Cross-Site Request Forgery (CSRF) Protection
 * Flood Risk Prediction & Relief Camp Locator
 */

class CSRF {
    /**
     * Start session if needed and return CSRF token
     */
    public static function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Generate hidden input HTML element for forms
     */
    public static function inputField(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate an incoming token against the stored session token
     */
    public static function validate(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Verify token from POST or X-CSRF-Token header, or abort request
     */
    public static function verifyOrDie(): void {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!self::validate($token)) {
            if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                require_once __DIR__ . '/Response.php';
                Response::error('Invalid or expired CSRF security token.', 403);
            } else {
                http_response_code(403);
                die('Security Error: Invalid or expired CSRF security token. Please refresh the page and try again.');
            }
        }
    }
}
