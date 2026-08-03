<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../models/Business.php';

$pageTitle = 'Genel Bakış';
require_once __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getPDO();
$bizId = (int)($_SESSION['biz_id'] ?? 0);

// İstatistikler
$stats = ['menu_items' => 0, 'categories' => 0, 'avg_rating' => 0, 'review_count' => 0, 'pending_reviews' => 0];
$analytics = ['total_views' => 0, 'views_today' => 0, 'views_7d' => 0, 'views_30d' => 0, 'last_view_date' => null];
$bizInfo = null;
$businessModel = new Business();

try {
    $stats['menu_items']    = $db->query("SELECT COUNT(*) FROM menu_items WHERE business_id = $bizId")->fetchColumn();
    $stats['categories']    = $db->query("SELECT COUNT(*) FROM menu_categories WHERE business_id = $bizId")->fetchColumn();

    $ratingRow = $db->query("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE business_id = $bizId AND status = 'approved'")->fetch(PDO::FETCH_ASSOC);
    if ($ratingRow) {
        $stats['avg_rating']    = round((float)$ratingRow['avg_r'], 1);
        $stats['review_count']  = (int)$ratingRow['cnt'];
    }
    $stats['pending_reviews'] = $db->query("SELECT COUNT(*) FROM reviews WHERE business_id = $bizId AND status = 'pending'")->fetchColumn();

    $bizInfo = $db->query("SELECT name, logo_path, cover_image_path, theme_color, is_premium, slug, district, city FROM businesses WHERE id = $bizId")->fetch(PDO::FETCH_ASSOC);
    $analytics = $businessModel->getAnalyticsSummary($bizId);
} catch (Exception $e) {}


$bizColor  = !empty($bizInfo['theme_color']) ? htmlspecialchars($bizInfo['theme_color']) : '#D62828';
$bizLetter = $bizInfo ? mb_strtoupper(mb_substr($bizInfo['name'], 0, 1, 'UTF-8'), 'UTF-8') : '?';
$bizName   = htmlspecialchars($bizInfo['name'] ?? ($_SESSION['biz_name'] ?? 'İşletme'));

