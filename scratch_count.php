<?php
require_once __DIR__ . '/autoload.php';
try {
    $pdo = Database::getInstance()->getPDO();
    $count = $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
    echo "Total logs: " . $count . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
