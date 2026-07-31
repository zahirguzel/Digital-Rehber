<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/models/Favorite.php';
require_once __DIR__ . '/models/Review.php';
require_once __DIR__ . '/includes/seo-meta.php';

Session::start();
$isLoggedIn = Session::get('user_logged_in');
$userId = Session::get('user_id');

$seoBaseUrl = seoGetBaseUrl();
$seoBaseUrlTrimmed = rtrim($seoBaseUrl, '/');
$siteUrl = function ($path = '') use ($seoBaseUrlTrimmed) {
    if ($path === '' || $path === '/') {
        return $seoBaseUrlTrimmed . '/';
    }

    return $seoBaseUrlTrimmed . '/' . ltrim($path, '/');
};

$reviewMsg = '';
$reviewErr = '';
$reviewSort   = $_GET['review_sort'] ?? 'newest';
$allowedReviewSorts = ['newest', 'oldest', 'rating_desc', 'rating_asc'];
if (!in_array($reviewSort, $allowedReviewSorts, true)) {
    $reviewSort = 'newest';
}
$reviewRating  = isset($_GET['review_rating']) ? (int) $_GET['review_rating'] : 0;
$reviewRating  = ($reviewRating >= 1 && $reviewRating <= 5) ? $reviewRating : 0;
$reviewPage    = max(1, (int) ($_GET['review_page'] ?? 1));
$reviewPerPage = 5;
$reviewTotal   = 0;
$reviewPages   = 1;

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: ' . $siteUrl('esnaflar'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*, c.name as category_name, c.slug as category_slug FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.slug = ?");
    $stmt->execute([$slug]);
    $business = $stmt->fetch();

    if (!$business) {
        header("HTTP/1.0 404 Not Found");
        header('Location: ' . $siteUrl('404'));
        exit;
    }

    $images = [];
    $stmtImg = $pdo->prepare("SELECT * FROM business_images WHERE business_id = ?");
    $stmtImg->execute([$business['id']]);
    $images = $stmtImg->fetchAll();

    $favoriteModel = new Favorite();
    $reviewModel = new Review();
    $isFavorited = false;
    
    if ($isLoggedIn) {
        $isFavorited = $favoriteModel->isFavorited($userId, $business['id']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if (!CSRFMiddleware::validate()) {
                $reviewErr = "Güvenlik doğrulaması başarısız.";
            } else {
                if ($_POST['action'] === 'toggle_favorite') {
                    $isFavorited = $favoriteModel->toggleFavorite($userId, $business['id']);
                } elseif ($_POST['action'] === 'add_review') {
                    $rating = (int)($_POST['rating'] ?? 0);
                    $comment = trim($_POST['comment'] ?? '');
                    
                    if ($rating < 1 || $rating > 5) {
                        $reviewErr = "Lütfen 1 ile 5 arası bir puan verin.";
                    } else {
                        $reviewModel->addReview($business['id'], $userId, $rating, $comment);
                        $reviewMsg = "Yorumunuz başarıyla eklendi!";
                        
                        $stmt = $pdo->prepare("SELECT b.*, c.name as category_name, c.slug as category_slug FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.slug = ?");
                        $stmt->execute([$slug]);
                        $business = $stmt->fetch();
                    }
                }
            }
        }
    }
    $reviews = $reviewModel->getBusinessReviews($business['id'], [
        'status' => 'approved',
        'sort'   => $reviewSort,
        'rating' => $reviewRating ?: null,
    ], $reviewPage, $reviewPerPage);
    $reviewTotal = $reviewModel->countBusinessReviews($business['id'], [
        'status' => 'approved',
        'rating' => $reviewRating ?: null,
    ]);
    $reviewPages = max(1, (int) ceil($reviewTotal / $reviewPerPage));
    if ($reviewPage > $reviewPages) {
        $reviewPage = $reviewPages;
    }
    
    // Fetch Gallery Images
    $images = [];
    try {
        $stmtGallery = $pdo->prepare("SELECT image_path FROM business_gallery WHERE business_id = ? ORDER BY sort_order ASC, id ASC LIMIT 6");
        $stmtGallery->execute([$business['id']]);
        $images = $stmtGallery->fetchAll();
    } catch (Exception $e) {}

    require_once __DIR__ . '/models/Business.php';
    (new Business())->recordAnalytics($business['id'], 'views');

} catch (Exception $e) {
    die("Veritabanı hatası.");
}

