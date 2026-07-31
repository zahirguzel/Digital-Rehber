<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/menu-helpers.php';
require_once __DIR__ . '/includes/seo-meta.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) { header('Location: ' . seoGetBaseUrl() . '/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE slug = ?");
$stmt->execute([$slug]);
$business = $stmt->fetch();
if (!$business) { header('HTTP/1.0 404 Not Found'); header('Location: ' . seoGetBaseUrl() . '/404'); exit; }

$bizId      = $business['id'];
$baseUrl    = seoGetBaseUrl();
$themeColor = !empty($business['theme_color']) ? $business['theme_color'] : '#b85c2b';
$hasLogo    = !empty($business['logo_path']) && $business['logo_path'] !== 'default_logo.png';
$logoSrc    = $hasLogo ? menuBusinessImageUrl($business['logo_path']) : '';
$coverSrc   = !empty($business['cover_image_path']) ? menuBusinessImageUrl($business['cover_image_path']) : '';
$heroDescription = trim((string) ($business['description'] ?? ''));
$heroDescription = $heroDescription !== '' ? preg_replace('/\s+/', ' ', $heroDescription) : '';
$heroDescription = $heroDescription !== '' ? mb_substr($heroDescription, 0, 160, 'UTF-8') : 'Lezzetleri inceleyin, ürün detaylarını keşfedin.';
$locationLabel = trim(implode(' / ', array_filter([$business['district'] ?? '', $business['city'] ?? ''])));
$phoneLink = !empty($business['phone']) ? preg_replace('/[^0-9+]/', '', $business['phone']) : '';
$whatsAppLink = !empty($business['whatsapp']) ? preg_replace('/[^0-9]/', '', $business['whatsapp']) : '';

