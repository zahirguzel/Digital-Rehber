<?php
/**
 * Dijital Rehber - force_password_change Sütunu Ekleme Betiği
 * Hem users (Bireysel) hem de business_users (İşletme) tablolarına
 * force_password_change (TINYINT(1) DEFAULT 0) sütununu ekler.
 */

require_once __DIR__ . '/../autoload.php';

try {
    $db = Database::getInstance()->getPDO();

    // 1. users tablosu kontrol ve ekleme
    $colsUsers = $db->query("SHOW COLUMNS FROM `users` LIKE 'force_password_change'")->fetch();
    if (!$colsUsers) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `force_password_change` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`");
        echo "[OK] 'users' tablosuna force_password_change sütunu eklendi.\n";
    } else {
        echo "[INFO] 'users' tablosunda force_password_change sütunu zaten mevcut.\n";
    }

    // 2. business_users tablosu kontrol ve ekleme
    $colsBizUsers = $db->query("SHOW COLUMNS FROM `business_users` LIKE 'force_password_change'")->fetch();
    if (!$colsBizUsers) {
        $db->exec("ALTER TABLE `business_users` ADD COLUMN `force_password_change` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`");
        echo "[OK] 'business_users' tablosuna force_password_change sütunu eklendi.\n";
    } else {
        echo "[INFO] 'business_users' tablosunda force_password_change sütunu zaten mevcut.\n";
    }

    echo "Tüm kontroller tamamlandı.\n";

} catch (Exception $e) {
    echo "[HATA] Sütun ekleme başarısız: " . $e->getMessage() . "\n";
    exit(1);
}
