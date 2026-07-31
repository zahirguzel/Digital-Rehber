<?php
ob_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/campaign-helpers.php';

$db = Database::getInstance();
$bizId = (int) ($_SESSION['biz_id'] ?? 0);
$bizName = $_SESSION['biz_name'] ?? 'İşletme';
$pageTitle = 'Kampanyalarım';

// Helper function for uploading campaign cover image
function isletmeCampUpload($fileKey, $urlKey, $current = '')
{
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES[$fileKey]['name']));
        $targetDir = __DIR__ . '/../public/images/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $fileName)) {
            return $fileName;
        }
    }
    if (!empty($_POST[$urlKey])) {
        return trim($_POST[$urlKey]);
    }
    return $current;
}

// Helper function to record history/log entries in admin_logs and file log
function logBizCampaignAction($action, $targetName, $targetId = null)
{
    global $db;
    try {
        $ip = SecurityHelper::getClientIP();
        $bizName = $_SESSION['biz_name'] ?? 'İşletme';
        $username = 'İşletme (' . $bizName . ')';
        $db->query(
            "INSERT INTO admin_logs (admin_id, admin_username, action, module, target_name, target_id, ip_address)
             VALUES (NULL, ?, ?, 'kampanya_isletme', ?, ?, ?)",
            [$username, $action, $targetName, $targetId, $ip]
        );
        Logger::info("Business campaign action: $action", [
            'business' => $bizName,
            'campaign' => $targetName,
            'ip' => $ip
        ]);
    } catch (Exception $e) {}
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : '';
$errorMsg = '';

// Get default business district
$bizDistrict = 'Girne';
try {
    $bizRow = $db->query("SELECT district FROM businesses WHERE id = ?", [$bizId])->fetch();
    if ($bizRow && !empty($bizRow['district'])) {
        $bizDistrict = $bizRow['district'];
    }
} catch (Exception $e) {}

// Handle Form Submission (new or edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'], true)) {
    $title = trim($_POST['title'] ?? '');
    $campaign_type = trim($_POST['campaign_type'] ?? 'indirim');
    $summary = trim($_POST['summary'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discount_label = trim($_POST['discount_label'] ?? '');
    $original_price = trim($_POST['original_price'] ?? '');
    $sale_price = trim($_POST['sale_price'] ?? '');
    $start_date = trim($_POST['start_date'] ?? date('Y-m-d'));
    $end_date = trim($_POST['end_date'] ?? '');
    $end_date_val = $end_date !== '' ? $end_date : null;

    if ($title === '') {
        $errorMsg = 'Lütfen kampanya başlığını girin.';
    } else {
        try {
            if ($_POST['action'] === 'add') {
                $slugBase = preg_replace('/[^a-z0-9]+/i', '-', strtolower(str_replace(['ı','ğ','ü','ş','ö','ç','İ','Ğ','Ü','Ş','Ö','Ç'], ['i','g','u','s','o','c','i','g','u','s','o','c'], $title)));
                $slugBase = trim($slugBase, '-');
                $slug = $slugBase;
                $counter = 1;
                while ($db->query('SELECT COUNT(*) FROM campaigns WHERE slug = ?', [$slug])->fetchColumn() > 0) {
                    $slug = $slugBase . '-' . $counter++;
                }
                $cover_image_path = isletmeCampUpload('cover_file', 'cover_url');
                $is_published = 0; // Requires admin approval by default
                $is_featured = 0;

                $sql = 'INSERT INTO campaigns (title, slug, business_id, district, campaign_type, summary, description, discount_label, original_price, sale_price, start_date, end_date, cover_image_path, cta_url, is_featured, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $db->query($sql, [
                    $title, $slug, $bizId, $bizDistrict, $campaign_type, $summary, $description,
                    $discount_label, $original_price !== '' ? $original_price : null,
                    $sale_price !== '' ? $sale_price : null, $start_date, $end_date_val,
                    $cover_image_path, null, $is_featured, $is_published
                ]);
                $newCampId = $db->getPDO()->lastInsertId();
                logBizCampaignAction('create', $title, $newCampId);
                $successMsg = 'Kampanyanız eklendi! Yönetici onayının ardından canlı yayına alınacaktır.';
            } else {
                // Ensure the campaign belongs to this business
                $cur = $db->query("SELECT * FROM campaigns WHERE id = ? AND business_id = ?", [$id, $bizId])->fetch();
                if (!$cur) {
                    $errorMsg = 'Bu kampanyayı düzenleme yetkiniz yok veya kampanya bulunamadı.';
                } else {
                    $cover_image_path = isletmeCampUpload('cover_file', 'cover_url', $cur['cover_image_path']);
                    $sql = 'UPDATE campaigns SET title=?, campaign_type=?, summary=?, description=?, discount_label=?, original_price=?, sale_price=?, start_date=?, end_date=?, cover_image_path=? WHERE id=? AND business_id=?';
                    $db->query($sql, [
                        $title, $campaign_type, $summary, $description, $discount_label,
                        $original_price !== '' ? $original_price : null,
                        $sale_price !== '' ? $sale_price : null,
                        $start_date, $end_date_val, $cover_image_path, $id, $bizId
                    ]);
                    logBizCampaignAction('update', $title, $id);
                    $successMsg = 'Kampanya bilgileriniz güncellendi.';
                }
            }
            if (empty($errorMsg)) {
                // PRG: POST sonrası redirect ile çift gönderim önlenir
                $redirectMsg = urlencode($successMsg);
                header('Location: campaigns.php?success=' . $redirectMsg);
                exit;
            }
        } catch (Exception $e) {
            $errorMsg = 'Hata: ' . $e->getMessage();
        }
    }
}

