<?php
/**
 * Favorite Model
 */

require_once __DIR__ . '/BaseModel.php';

class Favorite extends BaseModel {
    protected $table = 'user_favorites';
    
    /**
     * Get user's favorite businesses
     */
    public function getUserFavorites($userId) {
        $sql = "SELECT b.*, f.created_at as favorited_at 
                FROM {$this->table} f 
                JOIN businesses b ON f.business_id = b.id 
                WHERE f.user_id = ? 
                ORDER BY f.created_at DESC";
                
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Check if a user favorited a specific business
     */
    public function isFavorited($userId, $businessId) {
        $sql = "SELECT COUNT(*) as is_fav FROM {$this->table} WHERE user_id = ? AND business_id = ?";
        $result = $this->db->fetchOne($sql, [$userId, $businessId]);
        return ($result['is_fav'] > 0);
    }
    
    /**
     * Toggle favorite status
     */
    public function toggleFavorite($userId, $businessId) {
        if ($this->isFavorited($userId, $businessId)) {
            // Remove
            $sql = "DELETE FROM {$this->table} WHERE user_id = ? AND business_id = ?";
            $this->db->execute($sql, [$userId, $businessId]);
            return false; // Now unfavorited
        } else {
            // Add
            $this->create([
                'user_id' => $userId,
                'business_id' => $businessId
            ]);
            return true; // Now favorited
        }
    }
}
