<?php
ob_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
$db = Database::getInstance();

$pageTitle = 'Fotoğraf Galerisi';
$bizId     = intval($_SESSION['biz_id'] ?? 0);

if (!$bizId) {
    header('Location: login.php');
    exit;
}

$successMsg = '';
$errorMsg   = '';

// Handle Delete Active
if (isset($_GET['del'])) {
    $delId = intval($_GET['del']);
    $stmt = $db->getPDO()->prepare("SELECT image_path FROM business_gallery WHERE id = ? AND business_id = ?");
    $stmt->execute([$delId, $bizId]);
    $img = $stmt->fetch();
    if ($img) {
        $path = __DIR__ . '/../public/images/gallery/' . $bizId . '/' . $img['image_path'];
        if (file_exists($path)) {
            @unlink($path);
        }
        $db->getPDO()->prepare("DELETE FROM business_gallery WHERE id = ?")->execute([$delId]);
        $successMsg = 'Görsel silindi.';
    }
}

// Handle Delete Pending
if (isset($_GET['del_pending'])) {
    $delPendingId = intval($_GET['del_pending']);
    $stmt = $db->getPDO()->prepare("SELECT new_value FROM business_pending_changes WHERE id = ? AND business_id = ? AND status = 'pending'");
    $stmt->execute([$delPendingId, $bizId]);
    $pImg = $stmt->fetch();
    if ($pImg) {
        $path = __DIR__ . '/../public/images/gallery/' . $bizId . '/' . $pImg['new_value'];
        if (file_exists($path)) {
            @unlink($path);
        }
        $db->getPDO()->prepare("DELETE FROM business_pending_changes WHERE id = ?")->execute([$delPendingId]);
        $successMsg = 'Onay bekleyen görsel talebi iptal edildi.';
    }
}

// Handle Sort
if (isset($_GET['sort']) && isset($_GET['dir'])) {
    $sortId = intval($_GET['sort']);
    $dir = $_GET['dir'] === 'up' ? -15 : 15; // move by 1.5 positions basically, then re-sort
    $db->getPDO()->prepare("UPDATE business_gallery SET sort_order = sort_order + ? WHERE id = ? AND business_id = ?")->execute([$dir, $sortId, $bizId]);
    
    // Normalize sorts
    $items = $db->query("SELECT id FROM business_gallery WHERE business_id = ? ORDER BY sort_order ASC, id ASC", [$bizId])->fetchAll();
    $s = 10;
    foreach ($items as $it) {
        $db->getPDO()->prepare("UPDATE business_gallery SET sort_order = ? WHERE id = ?")->execute([$s, $it['id']]);
        $s += 10;
    }
    header("Location: gallery.php");
    exit;
}

// Check current count (active + pending)
$activeCount = intval($db->query("SELECT COUNT(*) FROM business_gallery WHERE business_id = ?", [$bizId])->fetchColumn());
$pendingCount = intval($db->query("SELECT COUNT(*) FROM business_pending_changes WHERE business_id = ? AND change_type = 'gallery' AND status = 'pending'", [$bizId])->fetchColumn());
$totalGalleryCount = $activeCount + $pendingCount;
$count = $activeCount; // for backwards compatibility in UI if needed

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_image'])) {
    if ($totalGalleryCount >= 6) {
        $errorMsg = 'En fazla 6 adet görsel (aktif + onay bekleyen) yükleyebilirsiniz. Maksimum limite ulaşıldı.';
    } else {
        $dirPath = __DIR__ . '/../public/images/gallery/' . $bizId;
        $processResult = processAndSaveImage($_FILES['gallery_image'], $dirPath, 'gal_');
        
        if ($processResult['success']) {
            $newName = $processResult['filename'];
            
            // Insert into pending changes for admin review
            $ins = $db->getPDO()->prepare("INSERT INTO business_pending_changes (business_id, field_name, field_label, old_value, new_value, change_type) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$bizId, 'gallery_add', 'Galeri Fotoğrafı', '', $newName, 'gallery']);
            $successMsg = 'Görsel başarıyla yüklendi ve yönetici onayına gönderildi. Onaylandıktan sonra galerinizde yayınlanacaktır.';
        } else {
            $errorMsg = $processResult['error'];
        }
    }
}

$images = $db->query("SELECT * FROM business_gallery WHERE business_id = ? ORDER BY sort_order ASC, id ASC", [$bizId])->fetchAll();
$pendingGallery = $db->query("SELECT * FROM business_pending_changes WHERE business_id = ? AND change_type = 'gallery' AND status = 'pending' ORDER BY submitted_at DESC", [$bizId])->fetchAll();
$rejectedGallery = $db->query("SELECT * FROM business_pending_changes WHERE business_id = ? AND change_type = 'gallery' AND status = 'rejected' ORDER BY reviewed_at DESC LIMIT 5", [$bizId])->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-images me-2 text-primary"></i> Fotoğraf Galerisi</h3>
        <p class="text-muted mb-0 mt-1 small">İşletmenizin profil sayfasında ve dijital kartvizitinde görünecek fotoğraflar.</p>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="fa-solid fa-circle-check"></i> <?= $successMsg ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div>
<?php endif; ?>

