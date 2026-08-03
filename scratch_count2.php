<?php
require_once __DIR__ . '/autoload.php';
try {
    $db = Database::getInstance();
    $sql = "SELECT * FROM admin_logs WHERE 1=1";
    $countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
    
    $stmtCount = $db->query($countSql, []);
    $totalLogs = (int)$stmtCount->fetchColumn();
    echo "totalLogs: " . $totalLogs . "\n";
    echo "totalPages: " . ceil($totalLogs / 30) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
