<?php
class AlterBusinessesAddRatings {
    public function up() {
        return "ALTER TABLE businesses 
                ADD COLUMN average_rating DECIMAL(3,2) DEFAULT 0.00 AFTER is_premium,
                ADD COLUMN review_count INT DEFAULT 0 AFTER average_rating";
    }

    public function down() {
        return "ALTER TABLE businesses 
                DROP COLUMN average_rating,
                DROP COLUMN review_count";
    }
}