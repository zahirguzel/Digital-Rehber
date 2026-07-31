<?php
class CreateReviewsTable {
    public function up() {
        return "CREATE TABLE reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function down() {
        return "DROP TABLE IF EXISTS reviews";
    }
}