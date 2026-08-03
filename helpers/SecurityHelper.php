<?php
/**
 * SecurityHelper
 * Security utilities: XSS protection, sanitization, password hashing, file upload validation
 */

class SecurityHelper {
    /**
     * Escape output for HTML (prevent XSS)
     */
    public static function escape($data, $encoding = 'UTF-8') {
        if (is_array($data)) {
            return array_map([self::class, 'escape'], $data);
        }
        if (!is_string($encoding) || is_numeric($encoding)) {
            $encoding = 'UTF-8';
        }
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, $encoding);
        }
        return $data;
    }

    /**
     * Alias for escape
     */
    public static function e($data) {
        return self::escape($data);
    }

    /**
     * Sanitize input
     */
    public static function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'string':
                return filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'int':
            case 'integer':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                // Requires HTMLPurifier (composer)
                if (class_exists('HTMLPurifier')) {
                    $config = HTMLPurifier_Config::createDefault();
                    $purifier = new HTMLPurifier($config);
                    return $purifier->purify($data);
                }
                return strip_tags($data);
            default:
                return strip_tags($data);
        }
    }

    /**
     * Hash password (bcrypt)
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Generate random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Validate file upload
     */
    public static function validateUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'], $maxSize = 5242880) {
        // Check if file exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large (max ' . round($maxSize / 1024 / 1024, 1) . 'MB)');
        }

        // Check MIME type (real content, not extension)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            Logger::security('Invalid file upload attempt', [
                'mime' => $mime,
                'allowed' => $allowedTypes,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            throw new Exception('Invalid file type');
        }

        // For images, double-check with getimagesize
        if (strpos($mime, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Not a valid image file');
            }
        }

        return true;
    }

    /**
     * Generate secure filename
     */
    public static function generateFilename($originalName, $prefix = '') {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension));

        if ($prefix) {
            $prefix .= '_';
        }

        return $prefix . uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        // Remove any path components
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Check if IP is in range (for IP blocking)
     */
    public static function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $mask) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);

        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * Get client IP (with proxy support)
     */
    public static function getClientIP() {
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
     * Clean string for use in URLs (slug)
     */
    public static function slug($text) {
        // Turkish character replacements
        $turkish = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $english = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
        $text = str_replace($turkish, $english, $text);

        // Convert to lowercase
        $text = strtolower($text);

        // Replace spaces and special characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        return $text;
    }

    /**
     * Constant-time string comparison (prevent timing attacks)
     */
    public static function timingSafeEquals($a, $b) {
        return hash_equals($a, $b);
    }

    /**
     * Normalize internal redirect targets and reject external destinations.
     */
    public static function normalizeLocalRedirect($redirect, $default = 'profil.php') {
        if (!is_string($redirect)) {
            return $default;
        }

        $redirect = trim(str_replace(["\r", "\n"], '', $redirect));
        if ($redirect === '') {
            return $default;
        }

        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $redirect) || strpos($redirect, '//') === 0) {
            return $default;
        }

        $parts = parse_url($redirect);
        if ($parts === false) {
            return $default;
        }

        if (isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return $default;
        }

        $path = str_replace('\\', '/', $parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';

        if ($path === '' && $query === '') {
            return $default;
        }

        if ($path !== '' && $path[0] !== '/') {
            $path = ltrim($path, './');
        }

        return ($path !== '' ? $path : $default) . $query;
    }

    /**
     * Merkezi Şifre Güvenlik Standardı Kontrolü
     * Kural: En az 8 karakter, en az 1 büyük harf, en az 1 küçük harf ve en az 1 rakam içermelidir.
     * @param string $password
     * @return bool
     */
    public static function validatePasswordStrength($password) {
        if (!is_string($password) || mb_strlen($password, 'UTF-8') < 8) {
            return false;
        }
        // En az 1 büyük harf (Türkçe karakterler dahil)
        if (!preg_match('/[A-ZÇĞİÖŞÜ]/u', $password)) {
            return false;
        }
        // En az 1 küçük harf (Türkçe karakterler dahil)
        if (!preg_match('/[a-zçğıöşü]/u', $password)) {
            return false;
        }
        // En az 1 rakam
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }

    /**
     * Şifre standardı karşılanmadığında gösterilecek standart Türkçe hata mesajı
     * @return string
     */
    public static function getPasswordStrengthMessage() {
        return 'Şifreniz en az 8 karakter uzunluğunda olmalı; en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.';
    }
}

