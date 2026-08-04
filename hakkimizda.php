<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';

// Fetch Hakkımızda content from pages table
$page = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'hakkimizda' AND is_published = 1");
    $stmt->execute();
    $page = $stmt->fetch();
} catch (Exception $e) {}

if (!$page) {
    // Fallback if deleted from db
    $page = [
        'title' => 'Hakkımızda',
        'meta_description' => 'Platformumuz hakkında bilgiler.',
        'content' => '<p>Platformumuza hoş geldiniz.</p>'
    ];
}

$pageTitle = $page['title'];
$metaDescription = $page['meta_description'];

require_once 'includes/header.php';
?>

<!-- Hakkımızda Hero Alanı -->
<header class="py-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, var(--primary) 0%, #1a1a2e 100%);">
    <!-- Dekoratif Arka Plan Şekilleri -->
    <div class="position-absolute rounded-circle" style="width: 300px; height: 300px; background: rgba(255,255,255,0.05); top: -100px; left: -100px;"></div>
    <div class="position-absolute rounded-circle" style="width: 400px; height: 400px; background: rgba(255,255,255,0.05); bottom: -200px; right: -100px;"></div>
    
    <div class="container text-center py-4 reveal-on-scroll position-relative" style="z-index: 2;">
        <h1 class="display-4 fw-bold text-white mb-3" style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?= SecurityHelper::escape($page['title']) ?></h1>
        <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
            <span style="width: 40px; height: 3px; background: rgba(255,255,255,0.4); border-radius: 2px;"></span>
            <i class="fa-solid fa-users text-white opacity-75"></i>
            <span style="width: 40px; height: 3px; background: rgba(255,255,255,0.4); border-radius: 2px;"></span>
        </div>
        <p class="lead text-white opacity-75 mx-auto" style="max-width: 600px;"><?= SecurityHelper::escape($page['meta_description']) ?></p>
    </div>
</header>

<!-- İçerik Alanı -->
<main class="py-5" style="min-height: 50vh;">
    <div class="container">
        <div class="row align-items-center mb-5 reveal-on-scroll">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="cms-content-wrapper pe-lg-4">
                    <!-- Dinamik İçerik (Veritabanından) -->
                    <?= $page['content'] ?>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <?php
                $hakkimizdaGorsel = $siteUrl('public/images/mockup.png');
                if (!empty($siteSettings['site_logo'])) {
                    $hakkimizdaGorsel = (strpos($siteSettings['site_logo'], 'http') === 0) ? $siteSettings['site_logo'] : $siteUrl('public/images/' . $siteSettings['site_logo']);
                }
                ?>
                <img src="<?= SecurityHelper::escape($hakkimizdaGorsel) ?>" alt="<?= SecurityHelper::escape($page['title']) ?>" class="img-fluid rounded-4 shadow-sm" style="max-height: 400px; object-fit: contain;">
            </div>
        </div>

        <!-- İstatistik veya Ekstra Grid (Opsiyonel Sabit Yapı) -->
        <div class="row g-4 mt-4 reveal-on-scroll">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-4" style="background: rgba(var(--primary-rgb), 0.03);">
                    <i class="fa-solid fa-shop fa-3x mb-3" style="color: var(--primary);"></i>
                    <h4 class="fw-bold text-navy">Yerel Güç</h4>
                    <p class="text-muted mb-0">Esnafımızın dijital dünyada hak ettiği yeri alması için çalışıyoruz.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-4" style="background: rgba(var(--primary-rgb), 0.03);">
                    <i class="fa-solid fa-globe fa-3x mb-3" style="color: var(--primary);"></i>
                    <h4 class="fw-bold text-navy">Geniş Erişim</h4>
                    <p class="text-muted mb-0">Tek bir platform üzerinden tüm işletmelere saniyeler içinde ulaşım imkanı.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-4" style="background: rgba(var(--primary-rgb), 0.03);">
                    <i class="fa-solid fa-handshake fa-3x mb-3" style="color: var(--primary);"></i>
                    <h4 class="fw-bold text-navy">Güvenilir Ağ</h4>
                    <p class="text-muted mb-0">Onaylı ve güvenilir yerel işletmelerle aradığınız hizmet hep yanınızda.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.cms-content-wrapper {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #4b5563;
}
.cms-content-wrapper h1, 
.cms-content-wrapper h2, 
.cms-content-wrapper h3 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 1rem;
}
.cms-content-wrapper p {
    margin-bottom: 1.2rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
