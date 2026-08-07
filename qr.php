<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: /");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.slug = ? AND b.is_deleted = 0");
    $stmt->execute([$slug]);
    $business = $stmt->fetch();
    
    if (!$business) {
        // Fallback: Check if it's a CMS page
        $pageStmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND is_published = 1");
        $pageStmt->execute([$slug]);
        $cmsPage = $pageStmt->fetch();
        if ($cmsPage) {
            require_once __DIR__ . '/page_template.php';
            exit;
        }

        header("HTTP/1.0 404 Not Found");
        header("Location: /404");
        exit;
    }
} catch (Exception $e) {
    die("Veritabanı hatası oluştu.");
}

// Site adı — settings tablosundan çekilir
$siteTitle = '';
try {
    $siteRow = $pdo->query("SELECT site_title FROM settings WHERE id = 1 LIMIT 1")->fetch();
    if ($siteRow && !empty(trim($siteRow['site_title']))) {
        $siteTitle = trim($siteRow['site_title']);
    }
} catch (Exception $e) {}

// Cover image resolution
$coverImage = !empty($business['cover_image_path']) ? 
    (strpos($business['cover_image_path'], 'http') === 0 ? $business['cover_image_path'] : seoGetBaseUrl() . '/public/images/' . ltrim($business['cover_image_path'], '/')) :
    'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&q=80';

// Logo image resolution
$logoUrl = (!empty($business['logo_path']) && $business['logo_path'] !== 'default_logo.png') ?
    (strpos($business['logo_path'], 'http') === 0 ? $business['logo_path'] : seoGetBaseUrl() . '/public/images/' . ltrim($business['logo_path'], '/')) :
    seoGetBaseUrl() . '/public/images/default_logo.png';

// Theme color extraction (Default is a sleek dark green matching the reference)
$themeColor = !empty($business['theme_color']) ? $business['theme_color'] : '#1e3932';

// Check which buttons to render
$buttons = [];

// 1. Menu — smart: in-app first, then fallback to menu_url
$menuLink = null;
try {
    $menuCheck = $pdo->prepare("SELECT COUNT(*) FROM menu_categories WHERE business_id=? AND is_active=1");
    $menuCheck->execute([$business['id']]);
    if ($menuCheck->fetchColumn() > 0) {
        $menuLink = seoGetBaseUrl() . '/menu/' . $business['slug'];
    } elseif (!empty($business['menu_url'])) {
        $menuLink = $business['menu_url'];
    }
} catch (Exception $e) {
    if (!empty($business['menu_url'])) $menuLink = $business['menu_url'];
}
if ($menuLink) {
    $buttons[] = [
        'label' => 'MENÜ',
        'icon' => 'fa-solid fa-utensils',
        'url' => $menuLink,
        'color' => '#1A202C'
    ];
}

if (!empty($business['website'])) {
    $websiteUrl = $business['website'];
    if (strpos($websiteUrl, 'http') !== 0) {
        $websiteUrl = 'https://' . ltrim($websiteUrl, '/');
    }
    $buttons[] = [
        'label' => 'WEB SİTESİ',
        'icon' => 'fa-solid fa-globe',
        'url' => $websiteUrl,
        'color' => '#2d6a4f'
    ];
}

// 2. Instagram
if (!empty($business['instagram'])) {
    $buttons[] = [
        'label' => 'INSTAGRAM',
        'icon' => 'fa-brands fa-instagram',
        'url' => $business['instagram'],
        'color' => '#E1306C'
    ];
}

// 3. Google Maps Location
if (!empty($business['google_maps_iframe'])) {
    // Attempt to extract src URL from iframe if it's raw HTML, or use it directly
    $mapUrl = '#';
    if (preg_match('/src="([^"]+)"/', $business['google_maps_iframe'], $match)) {
        $mapUrl = $match[1];
    } else {
        $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($business['name'] . ' ' . $business['address']);
    }
    
    // We want to open the direct maps search/directions link for the button
    $directMapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($business['name'] . ' ' . $business['address']);
    
    $buttons[] = [
        'label' => 'KONUM',
        'icon' => 'fa-solid fa-location-dot',
        'url' => $directMapUrl,
        'color' => '#4285F4'
    ];
}

// 4. Facebook
if (!empty($business['facebook'])) {
    $buttons[] = [
        'label' => 'FACEBOOK',
        'icon' => 'fa-brands fa-facebook-f',
        'url' => $business['facebook'],
        'color' => '#1877F2'
    ];
}

