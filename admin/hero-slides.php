<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/logger.php';

$pageTitle = 'Hero Slayt Yönetimi';
$activePage = 'hero-slides.php';

$success = $error = '';

// --- SLAYT SİL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int) ($_POST['slide_id'] ?? 0);
    if ($delId > 0) {
        $pdo->prepare("DELETE FROM hero_slides WHERE id = ?")->execute([$delId]);
        $success = 'Slayt silindi.';
        logAction('delete', 'hero_slides', 'Slide ' . $delId, $delId);
    }
}

// --- SIRAYI GÜNCELLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    $orders = $_POST['order'] ?? [];
    foreach ($orders as $id => $ord) {
        $pdo->prepare("UPDATE hero_slides SET sort_order = ? WHERE id = ?")->execute([(int)$ord, (int)$id]);
    }
    $success = 'Sıralama kaydedildi.';
}

// --- AKTİF/PASİF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $toggleId = (int)($_POST['slide_id'] ?? 0);
    if ($toggleId > 0) {
        $pdo->prepare("UPDATE hero_slides SET is_active = !is_active WHERE id = ?")->execute([$toggleId]);
        $success = 'Durum güncellendi.';
    }
}

// --- EKLE / GÜNCELLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'])) {
    $slideId  = (int)($_POST['slide_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $imgPath  = trim($_POST['image_path'] ?? 'public/images/hero-slider.jpg');
    $btn1Text = trim($_POST['button_text'] ?? '');
    $btn1Url  = trim($_POST['button_url'] ?? '');
    $btn2Text = trim($_POST['button2_text'] ?? '');
    $btn2Url  = trim($_POST['button2_url'] ?? '');
    $sortOrd  = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Fotoğraf yükleme
    if (!empty($_FILES['slide_image']['name'])) {
        $uploadDir = __DIR__ . '/../public/images/hero/';
        $processResult = processAndSaveImage($_FILES['slide_image'], $uploadDir, 'hero_');
        
        if ($processResult['success']) {
            $imgPath = 'public/images/hero/' . $processResult['filename'];
        } else {
            $error = $processResult['error'];
        }
    }

    if (empty($title)) {
        $error = 'Başlık zorunludur.';
    } elseif (empty($error)) {
        if ($slideId > 0) {
            $pdo->prepare("UPDATE hero_slides SET title=?, subtitle=?, description=?, image_path=?, button_text=?, button_url=?, button2_text=?, button2_url=?, sort_order=?, is_active=? WHERE id=?")
                ->execute([$title, $subtitle, $desc, $imgPath, $btn1Text, $btn1Url, $btn2Text, $btn2Url, $sortOrd, $isActive, $slideId]);
            $success = 'Slayt güncellendi.';
            logAction('update', 'hero_slides', $title, $slideId);
        } else {
            $pdo->prepare("INSERT INTO hero_slides (title, subtitle, description, image_path, button_text, button_url, button2_text, button2_url, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$title, $subtitle, $desc, $imgPath, $btn1Text, $btn1Url, $btn2Text, $btn2Url, $sortOrd, $isActive]);
            $success = 'Yeni slayt eklendi.';
            logAction('create', 'hero_slides', $title, null);
        }
    }
}

// Düzenleme modunda mevcut slaytı getir
$editSlide = null;
if (isset($_GET['edit'])) {
    $editSlide = $pdo->prepare("SELECT * FROM hero_slides WHERE id = ?");
    $editSlide->execute([(int)$_GET['edit']]);
    $editSlide = $editSlide->fetch();
}

// Tüm slaytları getir
$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="admin-page-title"><i class="fa-solid fa-images me-2 text-primary"></i>Hero Slayt Yönetimi</h1>
        <p class="text-muted mb-0">Ana sayfanın üst carousel/slider bölümünü buradan yönetin.</p>
    </div>
    <a href="?add=1" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Yeni Slayt Ekle</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fa-solid fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Ekleme / Düzenleme Formu -->
<?php if (isset($_GET['add']) || $editSlide): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-primary text-white rounded-top-4 py-3">
        <h5 class="mb-0"><i class="fa-solid fa-<?= $editSlide ? 'pen-to-square' : 'plus' ?> me-2"></i><?= $editSlide ? 'Slayt Düzenle' : 'Yeni Slayt Ekle' ?></h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editSlide ? 'edit' : 'add' ?>">
            <?php if ($editSlide): ?><input type="hidden" name="slide_id" value="<?= $editSlide['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Başlık <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editSlide['title'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Alt Başlık / Rozet</label>
                    <input type="text" name="subtitle" class="form-control" value="<?= htmlspecialchars($editSlide['subtitle'] ?? '') ?>" placeholder="📍 Kıbrıs BÖLGESİ">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Açıklama</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editSlide['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Arka Plan Fotoğrafı (yükle)</label>
                    <input type="file" name="slide_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">Max 5MB · JPG, PNG, WebP · Önerilen: 1920×800px</div>
                    <?php if (!empty($editSlide['image_path'])): 
                        $editSlideImgUrl = strpos($editSlide['image_path'], 'http') === 0 ? $editSlide['image_path'] : '/' . ltrim($editSlide['image_path'], '/');
                    ?>
                    <div class="mt-2"><img src="<?= htmlspecialchars($editSlideImgUrl) ?>" alt="Mevcut" style="height: 80px; border-radius: 8px; object-fit: cover;"></div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Fotoğraf URL (veya yolu)</label>
                    <input type="text" name="image_path" class="form-control" value="<?= htmlspecialchars($editSlide['image_path'] ?? '/digitalrehber/public/images/hero-slider.jpg') ?>" placeholder="/digitalrehber/public/images/hero-slider.jpg">
                    <div class="form-text">Yukarıya fotoğraf yüklerseniz bu alan otomatik güncellenir.</div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">1. Buton Metni</label>
                    <input type="text" name="button_text" class="form-control" value="<?= htmlspecialchars($editSlide['button_text'] ?? '') ?>" placeholder="İşletme Ara">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">1. Buton URL</label>
                    <input type="text" name="button_url" class="form-control" value="<?= htmlspecialchars($editSlide['button_url'] ?? '') ?>" placeholder="/esnaflar">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">2. Buton Metni</label>
                    <input type="text" name="button2_text" class="form-control" value="<?= htmlspecialchars($editSlide['button2_text'] ?? '') ?>" placeholder="İşletmemi Ekle">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">2. Buton URL</label>
                    <input type="text" name="button2_url" class="form-control" value="<?= htmlspecialchars($editSlide['button2_url'] ?? '') ?>" placeholder="/isletme">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Sıra No</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)($editSlide['sort_order'] ?? count($slides) + 1) ?>" min="0">
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($editSlide['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save me-2"></i>Kaydet</button>
                    <a href="hero-slides.php" class="btn btn-outline-secondary px-4">İptal</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Slayt Listesi -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Önizleme</th>
                        <th>Başlık</th>
                        <th>Alt Başlık</th>
                        <th class="text-center" style="width: 80px;">Sıra</th>
                        <th class="text-center" style="width: 90px;">Durum</th>
                        <th class="text-end" style="width: 160px;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slides)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">Henüz slayt eklenmemiş. <a href="?add=1">İlk slaytı ekle →</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($slides as $slide): ?>
                    <tr>
                        <td>
                            <?php 
                                $slideImgUrl = strpos($slide['image_path'], 'http') === 0 ? $slide['image_path'] : '/' . ltrim($slide['image_path'], '/');
                            ?>
                            <img src="<?= htmlspecialchars($slideImgUrl) ?>" alt=""
                                style="width: 70px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;"
                                onerror="this.src='data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'70\' height=\'44\'><rect fill=\'%23e2e8f0\' width=\'70\' height=\'44\'/><text x=\'50%25\' y=\'55%25\' text-anchor=\'middle\' fill=\'%23999\' font-size=\'10\'>Yok</text></svg>'">
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(mb_substr($slide['title'], 0, 60)) ?></div>
                            <?php if ($slide['button_text']): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small"><?= htmlspecialchars($slide['button_text']) ?> → <?= htmlspecialchars($slide['button_url']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars(mb_substr($slide['subtitle'] ?? '', 0, 50)) ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?= (int)$slide['sort_order'] ?></span>
                        </td>
                        <td class="text-center">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $slide['is_active'] ? 'btn-success' : 'btn-secondary' ?>" style="min-width: 70px;">
                                    <?= $slide['is_active'] ? 'Aktif' : 'Pasif' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <a href="?edit=<?= $slide['id'] ?>" class="btn btn-sm btn-outline-primary" title="Düzenle"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bu slaytı silmek istediğinizden emin misiniz?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="slide_id" value="<?= $slide['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Sil"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