// Handle Delete
if ($action === 'delete' && $id > 0) {
    try {
        $cur = $db->query("SELECT title FROM campaigns WHERE id = ? AND business_id = ?", [$id, $bizId])->fetch();
        if ($cur) {
            $db->query("DELETE FROM campaigns WHERE id = ? AND business_id = ?", [$id, $bizId]);
            logBizCampaignAction('delete', $cur['title'] . " (ID: $id)", $id);
            header('Location: campaigns.php?success=' . urlencode('Kampanya silindi.'));
            exit;
        } else {
            $errorMsg = 'Silmek istediğiniz kampanya bulunamadı.';
        }
    } catch (Exception $e) {
        $errorMsg = 'Silinemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

// Fetch Campaign for Edit
$campaign = null;
if ($action === 'edit' && $id > 0) {
    $campaign = $db->query("SELECT * FROM campaigns WHERE id = ? AND business_id = ?", [$id, $bizId])->fetch();
    if (!$campaign) {
        $errorMsg = 'Kampanya bulunamadı.';
        $action = 'list';
    }
}

// Fetch List and Stats
$campaigns = [];
$stats = ['total' => 0, 'published' => 0, 'pending' => 0, 'indirim' => 0];
if ($action === 'list') {
    try {
        $campaigns = $db->query("SELECT * FROM campaigns WHERE business_id = ? ORDER BY id DESC", [$bizId])->fetchAll();
        $stats['total'] = count($campaigns);
        foreach ($campaigns as $c) {
            if ($c['is_published']) {
                $stats['published']++;
            } else {
                $stats['pending']++;
            }
            if (($c['campaign_type'] ?? '') === 'indirim') {
                $stats['indirim']++;
            }
        }
    } catch (Exception $e) {}
}

$types = campaignTypes();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--navy);">
            <i class="fa-solid fa-tags me-2" style="color:var(--primary);"></i>
            <?= htmlspecialchars($bizName) ?> - Kampanyalarım
        </h5>
        <p class="text-muted small mb-0">İşletmenize ait indirim, fırsat ve özel gün kampanyalarını ekleyin, yönetin.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($action !== 'list'): ?>
            <a href="campaigns.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kampanyalara Dön
            </a>
        <?php else: ?>
            <a href="../kampanyalar" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Rehberde Göster
            </a>
            <a href="campaigns.php?action=new" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus me-1"></i> Yeni Kampanya Ekle
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="biz-panel-stat-card h-100">
                <div class="biz-panel-stat-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;">
                    <i class="fa-solid fa-tags"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value"><?= $stats['total'] ?></div>
                    <div class="biz-panel-stat-label">Toplam Kampanya</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="biz-panel-stat-card h-100">
                <div class="biz-panel-stat-icon" style="background:rgba(16,185,129,0.1);color:#10B981;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value"><?= $stats['published'] ?></div>
                    <div class="biz-panel-stat-label">Yayında Olanlar</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="biz-panel-stat-card h-100">
                <div class="biz-panel-stat-icon" style="background:rgba(245,158,11,0.1);color:#F59E0B;">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value"><?= $stats['pending'] ?></div>
                    <div class="biz-panel-stat-label">Onay Bekleyenler</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="biz-panel-stat-card h-100">
                <div class="biz-panel-stat-icon" style="background:rgba(239,68,68,0.1);color:#EF4444;">
                    <i class="fa-solid fa-percent"></i>
                </div>
                <div>
                    <div class="biz-panel-stat-value"><?= $stats['indirim'] ?></div>
                    <div class="biz-panel-stat-label">İndirim Fırsatları</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Kampanya Başlığı</th>
                        <th>Türü</th>
                        <th>İndirim / Fiyat</th>
                        <th>Geçerlilik Tarihi</th>
                        <th>Durum</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-tags fs-1 d-block mb-3 opacity-25"></i>
                                Henüz işletmenize ait bir kampanya eklememişsiniz.
                                <div class="mt-3">
                                    <a href="campaigns.php?action=new" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-plus me-1"></i> İlk Kampanyayı Ekle
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($campaigns as $camp): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($camp['title']) ?></div>
                                <?php if (!empty($camp['summary'])): ?>
                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;"><?= htmlspecialchars($camp['summary']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars(getCampaignTypeLabel($camp['campaign_type'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($camp['discount_label'])): ?>
                                    <span class="badge bg-danger mb-1"><?= htmlspecialchars($camp['discount_label']) ?></span><br>
                                <?php endif; ?>
                                <?php if (!empty($camp['sale_price']) || !empty($camp['original_price'])): ?>
                                    <small class="fw-bold text-success"><?= htmlspecialchars($camp['sale_price'] ?: $camp['original_price']) ?></small>
                                    <?php if (!empty($camp['original_price']) && !empty($camp['sale_price'])): ?>
                                        <s class="text-muted small ms-1"><?= htmlspecialchars($camp['original_price']) ?></s>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars(formatCampaignDateRange($camp['start_date'], $camp['end_date'])) ?></small>
                            </td>
                            <td>
                                <?php if ($camp['is_published']): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Yayında</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Onay Bekliyor</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="../kampanya/<?= htmlspecialchars($camp['slug']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Sayfayı Görüntüle">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="campaigns.php?action=edit&id=<?= $camp['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Düzenle">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="campaigns.php?action=delete&id=<?= $camp['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Bu kampanyayı silmek istediğinize emin misiniz?')" title="Sil">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array($action, ['new', 'edit'], true)): ?>
    <?php $camp = $campaign ?: []; ?>
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold">
                <?= $action === 'new' ? '<i class="fa-solid fa-plus-circle me-2 text-primary"></i>Yeni Kampanya Ekle' : '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Kampanyayı Düzenle' ?>
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="campaigns.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Kampanya Başlığı <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="<?= htmlspecialchars($camp['title'] ?? '') ?>"
                               placeholder="Örn: Nakit Ödemelerde %20 İndirim Fırsatı">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kampanya Türü</label>
                        <select name="campaign_type" class="form-select">
                            <?php foreach ($types as $s => $l): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= (($camp['campaign_type'] ?? 'indirim') === $s) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">İndirim Etiketi <small class="text-muted">(Opsiyonel)</small></label>
                        <input type="text" name="discount_label" class="form-control"
                               value="<?= htmlspecialchars($camp['discount_label'] ?? '') ?>"
                               placeholder="Örn: %20 İNDİRİM, 3 AL 2 ÖDE">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Eski Fiyat <small class="text-muted">(Opsiyonel)</small></label>
                        <input type="text" name="original_price" class="form-control"
                               value="<?= htmlspecialchars($camp['original_price'] ?? '') ?>"
                               placeholder="Örn: 1500 TL">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">İndirimli / Yeni Fiyat <small class="text-muted">(Opsiyonel)</small></label>
                        <input type="text" name="sale_price" class="form-control"
                               value="<?= htmlspecialchars($camp['sale_price'] ?? '') ?>"
                               placeholder="Örn: 1200 TL">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Başlangıç Tarihi <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" required
                               value="<?= htmlspecialchars($camp['start_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Bitiş Tarihi <small class="text-muted">(Boş bırakılırsa süresiz)</small></label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?= htmlspecialchars($camp['end_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Kısa Özet <small class="text-muted">(Kart üzerinde görünür)</small></label>
                        <input type="text" name="summary" class="form-control" maxlength="250"
                               value="<?= htmlspecialchars($camp['summary'] ?? '') ?>"
                               placeholder="Kampanyanızı bir cümle ile özetleyin...">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Detaylı Açıklama <small class="text-muted">(Kalın, büyük başlık, liste gibi biçimler ekleyebilirsiniz)</small></label>
                        <div id="quill-editor" style="min-height:180px; background:#fff; border:1px solid #dee2e6; border-radius:0 0 .375rem .375rem;"></div>
                        <textarea name="description" id="description-hidden" class="d-none"><?= htmlspecialchars($camp['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Kapak Görseli <small class="text-muted">(Dosya Yükle veya İnternet URL'si)</small></label>
                        <?php if (!empty($camp['cover_image_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= htmlspecialchars(getCampaignImageUrl($camp['cover_image_path'])) ?>" alt="Kapak" class="img-thumbnail" style="max-height: 120px;">
                            </div>
                        <?php endif; ?>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="file" name="cover_file" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <input type="url" name="cover_url" class="form-control" placeholder="https://..." value="<?= (strpos($camp['cover_image_path'] ?? '', 'http') === 0) ? htmlspecialchars($camp['cover_image_path']) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="campaigns.php" class="btn btn-light border px-4">İptal</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= $action === 'new' ? 'Kampanyayı Kaydet' : 'Değişiklikleri Güncelle' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
