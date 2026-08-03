<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$successMsg = '';
$errorMsg = '';

// Helper function for uploading images
function handleUpload($fileKey, $fallbackUrlKey, $currentValue = '') {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES[$fileKey]['name']));
        $targetDir = '../public/images/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $fileName)) {
            return $fileName;
        }
    }
    
    if (!empty($_POST[$fallbackUrlKey])) {
        return trim($_POST[$fallbackUrlKey]);
    }
    
    return $currentValue;
}

// ----------------------------------------------------
// PROCESS ACTIONS (Insert, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $title = trim($_POST['title']);
        $target_url = trim($_POST['target_url']);
        $position = trim($_POST['position']) ?: 'home_banner';
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Handle upload
        $currentImage = '';
        if ($_POST['action'] === 'edit') {
            $stmtCur = $db->query("SELECT image_path FROM advertisements WHERE id = ?", [$id]);
            $currentImage = $stmtCur->fetchColumn() ?: '';
        }
        
        $image_path = handleUpload('ad_file', 'ad_url', $currentImage);
        
        if (empty($image_path)) {
            $errorMsg = "Reklam görseli yüklenmesi veya görsel URL'si girilmesi zorunludur.";
        } else {
            try {
                if ($_POST['action'] === 'add') {
                    $stmtIns = $db->query("INSERT INTO advertisements (title, image_path, target_url, position, active) VALUES (?, ?, ?, ?, ?)", [$title, $image_path, $target_url, $position, $active]);
                    $newId = $db->getPDO()->lastInsertId();
                    if (function_exists('logAction')) logAction('create', 'advertisements', $title, $newId);
                    $successMsg = "Reklam başarıyla eklendi.";
                    $action = 'list';
                } else {
                    $stmtUp = $db->query("UPDATE advertisements SET title = ?, image_path = ?, target_url = ?, position = ?, active = ? WHERE id = ?", [$title, $image_path, $target_url, $position, $active, $id]);
                    if (function_exists('logAction')) logAction('update', 'advertisements', $title, $id);
                    
                    if ($image_path !== $currentImage && !empty($currentImage) && strpos($currentImage, 'http') !== 0) {
                        @unlink('../public/images/' . $currentImage);
                    }
                    
                    $successMsg = "Reklam başarıyla güncellendi.";
                    $action = 'list';
                }
            } catch (Exception $e) {
                $errorMsg = "İşlem sırasında hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Delete Advertisement
if ($action === 'delete' && $id > 0) {
    try {
        $stmtCur = $db->query("SELECT title, image_path FROM advertisements WHERE id = ?", [$id]);
        $curData = $stmtCur->fetch();
        $currentImage = $curData['image_path'] ?? '';
        $delTitle = $curData['title'] ?? 'Reklam ID: ' . $id;

        $stmtDel = $db->query("DELETE FROM advertisements WHERE id = ?", [$id]);

        if (!empty($currentImage) && strpos($currentImage, 'http') !== 0) {
            @unlink('../public/images/' . $currentImage);
        }
        
        if (function_exists('logAction')) logAction('delete', 'advertisements', $delTitle, $id);

        $successMsg = "Reklam başarıyla silindi.";
    } catch (Exception $e) {
        $errorMsg = "Reklam silinemedi: " . $e->getMessage();
    }
    $action = 'list';
}

// ----------------------------------------------------
// FETCH VIEW DATA
// ----------------------------------------------------
$ad = null;
if ($action === 'edit' && $id > 0) {
    try {
        $stmtAd = $db->query("SELECT * FROM advertisements WHERE id = ?", [$id]);
        $ad = $stmtAd->fetch();
        if (!$ad) {
            $errorMsg = "Düzenlenecek reklam bulunamadı.";
            $action = 'list';
        }
    } catch (Exception $e) {
        $errorMsg = "Reklam yüklenemedi.";
        $action = 'list';
    }
}

$ads = [];
if ($action === 'list') {
    try {
        $ads = $db->query("SELECT * FROM advertisements ORDER BY id DESC")->fetchAll();
    } catch (Exception $e) {
        $errorMsg = "Reklam listesi yüklenemedi: " . $e->getMessage();
    }
}

$pageTitle = 'Reklam Yönetimi';
include 'includes/header.php';
?>

<!-- Alerts -->
<?php
if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<?php
if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<!-- LIST VIEW -->
<?php
if ($action === 'list'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-rectangle-ad me-2 text-primary"></i> Banner & Reklam Yönetimi</h5>
            <a href="ads.php?action=new" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-plus me-1"></i> Yeni Reklam Ekle</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Önizleme</th>
                            <th>Reklam Başlığı</th>
                            <th>Konum</th>
                            <th>Yayın Durumu</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
if (empty($ads)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Kayıtlı reklam afişi bulunamadı.</td>
                            </tr>
                        <?php
else: ?>
                            <?php
foreach ($ads as $row): ?>
                                <?php
$imgSrc = (strpos($row['image_path'], 'http') === 0) ? $row['image_path'] : '../public/images/' . $row['image_path'];
                                ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="border rounded overflow-hidden" style="width: 100px; height: 50px;">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Banner Preview" class="w-100 h-100 object-fit-cover">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-navy"><?= htmlspecialchars($row['title'] ?: 'Başlıksız Reklam') ?></div>
                                        <a href="<?= htmlspecialchars($row['target_url']) ?>" target="_blank" class="small text-muted text-decoration-none">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= htmlspecialchars($row['target_url']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($row['position']) ?></span>
                                    </td>
                                    <td>
                                        <?php
if ($row['active']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 px-3 py-2 rounded-1 fw-bold"><i class="fa-solid fa-circle-play me-1"></i> Aktif</span>
                                        <?php
else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 px-3 py-2 rounded-1 fw-bold"><i class="fa-solid fa-circle-pause me-1"></i> Pasif</span>
                                        <?php
endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group gap-2">
                                            <a href="ads.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                            <a href="ads.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu reklam afişini kalıcı olarak silmek istediğinizden emin misiniz?" data-confirm-title="Reklamı Sil"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php
endforeach; ?>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- ADD NEW OR EDIT VIEW -->
<?php
elseif ($action === 'new' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid <?= $action === 'new' ? 'fa-plus text-success' : 'fa-pen text-primary' ?> me-2"></i>
                <?= $action === 'new' ? 'Yeni Reklam Ekle' : htmlspecialchars($ad['title']) . ' - Düzenle' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">
                
                <div class="row g-4">
                    <!-- Title -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Reklam / Kampanya Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($ad['title'] ?? '') ?>" placeholder="Örn: Yaz Sezonu İndirimleri">
                    </div>
                    
                    <!-- Position -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Görünüm Pozisyonu</label>
                        <select name="position" class="form-select">
                            <option value="home_banner" <?= (isset($ad['position']) && $ad['position'] === 'home_banner') ? 'selected' : '' ?>>Ana Sayfa Banner</option>
                            <option value="sidebar" <?= (isset($ad['position']) && $ad['position'] === 'sidebar') ? 'selected' : '' ?>>Yan Panel (Sidebar)</option>
                            <option value="esnaf_alt" <?= (isset($ad['position']) && $ad['position'] === 'esnaf_alt') ? 'selected' : '' ?>>Esnaf Sayfası - Sağ Alt (Ayrıntıların Altı)</option>
                        </select>
                    </div>

                    <!-- Target URL Link -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Yönlendirilecek URL (Target Link)</label>
                        <input type="url" name="target_url" class="form-control" value="<?= htmlspecialchars($ad['target_url'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <!-- Image Upload -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Reklam Afişi Görseli</label>
                        <input type="file" name="ad_file" class="form-control mb-2">
                        <input type="text" name="ad_url" class="form-control form-control-sm" placeholder="Veya harici görsel URL adresi yapıştırın..." value="<?= htmlspecialchars((!empty($ad['image_path']) && strpos($ad['image_path'], 'http') === 0) ? $ad['image_path'] : '') ?>">
                        <?php
if (!empty($ad['image_path']) && strpos($ad['image_path'], 'http') !== 0): ?>
                            <div class="small text-success mt-1"><i class="fa-solid fa-file-image me-1"></i> Mevcut dosya: <?= htmlspecialchars($ad['image_path']) ?></div>
                        <?php
endif; ?>
                    </div>

                    <!-- Active status switch -->
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="active" id="activeAdSwitch" <?= (!isset($ad['active']) || $ad['active']) ? 'checked' : '' ?> style="transform: scale(1.3); margin-right: 10px; cursor:pointer;">
                            <label class="form-check-label fw-semibold" for="activeAdSwitch" style="cursor:pointer;">Yayında / Aktif</label>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 border-top pt-4 d-flex justify-content-end gap-2">
                    <a href="ads.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i> İptal</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
<?php
endif; ?>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
