<?php
require_once 'config/db.php';
require_once 'includes/seo-meta.php';
$pageTitle = 'Sayfa Bulunamadı - 404';
$metaDescription = 'Aradığınız sayfa bulunamadı veya taşınmış olabilir.';
$metaKeywords = '404, sayfa bulunamadı, ' . strtolower(seoGetSiteTitle());
require_once 'includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row align-items-center justify-content-center" style="min-height: 50vh;">
        <div class="col-lg-6 text-center">
            <!-- Illustration / Icon -->
            <div class="mx-auto mb-4 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 100px; height: 100px; animation: pulse 2s infinite;">
                <i class="fa-solid fa-compass-drafting" style="font-size: 44px;"></i>
            </div>
            
            <!-- Animated 404 Text -->
            <h1 class="fw-bold text-navy mb-2" style="font-size: 80px; font-family: var(--font-display); letter-spacing: -3px; line-height: 1;">404</h1>
            <h2 class="fw-bold mb-3 text-dark fs-3" style="font-family: var(--font-display); letter-spacing: -0.5px;">Aradığınız Sayfa Bulunamadı</h2>
            <p class="text-muted mb-5 mx-auto" style="max-width: 500px; line-height: 1.7; font-size: 14.5px;">
                Ulaşmaya çalıştığınız sayfa silinmiş, adı değiştirilmiş veya geçici olarak servis dışı bırakılmış olabilir. Lütfen adres satırını kontrol edip tekrar deneyin veya aşağıdaki hızlı bağlantılarla keşfetmeye devam edin.
            </p>
            
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="/" class="btn btn-primary px-4 py-3 fw-bold text-white d-flex align-items-center justify-content-center gap-2" style="border-radius: var(--radius-sm); font-size: 14px;">
                    <i class="fa-solid fa-house"></i> Ana Sayfaya Dön
                </a>
                <a href="<?= seoGetBaseUrl() ?>/esnaflar" class="btn btn-outline-primary px-4 py-3 fw-bold d-flex align-items-center justify-content-center gap-2" style="border-radius: var(--radius-sm); font-size: 14px;">
                    <i class="fa-solid fa-magnifying-glass"></i> İşletmeleri Keşfet
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
