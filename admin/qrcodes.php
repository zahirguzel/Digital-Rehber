<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/influencer-helpers.php';

$pageTitle = 'QR Kod Sistemi';

// Dynamically resolve base URL for QR codes
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain . str_replace('admin/qrcodes.php', '', $_SERVER['PHP_SELF']);

$successMsg = '';
$errorMsg = '';

// Fetch categories for filter
$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $errorMsg = "Kategoriler yüklenemedi: " . $e->getMessage();
}

// Stats
$totalQR = 0;
$premiumQR = 0;
$totalInfQR = 0;
$premiumInfQR = 0;
try {
    $totalQR = $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
    $premiumQR = $db->query("SELECT COUNT(*) FROM businesses WHERE is_premium = 1")->fetchColumn();
    $totalInfQR = $db->query("SELECT COUNT(*) FROM influencers WHERE is_published = 1 AND consent_given = 1")->fetchColumn();
    $premiumInfQR = $db->query("SELECT COUNT(*) FROM influencers WHERE is_published = 1 AND consent_given = 1 AND is_premium = 1")->fetchColumn();
} catch (Exception $e) {
    // Fallback
}

// Fetch businesses for QR listing
$businesses = [];
try {
    $search = $_GET['search'] ?? '';
    $catFilter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    
    $sql = "SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE 1=1";
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

// Fetch influencers for QR listing
$influencers = [];
$infSearch = $_GET['inf_search'] ?? '';
try {
    $infSql = "SELECT * FROM influencers WHERE is_published = 1 AND consent_given = 1";
    $infParams = [];
    if (!empty($infSearch)) {
        $infSql .= " AND (name LIKE ? OR district LIKE ? OR slug LIKE ?)";
        $infParams[] = "%$infSearch%";
        $infParams[] = "%$infSearch%";
        $infParams[] = "%$infSearch%";
    }
    $infSql .= " ORDER BY name ASC";
    $stmtInf = $db->query($infSql, $infParams);
    $influencers = $stmtInf->fetchAll();
} catch (Exception $e) {
    if (empty($errorMsg)) {
        $errorMsg = 'Influencer listesi yüklenirken hata oluştu.';
    }
}

include 'includes/header.php';
?>

<!-- QR Instructions Card -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--primary) !important;">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="text-primary bg-primary bg-opacity-10 p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fa-solid fa-circle-info fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-navy mb-2">QR Menü ve Dijital Kartvizit Sistemi Nasıl Çalışır?</h5>
                        <p class="text-muted mb-0 small" style="line-height: 1.6;">
                            Dijital Rehber portalındaki her esnafın ve yayında olan her influencer profilinin kendine özel bir mobil dijital kartvizit sayfası vardır. Sosyal medya, menü ve iletişim bilgilerini tanımladığınızda sistem otomatik olarak
                            <strong>esnaf için /slug</strong>, <strong>influencer için /i/slug</strong> formatında QR kod üretir. Kodu indirip teslim edebilirsiniz.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats row -->
<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--navy) !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Esnaf QR Profili</span>
                    <h3 class="fw-bold mb-0 text-navy"><?= $totalQR ?></h3>
                </div>
                <div class="bg-navy bg-opacity-10 text-navy p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-qrcode fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--primary) !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Influencer QR Profili</span>
                    <h3 class="fw-bold mb-0 text-primary"><?= $totalInfQR ?></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-star fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Premium Influencer</span>
                    <h3 class="fw-bold mb-0 text-success"><?= $premiumInfQR ?></h3>
                </div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-circle-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #3b82f6 !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">QR Çözünürlüğü</span>
                    <h3 class="fw-bold mb-0 text-primary-emphasis">300x300 px</h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-expand fs-4 text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- List & Manage Section -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-shop me-2 text-primary"></i> Esnaf QR Kod Yönetimi</h5>
            </div>
            
            <!-- Filters -->
            <div class="card-body bg-light border-bottom p-3">
                <form action="" method="GET" class="row g-2">
                    <input type="hidden" name="page" value="qrcodes">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="İşletme adı veya ilçe ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">-- Tüm Kategoriler --</option>
                            <?php
foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category_id']) && intval($_GET['category_id']) == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-navy btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Filtrele</button>
                    </div>
                </form>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Esnaf Bilgisi</th>
                                <th>Profil URL</th>
                                <th class="text-center">Önizleme</th>
                                <th class="text-end pe-4">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
