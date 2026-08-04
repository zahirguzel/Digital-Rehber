<?php
require_once '../autoload.php';
// NEW: Enterprise autoloader
require_once 'includes/auth.php'; // Authentication & authorization

// Role check: Only superadmin and admin can manage businesses
requireRole(['superadmin', 'admin']);

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance(); // NEW: Use Database singleton

// Fetch Cities and Districts for dropdowns
$citiesData = [];
try {
    $cRes = $db->getPDO()->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll();
    foreach ($cRes as $c) {
        $dRes = $db->getPDO()->prepare("SELECT name FROM districts WHERE city_id = ? ORDER BY name ASC");
        $dRes->execute([$c['id']]);
        $citiesData[$c['name']] = $dRes->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
$citiesJson = json_encode($citiesData, JSON_UNESCAPED_UNICODE);

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Dynamically resolve base URL for QR codes
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain . str_replace('admin/businesses.php', '', $_SERVER['PHP_SELF']);

$successMsg = '';
$errorMsg = '';

// Helper function for uploading images
function handleUpload($fileKey, $fallbackUrlKey, $currentValue = '')
{
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES[$fileKey]['name']));
        $targetDir = '../public/images/';

        // Ensure folder exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $fileName)) {
            return $fileName;
        }
    }

    // Check fallback URL field
    if (!empty($_POST[$fallbackUrlKey])) {
        return trim($_POST[$fallbackUrlKey]);
    }

    return $currentValue;
}

