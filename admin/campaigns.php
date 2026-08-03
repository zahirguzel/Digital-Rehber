<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/campaign-helpers.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = '';
$errorMsg = '';

function campHandleUpload($fileKey, $urlKey, $current = '')
{
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
    if (!empty($_POST[$urlKey])) {
        return trim($_POST[$urlKey]);
    }
    return $current;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'], true)) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $campaign_type = trim($_POST['campaign_type'] ?? 'indirim');
    $summary = trim($_POST['summary'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discount_label = trim($_POST['discount_label'] ?? '');
    $original_price = trim($_POST['original_price'] ?? '');
    $sale_price = trim($_POST['sale_price'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $cta_url = trim($_POST['cta_url'] ?? '');
    $business_id = (int) ($_POST['business_id'] ?? 0);
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    if ($title === '' || $slug === '' || $start_date === '') {
        $errorMsg = 'Başlık, slug ve başlangıç tarihi zorunludur.';
    } else {
        try {
            if ($business_id > 0) {
                $bizStmt = $db->query('SELECT district FROM businesses WHERE id = ? LIMIT 1', [$business_id]);
                $bizRow = $bizStmt->fetch();
                if ($bizRow && !empty($bizRow['district'])) {
                    $district = $bizRow['district'];
                }
            }
            if ($district === '') {
                $district = 'Hatay';
            }

            $stmtCheck = $db->query('SELECT COUNT(*) FROM campaigns WHERE slug = ? AND id != ?', [$slug, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errorMsg = 'Bu slug başka bir kampanyada kullanılıyor.';
            } else {
                $currentCover = '';
                if ($_POST['action'] === 'edit' && $id > 0) {
                    $cur = $db->query('SELECT cover_image_path FROM campaigns WHERE id = ?', [$id]);
                    $curData = $cur->fetch();
                    if ($curData) {
                        $currentCover = $curData['cover_image_path'];
                    }
                }
                $cover_image_path = campHandleUpload('cover_file', 'cover_url', $currentCover);
                $end_date_val = $end_date !== '' ? $end_date : null;
                $business_id_val = $business_id > 0 ? $business_id : null;

                if ($_POST['action'] === 'add') {
                    $sql = 'INSERT INTO campaigns (title, slug, business_id, district, campaign_type, summary, description, discount_label, original_price, sale_price, start_date, end_date, cover_image_path, cta_url, is_featured, is_published, meta_description, meta_keywords) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $stmt = $db->query($sql, [$title, $slug, $business_id_val, $district, $campaign_type, $summary, $description, $discount_label, $original_price !== '' ? $original_price : null, $sale_price !== '' ? $sale_price : null, $start_date, $end_date_val, $cover_image_path, $cta_url !== '' ? $cta_url : null, $is_featured, $is_published, $meta_description, $meta_keywords]);
                    logAction('create', 'campaigns', $title);
                    $successMsg = 'Kampanya eklendi.';
                } else {
                    $sql = 'UPDATE campaigns SET title=?, slug=?, business_id=?, district=?, campaign_type=?, summary=?, description=?, discount_label=?, original_price=?, sale_price=?, start_date=?, end_date=?, cover_image_path=?, cta_url=?, is_featured=?, is_published=?, meta_description=?, meta_keywords=? WHERE id=?';
                    $stmt = $db->query($sql, [$title, $slug, $business_id_val, $district, $campaign_type, $summary, $description, $discount_label, $original_price !== '' ? $original_price : null, $sale_price !== '' ? $sale_price : null, $start_date, $end_date_val, $cover_image_path, $cta_url !== '' ? $cta_url : null, $is_featured, $is_published, $meta_description, $meta_keywords, $id]);
                    logAction('update', 'campaigns', $title, $id);
                    $successMsg = 'Kampanya güncellendi.';
                }
                $action = 'list';
            }
        } catch (Exception $e) {
            $errorMsg = 'Hata: ' . $e->getMessage();
        }
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $db->getPDO()->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$id]);
        logAction('delete', 'campaigns', 'Kampanya ID: ' . $id, $id);
        $successMsg = 'Kampanya silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silinemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

if ($action === 'toggle_publish' && $id > 0) {
    try {
        $stmt = $db->query('SELECT title, business_id, is_published FROM campaigns WHERE id = ?', [$id]);
        $campRow = $stmt->fetch();
        if ($campRow) {
            $newStatus = $campRow['is_published'] ? 0 : 1;
            $db->query('UPDATE campaigns SET is_published = ?, status = ? WHERE id = ?', [$newStatus, $newStatus ? 'approved' : 'pending', $id]);
            $statusText = $newStatus ? 'Yayınlandı (Onaylandı)' : 'Taslak (Yayından kaldırıldı)';
            logAction('status_change', 'campaigns', $campRow['title'] . ' -> ' . $statusText, $id);
            
            if ($campRow['business_id']) {
                $msg = $newStatus ? "Kampanyanız ('{$campRow['title']}') onaylandı ve yayına alındı." : "Kampanyanız ('{$campRow['title']}') yayından kaldırıldı.";
                $db->getPDO()->prepare("INSERT INTO business_notifications (business_id, type, title, message, is_read) VALUES (?, ?, ?, ?, 0)")->execute([
                    $campRow['business_id'],
                    $newStatus ? 'success' : 'warning',
                    $newStatus ? 'Kampanya Onaylandı' : 'Kampanya Yayından Kaldırıldı',
                    $msg
                ]);
            }
            $successMsg = 'Kampanya durumu güncellendi: ' . $statusText;
        }
    } catch (Exception $e) {
        $errorMsg = 'Durum güncellenemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_campaign') {
    $rejectId = (int)$_POST['reject_id'];
    $reason = trim($_POST['reject_reason'] ?? '');
    try {
        $stmt = $db->query('SELECT title, business_id FROM campaigns WHERE id = ?', [$rejectId]);
        $campRow = $stmt->fetch();
        if ($campRow) {
            $db->query("UPDATE campaigns SET is_published = 0, status = 'rejected', reject_reason = ? WHERE id = ?", [$reason, $rejectId]);
            logAction('status_change', 'campaigns', $campRow['title'] . ' -> Reddedildi (Neden: ' . ($reason ?: 'Belirtilmedi') . ')', $rejectId);
            
            if ($campRow['business_id']) {
                $msg = "Kampanyanız ('{$campRow['title']}') reddedildi. Neden: " . ($reason ?: 'Belirtilmedi');
                $db->getPDO()->prepare("INSERT INTO business_notifications (business_id, type, title, message, is_read) VALUES (?, 'error', 'Kampanya Reddedildi', ?, 0)")->execute([$campRow['business_id'], $msg]);
            }
            $successMsg = 'Kampanya reddedildi.';
        }
    } catch (Exception $e) {
        $errorMsg = 'Red işlemi başarısız: ' . $e->getMessage();
    }
    $action = 'list';
}

$campaign = null;
if (in_array($action, ['edit', 'new'], true)) {
    $allBusinesses = $db->query('SELECT id, name, district FROM businesses ORDER BY name ASC')->fetchAll();
}
if ($action === 'edit' && $id > 0) {
    $stmt = $db->query('SELECT * FROM campaigns WHERE id = ?', [$id]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        $errorMsg = 'Kampanya bulunamadı.';
        $action = 'list';
    }
}

$campaigns = [];
if ($action === 'list') {
    try {
        $campaigns = $db->query(
            'SELECT c.*, b.name AS business_name
             FROM campaigns c
             LEFT JOIN businesses b ON b.id = c.business_id
             ORDER BY c.start_date DESC, c.is_featured DESC'
        )->fetchAll();
    } catch (Exception $e) {
        if ($errorMsg === '') {
            $errorMsg = 'Veritabanı tabloları bulunamadı. migrations/add_campaigns_system.sql dosyasını çalıştırın.';
        }
    }
}

$types = campaignTypes();
$districts = campaignDistricts();
$pageTitle = 'Kampanya Yönetimi';
include 'includes/header.php';
?>

<?php
if ($successMsg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($successMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php
endif; ?>
<?php
if ($errorMsg): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php
endif; ?>

<?php
if ($action === 'list'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-tags me-2 text-primary"></i> Kampanyalar</h5>
        <div class="d-flex gap-2">
            <a href="../kampanyalar" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Canlı Sayfa</a>
            <a href="campaigns.php?action=new" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Yeni Kampanya</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-4">Kampanya</th><th>İşletme</th><th>Tarih</th><th>Durum</th><th class="text-end pe-4">İşlem</th></tr></thead>
            <tbody>
            <?php
if (empty($campaigns)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">Henüz kampanya yok.</td></tr>
            <?php
else: foreach ($campaigns as $camp): ?>
                <tr>
                    <td class="ps-4">
                        <strong><?= htmlspecialchars($camp['title']) ?></strong>
                        <?php
if (!empty($camp['discount_label'])): ?><br><small class="text-primary"><?= htmlspecialchars($camp['discount_label']) ?></small><?php
endif; ?>
                        <br><small class="text-muted font-monospace"><?= htmlspecialchars($camp['slug']) ?></small>
                    </td>
                    <td><?= !empty($camp['business_name']) ? htmlspecialchars($camp['business_name']) : '<span class="text-muted">—</span>' ?><br><small><?= htmlspecialchars($camp['district']) ?> · <?= htmlspecialchars(getCampaignTypeLabel($camp['campaign_type'])) ?></small></td>
                    <td><?= htmlspecialchars(formatCampaignDateRange($camp['start_date'], $camp['end_date'])) ?><?php
if (!empty($camp['sale_price']) || !empty($camp['original_price'])): ?><br><small class="text-primary"><?= htmlspecialchars($camp['sale_price'] ?: $camp['original_price']) ?><?php
if (!empty($camp['original_price']) && !empty($camp['sale_price'])): ?> <s class="text-muted"><?= htmlspecialchars($camp['original_price']) ?></s><?php
endif; ?></small><?php
endif; ?></td>
                    <td>
                        <?php
                        if ($camp['is_published']): ?><span class="badge bg-success">Yayında</span><?php
                        elseif (($camp['status'] ?? '') === 'rejected'): ?><span class="badge bg-danger">Reddedildi</span><?php
                        else: ?><span class="badge bg-secondary">Taslak</span><?php
                        endif; ?>
                        <?php
if ($camp['is_featured']): ?><span class="badge bg-warning text-dark">Öne Çıkan</span><?php
endif; ?>
                        <?php
                        if (isCampaignPast($camp)): ?><span class="badge bg-light text-muted border">Sona Erdi</span><?php
                        elseif ($camp['is_published'] && isCampaignActive($camp)): ?><span class="badge bg-primary">Aktif</span><?php
                        endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <?php if ($camp['is_published']): ?>
                            <a href="campaigns.php?action=toggle_publish&id=<?= $camp['id'] ?>" class="btn btn-outline-warning btn-sm" title="Yayından Kaldır"><i class="fa-solid fa-pause"></i></a>
                        <?php else: ?>
                            <a href="campaigns.php?action=toggle_publish&id=<?= $camp['id'] ?>" class="btn btn-success btn-sm" title="Onayla / Yayınla"><i class="fa-solid fa-check"></i> Onayla</a>
                            <?php if (($camp['status'] ?? 'pending') !== 'rejected'): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $camp['id'] ?>)" title="Reddet"><i class="fa-solid fa-xmark"></i> Reddet</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewCampModal<?= $camp['id'] ?>" title="Görüntüle / Detayları İncele"><i class="fa-solid fa-eye"></i></button>
                        <a href="campaigns.php?action=edit&id=<?= $camp['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                        <a href="campaigns.php?action=delete&id=<?= $camp['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu kampanyayı silmek istediğinize emin misiniz?" data-confirm-title="Kampanyayı Sil" data-confirm-btn="Evet, Sil"><i class="fa-solid fa-trash"></i></a>

                        <div class="modal fade text-start" id="viewCampModal<?= $camp['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title fw-bold">
                                            <i class="fa-solid fa-tags text-primary me-2"></i><?= htmlspecialchars($camp['title']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <?php if (!empty($camp['cover_image_path'])): ?>
                                            <div class="mb-4 text-center">
                                                <img src="<?= htmlspecialchars(getCampaignImageUrl($camp['cover_image_path'], '')) ?>" 
                                                     alt="Kapak Görseli" class="img-fluid rounded shadow-sm border" style="max-height: 280px; object-fit: cover; width: 100%;">
                                            </div>
                                        <?php endif; ?>

                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <strong class="small text-muted d-block">İşletme</strong>
                                                <span class="fw-semibold"><?= htmlspecialchars($camp['business_name'] ?? 'Bilinmiyor') ?></span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong class="small text-muted d-block">Yayın Durumu</strong>
                                                <?php if ($camp['is_published']): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Yayında</span>
                                                <?php elseif ($camp['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Reddedildi</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Onay Bekliyor</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="small text-muted d-block">İlçe / Bölge</strong>
                                                <?= htmlspecialchars($camp['district'] ?: 'Tümü') ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="small text-muted d-block">Kampanya Türü</strong>
                                                <?= htmlspecialchars(getCampaignTypeLabel($camp['campaign_type'] ?? 'default')) ?>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="small text-muted d-block">İndirim / Fırsat Rozeti</strong>
                                                <?= htmlspecialchars($camp['discount_label'] ?: '-') ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong class="small text-muted d-block">Başlangıç Tarihi</strong>
                                                <?= !empty($camp['start_date']) ? date('d.m.Y', strtotime($camp['start_date'])) : '-' ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong class="small text-muted d-block">Bitiş Tarihi</strong>
                                                <?= !empty($camp['end_date']) ? date('d.m.Y', strtotime($camp['end_date'])) : '-' ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($camp['summary'])): ?>
                                        <div class="mb-3">
                                            <strong class="small text-muted d-block mb-1">Kısa Özet</strong>
                                            <div class="p-3 rounded border bg-light" style="white-space: pre-wrap;"><?= htmlspecialchars($camp['summary']) ?></div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($camp['description'])): ?>
                                        <div class="mb-3">
                                            <strong class="small text-muted d-block mb-1">Detaylı Açıklama</strong>
                                            <div class="p-3 rounded border bg-light"><?= renderCampaignDescriptionHtml($camp['description']) ?></div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($camp['terms'])): ?>
                                        <div class="mb-3">
                                            <strong class="small text-muted d-block mb-1">Kampanya Koşulları (Şartlar)</strong>
                                            <div class="p-3 rounded border bg-light" style="white-space: pre-wrap;"><?= htmlspecialchars($camp['terms']) ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer bg-light d-flex justify-content-between">
                                        <div>
                                            <a href="../kampanya/<?= htmlspecialchars($camp['slug']) ?>" target="_blank" class="btn btn-outline-info btn-sm me-2">
                                                <i class="fa-solid fa-external-link-alt me-1"></i> Canlı Sayfayı Aç
                                            </a>
                                            <a href="campaigns.php?action=edit&id=<?= $camp['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fa-solid fa-pen me-1"></i> Düzenle
                                            </a>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Kapat</button>
                                            <?php if (!$camp['is_published']): ?>
                                                <a href="campaigns.php?action=toggle_publish&id=<?= $camp['id'] ?>" class="btn btn-success btn-sm fw-semibold shadow-sm mb-1">
                                                    <i class="fa-solid fa-check me-1"></i> Onayla ve Ana Sayfada Yayınla
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm shadow-sm mb-1" data-bs-dismiss="modal" onclick="openRejectModal(<?= $camp['id'] ?>)">
                                                    <i class="fa-solid fa-xmark me-1"></i> Reddet
                                                </button>
                                            <?php else: ?>
                                                <a href="campaigns.php?action=toggle_publish&id=<?= $camp['id'] ?>" class="btn btn-outline-warning btn-sm">
                                                    <i class="fa-solid fa-pause me-1"></i> Yayından Kaldır
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php
endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
else:
    $camp = $campaign ?: [];
    $isEdit = $action === 'edit';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold"><?= $isEdit ? 'Kampanyayı Düzenle' : 'Yeni Kampanya' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="campaigns.php" enctype="multipart/form-data">
            <?= CSRFMiddleware::field() ?>
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Başlık *</label><input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($camp['title'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Slug *</label><input type="text" name="slug" class="form-control" required value="<?= htmlspecialchars($camp['slug'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Bağlı işletme</label>
                    <select name="business_id" class="form-select">
                        <option value="0">— İşletme seçilmedi —</option>
                        <?php
foreach ($allBusinesses as $biz): ?>
                        <option value="<?= $biz['id'] ?>" <?= ((int) ($camp['business_id'] ?? 0) === (int) $biz['id']) ? 'selected' : '' ?>><?= htmlspecialchars($biz['name']) ?> (<?= htmlspecialchars($biz['district']) ?>)</option>
                        <?php
endforeach; ?>
                    </select>
                    <small class="text-muted">Seçilirse ilçe otomatik işletmeden alınır.</small>
                </div>
                <div class="col-md-3"><label class="form-label">İlçe</label><select name="district" class="form-select"><?php
foreach ($districts as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= (($camp['district'] ?? '') === $d) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Kampanya türü</label><select name="campaign_type" class="form-select"><?php
foreach ($types as $s => $l): ?><option value="<?= htmlspecialchars($s) ?>" <?= (($camp['campaign_type'] ?? 'indirim') === $s) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">İndirim / fırsat etiketi</label><input type="text" name="discount_label" class="form-control" placeholder="%20 İndirim, 1 Alana 1 Bedava..." value="<?= htmlspecialchars($camp['discount_label'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Eski fiyat</label><input type="text" name="original_price" class="form-control" placeholder="499 TL" value="<?= htmlspecialchars($camp['original_price'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Kampanya fiyatı</label><input type="text" name="sale_price" class="form-control" placeholder="399 TL" value="<?= htmlspecialchars($camp['sale_price'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Başlangıç tarihi *</label><input type="date" name="start_date" class="form-control" required value="<?= htmlspecialchars($camp['start_date'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Bitiş tarihi</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($camp['end_date'] ?? '') ?>"></div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Kapak Görseli <small class="text-muted">(Dosya yükle veya URL girin)</small></label>
                    <?php if (!empty($camp['cover_image_path'] ?? '')): ?>
                        <div class="mb-2 d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars(getCampaignImageUrl($camp['cover_image_path'] ?? '', '')) ?>" alt="Mevcut kapak" class="img-thumbnail shadow-sm" style="max-height:100px; max-width:200px; object-fit:cover;">
                            <span class="small text-muted">Mevcut görsel. Değiştirmek için aşağıdan yeni dosya seçin veya URL girin.</span>
                        </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Dosya Yükle</label>
                            <input type="file" name="cover_file" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">veya Görsel URL</label>
                            <input type="url" name="cover_url" class="form-control" placeholder="https://..." value="<?= (strpos($camp['cover_image_path'] ?? '', 'http') === 0) ? htmlspecialchars($camp['cover_image_path']) : '' ?>">
                        </div>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Kısa özet (kartlarda görünür)</label><input type="text" name="summary" class="form-control" maxlength="500" value="<?= htmlspecialchars($camp['summary'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Detaylı açıklama <small class="text-muted">(Kalın, başlık, liste ekleyebilirsiniz)</small></label>
                    <div id="quill-editor-camp" style="min-height:160px; background:#fff; border:1px solid #dee2e6; border-radius:0 0 .375rem .375rem;"></div>
                    <textarea name="description" id="description-hidden-camp" class="d-none"><?= htmlspecialchars($camp['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6"><label class="form-label">Harici kampanya linki (opsiyonel)</label><input type="url" name="cta_url" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($camp['cta_url'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Meta açıklama</label><input type="text" name="meta_description" class="form-control" maxlength="255" value="<?= htmlspecialchars($camp['meta_description'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Meta anahtar kelimeler</label><input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($camp['meta_keywords'] ?? '') ?>"></div>
                <div class="col-12">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" <?= !empty($camp['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Yayında</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?= !empty($camp['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="is_featured">Öne çıkan</label></div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">Kaydet</button>
                    <a href="campaigns.php" class="btn btn-outline-secondary ms-2">İptal</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
endif; ?>

<!-- Reject Campaign Modal -->
<div class="modal fade" id="rejectCampaignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="campaigns.php" method="POST">
                <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="reject_campaign">
                <input type="hidden" name="reject_id" id="reject_campaign_id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation me-2"></i> Kampanyayı Reddet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Bu kampanyayı reddetmek üzeresiniz. İşletmeye bildirim gönderilecek. Lütfen ret nedenini belirtin (İsteğe bağlı).</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ret Nedeni</label>
                        <textarea name="reject_reason" class="form-control" rows="3" placeholder="Örn: Görsel uygunsuz, içerik kurallara uymuyor vb."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger fw-semibold">Reddet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('reject_campaign_id').value = id;
    var rejectModal = new bootstrap.Modal(document.getElementById('rejectCampaignModal'));
    rejectModal.show();
}
</script>

<?php
include 'includes/footer.php'; ?>