if (empty($businesses)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">Aranan kriterlere uygun esnaf kaydı bulunamadı.</td>
                                </tr>
                            <?php
else: ?>
                                <?php
foreach ($businesses as $biz): 
                                    $qrUrl = $baseUrl . $biz['slug'];
                                    $qrImageSrc = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrUrl);
                                ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="fw-bold text-navy"><?= htmlspecialchars($biz['name']) ?></div>
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 10px;"><?= htmlspecialchars($biz['category_name']) ?></span>
                                            <span class="text-muted small ms-2"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($biz['district']) ?></span>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($qrUrl) ?>" target="_blank" class="text-decoration-none text-navy font-monospace small">
                                                /<?= htmlspecialchars($biz['slug']) ?> <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 9px;"></i>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <img src="<?= $qrImageSrc ?>" alt="QR" class="border p-1 bg-white shadow-sm" style="width: 42px; height: 42px; border-radius: var(--radius);">
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group gap-2">
                                                <button type="button" class="btn btn-navy btn-sm px-3" data-bs-toggle="modal" data-bs-target="#qrModalView<?= $biz['id'] ?>"><i class="fa-solid fa-eye me-1"></i> Önizle</button>
                                                <button class="btn btn-success btn-sm px-3" onclick="downloadQRCode('<?= $qrImageSrc ?>', '<?= htmlspecialchars(addslashes($biz['name'])) ?>')">
                                                    <i class="fa-solid fa-download"></i> İndir
                                                </button>
                                            </div>
                                            
                                            <!-- MODAL FOR BIG PREVIEW -->
                                            <div class="modal fade" id="qrModalView<?= $biz['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered text-start" style="max-width: 400px;">
                                                    <div class="modal-content" style="border-radius: var(--radius);">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-qrcode me-2 text-primary"></i> QR Önizleme</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                                        </div>
                                                        <div class="modal-body text-center p-4">
                                                            <h6 class="fw-bold mb-3 text-navy"><?= htmlspecialchars($biz['name']) ?></h6>
                                                            
                                                            <div class="p-3 border bg-white rounded-3 d-inline-block shadow-sm mb-3">
                                                                <img src="<?= $qrImageSrc ?>" alt="Big QR" class="img-fluid" style="width: 220px; height: 220px;">
                                                            </div>
                                                            
                                                            <div class="mb-3 text-muted small px-2">
                                                                Bağlantı Hedefi:<br>
                                                                <a href="<?= htmlspecialchars($qrUrl) ?>" target="_blank" class="fw-semibold text-break"><?= htmlspecialchars($qrUrl) ?></a>
                                                            </div>
                                                            
                                                            <button class="btn btn-success w-100 py-2 fw-bold" onclick="downloadQRCode('<?= $qrImageSrc ?>', '<?= htmlspecialchars(addslashes($biz['name'])) ?>')">
                                                                <i class="fa-solid fa-download me-2"></i> QR Kodu İndir (PNG)
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
endforeach; ?>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Free Custom URL QR Generator Section -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-qrcode me-2 text-primary"></i> Serbest QR Kod Oluşturucu</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    Sistem dışındaki herhangi bir link (Google Haritalar, Instagram, kişisel web sitesi vb.) veya özel kampanya sayfası için anında QR kod tasarlayıp indirebilirsiniz.
                </p>
                
                <form id="customQrForm" onsubmit="generateCustomQR(event)">
                    <!-- Target Link -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hedef URL / Bağlantı <span class="text-danger">*</span></label>
                        <input type="url" id="customUrl" class="form-control" placeholder="https://example.com" required>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <!-- Custom foreground color -->
                        <div class="col-6">
                            <label class="form-label fw-semibold">QR Rengi (Ön Plan)</label>
                            <input type="color" id="customColor" class="form-control form-control-color w-100" style="height: 42px; padding: 2px;" value="#0f172a">
                        </div>
                        
                        <!-- Custom background color -->
                        <div class="col-6">
                            <label class="form-label fw-semibold">Arka Plan</label>
                            <input type="color" id="customBgColor" class="form-control form-control-color w-100" style="height: 42px; padding: 2px;" value="#ffffff">
                        </div>
                    </div>
                    
                    <!-- QR Size -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Çözünürlük / Boyut</label>
                        <select id="customSize" class="form-select">
                            <option value="150x150">150x150 px (Küçük)</option>
                            <option value="300x300" selected>300x300 px (Orta - Önerilen)</option>
                            <option value="500x500">500x500 px (Büyük - Baskı İçin)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2"></i> QR Kod Oluştur</button>
                </form>
                
                <!-- Custom QR Preview and Download area -->
                <div id="customPreviewContainer" class="mt-4 pt-4 border-top text-center d-none">
                    <h6 class="fw-bold text-navy mb-3">Oluşturulan Özel QR Kod</h6>
                    <div class="p-3 border bg-white rounded-3 d-inline-block shadow-sm mb-3">
                        <img id="customQrImg" src="" alt="Custom QR" class="img-fluid" style="width: 180px; height: 180px;">
                    </div>
                    
                    <button class="btn btn-success w-100 py-2 fw-bold" onclick="downloadCustomQR()">
                        <i class="fa-solid fa-download me-2"></i> QR Kodu İndir (PNG)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-star me-2 text-primary"></i> Influencer QR Kod Yönetimi</h5>
                <span class="badge bg-light text-muted border"><?= count($influencers) ?> yayında profil</span>
            </div>

            <div class="card-body bg-light border-bottom p-3">
                <form action="" method="GET" class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="inf_search" class="form-control form-control-sm" placeholder="Influencer adı, slug veya ilçe ara..." value="<?= htmlspecialchars($infSearch) ?>">
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-navy btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Filtrele</button>
                    </div>
                    <?php
