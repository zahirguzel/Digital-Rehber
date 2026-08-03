<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);

$db = Database::getInstance();

$successMsg = '';
$errorMsg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id && in_array($action, ['approve', 'reject', 'delete'])) {
        try {
            if ($action === 'delete') {
                $db->execute("DELETE FROM reviews WHERE id = ?", [$id]);
                if (function_exists('logAction')) logAction('delete', 'reviews', 'Yorum ID: ' . $id, $id);
                $successMsg = "Yorum başarıyla silindi.";
            } elseif ($action === 'approve') {
                $db->execute("UPDATE reviews SET status = 'approved' WHERE id = ?", [$id]);
                if (function_exists('logAction')) logAction('approve', 'reviews', 'Yorum ID: ' . $id, $id);
                $successMsg = "Yorum başarıyla onaylandı.";
                
                // Update average rating for the business
                $review = $db->fetchOne("SELECT business_id FROM reviews WHERE id = ?", [$id]);
                if ($review) {
                    require_once __DIR__ . '/../models/Business.php';
                    (new Business())->updateAverageRating($review['business_id']);
                }
            } elseif ($action === 'reject') {
                $db->execute("UPDATE reviews SET status = 'rejected' WHERE id = ?", [$id]);
                if (function_exists('logAction')) logAction('reject', 'reviews', 'Yorum ID: ' . $id, $id);
                $successMsg = "Yorum reddedildi.";
                
                // Update average rating for the business
                $review = $db->fetchOne("SELECT business_id FROM reviews WHERE id = ?", [$id]);
                if ($review) {
                    require_once __DIR__ . '/../models/Business.php';
                    (new Business())->updateAverageRating($review['business_id']);
                }
            }
        } catch (Exception $e) {
            $errorMsg = "Bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Fetch Reviews
$statusFilter = $_GET['status'] ?? '';
$where = "1=1";
$params = [];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$sql = "SELECT r.*, u.name as user_name, u.email as user_email, b.name as business_name, b.slug as business_slug 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN businesses b ON r.business_id = b.id 
        WHERE $where 
        ORDER BY r.created_at DESC";

$reviews = $db->fetchAll($sql, $params);
?>
<?php require_once 'includes/header.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-star-half-stroke me-2 text-warning"></i> Yorum Yönetimi</h2>
            <p class="text-muted mb-0">Platformdaki tüm müşteri yorumlarını onaylayın, reddedin veya silin.</p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <ul class="nav nav-pills custom-pills">
                <li class="nav-item">
                    <a class="nav-link <?= empty($statusFilter) ? 'active' : '' ?>" href="reviews.php">Tümü</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="reviews.php?status=pending">Onay Bekleyenler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="reviews.php?status=approved">Onaylananlar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="reviews.php?status=rejected">Reddedilenler</a>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kullanıcı</th>
                            <th>İşletme</th>
                            <th>Puan</th>
                            <th style="width: 35%;">Yorum</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviews)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-regular fa-comment-slash fs-1 opacity-50 mb-3"></i>
                                    <p class="mb-0">Kayıtlı yorum bulunamadı.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($rev['user_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($rev['user_email']) ?></small>
                                    </td>
                                    <td>
                                        <a href="../esnaf.php?slug=<?= htmlspecialchars($rev['business_slug']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($rev['business_name']) ?> <i class="fa-solid fa-arrow-up-right-from-square small ms-1"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="text-warning">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="fa-<?= $i <= $rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="mb-0 small text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($rev['comment']) ?>">
                                            <?= htmlspecialchars($rev['comment']) ?>
                                        </p>
                                    </td>
                                    <td>
                                        <?php if ($rev['status'] === 'approved'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check me-1"></i> Onaylı</span>
                                        <?php elseif ($rev['status'] === 'pending'): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-clock me-1"></i> Bekliyor</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-xmark me-1"></i> Reddedildi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= date('d.m.Y H:i', strtotime($rev['created_at'])) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <?php if ($rev['status'] !== 'approved'): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <?= CSRFMiddleware::field() ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Onayla"><i class="fa-solid fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($rev['status'] !== 'rejected'): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <?= CSRFMiddleware::field() ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning text-dark" title="Reddet"><i class="fa-solid fa-xmark"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bu yorumu kalıcı olarak silmek istediğinize emin misiniz?');">
                                                <?= CSRFMiddleware::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