$sidebarAds = [];
try {
    $stmtAds = $pdo->query("SELECT * FROM advertisements WHERE active = 1 AND position = 'sidebar' ORDER BY id DESC");
    $sidebarAds = $stmtAds->fetchAll();
} catch (Exception $e) {}

if (!function_exists('bizResolveImageUrl')) {
    function bizResolveImageUrl($path) {
        if (empty($path)) {
            return '';
        }
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        if (strpos($path, '/') === 0) {
            return $path;
        }
        return seoGetBaseUrl() . '/public/images/' . ltrim($path, '/');
    }
}

$coverImage = !empty($business['cover_image_path'])
    ? bizResolveImageUrl($business['cover_image_path'])
    : 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&q=80';

$hasCustomLogo = !empty($business['logo_path']) && $business['logo_path'] !== 'default_logo.png';
$logoImage = $hasCustomLogo ? bizResolveImageUrl($business['logo_path']) : '';
$bizDetailLetter = mb_strtoupper(mb_substr($business['name'], 0, 1, 'UTF-8'), 'UTF-8');
$bizDetailColor  = !empty($business['theme_color']) ? SecurityHelper::escape($business['theme_color']) : '#1F242B';
$authRedirectTarget = 'esnaf/' . $business['slug'];
$loginUrl = $siteUrl('giris.php?redirect=' . rawurlencode($authRedirectTarget));
$reviewsUrl = $siteUrl('esnaf/' . $business['slug']);
$buildReviewUrl = static function (array $overrides = []) use ($reviewsUrl, $reviewSort, $reviewRating, $reviewPage) {
    $params = [
        'review_sort'   => ($reviewSort !== 'newest')  ? $reviewSort   : null,
        'review_rating' => ($reviewRating > 0)          ? $reviewRating : null,
        'review_page'   => ($reviewPage > 1)            ? $reviewPage   : null,
    ];
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    if (($params['review_sort']   ?? null) === 'newest') { $params['review_sort']   = null; }
    if (empty($params['review_rating'])) { $params['review_rating'] = null; }
    if (empty($params['review_page']) || (int)($params['review_page'] ?? 0) <= 1) { $params['review_page'] = null; }
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $q = http_build_query($params);
    return $reviewsUrl . ($q !== '' ? "?{$q}" : '') . '#reviews';
};
// eski alias
$buildReviewSortUrl = $buildReviewUrl;

$quickLinks = [
    [
        'label' => 'Dijital Kartvizit',
        'sublabel' => 'QR dijital kartvizit sayfası',
        'icon' => 'fa-qrcode',
        'url' => $siteUrl($business['slug']),
        'external' => false,
        'type' => 'qr',
    ],
];

$menuLink = null;
$menuIsExternal = false;
try {
    $menuCheck = $pdo->prepare("SELECT COUNT(*) FROM menu_categories WHERE business_id = ? AND is_active = 1");
    $menuCheck->execute([$business['id']]);
    if ($menuCheck->fetchColumn() > 0) {
        $menuLink = $siteUrl('menu/' . $business['slug']);
    } elseif (!empty($business['menu_url'])) {
        $menuLink = $business['menu_url'];
        $menuIsExternal = (strpos($business['menu_url'], 'http') === 0);
    }
} catch (Exception $e) {
    if (!empty($business['menu_url'])) {
        $menuLink = $business['menu_url'];
        $menuIsExternal = (strpos($business['menu_url'], 'http') === 0);
    }
}

if ($menuLink) {
    $quickLinks[] = [
        'label' => 'Dijital Menü',
        'sublabel' => 'Online menüyü inceleyin',
        'icon' => 'fa-utensils',
        'url' => $menuLink,
        'external' => $menuIsExternal || (strpos($menuLink, 'http') === 0),
        'type' => 'menu',
    ];
}