if (!empty($_GET['search']) || !empty($_GET['category_id'])): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($_GET['category_id'] ?? '') ?>">
                    <?php
endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Influencer</th>
                                <th>QR Profil URL</th>
                                <th class="text-center">Önizleme</th>
                                <th class="text-end pe-4">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
if (empty($influencers)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">Yayında influencer profili bulunamadı. Profil yayına alınmalı ve onay verilmiş olmalıdır.</td>
                            </tr>
                            <?php
else: foreach ($influencers as $inf):
                                $infQrUrl = rtrim($baseUrl, '/') . influencerQrUrl($inf['slug']);
                                $infQrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($infQrUrl);
                            ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-navy"><?= htmlspecialchars($inf['name']) ?></div>
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 10px;"><?= htmlspecialchars(getInfluencerNicheLabel($inf['niche'])) ?></span>
                                    <span class="text-muted small ms-2"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($inf['district']) ?></span>
                                    <?php
if ($inf['is_premium']): ?><span class="badge bg-warning text-dark ms-1">Premium</span><?php
endif; ?>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($infQrUrl) ?>" target="_blank" class="text-decoration-none text-navy font-monospace small">
                                        <?= htmlspecialchars(influencerQrUrl($inf['slug'])) ?> <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 9px;"></i>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <img src="<?= $infQrImageSrc ?>" alt="QR" class="border p-1 bg-white shadow-sm" style="width: 42px; height: 42px; border-radius: var(--radius);">
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group gap-2">
                                        <button type="button" class="btn btn-navy btn-sm px-3" data-bs-toggle="modal" data-bs-target="#infQrModalView<?= $inf['id'] ?>"><i class="fa-solid fa-eye me-1"></i> Önizle</button>
                                        <button class="btn btn-success btn-sm px-3" onclick="downloadQRCode('<?= $infQrImageSrc ?>', '<?= htmlspecialchars(addslashes($inf['name'])) ?>')">
                                            <i class="fa-solid fa-download"></i> İndir
                                        </button>
                                    </div>

                                    <div class="modal fade" id="infQrModalView<?= $inf['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start" style="max-width: 400px;">
                                            <div class="modal-content" style="border-radius: var(--radius);">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-qrcode me-2 text-primary"></i> Influencer QR Önizleme</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                                                </div>
                                                <div class="modal-body text-center p-4">
                                                    <h6 class="fw-bold mb-3 text-navy"><?= htmlspecialchars($inf['name']) ?></h6>
                                                    <div class="p-3 border bg-white rounded-3 d-inline-block shadow-sm mb-3">
                                                        <img src="<?= $infQrImageSrc ?>" alt="Big QR" class="img-fluid" style="width: 220px; height: 220px;">
                                                    </div>
                                                    <div class="mb-3 text-muted small px-2">
                                                        Bağlantı Hedefi:<br>
                                                        <a href="<?= htmlspecialchars($infQrUrl) ?>" target="_blank" class="fw-semibold text-break"><?= htmlspecialchars($infQrUrl) ?></a>
                                                    </div>
                                                    <button class="btn btn-success w-100 py-2 fw-bold" onclick="downloadQRCode('<?= $infQrImageSrc ?>', '<?= htmlspecialchars(addslashes($inf['name'])) ?>')">
                                                        <i class="fa-solid fa-download me-2"></i> QR Kodu İndir (PNG)
                                                    </button>
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
        </div>
    </div>
</div>

<script>
    // JS function to download external QR Code image by converting it into a Blob first (CORS compliant download)
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
                text: 'QR Kod indirilirken bir sorun oluştu. Lütfen önizleme penceresinde QR koduna sağ tıklayıp farklı kaydedin.',
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

    // Custom QR Generator details
    let generatedCustomSrc = "";

    function generateCustomQR(event) {
        event.preventDefault();
        
        const url = document.getElementById('customUrl').value;
        const color = document.getElementById('customColor').value.replace('#', '');
        const bgColor = document.getElementById('customBgColor').value.replace('#', '');
        const size = document.getElementById('customSize').value;
        
        // Generate QR code link via secure API
        generatedCustomSrc = `https://api.qrserver.com/v1/create-qr-code/?size=${size}&color=${color}&bgcolor=${bgColor}&data=${encodeURIComponent(url)}`;
        
        const qrImg = document.getElementById('customQrImg');
        const container = document.getElementById('customPreviewContainer');
        
        // Apply to preview
        qrImg.src = generatedCustomSrc;
        container.classList.remove('d-none');
        
        // Adjust displayed image container size matching the selections
        if (size === "150x150") {
            qrImg.style.width = "120px";
            qrImg.style.height = "120px";
        } else if (size === "500x500") {
            qrImg.style.width = "220px";
            qrImg.style.height = "220px";
        } else {
            qrImg.style.width = "180px";
            qrImg.style.height = "180px";
        }
    }

    async function downloadCustomQR() {
        if (!generatedCustomSrc) return;
        await downloadQRCode(generatedCustomSrc, 'rehber-ozel');
    }
</script>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
