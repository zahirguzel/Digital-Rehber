<?php
/**
 * Logger Class
 * Handles error, security, and access logging with log rotation
 */

require_once __DIR__ . '/../config/environment.php';
Environment::load();

class Logger {
    private static $logDir;
    private static $maxFileSize = 10485760; // 10MB

    /**
     * Initialize logger
     */
    private static function init() {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../logs/';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
    }

    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::write('error.log', 'ERROR', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::write('error.log', 'WARNING', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        if (Environment::get('LOG_LEVEL') === 'debug' || Environment::get('LOG_LEVEL') === 'info') {
            self::write('info.log', 'INFO', $message, $context);
        }
    }

    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        // Add request info to context
        $context = array_merge($context, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        self::write('security.log', 'SECURITY', $message, $context);

        // Send Telegram notification for critical security events
        if (Environment::get('TELEGRAM_ENABLED') === 'true' && class_exists('NotificationService')) {
            $alert = "🚨 Security Alert\n\n" .
                     "Event: $message\n" .
                     "IP: " . ($context['ip'] ?? 'unknown') . "\n" .
                     "URI: " . ($context['uri'] ?? 'unknown') . "\n" .
                     "Time: " . date('Y-m-d H:i:s');

            // NotificationService::sendTelegram($alert);
        }
    }

    /**
     * Log access (HTTP request)
     */
    public static function access() {
        $log = sprintf(
            "[%s] %s %s %s - User: %s - IP: %s - UA: %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            $_SERVER['REQUEST_URI'] ?? '/',
            http_response_code(),
            $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 'guest'),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100)
        );

        self::writeRaw('access.log', $log);
    }

    /**
     * Write log entry
     */
    private static function write($file, $level, $message, $context = []) {
        self::init();

        $log = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        self::writeRaw($file, $log);
    }

    /**
     * Write raw log entry
     */
    private static function writeRaw($file, $log) {
        self::init();

        $filepath = self::$logDir . $file;

        // Write log
        file_put_contents($filepath, $log, FILE_APPEND | LOCK_EX);

        // Check if log rotation needed
        if (file_exists($filepath) && filesize($filepath) > self::$maxFileSize) {
            self::rotateLog($file);
        }
    }

    /**
     * Rotate log file
     */
    private static function rotateLog($file) {
        $filepath = self::$logDir . $file;
        $timestamp = date('Ymd_His');
        $rotatedFile = self::$logDir . $timestamp . '_' . $file;

        rename($filepath, $rotatedFile);

        // Compress rotated log
        if (function_exists('gzopen')) {
            self::compressLog($rotatedFile);
        }

        // Clean old logs (keep last 30 days)
        self::cleanOldLogs();
    }

    /**
     * Compress log file
     */
    private static function compressLog($filepath) {
        $gz = gzopen($filepath . '.gz', 'wb9');
        if ($gz) {
            gzwrite($gz, file_get_contents($filepath));
            gzclose($gz);
            unlink($filepath); // Remove uncompressed file
        }
    }

    /**
     * Clean old log files
     */
    private static function cleanOldLogs() {
        $cutoff = time() - (30 * 86400); // 30 days
        $files = glob(self::$logDir . '*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    /**
     * Get recent logs (for admin dashboard)
     */
    public static function getRecent($file = 'error.log', $lines = 50) {
        self::init();
        $filepath = self::$logDir . $file;

        if (!file_exists($filepath)) {
            return [];
        }

        // Read last N lines
        $file_handle = fopen($filepath, 'r');
        $line_arr = [];

        if ($file_handle) {
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if ($line) {
                    $line_arr[] = $line;
                    if (count($line_arr) > $lines) {
                        array_shift($line_arr);
                    }
                }
            }
            fclose($file_handle);
        }

        return $line_arr;
    }
}

// Register global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't log suppressed errors (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    Logger::error("PHP Error [$errno]", [
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);

    // Display error if debug mode
    if (Environment::isDebug()) {
        echo "<pre>Error: $errstr in $errfile on line $errline</pre>";
        return true;
    }

    return false;
});

// Register global exception handler
set_exception_handler(function($exception) {
    Logger::error('Uncaught Exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    http_response_code(500);

    if (Environment::isDebug()) {
        echo "<pre>" . $exception . "</pre>";
    } else {
        echo "An error occurred. Please contact support.";
    }
});