if (!empty($business['website'])) {
    $websiteUrl = $business['website'];
    if (strpos($websiteUrl, 'http') !== 0) {
        $websiteUrl = 'https://' . ltrim($websiteUrl, '/');
    }
    $quickLinks[] = [
        'label' => 'Web Sitesi',
        'sublabel' => 'Resmi siteye gidin',
        'icon' => 'fa-globe',
        'url' => $websiteUrl,
        'external' => true,
        'type' => 'website',
    ];
}

$pageTitle = $business['name'];
$regionName = seoGetRegionName();
$regionLower = mb_strtolower($regionName, 'UTF-8');
$siteTitle = seoGetSiteTitle();
$metaDescription = !empty($business['description'])
    ? seoTruncateMetaDescription($business['description'])
    : $business['name'] . ' ' . $regionName . ' ' . $business['category_name'] . ' firması, iletişim bilgileri, harita konumu ve detayları.';
$metaKeywords = $business['name'] . ', ' . $regionLower . ' ' . $business['category_name'] . ', ' . $business['district'] . ' esnafları, ' . $business['name'] . ' iletişim';
$canonicalUrl = $siteUrl('esnaf/' . $business['slug']);
$ogImage = $coverImage;
$ogImageAlt = $business['name'] . ' - ' . $regionName;
$ogType = 'website';
$schemaLocalBusiness = seoBuildLocalBusinessSchema(
    $business,
    $business['category_name'] ?? '',
    $seoBaseUrl,
    $canonicalUrl,
    array_values(array_filter([seoResolveAbsoluteUrl($coverImage, $seoBaseUrl), $logoImage ? seoResolveAbsoluteUrl($logoImage, $seoBaseUrl) : null]))
);
require_once 'includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($schemaLocalBusiness, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="biz-portal-hero" style="--biz-cover: url('<?= SecurityHelper::escape($coverImage) ?>');">
    <div class="biz-portal-hero__overlay" aria-hidden="true"></div>
    <div class="biz-portal-hero__backdrop" aria-hidden="true">
        <div class="biz-portal-hero__panel biz-portal-hero__panel--guide"></div>
        <div class="biz-portal-hero__panel biz-portal-hero__panel--media"></div>
    </div>
    <div class="container biz-portal-hero__inner">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="<?= SecurityHelper::escape($siteUrl('/')) ?>">Ana Sayfa</a>
            <span aria-hidden="true">/</span>
            <a href="<?= SecurityHelper::escape($siteUrl('esnaflar')) ?>">Esnaflar</a>
            <?php if (!empty($business['category_slug'])): ?>
                <span aria-hidden="true">/</span>
                <a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=' . urlencode($business['category_slug']))) ?>"><?= SecurityHelper::escape($business['category_name']) ?></a>
            <?php endif; ?>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current"><?= SecurityHelper::escape($business['name']) ?></span>
        </nav>
    </div>
</header>

