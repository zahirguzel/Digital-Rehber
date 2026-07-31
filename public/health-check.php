<?php
/**
 * Health Check Endpoint
 * System monitoring and diagnostics
 */

require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'checks' => []
];

// 1. Database check
try {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT 1 as test");
    $health['checks']['database'] = [
        'status' => 'ok',
        'queries' => $db->getQueryCount(),
        'query_time' => $db->getTotalQueryTime() . 'ms'
    ];
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => Environment::isDebug() ? $e->getMessage() : 'Database connection failed'
    ];
}

// 2. Cache check
try {
    Cache::set('health_check', 'ok', 10);
    $result = Cache::get('health_check');
    $stats = Cache::stats();

    $health['checks']['cache'] = [
        'status' => $result === 'ok' ? 'ok' : 'error',
        'redis' => $stats['redis'] ? 'connected' : 'disconnected',
        'apcu' => $stats['apcu'] ? 'enabled' : 'disabled',
        'file_cache_count' => $stats['file_count']
    ];
} catch (Exception $e) {
    $health['checks']['cache'] = [
        'status' => 'warning',
        'message' => 'Cache unavailable'
    ];
}

// 3. Disk space check
$disk_free = @disk_free_space('/');
$disk_total = @disk_total_space('/');

if ($disk_free && $disk_total) {
    $disk_usage = (1 - $disk_free / $disk_total) * 100;

    if ($disk_usage > 90) {
        $health['status'] = 'warning';
        $health['checks']['disk'] = [
            'status' => 'warning',
            'usage' => round($disk_usage, 2) . '%',
            'free' => round($disk_free / 1024 / 1024 / 1024, 2) . ' GB'
        ];
    } else {
        $health['checks']['disk'] = [
            'status' => 'ok',
            'usage' => round($disk_usage, 2) . '%',
            'free' => round($disk_free / 1024 / 1024 / 1024, 2) . ' GB'
        ];
    }
}

// 4. Memory usage
$memory_limit = ini_get('memory_limit');
$memory_usage = memory_get_usage(true);
$health['checks']['memory'] = [
    'status' => 'ok',
    'usage' => round($memory_usage / 1024 / 1024, 2) . ' MB',
    'limit' => $memory_limit
];

// 5. PHP version
$health['checks']['php'] = [
    'version' => PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'warning'
];

// 6. Extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
$missing = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}

$health['checks']['extensions'] = [
    'status' => empty($missing) ? 'ok' : 'error',
    'missing' => $missing
];

// 7. Logs directory writable
$logs_writable = is_writable(__DIR__ . '/logs');
$cache_writable = is_writable(__DIR__ . '/cache');
$uploads_writable = is_writable(__DIR__ . '/public/images');

$health['checks']['filesystem'] = [
    'status' => ($logs_writable && $cache_writable && $uploads_writable) ? 'ok' : 'error',
    'logs_writable' => $logs_writable,
    'cache_writable' => $cache_writable,
    'uploads_writable' => $uploads_writable
];

// Set HTTP status code
http_response_code($health['status'] === 'ok' ? 200 : 503);

// Output JSON
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Log access
Logger::access();
