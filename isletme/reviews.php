<?php
ob_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../models/Review.php';

$db = Database::getInstance();
$bizId = (int) ($_SESSION['biz_id'] ?? 0);
$bizName = $_SESSION['biz_name'] ?? 'Isletme';
$pageTitle = 'Yorumlar';

$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['approved', 'pending', 'rejected'], true) ? $statusFilter : '';
$sortFilter = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$ratingFilter = isset($_GET['rating']) ? (int) $_GET['rating'] : 0;
$ratingFilter = ($ratingFilter >= 1 && $ratingFilter <= 5) ? $ratingFilter : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$reviewModel = new Review();
$reviewSummary = [
    'all' => 0,
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0,
];
$reviews = [];
$totalReviews = 0;
$totalPages = 1;
$approvedRating = 0.0;

try {
    $reviewSummary = $reviewModel->getBusinessReviewSummary($bizId);
    $totalReviews = $reviewModel->countBusinessReviews($bizId, [
        'status' => $statusFilter ?: null,
        'sort' => $sortFilter,
        'rating' => $ratingFilter ?: null,
    ]);
    $totalPages = max(1, (int) ceil($totalReviews / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $reviews = $reviewModel->getBusinessReviews($bizId, [
        'status' => $statusFilter ?: null,
        'sort' => $sortFilter,
        'rating' => $ratingFilter ?: null,
    ], $page, $perPage);

    $approvedStats = $db->fetchOne(
        "SELECT AVG(rating) as avg_rating FROM reviews WHERE business_id = ? AND status = 'approved'",
        [$bizId]
    );
    $approvedRating = round((float) ($approvedStats['avg_rating'] ?? 0), 1);
} catch (Exception $e) {}

$buildReviewsUrl = static function (array $overrides = []) use ($statusFilter, $sortFilter, $ratingFilter, $page) {
    $params = [
        'status' => $statusFilter ?: null,
        'sort' => $sortFilter !== 'newest' ? $sortFilter : null,
        'rating' => $ratingFilter > 0 ? $ratingFilter : null,
        'page' => $page > 1 ? $page : null,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    if (($params['status'] ?? null) === '') {
        $params['status'] = null;
    }
    if (($params['sort'] ?? null) === 'newest') {
        $params['sort'] = null;
    }
    if (empty($params['rating'])) {
        $params['rating'] = null;
    }
    if (empty($params['page']) || (int) $params['page'] <= 1) {
        $params['page'] = null;
    }

    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });

    $query = http_build_query($params);
    return 'reviews.php' . ($query !== '' ? ('?' . $query) : '');
};

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--navy);">
            <i class="fa-solid fa-comments me-2" style="color:var(--primary);"></i>
            <?= htmlspecialchars($bizName) ?> - Yorumlar
        </h5>
        <p class="text-muted small mb-0">Musteri yorumlarini yeni/eski ve yildiz puanina gore filtreleyebilirsiniz.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Genel Bakis
        </a>
        <a href="reviews.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-rotate-left me-1"></i> Listeyi Yenile
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card h-100">
            <div class="biz-panel-stat-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= (int) $reviewSummary['all'] ?></div>
                <div class="biz-panel-stat-label">Toplam Yorum</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card h-100">
            <div class="biz-panel-stat-icon" style="background:rgba(16,185,129,0.1);color:#10B981;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= (int) $reviewSummary['approved'] ?></div>
                <div class="biz-panel-stat-label">Onayli</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card h-100">
            <div class="biz-panel-stat-icon" style="background:rgba(245,158,11,0.1);color:#F59E0B;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= (int) $reviewSummary['pending'] ?></div>
                <div class="biz-panel-stat-label">Bekleyen</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card h-100">
            <div class="biz-panel-stat-icon" style="background:rgba(234,179,8,0.12);color:#EAB308;">
                <i class="fa-solid fa-star"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= $approvedRating > 0 ? number_format($approvedRating, 1) : '-' ?></div>
                <div class="biz-panel-stat-label">Ortalama Puan</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        <form method="GET" action="reviews.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="reviewStatus" class="form-label fw-semibold small text-muted">Durum</label>
                <select name="status" id="reviewStatus" class="form-select">
                    <option value="">Tum durumlar</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Onayli</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Bekleyen</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Reddedilen</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="reviewSort" class="form-label fw-semibold small text-muted">Siralama</label>
                <select name="sort" id="reviewSort" class="form-select">
                    <option value="newest" <?= $sortFilter === 'newest' ? 'selected' : '' ?>>En yeni yorumlar</option>
                    <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>En eski yorumlar</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="reviewRating" class="form-label fw-semibold small text-muted">Yildiz</label>
                <select name="rating" id="reviewRating" class="form-select">
                    <option value="">Tum yildizlar</option>
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <option value="<?= $star ?>" <?= $ratingFilter === $star ? 'selected' : '' ?>><?= $star ?> yildiz</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <i class="fa-solid fa-filter me-1"></i> Filtrele
                </button>
                <?php if ($statusFilter !== '' || $sortFilter !== 'newest' || $ratingFilter > 0): ?>
                    <a href="reviews.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-rotate-left me-1"></i> Sifirla
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Yorum Listesi</span>
        <span class="badge bg-secondary"><?= (int) $totalReviews ?> kayit</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reviews)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-regular fa-comment-dots fs-1 opacity-50 mb-3"></i>
                <p class="mb-0">Secilen filtrelere uygun yorum bulunamadi.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($reviews as $review):
                    $reviewerName = !empty($review['user_name']) ? $review['user_name'] : 'Ziyaretci';
                    $reviewerLetter = mb_strtoupper(mb_substr($reviewerName, 0, 1, 'UTF-8'), 'UTF-8');
                ?>
                    <div class="list-group-item p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div class="d-flex gap-3 flex-grow-1">
                                <div class="biz-review-avatar"><?= htmlspecialchars($reviewerLetter) ?></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($reviewerName) ?></div>
                                            <?php if (!empty($review['user_email'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars($review['user_email']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-warning small mb-1">
                                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                                    <i class="fa-<?= $star <= (int) $review['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php if ($review['status'] === 'approved'): ?>
                                            <span class="badge bg-success-subtle text-success">Onayli</span>
                                        <?php elseif ($review['status'] === 'pending'): ?>
                                            <span class="badge bg-warning-subtle text-warning">Bekliyor</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Reddedildi</span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-dark border"><?= (int) $review['rating'] ?> yildiz</span>
                                    </div>
                                    <p class="mb-0 text-muted" style="white-space: pre-line;"><?= htmlspecialchars((string) ($review['comment'] ?? 'Yorum metni girilmemis.')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white border-top py-3">
                    <nav aria-label="Isletme yorumlari sayfalama">
                        <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap gap-1">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildReviewsUrl(['page' => $page - 1])) ?>">Onceki</a>
                            </li>
                            <?php for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++): ?>
                                <li class="page-item <?= $pageNo === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($buildReviewsUrl(['page' => $pageNo])) ?>"><?= $pageNo ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildReviewsUrl(['page' => $page + 1])) ?>">Sonraki</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.biz-review-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), #2563eb);
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
