<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';

// Fetch content from pages table
$page = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'vizyon-misyon' AND is_published = 1");
    $stmt->execute();
    $page = $stmt->fetch();
} catch (Exception $e) {}

if (!$page) {
    // Fallback if deleted from db
    $page = [
        'title' => 'Vizyon & Misyon',
        'meta_description' => 'Platformumuzun hedefleri ve misyonu.',
        'content' => '<p>Vizyonumuz ve Misyonumuz</p>'
    ];
}

$pageTitle = $page['title'];
$metaDescription = $page['meta_description'];

require_once 'includes/header.php';
?>

<!-- Vizyon Misyon Hero Alanı -->
<header class="py-5" style="background: linear-gradient(135deg, var(--primary) 0%, #1e293b 100%); color: white; border-bottom: 5px solid rgba(255,255,255,0.1);">
    <div class="container text-center py-4 reveal-on-scroll">
        <h1 class="display-4 fw-bold mb-3"><?= SecurityHelper::escape($page['title']) ?></h1>
        <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
            <span style="width: 40px; height: 2px; background: rgba(255,255,255,0.3);"></span>
            <i class="fa-solid fa-bullseye opacity-75"></i>
            <span style="width: 40px; height: 2px; background: rgba(255,255,255,0.3);"></span>
        </div>
        <p class="lead mx-auto opacity-75" style="max-width: 600px; font-weight: 300;"><?= SecurityHelper::escape($page['meta_description']) ?></p>
    </div>
</header>

<!-- İçerik Alanı -->
<main class="py-5 bg-white" style="min-height: 50vh;">
    <div class="container">
        
        <div class="row justify-content-center mb-5 reveal-on-scroll">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="margin-top: -60px;">
                    <div class="card-body p-4 p-md-5">
                        <div class="cms-content-wrapper text-center">
                            <!-- Dinamik İçerik (Veritabanından) -->
                            <?= $page['content'] ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vizyon vs Misyon İki Kolon Yapısı (Statik Opsiyonel Alt Kısım) -->
        <div class="row g-4 mt-2 reveal-on-scroll">
            <div class="col-md-6">
                <div class="p-5 h-100 rounded-4" style="background: #f8fafc; border-left: 4px solid var(--primary);">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fa-solid fa-eye fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-navy">Vizyonumuz</h3>
                    </div>
                    <p class="text-muted" style="line-height: 1.8;">Tüm yerel esnafın ve küçük işletmelerin büyük e-ticaret ağlarındaki görünürlüğüne eşit bir şekilde ulaşmasını sağlamak, şehrin bir numaralı dijital haritası olmak.</p>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="p-5 h-100 rounded-4" style="background: #f8fafc; border-left: 4px solid #10b981;">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 50px; height: 50px; background: #10b981;">
                            <i class="fa-solid fa-rocket fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-navy">Misyonumuz</h3>
                    </div>
                    <p class="text-muted" style="line-height: 1.8;">İşletmeleri dijital çağın gereksinimleriyle (QR menü, dijital kartvizit, online vitrin) donatmak ve kullanıcıların aradıkları her hizmete tek bir tıkla, güvenle ulaşmasını sağlamak.</p>
                </div>
            </div>
        </div>
        
    </div>
</main>

<style>
.cms-content-wrapper {
    font-size: 1.15rem;
    line-height: 1.8;
    color: #4b5563;
}
.cms-content-wrapper h1, 
.cms-content-wrapper h2, 
.cms-content-wrapper h3 {
    color: #1e293b;
    font-weight: 700;
    margin-bottom: 1.5rem;
}
.cms-content-wrapper p {
    margin-bottom: 1.5rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
