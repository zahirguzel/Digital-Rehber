<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$pageTitle = 'SEO & Arama Motoru Yönetimi';
$successMsg = '';
$errorMsg = '';

// Handle SEO General settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seo'])) {
    $site_description = trim($_POST['site_description']);
    $site_keywords = trim($_POST['site_keywords']);
    $google_analytics = trim($_POST['google_analytics']);
    
    try {
        $stmt = $db->query("UPDATE settings SET site_description = ?, site_keywords = ?, google_analytics = ? WHERE id = 1", [$site_description, $site_keywords, $google_analytics]);
        $successMsg = "SEO ve Analitik ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $errorMsg = "Ayarlar kaydedilirken hata oluştu: " . $e->getMessage();
    }
}

// Handle Robots.txt update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_robots'])) {
    $robots_content = $_POST['robots_content'];
    $robotsFile = '../robots.txt';
    try {
        if (file_put_contents($robotsFile, $robots_content) !== false) {
            $successMsg = "Robots.txt dosyası başarıyla güncellendi.";
        } else {
            $errorMsg = "Robots.txt yazılırken bir hata oluştu. Lütfen dosya izinlerini kontrol edin.";
        }
    } catch (Exception $e) {
        $errorMsg = "Hata: " . $e->getMessage();
    }
}

// Fetch current settings
try {
    $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
} catch (Exception $e) {
    die("Ayarlar yüklenemedi: " . $e->getMessage());
}

// Fetch sitemap counts
$countCats = 0;
$countBiz = 0;
$countBlogs = 0;
$countInfluencers = 0;
try {
    $countCats = intval($db->query("SELECT COUNT(*) FROM categories")->fetchColumn());
    $countBiz = intval($db->query("SELECT COUNT(*) FROM businesses")->fetchColumn());
    $countBlogs = intval($db->query("SELECT COUNT(*) FROM blogs")->fetchColumn());
    $countInfluencers = intval($db->query("SELECT COUNT(*) FROM influencers WHERE is_published = 1 AND consent_given = 1")->fetchColumn());
} catch (Exception $e) {}

$totalSitemapLinks = 11 + $countCats + $countBiz + $countBlogs + $countInfluencers;

// Fetch robots.txt content
$robotsContent = "";
if (file_exists('../robots.txt')) {
    $robotsContent = file_get_contents('../robots.txt');
}

// Resolve dynamic public URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$publicBase = $protocol . $domain . str_replace('admin/seo.php', '', $_SERVER['PHP_SELF']);
$sitemapUrl = $publicBase . 'sitemap.xml';

include 'includes/header.php';
?>