<?php if (!empty($rejectedGallery)): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-circle-xmark fs-4 mt-1 text-danger"></i>
            <div class="w-100">
                <h6 class="fw-bold mb-1">Reddedilen Galeri Fotoğrafları</h6>
                <p class="mb-2 small">Yüklediğiniz bazı fotoğraflar yönetici tarafından reddedildi:</p>
                <div class="list-group list-group-flush border rounded small">
                    <?php foreach ($rejectedGallery as $rej): ?>
                    <div class="list-group-item bg-light d-flex justify-content-between align-items-center py-2">
                        <div>
                            <strong class="text-danger"><?= htmlspecialchars($rej['field_label']) ?></strong>
                            <div class="text-dark mt-1"><strong>Red Sebebi:</strong> <?= htmlspecialchars($rej['reject_reason'] ?: 'Belirtilmedi') ?></div>
                            <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> Tarih: <?= date('d.m.Y H:i', strtotime($rej['reviewed_at'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($pendingGallery)): ?>
    <div class="alert alert-warning shadow-sm border-0 mb-4 d-flex align-items-start gap-3">
        <i class="fa-solid fa-hourglass-half fs-4 mt-1 text-warning"></i>
        <div class="w-100">
            <h6 class="fw-bold mb-1">Onay Bekleyen Galeri Fotoğraflarınız (<?= count($pendingGallery) ?> Adet)</h6>
            <p class="mb-2 small">Aşağıdaki yüklediğiniz fotoğraflar yönetici onayına sunulmuştur. Onaylandığında galerinizde görünecektir.</p>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($pendingGallery as $pGal): ?>
                    <div class="bg-white p-2 rounded border border-warning text-center" style="width: 130px;">
                        <img src="../public/images/gallery/<?= $bizId ?>/<?= htmlspecialchars($pGal['new_value']) ?>" class="img-fluid rounded mb-1" style="height: 60px; object-fit: cover; width: 100%;" alt="Pending">
                        <span class="badge bg-warning text-dark d-block small mb-1">Onay Bekliyor</span>
                        <small class="text-muted d-block" style="font-size: 10px;"><i class="fa-regular fa-clock"></i> <?= date('d.m.Y H:i', strtotime($pGal['submitted_at'])) ?></small>
                        <a href="gallery.php?del_pending=<?= $pGal['id'] ?>" class="btn btn-sm btn-outline-danger w-100 mt-1 py-0" style="font-size: 11px;" onclick="return confirm('Bu onay bekleyen görseli iptal etmek istediğinize emin misiniz?')"><i class="fa-solid fa-trash me-1"></i> İptal</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Upload Section -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:16px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold text-navy"><i class="fa-solid fa-cloud-arrow-up me-2" style="color:var(--primary)"></i> Yeni Görsel Yükle</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    İşletmenizi anlatan en güzel fotoğrafları yükleyin. Toplam <strong>6 adet</strong> görsel yükleyebilirsiniz.
                </p>
                <div class="d-flex flex-wrap gap-1 mb-4">
                    <span class="badge bg-light text-dark border">Aktif: <?= $activeCount ?></span>
                    <span class="badge bg-light text-dark border">Onay Bekleyen: <?= $pendingCount ?></span>
                    <span class="badge bg-primary">Toplam: <?= $totalGalleryCount ?> / 6</span>
                </div>
                
                <?php if ($totalGalleryCount < 6): ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input class="form-control" type="file" name="gallery_image" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text small text-muted mt-2">Önerilen boyut: 1200x800 px (Yatay). Görseller otomatik olarak sıkıştırılır.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="fa-solid fa-upload me-1"></i> Yükle
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning mb-0 text-center small">
                    <i class="fa-solid fa-circle-info mb-2 fs-4 d-block"></i>
                    Maksimum görsel sınırına (<strong><?= $activeCount ?></strong> Aktif + <strong><?= $pendingCount ?></strong> Onay Bekleyen = <strong>6</strong>) ulaştınız. Yeni bir görsel eklemek için lütfen önce mevcut fotoğraflardan veya onay bekleyenlerden birini silin.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Gallery Grid -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-navy"><i class="fa-solid fa-grip me-2 text-muted"></i> Mevcut Görseller</h6>
            </div>
            <div class="card-body p-4">
                <?php if (empty($images)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-images fs-1 mb-3" style="color:#e2e8f0;"></i>
                    <p class="mb-0">Henüz galeriye görsel eklemediniz.</p>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($images as $index => $img): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="position-relative bg-light rounded-3 overflow-hidden shadow-sm" style="padding-top: 75%; border: 1px solid #e2e8f0;">
                            <img src="../public/images/gallery/<?= $bizId ?>/<?= $img['image_path'] ?>" 
                                 class="position-absolute top-0 start-0 w-100 h-100" style="object-fit:cover;" alt="">
                            
                            <!-- Action overlay -->
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-1" style="background: linear-gradient(to bottom left, rgba(0,0,0,0.6), transparent); border-bottom-left-radius: 12px;">
                                <?php if ($index > 0): ?>
                                    <a href="gallery.php?sort=<?= $img['id'] ?>&dir=up" class="btn btn-sm btn-light" style="padding: 2px 6px;" title="Öne Al"><i class="fa-solid fa-arrow-left"></i></a>
                                <?php endif; ?>
                                <?php if ($index < count($images)-1): ?>
                                    <a href="gallery.php?sort=<?= $img['id'] ?>&dir=down" class="btn btn-sm btn-light" style="padding: 2px 6px;" title="Geriye Al"><i class="fa-solid fa-arrow-right"></i></a>
                                <?php endif; ?>
                            </div>
                            <div class="position-absolute bottom-0 end-0 p-2">
                                <a href="gallery.php?del=<?= $img['id'] ?>" class="btn btn-sm btn-danger confirm-btn" data-confirm="Bu görseli galeriden silmek istediğinizden emin misiniz?">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