// Son red ve onay bekleyen talepler
$rejectedChangesDash = [];
$pendingChangesDash = [];
try {
    $rejectedChangesDash = $db->query("SELECT * FROM business_pending_changes WHERE business_id = $bizId AND status = 'rejected' ORDER BY reviewed_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $pendingChangesDash = $db->query("SELECT * FROM business_pending_changes WHERE business_id = $bizId AND status = 'pending' ORDER BY submitted_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>



<?php if (!empty($pendingChangesDash)): ?>
    <div class="alert alert-warning shadow-sm border-0 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-hourglass-half fs-5 text-warning"></i>
            <div>
                <strong class="text-dark">Onay Bekleyen <?= count($pendingChangesDash) ?> Adet Değişikliğiniz Var (Sarı)</strong>
                <div class="small text-muted">Değişiklikleriniz yönetici onayı sonrasında yayına girecektir.</div>
            </div>
        </div>
        <a href="profile.php" class="btn btn-sm btn-warning text-dark fw-bold">Ayarları İncele</a>
    </div>
<?php endif; ?>

<!-- Karşılama Başlığı -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h3 class="mb-0 fw-bold" style="color:var(--secondary);">Hoş Geldiniz, <?= $bizName ?> 👋</h3>
        <p class="text-muted mb-0 mt-1 small">İşletmenizin güncel istatistikleri ve durum özeti.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($bizInfo['slug'])): ?>
        <a href="../esnaf/<?= htmlspecialchars($bizInfo['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-eye me-1"></i> Profilimi Gör
        </a>
        <?php endif; ?>
        <a href="qr.php" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-qrcode me-1"></i> QR Kartvizit
        </a>
        <a href="menu-urunler.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Ürün Ekle
        </a>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="menu-urunler.php" class="text-decoration-none">
            <div class="biz-panel-stat-card h-100" style="transition: transform 0.2s;">
                <div class="biz-panel-stat-icon" style="background:rgba(214,40,40,0.1);color:var(--primary);">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value" style="font-size: 18px; line-height: 1.3;"><?= (int)$stats['menu_items'] ?> Ürün &bull; <?= (int)$stats['categories'] ?> Kategori</div>
                    <div class="biz-panel-stat-label">Dijital Menü</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card">
            <div class="biz-panel-stat-icon" style="background:rgba(16,185,129,0.1);color:#10B981;">
                <i class="fa-solid fa-star"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '—' ?></div>
                <div class="biz-panel-stat-label">Ortalama Puan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="reviews.php" class="text-decoration-none">
            <div class="biz-panel-stat-card h-100">
                <div class="biz-panel-stat-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value"><?= (int)$stats['review_count'] ?></div>
                    <div class="biz-panel-stat-label">Değerlendirme
                        <?php if ($stats['pending_reviews'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-1" style="font-size:9px;"><?= (int)$stats['pending_reviews'] ?> Bekliyor</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="biz-panel-stat-card">
            <div class="biz-panel-stat-icon" style="background:rgba(245,158,11,0.1);color:#F59E0B;">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
                <div class="biz-panel-stat-value"><?= number_format((int)($analytics['total_views'] ?? 0), 0, ',', '.') ?></div>
                <div class="biz-panel-stat-label">Profil Görüntülenmesi</div>
            </div>
        </div>
    </div>
</div>

<!-- Görüntülenme Özeti -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Profil Görüntülenmeleri</h6>
            <p class="text-muted small mb-0">İşletme profil sayfanız ziyaret edildikçe bu sayaçlar otomatik artar.</p>
        </div>
        <?php if (!empty($analytics['last_view_date'])): ?>
        <span class="badge bg-light text-dark border">Son görüntülenme: <?= date('d.m.Y', strtotime($analytics['last_view_date'])) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body pt-3">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="small text-muted mb-1">Toplam</div>
                    <div class="fw-bold fs-4" style="color:var(--secondary);"><?= number_format((int) $analytics['total_views'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="small text-muted mb-1">Bugün</div>
                    <div class="fw-bold fs-4" style="color:var(--secondary);"><?= number_format((int) $analytics['views_today'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="small text-muted mb-1">Son 7 Gün</div>
                    <div class="fw-bold fs-4" style="color:var(--secondary);"><?= number_format((int) $analytics['views_7d'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="border rounded-3 p-3 h-100">
                    <div class="small text-muted mb-1">Son 30 Gün</div>
                    <div class="fw-bold fs-4" style="color:var(--secondary);"><?= number_format((int) $analytics['views_30d'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı İşlemler + QR Kart -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold mb-0" style="color:var(--secondary);"><i class="fa-solid fa-rocket me-2 text-primary"></i>Hızlı İşlemler</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="profile.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-image me-2 text-success"></i>Profili Güncelle</h6>
                            <p class="small text-muted mb-0">Logo, kapak görseli ve iletişim bilgilerini düzenleyin.</p>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="menu.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-utensils me-2 text-primary"></i>Menüyü Düzenle</h6>
                            <p class="small text-muted mb-0">Ürün fiyatlarını, kategorileri veya fotoğrafları güncelleyin.</p>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="qr.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-qrcode me-2" style="color:#8B5CF6;"></i>QR Kartvizit & Menü</h6>
                            <p class="small text-muted mb-0">Dijital kartvizitinizi ve QR menünüzü görüntüleyin, indirin.</p>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="settings.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-lock me-2" style="color:#F59E0B;"></i>Şifre Değiştir</h6>
                            <p class="small text-muted mb-0">Panel giriş şifrenizi güncelleyin.</p>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="reviews.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-comments me-2" style="color:#3B82F6;"></i>Yorumları İncele</h6>
                            <p class="small text-muted mb-0">Müşteri değerlendirmelerini sıralayın, filtreleyin ve tüm kayıtları görüntüleyin.</p>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="campaigns.php" class="text-decoration-none d-block p-3 border rounded-3 transition hover-shadow">
                            <h6 class="fw-bold mb-1" style="color:var(--secondary);"><i class="fa-solid fa-tags me-2" style="color:#10B981;"></i>Kampanyaları Yönet</h6>
                            <p class="small text-muted mb-0">İndirim ve fırsat kampanyaları ekleyin, düzenleyin.</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 text-white h-100" style="border-radius:12px;background:linear-gradient(135deg,<?= $bizColor ?> 0%,<?= $bizColor ?>cc 100%);">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-5">
                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center mb-3 shadow" style="width:60px;height:60px;color:<?= $bizColor ?>;">
                    <i class="fa-solid fa-qrcode fs-3"></i>
                </div>
                <h5 class="fw-bold mb-2">Dijital Kartvizit</h5>
                <p class="small mb-4" style="opacity:0.8;">QR kodunuzu masalara yerleştirin veya dijitalde paylaşın.</p>
                <a href="qr.php" class="btn btn-light rounded-pill fw-bold px-4 shadow-sm" style="color:<?= $bizColor ?>;">
                    <i class="fa-solid fa-qrcode me-2"></i>QR Kod İndir
                </a>
                <?php if (!empty($bizInfo['is_premium'])): ?>
                <span class="badge mt-3" style="background:rgba(255,255,255,0.25);font-size:11px;">
                    <i class="fa-solid fa-crown me-1" style="color:#F5C842;"></i> Premium Hesap
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<style>
.hover-shadow:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); border-color: #e2e8f0 !important; background: #fafafa !important; }
.transition { transition: all 0.2s ease; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>