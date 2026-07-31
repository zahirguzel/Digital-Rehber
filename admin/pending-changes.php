<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getInstance();
$successMsg = '';
$errorMsg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCSRF();
    $action = $_POST['action'];
    $changeId = intval($_POST['change_id']);
    
    $change = $db->query("SELECT * FROM business_pending_changes WHERE id = ? AND status = 'pending'", [$changeId])->fetch();
    
    if ($change) {
        $bizId = $change['business_id'];
        $field = $change['field_name'];
        $newValue = $change['new_value'];
        $adminId = $_SESSION['admin_id'] ?? 1;

        if ($action === 'approve') {
            try {
                $db->getPDO()->beginTransaction();
                
                // If it is a gallery photo upload, insert into business_gallery, otherwise update business table
                if ($change['change_type'] === 'gallery' || $field === 'gallery_add') {
                    $maxOrd = $db->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM business_gallery WHERE business_id=?", [$bizId])->fetchColumn();
                    $db->getPDO()->prepare("INSERT INTO business_gallery (business_id, image_path, sort_order) VALUES (?, ?, ?)")->execute([$bizId, $newValue, $maxOrd]);
                } else {
                    $db->getPDO()->prepare("UPDATE businesses SET {$field} = ? WHERE id = ?")->execute([$newValue, $bizId]);
                }
                
                // Update pending change status
                $db->getPDO()->prepare("UPDATE business_pending_changes SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?")->execute([$adminId, $changeId]);
                
                $db->getPDO()->commit();
                $bizName = $db->query("SELECT name FROM businesses WHERE id = ?", [$bizId])->fetchColumn() ?: "İşletme #$bizId";
                if (function_exists('logAdminAction')) {
                    logAdminAction('approve', 'pending_changes', $bizName . ' - ' . $change['field_label'] . ' (Onaylandı)', $changeId);
                }
                $successMsg = "Değişiklik onaylandı ve yayına alındı.";
            } catch (Exception $e) {
                $db->getPDO()->rollBack();
                $errorMsg = "Onaylama sırasında hata oluştu: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reject_reason'] ?? '');
            $db->getPDO()->prepare("UPDATE business_pending_changes SET status = 'rejected', reject_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?")->execute([$reason, $adminId, $changeId]);
            $bizName = $db->query("SELECT name FROM businesses WHERE id = ?", [$bizId])->fetchColumn() ?: "İşletme #$bizId";
            if (function_exists('logAdminAction')) {
                logAdminAction('reject', 'pending_changes', $bizName . ' - ' . $change['field_label'] . ' (Ret: ' . ($reason ?: 'Belirtilmedi') . ')', $changeId);
            }
            $successMsg = "Değişiklik reddedildi.";
        }
    } else {
        $errorMsg = "Değişiklik bulunamadı veya zaten işlem görmüş.";
    }
}

