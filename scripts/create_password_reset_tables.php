<?php
/**
 * Dijital Rehber - OTP Şifre Sıfırlama ve Siber Güvenlik Tabloları Kurulum Betiği
 * Bu betik, şifre sıfırlama mekanizması (password_reset_codes) ve denetim kayıtları (security_logs)
 * tablolarını veritabanında güvenle oluşturur.
 */

require_once __DIR__ . '/../autoload.php';

try {
    $db = Database::getInstance()->getPDO();

    // 1. users tablosunun varlığını doğrula (Yoksa temel şemasıyla oluştur)
    $sqlUsers = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `avatar_path` VARCHAR(255) DEFAULT NULL,
        `role` VARCHAR(50) NOT NULL DEFAULT 'user',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlUsers);
    echo "[OK] users tablosu kontrol edildi / oluşturuldu.\n";

    // 2. password_reset_codes tablosu (5 dakika geçerli 6 haneli OTP kodları)
    $sqlResetCodes = "
    CREATE TABLE IF NOT EXISTS `password_reset_codes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `user_type` ENUM('user', 'business') NOT NULL DEFAULT 'user',
        `otp_code` VARCHAR(10) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `is_used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_email_code` (`email`, `otp_code`),
        INDEX `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlResetCodes);
    echo "[OK] password_reset_codes tablosu oluşturuldu / güncellendi.\n";

    // 3. security_logs tablosu (Siber güvenlik, OTP denemeleri, IP blokesi ve şifre değişim denetim logları)
    $sqlSecurityLogs = "
    CREATE TABLE IF NOT EXISTS `security_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip_address` VARCHAR(45) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ip_action_time` (`ip_address`, `action`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sqlSecurityLogs);
    echo "[OK] security_logs tablosu oluşturuldu / güncellendi.\n";

    echo "Tüm tablolar başarıyla hazırlandı.\n";

} catch (Exception $e) {
    echo "[HATA] Tablolar oluşturulamadı: " . $e->getMessage() . "\n";
    exit(1);
}