<div class="container biz-portal-profile-wrap">
    <article class="biz-portal-profile <?= $business['is_premium'] ? 'biz-portal-profile--premium' : '' ?> reveal-on-scroll">
        <div class="biz-portal-profile__main">
            <div class="biz-portal-profile__brand">
                <?php if (!empty($logoImage)): ?>
                    <img src="<?= SecurityHelper::escape($logoImage) ?>"
                         alt="<?= SecurityHelper::escape($business['name']) ?> Logo"
                         class="biz-portal-profile__logo"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="biz-portal-profile__logo biz-portal-profile__logo--letter" style="display:none;background:<?= $bizDetailColor ?>;"><?= $bizDetailLetter ?></div>
                <?php else: ?>
                    <div class="biz-portal-profile__logo biz-portal-profile__logo--letter" style="background:<?= $bizDetailColor ?>;"><?= $bizDetailLetter ?></div>
                <?php endif; ?>

                <div class="biz-portal-profile__info">
                    <div class="biz-portal-profile__badges">
                        <a href="<?= SecurityHelper::escape($siteUrl('esnaflar?category=' . urlencode($business['category_slug']))) ?>" class="biz-portal-profile__category">
                            <?= SecurityHelper::escape($business['category_name']) ?>
                        </a>
                        <?php if ($business['is_premium']): ?>
                            <span class="badge-premium-inline"><i class="fa-solid fa-crown me-1"></i>Premium</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="biz-portal-profile__title"><?= SecurityHelper::escape($business['name']) ?></h1>
                    
                    <?php if (isset($business['review_count']) && $business['review_count'] > 0): ?>
                    <div class="mb-2 d-flex align-items-center gap-2">
                        <div class="text-warning">
                            <i class="fa-solid fa-star"></i> <span class="fw-bold"><?= number_format($business['average_rating'], 1) ?></span>
                        </div>
                        <span class="text-muted small">(<?= $business['review_count'] ?> Yorum)</span>
                    </div>
                    <?php endif; ?>
                    <p class="biz-portal-profile__location">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= SecurityHelper::escape($business['district']) ?> / <?= SecurityHelper::escape($business['city'] ?: $regionName) ?>
                        <?php if (!empty($business['address'])): ?>
                            <span class="biz-portal-profile__address"><?= SecurityHelper::escape($business['address']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="biz-portal-profile__actions">
                <form method="POST" action="" class="d-inline">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="action" value="toggle_favorite">
                    <?php if ($isLoggedIn): ?>
                        <button type="submit" class="btn btn-outline-danger biz-portal-profile__btn" title="Favorilere Ekle/Çıkar">
                            <i class="<?= $isFavorited ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                        </button>
                    <?php else: ?>
                        <a href="<?= SecurityHelper::escape($loginUrl) ?>" class="btn btn-outline-danger biz-portal-profile__btn" title="Favoriye eklemek için giriş yapın">
                            <i class="fa-regular fa-heart"></i>
                        </a>
                    <?php endif; ?>
                </form>
                
                <?php if (!empty($business['phone'])): ?>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $business['phone']) ?>" class="btn btn-primary biz-portal-profile__btn">
                        <i class="fa-solid fa-phone"></i> �?imdi Ara
                    </a>
                <?php endif; ?>
                <?php if (!empty($business['whatsapp'])): ?>
                    <a href="https://wa.me/<?= SecurityHelper::escape(preg_replace('/[^0-9]/', '', $business['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp biz-portal-profile__btn">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="biz-portal-profile__quick">
            <span class="biz-portal-profile__quick-label">Hızlı Erişim</span>
            <div class="biz-portal-quick-chips">
                <?php foreach ($quickLinks as $link): ?>
                    <a href="<?= SecurityHelper::escape($link['url']) ?>"
                       class="biz-portal-quick-chip biz-portal-quick-chip--<?= SecurityHelper::escape($link['type']) ?>"
                       <?php if ($link['external']): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                        <i class="fa-solid <?= SecurityHelper::escape($link['icon']) ?>"></i>
                        <?= SecurityHelper::escape($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
</div>

<section class="portal-section portal-section--muted biz-portal-main">
    <div class="container">
        <div class="biz-portal-layout">

            <div class="biz-portal-content">
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">01</span>
                        <div>
                            <span class="portal-section__eyebrow">Profil</span>
                            <h2>İşletme Hakkında</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <?php if (!empty($business['description'])): ?>
                            <div class="biz-portal-about"><?= nl2br(SecurityHelper::escape($business['description'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Bu işletme için henüz açıklama eklenmemiş.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <?php
                $contentSectionNum = 2;
                if (!empty($business['google_maps_iframe'])):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Konum</span>
                            <h2>Harita & Ulaşım</h2>
                            <?php if (!empty($business['address'])): ?>
                                <p class="biz-portal-map-intro mb-0"><i class="fa-solid fa-location-dot"></i> <?= SecurityHelper::escape($business['address']) ?></p>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="biz-portal-map biz-portal-map--main">
                        <?= $business['google_maps_iframe'] ?>
                    </div>
                    <div class="biz-portal-map-actions">
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode(trim($business['name'] . ' ' . ($business['address'] ?? ''))) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary fw-semibold">
                            <i class="fa-solid fa-diamond-turn-right me-2"></i> Yol Tarifi Al
                        </a>
                        <?php if (!empty($business['district'])): ?>
                            <a href="<?= SecurityHelper::escape($siteUrl('esnaflar?district=' . urlencode($business['district']))) ?>" class="btn btn-outline-primary fw-semibold">
                                <i class="fa-solid fa-compass me-2"></i> <?= SecurityHelper::escape($business['district']) ?> Esnafları
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php
                $contentSectionNum++;
                endif;

                if (!empty($images)):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Galeri</span>
                            <h2>Fotoğraf Galerisi</h2>
                        </div>
                    </header>
                    <div class="biz-portal-gallery">
                        <?php foreach ($images as $img):
                            $galleryUrl = seoGetBaseUrl() . '/public/images/gallery/' . $business['id'] . '/' . $img['image_path'];
                        ?>
                        <a href="<?= SecurityHelper::escape($galleryUrl) ?>" class="biz-portal-gallery__item gallery-lightbox-trigger" data-img-src="<?= SecurityHelper::escape($galleryUrl) ?>">
                            <img src="<?= SecurityHelper::escape($galleryUrl) ?>" alt="<?= SecurityHelper::escape($business['name']) ?> galeri görseli" loading="lazy" decoding="async">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endif; ?>
                <!-- Reviews Section -->
                <article class="biz-portal-panel reveal-on-scroll mt-4" id="reviews">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum++, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Değerlendirmeler</span>
                            <h2>Müşteri Yorumları</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        
                        <?php if ($reviewMsg): ?>
                            <div class="alert alert-success border-0 small"><i class="fa-solid fa-check me-2"></i> <?= htmlspecialchars($reviewMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($reviewErr): ?>
                            <div class="alert alert-danger border-0 small"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($reviewErr) ?></div>
                        <?php endif; ?>
                        
                        <!-- Write Review Form -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-body p-4">
                                <?php if ($isLoggedIn): ?>
                                    <h5 class="fw-bold mb-3">Yorum Yapın</h5>
                                    <form method="POST" action="#reviews">
                                        <?= CSRFMiddleware::field() ?>
                                        <input type="hidden" name="action" value="add_review">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small">Puanınız</label>
                                            <select name="rating" class="form-select border-0 shadow-sm" required style="max-width: 150px;">
                                                <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                                                <option value="4">⭐⭐⭐⭐ (4/5)</option>
                                                <option value="3">⭐⭐⭐ (3/5)</option>
                                                <option value="2">⭐⭐ (2/5)</option>
                                                <option value="1">⭐ (1/5)</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small">Yorumunuz</label>
                                            <textarea name="comment" class="form-control border-0 shadow-sm" rows="3" placeholder="İşletme hakkında deneyimlerinizi paylaşın..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary fw-semibold px-4">Yorumu Gönder</button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-3">Yorum yapabilmek için giriş yapmalısınız.</p>
                                        <a href="<?= SecurityHelper::escape($loginUrl) ?>" class="btn btn-outline-primary fw-semibold">Giriş Yap / Kayıt Ol</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Review Toolbar -->
                        <?php
                        $sortLabels = [
                            'newest'      => 'Yeniden Eskiye',
                            'oldest'      => 'Eskiden Yeniye',
                            'rating_desc' => 'Azalan Puan',
                            'rating_asc'  => 'Artan Puan',
                        ];
                        $isFiltered = ($reviewSort !== 'newest' || $reviewRating > 0);
                        ?>
                        <div class="rev-toolbar mb-4">
                            <!-- Başlık satırı: yorum sayısı + aktif etiket -->
                            <div class="rev-toolbar__top">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold" style="font-size:15px;">
                                        <?= (int)$reviewTotal ?> yorum
                                    </span>
                                    <?php if ($isFiltered): ?>
                                        <span class="rev-active-tag">
                                            <?php if ($reviewRating > 0): ?>
                                                <i class="fa-solid fa-star" style="font-size:11px;"></i>
                                                <?= $reviewRating ?> yıldız
                                            <?php endif; ?>
                                            <?php if ($reviewSort !== 'newest'): ?>
                                                <?php if ($reviewRating > 0): ?>&middot;<?php endif; ?>
                                                <?= htmlspecialchars($sortLabels[$reviewSort]) ?>
                                            <?php endif; ?>
                                            <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_sort' => null, 'review_rating' => null, 'review_page' => null])) ?>"
                                               class="rev-active-tag__clear" title="Filtreyi Temizle">
                                                <i class="fa-solid fa-xmark"></i>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <!-- Sıralama dropdown -->
                                <div class="dropdown">
                                    <button class="rev-sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-arrows-up-down" style="font-size:12px;"></i>
                                        <?= htmlspecialchars($sortLabels[$reviewSort]) ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:200px;">
                                        <?php foreach ($sortLabels as $sv => $sl): ?>
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center justify-content-between py-2 px-3 <?= $reviewSort === $sv && $reviewRating === 0 ? '' : '' ?>"
                                                   href="<?= SecurityHelper::escape($buildReviewUrl(['review_sort' => $sv, 'review_page' => null])) ?>"
                                                   style="font-size:14px;">
                                                    <?= htmlspecialchars($sl) ?>
                                                    <?php if ($reviewSort === $sv): ?>
                                                        <i class="fa-solid fa-check text-primary ms-2" style="font-size:12px;"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <!-- Hızlı yıldız filtre chip'leri -->
                            <div class="rev-chips">
                                <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_rating' => null, 'review_page' => null])) ?>"
                                   class="rev-chip <?= $reviewRating === 0 ? 'rev-chip--active' : '' ?>">
                                    Tümü <span><?= (int)$reviewTotal ?></span>
                                </a>
                                <?php foreach ([5,4,3,2,1] as $s):
                                    $sc = $reviewModel->countBusinessReviews($business['id'], ['status' => 'approved', 'rating' => $s]);
                                    if ($sc === 0) continue;
                                ?>
                                <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_rating' => $s, 'review_page' => null])) ?>"
                                   class="rev-chip <?= $reviewRating === $s ? 'rev-chip--active' : '' ?>">
                                    <?= $s ?> <i class="fa-solid fa-star" style="font-size:10px;vertical-align:middle;"></i>
                                    <span><?= $sc ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Review List -->
                        <div class="rev-list">
                            <?php if (empty($reviews)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-regular fa-comment-dots fs-2 mb-3 d-block opacity-40"></i>
                                    <p class="mb-0" style="font-size:15px;">Seçilen filtreye uygun yorum bulunamadı.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($reviews as $rev):
                                    $revName   = SecurityHelper::escape($rev['user_name'] ?? 'Ziyaretçi');
                                    $revLetter = mb_strtoupper(mb_substr($revName, 0, 1, 'UTF-8'), 'UTF-8');
                                    $revRating = (int)$rev['rating'];
                                ?>
                                <div class="rev-item">
                                    <div class="rev-item__avatar"><?= $revLetter ?></div>
                                    <div class="rev-item__body">
                                        <div class="rev-item__meta">
                                            <span class="rev-item__name"><?= $revName ?></span>
                                            <span class="rev-item__date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></span>
                                        </div>
                                        <div class="rev-item__stars">
                                            <?php for ($si = 1; $si <= 5; $si++): ?>
                                                <i class="fa-<?= $si <= $revRating ? 'solid' : 'regular' ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="rev-item__score"><?= $revRating ?>/5</span>
                                        </div>
                                        <?php if (!empty($rev['comment'])): ?>
                                            <p class="rev-item__text"><?= nl2br(SecurityHelper::escape($rev['comment'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Sayfalama -->
                        <?php if ($reviewPages > 1): ?>
                        <nav class="rev-pagination" aria-label="Yorum sayfalama">
                            <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_page' => max(1,$reviewPage-1)])) ?>"
                               class="rev-page-btn <?= $reviewPage <= 1 ? 'rev-page-btn--disabled' : '' ?>"
                               aria-disabled="<?= $reviewPage <= 1 ? 'true' : 'false' ?>">
                                <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i>
                            </a>
                            <?php for ($pn = 1; $pn <= $reviewPages; $pn++): ?>
                                <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_page' => $pn])) ?>"
                                   class="rev-page-btn <?= $pn === $reviewPage ? 'rev-page-btn--active' : '' ?>">
                                    <?= $pn ?>
                                </a>
                            <?php endfor; ?>
                            <a href="<?= SecurityHelper::escape($buildReviewUrl(['review_page' => min($reviewPages,$reviewPage+1)])) ?>"
                               class="rev-page-btn <?= $reviewPage >= $reviewPages ? 'rev-page-btn--disabled' : '' ?>"
                               aria-disabled="<?= $reviewPage >= $reviewPages ? 'true' : 'false' ?>">
                                <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
                            </a>
                        </nav>
                        <?php endif; ?>
                    </div>
                </article>

            </div>

            <aside class="biz-portal-sidebar reveal-on-scroll">
                <div class="biz-portal-widget biz-portal-widget--desktop-only">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">E</span>
                        <div>
                            <span class="portal-section__eyebrow">Erişim</span>
                            <h3>Hızlı Bağlantılar</h3>
                        </div>
                    </header>
                    <div class="biz-portal-quick-tiles">
                        <?php foreach ($quickLinks as $link): ?>
                        <a href="<?= SecurityHelper::escape($link['url']) ?>"
                           class="biz-portal-quick-tile biz-portal-quick-tile--<?= SecurityHelper::escape($link['type']) ?>"
                           <?php if ($link['external']): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                            <span class="biz-portal-quick-tile__icon"><i class="fa-solid <?= SecurityHelper::escape($link['icon']) ?>"></i></span>
                            <span class="biz-portal-quick-tile__text">
                                <strong><?= SecurityHelper::escape($link['label']) ?></strong>
                                <small><?= SecurityHelper::escape($link['sublabel']) ?></small>
                            </span>
                            <i class="fa-solid fa-arrow-right biz-portal-quick-tile__arrow"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">İ</span>
                        <div>
                            <span class="portal-section__eyebrow">İletişim</span>
                            <h3>İletişim Bilgileri</h3>
                        </div>
                    </header>
                    <ul class="biz-portal-contact">
                        <?php if (!empty($business['phone'])): ?>
                        <li>
                            <span class="biz-portal-contact__icon"><i class="fa-solid fa-phone"></i></span>
                            <span class="biz-portal-contact__body">
                                <small>Telefon</small>
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $business['phone']) ?>"><?= SecurityHelper::escape($business['phone']) ?></a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <?php if (!empty($business['whatsapp'])): ?>
                        <li>
                            <span class="biz-portal-contact__icon biz-portal-contact__icon--wa"><i class="fa-brands fa-whatsapp"></i></span>
                            <span class="biz-portal-contact__body">
                                <small>WhatsApp</small>
                                <a href="https://wa.me/<?= SecurityHelper::escape(preg_replace('/[^0-9]/', '', $business['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp'tan Ulaşın</a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <?php if (!empty($business['website'])):
                            $websiteDisplay = $business['website'];
                            if (strpos($websiteDisplay, 'http') !== 0) {
                                $websiteDisplay = 'https://' . ltrim($websiteDisplay, '/');
                            }
                        ?>
                        <li>
                            <span class="biz-portal-contact__icon"><i class="fa-solid fa-globe"></i></span>
                            <span class="biz-portal-contact__body">
                                <small>Web Sitesi</small>
                                <a href="<?= SecurityHelper::escape($websiteDisplay) ?>" target="_blank" rel="noopener noreferrer">Siteyi Ziyaret Et</a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <?php if (!empty($business['address'])): ?>
                        <li>
                            <span class="biz-portal-contact__icon biz-portal-contact__icon--muted"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="biz-portal-contact__body">
                                <small>Adres</small>
                                <span><?= SecurityHelper::escape($business['address']) ?></span>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if (!empty($sidebarAds)): ?>
                <div class="directory-portal-ad">
                    <span class="directory-portal-ad__label">Sponsorlu Bağlantı</span>
                    <?php foreach ($sidebarAds as $ad):
                        $adImg = (strpos($ad['image_path'], 'http') === 0) ? $ad['image_path'] : 'public/images/' . ltrim($ad['image_path'], '/');
                    ?>
                        <a href="<?= SecurityHelper::escape($ad['target_url'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" class="directory-portal-ad__link">
                            <img src="<?= SecurityHelper::escape($adImg) ?>" alt="<?= SecurityHelper::escape($ad['title'] ?: ($siteTitle . ' Reklam')) ?>" loading="lazy" decoding="async">
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="directory-portal-ad directory-portal-ad--empty mt-4">
                    <span class="directory-portal-ad__label">Reklam Alanı</span>
                    <a href="<?= seoResolveAbsoluteUrl('iletisim', $seoBaseUrl) ?>" class="directory-portal-ad__link d-flex flex-column align-items-center justify-content-center text-center p-4 shadow-sm" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b; text-decoration: none; min-height: 200px; transition: all 0.3s;">
                        <i class="fa-solid fa-rectangle-ad fs-1 mb-2 text-danger" style="opacity: 0.85;"></i>
                        <strong class="text-dark d-block mb-1 fs-6">Buraya Reklam Verebilirsiniz</strong>
                        <small class="text-muted">İşletmenizi burada öne çıkarmak ve detaylı bilgi almak için tıklayın</small>
                    </a>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<!-- İşletmeni Eklet CTA Banner -->
<section class="portal-section py-2">
    <div class="container">
        <div class="home-vip-cta reveal-on-scroll">
            <div class="home-vip-cta__content">
                <div class="home-vip-cta__badge">
                    <i class="fa-solid fa-rocket me-1"></i> Esnaflarımıza Özel Fırsat
                </div>
                <h2 class="home-vip-cta__title">Aramıza Katılın, İşletmenizi Binlerce Kişiye Ulaştırın!</h2>
                <p class="home-vip-cta__desc">Kıbrıs'ın en büyük dijital rehberinde yerinizi alın, dijital dünyada fark yaratın ve yeni müşteriler kazanın.</p>
            </div>
            <div class="home-vip-cta__actions">
                <a href="<?= seoResolveAbsoluteUrl('isletme/login.php', $seoBaseUrl) ?>" class="home-vip-cta__btn-primary" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-store"></i> Hemen İşletmeni Ekle
                </a>
                <a href="<?= seoResolveAbsoluteUrl('iletisim', $seoBaseUrl) ?>" class="home-vip-cta__btn-secondary">
                    <i class="fa-brands fa-whatsapp"></i> Bilgi Al
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Gallery Lightbox Modal -->
<div class="modal fade" id="galleryLightboxModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn btn-dark rounded-circle text-white shadow-lg d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; opacity:0.9;" data-bs-dismiss="modal" aria-label="Kapat">
                    <i class="fa-solid fa-xmark fs-5"></i>
                </button>
            </div>
            <div class="modal-body p-0 text-center position-relative d-flex align-items-center justify-content-center" style="min-height: 450px;">
                <button type="button" id="lightboxPrev" class="btn btn-dark rounded-circle position-absolute start-0 ms-2 text-white shadow" style="width: 48px; height: 48px; z-index:5; opacity: 0.85;">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <img id="lightboxModalImage" src="" class="img-fluid rounded-3 shadow-lg" style="max-height: 85vh; object-fit: contain; transition: transform 0.25s ease;" alt="Galeri Görseli">
                <button type="button" id="lightboxNext" class="btn btn-dark rounded-circle position-absolute end-0 me-2 text-white shadow" style="width: 48px; height: 48px; z-index:5; opacity: 0.85;">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const galleryLinks = Array.from(document.querySelectorAll('.gallery-lightbox-trigger'));
    if (galleryLinks.length > 0) {
        const modalEl = document.getElementById('galleryLightboxModal');
        const modalImg = document.getElementById('lightboxModalImage');
        const btnPrev = document.getElementById('lightboxPrev');
        const btnNext = document.getElementById('lightboxNext');
        let currentIndex = 0;

        function showImage(index) {
            if (index < 0) index = galleryLinks.length - 1;
            if (index >= galleryLinks.length) index = 0;
            currentIndex = index;
            const src = galleryLinks[currentIndex].getAttribute('data-img-src');
            modalImg.src = src;
        }

        galleryLinks.forEach((link, idx) => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                showImage(idx);
                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            });
        });

        btnPrev.addEventListener('click', function() { showImage(currentIndex - 1); });
        btnNext.addEventListener('click', function() { showImage(currentIndex + 1); });

        document.addEventListener('keydown', function(e) {
            if (modalEl.classList.contains('show')) {
                if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
                if (e.key === 'ArrowRight') showImage(currentIndex + 1);
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
