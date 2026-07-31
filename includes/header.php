<?php
// Load Settings dynamically
$siteSettings = [
    'site_title' => ($siteSettings['site_title'] ?? 'Şehir Rehberi'),
    'site_description' => 'şehrin dijital esnaf, işletme, gezi ve etkinlik rehberi.',
    'site_keywords' => 'şehir rehberi, yerel işletmeler, esnaf rehberi',
    'site_logo' => '',
    'contact_email' => '',
    'contact_phone' => '0326 222 22 22',
    'contact_whatsapp' => '905551112233',
    'contact_address' => 'Şehir Merkezi',
    'social_instagram' => '#',
    'social_facebook' => '#',
    'social_tiktok' => '#',
    'social_youtube' => '#',
    'google_analytics' => ''
];

$services = [];
if (class_exists('Database')) {
    try {
        $dbConn = Database::getInstance()->getPDO();
        $dbSettings = $dbConn->query("SELECT * FROM settings WHERE id = 1")->fetch();
        if ($dbSettings) {
            $siteSettings = $dbSettings;
        }
        $services = $dbConn->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();
    } catch (Exception $e) {
        // Fallback
    }
}

if (empty($services)) {
    $services = [
        [
            'title' => 'Google Harita Kaydı',
            'slug' => 'google-harita',
            'icon' => 'fa-solid fa-map-location-dot',
            'subject' => 'Google Haritalar Kurulumu',
            'description' => 'İşletmenizi Google Haritalar\'da görünür kılın, müşterilerin sizi kolayca bulmasını sağlayın.'
        ],
        [
            'title' => 'Sosyal Medya Yönetimi',
            'slug' => 'sosyal-medya',
            'icon' => 'fa-solid fa-share-nodes',
            'subject' => 'Sosyal Medya Yönetimi',
            'description' => 'Instagram, Facebook ve TikTok hesaplarınızı profesyonelce yönetiyoruz.'
        ],
        [
            'title' => 'Görsel & AI Tasarım',
            'slug' => 'gorsel-ai',
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'subject' => 'Yapay Zeka Görsel Üretimi',
            'description' => 'Yapay zeka destekli görsel ve tasarım içerikleri ile markanızı öne çıkarın.'
        ],
        [
            'title' => 'QR Menü & Dijital Kartvizit',
            'slug' => 'qr-menu',
            'icon' => 'fa-solid fa-qrcode',
            'subject' => 'Dijital Kartvizit & QR Menü',
            'description' => 'Temassız dijital menü ve dijital kartvizit çözümleri ile müşteri deneyimini iyileştirin.'
        ],
        [
            'title' => 'Özel Web Tasarımı',
            'slug' => 'web-tasarim',
            'icon' => 'fa-solid fa-code',
            'subject' => 'Yeni İşletme Kaydı',
            'description' => 'İşletmenize özel, mobil uyumlu ve SEO dostu web sitesi tasarımı.'
        ],
        [
            'title' => 'Premium Rehber Vitrini',
            'slug' => 'esnaf-vitrini',
            'icon' => 'fa-solid fa-crown',
            'subject' => 'Reklam ve Sponsorluk',
            'description' => 'Ana sayfa vitrininde öne çıkarak daha fazla müşteriye ulaşın.'
        ]
    ];
}