// ----------------------------------------------------
// PROCESS ACTIONS (Insert, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reset password
    if (isset($_POST['action']) && $_POST['action'] === 'reset_pw') {
        $bizId = intval($_POST['business_id']);
        try {
            $user = $db->query("SELECT * FROM business_users WHERE business_id = ?", [$bizId])->fetch();
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789!*?';
            $newPw = substr(str_shuffle($chars), 0, 6);
            $hashed = SecurityHelper::hashPassword($newPw);
            
            if ($user) {
                $db->query("UPDATE business_users SET password = ?, force_password_change = 1 WHERE id = ?", [$hashed, $user['id']]);
                $username = $user['username'];
            } else {
                // Fetch slug to generate username
                $biz = $db->query("SELECT slug FROM businesses WHERE id = ?", [$bizId])->fetch();
                if ($biz) {
                    $baseUsername = str_replace('-', '', $biz['slug']);
                    $username = $baseUsername;
                    $counter = 1;
                    while (true) {
                        $check = $db->query("SELECT COUNT(*) FROM business_users WHERE username = ?", [$username])->fetchColumn();
                        if ($check == 0) break;
                        $username = $baseUsername . $counter;
                        $counter++;
                    }
                    $db->query("INSERT INTO business_users (business_id, username, password, role, force_password_change) VALUES (?, ?, ?, 'business', 1)", [$bizId, $username, $hashed]);
                } else {
                    throw new Exception("İşletme bulunamadı.");
                }
            }
            $successMsg = "Şifre oluşturuldu/sıfırlandı! <br><strong>Kullanıcı Adı:</strong> {$username} <br><strong>Yeni Şifre:</strong> {$newPw} <br><small>Lütfen bu şifreyi işletmeye iletin. İlk girişinde değiştirmek zorunda kalacaktır.</small>";
        } catch (Exception $e) {
            $errorMsg = "Hata oluştu: " . $e->getMessage();
        }
        $action = 'list';
    }

    // Add or Update Business
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $category_id = intval($_POST['category_id']);
        $city = trim($_POST['city']);
        if ($city === '') {
            try {
                $settingsDefaultCity = $db->query("SELECT default_city FROM settings LIMIT 1")->fetchColumn();
                $city = trim((string) $settingsDefaultCity);
            } catch (Exception $e) {
                $city = '';
            }
        }
        $district = trim($_POST['district']);
        $description = trim($_POST['description']);
        $address = trim($_POST['address']);
        $phone = trim($_POST['phone']);
        $whatsapp = trim($_POST['whatsapp']);
        $email = trim($_POST['email'] ?? '');
        $google_maps_iframe = trim($_POST['google_maps_iframe']);
        $instagram = trim($_POST['instagram']);
        $tiktok = trim($_POST['tiktok']);
        $facebook = trim($_POST['facebook']);
        $menu_url = trim($_POST['menu_url']);
        $website = trim($_POST['website']);
        $yemeksepeti = trim($_POST['yemeksepeti']);
        $theme_color = trim($_POST['theme_color']) ?: '#1e3932';
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;

        if ($menu_url !== '' && strpos($menu_url, 'http://') !== 0 && strpos($menu_url, 'https://') !== 0) {
            $menu_url = 'https://' . $menu_url;
        }
        if ($website !== '' && strpos($website, 'http://') !== 0 && strpos($website, 'https://') !== 0) {
            $website = 'https://' . $website;
        }

        // Validation
        if (empty($name) || empty($slug) || empty($district) || $category_id <= 0) {
            $errorMsg = "İşletme adı, slug, ilçe ve kategori alanları zorunludur.";
        } else {
            // Check if slug is unique (excluding current ID on edit)
            try {
                $stmtCheck = $db->query("SELECT COUNT(*) FROM businesses WHERE slug = ? AND id != ?", [$slug, $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $errorMsg = "Bu URL (slug) başka bir işletme tarafından kullanılıyor.";
                } else {

                    // Handle uploads
                    $currentLogo = '';
                    $currentCover = '';
                    if ($_POST['action'] === 'edit') {
                        $stmtCur = $db->query("SELECT logo_path, cover_image_path FROM businesses WHERE id = ?", [$id]);
                        $curData = $stmtCur->fetch();
                        if ($curData) {
                            $currentLogo = $curData['logo_path'];
                            $currentCover = $curData['cover_image_path'];
                        }
                    }

                    $remove_logo = isset($_POST['remove_logo']) ? 1 : 0;
                    $remove_cover = isset($_POST['remove_cover']) ? 1 : 0;

                    if ($remove_logo) {
                        $logo_path = 'default_logo.png';
                        if (!empty($currentLogo) && $currentLogo !== 'default_logo.png' && strpos($currentLogo, 'http') !== 0) {
                            @unlink('../public/images/' . $currentLogo);
                        }
                    } else {
                        $logo_path = handleUpload('logo_file', 'logo_url', $currentLogo) ?: 'default_logo.png';
                        if ($logo_path !== $currentLogo && !empty($currentLogo) && $currentLogo !== 'default_logo.png' && strpos($currentLogo, 'http') !== 0) {
                            @unlink('../public/images/' . $currentLogo);
                        }
                    }

                    if ($remove_cover) {
                        $cover_image_path = 'default_cover.jpg';
                        if (!empty($currentCover) && $currentCover !== 'default_cover.jpg' && strpos($currentCover, 'http') !== 0) {
                            @unlink('../public/images/' . $currentCover);
                        }
                    } else {
                        $cover_image_path = handleUpload('cover_file', 'cover_url', $currentCover) ?: 'default_cover.jpg';
                        if ($cover_image_path !== $currentCover && !empty($currentCover) && $currentCover !== 'default_cover.jpg' && strpos($currentCover, 'http') !== 0) {
                            @unlink('../public/images/' . $currentCover);
                        }
                    }

                    if ($_POST['action'] === 'add') {
                        // Insert
                        $sql = "INSERT INTO businesses (category_id, city, district, name, slug, description, address, phone, whatsapp, email, google_maps_iframe, instagram, tiktok, facebook, menu_url, website, yemeksepeti, theme_color, logo_path, cover_image_path, is_premium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmtIns = $db->query($sql, [$category_id, $city, $district, $name, $slug, $description, $address, $phone, $whatsapp, $email, $google_maps_iframe, $instagram, $tiktok, $facebook, $menu_url, $website, $yemeksepeti, $theme_color, $logo_path, $cover_image_path, $is_premium]);
                        $businessId = $db->getPDO()->lastInsertId();

                        // Generate username
                        $baseUsername = str_replace('-', '', $slug);
                        $username = $baseUsername;
                        $counter = 1;
                        while (true) {
                            $check = $db->query("SELECT COUNT(*) FROM business_users WHERE username = ?", [$username])->fetchColumn();
                            if ($check == 0) break;
                            $username = $baseUsername . $counter;
                            $counter++;
                        }

                        // Generate Password
                        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789!*?';
                        $newPassword = substr(str_shuffle($chars), 0, 6);
                        $hashedPw = SecurityHelper::hashPassword($newPassword);

                        $db->query("INSERT INTO business_users (business_id, username, password, force_password_change) VALUES (?, ?, ?, 1)", [$businessId, $username, $hashedPw]);

                        if (function_exists('logAction')) logAction('create', 'businesses', $name, $businessId);

                        $successMsg = "İşletme başarıyla eklendi!<br><strong>Kullanıcı Adı:</strong> {$username}<br><strong>Şifre:</strong> {$newPassword}<br><small>Lütfen bu şifreyi işletmeye iletin.</small>";
                        $action = 'list'; // Redirect to list
                    } else {
                        // Update
                        $sql = "UPDATE businesses SET category_id = ?, city = ?, district = ?, name = ?, slug = ?, description = ?, address = ?, phone = ?, whatsapp = ?, email = ?, google_maps_iframe = ?, instagram = ?, tiktok = ?, facebook = ?, menu_url = ?, website = ?, yemeksepeti = ?, theme_color = ?, logo_path = ?, cover_image_path = ?, is_premium = ? WHERE id = ?";
                        $stmtUp = $db->query($sql, [$category_id, $city, $district, $name, $slug, $description, $address, $phone, $whatsapp, $email, $google_maps_iframe, $instagram, $tiktok, $facebook, $menu_url, $website, $yemeksepeti, $theme_color, $logo_path, $cover_image_path, $is_premium, $id]);
                        
                        // Handle Business User update
                        $buUser = trim($_POST['bu_username'] ?? '');
                        $buPass = trim($_POST['bu_password'] ?? '');
                        if (!empty($buUser)) {
                            $checkUser = $db->query("SELECT id FROM business_users WHERE business_id = ?", [$id])->fetch();
                            if ($checkUser) {
                                $uSql = "UPDATE business_users SET username = ?";
                                $uParams = [$buUser];
                                if (!empty($buPass) && strlen($buPass) >= 6) {
                                    $uSql .= ", password = ?, force_password_change = 1";
                                    $uParams[] = password_hash($buPass, PASSWORD_DEFAULT);
                                }
                                $uSql .= " WHERE id = ?";
                                $uParams[] = $checkUser['id'];
                                $db->query($uSql, $uParams);
                            } else {
                                if (!empty($buPass) && strlen($buPass) >= 6) {
                                    $db->query("INSERT INTO business_users (business_id, username, password, role, force_password_change) VALUES (?, ?, ?, 'business', 1)", [$id, $buUser, password_hash($buPass, PASSWORD_DEFAULT)]);
                                }
                            }
                        }

                        if (function_exists('logAction')) logAction('update', 'businesses', $name, $id);

                        $successMsg = "İşletme başarıyla güncellendi.";
                        $action = 'list'; // Redirect to list
                    }
                }
            } catch (Exception $e) {
                $errorMsg = "İşlem sırasında hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Soft Delete Business
if ($action === 'delete' && $id > 0) {
    try {
        $stmtName = $db->query("SELECT name FROM businesses WHERE id = ?", [$id]);
        $bizRow = $stmtName->fetch();
        $bizName = $bizRow ? $bizRow['name'] : 'Bilinmeyen İşletme';

        $db->query("UPDATE businesses SET is_deleted = 1 WHERE id = ?", [$id]);
        if (function_exists('logAction')) logAction('delete', 'businesses', "Soft Delete: $bizName", $id);
        $successMsg = "İşletme başarıyla silindi (Çöp Kutusuna taşındı).";
    } catch (Exception $e) {
        $errorMsg = "İşletme silinirken hata oluştu: " . $e->getMessage();
    }
    $action = 'list';
}

// Admin Gallery Image Delete
if (isset($_GET['del_gallery']) && intval($_GET['del_gallery']) > 0) {
    $delId = intval($_GET['del_gallery']);
    try {
        $row = $db->query("SELECT image_path, business_id FROM business_gallery WHERE id = ?", [$delId])->fetch();
        if ($row) {
            $db->query("DELETE FROM business_gallery WHERE id = ?", [$delId]);
            $file = __DIR__ . '/../public/images/gallery/' . $row['business_id'] . '/' . $row['image_path'];
            if (file_exists($file)) @unlink($file);
            if (function_exists('logAction')) logAction('delete', 'business_gallery', 'Galeri Görseli ID: ' . $delId, $delId);
            $successMsg = "Galeri görseli başarıyla silindi.";
            $action = 'edit';
            $id = intval($row['business_id']);
        }
    } catch (Exception $e) {
        $errorMsg = "Galeri görseli silinemedi.";
    }
}

// Admin Gallery Image Upload
if (isset($_POST['admin_upload_gallery']) && intval($_POST['admin_upload_gallery']) > 0 && isset($_FILES['gallery_file'])) {
    $bizIdUpload = intval($_POST['admin_upload_gallery']);
    try {
        $count = $db->query("SELECT COUNT(*) FROM business_gallery WHERE business_id = ?", [$bizIdUpload])->fetchColumn();
        if ($count >= 6) {
            $errorMsg = "Bir işletmeye en fazla 6 adet galeri görseli eklenebilir.";
        } else {
            $file = $_FILES['gallery_file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $dirPath = __DIR__ . '/../public/images/gallery/' . $bizIdUpload;
                    if (!is_dir($dirPath)) @mkdir($dirPath, 0777, true);
                    $newName = uniqid('admin_gal_') . '.webp';
                    $targetPath = $dirPath . '/' . $newName;

                    $info = getimagesize($file['tmp_name']);
                    if ($info !== false) {
                        $width = $info[0]; $height = $info[1]; $type = $info[2];
                        $src = null;
                        if ($type == IMAGETYPE_JPEG) $src = imagecreatefromjpeg($file['tmp_name']);
                        elseif ($type == IMAGETYPE_PNG) $src = imagecreatefrompng($file['tmp_name']);
                        elseif ($type == IMAGETYPE_WEBP) $src = imagecreatefromwebp($file['tmp_name']);
                        if ($src) {
                            if ($width > 1200) {
                                $newWidth = 1200;
                                $newHeight = intval($height * ($newWidth / $width));
                                $dst = imagecreatetruecolor($newWidth, $newHeight);
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                imagewebp($dst, $targetPath, 85);
                                imagedestroy($dst);
                            } else {
                                imagewebp($src, $targetPath, 85);
                            }
                            imagedestroy($src);
                            $maxOrd = $db->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM business_gallery WHERE business_id=?", [$bizIdUpload])->fetchColumn();
                            $db->query("INSERT INTO business_gallery (business_id, image_path, sort_order) VALUES (?, ?, ?)", [$bizIdUpload, $newName, $maxOrd]);
                            $successMsg = "Galeri görseli başarıyla eklendi (Yönetici yetkisiyle onaylandı).";
                        } else { $errorMsg = "Görsel dönüştürülemedi."; }
                    } else { $errorMsg = "Geçersiz görsel verisi."; }
                } else { $errorMsg = "JPG, PNG veya WEBP yükleyebilirsiniz."; }
            } else { $errorMsg = "Dosya yüklenirken hata oluştu."; }
        }
    } catch (Exception $e) {
        $errorMsg = "Galeri görseli yüklenemedi: " . $e->getMessage();
    }
    $action = 'edit';
    $id = $bizIdUpload;
}

// ----------------------------------------------------
// FETCH VIEW DATA
// ----------------------------------------------------
$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $errorMsg = "Kategoriler yüklenemedi: " . $e->getMessage();
}

// Fetch Cities and Districts for dropdowns
$citiesData = [];
$defaultCity = '';
try {
    $settingsRow = $db->query("SELECT default_city FROM settings LIMIT 1")->fetch();
    if ($settingsRow && !empty($settingsRow['default_city'])) {
        $defaultCity = $settingsRow['default_city'];
    }
    $cRes = $db->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll();
    foreach ($cRes as $c) {
        $dRes = $db->query("SELECT name FROM districts WHERE city_id = ? ORDER BY name ASC", [$c['id']]);
        $citiesData[$c['name']] = $dRes->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
$citiesJson = json_encode($citiesData, JSON_UNESCAPED_UNICODE);

$business = null;
$adminGalleryImages = [];
if ($action === 'edit' && $id > 0) {
    try {
        $stmtBiz = $db->query("SELECT * FROM businesses WHERE id = ?", [$id]);
        $business = $stmtBiz->fetch();
        if (!$business) {
            $errorMsg = "Düzenlenecek işletme bulunamadı.";
            $action = 'list';
        } else {
            $bizUser = $db->query("SELECT * FROM business_users WHERE business_id = ?", [$id])->fetch();
            $adminGalleryImages = $db->query("SELECT * FROM business_gallery WHERE business_id = ? ORDER BY sort_order ASC, id ASC", [$id])->fetchAll();
        }
    } catch (Exception $e) {
        $errorMsg = "İşletme bilgileri yüklenemedi.";
        $action = 'list';
    }
}

// Fetch list of businesses
$businesses = [];
if ($action === 'list') {
    try {
        $search = $_GET['search'] ?? '';
        $catFilter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

        $sql = "SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.is_deleted = 0";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (b.name LIKE ? OR b.district LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($catFilter > 0) {
            $sql .= " AND b.category_id = ?";
            $params[] = $catFilter;
        }

        $sql .= " ORDER BY b.name ASC";

        $stmtList = $db->query($sql, $params);
        $businesses = $stmtList->fetchAll();
    } catch (Exception $e) {
        $errorMsg = "Esnaf listesi yüklenirken hata oluştu.";
    }
}

$pageTitle = 'Esnaf Yönetimi';
include 'includes/header.php';
?>

<!-- Alerts -->
<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= $successMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<!-- LIST VIEW -->
<?php if ($action === 'list'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-shop me-2 text-primary"></i> Kayıtlı Esnaflar</h5>
            <a href="businesses.php?action=new" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-plus me-1"></i>
                Yeni Esnaf Ekle</a>
        </div>

        <!-- Search & Filters -->
        <div class="card-body bg-light border-bottom p-3">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="İşletme adı veya ilçe ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">-- Tüm Kategoriler --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category_id']) && intval($_GET['category_id']) == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-navy btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>
                        Filtrele</button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Esnaf Adı</th>
                            <th>Kategori</th>
                            <th>İlçe</th>
                            <th>İletişim</th>
                            <th>Premium</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($businesses)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Aramanıza uygun esnaf bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($businesses as $biz): ?>
                                <tr>
                                    <td class="ps-4 py-3 fw-bold text-navy">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block border"
                                                style="width: 10px; height: 10px; background-color: <?= htmlspecialchars($biz['theme_color']) ?>; border-radius: 50%;"></span>
                                            <?= htmlspecialchars($biz['name']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($biz['category_name']) ?></td>
                                    <td><i
                                            class="fa-solid fa-location-dot text-primary me-2"></i><?= htmlspecialchars($biz['district']) ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted mb-1"><i
                                                class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($biz['phone']) ?></div>
                                        <?php if (!empty($biz['whatsapp'])): ?>
                                            <div class="text-success small"><i
                                                    class="fa-brands fa-whatsapp me-1 fw-bold"></i><?= htmlspecialchars($biz['whatsapp']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($biz['email'])): ?>
                                            <div class="text-primary small"><i
                                                    class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($biz['email']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($biz['is_premium']): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i>
                                                Premium</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Standart</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <!-- QR code Modal trigger -->
                                            <button type="button" class="btn btn-primary btn-sm px-2" data-bs-toggle="modal"
                                                data-bs-target="#qrModal<?= $biz['id'] ?>" title="QR Kod Al">
                                                <i class="fa-solid fa-qrcode"></i> QR
                                            </button>
                                            
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Bu işletmenin şifresini sıfırlamak istediğinize emin misiniz?');">
                                                <?= CSRFMiddleware::field() ?>
                                                <input type="hidden" name="action" value="reset_pw">
                                                <input type="hidden" name="business_id" value="<?= $biz['id'] ?>">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Şifre Sıfırla"><i class="fa-solid fa-key"></i></button>
                                            </form>

                                            <a href="businesses.php?action=edit&id=<?= $biz['id'] ?>"
                                                class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                            <a href="businesses.php?action=delete&id=<?= $biz['id'] ?>"
                                                class="btn btn-outline-danger btn-sm confirm-btn"
                                                data-confirm="Bu işletmeyi kalıcı olarak silmek istediğinizden emin misiniz?"
                                                data-confirm-title="İşletmeyi Sil"><i class="fa-solid fa-trash"></i></a>
                                        </div>

                                        <!-- QR CODE VIEW MODAL -->
                                        <div class="modal fade" id="qrModal<?= $biz['id'] ?>" tabindex="-1"
                                            aria-labelledby="qrModalLabel<?= $biz['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start" style="max-width: 400px;">
                                                <div class="modal-content" style="border-radius: var(--radius);">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold text-navy"
                                                            id="qrModalLabel<?= $biz['id'] ?>">
                                                            <i class="fa-solid fa-qrcode me-2 text-primary"></i> QR Kod Oluşturucu
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Kapat"></button>
                                                    </div>
                                                    <div class="modal-body text-center p-4">
                                                        <h6 class="fw-bold mb-3 text-navy"><?= htmlspecialchars($biz['name']) ?>
                                                        </h6>

                                                        <!-- Dynamic QR code using public secure API -->
                                                        <div class="p-3 border bg-white rounded-3 d-inline-block shadow-sm mb-3">
                                                            <?php
                                                            $qrUrl = $baseUrl . $biz['slug'];
                                                            $qrImageSrc = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrUrl);
                                                            ?>
                                                            <img id="qrImg<?= $biz['id'] ?>" src="<?= $qrImageSrc ?>" alt="QR Code"
                                                                class="img-fluid" style="width: 200px; height: 200px;">
                                                        </div>

                                                        <div class="mb-3 text-muted small"
                                                            style="font-size: 13px; line-height: 1.4;">
                                                            Bu QR kod esnafın mobil dijital kartvizitine (<a
                                                                href="<?= htmlspecialchars($qrUrl) ?>"
                                                                target="_blank">/<?= htmlspecialchars($biz['slug']) ?></a>)
                                                            yönlendirir. Masalara veya vitrine asılabilir.
                                                        </div>

                                                        <button class="btn btn-success w-100 py-2 fw-bold"
                                                            onclick="downloadQRCode('<?= $qrImageSrc ?>', '<?= htmlspecialchars(addslashes($biz['name'])) ?>')">
                                                            <i class="fa-solid fa-download me-2"></i> QR Kodu İndir (PNG)
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ADD NEW OR EDIT VIEW -->
<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid <?= $action === 'new' ? 'fa-plus text-success' : 'fa-pen text-primary' ?> me-2"></i>
                <?= $action === 'new' ? 'Yeni Esnaf Ekle' : htmlspecialchars($business['name']) . ' - Düzenle' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">

                <div class="row g-4">
                    <!-- General Details Section -->
                    <div class="col-12">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i
                                class="fa-solid fa-circle-info me-2 text-primary"></i> Temel Bilgiler</h6>
                    </div>

                    <!-- Business Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">İşletme Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="biz_name" class="form-control" required
                            value="<?= htmlspecialchars($business['name'] ?? '') ?>" placeholder="Örn: şehir Sofrası">
                    </div>

                    <!-- Slug URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Temiz URL / Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="biz_slug" class="form-control font-monospace" required
                            value="<?= htmlspecialchars($business['slug'] ?? '') ?>" placeholder="örn-url-degeri">
                        <span class="text-muted small" style="font-size: 11px;">Site üzerinde /esnafadi şeklinde çıkacak
                            temiz bağlantı. Sadece küçük harf, rakam ve tire içermelidir.</span>
                    </div>

                    <!-- Category -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Kategori Seçin...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($business['category_id']) && $business['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- City -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Bölge (Şehir) <span class="text-danger">*</span></label>
                        <select name="city" id="citySelect" class="form-select" required disabled title="Bölge (Şehir) genel ayarlardan belirlenir ve buradan değiştirilemez.">
                            <option value="">-- Bölge Seçin --</option>
                            <?php foreach($citiesData as $cName => $dists): ?>
                                <option value="<?= htmlspecialchars($cName) ?>" <?= (($business['city'] ?? '') === $cName) ? 'selected' : '' ?>><?= htmlspecialchars($cName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- District -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">İlçe <span class="text-danger">*</span></label>
                        <select name="district" id="districtSelect" class="form-select" required>
                            <option value="">-- Önce Bölge Seçin --</option>
                        </select>
                        <!-- Hidden district value to preselect on edit -->
                        <input type="hidden" id="preselectedDistrict" value="<?= htmlspecialchars($business['district'] ?? '') ?>">
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">İşletme Açıklaması</label>
                        <textarea name="description" class="form-control" rows="4"
                            placeholder="İşletmenin sunduğu hizmetler, tarihçesi vb..."><?= htmlspecialchars($business['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Visual Settings Section -->
                    <div class="col-12 mt-5">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i
                                class="fa-solid fa-image me-2 text-primary"></i> Görsel & Kimlik Ayarları</h6>
                    </div>

                    <!-- Logo Upload -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logo Görseli</label>
                        <input type="file" name="logo_file" class="form-control mb-2">
                        <input type="text" name="logo_url" class="form-control form-control-sm"
                            placeholder="Veya harici logo URL adresi yapıştırın..."
                            value="<?= htmlspecialchars((!empty($business['logo_path']) && strpos($business['logo_path'], 'http') === 0) ? $business['logo_path'] : '') ?>">
                        <?php if (!empty($business['logo_path']) && strpos($business['logo_path'], 'http') !== 0): ?>
                            <div class="small text-success mt-1"><i class="fa-solid fa-file-image me-1"></i> Mevcut dosya:
                                <?= htmlspecialchars($business['logo_path']) ?></div>
                            <div class="mt-2">
                                <img src="../public/images/<?= htmlspecialchars($business['logo_path']) ?>" alt="Logo Önizleme" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc; padding: 2px;">
                            </div>
                        <?php endif; ?>
                        <?php if ($action === 'edit' && !empty($business['logo_path']) && $business['logo_path'] !== 'default_logo.png'): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_logo" id="removeLogoCheck"
                                    value="1">
                                <label class="form-check-label small text-danger fw-semibold" for="removeLogoCheck"
                                    style="cursor: pointer;">
                                    <i class="fa-solid fa-trash me-1"></i> Mevcut Logoyu Kaldır (Varsayılana Sıfırla)
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Cover Image Upload -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kapak Görseli</label>
                        <input type="file" name="cover_file" class="form-control mb-2">
                        <input type="text" name="cover_url" class="form-control form-control-sm"
                            placeholder="Veya harici kapak resmi URL adresi yapıştırın..."
                            value="<?= htmlspecialchars((!empty($business['cover_image_path']) && strpos($business['cover_image_path'], 'http') === 0) ? $business['cover_image_path'] : '') ?>">
                        <?php if (!empty($business['cover_image_path']) && strpos($business['cover_image_path'], 'http') !== 0): ?>
                            <div class="small text-success mt-1"><i class="fa-solid fa-file-image me-1"></i> Mevcut dosya:
                                <?= htmlspecialchars($business['cover_image_path']) ?></div>
                            <div class="mt-2">
                                <img src="../public/images/<?= htmlspecialchars($business['cover_image_path']) ?>" alt="Kapak Önizleme" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc; padding: 2px;">
                            </div>
                        <?php endif; ?>
                        <?php if ($action === 'edit' && !empty($business['cover_image_path']) && $business['cover_image_path'] !== 'default_cover.jpg'): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_cover" id="removeCoverCheck"
                                    value="1">
                                <label class="form-check-label small text-danger fw-semibold" for="removeCoverCheck"
                                    style="cursor: pointer;">
                                    <i class="fa-solid fa-trash me-1"></i> Mevcut Kapak Görselini Kaldır (Varsayılana Sıfırla)
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Contact & Map Section -->
                    <div class="col-12 mt-5">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i
                                class="fa-solid fa-address-book me-2 text-primary"></i> İletişim & Harita Detayları</h6>
                    </div>

                    <!-- Phone -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tel No</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?= htmlspecialchars($business['phone'] ?? '') ?>" placeholder="Örn: 05xx xxx xx xx">
                    </div>

                    <!-- WhatsApp -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">WhatsApp Numarası</label>
                        <input type="text" name="whatsapp" class="form-control"
                            value="<?= htmlspecialchars($business['whatsapp'] ?? '') ?>" placeholder="Örn: 905321112233">
                        <span class="text-muted small" style="font-size: 11px;">Başında ülke koduyla boşluksuz yazın (örn.
                            90 ile başlayarak).</span>
                    </div>

                    <!-- Email -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">E-posta Adresi</label>
                        <input type="email" name="email" id="edit_email" class="form-control"
                            value="<?= htmlspecialchars($business['email'] ?? '') ?>" placeholder="Örn: info@isletme.com">
                        <span class="text-muted small" style="font-size: 11px;">İşletme iletişim ve şifre sıfırlama e-postası.</span>
                    </div>

                    <!-- Address -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Fiziksel Adres</label>
                        <input type="text" name="address" class="form-control"
                            value="<?= htmlspecialchars($business['address'] ?? '') ?>"
                            placeholder="Örn: Atatürk Caddesi No:12 Merkez">
                    </div>

                    <!-- Google Maps Iframe -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Google Maps Harita Kodu (Iframe)</label>
                        <textarea name="google_maps_iframe" class="form-control" rows="3"
                            placeholder='<iframe src="https://www.google.com/maps/embed..." ...></iframe>'><?= htmlspecialchars($business['google_maps_iframe'] ?? '') ?></textarea>
                        <span class="text-muted small" style="font-size: 11px;">Google Haritalar üzerinden "Paylaş > Harita
                            yerleştir" seçeneğinde verilen iframe kodunu buraya yapıştırın.</span>
                    </div>

                    <!-- QR Profile & Social Media Section -->
                    <div class="col-12 mt-5">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i
                                class="fa-solid fa-qrcode me-2 text-primary"></i> QR Dijital Profil & Sosyal Medya
                            Bağlantıları</h6>
                    </div>

                    <!-- Custom Theme Color -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">Mobil Profil Sayfa Rengi</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="color" name="theme_color" class="form-control form-control-color"
                                style="width: 70px; height: 44px; padding: 2px;"
                                value="<?= htmlspecialchars($business['theme_color'] ?? '#1e3932') ?>">
                            <span class="text-muted small">QR okutulduğunda açılan sayfanın arka plan marka rengi.</span>
                        </div>
                    </div>

                    <!-- Premium showcase status -->
                    <div class="col-md-8 d-flex align-items-center">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_premium" id="isPremiumSwitch"
                                <?= (isset($business['is_premium']) && $business['is_premium']) ? 'checked' : '' ?>
                                style="transform: scale(1.3); margin-right: 10px; cursor:pointer;">
                            <label class="form-check-label fw-semibold" for="isPremiumSwitch" style="cursor:pointer;">Öne
                                Çıkan (Premium) Esnaf Vitrinine Ekle</label>
                        </div>
                    </div>

                    <!-- Digital Menu -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-solid fa-utensils me-1 text-dark"></i> Dijital Menü URL</label>
                        <input type="url" name="menu_url" class="form-control" placeholder="https://menu.ornek.com"
                            value="<?= htmlspecialchars($business['menu_url'] ?? '') ?>">
                        <span class="text-muted small">QR menü veya online yemek menüsü linki.</span>
                    </div>

                    <!-- Website -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-solid fa-globe me-1 text-primary"></i> Web Sitesi URL</label>
                        <input type="url" name="website" class="form-control" placeholder="https://www.ornek.com"
                            value="<?= htmlspecialchars($business['website'] ?? '') ?>">
                        <span class="text-muted small">İşletmenin resmi kurumsal web sitesi.</span>
                    </div>

                    <!-- Instagram Link -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-brands fa-instagram me-1 text-danger"></i>
                            Instagram Sayfası URL</label>
                        <input type="url" name="instagram" class="form-control"
                            placeholder="https://instagram.com/kullaniciadi"
                            value="<?= htmlspecialchars($business['instagram'] ?? '') ?>">
                    </div>

                    <!-- TikTok Link -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-brands fa-tiktok me-1 text-dark"></i> TikTok
                            Hesabı URL</label>
                        <input type="url" name="tiktok" class="form-control" placeholder="https://tiktok.com/@kullaniciadi"
                            value="<?= htmlspecialchars($business['tiktok'] ?? '') ?>">
                    </div>

                    <!-- Facebook Link -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-brands fa-facebook-f me-1 text-primary"></i>
                            Facebook Sayfası URL</label>
                        <input type="url" name="facebook" class="form-control"
                            placeholder="https://facebook.com/kullaniciadi"
                            value="<?= htmlspecialchars($business['facebook'] ?? '') ?>">
                    </div>

                    <!-- Yemeksepeti Link -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fa-solid fa-motorcycle me-1 text-danger"></i>
                            Yemeksepeti (veya Paket Servis) URL</label>
                        <input type="url" name="yemeksepeti" class="form-control" placeholder="https://yemeksepeti.com/..."
                            value="<?= htmlspecialchars($business['yemeksepeti'] ?? '') ?>">
                    </div>

                    <?php if ($action === 'edit'): ?>
                    <!-- Business User Settings -->
                    <div class="col-12 mt-5">
                        <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i class="fa-solid fa-user-tie me-2 text-primary"></i> İşletme Paneli Giriş Bilgileri</h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kullanıcı Adı</label>
                        <input type="text" name="bu_username" class="form-control"
                            value="<?= htmlspecialchars($bizUser['username'] ?? $business['slug']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Şifre <span class="text-muted fw-normal">(Değiştirmek veya belirlemek için girin)</span></label>
                        <div class="input-group">
                            <input type="text" name="bu_password" id="bu_password" class="form-control" placeholder="Min. 8 karakter (A-Z, a-z, 0-9)">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomPassword()"><i class="fa-solid fa-dice me-1"></i> Rastgele Üret</button>
                        </div>
                        <span class="text-muted small">İşletme yetkilisi ilk girişinde şifresini değiştirmeye zorlanacaktır.</span>
                    </div>
                    <script>
                    function generateRandomPassword() {
                        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789!*?';
                        let pwd = '';
                        for(let i=0; i<6; i++) {
                            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
                        }
                        document.getElementById('bu_password').value = pwd;
                    }
                    </script>
                    <?php endif; ?>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 border-top pt-4 d-flex justify-content-end gap-2">
                    <a href="businesses.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>
                        İptal</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i>
                        Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($action === 'edit'): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-bottom pt-4 pb-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-images me-2 text-primary"></i> Fotoğraf Galerisi Yönetimi (Yönetici Yetkisi)</h5>
                <p class="text-muted small mb-0 mt-1">Ana yönetici hesabı olarak işletmenin galerisine eklediğiniz veya sildiğiniz fotoğraflar <strong>doğrudan onaylanır</strong> ve anında yayına alınır.</p>
            </div>
            <span class="badge bg-primary fs-6"><?= count($adminGalleryImages) ?> / 6 Görsel</span>
        </div>
        <div class="card-body p-4">
            <!-- Mevcut Galeri Görselleri -->
            <?php if (!empty($adminGalleryImages)): ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($adminGalleryImages as $img): ?>
                    <div class="col-md-4 col-lg-2">
                        <div class="card h-100 border shadow-sm">
                            <div class="ratio ratio-4x3 bg-light">
                                <img src="../public/images/gallery/<?= $id ?>/<?= htmlspecialchars($img['image_path']) ?>" class="card-img-top object-fit-cover" alt="Galeri Görseli">
                            </div>
                            <div class="card-body p-2 text-center">
                                <a href="businesses.php?action=edit&id=<?= $id ?>&del_gallery=<?= $img['id'] ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Bu galeri fotoğrafını silmek istediğinize emin misiniz?')">
                                    <i class="fa-solid fa-trash me-1"></i> Sil
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center text-muted py-4 mb-4">
                    <i class="fa-regular fa-image fs-3 d-block mb-2 text-muted"></i>
                    Bu işletmenin henüz galeri fotoğrafı bulunmamaktadır.
                </div>
            <?php endif; ?>

            <!-- Yeni Görsel Yükleme Formu -->
            <?php if (count($adminGalleryImages) < 6): ?>
                <form action="businesses.php?action=edit&id=<?= $id ?>" method="POST" enctype="multipart/form-data" class="border rounded-3 p-3 bg-light">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="admin_upload_gallery" value="<?= $id ?>">
                    <div class="row align-items-center g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold mb-1"><i class="fa-solid fa-cloud-arrow-up me-1 text-primary"></i> Yeni Galeri Görseli Ekle</label>
                            <input type="file" name="gallery_file" class="form-control" accept="image/png, image/jpeg, image/webp" required>
                            <div class="form-text small mb-0">Maksimum 6 görsel. (JPG, PNG, WEBP — Doğrudan onaylanır ve yayına alınır)</div>
                        </div>
                        <div class="col-md-4 text-md-end mt-md-4">
                            <button type="submit" class="btn btn-primary fw-bold px-4">
                                <i class="fa-solid fa-upload me-1"></i> Fotoğrafı Yükle
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> Maksimum galeri limiti (6 görsel) dolmuştur. Yeni görsel eklemek için mevcut görsellerden birini silin.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- JS helper to auto-generate slugs -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nameInput = document.getElementById('biz_name');
            const slugInput = document.getElementById('biz_slug');

            // Only auto-generate on new business form
            <?php if ($action === 'new'): ?>
                nameInput.addEventListener('input', function () {
                    let text = nameInput.value;
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
        });

        // City & District Cascading logic
        const citiesData = <?= $citiesJson ?>;
        const defaultCity = <?= json_encode($defaultCity ?? '') ?>;
        const citySelect = document.getElementById('citySelect');
        const districtSelect = document.getElementById('districtSelect');
        const preselectedDistrict = document.getElementById('preselectedDistrict')?.value;

        function populateDistricts(city, selectedDist = '') {
            if (!districtSelect) return;
            districtSelect.innerHTML = '<option value="">-- İlçe Seçin --</option>';
            if (city && citiesData[city]) {
                citiesData[city].forEach(dist => {
                    const opt = document.createElement('option');
                    opt.value = dist;
                    opt.textContent = dist;
                    if (dist === selectedDist) opt.selected = true;
                    districtSelect.appendChild(opt);
                });
            }
        }

        if (citySelect) {
            citySelect.addEventListener('change', function() {
                populateDistricts(this.value);
            });
            
            // Prepopulate on load for edit mode or new mode
            if (citySelect.value) {
                populateDistricts(citySelect.value, preselectedDistrict);
            } else if (defaultCity && citiesData[defaultCity]) {
                citySelect.value = defaultCity;
                populateDistricts(defaultCity, preselectedDistrict);
            }
        }
    </script>
<?php endif; ?>

<!-- JS function to download external QR Code image by converting it into a Blob first -->
<script>
async function downloadQRCode(qrApiUrl, businessName) {
    try {
        const response = await fetch(qrApiUrl);
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);

        // Create temp link to download
        const tempLink = document.createElement('a');
        tempLink.href = blobUrl;

        // Format file name
        const sanitizedName = businessName.toLowerCase()
            .replace(/ğ/g, 'g')
            .replace(/ü/g, 'u')
            .replace(/ş/g, 's')
            .replace(/ı/g, 'i')
            .replace(/ö/g, 'o')
            .replace(/ç/g, 'c')
            .replace(/[^a-z0-9]/g, '-')
            .replace(/-+/g, '-');
        tempLink.download = sanitizedName + '-qr-kod.png';

        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);

        // Clean up object URL
        setTimeout(() => URL.revokeObjectURL(blobUrl), 100);
    } catch (error) {
        console.error("QR İndirme Hatası:", error);
        Swal.fire({
            title: 'Hata!',
            text: 'QR Kod indirilirken bir sorun oluştu. Lütfen sağ tıklayarak resmi kaydedin.',
            icon: 'error',
            confirmButtonColor: '#E0533C',
            confirmButtonText: 'Tamam',
            customClass: {
                confirmButton: 'btn btn-primary px-4 py-2'
            },
            buttonsStyling: false
        });
    }
}
</script>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>