<!-- Alerts -->
<?php
if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<?php
if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<div class="row g-4">
    <!-- Left Column: SEO Meta Settings -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i> Arama Motoru & SEO Ayarları</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_seo" value="1">
                    
                    <!-- Meta Description -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Portal Meta Açıklaması (Description)</label>
                        <textarea name="site_description" class="form-control" rows="3" placeholder="Arama motorlarında sitenizin altında görünecek açıklama..." required><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        <span class="text-muted small" style="font-size: 11px;">Maksimum 160 karakter olması önerilir. Sitenin genel özetidir.</span>
                    </div>
                    
                    <!-- Meta Keywords -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Portal Anahtar Kelimeleri (Keywords)</label>
                        <input type="text" name="site_keywords" class="form-control" placeholder="şehir esnaf, şehir rehberi, ilçe firmalar" value="<?= htmlspecialchars($settings['site_keywords'] ?? '') ?>" required>
                        <span class="text-muted small" style="font-size: 11px;">Kelimeleri aralarına virgül koyarak yazın.</span>
                    </div>
                    
                    <!-- Analytics scripts -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Google Analytics / Takip Kodları (HTML Script)</label>
                        <textarea name="google_analytics" class="form-control font-monospace text-primary-emphasis" rows="6" placeholder="<script async src='https://www.googletagmanager.com/gtag/js...'></script>"><?= htmlspecialchars($settings['google_analytics'] ?? '') ?></textarea>
                        <span class="text-muted small" style="font-size: 11px;">Google Search Console, Analytics veya diğer doğrulama kodlarını buraya yapıştırabilirsiniz. &lt;head&gt; bloğuna eklenir.</span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-floppy-disk me-2"></i> SEO Ayarlarını Kaydet</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Sitemap & Robots.txt -->
    <div class="col-lg-5 d-flex flex-column gap-4">
        <!-- Sitemap Info Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-sitemap me-2 text-primary"></i> Dinamik XML Site Haritası</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    Sitenize eklenen her yeni esnaf, kategori veya blog yazısı anında arama motoru haritasına eklenir. Dosya dinamik olarak üretilmektedir.
                </p>
                
                <!-- Stats grid -->
                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="bg-light p-3 border rounded text-center">
                            <span class="text-muted small d-block mb-1">Kategoriler</span>
                            <h5 class="fw-bold text-navy mb-0"><?= $countCats ?> Adet</h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-3 border rounded text-center">
                            <span class="text-muted small d-block mb-1">Esnaf Kayıtları</span>
                            <h5 class="fw-bold text-navy mb-0"><?= $countBiz ?> Adet</h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-3 border rounded text-center">
                            <span class="text-muted small d-block mb-1">Blog Yazıları</span>
                            <h5 class="fw-bold text-navy mb-0"><?= $countBlogs ?> Adet</h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-3 border rounded text-center" style="border-color: var(--primary) !important; background-color: rgba(224, 83, 60, 0.02) !important;">
                            <span class="text-primary small d-block mb-1 fw-bold">Toplam Link</span>
                            <h5 class="fw-bold text-primary mb-0"><?= $totalSitemapLinks ?> Adet</h5>
                        </div>
                    </div>
                </div>
                
                <!-- Sitemap URL -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Site Haritası URL Adresi</label>
                    <div class="input-group">
                        <input type="text" id="sitemapUrlInput" class="form-control form-control-sm font-monospace text-muted" value="<?= htmlspecialchars($sitemapUrl) ?>" readonly>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copySitemapLink()"><i class="fa-solid fa-copy"></i> Kopyala</button>
                    </div>
                </div>
                
                <a href="<?= htmlspecialchars($sitemapUrl) ?>" target="_blank" class="btn btn-navy w-100 py-2 fw-semibold"><i class="fa-solid fa-arrow-up-right-from-square me-2"></i> Sitemap.xml Dosyasını Aç</a>
            </div>
        </div>

        <!-- District landing pages -->
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i> İlçe Rehber Sayfaları</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Şehrinizdeki tüm ilçelerin <code>/ilce/{slug}</code> sayfalarının metinlerini, SSS bölümünü ve yayın durumunu buradan düzenleyebilirsiniz.
                </p>
                <a href="ilceler.php" class="btn btn-outline-primary w-100 py-2 fw-semibold"><i class="fa-solid fa-pen-to-square me-2"></i> İlçe Rehberlerini Yönet</a>
            </div>
        </div>
        
        <!-- Robots.txt Editor Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-robot me-2 text-primary"></i> Robots.txt Düzenleyici</h6>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_robots" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Robots.txt Dosya İçeriği</label>
                        <textarea name="robots_content" class="form-control font-monospace" rows="6" style="font-size: 13px;" required><?= htmlspecialchars($robotsContent) ?></textarea>
                        <span class="text-muted small" style="font-size: 11px;">Arama motoru botlarının erişim izinlerini buradan anlık düzenleyebilirsiniz.</span>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold"><i class="fa-solid fa-floppy-disk me-2"></i> Robots.txt Güncelle</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function copySitemapLink() {
        const input = document.getElementById('sitemapUrlInput');
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices
        navigator.clipboard.writeText(input.value);
        
        Swal.fire({
            title: 'Kopyalandı!',
            text: 'Site haritası linki panoya kopyalandı.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }
</script>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
