<?php
/**
 * Cache Class
 * Multi-level caching: APCu → Redis → File
 */

require_once __DIR__ . '/../config/environment.php';
Environment::load();

class Cache {
    private static $redis = null;
    private static $redisAvailable = false;
    private static $cacheDir;

    /**
     * Initialize cache system
     */
    public static function init() {
        self::$cacheDir = __DIR__ . '/../cache/';

        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }

        // Try to connect to Redis
        if (Environment::get('CACHE_DRIVER') === 'redis' && extension_loaded('redis')) {
            try {
                self::$redis = new Redis();
                $host = Environment::get('REDIS_HOST', '127.0.0.1');
                $port = Environment::get('REDIS_PORT', 6379);

                self::$redis->connect($host, $port, 2); // 2 second timeout
                self::$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                self::$redisAvailable = true;

                if (class_exists('Logger')) {
                    Logger::info('Redis cache connected');
                }
            } catch (Exception $e) {
                self::$redisAvailable = false;
                if (class_exists('Logger')) {
                    Logger::warning('Redis connection failed, using file cache', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get value from cache
     */
    public static function get($key) {
        self::init();

        // Try APCu first (memory cache - fastest)
        if (function_exists('apcu_fetch')) {
            $value = apcu_fetch($key, $success);
            if ($success) {
                return $value;
            }
        }

        // Try Redis second
        if (self::$redisAvailable) {
            try {
                $value = self::$redis->get($key);
                if ($value !== false) {
                    // Store in APCu for next time
                    if (function_exists('apcu_store')) {
                        apcu_store($key, $value, 300);
                    }
                    return $value;
                }
            } catch (Exception $e) {
                // Redis error, fallback to file
            }
        }

        // Fallback to file cache
        return self::fileGet($key);
    }

    /**
     * Set value in cache
     */
    public static function set($key, $value, $ttl = 3600) {
        self::init();

        // Store in APCu
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, min($ttl, 300)); // Max 5 min in memory
        }

        // Store in Redis
        if (self::$redisAvailable) {
            try {
                return self::$redis->setex($key, $ttl, $value);
            } catch (Exception $e) {
                // Redis error, fallback to file
            }
        }

        // Fallback to file cache
        return self::fileSet($key, $value, $ttl);
    }

    /**
     * Delete from cache
     */
    public static function delete($key) {
        self::init();

        // Delete from APCu
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
        }

        // Delete from Redis
        if (self::$redisAvailable) {
            try {
                self::$redis->del($key);
            } catch (Exception $e) {
                // Ignore
            }
        }

        // Delete from file cache
        return self::fileDelete($key);
    }

    /**
     * Clear all cache
     */
    public static function flush() {
        self::init();

        // Clear APCu
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        // Clear Redis
        if (self::$redisAvailable) {
            try {
                self::$redis->flushDB();
            } catch (Exception $e) {
                // Ignore
            }
        }

        // Clear file cache
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Get from file cache
     */
    private static function fileGet($key) {
        $file = self::$cacheDir . md5($key) . '.cache';

        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));

            if (isset($data['expire']) && $data['expire'] > time()) {
                return $data['value'];
            }

            // Expired, delete
            unlink($file);
        }

        return null;
    }

    /**
     * Set to file cache
     */
    private static function fileSet($key, $value, $ttl) {
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = [
            'expire' => time() + $ttl,
            'value' => $value
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Delete from file cache
     */
    private static function fileDelete($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        return file_exists($file) ? unlink($file) : false;
    }

    /**
     * Remember: Get from cache or execute callback and store
     */
    public static function remember($key, $ttl, $callback) {
        $value = self::get($key);

        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Get cache statistics
     */
    public static function stats() {
        $stats = [
            'redis' => self::$redisAvailable,
            'apcu' => function_exists('apcu_cache_info'),
            'file_count' => 0
        ];

        if (self::$redisAvailable) {
            try {
                $info = self::$redis->info();
                $stats['redis_keys'] = self::$redis->dbSize();
                $stats['redis_memory'] = $info['used_memory_human'] ?? 'N/A';
            } catch (Exception $e) {
                // Ignore
            }
        }

        if (function_exists('apcu_cache_info')) {
            $info = apcu_cache_info();
            $stats['apcu_entries'] = $info['num_entries'] ?? 0;
        }

        $files = glob(self::$cacheDir . '*.cache');
        $stats['file_count'] = count($files);

        return $stats;
    }
}