require_once __DIR__ . '/seo-meta.php';
$seoBaseUrl = seoGetBaseUrl();
$seoBaseUrlTrimmed = rtrim($seoBaseUrl, '/');
$siteUrl = function ($path = '') use ($seoBaseUrlTrimmed) {
    if ($path === '' || $path === '/') {
        return $seoBaseUrlTrimmed . '/';
    }

    return $seoBaseUrlTrimmed . '/' . ltrim($path, '/');
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? SecurityHelper::escape($pageTitle) . ' | ' . SecurityHelper::escape($siteSettings['site_title']) : (!empty($siteSettings['default_seo_title']) ? SecurityHelper::escape($siteSettings['default_seo_title']) : SecurityHelper::escape($siteSettings['site_title']) . ' | ' . SecurityHelper::escape($siteSettings['site_description'])) ?></title>
    
    <meta name="description" content="<?= isset($metaDescription) ? SecurityHelper::escape($metaDescription) : (!empty($siteSettings['default_seo_desc']) ? SecurityHelper::escape($siteSettings['default_seo_desc']) : SecurityHelper::escape($siteSettings['site_description'] ?? '')) ?>">
    <meta name="keywords" content="<?= isset($metaKeywords) ? SecurityHelper::escape($metaKeywords) : SecurityHelper::escape($siteSettings['site_keywords'] ?? '') ?>">
    <?php if (!empty($robotsMeta)): ?>
    <meta name="robots" content="<?= SecurityHelper::escape($robotsMeta) ?>">
    <?php endif; ?>
    
    <?php if (class_exists('CSRFMiddleware')): ?>
    <?= CSRFMiddleware::meta() ?>
    <?php endif; ?>
    
    <!-- Google Fonts: Oswald (logo wordmark karakterine yakın, dik/kalın) & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SecurityHelper::escape($siteUrl('public/css/style.css?v=' . time())) ?>">
    <?php
    // Dynamic primary color override from settings
    $_siteColor = $siteSettings['admin_primary_color'] ?? '#D62828';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $_siteColor)) $_siteColor = '#D62828';
    // Calculate a simple darkened hover color (reduce each channel by ~15%)
    list($r,$g,$b) = sscanf($_siteColor, '#%02x%02x%02x');
    $rh = max(0, intval($r * 0.85));
    $gh = max(0, intval($g * 0.85));
    $bh = max(0, intval($b * 0.85));
    $hoverColor = sprintf('#%02x%02x%02x', $rh, $gh, $bh);
    ?>
    <style>
        :root {
            --primary: <?= $_siteColor ?> !important;
            --primary-hover: <?= $hoverColor ?> !important;
        }
    </style>
    
    <!-- Favicon -->
    <?php $faviconUrl = !empty($siteSettings['site_logo']) ? (strpos($siteSettings['site_logo'], 'http') === 0 ? $siteSettings['site_logo'] : $siteUrl('public/images/' . $siteSettings['site_logo'])) : $siteUrl('public/images/default_favicon.png'); ?>
    <link rel="shortcut icon" href="<?= SecurityHelper::escape($faviconUrl) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= SecurityHelper::escape($faviconUrl) ?>">

    <?php
    $seoCanonicalUrl = seoGetCanonicalUrl(isset($canonicalUrl) ? $canonicalUrl : null);
    ?>
    <link rel="alternate" type="text/markdown" href="<?= SecurityHelper::escape($seoBaseUrl) ?>/llms.txt" title="LLMs.txt">
    <?php
    if (!empty($siteSettings['site_logo'])) {
        $preloadLogoUrl = (strpos($siteSettings['site_logo'], 'http') === 0)
            ? $siteSettings['site_logo']
            : $siteUrl('public/images/' . $siteSettings['site_logo']);
        echo '<link rel="preload" as="image" href="' . SecurityHelper::escape($preloadLogoUrl) . '">' . "\n    ";
    }
    ?>

    <?php
    $seoPageTitle = isset($pageTitle)
        ? SecurityHelper::escape($pageTitle) . ' | ' . SecurityHelper::escape($siteSettings['site_title'] ?? 'Rehber')
        : SecurityHelper::escape($siteSettings['default_seo_title'] ?? $siteSettings['site_title'] ?? 'Rehber');
    $seoDescription = isset($metaDescription)
        ? SecurityHelper::escape($metaDescription)
        : SecurityHelper::escape($siteSettings['default_seo_desc'] ?? $siteSettings['site_description'] ?? '');
    $seoShareImage = seoGetShareImageUrl($siteSettings, $seoBaseUrl, isset($ogImage) ? $ogImage : null);
    $seoOgType = isset($ogType) ? $ogType : 'website';
    $seoImageAlt = isset($ogImageAlt) ? $ogImageAlt : ($siteSettings['site_title'] ?? 'Şehir Rehberi');
    $seoSchema = seoBuildOrganizationSchema($siteSettings, $seoBaseUrl, $seoCanonicalUrl);
    seoRenderSocialMetaTags([
        'title' => html_entity_decode(strip_tags($seoPageTitle), ENT_QUOTES, 'UTF-8'),
        'description' => html_entity_decode(strip_tags($seoDescription), ENT_QUOTES, 'UTF-8'),
        'url' => $seoCanonicalUrl,
        'image' => $seoShareImage,
        'type' => $seoOgType,
        'siteName' => $siteSettings['site_title'] ?? 'Şehir Rehberi',
        'imageAlt' => $seoImageAlt,
    ]);
    ?>
    <script type="application/ld+json"><?= json_encode($seoSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    
    <!-- Google Analytics / Custom Tracking Codes -->
    <?= $siteSettings['google_analytics'] ?>
</head>
<body class="site-body">

<header class="site-header">
<!-- Top Bar -->
<div class="header-top py-2 d-none d-lg-block">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex gap-4">
                    <a href="mailto:<?= SecurityHelper::escape($siteSettings['contact_email']) ?>" class="top-bar-link"><i class="fa-regular fa-envelope me-2"></i><?= SecurityHelper::escape($siteSettings['contact_email']) ?></a>
                    <span class="top-bar-divider"></span>
                    <span class="top-bar-text"><i class="fa-solid fa-location-dot me-2"></i><?= SecurityHelper::escape($siteSettings['contact_address']) ?></span>
                    <?php if (!empty($siteSettings['contact_phone'])): ?>
                    <span class="top-bar-divider"></span>
                    <a href="tel:<?= preg_replace('/[^0-9]/', '', $siteSettings['contact_phone']) ?>" class="top-bar-link"><i class="fa-solid fa-phone me-2"></i><?= SecurityHelper::escape($siteSettings['contact_phone']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex justify-content-end align-items-center gap-3">
                    <div class="top-bar-social">
                        <?php if (!empty($siteSettings['social_youtube']) && $siteSettings['social_youtube'] !== '#'): ?>
                            <a href="<?= SecurityHelper::escape($siteSettings['social_youtube']) ?>" target="_blank" rel="noopener noreferrer" aria-label="YouTube kanalımız (yeni sekmede açılır)"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['social_facebook']) && $siteSettings['social_facebook'] !== '#'): ?>
                            <a href="<?= SecurityHelper::escape($siteSettings['social_facebook']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['social_instagram']) && $siteSettings['social_instagram'] !== '#'): ?>
                            <a href="<?= SecurityHelper::escape($siteSettings['social_instagram']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['social_tiktok']) && $siteSettings['social_tiktok'] !== '#'): ?>
                            <a href="<?= SecurityHelper::escape($siteSettings['social_tiktok']) ?>" target="_blank" rel="noopener noreferrer" aria-label="TikTok sayfamız (yeni sekmede açılır)"><i class="fa-brands fa-tiktok" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 
