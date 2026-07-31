<?php
/**
 * RateLimitMiddleware
 * Prevent brute force attacks and API abuse
 */

require_once __DIR__ . '/../core/Cache.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

class RateLimitMiddleware {
    /**
     * Check login rate limit (5 attempts per 15 minutes)
     */
    public static function checkLogin($identifier) {
        $key = 'rate_limit:login:' . md5($identifier);
        $attempts = Cache::get($key) ?? 0;

        if ($attempts >= 5) {
            Logger::security('Rate limit exceeded for login', [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'ip' => SecurityHelper::getClientIP()
            ]);
            return false;
        }

        // Increment attempts
        Cache::set($key, $attempts + 1, 900); // 15 minutes
        return true;
    }

    /**
     * Reset login attempts (call after successful login)
     */
    public static function resetLogin($identifier) {
        $key = 'rate_limit:login:' . md5($identifier);
        Cache::delete($key);
    }

    /**
     * Check API rate limit (60 requests per minute)
     */
    public static function checkAPI($limit = 60, $window = 60) {
        $ip = SecurityHelper::getClientIP();
        $key = 'rate_limit:api:' . md5($ip);
        $requests = Cache::get($key) ?? 0;

        if ($requests >= $limit) {
            Logger::security('API rate limit exceeded', [
                'ip' => $ip,
                'requests' => $requests,
                'limit' => $limit
            ]);

            http_response_code(429);
            header('Retry-After: ' . $window);
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . (time() + $window));

            die(json_encode([
                'error' => 'Rate limit exceeded',
                'retry_after' => $window
            ]));
        }

        // Increment requests
        Cache::set($key, $requests + 1, $window);

        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . ($limit - $requests - 1));
        header('X-RateLimit-Reset: ' . (time() + $window));
    }

    /**
     * Check review submission rate limit (10 reviews per hour)
     */
    public static function checkReview($user_id, $limit = 10) {
        $key = 'rate_limit:review:' . $user_id;
        $count = Cache::get($key) ?? 0;

        if ($count >= $limit) {
            return false;
        }

        Cache::set($key, $count + 1, 3600); // 1 hour
        return true;
    }

    /**
     * Check registration rate limit (IP-based)
     */
    public static function checkRegistration($limit = 3) {
        $ip = SecurityHelper::getClientIP();
        $key = 'rate_limit:register:' . md5($ip);
        $attempts = Cache::get($key) ?? 0;

        if ($attempts >= $limit) {
            Logger::security('Registration rate limit exceeded', [
                'ip' => $ip,
                'attempts' => $attempts
            ]);

            http_response_code(429);
            die('Too many registration attempts. Please try again later.');
        }

        Cache::set($key, $attempts + 1, 3600); // 1 hour
    }

    /**
     * Check password reset rate limit
     */
    public static function checkPasswordReset($email, $limit = 3) {
        $key = 'rate_limit:password_reset:' . md5($email);
        $attempts = Cache::get($key) ?? 0;

        if ($attempts >= $limit) {
            Logger::security('Password reset rate limit exceeded', [
                'email' => $email,
                'attempts' => $attempts
            ]);

            return false;
        }

        Cache::set($key, $attempts + 1, 3600); // 1 hour
        return true;
    }

    /**
     * Check contact form rate limit
     */
    public static function checkContactForm($limit = 5) {
        $ip = SecurityHelper::getClientIP();
        $key = 'rate_limit:contact:' . md5($ip);
        $count = Cache::get($key) ?? 0;

        if ($count >= $limit) {
            return false;
        }

        Cache::set($key, $count + 1, 3600); // 1 hour
        return true;
    }

    /**
     * Generic rate limiter
     */
    public static function check($identifier, $limit, $window, $prefix = 'rate_limit') {
        $key = $prefix . ':' . md5($identifier);
        $count = Cache::get($key) ?? 0;

        if ($count >= $limit) {
            return false;
        }

        Cache::set($key, $count + 1, $window);
        return true;
    }
}
