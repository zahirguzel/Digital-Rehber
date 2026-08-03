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

// ----------------------------------------------------
// PROCESS ACTIONS (Insert, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $title       = trim($_POST['title']);
        $slug        = trim($_POST['slug']);
        $icon        = trim($_POST['icon']) ?: 'fa-solid fa-cube';
        $subject     = trim($_POST['subject']);
        $description = trim($_POST['description']);
        $cta_type    = in_array(trim($_POST['cta_type'] ?? ''), ['iletisim', 'whatsapp']) ? trim($_POST['cta_type']) : 'iletisim';
        $cta_url     = $cta_type === 'whatsapp' ? trim($_POST['cta_url'] ?? '') : null;
        
        if (empty($title) || empty($slug) || empty($description) || empty($subject)) {
            $errorMsg = "Başlık, Slug, İletişim Konusu ve Açıklama alanları zorunludur.";
        } else {
            try {
                // Check if slug is unique
                $stmtCheck = $db->query("SELECT COUNT(*) FROM services WHERE slug = ? AND id != ?", [$slug, $id]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $errorMsg = "Bu URL (slug) başka bir hizmet tarafından kullanılıyor.";
                } else {
                    if ($_POST['action'] === 'add') {
                        $db->query("INSERT INTO services (title, slug, icon, description, subject, cta_url, cta_type) VALUES (?, ?, ?, ?, ?, ?, ?)", [$title, $slug, $icon, $description, $subject, $cta_url, $cta_type]);
                        $newId = $db->getPDO()->lastInsertId();
                        if (function_exists('logAction')) logAction('create', 'services', $title, $newId);
                        $successMsg = "Hizmet başarıyla eklendi.";
                        $action = 'list';
                    } else {
                        $db->query("UPDATE services SET title = ?, slug = ?, icon = ?, description = ?, subject = ?, cta_url = ?, cta_type = ? WHERE id = ?", [$title, $slug, $icon, $description, $subject, $cta_url, $cta_type, $id]);
                        if (function_exists('logAction')) logAction('update', 'services', $title, $id);
                        $successMsg = "Hizmet başarıyla güncellendi.";
                        $action = 'list';
                    }
                }
            } catch (Exception $e) {
                $errorMsg = "Hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Delete Service
if ($action === 'delete' && $id > 0) {
    try {
        $stmtSrv = $db->query("SELECT title FROM services WHERE id = ?", [$id]);
        $srvTitle = $stmtSrv->fetchColumn() ?: 'Hizmet ID: ' . $id;

        $stmtDel = $db->query("DELETE FROM services WHERE id = ?", [$id]);
        if (function_exists('logAction')) logAction('delete', 'services', $srvTitle, $id);
        $successMsg = "Hizmet başarıyla silindi.";
    } catch (Exception $e) {
        $errorMsg = "Hizmet silinemedi: " . $e->getMessage();
    }
    $action = 'list';
}

// ----------------------------------------------------
// FETCH VIEW DATA
// ----------------------------------------------------
$service = null;
if ($action === 'edit' && $id > 0) {
    try {
        $stmtSrv = $db->query("SELECT * FROM services WHERE id = ?", [$id]);
        $service = $stmtSrv->fetch();
        if (!$service) {
            $errorMsg = "Düzenlenecek hizmet bulunamadı.";
            $action = 'list';
        }
    } catch (Exception $e) {
        $errorMsg = "Hizmet yükleme hatası.";
        $action = 'list';
    }
}

$services = [];
if ($action === 'list') {
    try {
        $services = $db->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();
    } catch (Exception $e) {
        $errorMsg = "Hizmet listesi yüklenemedi: " . $e->getMessage();
    }
}

$pageTitle = 'Hizmet Yönetimi';
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
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-handshake-angle me-2 text-primary"></i> Dijital Hizmetlerimiz</h5>
            <a href="services.php?action=new" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-plus me-1"></i> Yeni Hizmet Ekle</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 100px;">İkon</th>
                            <th>Hizmet Başlığı</th>
                            <th>URL Anchor Slug</th>
                            <th>Form Konusu (Subject)</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
if (empty($services)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Henüz hizmet eklenmemiş.</td>
                            </tr>
                        <?php
else: ?>
                            <?php foreach ($services as $srv): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="bg-navy bg-opacity-10 text-navy rounded d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 20px;">
                                            <i class="<?= htmlspecialchars($srv['icon'] ?: 'fa-solid fa-cube') ?>"></i>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-navy"><?= htmlspecialchars($srv['title']) ?></td>
                                    <td class="font-monospace text-muted small">#<?= htmlspecialchars($srv['slug']) ?></td>
                                    <td>
                                        <?php if (($srv['cta_type'] ?? 'iletisim') === 'whatsapp' && !empty($srv['cta_url'])): ?>
                                            <span class="badge bg-success px-3 py-2 rounded-1"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2 rounded-1"><?= htmlspecialchars($srv['subject']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group gap-2">
                                            <a href="services.php?action=edit&id=<?= $srv['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Düzeyle"><i class="fa-solid fa-pen"></i></a>
                                            <a href="services.php?action=delete&id=<?= $srv['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu hizmeti silmek istediğinizden emin misiniz? Bu işlem geri alınamaz." data-confirm-title="Hizmeti Sil" title="Sil"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
                <?= $action === 'new' ? 'Yeni Hizmet Ekle' : htmlspecialchars($service['title']) . ' - Düzenle' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">
                
                <div class="row g-4">
                    <!-- Title -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Hizmet Başlığı <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="srv_title" class="form-control" required value="<?= htmlspecialchars($service['title'] ?? '') ?>" placeholder="Örn: Google Harita Kaydı">
                    </div>
                    
                    <!-- Slug URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">URL Anchor / Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="srv_slug" class="form-control font-monospace" required value="<?= htmlspecialchars($service['slug'] ?? '') ?>" placeholder="google-harita">
                        <span class="text-muted small" style="font-size: 11px;">Sayfa içi yönlendirme kimliği (Anchor ID). Örn: hizmetlerimiz.php#google-harita</span>
                    </div>

                    <!-- Icon FontAwesome Class -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">FontAwesome İkon Sınıfı <span class="text-danger">*</span></label>
                        <input type="text" name="icon" class="form-control font-monospace" required value="<?= htmlspecialchars($service['icon'] ?? 'fa-solid fa-cube') ?>" placeholder="Örn: fa-solid fa-map-location-dot">
                        <span class="text-muted small" style="font-size: 11px;">Hizmeti simgeleyen tam ikon kodu. Örnek: `fa-solid fa-map-location-dot`</span>
                    </div>

                    <!-- Form Subject Prefill -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">İletişim Formu Ön Başlığı (Subject) <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required value="<?= htmlspecialchars($service['subject'] ?? '') ?>" placeholder="Örn: Google Haritalar Kurulumu">
                        <span class="text-muted small" style="font-size: 11px;">Ziyaretçi form butonuna tıkladığında iletişim sayfasındaki konu alanına otomatik yazılacak metin.</span>
                    </div>

                    <!-- Description -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Açıklama <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="5" required placeholder="Hizmet detaylarını ve sağladığı faydaları açıklayınız..."><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                    </div>

                    <!-- CTA Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">"Hemen Bilgi Al" Butonu Tipi</label>
                        <select name="cta_type" id="srv_cta_type" class="form-select">
                            <option value="iletisim" <?= ($service['cta_type'] ?? 'iletisim') === 'iletisim' ? 'selected' : '' ?>>Sitedeki İletişim Formu</option>
                            <option value="whatsapp" <?= ($service['cta_type'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp'a Yönlendir</option>
                        </select>
                        <span class="text-muted small" style="font-size: 11px;">Buton tıklanınca nereye gitsin?</span>
                    </div>

                    <!-- CTA URL (WhatsApp) -->
                    <div class="col-md-6" id="cta_url_wrap" style="<?= ($service['cta_type'] ?? 'iletisim') !== 'whatsapp' ? 'display:none' : '' ?>">
                        <label class="form-label fw-semibold">WhatsApp URL</label>
                        <input type="url" name="cta_url" id="srv_cta_url" class="form-control" value="<?= htmlspecialchars($service['cta_url'] ?? '') ?>" placeholder="https://wa.me/905XXXXXXXXX?text=...">
                        <span class="text-muted small" style="font-size: 11px;">Ziyaretçi WhatsApp'a yönlendirilir. ?text= parametresiyle ön mesaj ekleyebilirsiniz.</span>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 border-top pt-4 d-flex justify-content-end gap-2">
                    <a href="services.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i> İptal</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS helper to auto-generate slugs and toggle CTA URL -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('srv_title');
            const slugInput  = document.getElementById('srv_slug');
            const ctaType    = document.getElementById('srv_cta_type');
            const ctaUrlWrap = document.getElementById('cta_url_wrap');

            <?php if ($action === 'new'): ?>
            titleInput.addEventListener('input', function() {
                let text = titleInput.value;
                slugInput.value = text.toLowerCase()
                                    .replace(/ğ/g, 'g')
                                    .replace(/ü/g, 'u')
                                    .replace(/ş/g, 's')
                                    .replace(/ı/g, 'i')
                                    .replace(/ö/g, 'o')
                                    .replace(/ç/g, 'c')
                                    .replace(/[^a-z0-9]/g, '-')
                                    .replace(/-+/g, '-')
                                    .replace(/^-|-$/g, '');
            });
            <?php endif; ?>

            if (ctaType && ctaUrlWrap) {
                ctaType.addEventListener('change', function() {
                    ctaUrlWrap.style.display = this.value === 'whatsapp' ? '' : 'none';
                });
            }
        });
    </script>
<?php
endif; ?>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
