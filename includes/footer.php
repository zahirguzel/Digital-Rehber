<?php
if (!isset($siteSettings)) {
    $siteSettings = [
        'site_title' => ($siteSettings['site_title'] ?? 'Şehir Rehberi'),
        'site_description' => 'şehrin dijital esnaf, işletme, gezi ve etkinlik rehberi.',
        'site_logo' => '',
        'contact_email' => 'bilgi@site.com',
        'contact_phone' => '0326 222 22 22',
        'contact_whatsapp' => '905551112233',
        'contact_address' => 'Merkez, ' . (function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir'),
        'social_instagram' => '#',
        'social_facebook' => '#',
        'social_tiktok' => '#',
        'social_youtube' => '#',
    ];
    if (class_exists('Database')) {
        try {
            $dbConn = Database::getInstance()->getPDO();
            $dbSettings = $dbConn->query("SELECT * FROM settings WHERE id = 1")->fetch();
            if ($dbSettings) {
                $siteSettings = $dbSettings;
            }
        } catch (Exception $e) {
            // Fallback
        }
    }
}

require_once __DIR__ . '/seo-meta.php';
if (!isset($seoBaseUrl)) {
    $seoBaseUrl = seoGetBaseUrl();
}
if (!isset($siteUrl)) {
    $seoBaseUrlTrimmed = rtrim($seoBaseUrl, '/');
    $siteUrl = function ($path = '') use ($seoBaseUrlTrimmed) {
        if ($path === '' || $path === '/') {
            return $seoBaseUrlTrimmed . '/';
        }

        return $seoBaseUrlTrimmed . '/' . ltrim($path, '/');
    };
}
?>
<?php if (!isset($hideFooterCTA) || !$hideFooterCTA): ?>
<!-- Dual Portal Footer CTA -->
<section class="footer-portal-cta">
    <div class="container">
        <div class="footer-portal-cta__grid">
            <article class="footer-portal-cta__panel footer-portal-cta__panel--guide">
                <span class="footer-portal-cta__tag">Rehber</span>
                <h2>Şehirdeki işletmeleri keşfedin</h2>
                <p>İlçe, kategori ve anahtar kelimeyle aradığınız esnafa saniyeler içinde ulaşın.</p>
                <a href="<?= SecurityHelper::escape($siteUrl('esnaflar')) ?>" class="btn btn-light fw-bold">Esnafları Keşfet <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </article>
            <article class="footer-portal-cta__panel footer-portal-cta__panel--media">
                <span class="footer-portal-cta__tag">Medya</span>
                <h2>İşletmenizi dijitalde büyütün</h2>
                <p>Google Harita, sosyal medya, QR menü ve premium vitrin — uçtan uca dijital çözümler.</p>
                <a href="<?= SecurityHelper::escape($siteUrl('hizmetlerimiz')) ?>" class="btn btn-primary fw-bold">Hizmetlerimiz <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Main Corporate Footer -->
<footer class="corporate-footer<?= (isset($hideFooterCTA) && $hideFooterCTA) ? ' no-cta' : '' ?>">
    <div class="container">
        <div class="footer-main-row row">
            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6 mb-5 mb-lg-0">
                <div class="footer-brand mb-4">
                    <?php if (!empty($siteSettings['site_logo'])): ?>
                        <?php 
                        $logoUrl = (strpos($siteSettings['site_logo'], 'http') === 0) ? $siteSettings['site_logo'] : $siteUrl('public/images/' . $siteSettings['site_logo']); 
                        ?>
                        <img src="<?= SecurityHelper::escape($logoUrl) ?>" alt="<?= SecurityHelper::escape($siteSettings['site_title']) ?>" style="max-height: 120px; margin-top: -25px; margin-bottom: -15px; object-fit: contain; border-radius: var(--radius-sm); display: block;">
                    <?php else: ?>
                        <div class="brand-logo-wrapper d-inline-flex mb-3 align-items-center justify-content-center" style="width: 44px; height: 44px; background: var(--primary); border-radius: 12px;">
                            <i class="fa-solid fa-leaf text-white" style="font-size: 20px;"></i>
                        </div>
                        <div class="brand-text d-inline-flex flex-column ms-2 align-middle">
                            <span class="brand-title" style="font-family: var(--font-display); font-weight: 800; font-size: 22px; line-height: 1; color: var(--text-main);"><?= SecurityHelper::escape($siteSettings['site_title']) ?></span>
                            <span class="brand-subtitle" style="font-size: 11px; font-weight: 500; text-transform: uppercase; color: var(--text-muted);">Şehrin Dijital Rehberi</span>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="footer-desc pe-lg-4">
                    Şehrin yerel işletmelerini, lezzet duraklarını ve profesyonel hizmetlerini tek bir çatı altında toplayan, yenilikçi ve modern şehir rehberi platformu.
                </p>
                <div class="footer-social mt-4">
                    <?php if (!empty($siteSettings['social_youtube']) && $siteSettings['social_youtube'] !== '#'): ?>
                        <a href="<?= SecurityHelper::escape($siteSettings['social_youtube']) ?>" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="YouTube kanalımız (yeni sekmede açılır)"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_facebook']) && $siteSettings['social_facebook'] !== '#'): ?>
                        <a href="<?= SecurityHelper::escape($siteSettings['social_facebook']) ?>" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Facebook sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_instagram']) && $siteSettings['social_instagram'] !== '#'): ?>
                        <a href="<?= SecurityHelper::escape($siteSettings['social_instagram']) ?>" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Instagram sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['social_tiktok']) && $siteSettings['social_tiktok'] !== '#'): ?>
                        <a href="<?= SecurityHelper::escape($siteSettings['social_tiktok']) ?>" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="TikTok sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-tiktok" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 mb-5 mb-lg-0">
                <h5 class="footer-widget-title">Kurumsal</h5>
                <ul class="footer-nav">
                    <li><a href="<?= SecurityHelper::escape($siteUrl('hakkimizda')) ?>">Hakkımızda</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar')) ?>">İşletmeler</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('influencerlar')) ?>">Influencerlar</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('etkinlikler')) ?>">Etkinlikler</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('kampanyalar')) ?>">Kampanyalar</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('bolgeler')) ?>">İlçeler</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('vizyon-misyon')) ?>">Vizyon & Misyon</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('sikca-sorulan-sorular')) ?>">Sıkça Sorulan Sorular</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('iletisim')) ?>">İletişim</a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="col-lg-3 col-md-6 mb-5 mb-lg-0">
                <h5 class="footer-widget-title">Popüler Kategoriler</h5>
                <ul class="footer-nav">
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=yeme-icme')) ?>">Restoranlar & Kafeler</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=otomotiv-sanayi')) ?>">Otomotiv & Servis</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=giyim-alisveris')) ?>">Giyim & Alışveriş</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=insaat-ev-dekorasyonu')) ?>">İnşaat & Ev</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=saglik-medikal')) ?>">Sağlık & Eczane</a></li>
                    <li><a href="<?= SecurityHelper::escape($siteUrl('nobetci-eczane')) ?>">Nöbetçi Eczaneler</a></li>
                </ul>
            </div>

            <!-- Contact & Newsletter -->
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-widget-title">Bize Ulaşın</h5>
                <ul class="footer-contact-info mb-4">
                    <li>
                        <i class="fa-solid fa-location-dot mt-1"></i>
                        <span><?= SecurityHelper::escape($siteSettings['contact_address']) ?></span>
                    </li>
                    <?php if (!empty($siteSettings['contact_phone'])): ?>
                    <li>
                        <i class="fa-solid fa-phone mt-1"></i>
                        <a href="tel:<?= preg_replace('/[^0-9]/', '', $siteSettings['contact_phone']) ?>"><?= SecurityHelper::escape($siteSettings['contact_phone']) ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($siteSettings['contact_email'])): ?>
                    <li>
                        <i class="fa-solid fa-envelope mt-1"></i>
                        <a href="mailto:<?= SecurityHelper::escape(trim($siteSettings['contact_email'])) ?>"><?= SecurityHelper::escape($siteSettings['contact_email']) ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="copyright-text mb-0">
                        &copy; <?= date('Y') ?> <?= SecurityHelper::escape($siteSettings['site_title']) ?> Platformu. Tüm hakları saklıdır.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="footer-legal-nav">
                        <li><a href="<?= SecurityHelper::escape($siteUrl('gizlilik-politikasi')) ?>">Gizlilik & KVKK</a></li>
                        <li><a href="<?= SecurityHelper::escape($siteUrl('kullanim-kosullari')) ?>">Kullanım Koşulları</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= SecurityHelper::escape($siteUrl('public/js/main.js')) ?>"></script>
</body>
</html>
