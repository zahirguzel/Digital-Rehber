<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/influencer-helpers.php';
require_once 'includes/seo-meta.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /influencerlar');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE slug = ? AND is_published = 1 AND consent_given = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $influencer = $stmt->fetch();

    if (!$influencer) {
        header('HTTP/1.0 404 Not Found');
        header('Location: /404');
        exit;
    }
} catch (Exception $e) {
    die('Veritabanı hatası oluştu.');
}

$siteTitle = '';
try {
    $siteRow = $pdo->query("SELECT site_title FROM settings WHERE id = 1 LIMIT 1")->fetch();
    if ($siteRow && !empty(trim($siteRow['site_title']))) {
        $siteTitle = trim($siteRow['site_title']);
    }
} catch (Exception $e) {}

$coverImage = getInfluencerImageUrl(
    $influencer['cover_path'],
    'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=1200&q=80'
);
$avatarImage = getInfluencerImageUrl($influencer['avatar_path']);
$letter = mb_strtoupper(mb_substr($influencer['name'], 0, 1, 'UTF-8'), 'UTF-8');
$themeColor = !empty($influencer['theme_color']) ? $influencer['theme_color'] : '#1e3932';
$nicheLabel = getInfluencerNicheLabel($influencer['niche']);
$platforms = influencerPlatforms();
$featuredLinks = parseInfluencerFeaturedLinks($influencer['featured_links']);
$profileUrl = '/influencer/' . $influencer['slug'];
$collabUrl = $profileUrl . '#isbirligi';

$buttons = [];

foreach ($platforms as $pSlug => $pData) {
    if (empty($influencer[$pData['field']])) {
        continue;
    }
    $colors = [
        'instagram' => '#E1306C',
        'tiktok' => '#010101',
        'youtube' => '#FF0000',
    ];
    $labels = [
        'instagram' => 'INSTAGRAM',
        'tiktok' => 'TIKTOK',
        'youtube' => 'YOUTUBE',
    ];
    $buttons[] = [
        'label' => $labels[$pSlug] ?? strtoupper($pData['label']),
        'icon' => $pData['icon'],
        'url' => $influencer[$pData['field']],
        'color' => isset($colors[$pSlug]) ? $colors[$pSlug] : '#1A202C',
    ];
}

foreach ($featuredLinks as $index => $link) {
    if ($index >= 3) {
        break;
    }
    $buttons[] = [
        'label' => count($featuredLinks) > 1 ? 'İÇERİK ' . ($index + 1) : 'İÇERİK',
        'icon' => 'fa-solid fa-play',
        'url' => $link,
        'color' => '#6366F1',
    ];
}

$buttons[] = [
    'label' => 'TAM PROFİL',
    'icon' => 'fa-solid fa-user',
    'url' => $profileUrl,
    'color' => '#1A202C',
    'internal' => true,
];

$buttons[] = [
    'label' => 'İŞ BİRLİĞİ',
    'icon' => 'fa-solid fa-handshake',
    'url' => $collabUrl,
    'color' => '#0EA5E9',
    'internal' => true,
];

if (!empty($influencer['contact_email'])) {
    $buttons[] = [
        'label' => 'E-POSTA',
        'icon' => 'fa-solid fa-envelope',
        'url' => 'mailto:' . $influencer['contact_email'],
        'color' => '#64748B',
    ];
}

