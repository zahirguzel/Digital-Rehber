<?php
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/models/Favorite.php';
require_once __DIR__ . '/models/Review.php';
require_once __DIR__ . '/models/User.php';

Session::start();

if (!Session::get('user_logged_in')) {
    header('Location: giris.php');
    exit;
}

$userId = Session::get('user_id');

$favoriteModel = new Favorite();
$favorites = $favoriteModel->getUserFavorites($userId);

$db = Database::getInstance();
$myReviews = $db->fetchAll("SELECT r.*, b.name as business_name, b.slug as business_slug, b.logo_path 
                            FROM reviews r 
                            JOIN businesses b ON r.business_id = b.id 
                            WHERE r.user_id = ? 
                            ORDER BY r.created_at DESC", [$userId]);

$pageTitle = 'Profilim';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-3">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold text-navy mb-1">Merhaba, <?= SecurityHelper::escape(Session::get('user_name')) ?></h2>
            <p class="text-muted">Hesap bilgilerinizi, favorilerinizi ve yorumlarınızı buradan yönetebilirsiniz.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="cikis.php" class="btn btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Çıkış Yap</a>
        </div>
    </div>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success border-0 small py-3 mb-4 rounded-3 shadow-sm">
            <i class="fa-solid fa-circle-check me-2"></i> Hesabınız başarıyla oluşturuldu. Aramıza hoş geldiniz!
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Favoriler -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-heart text-danger me-2"></i>Favori İşletmelerim</h5>
                    <span class="badge bg-secondary"><?= count($favorites) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($favorites)): ?>
                        <div class="text-center py-5">
                            <i class="fa-regular fa-folder-open fs-1 text-muted opacity-50 mb-3"></i>
                            <p class="text-muted mb-0">Henüz favorilere eklediğiniz bir işletme bulunmuyor.</p>
                            <a href="esnaflar.php" class="btn btn-sm btn-primary mt-3">İşletmeleri Keşfet</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($favorites as $fav): ?>
                                <a href="esnaf.php?slug=<?= htmlspecialchars($fav['slug']) ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center gap-3">
                                    <?php 
                                    $logo = !empty($fav['logo_path']) && $fav['logo_path'] !== 'default_logo.png' 
                                            ? (strpos($fav['logo_path'], 'http') === 0 ? $fav['logo_path'] : 'public/images/' . $fav['logo_path'])
                                            : 'public/images/default_logo.png';
                                    ?>
                                    <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($fav['name']) ?>" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold text-navy"><?= htmlspecialchars($fav['name']) ?></h6>
                                        <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($fav['district']) ?>, <?= htmlspecialchars($fav['city']) ?></small>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Yorumlar -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-star text-warning me-2"></i>Yaptığım Yorumlar</h5>
                    <span class="badge bg-secondary"><?= count($myReviews) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myReviews)): ?>
                        <div class="text-center py-5">
                            <i class="fa-regular fa-comment-dots fs-1 text-muted opacity-50 mb-3"></i>
                            <p class="text-muted mb-0">Henüz hiçbir işletmeye yorum yapmadınız.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($myReviews as $rev): ?>
                                <div class="list-group-item p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <a href="esnaf.php?slug=<?= htmlspecialchars($rev['business_slug']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= htmlspecialchars($rev['business_name']) ?>
                                        </a>
                                        <small class="text-muted"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></small>
                                    </div>
                                    <div class="text-warning mb-2" style="font-size: 14px;">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fa-<?= $i <= $rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                        
                                        <?php if ($rev['status'] === 'pending'): ?>
                                            <span class="badge bg-warning ms-2 text-dark">Onay Bekliyor</span>
                                        <?php elseif ($rev['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger ms-2">Reddedildi</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-0 text-muted small" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
