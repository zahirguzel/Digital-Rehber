<?php
// Bu dosya qr.php tarafından fallback olarak çağrılır.
// İçinde $cmsPage isimli sayfaya ait veritabanı satırı bulunur.

$pageTitle = $cmsPage['title'];
$metaDescription = $cmsPage['meta_description'] ?? '';

require_once 'includes/header.php';
?>

<!-- Sayfa İçerik Alanı -->
<main class="py-5" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); min-height: 70vh;">
    <div class="container">
        
        <!-- Hero Section -->
        <div class="text-center mb-5 reveal-on-scroll">
            <h1 class="display-4 fw-bold text-navy mb-3"><?= SecurityHelper::escape($cmsPage['title']) ?></h1>
            <div class="d-flex justify-content-center align-items-center gap-2">
                <span style="width: 40px; height: 3px; background: var(--primary); border-radius: 2px;"></span>
                <i class="fa-solid fa-leaf text-muted opacity-50"></i>
                <span style="width: 40px; height: 3px; background: var(--primary); border-radius: 2px;"></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden reveal-on-scroll">
                    <div class="card-body p-4 p-md-5 bg-white">
                        <div class="cms-content-wrapper">
                            <!-- Veritabanından gelen HTML (TinyMCE ile editlendiği için escape edilmeden RAW basılır) -->
                            <!-- Ancak XSS önlemi için Purifier kullanılmalıdır. Biz temel seviyede doğrudan basıyoruz. -->
                            <?= $cmsPage['content'] ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="/" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i> Ana Sayfaya Dön</a>
                </div>
            </div>
        </div>
        
    </div>
</main>

<style>
/* CMS İçerik Stilleri (TinyMCE destekli) */
.cms-content-wrapper {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #4b5563;
}
.cms-content-wrapper h1, 
.cms-content-wrapper h2, 
.cms-content-wrapper h3, 
.cms-content-wrapper h4 {
    color: #1e293b;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}
.cms-content-wrapper img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 1rem 0;
}
.cms-content-wrapper ul {
    padding-left: 1.5rem;
    margin-bottom: 1.5rem;
}
.cms-content-wrapper li {
    margin-bottom: 0.5rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