$buttons[] = [
    'label' => 'PAYLAŞ',
    'icon' => 'fa-solid fa-share-nodes',
    'url' => 'javascript:shareProfile();',
    'color' => '#4A5568',
    'is_js' => true,
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= SecurityHelper::escape($influencer['name']) ?> - Dijital Influencer Profili</title>
    <meta name="description" content="<?= SecurityHelper::escape($influencer['name']) ?> <?= SecurityHelper::escape(seoGetRegionName()) ?> influencer dijital kartvizit ve sosyal medya bağlantıları.">
    <meta name="keywords" content="<?= SecurityHelper::escape($influencer['name']) ?>, <?= SecurityHelper::escape(strtolower(seoGetRegionName())) ?> influencer, qr dijital profil, <?= SecurityHelper::escape($influencer['district']) ?> fenomen">

    <?php
    require_once __DIR__ . '/includes/seo-meta.php';
    $_defaultSiteTitle = seoGetSiteTitle();
    $qrSiteSettings = ['site_title' => $siteTitle ?: $_defaultSiteTitle, 'site_logo' => ''];
    try {
        $qrSettingsRow = $pdo->query("SELECT site_title, site_logo FROM settings WHERE id = 1 LIMIT 1")->fetch();
        if ($qrSettingsRow) {
            $qrSiteSettings = $qrSettingsRow;
        }
    } catch (Exception $e) {}
    $qrBaseUrl = seoGetBaseUrl();
    $qrCanonicalUrl = $qrBaseUrl . influencerQrUrl($influencer['slug']);
    $qrOgImage = $avatarImage ?: $coverImage;
    $_qrFaviconSetting = $siteSettings['site_logo'] ?? '';
    $qrSiteFavicon = !empty($_qrFaviconSetting) ? ($qrBaseUrl . '/public/images/' . ltrim($_qrFaviconSetting, '/')) : ($qrBaseUrl . '/public/images/default_favicon.png');
    seoRenderSocialMetaTags([
        'title' => $influencer['name'] . ' | ' . ($qrSiteSettings['site_title'] ?? $_defaultSiteTitle),
        'description' => $influencer['name'] . ' — ' . seoGetRegionName() . ' influencer dijital kartvizit ve sosyal medya bağlantıları.',
        'url' => $qrCanonicalUrl,
        'image' => seoResolveAbsoluteUrl($qrOgImage, $qrBaseUrl),
        'type' => 'profile',
        'siteName' => $qrSiteSettings['site_title'] ?? $_defaultSiteTitle,
        'imageAlt' => $influencer['name'],
    ]);
    ?>
    <link rel="shortcut icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>" type="image/png">
    <link rel="icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= SecurityHelper::escape($qrSiteFavicon) ?>">
    <meta name="theme-color" content="<?= SecurityHelper::escape($themeColor) ?>">

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

        .cover-photo {
            width: 100%;
            height: 280px;
            background-image: url('<?= SecurityHelper::escape($coverImage) ?>');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .cover-photo::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 150px;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, var(--bg-color) 100%);
        }

        .avatar-wrapper {
            position: relative;
            margin-top: -75px;
            display: flex;
            justify-content: center;
            z-index: 10;
        }

        .avatar-image {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 4px solid #ffffff;
            object-fit: cover;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .avatar-letter {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 4px solid #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: var(--bg-color);
        }

        .profile-info {
            text-align: center;
            padding: 20px 24px 30px 24px;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .profile-niche {
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

        .profile-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 10px;
        }

        .profile-desc {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.5;
            margin-top: 10px;
            padding: 0 10px;
        }

        .follower-row {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .follower-item {
            font-size: 12px;
            opacity: 0.9;
        }

        .follower-item strong {
            display: block;
            font-size: 16px;
            font-weight: 700;
        }

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
            color: var(--bg-color);
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
        <div class="cover-photo"></div>

        <div class="avatar-wrapper">
            <?php if ($avatarImage): ?>
            <img src="<?= SecurityHelper::escape($avatarImage) ?>" alt="<?= SecurityHelper::escape($influencer['name']) ?>" class="avatar-image">
            <?php else: ?>
            <div class="avatar-letter" aria-hidden="true"><?= SecurityHelper::escape($letter) ?></div>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <?php if ($influencer['is_verified']): ?>
            <div class="profile-verified"><i class="fa-solid fa-circle-check"></i> Doğrulanmış Profil</div>
            <?php endif; ?>

            <span class="profile-niche"><?= SecurityHelper::escape($nicheLabel) ?></span>
            <h1 class="profile-name"><?= SecurityHelper::escape($influencer['name']) ?></h1>
            <p style="font-size: 13px; opacity: 0.7;"><i class="fa-solid fa-location-dot" style="margin-right: 6px;"></i><?= SecurityHelper::escape($influencer['district']) ?> / <?= SecurityHelper::escape(seoGetRegionName()) ?></p>

            <?php
            $hasFollowers = false;
            foreach ($platforms as $pData) {
                if ((int) $influencer[$pData['followers']] > 0 && !empty($influencer[$pData['field']])) {
                    $hasFollowers = true;
                    break;
                }
            }
            if ($hasFollowers):
            ?>
            <div class="follower-row">
                <?php foreach ($platforms as $pData):
                    $fCount = (int) $influencer[$pData['followers']];
                    if ($fCount <= 0 || empty($influencer[$pData['field']])) continue;
                ?>
                <div class="follower-item">
                    <strong><?= formatInfluencerFollowers($fCount) ?></strong>
                    <?= SecurityHelper::escape($pData['label']) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($influencer['bio'])): ?>
            <p class="profile-desc"><?= SecurityHelper::escape(mb_strlen($influencer['bio']) > 160 ? mb_substr($influencer['bio'], 0, 157, 'UTF-8') . '...' : $influencer['bio']) ?></p>
            <?php endif; ?>
        </div>

        <div class="links-grid">
            <?php foreach ($buttons as $btn):
                $target = '';
                if (empty($btn['is_js']) && empty($btn['internal'])) {
                    $target = 'target="_blank" rel="noopener noreferrer"';
                }
            ?>
            <a href="<?= SecurityHelper::escape($btn['url']) ?>" class="grid-item" <?= $target ?>>
                <div class="circle-button">
                    <i class="<?= $btn['icon'] ?>"></i>
                </div>
                <span class="button-label"><?= $btn['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="footer-tag">
            <p class="footer-tag-inner">
                <img src="<?= SecurityHelper::escape($qrSiteFavicon) ?>" alt="" class="footer-tag-logo" width="18" height="18">
                <span>Powered by <a href="/"><?= SecurityHelper::escape($siteTitle ?: seoGetSiteTitle()) ?></a></span>
            </p>
        </div>
    </div>

    <div id="shareToast" class="toast-msg">
        <i class="fa-solid fa-circle-check text-success"></i>
        <span>Profil linki kopyalandı!</span>
    </div>

    <script>
        function shareProfile() {
            const shareData = {
                title: '<?= SecurityHelper::escape(addslashes($influencer['name'])) ?>',
                text: '<?= SecurityHelper::escape(addslashes($influencer['name'])) ?> <?= SecurityHelper::escape(addslashes($siteTitle ?: seoGetSiteTitle())) ?> Dijital Influencer Profili',
                url: window.location.href
            };

            if (navigator.share) {
                navigator.share(shareData)
                    .catch((error) => console.log('Paylaşım hatası:', error));
            } else {
                const dummy = document.createElement('input');
                document.body.appendChild(dummy);
                dummy.value = window.location.href;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);

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
