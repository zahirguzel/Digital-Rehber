<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getInstance()->getPDO();
$bizId = $_SESSION['biz_id'] ?? 0;

$successMsg = '';
$errorMsg = '';

// Handle Image Upload
function handleBizUpload($fileKey, $currentValue) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES[$fileKey]['name']));
        $targetDir = __DIR__ . '/../public/images/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $fileName)) {
            return $fileName;
        }
    }
    return $currentValue;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    // Fetch current data to compare
    $stmt = $db->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$bizId]);
    $curr = $stmt->fetch();
    
    $fields = [
        'description' => ['label' => 'Hakkımızda', 'new' => trim($_POST['description'] ?? '')],
        'address' => ['label' => 'Adres', 'new' => trim($_POST['address'] ?? '')],
        'phone' => ['label' => 'Telefon', 'new' => trim($_POST['phone'] ?? '')],
        'whatsapp' => ['label' => 'WhatsApp', 'new' => trim($_POST['whatsapp'] ?? '')],
        'email' => ['label' => 'E-posta Adresi', 'new' => trim($_POST['email'] ?? '')],
        'instagram' => ['label' => 'Instagram', 'new' => trim($_POST['instagram'] ?? '')],
        'facebook' => ['label' => 'Facebook', 'new' => trim($_POST['facebook'] ?? '')],
        'tiktok' => ['label' => 'TikTok', 'new' => trim($_POST['tiktok'] ?? '')],
        'website' => ['label' => 'Web Sitesi', 'new' => trim($_POST['website'] ?? '')],
        'yemeksepeti' => ['label' => 'Yemeksepeti', 'new' => trim($_POST['yemeksepeti'] ?? '')],
        'google_maps_iframe' => ['label' => 'Google Maps', 'new' => trim($_POST['google_maps_iframe'] ?? '')],
        'theme_color' => ['label' => 'Tema Rengi', 'new' => trim($_POST['theme_color'] ?? '#E0533C')]
    ];
    
    // Handle Images
    $postLogoUrl = trim($_POST['logo_url'] ?? '');
    $logoFallback = !empty($postLogoUrl) ? $postLogoUrl : $curr['logo_path'];
    $newLogo = handleBizUpload('logo_file', $logoFallback);
    if ($newLogo !== $curr['logo_path']) {
        $fields['logo_path'] = ['label' => 'Logo', 'new' => $newLogo, 'type' => 'image'];
    }

    $postCoverUrl = trim($_POST['cover_url'] ?? '');
    $coverFallback = !empty($postCoverUrl) ? $postCoverUrl : $curr['cover_image_path'];
    $newCover = handleBizUpload('cover_file', $coverFallback);
    if ($newCover !== $curr['cover_image_path']) {
        $fields['cover_image_path'] = ['label' => 'Kapak Fotoğrafı', 'new' => $newCover, 'type' => 'image'];
    }

    $changesPending = false;
    $db->beginTransaction();
    try {
        foreach ($fields as $key => $data) {
            $newVal = $data['new'] ?? '';
            $oldVal = $curr[$key] ?? '';
            
            if ($newVal !== $oldVal) {
                // If there's already a pending change for this field, update it. Otherwise insert.
                $check = $db->prepare("SELECT id FROM business_pending_changes WHERE business_id = ? AND field_name = ? AND status = 'pending'");
                $check->execute([$bizId, $key]);
                $existingPending = $check->fetchColumn();
                
                $type = $data['type'] ?? 'text';
                
                if ($existingPending) {
                    $upd = $db->prepare("UPDATE business_pending_changes SET new_value = ?, submitted_at = NOW() WHERE id = ?");
                    $upd->execute([$newVal, $existingPending]);
                } else {
                    $ins = $db->prepare("INSERT INTO business_pending_changes (business_id, field_name, field_label, old_value, new_value, change_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->execute([$bizId, $key, $data['label'], $oldVal, $newVal, $type]);
                }
                $changesPending = true;
            }
        }
        $db->commit();
        if ($changesPending) {
            $successMsg = "Değişiklikleriniz başarıyla kaydedildi. Onaylandıktan sonra yayına alınacaktır.";
        } else {
            $successMsg = "Herhangi bir değişiklik yapılmadı.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $errorMsg = "Hata oluştu: " . $e->getMessage();
    }
}

// Fetch business data
$stmt = $db->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$bizId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch pending changes
$stmtPending = $db->prepare("SELECT * FROM business_pending_changes WHERE business_id = ? AND status = 'pending'");
$stmtPending->execute([$bizId]);
$pending = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
$pendingFields = [];
foreach ($pending as $p) {
    $pendingFields[$p['field_name']] = $p;
}

// Fetch rejected changes for display is removed because it will be handled by notifications.

// Override business data with pending values for display in the form so the user sees what they submitted
foreach ($pendingFields as $key => $p) {
    if ($p['change_type'] === 'text') {
        $business[$key] = $p['new_value'];
    }
}

function getPendingClass($fieldName, $pendingFields) {
    return isset($pendingFields[$fieldName]) ? 'bg-warning bg-opacity-25 border-warning' : '';
}
function getPendingBadge($fieldName, $pendingFields) {
    return isset($pendingFields[$fieldName]) ? '<span class="badge bg-warning text-dark ms-2 small"><i class="fa-solid fa-clock me-1"></i>Onay Bekliyor (Sarı)</span>' : '';
}

$pageTitle = 'Profil Ayarları';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-id-card me-2 text-primary"></i> Profil Ayarları</h3>
    <a href="../esnaf/<?= htmlspecialchars($business['slug'] ?? '') ?>" target="_blank" class="btn btn-outline-primary"><i class="fa-solid fa-external-link-alt me-2"></i>Profili Görüntüle</a>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>


<?php if (!empty($pendingFields)): ?>
    <div class="alert alert-warning shadow-sm border-0 d-flex align-items-start gap-3">
        <i class="fa-solid fa-hourglass-half fs-4 mt-1 text-warning"></i>
        <div>
            <h6 class="fw-bold mb-1">Onay Bekleyen Değişiklikleriniz Var (Sarı)</h6>
            <p class="mb-0 small">Profilinizde yaptığınız son değişiklikler yönetici onayına sunulmuştur. Onaylandıktan sonra yayına alınacaktır. Onay bekleyen alanlar aşağıda <strong>sarı</strong> renkte vurgulanmıştır.</p>
            <div class="mt-2 small">
                <strong>Değişen Alanlar:</strong> 
                <?= implode(', ', array_map(function($p) { return htmlspecialchars($p['field_label']); }, $pendingFields)) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<form action="profile.php" method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
    
    <div class="row g-4">
        <!-- Sol Taraf -->
        <div class="col-lg-8">
            <div class="card border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-bold text-navy"><i class="fa-solid fa-circle-info me-2 text-primary"></i> Genel Bilgiler</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">İşletme Açıklaması (Hakkımızda) <?= getPendingBadge('description', $pendingFields) ?></label>
                        <textarea name="description" class="form-control <?= getPendingClass('description', $pendingFields) ?>" rows="5" placeholder="İşletmenizin tarihçesi, öne çıkan özellikleri..."><?= htmlspecialchars($business['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Açık Adres <?= getPendingBadge('address', $pendingFields) ?></label>
                        <textarea name="address" class="form-control <?= getPendingClass('address', $pendingFields) ?>" rows="2" placeholder="Örn: Atatürk Cad. No:12..."><?= htmlspecialchars($business['address'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Google Maps Iframe Kodu <?= getPendingBadge('google_maps_iframe', $pendingFields) ?></label>
                        <textarea name="google_maps_iframe" class="form-control <?= getPendingClass('google_maps_iframe', $pendingFields) ?>" rows="3" placeholder="<iframe src=...><\/iframe>"><?= htmlspecialchars($business['google_maps_iframe'] ?? '') ?></textarea>
                        <div class="form-text">Haritayı sitenize gömmek için Google Haritalar'dan "Harita Yerleştir" kodunu yapıştırın.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-bold text-navy"><i class="fa-solid fa-link me-2 text-primary"></i> İletişim & Sosyal Medya</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Telefon <?= getPendingBadge('phone', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="text" name="phone" class="form-control border-start-0 <?= getPendingClass('phone', $pendingFields) ?>" value="<?= htmlspecialchars($business['phone'] ?? '') ?>" placeholder="0555...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">WhatsApp <?= getPendingBadge('whatsapp', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-brands fa-whatsapp text-success"></i></span>
                                <input type="text" name="whatsapp" class="form-control border-start-0 <?= getPendingClass('whatsapp', $pendingFields) ?>" value="<?= htmlspecialchars($business['whatsapp'] ?? '') ?>" placeholder="90555...">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">E-posta Adresi <?= getPendingBadge('email', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-primary"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 <?= getPendingClass('email', $pendingFields) ?>" value="<?= htmlspecialchars($business['email'] ?? '') ?>" placeholder="info@isletme.com">
                            </div>
                            <span class="text-muted small" style="font-size: 11px;">Müşteri iletişimi ve panel şifre sıfırlama için kullanılır.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Instagram <?= getPendingBadge('instagram', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-brands fa-instagram text-danger"></i></span>
                                <input type="text" name="instagram" class="form-control border-start-0 <?= getPendingClass('instagram', $pendingFields) ?>" value="<?= htmlspecialchars($business['instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Facebook <?= getPendingBadge('facebook', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-brands fa-facebook text-primary"></i></span>
                                <input type="text" name="facebook" class="form-control border-start-0 <?= getPendingClass('facebook', $pendingFields) ?>" value="<?= htmlspecialchars($business['facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">TikTok <?= getPendingBadge('tiktok', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-brands fa-tiktok text-dark"></i></span>
                                <input type="text" name="tiktok" class="form-control border-start-0 <?= getPendingClass('tiktok', $pendingFields) ?>" value="<?= htmlspecialchars($business['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Yemeksepeti / Trendyol Linki <?= getPendingBadge('yemeksepeti', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-motorcycle text-warning"></i></span>
                                <input type="text" name="yemeksepeti" class="form-control border-start-0 <?= getPendingClass('yemeksepeti', $pendingFields) ?>" value="<?= htmlspecialchars($business['yemeksepeti'] ?? '') ?>" placeholder="Sipariş Linki">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Web Sitesi <?= getPendingBadge('website', $pendingFields) ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-globe text-info"></i></span>
                                <input type="text" name="website" class="form-control border-start-0 <?= getPendingClass('website', $pendingFields) ?>" value="<?= htmlspecialchars($business['website'] ?? '') ?>" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sağ Taraf -->
        <div class="col-lg-4">
            <div class="card border-0 mb-4 sticky-top" style="top: 90px;">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-bold text-navy"><i class="fa-solid fa-image me-2 text-primary"></i> Görsel Ayarları</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4 text-center">
                        <?php 
                        $logoShow = !empty($business['logo_path']) 
                            ? (strpos($business['logo_path'], 'http') === 0 ? $business['logo_path'] : '../public/images/' . $business['logo_path']) 
                            : '../public/images/default-logo.png'; 
                        ?>
                        <img src="<?= htmlspecialchars($logoShow) ?>" alt="Logo" class="img-thumbnail rounded-circle mb-2" style="width: 120px; height: 120px; object-fit: cover;">
                        <div class="small text-muted mb-2">Mevcut Logo</div>
                        
                        <input type="file" name="logo_file" class="form-control form-control-sm mb-2" accept="image/*">
                        <div class="text-muted small mb-1">- VEYA URL GİRİN -</div>
                        <input type="url" name="logo_url" class="form-control form-control-sm" value="<?= (strpos($business['logo_path'] ?? '', 'http') === 0) ? htmlspecialchars($business['logo_path']) : '' ?>" placeholder="https://...">
                    </div>
                    
                    <hr>
                    
                    <div class="mb-4 text-center">
                        <?php 
                        $coverShow = !empty($business['cover_image_path']) 
                            ? (strpos($business['cover_image_path'], 'http') === 0 ? $business['cover_image_path'] : '../public/images/' . $business['cover_image_path']) 
                            : '../public/images/default-cover.jpg'; 
                        ?>
                        <img src="<?= htmlspecialchars($coverShow) ?>" alt="Kapak" class="img-thumbnail mb-2" style="width: 100%; height: 100px; object-fit: cover;">
                        <div class="small text-muted mb-2">Mevcut Kapak Görseli</div>
                        
                        <input type="file" name="cover_file" class="form-control form-control-sm mb-2" accept="image/*">
                        <div class="text-muted small mb-1">- VEYA URL GİRİN -</div>
                        <input type="url" name="cover_url" class="form-control form-control-sm" value="<?= (strpos($business['cover_image_path'], 'http') === 0) ? htmlspecialchars($business['cover_image_path']) : '' ?>" placeholder="https://...">
                    </div>
                    
                    <hr>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Tema Rengi</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="theme_color" class="form-control form-control-color p-1" value="<?= htmlspecialchars($business['theme_color'] ?? '#E0533C') ?>" title="Tema Renginizi Seçin">
                            <span class="small text-muted">İşletme sayfanızın renk tonu.</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-save me-2"></i> Değişiklikleri Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>