// Fetch pending changes
$pending = $db->query("
    SELECT p.*, b.name as business_name, b.slug as business_slug 
    FROM business_pending_changes p
    JOIN businesses b ON p.business_id = b.id
    WHERE p.status = 'pending'
    ORDER BY p.submitted_at DESC
")->fetchAll();

$pageTitle = 'Onay Bekleyen Değişiklikler';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-navy">
        <i class="fa-solid fa-code-pull-request me-2 text-primary"></i> Onay Bekleyen Değişiklikler
    </h3>
    <a href="logs.php?module=pending_changes" class="btn btn-outline-primary btn-sm px-3">
        <i class="fa-solid fa-clock-rotate-left me-1"></i> Geçmiş Onay Logları
    </a>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-body p-0">
        <?php if (empty($pending)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-check-double fs-1 mb-3 text-success" style="opacity: 0.5;"></i>
                <p class="mb-0 fw-semibold">Onay bekleyen değişiklik bulunmuyor.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Tarih</th>
                            <th>İşletme</th>
                            <th>Değişen Alan</th>
                            <th>Eski Değer</th>
                            <th>Yeni Değer</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php foreach ($pending as $item): ?>
                            <tr class="table-warning">
                                <td class="ps-4 text-muted small">
                                    <i class="fa-regular fa-clock me-1"></i> <?= date('d.m.Y H:i', strtotime($item['submitted_at'])) ?>
                                </td>
                                <td>
                                    <a href="../esnaf/<?= htmlspecialchars($item['business_slug']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                        <?= htmlspecialchars($item['business_name']) ?> <i class="fa-solid fa-external-link-alt small"></i>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark border"><?= htmlspecialchars($item['field_label'] ?: $item['field_name']) ?></span>
                                </td>
                                
                                <td style="max-width:200px;">
                                    <?php if ($item['change_type'] === 'image'): ?>
                                        <?php if ($item['old_value']): ?>
                                            <a href="../public/images/<?= htmlspecialchars($item['old_value']) ?>" target="_blank">Görseli Gör</a>
                                        <?php else: ?>
                                            <span class="text-muted">Yok</span>
                                        <?php endif; ?>
                                    <?php elseif ($item['change_type'] === 'gallery'): ?>
                                        <span class="text-muted">(Yeni Ekleme)</span>
                                    <?php else: ?>
                                        <div class="text-muted small text-truncate" title="<?= htmlspecialchars($item['old_value'] ?? '') ?>">
                                            <?= htmlspecialchars($item['old_value'] ?? 'Boş') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="max-width:200px;">
                                    <?php if ($item['change_type'] === 'image'): ?>
                                        <a href="../public/images/<?= htmlspecialchars($item['new_value']) ?>" target="_blank" class="text-primary fw-bold">Yeni Görseli Gör</a>
                                    <?php elseif ($item['change_type'] === 'gallery'): ?>
                                        <a href="../public/images/gallery/<?= $item['business_id'] ?>/<?= htmlspecialchars($item['new_value']) ?>" target="_blank" class="text-primary fw-bold"><i class="fa-solid fa-image me-1"></i>Galeri Fotoğrafını Gör</a>
                                    <?php else: ?>
                                        <div class="text-dark small text-truncate" title="<?= htmlspecialchars($item['new_value'] ?? '') ?>">
                                            <?= htmlspecialchars($item['new_value'] ?? '') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?= $item['id'] ?>">İncele</button>
                                </td>
                            </tr>
                            
                            <!-- İnceleme Modalı -->
                            <div class="modal fade" id="viewModal<?= $item['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold text-navy">Değişiklik İnceleme</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <h6 class="text-muted small fw-bold text-uppercase">Eski Değer</h6>
                                                    <div class="p-3 bg-light rounded-3 border h-100" style="min-height:100px; font-size:14px; overflow-wrap:break-word;">
                                                        <?php if ($item['change_type'] === 'image'): ?>
                                                            <?php if ($item['old_value']): ?>
                                                                <img src="../public/images/<?= htmlspecialchars($item['old_value']) ?>" class="img-fluid rounded">
                                                            <?php else: ?>
                                                                (Yok)
                                                            <?php endif; ?>
                                                        <?php elseif ($item['change_type'] === 'gallery'): ?>
                                                            (Yok - Yeni Galeri Görseli)
                                                        <?php else: ?>
                                                            <?= nl2br(htmlspecialchars($item['old_value'] ?? '')) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-primary small fw-bold text-uppercase">Yeni Değer</h6>
                                                    <div class="p-3 bg-white rounded-3 border border-primary h-100" style="min-height:100px; font-size:14px; overflow-wrap:break-word;">
                                                        <?php if ($item['change_type'] === 'image'): ?>
                                                            <img src="../public/images/<?= htmlspecialchars($item['new_value']) ?>" class="img-fluid rounded">
                                                        <?php elseif ($item['change_type'] === 'gallery'): ?>
                                                            <img src="../public/images/gallery/<?= $item['business_id'] ?>/<?= htmlspecialchars($item['new_value']) ?>" class="img-fluid rounded">
                                                        <?php else: ?>
                                                            <?= nl2br(htmlspecialchars($item['new_value'] ?? '')) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <form method="POST" action="" class="w-100 d-flex gap-2">
                                                <?= CSRFMiddleware::field() ?>
                                                <input type="hidden" name="change_id" value="<?= $item['id'] ?>">
                                                
                                                <div class="flex-grow-1">
                                                    <input type="text" name="reject_reason" class="form-control" placeholder="Reddederseniz sebep yazabilirsiniz (opsiyonel)">
                                                </div>
                                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reddet</button>
                                                <button type="submit" name="action" value="approve" class="btn btn-success fw-bold"><i class="fa-solid fa-check me-1"></i> Onayla & Yayınla</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