// 5. TikTok
if (!empty($business['tiktok'])) {
    $buttons[] = [
        'label' => 'TIKTOK',
        'icon' => 'fa-brands fa-tiktok',
        'url' => $business['tiktok'],
        'color' => '#010101'
    ];
}

// 6. WhatsApp
if (!empty($business['whatsapp'])) {
    $buttons[] = [
        'label' => 'WHATSAPP',
        'icon' => 'fa-brands fa-whatsapp',
        'url' => "https://wa.me/" . preg_replace('/[^0-9]/', '', $business['whatsapp']),
        'color' => '#25D366'
    ];
}

// 7. Yemeksepeti
if (!empty($business['yemeksepeti'])) {
    $buttons[] = [
        'label' => 'YEMEKSEPETİ',
        'icon' => 'fa-solid fa-motorcycle',
        'url' => $business['yemeksepeti'],
        'color' => '#fa0050'
    ];
}

// 8. Share (Paylaş) - Always present at the end
$buttons[] = [
    'label' => 'PAYLAŞ',
    'icon' => 'fa-solid fa-share-nodes',
    'url' => 'javascript:shareProfile();',
    'color' => '#4A5568',
    'is_js' => true
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= SecurityHelper::escape($business['name']) ?> - Dijital Menü & Profil</title>
    <meta name="description" content="<?= SecurityHelper::escape($business['name']) ?> Yerel esnaf dijital kartvizit ve menü bağlantıları.">
    <meta name="keywords" content="<?= SecurityHelper::escape($business['name']) ?>, dijital menü, qr kod menü, <?= SecurityHelper::escape(strtolower(seoGetRegionName())) ?> esnaf rehberi">

    <?php
    require_once __DIR__ . '/includes/seo-meta.php';
    $qrSiteSettings = ['site_title' => $siteTitle ?: ($siteSettings['site_title'] ?? 'Şehir Rehberi'), 'site_logo' => ''];
    try {
        $qrSettingsRow = $pdo->query("SELECT site_title, site_logo FROM settings WHERE id = 1 LIMIT 1")->fetch();
        if ($qrSettingsRow) {
            $qrSiteSettings = $qrSettingsRow;
        }
    } catch (Exception $e) {}
    $qrBaseUrl = seoGetBaseUrl();
    $qrCanonicalUrl = $qrBaseUrl . '/' . $business['slug'];
    $qrOgImage = $coverImage;
    // Use dynamic favicon from settings
    $_qrFaviconSetting = $siteSettings['site_logo'] ?? '';
    $qrSiteFavicon = !empty($_qrFaviconSetting) ? ($qrBaseUrl . '/public/images/' . ltrim($_qrFaviconSetting, '/')) : ($qrBaseUrl . '/public/images/default_favicon.png');
    seoRenderSocialMetaTags([
        'title' => $business['name'] . ' | ' . ($qrSiteSettings['site_title'] ?? ($siteSettings['site_title'] ?? 'Şehir Rehberi')),
        'description' => $business['name'] . ' — Şehir dijital profil, menü ve iletişim bağlantıları.',
        'url' => $qrCanonicalUrl,
        'image' => seoResolveAbsoluteUrl($qrOgImage, $qrBaseUrl),
        'type' => 'website',
        'siteName' => $qrSiteSettings['site_title'] ?? ($siteSettings['site_title'] ?? 'Şehir Rehberi'),
        'imageAlt' => $business['name'],
    ]);
    ?>
    <link rel="shortcut icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>" type="image/png">
    <link rel="icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>">
    <meta name="theme-color" content="<?= SecurityHelper::escape($themeColor) ?>">
    
    <!-- Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: <?= $themeColor ?>;
            --font-main: 'Outfit', sans-serif;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        /* Mobile container constraints */
        .profile-container {
            width: 100%;
            max-width: 480px;
            min-height: 100vh;
            background-color: var(--bg-color);
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            padding-bottom: 60px;
        }
        
        /* Cover photo layout */
        .cover-photo {
            width: 100%;
            height: 280px;
            background-image: url('<?= SecurityHelper::escape($coverImage) ?>');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        /* Gradient overlay to smoothly transition into the background color */
        .cover-photo::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 150px;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, var(--bg-color) 100%);
        }
        
        /* Centered Logo */
        .logo-wrapper {
            position: relative;
            margin-top: -75px;
            display: flex;
            justify-content: center;
            z-index: 10;
        }
        
        .logo-image {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 4px solid #ffffff;
            object-fit: contain;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Business Info styling */
        .business-info {
            text-align: center;
            padding: 20px 24px 30px 24px;
        }
        
        .business-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        
        .business-category {
            font-size: 13px;
            opacity: 0.8;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            margin-bottom: 8px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 14px;
            border-radius: 20px;
        }
        
        .business-desc {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.5;
            margin-top: 10px;
            padding: 0 10px;
        }
        
        /* Grid Buttons layout */
        .links-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px 12px;
            padding: 0 24px;
            margin-bottom: 40px;
        }
        
        .grid-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #ffffff;
            transition: transform 0.2s ease;
        }
        
        .grid-item:active {
            transform: scale(0.92);
        }
        
        .circle-button {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background-color: #ffffff;
            color: var(--bg-color); /* Icon matches business theme color */
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            font-size: 28px;
            transition: all 0.2s ease;
        }
        
        .grid-item:hover .circle-button {
            box-shadow: 0 8px 22px rgba(255, 255, 255, 0.2);
        }
        
        .button-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-align: center;
            opacity: 0.9;
        }
        
        /* Footer / Powered By statement */
        .footer-tag {
            margin-top: auto;
            text-align: center;
            font-size: 12px;
            opacity: 0.5;
            letter-spacing: 0.5px;
        }

        .footer-tag-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .footer-tag-logo {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.9);
            padding: 2px;
        }
        
        .footer-tag a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 700;
        }
        
        /* Custom Toast notification */
        .toast-msg {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background-color: rgba(255, 255, 255, 0.95);
            color: #1A202C;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 9999;
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s ease;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .toast-msg.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <!-- Cover Section -->
        <div class="cover-photo"></div>
        
        <!-- Logo Section -->
        <div class="logo-wrapper">
            <img src="<?= SecurityHelper::escape($logoUrl) ?>" alt="<?= SecurityHelper::escape($business['name']) ?> Logo" class="logo-image">
        </div>
        
        <!-- Info Section -->
        <div class="business-info">
            <?php if (!empty($business['category_name'])): ?>
                <span class="business-category"><?= SecurityHelper::escape($business['category_name']) ?></span>
            <?php endif; ?>
            
            <h1 class="business-name"><?= SecurityHelper::escape($business['name']) ?></h1>
            <p style="font-size: 13px; opacity: 0.7;"><i class="fa-solid fa-location-dot me-1"></i> <?= SecurityHelper::escape($business['district']) ?> / Şehir</p>
            
            <?php if (!empty($business['description'])): ?>
                <p class="business-desc"><?= SecurityHelper::escape($business['description']) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Links Grid -->
        <div class="links-grid">
            <?php foreach ($buttons as $btn): ?>
                <a href="<?= $btn['url'] ?>" class="grid-item" <?= (isset($btn['is_js']) ? '' : 'target="_blank"') ?>>
                    <div class="circle-button">
                        <i class="<?= $btn['icon'] ?>"></i>
                    </div>
                    <span class="button-label"><?= $btn['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="footer-tag">
            <p class="footer-tag-inner">
                <span>Powered by <a href="https://www.zakyazilim.com" target="_blank" rel="noopener noreferrer">Zak Yazılım</a></span>
            </p>
        </div>
    </div>
    
    <!-- Share toast notification -->
    <div id="shareToast" class="toast-msg">
        <i class="fa-solid fa-circle-check text-success"></i>
        <span>Profil linki kopyalandı!</span>
    </div>

    <script>
        function shareProfile() {
            const shareData = {
                title: '<?= SecurityHelper::escape(addslashes($business['name'])) ?>',
                text: '<?= SecurityHelper::escape(addslashes($business['name'])) ?> <?= SecurityHelper::escape(addslashes($siteTitle)) ?> Dijital Kartvizit Profili',
                url: window.location.href
            };

            if (navigator.share) {
                navigator.share(shareData)
                    .catch((error) => console.log('Paylaşım hatası:', error));
            } else {
                // Clipboard fallback
                const dummy = document.createElement('input');
                document.body.appendChild(dummy);
                dummy.value = window.location.href;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);
                
                // Show toast
                const toast = document.getElementById('shareToast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        }
    </script>
</body>
</html>
