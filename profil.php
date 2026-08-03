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

// Zorunlu şifre yenileme kontrolü (Yönetici tarafından sıfırlanmışsa)
$uCheck = $db->fetchOne("SELECT force_password_change, password FROM users WHERE id = ?", [$userId]);
if (!empty($uCheck['force_password_change']) || Session::get('user_force_password')) {
    header('Location: sifre-degistir.php?force=1');
    exit;
}

$pwError   = '';
$pwSuccess = '';

if (isset($_GET['pw_success']) || isset($_GET['pw_updated'])) {
    $pwSuccess = 'Şifreniz başarıyla ve güvenlik standartlarına uygun şekilde güncellendi.';
}

// Güvenli Şifre Değiştirme Form İşleyicisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!CSRFMiddleware::validate()) {
        $pwError = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $currPass = trim($_POST['current_password'] ?? '');
        $newPass  = trim($_POST['new_password'] ?? '');
        $confPass = trim($_POST['new_password_confirm'] ?? '');

        if (empty($currPass) || empty($newPass) || empty($confPass)) {
            $pwError = 'Lütfen tüm şifre alanlarını eksiksiz doldurunuz.';
        } elseif (!SecurityHelper::verifyPassword($currPass, $uCheck['password'])) {
            $pwError = 'Mevcut şifreniz hatalı.';
        } elseif ($newPass !== $confPass) {
            $pwError = 'Yeni şifreler birbiriyle eşleşmiyor.';
        } elseif (!SecurityHelper::validatePasswordStrength($newPass)) {
            $pwError = SecurityHelper::getPasswordStrengthMessage();
        } else {
            try {
                $hashed = SecurityHelper::hashPassword($newPass);
                $db->query("UPDATE users SET password = ?, force_password_change = 0, updated_at = NOW() WHERE id = ?", [$hashed, $userId]);
                $pwSuccess = 'Şifreniz başarıyla ve güvenlik standartlarına uygun olarak güncellendi!';
            } catch (Exception $e) {
                $pwError = 'Şifre güncellenirken bir sistem hatası oluştu.';
            }
        }
    }
}

