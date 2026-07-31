<?php
class CreateBusinessAnalyticsTable {
    public function up() {
        return "CREATE TABLE business_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            date DATE NOT NULL,
            views INT DEFAULT 0,
            clicks INT DEFAULT 0,
            qr_scans INT DEFAULT 0,
            UNIQUE KEY unique_business_date (business_id, date),
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function down() {
        return "DROP TABLE IF EXISTS business_analytics";
    }
}