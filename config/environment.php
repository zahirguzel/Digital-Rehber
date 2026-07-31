<?php
/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class Environment {
    private static $loaded = false;
    private static $vars = [];

    /**
     * Load .env file and define constants
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }

        if ($envFile === null) {
            $envFile = __DIR__ . '/../.env';
        }

        if (!file_exists($envFile)) {
            die('.env file not found. Please copy .env.example to .env and configure it.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes
            $value = trim($value, '"\'');

            // Store in array
            self::$vars[$key] = $value;

            // Define as constant if not already defined
            if (!defined($key)) {
                define($key, $value);
            }

            // Also set in $_ENV and $_SERVER
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        return self::$vars[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Check if running in production
     */
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }

    /**
     * Check if debug mode is enabled
     */
    public static function isDebug() {
        return self::get('APP_DEBUG') === 'true' || self::get('APP_DEBUG') === '1';
    }

    /**
     * Get all environment variables
     */
    public static function all() {
        return self::$vars;
    }
}
