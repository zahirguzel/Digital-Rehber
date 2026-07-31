<?php
/**
 * Business Model
 */

require_once __DIR__ . '/BaseModel.php';

class Business extends BaseModel {
    protected $table = 'businesses';
    
    /**
     * Get active premium businesses
     */
    public function getPremium() {
        return $this->where('is_premium', 1);
    }
    
    /**
     * Update business rating based on reviews
     */
    public function updateAverageRating($businessId) {
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(id) as total_reviews 
                FROM reviews 
                WHERE business_id = ? AND status = 'approved'";
                
        $result = $this->db->fetchOne($sql, [$businessId]);
        
        if ($result) {
            $this->update($businessId, [
                'average_rating' => $result['avg_rating'] ?? 0,
                'review_count' => $result['total_reviews'] ?? 0
            ]);
        }
    }
    
    /**
     * Record a view/click in analytics
     */
    public function recordAnalytics($businessId, $type = 'views') {
        $date = date('Y-m-d');
        $allowedTypes = ['views', 'clicks', 'qr_scans'];
        
        if (!in_array($type, $allowedTypes)) {
            return false;
        }
        
        // Try to insert or update existing record for today
        $sql = "INSERT INTO business_analytics (business_id, date, {$type}) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE {$type} = {$type} + 1";
                 
        return $this->db->execute($sql, [$businessId, $date]);
    }

    /**
     * Get dashboard analytics summary for a business.
     */
    public function getAnalyticsSummary($businessId) {
        $sql = "SELECT
                    COALESCE(SUM(views), 0) AS total_views,
                    COALESCE(SUM(CASE WHEN date = CURDATE() THEN views ELSE 0 END), 0) AS views_today,
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 6 DAY THEN views ELSE 0 END), 0) AS views_7d,
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 29 DAY THEN views ELSE 0 END), 0) AS views_30d,
                    MAX(CASE WHEN views > 0 THEN date ELSE NULL END) AS last_view_date
                FROM business_analytics
                WHERE business_id = ?";

        $result = $this->db->fetchOne($sql, [$businessId]);

        if (!$result) {
            return [
                'total_views' => 0,
                'views_today' => 0,
                'views_7d' => 0,
                'views_30d' => 0,
                'last_view_date' => null,
            ];
        }

        return [
            'total_views' => (int) ($result['total_views'] ?? 0),
            'views_today' => (int) ($result['views_today'] ?? 0),
            'views_7d' => (int) ($result['views_7d'] ?? 0),
            'views_30d' => (int) ($result['views_30d'] ?? 0),
            'last_view_date' => $result['last_view_date'] ?? null,
        ];
    }
}
