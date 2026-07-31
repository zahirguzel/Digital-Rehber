<?php
/**
 * Review Model
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Business.php';

class Review extends BaseModel {
    protected $table = 'reviews';

    private const SORT_NEWEST = 'newest';
    private const SORT_OLDEST = 'oldest';
    private const SORT_RATING_DESC = 'rating_desc';
    private const SORT_RATING_ASC = 'rating_asc';
    
    /**
     * Get approved reviews for a business
     */
    public function getForBusiness($businessId) {
        return $this->getBusinessReviews($businessId, ['status' => 'approved'], 1, 1000);
    }

    public function getHomepageReviews(array $filters = [], $page = 1, $perPage = 6) {
        $ratingFilter = $this->normalizeRatingFilter($filters['rating'] ?? null);
        $sort = $this->normalizeSort($filters['sort'] ?? self::SORT_NEWEST);
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $where = ["r.status = 'approved'"];
        $params = [];

        if ($ratingFilter !== null) {
            $where[] = 'r.rating = ?';
            $params[] = $ratingFilter;
        }

        $sql = "SELECT r.*, u.name as user_name, u.avatar_path,
                       b.name as business_name, b.slug as business_slug, b.theme_color as business_theme_color
                FROM {$this->table} r
                JOIN users u ON r.user_id = u.id
                JOIN businesses b ON r.business_id = b.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildSortSql($sort) . "
                LIMIT {$offset}, {$perPage}";

        return $this->db->fetchAll($sql, $params);
    }

    public function countHomepageReviews(array $filters = []) {
        $ratingFilter = $this->normalizeRatingFilter($filters['rating'] ?? null);
        $where = ["status = 'approved'"];
        $params = [];

        if ($ratingFilter !== null) {
            $where[] = 'rating = ?';
            $params[] = $ratingFilter;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM {$this->table} WHERE " . implode(' AND ', $where),
            $params
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function getBusinessReviews($businessId, array $filters = [], $page = 1, $perPage = 10) {
        $sort = $this->normalizeSort($filters['sort'] ?? self::SORT_NEWEST);
        $ratingFilter = $this->normalizeRatingFilter($filters['rating'] ?? null);
        $statusFilter = $this->normalizeStatusFilter($filters['status'] ?? null);
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $where = ['r.business_id = ?'];
        $params = [(int) $businessId];

        if ($statusFilter !== null) {
            $where[] = 'r.status = ?';
            $params[] = $statusFilter;
        }

        if ($ratingFilter !== null) {
            $where[] = 'r.rating = ?';
            $params[] = $ratingFilter;
        }

        $sql = "SELECT r.*, u.name as user_name, u.email as user_email, u.avatar_path
                FROM {$this->table} r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . $this->buildSortSql($sort) . "
                LIMIT {$offset}, {$perPage}";

        return $this->db->fetchAll($sql, $params);
    }

    public function countBusinessReviews($businessId, array $filters = []) {
        $ratingFilter = $this->normalizeRatingFilter($filters['rating'] ?? null);
        $statusFilter = $this->normalizeStatusFilter($filters['status'] ?? null);
        $where = ['business_id = ?'];
        $params = [(int) $businessId];

        if ($statusFilter !== null) {
            $where[] = 'status = ?';
            $params[] = $statusFilter;
        }

        if ($ratingFilter !== null) {
            $where[] = 'rating = ?';
            $params[] = $ratingFilter;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM {$this->table} WHERE " . implode(' AND ', $where),
            $params
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function getBusinessReviewSummary($businessId) {
        $summary = [
            'all' => 0,
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
        ];

        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) as cnt
             FROM {$this->table}
             WHERE business_id = ?
             GROUP BY status",
            [(int) $businessId]
        );

        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if (isset($summary[$status])) {
                $summary[$status] = (int) $row['cnt'];
                $summary['all'] += (int) $row['cnt'];
            }
        }

        return $summary;
    }
    
    /**
     * Add a new review
     */
    public function addReview($businessId, $userId, $rating, $comment) {
        $id = $this->create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'approved' // Could be pending if moderation is enabled
        ]);
        
        // Update average rating
        $business = new Business();
        $business->updateAverageRating($businessId);
        
        return $id;
    }

    private function normalizeSort($sort) {
        return in_array($sort, [self::SORT_NEWEST, self::SORT_OLDEST, self::SORT_RATING_DESC, self::SORT_RATING_ASC], true)
            ? $sort
            : self::SORT_NEWEST;
    }

    private function buildSortSql($sort) {
        if ($sort === self::SORT_OLDEST) {
            return 'r.created_at ASC, r.id ASC';
        }

        if ($sort === self::SORT_RATING_ASC) {
            return 'r.rating ASC, r.created_at DESC, r.id DESC';
        }

        if ($sort === self::SORT_RATING_DESC) {
            return 'r.rating DESC, r.created_at DESC, r.id DESC';
        }

        return 'r.created_at DESC, r.id DESC';
    }

    private function normalizeRatingFilter($rating) {
        $rating = (int) $rating;
        return ($rating >= 1 && $rating <= 5) ? $rating : null;
    }

    private function normalizeStatusFilter($status) {
        return in_array($status, ['approved', 'pending', 'rejected'], true) ? $status : null;
    }
}
