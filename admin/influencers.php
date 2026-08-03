<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/influencer-helpers.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain . str_replace('admin/influencers.php', '', $_SERVER['PHP_SELF']);

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = '';
$errorMsg = '';
$adminUser = $_SESSION['admin_username'] ?? 'admin';

function infHandleUpload($fileKey, $urlKey, $current = '')
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
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $niche = trim($_POST['niche'] ?? 'diger');
    $bio = trim($_POST['bio'] ?? '');
    $collaboration_types = trim($_POST['collaboration_types'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $tiktok = trim($_POST['tiktok'] ?? '');
    $youtube = trim($_POST['youtube'] ?? '');
    $follower_instagram = $_POST['follower_instagram'] !== '' ? (int) $_POST['follower_instagram'] : null;
    $follower_tiktok = $_POST['follower_tiktok'] !== '' ? (int) $_POST['follower_tiktok'] : null;
    $follower_youtube = $_POST['follower_youtube'] !== '' ? (int) $_POST['follower_youtube'] : null;
    $featured_links = trim($_POST['featured_links'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $theme_color = trim($_POST['theme_color'] ?? '#1e3932');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $consent_given = isset($_POST['consent_given']) ? 1 : 0;
    $consent_date = trim($_POST['consent_date'] ?? '');
    $followers_verified = isset($_POST['followers_verified']) ? 1 : 0;
    $businessIds = isset($_POST['business_ids']) && is_array($_POST['business_ids']) ? array_map('intval', $_POST['business_ids']) : [];

    if ($name === '' || $slug === '' || $district === '') {
        $errorMsg = 'Ad, slug ve ilçe zorunludur.';
    } elseif ($is_published && !$consent_given) {
        $errorMsg = 'Yayına almak için isim/görsel kullanım onayı işaretlenmelidir.';
    } else {
        try {
            $stmtCheck = $db->query('SELECT COUNT(*) FROM influencers WHERE slug = ? AND id != ?', [$slug, $id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errorMsg = 'Bu slug başka bir profilde kullanılıyor.';
            } else {
                $currentAvatar = '';
                $currentCover = '';
                if ($_POST['action'] === 'edit' && $id > 0) {
                    $cur = $db->query('SELECT avatar_path, cover_path FROM influencers WHERE id = ?', [$id]);
                    $curData = $cur->fetch();
                    if ($curData) {
                        $currentAvatar = $curData['avatar_path'];
                        $currentCover = $curData['cover_path'];
                    }
                }
                $avatar_path = infHandleUpload('avatar_file', 'avatar_url', $currentAvatar);
                $cover_path = infHandleUpload('cover_file', 'cover_url', $currentCover);

                $followers_verified_at = null;
                $followers_verified_by = null;
                if ($followers_verified) {
                    $followers_verified_at = date('Y-m-d');
                    $followers_verified_by = $adminUser;
                }

                $consent_date_val = $consent_given && $consent_date !== '' ? $consent_date : ($consent_given ? date('Y-m-d') : null);

                if ($_POST['action'] === 'add') {
                    $sql = 'INSERT INTO influencers (name, slug, district, niche, bio, collaboration_types, instagram, tiktok, youtube, follower_instagram, follower_tiktok, follower_youtube, followers_verified_at, followers_verified_by, avatar_path, cover_path, featured_links, contact_email, theme_color, is_premium, is_verified, is_published, consent_given, consent_date, meta_description, meta_keywords) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $stmt = $db->query($sql, [$name, $slug, $district, $niche, $bio, $collaboration_types, $instagram, $tiktok, $youtube, $follower_instagram, $follower_tiktok, $follower_youtube, $followers_verified_at, $followers_verified_by, $avatar_path, $cover_path, $featured_links, $contact_email, $theme_color, $is_premium, $is_verified, $is_published, $consent_given, $consent_date_val, $meta_description, $meta_keywords]);
                    $id = (int) $db->getPDO()->lastInsertId();
                    if (function_exists('logAction')) logAction('create', 'influencers', $name, $id);
                    $successMsg = 'Influencer profili eklendi.';
                } else {
                    $sql = 'UPDATE influencers SET name=?, slug=?, district=?, niche=?, bio=?, collaboration_types=?, instagram=?, tiktok=?, youtube=?, follower_instagram=?, follower_tiktok=?, follower_youtube=?, followers_verified_at=?, followers_verified_by=?, avatar_path=?, cover_path=?, featured_links=?, contact_email=?, theme_color=?, is_premium=?, is_verified=?, is_published=?, consent_given=?, consent_date=?, meta_description=?, meta_keywords=? WHERE id=?';
                    $stmt = $db->query($sql, [$name, $slug, $district, $niche, $bio, $collaboration_types, $instagram, $tiktok, $youtube, $follower_instagram, $follower_tiktok, $follower_youtube, $followers_verified_at, $followers_verified_by, $avatar_path, $cover_path, $featured_links, $contact_email, $theme_color, $is_premium, $is_verified, $is_published, $consent_given, $consent_date_val, $meta_description, $meta_keywords, $id]);
                    if (function_exists('logAction')) logAction('update', 'influencers', $name, $id);
                    $successMsg = 'Profil güncellendi.';
                }

                $db->getPDO()->prepare('DELETE FROM influencer_business_links WHERE influencer_id = ?')->execute([$id]);
                if (!empty($businessIds)) {
                    $linkStmt = $db->getPDO()->prepare('INSERT IGNORE INTO influencer_business_links (influencer_id, business_id) VALUES (?, ?)');
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
        $stmtName = $db->getPDO()->prepare("SELECT name FROM influencers WHERE id = ?");
        $stmtName->execute([$id]);
        $delName = $stmtName->fetchColumn() ?: 'Influencer ID: ' . $id;

        $db->getPDO()->prepare('DELETE FROM influencers WHERE id = ?')->execute([$id]);
        if (function_exists('logAction')) logAction('delete', 'influencers', $delName, $id);
        $successMsg = 'Profil silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silinemedi: ' . $e->getMessage();
    }
    $action = 'list';
}

$influencer = null;
$linkedBusinessIds = [];
if (in_array($action, ['edit', 'new'], true)) {
    $allBusinesses = $db->query('SELECT id, name, district FROM businesses ORDER BY name ASC')->fetchAll();
}
if ($action === 'edit' && $id > 0) {
    $stmt = $db->query('SELECT * FROM influencers WHERE id = ?', [$id]);
    $influencer = $stmt->fetch();
    if (!$influencer) {
        $errorMsg = 'Profil bulunamadı.';
        $action = 'list';
    } else {
        $links = $db->query('SELECT business_id FROM influencer_business_links WHERE influencer_id = ?', [$id]);
        $linkedBusinessIds = $links->fetchAll(PDO::FETCH_COLUMN);
    }
}

$influencers = [];
if ($action === 'list') {
    $influencers = $db->query('SELECT * FROM influencers ORDER BY is_premium DESC, name ASC')->fetchAll();
}

$niches = influencerNiches();
$districts = influencerDistricts();
$collabTypes = influencerCollabTypes();
$pageTitle = 'Influencer Yönetimi';
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
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-star me-2 text-primary"></i> Influencer Profilleri</h5>
        <div>
            <a href="influencers.php?action=new" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Yeni Profil</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-4">Ad</th><th>İlçe / Niş</th><th>Takipçi (IG)</th><th>Durum</th><th class="text-end pe-4">İşlem</th></tr></thead>
            <tbody>
            <?php
if (empty($influencers)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">Henüz profil yok.</td></tr>
            <?php
else: foreach ($influencers as $inf): ?>
                <tr>
                    <td class="ps-4 fw-bold"><?= htmlspecialchars($inf['name']) ?><br><small class="text-muted font-monospace"><?= htmlspecialchars($inf['slug']) ?></small></td>
                    <td><?= htmlspecialchars($inf['district']) ?><br><small><?= htmlspecialchars(getInfluencerNicheLabel($inf['niche'])) ?></small></td>
                    <td><?= $inf['follower_instagram'] ? number_format($inf['follower_instagram']) : '—' ?><?php
if ($inf['followers_verified_at']): ?><br><small class="text-success">Doğrulandı</small><?php
endif; ?></td>
                    <td>
                        <?php
if ($inf['is_published']): ?><span class="badge bg-success">Yayında</span><?php
else: ?><span class="badge bg-secondary">Taslak</span><?php
endif; ?>
                        <?php
if ($inf['is_verified']): ?><span class="badge bg-primary">Doğrulanmış</span><?php
endif; ?>
                        <?php
if ($inf['is_premium']): ?><span class="badge bg-warning text-dark">Premium</span><?php
endif; ?>
                        <?php
if (!$inf['consent_given']): ?><span class="badge bg-danger">Onay yok</span><?php
endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group gap-2">
                        <?php
if ($inf['is_published'] && $inf['consent_given']):
                            $infQrUrl = rtrim($baseUrl, '/') . influencerQrUrl($inf['slug']);
                            $infQrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($infQrUrl);
                        ?>
                        <button type="button" class="btn btn-primary btn-sm px-2" data-bs-toggle="modal" data-bs-target="#infQrModal<?= $inf['id'] ?>" title="QR Kod Al">
                            <i class="fa-solid fa-qrcode"></i>
                        </button>
                        <?php
endif; ?>
                        <a href="../influencer/<?= htmlspecialchars($inf['slug']) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-eye"></i></a>
                        <a href="influencers.php?action=edit&id=<?= $inf['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                        <a href="influencers.php?action=delete&id=<?= $inf['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu profili silmek istediğinize emin misiniz?" data-confirm-title="Profili Sil" data-confirm-btn="Evet, Sil"><i class="fa-solid fa-trash"></i></a>
                        </div>

                        <?php
if ($inf['is_published'] && $inf['consent_given']): ?>
                        <div class="modal fade" id="infQrModal<?= $inf['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered text-start" style="max-width: 400px;">
                                <div class="modal-content" style="border-radius: var(--radius);">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-qrcode me-2 text-primary"></i> Influencer QR Kodu</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                    </div>
                                    <div class="modal-body text-center p-4">
                                        <h6 class="fw-bold mb-3 text-navy"><?= htmlspecialchars($inf['name']) ?></h6>
                                        <div class="p-3 border bg-white rounded-3 d-inline-block shadow-sm mb-3">
                                            <img src="<?= $infQrImageSrc ?>" alt="QR Code" class="img-fluid" style="width: 200px; height: 200px;">
                                        </div>
                                        <div class="mb-3 text-muted small" style="font-size: 13px; line-height: 1.4;">
                                            Bu QR kod influencer dijital kartvizitine (<a href="<?= htmlspecialchars($infQrUrl) ?>" target="_blank"><?= htmlspecialchars(influencerQrUrl($inf['slug'])) ?></a>) yönlendirir.
                                        </div>
                                        <button class="btn btn-success w-100 py-2 fw-bold" onclick="downloadInfluencerQR('<?= $infQrImageSrc ?>', '<?= htmlspecialchars(addslashes($inf['name'])) ?>')">
                                            <i class="fa-solid fa-download me-2"></i> QR Kodu İndir (PNG)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
endif; ?>
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
    $inf = $influencer ?: [];
    $isEdit = $action === 'edit';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-3"><h5 class="mb-0 fw-bold"><?= $isEdit ? 'Profili Düzenle' : 'Yeni Influencer Profili' ?></h5></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Ad / Sahne Adı *</label><input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($inf['name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Slug *</label><input type="text" name="slug" class="form-control" required value="<?= htmlspecialchars($inf['slug'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">İlçe *</label><select name="district" class="form-select" required><?php
foreach ($districts as $d): ?><option value="<?= htmlspecialchars($d) ?>" <?= (($inf['district'] ?? '') === $d) ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Niş</label><select name="niche" class="form-select"><?php
foreach ($niches as $s => $l): ?><option value="<?= htmlspecialchars($s) ?>" <?= (($inf['niche'] ?? 'diger') === $s) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option><?php
endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Tema Rengi</label><input type="color" name="theme_color" class="form-control form-control-color" value="<?= htmlspecialchars($inf['theme_color'] ?? '#1e3932') ?>"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($inf['bio'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label">İş birliği türleri (virgülle: restoran,reel,marka)</label><input type="text" name="collaboration_types" class="form-control" value="<?= htmlspecialchars($inf['collaboration_types'] ?? '') ?>" placeholder="restoran,urun,reel"></div>
                <div class="col-md-4"><label class="form-label">Instagram URL</label><input type="url" name="instagram" class="form-control" value="<?= htmlspecialchars($inf['instagram'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">TikTok URL</label><input type="url" name="tiktok" class="form-control" value="<?= htmlspecialchars($inf['tiktok'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">YouTube URL</label><input type="url" name="youtube" class="form-control" value="<?= htmlspecialchars($inf['youtube'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">Instagram takipçi (manuel)</label><input type="number" name="follower_instagram" class="form-control" value="<?= htmlspecialchars($inf['follower_instagram'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">TikTok takipçi (manuel)</label><input type="number" name="follower_tiktok" class="form-control" value="<?= htmlspecialchars($inf['follower_tiktok'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label">YouTube abone (manuel)</label><input type="number" name="follower_youtube" class="form-control" value="<?= htmlspecialchars($inf['follower_youtube'] ?? '') ?>"></div>
                <div class="col-12">
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="followers_verified" id="followers_verified" value="1" <?= !empty($inf['followers_verified_at']) ? 'checked' : '' ?>><label class="form-check-label" for="followers_verified">Takipçi sayılarını doğruladım (bugünün tarihi kaydedilir)</label></div>
                </div>
                <div class="col-md-6"><label class="form-label">Avatar (dosya)</label><input type="file" name="avatar_file" class="form-control"><input type="url" name="avatar_url" class="form-control mt-1" placeholder="veya görsel URL" value="<?= (strpos($inf['avatar_path'] ?? '', 'http') === 0) ? htmlspecialchars($inf['avatar_path']) : '' ?>"></div>
                <div class="col-md-6"><label class="form-label">Kapak (dosya)</label><input type="file" name="cover_file" class="form-control"><input type="url" name="cover_url" class="form-control mt-1" placeholder="veya görsel URL" value="<?= (strpos($inf['cover_path'] ?? '', 'http') === 0) ? htmlspecialchars($inf['cover_path']) : '' ?>"></div>
                <div class="col-12"><label class="form-label">Öne çıkan linkler (satır satır)</label><textarea name="featured_links" class="form-control" rows="2"><?= htmlspecialchars($inf['featured_links'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label">İletişim e-posta (gizli)</label><input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($inf['contact_email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Onay tarihi</label><input type="date" name="consent_date" class="form-control" value="<?= htmlspecialchars($inf['consent_date'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Meta açıklama</label><input type="text" name="meta_description" class="form-control" maxlength="255" value="<?= htmlspecialchars($inf['meta_description'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Meta anahtar kelimeler</label><input type="text" name="meta_keywords" class="form-control" value="<?= htmlspecialchars($inf['meta_keywords'] ?? '') ?>"></div>
                <div class="col-12">
                    <label class="form-label">Bağlı işletmeler</label>
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
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="consent_given" id="consent_given" value="1" <?= !empty($inf['consent_given']) ? 'checked' : '' ?>><label class="form-check-label" for="consent_given">İsim/görsel kullanım onayı alındı</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_verified" id="is_verified" value="1" <?= !empty($inf['is_verified']) ? 'checked' : '' ?>><label class="form-check-label" for="is_verified">Doğrulanmış rozet</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" <?= !empty($inf['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Yayında</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_premium" id="is_premium" value="1" <?= !empty($inf['is_premium']) ? 'checked' : '' ?>><label class="form-check-label" for="is_premium">Premium vitrin</label></div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">Kaydet</button>
                    <a href="influencers.php" class="btn btn-outline-secondary ms-2">İptal</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
endif; ?>

<script>
async function downloadInfluencerQR(qrApiUrl, profileName) {
    try {
        const response = await fetch(qrApiUrl);
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        const tempLink = document.createElement('a');
        tempLink.href = blobUrl;
        const sanitizedName = profileName.toLowerCase()
            .replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's')
            .replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c')
            .replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');
        tempLink.download = sanitizedName + '-influencer-qr.png';
        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 100);
    } catch (error) {
        console.error('QR İndirme Hatası:', error);
        alert('QR Kod indirilirken bir sorun oluştu. Lütfen QR koduna sağ tıklayıp kaydedin.');
    }
}
</script>

<?php
include 'includes/footer.php'; ?>
