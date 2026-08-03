<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/event-helpers.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = '';
$errorMsg = '';

function evtHandleUpload($fileKey, $urlKey, $current = '')
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
    $venue_name = trim($_POST['venue_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $category = trim($_POST['category'] ?? 'diger');
    $description = trim($_POST['description'] ?? '');
    $ticket_url = trim($_POST['ticket_url'] ?? '');
    $ticket_price = trim($_POST['ticket_price'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $google_maps_url = trim($_POST['google_maps_url'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $businessIds = isset($_POST['business_ids']) && is_array($_POST['business_ids']) ? array_map('intval', $_POST['business_ids']) : [];

    if ($title === '' || $slug === '' || $district === '' || $start_date === '') {
        $errorMsg = 'Başlık, slug, ilçe ve başlangıç tarihi zorunludur.';
    } else {
        try {
            $stmtCheck = $db->query('SELECT COUNT(*) FROM events WHERE slug = ? AND id != ?', [$slug, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errorMsg = 'Bu slug başka bir etkinlikte kullanılıyor.';
            } else {
                $currentCover = '';
                if ($_POST['action'] === 'edit' && $id > 0) {
                    $cur = $db->query('SELECT cover_image_path FROM events WHERE id = ?', [$id]);
                    $curData = $cur->fetch();
                    if ($curData) {
                        $currentCover = $curData['cover_image_path'];
                    }
                }
                if (!empty($_POST['remove_cover'])) {
                    $currentCover = '';
                }
                $cover_image_path = evtHandleUpload('cover_file', 'cover_url', $currentCover);
                $end_date_val = $end_date !== '' ? $end_date : null;
                $start_time_val = $start_time !== '' ? $start_time : null;
                $end_time_val = $end_time !== '' ? $end_time : null;

                if ($_POST['action'] === 'add') {
                    $sql = 'INSERT INTO events (title, slug, district, venue_name, address, start_date, end_date, start_time, end_time, category, description, cover_image_path, ticket_url, ticket_price, organizer, contact_phone, contact_email, google_maps_url, is_featured, is_published, meta_description, meta_keywords) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $stmt = $db->query($sql, [$title, $slug, $district, $venue_name, $address, $start_date, $end_date_val, $start_time_val, $end_time_val, $category, $description, $cover_image_path, $ticket_url, $ticket_price, $organizer, $contact_phone, $contact_email, $google_maps_url, $is_featured, $is_published, $meta_description, $meta_keywords]);
                    $id = (int) $db->getPDO()->lastInsertId();
                    if (function_exists('logAction')) logAction('create', 'events', $title, $id);
                    $successMsg = 'Etkinlik eklendi.';
                    $submissionId = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
                    if ($submissionId > 0) {
                        $db->getPDO()->prepare("UPDATE event_submissions SET status = 'approved', event_id = ? WHERE id = ?")->execute([$id, $submissionId]);
                        $successMsg = 'Etkinlik eklendi ve başvuru yayınlandı olarak işaretlendi.';
                    }
                } else {
                    $sql = 'UPDATE events SET title=?, slug=?, district=?, venue_name=?, address=?, start_date=?, end_date=?, start_time=?, end_time=?, category=?, description=?, cover_image_path=?, ticket_url=?, ticket_price=?, organizer=?, contact_phone=?, contact_email=?, google_maps_url=?, is_featured=?, is_published=?, meta_description=?, meta_keywords=? WHERE id=?';
                    $stmt = $db->query($sql, [$title, $slug, $district, $venue_name, $address, $start_date, $end_date_val, $start_time_val, $end_time_val, $category, $description, $cover_image_path, $ticket_url, $ticket_price, $organizer, $contact_phone, $contact_email, $google_maps_url, $is_featured, $is_published, $meta_description, $meta_keywords, $id]);
                    if (function_exists('logAction')) logAction('update', 'events', $title, $id);
                    $successMsg = 'Etkinlik güncellendi.';
                }

                $db->getPDO()->prepare('DELETE FROM event_business_links WHERE event_id = ?')->execute([$id]);
                if (!empty($businessIds)) {
                    $linkStmt = $db->getPDO()->prepare('INSERT IGNORE INTO event_business_links (event_id, business_id) VALUES (?, ?)');
                    foreach ($businessIds as $bid) {
                        if ($bid > 0) {
                            $linkStmt->execute([$id, $bid]);
                        }
                    }
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
        $stmtName = $db->getPDO()->prepare("SELECT title FROM events WHERE id = ?");
        $stmtName->execute([$id]);
        $delTitle = $stmtName->fetchColumn() ?: 'Etkinlik ID: ' . $id;

        $db->getPDO()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        if (function_exists('logAction')) logAction('delete', 'events', $delTitle, $id);
        $successMsg = 'Etkinlik silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silinemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

if ($action === 'seed') {
    try {
        ob_start();
        require_once __DIR__ . '/../scripts/seed_events.php';
        ob_end_clean();
        $successMsg = 'Örnek etkinlik verileri başarıyla yüklendi.';
    } catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $errorMsg = 'Örnek veriler yüklenemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

$event = null;
$linkedBusinessIds = [];
$fromSubmissionId = isset($_GET['from_submission']) ? (int) $_GET['from_submission'] : 0;
$prefillSubmission = null;
if (in_array($action, ['edit', 'new'], true)) {
    $allBusinesses = $db->query('SELECT id, name, district FROM businesses ORDER BY name ASC')->fetchAll();
}
if ($action === 'new' && $fromSubmissionId > 0) {
    $subStmt = $db->query('SELECT * FROM event_submissions WHERE id = ?', [$fromSubmissionId]);
    $prefillSubmission = $subStmt->fetch();
    if ($prefillSubmission) {
        $event = mapEventSubmissionToEvent($prefillSubmission);
    }
}
if ($action === 'edit' && $id > 0) {
    $stmt = $db->query('SELECT * FROM events WHERE id = ?', [$id]);
    $event = $stmt->fetch();
    if (!$event) {
        $errorMsg = 'Etkinlik bulunamadı.';
        $action = 'list';
    } else {
        $links = $db->query('SELECT business_id FROM event_business_links WHERE event_id = ?', [$id]);
        $linkedBusinessIds = $links->fetchAll(PDO::FETCH_COLUMN);
    }
}

$events = [];
if ($action === 'list') {
    $events = $db->query('SELECT * FROM events ORDER BY start_date DESC, is_featured DESC')->fetchAll();
}

$categories = eventCategories();
$districts = eventDistricts();
$pageTitle = 'Etkinlik Yönetimi';
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
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-calendar-days me-2 text-primary"></i> Etkinlikler</h5>
        <div class="d-flex gap-2">
            <a href="events.php?action=seed" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-seedling me-1"></i> Örnek Veri Yükle</a>
            <a href="event-talepler.php" class="btn btn-outline-warning btn-sm"><i class="fa-solid fa-inbox me-1"></i> Başvurular<?php
$pendingSub = getEventPendingSubmissionsCount($db->getPDO()); if ($pendingSub > 0): ?> <span class="badge bg-danger"><?= $pendingSub ?></span><?php
endif; ?></a>
            <a href="events.php?action=new" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Yeni Etkinlik</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-4" style="width: 80px;">Görsel</th><th>Etkinlik</th><th>Tarih</th><th>İlçe / Kategori</th><th>Durum</th><th class="text-end pe-4">İşlem</th></tr></thead>
            <tbody>
            <?php
if (empty($events)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">Henüz etkinlik yok.</td></tr>
            <?php
else: foreach ($events as $ev): 
                $evCover = getEventImageUrl($ev['cover_image_path'] ?? '', '../public/images/hero-slider.jpg');
            ?>
                <tr>
                    <td class="ps-4">
                        <img src="<?= htmlspecialchars($evCover) ?>" alt="Cover" class="border" style="width: 55px; height: 38px; object-fit: cover; border-radius: 6px;">
                    </td>
                    <td class="fw-bold"><?= htmlspecialchars($ev['title']) ?><br><small class="text-muted font-monospace"><?= htmlspecialchars($ev['slug']) ?></small></td>
                    <td><?= htmlspecialchars(formatEventDateRange($ev['start_date'], $ev['end_date'])) ?><?php
if (!empty($ev['start_time'])): ?><br><small class="text-muted"><?= htmlspecialchars(formatEventTimeRange($ev['start_time'], $ev['end_time'])) ?></small><?php
endif; ?></td>
                    <td><?= htmlspecialchars($ev['district']) ?><br><small><?= htmlspecialchars(getEventCategoryLabel($ev['category'])) ?></small></td>
                    <td>
                        <?php
if ($ev['is_published']): ?><span class="badge bg-success">Yayında</span><?php
else: ?><span class="badge bg-secondary">Taslak</span><?php
endif; ?>
                        <?php
if ($ev['is_featured']): ?><span class="badge bg-warning text-dark">Öne Çıkan</span><?php
endif; ?>
                        <?php
if (isEventPast($ev)): ?><span class="badge bg-light text-muted border">Geçmiş</span><?php
endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <a href="../etkinlik/<?= htmlspecialchars($ev['slug']) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-eye"></i></a>
                        <a href="events.php?action=edit&id=<?= $ev['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                        <a href="events.php?action=delete&id=<?= $ev['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu etkinliği silmek istediğinize emin misiniz?" data-confirm-title="Etkinliği Sil" data-confirm-btn="Evet, Sil"><i class="fa-solid fa-trash"></i></a>
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
    $ev = $event ?: [];
    $isEdit = $action === 'edit';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold"><?= $isEdit ? 'Etkinliği Düzenle' : 'Yeni Etkinlik' ?></h5>
        <?php
if ($prefillSubmission): ?>
        <p class="small text-muted mb-0 mt-2"><i class="fa-solid fa-inbox me-1"></i> Başvurudan dolduruldu: <strong><?= htmlspecialchars($prefillSubmission['contact_name']) ?></strong> (<?= htmlspecialchars($prefillSubmission['contact_email']) ?>)</p>
        <?php
endif; ?>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php
if ($prefillSubmission): ?><input type="hidden" name="submission_id" value="<?= (int) $prefillSubmission['id'] ?>"><?php
endif; ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Başlık *</label><input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($ev['title'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Slug *</label><input type="text" name="slug" class="form-control" required value="<?= htmlspecialchars($ev['slug'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">İlçe *</label><select name="district" class="form-select" required><?php
foreach ($districts as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= (($ev['district'] ?? '') === $d) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Kategori</label><select name="category" class="form-select"><?php
foreach ($categories as $s => $l): ?><option value="<?= htmlspecialchars($s) ?>" <?= (($ev['category'] ?? 'diger') === $s) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Mekân adı</label><input type="text" name="venue_name" class="form-control" value="<?= htmlspecialchars($ev['venue_name'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Başlangıç tarihi *</label><input type="date" name="start_date" class="form-control" required value="<?= htmlspecialchars($ev['start_date'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Bitiş tarihi</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($ev['end_date'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Başlangıç saati</label><input type="time" name="start_time" class="form-control" value="<?= htmlspecialchars(substr($ev['start_time'] ?? '', 0, 5)) ?>"></div>
                <div class="col-md-3"><label class="form-label">Bitiş saati</label><input type="time" name="end_time" class="form-control" value="<?= htmlspecialchars(substr($ev['end_time'] ?? '', 0, 5)) ?>"></div>
                <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($ev['address'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label">Açıklama <small class="text-muted">(Kalın, başlık, liste ekleyebilirsiniz)</small></label>
                    <textarea name="description" id="eventDescriptionEditor" class="form-control" rows="12" placeholder="Etkinlik açıklamasını buraya yazın..."><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kapak (dosya veya URL)</label>
                    <input type="file" name="cover_file" class="form-control">
                    <input type="url" name="cover_url" class="form-control mt-1" placeholder="veya görsel URL (http://...)" value="<?= (strpos($ev['cover_image_path'] ?? '', 'http') === 0) ? htmlspecialchars($ev['cover_image_path']) : '' ?>">
                    <?php if (!empty($ev['cover_image_path'])): 
                        $previewEvImg = getEventImageUrl($ev['cover_image_path'], '');
                    ?>
                        <div class="d-flex align-items-center gap-3 p-2 mt-2 rounded bg-light border">
                            <img src="<?= htmlspecialchars($previewEvImg) ?>" alt="Önizleme" style="width: 120px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                            <div>
                                <div class="small fw-bold text-dark mb-1"><i class="fa-solid fa-image me-1 text-primary"></i> Şu Anki Kapak Görseli</div>
                                <div class="small text-muted mb-2" style="word-break: break-all; font-size: 11px;"><?= htmlspecialchars($ev['cover_image_path']) ?></div>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" name="remove_cover" id="removeCoverCheck" value="1">
                                    <label class="form-check-label small text-danger fw-semibold" for="removeCoverCheck" style="cursor: pointer;">
                                        <i class="fa-solid fa-trash-can me-1"></i> Mevcut Görseli Kaldır
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3"><label class="form-label">Bilet URL</label><input type="url" name="ticket_url" class="form-control" value="<?= htmlspecialchars($ev['ticket_url'] ?? '') ?>"></div>
                <div class="col-md-3"><label class="form-label">Bilet / ücret</label><input type="text" name="ticket_price" class="form-control" placeholder="Ücretsiz, 150 TL..." value="<?= htmlspecialchars($ev['ticket_price'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Düzenleyen</label><input type="text" name="organizer" class="form-control" value="<?= htmlspecialchars($ev['organizer'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Telefon</label><input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($ev['contact_phone'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">E-posta</label><input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($ev['contact_email'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Google Maps (embed URL veya iframe)</label><textarea name="google_maps_url" class="form-control" rows="2" placeholder="https://maps.google.com/maps?q=...&output=embed"><?= htmlspecialchars($ev['google_maps_url'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Meta açıklama</label><input type="text" name="meta_description" class="form-control" maxlength="255" value="<?= htmlspecialchars($ev['meta_description'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Meta anahtar kelimeler</label><input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($ev['meta_keywords'] ?? '') ?>"></div>
                <div class="col-12">
                    <label class="form-label">Bağlı işletmeler (mekân)</label>
                    <select name="business_ids[]" class="form-select" multiple size="6">
                        <?php
foreach ($allBusinesses as $biz): ?>
                        <option value="<?= $biz['id'] ?>" <?= in_array($biz['id'], $linkedBusinessIds) ? 'selected' : '' ?>><?= htmlspecialchars($biz['name']) ?> (<?= htmlspecialchars($biz['district']) ?>)</option>
                        <?php
endforeach; ?>
                    </select>
                    <small class="text-muted">Ctrl/Cmd ile çoklu seçim</small>
                </div>
                <div class="col-12">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" <?= !empty($ev['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Yayında</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?= !empty($ev['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="is_featured">Öne çıkan (ana sayfa)</label></div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">Kaydet</button>
                    <a href="events.php" class="btn btn-outline-secondary ms-2">İptal</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
endif; ?>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof tinymce !== 'undefined') {
            tinymce.init({
                selector: '#eventDescriptionEditor',
                height: 380,
                menubar: false,
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat | code',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 15px; color: #1e293b; line-height: 1.6; }'
            });
        }
    });
</script>

<?php
include 'includes/footer.php'; ?>
