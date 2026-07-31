<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/event-helpers.php';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submission_status']) && (int) $_POST['submission_id'] > 0) {
        $status = $_POST['submission_status'];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $notes = trim($_POST['admin_notes'] ?? '');
            $db->getPDO()->prepare('UPDATE event_submissions SET status = ?, admin_notes = ? WHERE id = ?')->execute([$status, $notes, (int) $_POST['submission_id']]);
            $successMsg = 'Başvuru durumu güncellendi.';
        }
    }
}

$submissions = [];
try {
    $submissions = $db->query("SELECT s.*, e.slug AS event_slug FROM event_submissions s LEFT JOIN events e ON e.id = s.event_id ORDER BY FIELD(s.status,'pending','approved','rejected'), s.created_at DESC")->fetchAll();
} catch (Exception $e) {
    $errorMsg = 'Tablo bulunamadı. Canlıda event_submissions tablosunu oluşturmanız gerekir.';
}

$pendingCount = count(array_filter($submissions, function ($s) {
    return $s['status'] === 'pending';
}));

$pageTitle = 'Etkinlik Başvuruları';
include 'includes/header.php';
?>

<?php
if ($successMsg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($successMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php
endif; ?>
<?php
if ($errorMsg): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php
endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i> Etkinlik Yayınlama Başvuruları</h5>
        <div class="d-flex gap-2">
            <?php
if ($pendingCount > 0): ?><span class="badge bg-danger align-self-center"><?= $pendingCount ?> bekleyen</span><?php
endif; ?>
            <a href="events.php?action=new" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-plus me-1"></i> Manuel Etkinlik</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Etkinlik</th>
                    <th>Başvuran</th>
                    <th>Tarih / İlçe</th>
                    <th>Durum</th>
                    <th class="text-end pe-4">İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php
if (empty($submissions)): ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">Henüz etkinlik başvurusu yok.</td></tr>
            <?php
else: foreach ($submissions as $sub): ?>
                <tr id="row-<?= (int) $sub['id'] ?>" class="<?= $sub['status'] === 'pending' ? 'table-warning' : '' ?>">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($sub['cover_image_url'])): 
                                $thumbUrl = getEventImageUrl($sub['cover_image_url'], '../public/images/hero-slider.jpg');
                            ?>
                                <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="" class="border" style="width: 45px; height: 32px; object-fit: cover; border-radius: 4px;">
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($sub['title']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars(getEventCategoryLabel($sub['category'])) ?><?php
if (!empty($sub['venue_name'])): ?> · <?= htmlspecialchars($sub['venue_name']) ?><?php
endif; ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?= htmlspecialchars($sub['contact_name']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($sub['contact_email']) ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars(formatEventDateRange($sub['start_date'], $sub['end_date'])) ?>
                        <?php
if (!empty($sub['start_time'])): ?><br><small class="text-muted"><?= htmlspecialchars(formatEventTimeRange($sub['start_time'], $sub['end_time'])) ?></small><?php
endif; ?>
                        <br><small><?= htmlspecialchars($sub['district']) ?></small>
                    </td>
                    <td>
                        <?php
if ($sub['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Beklemede</span>
                        <?php
elseif ($sub['status'] === 'approved'): ?>
                            <span class="badge bg-success">Yayınlandı</span>
                            <?php
if (!empty($sub['event_slug'])): ?><br><a href="../etkinlik/<?= htmlspecialchars($sub['event_slug']) ?>" target="_blank" class="small">Sayfayı aç</a><?php
endif; ?>
                        <?php
else: ?>
                            <span class="badge bg-secondary">Reddedildi</span>
                        <?php
endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int) $sub['id'] ?>"><i class="fa-solid fa-eye"></i></button>
                        <?php
if (empty($sub['event_id'])): ?>
                        <a href="events.php?action=new&from_submission=<?= (int) $sub['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-calendar-check me-1"></i> Etkinliğe Dönüştür</a>
                        <?php
elseif (!empty($sub['event_id'])): ?>
                        <a href="events.php?action=edit&id=<?= (int) $sub['event_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                        <?php
endif; ?>
                    </td>
                </tr>

                <div class="modal fade" id="detailModal<?= (int) $sub['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold"><?= htmlspecialchars($sub['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6"><strong class="small text-muted d-block">Başvuran</strong><?= htmlspecialchars($sub['contact_name']) ?></div>
                                    <div class="col-md-6"><strong class="small text-muted d-block">E-posta</strong><a href="mailto:<?= htmlspecialchars($sub['contact_email']) ?>"><?= htmlspecialchars($sub['contact_email']) ?></a></div>
                                    <?php
if (!empty($sub['contact_phone'])): ?><div class="col-md-6"><strong class="small text-muted d-block">Telefon</strong><?= htmlspecialchars($sub['contact_phone']) ?></div><?php
endif; ?>
                                    <?php
if (!empty($sub['organizer'])): ?><div class="col-md-6"><strong class="small text-muted d-block">Düzenleyen</strong><?= htmlspecialchars($sub['organizer']) ?></div><?php
endif; ?>
                                    <div class="col-md-6"><strong class="small text-muted d-block">Tarih</strong><?= htmlspecialchars(formatEventDateRange($sub['start_date'], $sub['end_date'])) ?><?php
if (!empty($sub['start_time'])): ?> · <?= htmlspecialchars(formatEventTimeRange($sub['start_time'], $sub['end_time'])) ?><?php
endif; ?></div>
                                    <div class="col-md-6"><strong class="small text-muted d-block">İlçe / Kategori</strong><?= htmlspecialchars($sub['district']) ?> · <?= htmlspecialchars(getEventCategoryLabel($sub['category'])) ?></div>
                                    <?php
if (!empty($sub['venue_name'])): ?><div class="col-md-6"><strong class="small text-muted d-block">Mekân</strong><?= htmlspecialchars($sub['venue_name']) ?></div><?php
endif; ?>
                                    <?php
if (!empty($sub['ticket_price'])): ?><div class="col-md-6"><strong class="small text-muted d-block">Bilet</strong><?= htmlspecialchars($sub['ticket_price']) ?></div><?php
endif; ?>
                                    <?php
if (!empty($sub['ticket_url'])): ?><div class="col-12"><strong class="small text-muted d-block">Bilet Linki</strong><a href="<?= htmlspecialchars($sub['ticket_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($sub['ticket_url']) ?></a></div><?php
endif; ?>
                                    <?php
if (!empty($sub['address'])): ?><div class="col-12"><strong class="small text-muted d-block">Adres</strong><?= nl2br(htmlspecialchars($sub['address'])) ?></div><?php
endif; ?>
                                    <?php if (!empty($sub['cover_image_url'])): 
                                        $modalImgUrl = getEventImageUrl($sub['cover_image_url'], '');
                                    ?>
                                        <div class="col-12">
                                            <strong class="small text-muted d-block mb-1">Kapak Görseli</strong>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= htmlspecialchars($modalImgUrl) ?>" alt="Kapak" style="width: 100px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                                                <a href="<?= htmlspecialchars($modalImgUrl) ?>" target="_blank" rel="noopener" class="small btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Görseli Tam Boyutta Aç</a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <strong class="small text-muted d-block mb-1">Açıklama</strong>
                                    <div class="p-3 rounded border bg-light" style="white-space: pre-wrap;"><?= htmlspecialchars($sub['description']) ?></div>
                                </div>
                                <?php
if (!empty($sub['notes'])): ?>
                                <div class="mb-3">
                                    <strong class="small text-muted d-block mb-1">Ek Notlar</strong>
                                    <div class="p-3 rounded border bg-light" style="white-space: pre-wrap;"><?= htmlspecialchars($sub['notes']) ?></div>
                                </div>
                                <?php
endif; ?>
                                <form method="POST" class="border-top pt-3">
    <?= CSRFMiddleware::field() ?>
                                    <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Durum</label>
                                            <select name="submission_status" class="form-select form-select-sm">
                                                <option value="pending" <?= $sub['status'] === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                                                <option value="approved" <?= $sub['status'] === 'approved' ? 'selected' : '' ?>>Onaylandı (Etkinlik Var)</option>
                                                <option value="rejected" <?= $sub['status'] === 'rejected' ? 'selected' : '' ?>>Reddedildi</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold">Admin Notu</label>
                                            <input type="text" name="admin_notes" class="form-control form-control-sm" value="<?= htmlspecialchars($sub['admin_notes'] ?? '') ?>" placeholder="İç not">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Durumu Kaydet</button>
                                            <?php
if (empty($sub['event_id'])): ?>
                                            <a href="events.php?action=new&from_submission=<?= (int) $sub['id'] ?>" class="btn btn-sm btn-primary ms-1"><i class="fa-solid fa-calendar-check me-1"></i> Etkinliğe Dönüştür</a>
                                            <?php
endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include 'includes/footer.php'; ?>