// Fetch categories and items
$categories      = [];
$itemsByCategory = [];
try {
    $catStmt = $pdo->prepare("SELECT * FROM menu_categories WHERE business_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    $catStmt->execute([$bizId]);
    $categories = $catStmt->fetchAll();

    $itemStmt = $pdo->prepare("SELECT * FROM menu_items WHERE business_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    $itemStmt->execute([$bizId]);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByCategory[$item['category_id']][] = $item;
    }
} catch (Exception $e) {
    $categories = [];
}

// Lighten theme color for tab background
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

$rgb = hexToRgb($themeColor);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= SecurityHelper::escape($business['name']) ?> — Dijital Menü</title>
    <meta name="robots" content="noindex">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?= $themeColor ?>;
            --primary-rgb: <?= $rgb[0] ?>, <?= $rgb[1] ?>, <?= $rgb[2] ?>;
            --radius-card: 14px;
            --radius-sm: 8px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #f6f7fb;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f6f7fb;
            color: #1a1a1a;
            min-height: 100vh;
            max-width: 540px;
            margin: 0 auto;
            position: relative;
            overflow-x: hidden;
            width: 100%;
        }

        /* ── COMPACT APP HEADER ─────────────────────────── */
        .menu-header {
            position: relative;
            background: #f6f7fb;
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: 12px 16px;
            overflow: hidden;
        }
        .menu-header-inner {
            position: relative;
            z-index: 1;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .logo-wrap {
            flex-shrink: 0;
            margin: 0;
        }
        .logo-img {
            width: 44px; height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .logo-letter {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: rgba(var(--primary-rgb), 0.12);
            color: var(--primary);
            font-size: 18px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(var(--primary-rgb), 0.25);
        }
        .biz-info {
            min-width: 0;
            text-align: left;
            flex: 1;
        }
        .biz-name {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.2px;
            line-height: 1.25;
            color: #0f172a;
            word-break: break-word;
        }
        .biz-sub {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 600;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 2px;
            line-height: 1.35;
        }
        .biz-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .biz-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            transition: transform 0.15s, background 0.15s;
        }
        .biz-action-btn:active {
            transform: scale(0.92);
        }
        .biz-action-btn--light {
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary);
            border: 1px solid rgba(var(--primary-rgb), 0.2);
            width: auto;
            padding: 0 12px;
            font-size: 12px;
            font-weight: 700;
            gap: 5px;
        }

        /* ── CATEGORY TABS ──────────────────────── */
        .cat-bar {
            background: rgba(255,255,255,.94);
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
        }
        .cat-list {
            display: flex;
            overflow-x: auto;
            scrollbar-width: none;
            padding: 0 12px;
            gap: 0;
        }
        .cat-list::-webkit-scrollbar { display: none; }
        .cat-btn {
            flex-shrink: 0;
            padding: 14px 16px 12px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #999;
            border: none;
            background: none;
            border-bottom: 2.5px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: color .15s, border-color .15s;
            text-decoration: none;
            display: block;
        }
        .cat-btn.active, .cat-btn:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* ── MENU CONTENT ────────────────────────── */
        .menu-content { padding: 12px 12px 80px; }

        .cat-section { margin-bottom: 32px; scroll-margin-top: 90px; }
        .cat-title {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #1a1a1a;
            padding: 4px 4px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cat-title span.cnt {
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
            border-radius: 20px;
            padding: 1px 8px;
        }

        /* ── PRODUCT GRID ────────────────────────── */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        @media (max-width: 320px) {
            .items-grid { grid-template-columns: 1fr; }
        }

        .item-card {
            background: #fff;
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
            transition: transform .15s;
            border: 1px solid rgba(0,0,0,.04);
        }
        .item-card:active { transform: scale(.97); }

        .item-img-wrap {
            position: relative;
            width: 100%;
            padding-top: 70%; /* 10:7 aspect ratio */
            background: #f5f5f5;
            overflow: hidden;
        }
        .item-img-wrap img {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .item-no-img {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 34px;
            background: linear-gradient(145deg, rgba(var(--primary-rgb), .12), rgba(var(--primary-rgb), .04));
        }
        .price-badge {
            position: absolute;
            bottom: 8px; right: 8px;
            background: var(--primary);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            box-shadow: 0 2px 6px rgba(var(--primary-rgb),.4);
        }

        .item-body {
            padding: 10px 11px 12px;
        }
        .item-name {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 3px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .item-desc {
            font-size: 11px;
            color: #888;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Full-width card (no image) */
        .item-card.no-photo {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            gap: 12px;
        }
        .item-card.no-photo .item-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: linear-gradient(145deg, rgba(var(--primary-rgb),.14), rgba(var(--primary-rgb),.06));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--primary);
            font-size: 20px;
        }
        .item-card.no-photo .item-body { padding: 0; flex: 1; min-width: 0; }
        .item-card.no-photo .item-price-text {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary);
            margin-top: 3px;
        }

        /* ── CHEF / BADGE / CHIPS ────────────────── */
        .item-badge {
            position: absolute;
            top: 8px; left: 8px;
            background: var(--primary);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: .3px;
            white-space: nowrap;
        }
        .item-badge.chefs { background: #1a1a1a; }
        .meta-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 5px;
        }
        .meta-chip {
            font-size: 10px;
            color: #666;
            background: #f5f5f5;
            border-radius: 20px;
            padding: 2px 7px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }
        .allergen-btn {
            font-size: 10px;
            color: #c0392b;
            background: #fff4f4;
            border: none;
            border-radius: 20px;
            padding: 2px 7px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .allergen-popup {
            display: none;
            margin-top: 4px;
            font-size: 10px;
            color: #c0392b;
            background: #fff4f4;
            border-radius: 6px;
            padding: 4px 8px;
            line-height: 1.5;
        }
        .allergen-popup.open { display: block; }
        .allergen-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .allergen-pill {
            font-size: 10px;
            color: #9f1239;
            background: rgba(244, 63, 94, .10);
            border-radius: 999px;
            padding: 3px 8px;
            font-weight: 600;
        }
        .item-desc-bullets {
            margin-top: 4px;
            padding-left: 0;
            list-style: none;
        }
        .item-desc-bullets li {
            font-size: 11px;
            color: #888;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 4px;
        }
        .item-desc-bullets li::before {
            content: '•';
            color: var(--primary);
            font-weight: 900;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── EMPTY STATE ─────────────────────────── */
        .empty-menu {
            text-align: center;
            padding: 60px 20px;
            color: #bbb;
        }
        .empty-menu i { font-size: 52px; margin-bottom: 14px; display: block; }
        .empty-menu p { font-size: 15px; font-weight: 600; }

        /* ── BOTTOM BAR ──────────────────────────── */
        .bottom-bar {
            position: fixed;
            bottom: 0; left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 540px;
            background: rgba(255,255,255,.96);
            backdrop-filter: blur(8px);
            border-top: 1px solid #eee;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 200;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary);
            color: #fff;
            border-radius: 20px;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }
        /* ── CATEGORY HOME GRID (ANA SAYFA KATEGORİLERİ) ── */
        .cat-home-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            padding: 16px 14px 40px;
        }
        .cat-home-card {
            position: relative;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            height: 140px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(0,0,0,.04);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cat-home-card:active {
            transform: scale(0.96);
        }
        .cat-home-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .cat-home-bg img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }
        .cat-home-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.30) 0%, rgba(0,0,0,0.50) 50%, rgba(0,0,0,0.85) 100%);
            z-index: 1;
        }
        .cat-home-bg .cat-home-icon {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 32px;
            color: rgba(255, 255, 255, 0.35);
            z-index: 1;
        }
        .cat-home-content {
            position: relative;
            z-index: 2;
            color: #fff;
        }
        .cat-home-title {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 4px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .cat-home-count {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            background: rgba(255,255,255,0.22);
            backdrop-filter: blur(4px);
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* ── ITEM DETAIL MODAL (ÜRÜN DETAY MODALI) ── */
        .item-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: none;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .item-modal-overlay.show {
            display: flex;
            opacity: 1;
        }
        .item-modal-box {
            background: #fff;
            width: 100%;
            max-width: 540px;
            border-radius: 24px 24px 0 0;
            overflow: hidden;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.25);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .item-modal-overlay.show .item-modal-box {
            transform: translateY(0);
        }
        .item-modal-img {
            width: 100%;
            height: 240px;
            object-fit: cover;
            background: #f1f5f9;
            position: relative;
        }
        .item-modal-img-empty {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, rgba(var(--primary-rgb),0.15), rgba(var(--primary-rgb),0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            color: var(--primary);
        }
        .item-modal-body {
            padding: 20px 20px 30px;
            overflow-y: auto;
        }
        .item-modal-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .item-modal-price {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 14px;
        }
        .item-modal-desc {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .item-modal-close {
            background: #f1f5f9;
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            color: #334155;
            cursor: pointer;
            margin-top: 10px;
        }
        .powered {
            font-size: 11px;
            color: #bbb;
            font-weight: 500;
        }
        .powered a { color: var(--primary); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>

<!-- Header -->
<div class="menu-header">
    <div class="menu-header-inner">
        <div class="header-left">
            <div class="logo-wrap">
                <?php if ($hasLogo && $logoSrc): ?>
                    <img src="<?= SecurityHelper::escape($logoSrc) ?>" alt="" class="logo-img">
                <?php else: ?>
                    <div class="logo-letter"><?= mb_strtoupper(mb_substr($business['name'], 0, 1, 'UTF-8'), 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
            <div class="biz-info">
                <div class="biz-name"><?= SecurityHelper::escape($business['name']) ?></div>
                <div class="biz-sub">
                    <span>Dijital Menü</span>
                    <?php if ($locationLabel !== ''): ?>
                    <span>• <?= SecurityHelper::escape($locationLabel) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="biz-actions">
            <a href="<?= SecurityHelper::escape($baseUrl) ?>/esnaf/<?= SecurityHelper::escape($business['slug']) ?>" class="biz-action-btn biz-action-btn--light" title="İşletme Profili">
                <i class="fa-solid fa-store"></i> <span>Profil</span>
            </a>
            <?php if ($phoneLink !== ''): ?>
            <a href="tel:<?= SecurityHelper::escape($phoneLink) ?>" class="biz-action-btn" title="Ara">
                <i class="fa-solid fa-phone"></i>
            </a>
            <?php endif; ?>
            <?php if ($whatsAppLink !== ''): ?>
            <a href="https://wa.me/<?= SecurityHelper::escape($whatsAppLink) ?>" target="_blank" rel="noopener noreferrer" class="biz-action-btn" title="WhatsApp">
                <i class="fa-brands fa-whatsapp"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($categories)): ?>
    <div class="empty-menu">
        <i class="fa-solid fa-utensils"></i>
        <p>Menü henüz hazırlanmadı.</p>
    </div>
<?php else: ?>

<!-- 1) CATEGORY HOME VIEW (KATEGORİLER ANA SAYFASI) -->
<div id="catHomeView">
    <div style="padding: 20px 16px 8px; display:flex; align-items:center; justify-content:space-between;">
        <h2 style="font-size: 18px; font-weight: 800; color: #1e293b; margin:0;"><i class="fa-solid fa-list me-2" style="color:var(--primary);"></i> Menü Kategorileri</h2>
        <span style="font-size: 12px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 20px;"><?= count($categories) ?> Kategori</span>
    </div>
    <div class="cat-home-grid">
        <?php foreach ($categories as $cat):
            $items = $itemsByCategory[$cat['id']] ?? [];
            $catImg = !empty($cat['image_path']) ? menuItemImageUrl($cat['image_path']) : null;
            $catIcon = !empty($cat['icon']) ? $cat['icon'] : 'fa-utensils';
        ?>
        <a href="#cat-<?= $cat['id'] ?>" class="cat-home-card" onclick="showCategoryView(<?= $cat['id'] ?>, event)">
            <div class="cat-home-bg">
                <?php if ($catImg): ?>
                    <img src="<?= SecurityHelper::escape($catImg) ?>" alt="<?= SecurityHelper::escape($cat['name']) ?>">
                <?php else: ?>
                    <i class="fa-solid <?= SecurityHelper::escape($catIcon) ?> cat-home-icon"></i>
                <?php endif; ?>
            </div>
            <div class="cat-home-content">
                <div class="cat-home-title"><?= SecurityHelper::escape($cat['name']) ?></div>
                <div class="cat-home-count"><?= count($items) ?> Ürün <i class="fa-solid fa-chevron-right ms-1" style="font-size:9px;"></i></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 2) CATEGORY PRODUCTS VIEW (ÜRÜNLER LİSTESİ SAYFASI) -->
<div id="catProductsView" style="display: none;">
    <!-- Sticky Category Tab Bar -->
    <div class="cat-bar">
        <div class="cat-list" id="catList">
            <a class="cat-btn" href="#" onclick="showHomeView(event)" style="color:var(--primary); font-weight:800; border-right: 1px solid #e2e8f0; margin-right: 4px;">
                <i class="fa-solid fa-arrow-left me-1"></i> Kategoriler
            </a>
            <?php foreach ($categories as $i => $cat): ?>
                <a class="cat-btn <?= $i === 0 ? 'active' : '' ?>"
                   href="#cat-<?= $cat['id'] ?>"
                   id="tab-<?= $cat['id'] ?>"
                   onclick="setActive(this, event)">
                    <?= SecurityHelper::escape($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Menu Content -->
    <div class="menu-content" id="menuContent">
        <?php foreach ($categories as $cat):
            $items = $itemsByCategory[$cat['id']] ?? [];
            $hasAnyImage = false;
            foreach ($items as $it) { if (!empty($it['image_path'])) { $hasAnyImage = true; break; } }
        ?>
        <div class="cat-section" id="cat-<?= $cat['id'] ?>">
            <div class="cat-title">
                <?= SecurityHelper::escape($cat['name']) ?>
                <?php if (!empty($items)): ?>
                    <span class="cnt"><?= count($items) ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($items)): ?>
                <p style="color:#bbb;font-size:13px;padding:4px 4px 12px;">Bu kategoride henüz ürün eklenmedi.</p>
            <?php elseif ($hasAnyImage): ?>
                <!-- Grid layout (some have images) -->
                <div class="items-grid">
                    <?php foreach ($items as $item):
                        $imgSrc = menuItemImageUrl($item['image_path'] ?? null);
                        $itemIcon = menuItemIcon($item['name']);
                        $isChef = !empty($item['is_chefs_choice']);
                        $itemBadge = $item['badge'] ?? '';
                    ?>
                    <div class="item-card" 
                         style="cursor: pointer;"
                         onclick="openItemModal(this)"
                         data-title="<?= SecurityHelper::escape($item['name']) ?>"
                         data-price="<?= number_format((float)$item['price'], 2, ',', '.') ?> ₺"
                         data-desc="<?= SecurityHelper::escape($item['description'] ?? '') ?>"
                         data-img="<?= SecurityHelper::escape($imgSrc ? $imgSrc : '') ?>"
                         data-icon="<?= SecurityHelper::escape($itemIcon) ?>"
                         data-chef="<?= $isChef ? '1' : '0' ?>"
                         data-badge="<?= SecurityHelper::escape($itemBadge) ?>"
                         data-time="<?= SecurityHelper::escape($item['cooking_time'] ?? '') ?>"
                         data-cal="<?= SecurityHelper::escape($item['calories'] ?? '') ?>"
                         data-allergens="<?= SecurityHelper::escape($item['allergens'] ?? '') ?>">
                        <div class="item-img-wrap">
                            <?php if ($imgSrc): ?>
                                <img src="<?= SecurityHelper::escape($imgSrc) ?>" alt="<?= SecurityHelper::escape($item['name']) ?>" loading="lazy" decoding="async"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="item-no-img" style="display:none;"><i class="fa-solid <?= $itemIcon ?>"></i></div>
                            <?php else: ?>
                                <div class="item-no-img"><i class="fa-solid <?= $itemIcon ?>"></i></div>
                            <?php endif; ?>
                            <div class="price-badge"><?= number_format((float)$item['price'], 2, ',', '.') ?> ₺</div>
                            <?php if ($isChef): ?>
                                <div class="item-badge chefs">👨‍🍳 Şefin Seçimi</div>
                            <?php elseif ($itemBadge): ?>
                                <div class="item-badge"><?= SecurityHelper::escape($itemBadge) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="item-body">
                            <div class="item-name"><?= SecurityHelper::escape($item['name']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <?php $lines = array_filter(array_map('trim', explode("\n", $item['description']))); ?>
                                <?php if (count($lines) > 1): ?>
                                    <ul class="item-desc-bullets">
                                        <?php foreach ($lines as $line): ?>
                                            <li><?= SecurityHelper::escape($line) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="item-desc"><?= SecurityHelper::escape($item['description']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php $hasChips = !empty($item['cooking_time']) || !empty($item['calories']) || !empty($item['allergens']); ?>
                            <?php if ($hasChips): ?>
                            <div class="meta-chips" onclick="event.stopPropagation();">
                                <?php if (!empty($item['cooking_time'])): ?>
                                    <span class="meta-chip">⏱ <?= SecurityHelper::escape($item['cooking_time']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['calories'])): ?>
                                    <span class="meta-chip">🔥 <?= SecurityHelper::escape($item['calories']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['allergens'])): ?>
                                    <?php $allergenItems = menuParseAllergenList($item['allergens']); ?>
                                    <button class="allergen-btn" onclick="this.nextElementSibling.classList.toggle('open')" type="button">
                                        ⚠️ Alerjenler
                                    </button>
                                    <div class="allergen-popup">
                                        <div class="allergen-list">
                                            <?php foreach ($allergenItems as $allergenItem): ?>
                                                <span class="allergen-pill"><?= SecurityHelper::escape($allergenItem) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- List layout (no images in this category) -->
                <div class="items-list">
                <?php foreach ($items as $item):
                    $itemIcon = menuItemIcon($item['name']);
                    $isChef2 = !empty($item['is_chefs_choice']);
                    $itemBadge2 = $item['badge'] ?? '';
                ?>
                <div class="item-card no-photo"
                     style="cursor: pointer;"
                     onclick="openItemModal(this)"
                     data-title="<?= SecurityHelper::escape($item['name']) ?>"
                     data-price="<?= number_format((float)$item['price'], 2, ',', '.') ?> ₺"
                     data-desc="<?= SecurityHelper::escape($item['description'] ?? '') ?>"
                     data-img=""
                     data-icon="<?= SecurityHelper::escape($itemIcon) ?>"
                     data-chef="<?= $isChef2 ? '1' : '0' ?>"
                     data-badge="<?= SecurityHelper::escape($itemBadge2) ?>"
                     data-time="<?= SecurityHelper::escape($item['cooking_time'] ?? '') ?>"
                     data-cal="<?= SecurityHelper::escape($item['calories'] ?? '') ?>"
                     data-allergens="<?= SecurityHelper::escape($item['allergens'] ?? '') ?>">
                    <div class="item-icon"><i class="fa-solid <?= $itemIcon ?>"></i></div>
                    <div class="item-body">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="item-name" style="margin-bottom:0;"><?= SecurityHelper::escape($item['name']) ?></div>
                            <?php if ($isChef2): ?><span style="font-size:11px;color:#888;">👨‍🍳 Şefin Seçimi</span><?php endif; ?>
                            <?php if ($itemBadge2 && !$isChef2): ?><span style="font-size:10px;font-weight:700;color:var(--primary);"><?= SecurityHelper::escape($itemBadge2) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($item['description'])): ?>
                            <?php $lines2 = array_filter(array_map('trim', explode("\n", $item['description']))); ?>
                            <?php if (count($lines2) > 1): ?>
                                <ul class="item-desc-bullets">
                                    <?php foreach ($lines2 as $line2): ?>
                                        <li><?= SecurityHelper::escape($line2) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="item-desc"><?= SecurityHelper::escape($item['description']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="item-price-text"><?= number_format((float)$item['price'], 2, ',', '.') ?> ₺</div>
                        <?php $hasChips2 = !empty($item['cooking_time']) || !empty($item['calories']) || !empty($item['allergens']); ?>
                        <?php if ($hasChips2): ?>
                        <div class="meta-chips" onclick="event.stopPropagation();">
                            <?php if (!empty($item['cooking_time'])): ?><span class="meta-chip">⏱ <?= SecurityHelper::escape($item['cooking_time']) ?></span><?php endif; ?>
                            <?php if (!empty($item['calories'])): ?><span class="meta-chip">🔥 <?= SecurityHelper::escape($item['calories']) ?></span><?php endif; ?>
                            <?php if (!empty($item['allergens'])): ?>
                                <?php $allergenItems2 = menuParseAllergenList($item['allergens']); ?>
                                <button class="allergen-btn" onclick="this.nextElementSibling.classList.toggle('open')" type="button">⚠️ Alerjenler</button>
                                <div class="allergen-popup">
                                    <div class="allergen-list">
                                        <?php foreach ($allergenItems2 as $allergenItem2): ?>
                                            <span class="allergen-pill"><?= SecurityHelper::escape($allergenItem2) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 3) ITEM DETAIL MODAL OVERLAY -->
<div class="item-modal-overlay" id="itemModalOverlay" onclick="closeItemModal(event)">
    <div class="item-modal-box" id="itemModalBox" onclick="event.stopPropagation();">
        <div id="modalImgContainer"></div>
        <div class="item-modal-body">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="item-modal-title" id="modalTitle"></div>
                <div class="item-modal-price" id="modalPrice"></div>
            </div>
            <div id="modalBadges" class="mb-3 d-flex flex-wrap gap-2"></div>
            <div class="item-modal-desc" id="modalDesc"></div>
            <div id="modalChips" class="d-flex flex-wrap gap-2 mb-3"></div>
            <button class="item-modal-close" onclick="closeItemModal()">
                <i class="fa-solid fa-xmark me-1"></i> Kapat
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Bottom Bar -->
<div class="bottom-bar">
    <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($business['slug']) ?>" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Profil
    </a>
    <span class="powered">by <a href="<?= SecurityHelper::escape($baseUrl) ?>/"><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'rehber') ?></a></span>
</div>

<script>
function showCategoryView(catId, e) {
    if (e) e.preventDefault();
    var homeView = document.getElementById('catHomeView');
    var prodView = document.getElementById('catProductsView');
    if (homeView) homeView.style.display = 'none';
    if (prodView) prodView.style.display = 'block';

    var tab = document.getElementById('tab-' + catId);
    if (tab) {
        setActive(tab);
    } else {
        var firstTab = document.querySelector('.cat-btn[id^="tab-"]');
        if (firstTab) setActive(firstTab);
    }
}

function showHomeView(e) {
    if (e) e.preventDefault();
    var homeView = document.getElementById('catHomeView');
    var prodView = document.getElementById('catProductsView');
    if (prodView) prodView.style.display = 'none';
    if (homeView) homeView.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setActive(el, e) {
    if (e) e.preventDefault();
    document.querySelectorAll('.cat-btn').forEach(function(b){ b.classList.remove('active'); });
    el.classList.add('active');

    // Scroll tab into view horizontally without breaking vertical page scroll
    var catList = document.getElementById('catList');
    if (catList) {
        var btnLeft = el.offsetLeft;
        var btnWidth = el.offsetWidth;
        var listWidth = catList.offsetWidth;
        catList.scrollTo({ left: Math.max(0, btnLeft - (listWidth / 2) + (btnWidth / 2)), behavior: 'smooth' });
    }

    var targetId = el.getAttribute('href') ? el.getAttribute('href').replace('#','') : null;
    if (targetId) {
        var section = document.getElementById(targetId);
        if (section) {
            setTimeout(function() {
                var top = section.getBoundingClientRect().top + window.scrollY - 90;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            }, 50);
        }
    }
}

function openItemModal(el) {
    var title = el.getAttribute('data-title') || '';
    var price = el.getAttribute('data-price') || '';
    var desc = el.getAttribute('data-desc') || '';
    var img = el.getAttribute('data-img') || '';
    var icon = el.getAttribute('data-icon') || 'fa-utensils';
    var chef = el.getAttribute('data-chef') === '1';
    var badge = el.getAttribute('data-badge') || '';
    var time = el.getAttribute('data-time') || '';
    var cal = el.getAttribute('data-cal') || '';
    var allergens = el.getAttribute('data-allergens') || '';

    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalPrice').textContent = price;
    document.getElementById('modalDesc').textContent = desc;
    document.getElementById('modalDesc').style.display = desc ? 'block' : 'none';

    var imgContainer = document.getElementById('modalImgContainer');
    if (img) {
        imgContainer.innerHTML = '<img src="' + img + '" class="item-modal-img" alt="">';
    } else {
        imgContainer.innerHTML = '<div class="item-modal-img-empty"><i class="fa-solid ' + icon + '"></i></div>';
    }

    var badgesHtml = '';
    if (chef) badgesHtml += '<span class="item-badge chefs" style="font-size:12px; padding:4px 10px;">👨‍🍳 Şefin Seçimi</span>';
    if (badge) badgesHtml += '<span class="item-badge" style="font-size:12px; padding:4px 10px;">' + badge + '</span>';
    document.getElementById('modalBadges').innerHTML = badgesHtml;
    document.getElementById('modalBadges').style.display = (chef || badge) ? 'flex' : 'none';

    var chipsHtml = '';
    if (time) chipsHtml += '<span class="meta-chip" style="font-size:12px; padding:6px 12px;">⏱ ' + time + '</span>';
    if (cal) chipsHtml += '<span class="meta-chip" style="font-size:12px; padding:6px 12px;">🔥 ' + cal + '</span>';
    if (allergens) {
        var list = allergens.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
        list.forEach(function(a) {
            chipsHtml += '<span class="allergen-pill" style="font-size:12px; padding:4px 10px;">⚠️ ' + a + '</span>';
        });
    }
    document.getElementById('modalChips').innerHTML = chipsHtml;
    document.getElementById('modalChips').style.display = (time || cal || allergens) ? 'flex' : 'none';

    var overlay = document.getElementById('itemModalOverlay');
    if (overlay) {
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeItemModal(e) {
    if (e && e.target && e.target.id !== 'itemModalOverlay' && !e.target.classList.contains('item-modal-close')) return;
    var overlay = document.getElementById('itemModalOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeItemModal();
});

// Highlight active tab on scroll in category view
(function() {
    var sections = document.querySelectorAll('.cat-section');
    var tabs     = document.querySelectorAll('.cat-btn[id^="tab-"]');
    if (!sections.length) return;

    window.addEventListener('scroll', function() {
        var prodView = document.getElementById('catProductsView');
        if (!prodView || prodView.style.display === 'none') return;
        var scrollY = window.scrollY + 110;
        var activeIdx = 0;
        sections.forEach(function(sec, i) {
            if (sec.offsetTop <= scrollY) activeIdx = i;
        });
        var activeTab = tabs[activeIdx];
        if (activeTab && !activeTab.classList.contains('active')) {
            tabs.forEach(function(t){ t.classList.remove('active'); });
            activeTab.classList.add('active');
            var catList = document.getElementById('catList');
            if (catList) {
                var btnLeft = activeTab.offsetLeft;
                var btnWidth = activeTab.offsetWidth;
                var listWidth = catList.offsetWidth;
                catList.scrollTo({ left: Math.max(0, btnLeft - (listWidth / 2) + (btnWidth / 2)), behavior: 'smooth' });
            }
        }
    }, { passive: true });
})();
</script>
</body>
</html>
