<?php
/**
 * Session Class
 * Secure session management with hijacking protection
 */

require_once __DIR__ . '/../config/environment.php';
Environment::load();

class Session {
    private static $lifetime = 7200; // 2 hours (from .env)
    private static $regenerate_interval = 300; // 5 minutes
    private static $started = false;

    /**
     * Start secure session
     */
    public static function start() {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        // Get settings from environment
        self::$lifetime = (int) Environment::get('SESSION_LIFETIME', 7200);
        $cookieSecure = Environment::get('SESSION_COOKIE_SECURE', 'false') === 'true';

        // Configure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', $cookieSecure ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', self::$lifetime);
        ini_set('session.cookie_lifetime', self::$lifetime);

        // Custom session name (not PHPSESSID)
        session_name('SECURE_SESSION_ID');

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize session if new
        if (!isset($_SESSION['initiated'])) {
            self::initialize();
        }

        // Validate session
        if (!self::validate()) {
            self::destroy();
            return false;
        }

        // Regenerate ID periodically
        self::regenerateId();

        // Update activity timestamp
        $_SESSION['last_activity'] = time();

        self::$started = true;
        return true;
    }

    /**
     * Initialize new session
     */
    private static function initialize() {
        // Olası csrf_token'ı koru
        $csrfToken = $_SESSION['csrf_token'] ?? null;

        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['ip'] = self::getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_regeneration'] = time();
        $_SESSION['last_activity'] = time();

        // CSRF token'ı geri yaz
        if ($csrfToken !== null && !isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $csrfToken;
        }
    }

    /**
     * Validate session (prevent hijacking)
     */
    private static function validate() {
        // Check IP address (strict validation)
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== self::getClientIP()) {
            self::logSecurity('Session hijacking attempt detected - IP mismatch');
            return false;
        }

        // Check User-Agent (basic validation)
        if (isset($_SESSION['user_agent'])) {
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $currentUA) {
                self::logSecurity('Session hijacking attempt detected - User-Agent mismatch');
                return false;
            }
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > self::$lifetime) {
                self::logSecurity('Session timeout');
                return false;
            }
        }

        return true;
    }

    /**
     * Regenerate session ID periodically
     */
    private static function regenerateId() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            return;
        }

        $elapsed = time() - $_SESSION['last_regeneration'];
        if ($elapsed > self::$regenerate_interval) {
            // Kritik token'ları yenileme öncesi koru
            $csrfToken = $_SESSION['csrf_token'] ?? null;

            session_regenerate_id(false); // false: eski oturum dosyasını hemen silme
            $_SESSION['last_regeneration'] = time();

            // CSRF token'ı koru — regenerate sonrası kaybolmaması için geri yaz
            if ($csrfToken !== null) {
                $_SESSION['csrf_token'] = $csrfToken;
            }
        }
    }

    /**
     * Set session variable
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session variable
     */
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session variable
     */
    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Flash message (one-time message)
     */
    public static function setFlash($key, $message) {
        self::start();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Get and remove flash message
     */
    public static function getFlash($key, $default = null) {
        self::start();
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return $default;
    }

    /**
     * Destroy session
     */
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(
                    session_name(),
                    '',
                    time() - 3600,
                    '/',
                    '',
                    Environment::get('SESSION_COOKIE_SECURE', 'false') === 'true',
                    true
                );
            }

            session_destroy();
        }
        self::$started = false;
    }

    /**
     * Get client IP address (supports proxy)
     */
    private static function getClientIP() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Log security event
     */
    private static function logSecurity($message) {
        if (class_exists('Logger')) {
            Logger::security($message, [
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'admin_id' => $_SESSION['admin_id'] ?? null
            ]);
        }
    }

    /**
     * Get all session data (for debugging)
     */
    public static function all() {
        self::start();
        return $_SESSION;
    }
}
