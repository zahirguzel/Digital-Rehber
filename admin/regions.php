<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);

$db = Database::getInstance();
$pdo = $db->getPDO();

$successMsg = '';
$errorMsg = '';

// Handle City Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_city') {
    validateCSRF();
    $cityName = trim($_POST['city_name'] ?? '');
    if (empty($cityName)) {
        $errorMsg = 'Bölge (İl) adı boş olamaz.';
    } else {
        try {
            $cityName = mb_convert_case($cityName, MB_CASE_TITLE, "UTF-8");
            
            $stmt = $pdo->prepare("SELECT id FROM cities WHERE name = ?");
            $stmt->execute([$cityName]);
            if (!$stmt->fetchColumn()) {
                $stmt = $pdo->prepare("INSERT INTO cities (name) VALUES (?)");
                $stmt->execute([$cityName]);
                $successMsg = 'Yeni bölge eklendi.';
                $_GET['city_id'] = $pdo->lastInsertId();
            } else {
                $errorMsg = 'Bu bölge zaten mevcut.';
            }
        } catch (Exception $e) {
            $errorMsg = 'Ekleme başarısız: ' . $e->getMessage();
        }
    }
}

// Handle District Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_district') {
    validateCSRF();
    $cityId = (int)$_POST['city_id'];
    $districtName = trim($_POST['district_name'] ?? '');
    
    if (empty($districtName) || $cityId <= 0) {
        $errorMsg = 'Lütfen geçerli bir bölge seçin ve ilçe adını yazın.';
    } else {
        try {
            $districtName = mb_convert_case($districtName, MB_CASE_TITLE, "UTF-8");
            
            $stmt = $pdo->prepare("SELECT id FROM districts WHERE city_id = ? AND name = ?");
            $stmt->execute([$cityId, $districtName]);
            if (!$stmt->fetchColumn()) {
                $stmt = $pdo->prepare("INSERT INTO districts (city_id, name) VALUES (?, ?)");
                $stmt->execute([$cityId, $districtName]);
                $successMsg = 'Yeni ilçe başarıyla eklendi.';
            } else {
                $errorMsg = 'Bu ilçe bu bölgede zaten mevcut.';
            }
        } catch (Exception $e) {
            $errorMsg = 'Ekleme başarısız: ' . $e->getMessage();
        }
    }
    $_GET['city_id'] = $cityId;
}

// Handle City Deletion
if (isset($_GET['delete_city'])) {
    $cityId = (int)$_GET['delete_city'];
    try {
        $pdo->prepare("DELETE FROM cities WHERE id = ?")->execute([$cityId]);
        $successMsg = 'Bölge ve bağlı tüm ilçeler silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silme işlemi başarısız: ' . $e->getMessage();
    }
}

// Handle District Deletion
if (isset($_GET['delete_district'])) {
    $districtId = (int)$_GET['delete_district'];
    $cityId = (int)$_GET['city_id'];
    try {
        $pdo->prepare("DELETE FROM districts WHERE id = ?")->execute([$districtId]);
        $successMsg = 'İlçe başarıyla silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silme işlemi başarısız: ' . $e->getMessage();
    }
    $_GET['city_id'] = $cityId;
}

// Fetch All Cities for Dropdown
$cities = [];
try {
    $cities = $pdo->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Selected City logic
$settingsQuery = $pdo->query("SELECT default_city FROM settings WHERE id = 1")->fetch();
$defaultCityName = $settingsQuery['default_city'] ?? '';

$selectedCityId = 0;
$selectedCityName = '';
$districts = [];

if ($defaultCityName) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM cities WHERE name = ? LIMIT 1");
        $stmt->execute([$defaultCityName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $selectedCityId = (int)$row['id'];
            $selectedCityName = $row['name'];
            
            $stmtDist = $pdo->prepare("SELECT * FROM districts WHERE city_id = ? ORDER BY name ASC");
            $stmtDist->execute([$selectedCityId]);
            $districts = $stmtDist->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

$pageTitle = 'Bölgeler ve İlçeler';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i> Bölgeler ve İlçeler</h2>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Sol Taraf: Bölge (Şehir) Bilgisi -->
    <div class="col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fa-solid fa-city me-2"></i> Aktif Bölge (Şehir)</h6>
            </div>
            <div class="card-body">
                
                <div class="alert alert-info border-0 shadow-sm">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    Şu anda Site Ayarları üzerinden <strong><?= htmlspecialchars($defaultCityName ? $defaultCityName : 'Seçilmedi') ?></strong> bölgesi aktif olarak ayarlanmıştır. Ana bölgeyi değiştirmek için lütfen <strong>Site & SEO Ayarları</strong> sayfasına gidiniz.
                </div>
                
                <?php if ($selectedCityId == 0): ?>
                    <div class="alert alert-warning border-0 shadow-sm mt-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Lütfen ilçe yönetimi yapabilmek için Site Ayarlarından bir hedef il seçip kaydedin.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Sağ Taraf: İlçeler Listesi -->
    <div class="col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-success"><i class="fa-solid fa-map-pin me-2"></i> Adım 2: İlçeler 
                    <?php if ($selectedCityName): ?>
                        <span class="text-muted small fw-normal ms-2">(<?= htmlspecialchars($selectedCityName) ?> için listeleniyor)</span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                
                <?php if ($selectedCityId == 0): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-triangle-exclamation fs-1 mb-3 text-warning"></i>
                        <br>
                        İlçeleri görebilmek ve yönetebilmek için <strong>Site & SEO Ayarları</strong> sayfasından bir Ana Bölge seçmelisiniz.
                    </div>
                <?php else: ?>
                    
                    <form action="regions.php" method="POST" class="mb-4 bg-light p-3 rounded border">
                        <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="action" value="add_district">
                        <input type="hidden" name="city_id" value="<?= $selectedCityId ?>">
                        
                        <label class="form-label fw-bold small text-muted mb-2">Bu Bölgeye Yeni İlçe Ekle:</label>
                        <div class="input-group">
                            <input type="text" name="district_name" class="form-control" placeholder="Örn: Yeni İlçe..." required>
                            <button class="btn btn-success px-4" type="submit"><i class="fa-solid fa-plus me-1"></i> Ekle</button>
                        </div>
                    </form>

                    <h6 class="fw-bold text-navy mb-3">Kayıtlı İlçeler <span class="badge bg-secondary rounded-pill ms-1"><?= count($districts) ?></span></h6>
                    
                    <?php if (empty($districts)): ?>
                        <div class="alert alert-warning border-0">Bu bölgeye ait hiç ilçe bulunmuyor. Yukarıdan ekleyebilirsiniz.</div>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($districts as $dist): ?>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white d-flex justify-content-between align-items-center shadow-sm">
                                        <span class="fw-semibold text-dark"><i class="fa-solid fa-caret-right text-muted me-2"></i><?= htmlspecialchars($dist['name']) ?></span>
                                        <a href="regions.php?delete_district=<?= $dist['id'] ?>&city_id=<?= $selectedCityId ?>" class="btn btn-sm text-danger" onclick="return confirm('Bu ilçeyi silmek istediğinize emin misiniz?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>