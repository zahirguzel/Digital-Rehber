<?php
class CreateBusinessHoursTable {
    public function up() {
        return "CREATE TABLE business_hours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            day_of_week TINYINT NOT NULL COMMENT '1=Monday, 7=Sunday',
            open_time TIME NULL,
            close_time TIME NULL,
            is_closed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_business_day (business_id, day_of_week),
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function down() {
        return "DROP TABLE IF EXISTS business_hours";
    }
}