<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-light main-nav portal-nav sticky-top py-2 py-lg-3">
    <div class="container portal-nav-container align-items-center">
        <div class="portal-nav__top w-100 w-lg-auto d-flex align-items-center">
        <!-- Logo -->
        <a class="navbar-brand portal-brand d-flex align-items-center gap-2 flex-shrink-0" href="<?= SecurityHelper::escape($siteUrl('/')) ?>" aria-label="<?= SecurityHelper::escape($siteSettings['site_title']) ?> — Ana Sayfa">
            <?php if (!empty($siteSettings['site_logo'])): ?>
                <?php 
                $logoUrl = (strpos($siteSettings['site_logo'], 'http') === 0) ? $siteSettings['site_logo'] : $siteUrl('public/images/' . $siteSettings['site_logo']); 
                ?>
                <span class="navbar-brand-logo-wrap">
                    <img src="<?= SecurityHelper::escape($logoUrl) ?>" alt="<?= SecurityHelper::escape($siteSettings['site_title']) ?>" class="navbar-brand-logo" width="260" height="120" decoding="async" fetchpriority="high">
                </span>
            <?php else: ?>
                <div class="brand-logo-wrapper">
                    <i class="fa-solid fa-leaf text-white"></i>
                </div>
                <div class="brand-text d-flex flex-column justify-content-center">
                    <span class="brand-title fw-bold" style="white-space: normal; line-height: 1.15; max-width: 220px; font-size: 1.3rem; color: #1a1a1a; letter-spacing: -0.5px; word-break: break-word;">
                        <?= SecurityHelper::escape($siteSettings['site_title']) ?>
                    </span>
                    <span class="brand-subtitle text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 1px; margin-top: 3px;">
                        Şehrin Dijital Rehberi
                    </span>
                </div>
            <?php endif; ?>
        </a>

        <!-- Mobil: logo yanında İşletmeni Ekle butonu ve 3 çizgi menü butonu -->
        <div class="d-flex d-lg-none align-items-center gap-2 ms-auto">
            <a href="<?= SecurityHelper::escape($siteUrl('iletisim?subject=' . urlencode('Yeni İşletme Kaydı'))) ?>" class="btn btn-primary btn-sm fw-semibold d-inline-flex align-items-center gap-1" style="border-radius: 6px; font-size: 11.5px; line-height: 1.2; padding: 6px 10px !important; white-space: nowrap;">
                <i class="fa-solid fa-plus" style="font-size: 10px;"></i>
                <span>İşletmeni Ekle</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none p-2 flex-shrink-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menüyü aç">
                <i class="fa-solid fa-bars fs-4 text-dark"></i>
            </button>
        </div>
        </div>
 
        <!-- Menu Links -->
        <div class="collapse navbar-collapse portal-nav-collapse" id="mainNav">
            <ul class="navbar-nav portal-nav-links mx-auto mb-2 mb-lg-0 gap-1 gap-lg-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'esnaflar.php' ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('esnaflar')) ?>">İşletmeler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['influencerlar.php', 'influencer.php', 'influencer-basvuru.php', 'influencer-kaldirma-talebi.php'], true) ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('influencerlar')) ?>">Influencerlar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['etkinlikler.php', 'etkinlik.php'], true) ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('etkinlikler')) ?>">Etkinlikler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['kampanyalar.php', 'kampanya.php'], true) ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('kampanyalar')) ?>">Kampanyalar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'nobetci-eczane.php' ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('nobetci-eczane')) ?>">Nöbetçi Eczane</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link custom-nav-link dropdown-toggle <?= basename($_SERVER['SCRIPT_NAME']) == 'hizmetlerimiz.php' ? 'active' : '' ?>" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Hizmetlerimiz
                    </a>
                    <ul class="dropdown-menu border shadow-sm" aria-labelledby="servicesDropdown">
                        <?php foreach ($services as $srv): ?>
                            <li><a class="dropdown-item py-2" href="<?= SecurityHelper::escape($siteUrl('hizmetlerimiz/' . $srv['slug'])) ?>"><i class="<?= SecurityHelper::escape($srv['icon']) ?> text-primary me-2" style="width: 18px;"></i> <?= SecurityHelper::escape($srv['title']) ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider" style="border-color: #f1f5f9;"></li>
                        <li><a class="dropdown-item py-2 text-primary fw-bold text-center" href="<?= SecurityHelper::escape($siteUrl('hizmetlerimiz')) ?>">Tüm Hizmetlerimiz</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'hakkimizda.php' ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('hakkimizda')) ?>">Hakkımızda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'blog.php') !== false || strpos($_SERVER['SCRIPT_NAME'], 'blog-detay.php') !== false ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('blog')) ?>">Blog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link custom-nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'iletisim.php' ? 'active' : '' ?>" href="<?= SecurityHelper::escape($siteUrl('iletisim')) ?>">İletişim</a>
                </li>
            </ul>
 
            <!-- Right Action Buttons -->
            <div class="nav-actions portal-nav-actions d-flex align-items-center gap-2 mt-3 mt-lg-0" style="align-self: center; margin-top: -8px !important;">
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                    <div class="dropdown w-100">
                        <a href="#" class="btn btn-light border dropdown-toggle px-3 py-2 w-100 d-flex align-items-center justify-content-center gap-1" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-regular fa-user"></i> <span><?= SecurityHelper::escape($_SESSION['user_name'] ?? 'Profilim') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border shadow-sm">
                            <li><a class="dropdown-item py-2" href="<?= SecurityHelper::escape($siteUrl('profil.php')) ?>"><i class="fa-solid fa-id-card me-2 text-muted"></i> Profilim</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?= SecurityHelper::escape($siteUrl('cikis.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i> Çıkış Yap</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="dropdown w-100">
                        <button class="btn btn-outline-primary fw-semibold dropdown-toggle px-3 py-2 w-100 d-flex align-items-center justify-content-center gap-1" type="button" id="loginDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-regular fa-user"></i> <span>Giriş Yap</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" aria-labelledby="loginDropdown" style="border-radius: 12px; padding: 10px; min-width: 200px;">
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-2 d-flex align-items-center gap-2" href="<?= SecurityHelper::escape($siteUrl('giris.php')) ?>" style="transition: all 0.2s;">
                                    <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="fa-regular fa-user"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold" style="font-size: 14px;">Bireysel Giriş</span>
                                        <span class="text-muted" style="font-size: 11px;">Kullanıcı paneli</span>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-2" style="border-color: #f1f5f9;"></li>
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded-2 d-flex align-items-center gap-2" href="<?= SecurityHelper::escape($siteUrl('isletme/login.php')) ?>" target="_blank" rel="noopener noreferrer" style="transition: all 0.2s;">
                                    <div class="bg-light text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="fa-solid fa-store"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark" style="font-size: 14px;">İşletme Girişi</span>
                                        <span class="text-muted" style="font-size: 11px;">Esnaf yönetim paneli</span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <a href="<?= SecurityHelper::escape($siteUrl('iletisim.php')) ?>" class="btn btn-primary btn-add-business portal-nav-cta-primary px-3 d-none d-lg-inline-flex">
                    <i class="fa-solid fa-plus"></i>
                    <span class="d-none d-xl-inline ms-1">İşletme Ekle</span>
                    <i class="fa-solid fa-arrow-right d-none d-xxl-inline ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
</header>
