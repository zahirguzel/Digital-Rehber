<?php
/**
 * CSRF Middleware
 * Cross-Site Request Forgery protection using double submit cookie pattern
 */

require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Logger.php';

class CSRFMiddleware {
    /**
     * Generate CSRF token
     */
    public static function generate() {
        Session::start();

        if (!Session::has('csrf_token')) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        return Session::get('csrf_token');
    }

    /**
     * Get CSRF token (alias)
     */
    public static function token() {
        return self::generate();
    }

    /**
     * Validate CSRF token
     */
    public static function validate($token = null) {
        Session::start();

        // Get token from request if not provided
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token) {
            Logger::security('CSRF token missing', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                'user_id' => Session::get('user_id'),
                'admin_id' => Session::get('admin_id')
            ]);
            return false;
        }

        $sessionToken = Session::get('csrf_token');

        if (!$sessionToken) {
            Logger::security('CSRF token missing in session');
            return false;
        }

        $valid = hash_equals($sessionToken, $token);

        if (!$valid) {
            Logger::security('CSRF validation failed', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                'user_id' => Session::get('user_id'),
                'admin_id' => Session::get('admin_id')
            ]);
        }

        return $valid;
    }

    /**
     * Protect (middleware function)
     * Call this at the beginning of POST request handlers
     */
    public static function protect() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::validate()) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }

    /**
     * Generate HTML input field
     */
    public static function field() {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get token for AJAX requests
     */
    public static function meta() {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}
