<?php
/**
 * Database Class
 * PDO Singleton with Connection Pooling, Query Logging, and Error Handling
 */

require_once __DIR__ . '/../config/environment.php';
Environment::load();

class Database {
    private static $instance = null;
    private $pdo;
    private $queryCount = 0;
    private $totalQueryTime = 0;

    /**
     * Private constructor for Singleton pattern
     */
    private function __construct() {
        $host = Environment::get('DB_HOST', 'localhost');
        $dbname = Environment::get('DB_NAME', 'digitalrehber_db');
        $username = Environment::get('DB_USER', 'root');
        $password = Environment::get('DB_PASS', '');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,  // Gerçek prepared statements
            PDO::ATTR_PERSISTENT => true,          // Connection pooling
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            $this->logError('Database connection failed', $e);

            if (Environment::isDebug()) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }

    /**
     * Prevent cloning of instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a query with parameters
     */
    public function query($sql, $params = []) {
        $this->queryCount++;
        $start = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $duration = microtime(true) - $start;
            $this->totalQueryTime += $duration;

            // Log slow queries
            if ($duration > 1.0) {
                $this->logSlowQuery($sql, $params, $duration);
            }

            return $stmt;
        } catch (PDOException $e) {
            $this->logQueryError($sql, $params, $e);
            throw $e;
        }
    }

    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Insert and return last insert ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Execute update/delete and return affected rows
     */
    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Get query count
     */
    public function getQueryCount() {
        return $this->queryCount;
    }

    /**
     * Get total query time
     */
    public function getTotalQueryTime() {
        return round($this->totalQueryTime * 1000, 2); // milliseconds
    }

    /**
     * Prepare a statement
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    /**
     * Get PDO instance (for special cases)
     */
    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Log slow query
     */
    private function logSlowQuery($sql, $params, $duration) {
        if (class_exists('Logger')) {
            Logger::warning('Slow query detected', [
                'query' => $sql,
                'params' => $params,
                'duration' => round($duration * 1000, 2) . 'ms'
            ]);
        }
    }

    /**
     * Log query error
     */
    private function logQueryError($sql, $params, $exception) {
        if (class_exists('Logger')) {
            Logger::error('Query failed', [
                'query' => $sql,
                'params' => $params,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Log connection error
     */
    private function logError($message, $exception) {
        $logFile = __DIR__ . '/../logs/error.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $log = sprintf(
            "[%s] [ERROR] %s: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            $exception->getMessage()
        );

        file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
    }
}