$myReviews = $db->fetchAll("SELECT r.*, b.name as business_name, b.slug as business_slug, b.logo_path 
                            FROM reviews r 
                            JOIN businesses b ON r.business_id = b.id 
                            WHERE r.user_id = ? 
                            ORDER BY r.created_at DESC", [$userId]);

// ── Favoriler için Sayfalama (Her sayfada 5 kayıt) ──────────────────────────
$favLimit       = 5;
$totalFavorites = count($favorites);
$favPage        = isset($_GET['fav_page']) ? max(1, intval($_GET['fav_page'])) : 1;
$totalFavPages  = max(1, (int)ceil($totalFavorites / $favLimit));
if ($favPage > $totalFavPages) $favPage = $totalFavPages;
$favOffset      = ($favPage - 1) * $favLimit;
$pagedFavorites = array_slice($favorites, $favOffset, $favLimit);

// ── Yorumlar için Sayfalama (Her sayfada 5 kayıt) ───────────────────────────
$revLimit       = 5;
$totalReviews   = count($myReviews);
$revPage        = isset($_GET['rev_page']) ? max(1, intval($_GET['rev_page'])) : 1;
$totalRevPages  = max(1, (int)ceil($totalReviews / $revLimit));
if ($revPage > $totalRevPages) $revPage = $totalRevPages;
$revOffset      = ($revPage - 1) * $revLimit;
$pagedReviews   = array_slice($myReviews, $revOffset, $revLimit);

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

    <?php if ($pwSuccess): ?>
        <div class="alert alert-success border-0 small py-3 mb-4 rounded-3 shadow-sm d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
            <div><?= htmlspecialchars($pwSuccess) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($pwError): ?>
        <div class="alert alert-danger border-0 small py-3 mb-4 rounded-3 shadow-sm d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-danger"></i>
            <div><?= htmlspecialchars($pwError) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Favoriler -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-heart text-danger me-2"></i>Favori İşletmelerim</h5>
                    <span class="badge bg-secondary"><?= $totalFavorites ?></span>
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
                            <?php foreach ($pagedFavorites as $fav): ?>
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

                <?php if ($totalFavPages > 1): ?>
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <?= $favOffset + 1 ?>-<?= min($favOffset + $favLimit, $totalFavorites) ?> / <?= $totalFavorites ?> favori
                    </small>
                    <nav aria-label="Favoriler Sayfalama">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $favPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?fav_page=<?= $favPage - 1 ?><?= isset($_GET['rev_page']) ? '&rev_page='.intval($_GET['rev_page']) : '' ?>" aria-label="Önceki">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($fp = 1; $fp <= $totalFavPages; $fp++): ?>
                                <li class="page-item <?= $fp === $favPage ? 'active' : '' ?>">
                                    <a class="page-link <?= $fp === $favPage ? 'bg-primary border-primary text-white' : '' ?>" href="?fav_page=<?= $fp ?><?= isset($_GET['rev_page']) ? '&rev_page='.intval($_GET['rev_page']) : '' ?>"><?= $fp ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $favPage >= $totalFavPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?fav_page=<?= $favPage + 1 ?><?= isset($_GET['rev_page']) ? '&rev_page='.intval($_GET['rev_page']) : '' ?>" aria-label="Sonraki">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Yorumlar -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-star text-warning me-2"></i>Yaptığım Yorumlar</h5>
                    <span class="badge bg-secondary"><?= $totalReviews ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($myReviews)): ?>
                        <div class="text-center py-5">
                            <i class="fa-regular fa-comment-dots fs-1 text-muted opacity-50 mb-3"></i>
                            <p class="text-muted mb-0">Henüz hiçbir işletmeye yorum yapmadınız.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pagedReviews as $rev): ?>
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

                <?php if ($totalRevPages > 1): ?>
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <?= $revOffset + 1 ?>-<?= min($revOffset + $revLimit, $totalReviews) ?> / <?= $totalReviews ?> yorum
                    </small>
                    <nav aria-label="Yorumlar Sayfalama">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $revPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?rev_page=<?= $revPage - 1 ?><?= isset($_GET['fav_page']) ? '&fav_page='.intval($_GET['fav_page']) : '' ?>" aria-label="Önceki">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($rp = 1; $rp <= $totalRevPages; $rp++): ?>
                                <li class="page-item <?= $rp === $revPage ? 'active' : '' ?>">
                                    <a class="page-link <?= $rp === $revPage ? 'bg-primary border-primary text-white' : '' ?>" href="?rev_page=<?= $rp ?><?= isset($_GET['fav_page']) ? '&fav_page='.intval($_GET['fav_page']) : '' ?>"><?= $rp ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $revPage >= $totalRevPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?rev_page=<?= $revPage + 1 ?><?= isset($_GET['fav_page']) ? '&fav_page='.intval($_GET['fav_page']) : '' ?>" aria-label="Sonraki">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Güvenli Şifre Değiştirme (Merkezi Şifre Standardı) -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Güvenli Şifre Değiştir</h5>
                    <span class="badge bg-primary">8 Karakter • Büyük/Küçük Harf • Rakam</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-muted">Mevcut Şifreniz</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock-open text-muted"></i></span>
                                    <input type="password" name="current_password" class="form-control border-start-0 ps-2" required placeholder="Şu anki şifreniz">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-muted">Yeni Şifreniz</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" name="new_password" class="form-control border-start-0 ps-2" required placeholder="En az 8 karakter (A-Z, a-z, 0-9)">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-muted">Yeni Şifreniz (Tekrar)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" name="new_password_confirm" class="form-control border-start-0 ps-2" required placeholder="Yeni şifrenizi tekrar yazın">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                            <div class="small text-muted">
                                <i class="fa-solid fa-circle-info text-primary me-1"></i>
                                Şifreniz en az 8 karakter uzunluğunda olmalı; en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.
                            </div>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                                Şifremi Güncelle <i class="fa-solid fa-shield-check ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
