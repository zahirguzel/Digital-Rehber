<?php
require_once 'config/db.php';
require_once __DIR__ . '/models/Review.php';
require_once 'includes/influencer-helpers.php';
require_once 'includes/event-helpers.php';
require_once 'includes/campaign-helpers.php';
require_once 'includes/seo-meta.php';
require_once __DIR__ . '/includes/blog-helpers.php';
require_once 'includes/district-helpers.php';
$regionName = seoGetRegionName();
$siteTitle = seoGetSiteTitle();
$regionLower = mb_strtolower($regionName, 'UTF-8');
$regionLower = mb_strtolower($regionName, 'UTF-8');

// Fetch Categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch Premium Businesses (homepage rotator)
$premiumBusinesses = [];
try {
    $stmt = $pdo->query("SELECT b.*, c.name as category_name, c.slug as category_slug FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.is_premium = 1 AND b.is_deleted = 0 ORDER BY RAND() LIMIT 12");
    $premiumBusinesses = $stmt->fetchAll();
} catch (Exception $e) {}

// Platform stats (homepage)
$statBusinesses = 0;
$statDistricts = 0;
$statCategories = 0;
try {
    $statBusinesses = (int) $pdo->query("SELECT COUNT(*) FROM businesses WHERE is_deleted = 0")->fetchColumn();
    $statDistricts = (int) $pdo->query("SELECT COUNT(DISTINCT district) FROM businesses WHERE district IS NOT NULL AND district != '' AND is_deleted = 0")->fetchColumn();
    $statCategories = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch (Exception $e) {}

// Hero Carousel Slides
$heroSlides = [];
try {
    $heroSlides = $pdo->query("SELECT * FROM hero_slides WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 5")->fetchAll();
} catch (Exception $e) {}
// Fallback: en az 1 slide olsun
if (empty($heroSlides)) {
    $heroSlides = [[
        'id' => 0,
        'title' => 'Kıbrıs\'ın Dijital İşletme & Esnaf Rehberi',
        'subtitle' => 'ğŸ“ Kıbrıs Bölgesinin Dijital Rehberi',
        'description' => 'Avukat, oto servis, güzellik merkezi ve daha fazlasını tek tıkla bulun.',
        'image_path' => rtrim(seoGetBaseUrl(), '/') . '/public/images/hero-slider.jpg',
        'button_text' => 'İşletme Ara',
        'button_url' => '/esnaflar',
        'button2_text' => 'İşletmeni Eklet',
        'button2_url' => '/isletme',
    ]];
}


if (!function_exists('formatHomeStat')) {
    function formatHomeStat($n) {
        $n = (int) $n;
        if ($n >= 1000) {
            return number_format($n, 0, ',', '.') . '+';
        }
        return (string) $n;
    }
}

// Latest blog posts for homepage strip
$latestBlogs = [];
try {
    $latestBlogs = $pdo->query('SELECT ' . blogListSelectSql() . ' FROM blogs ORDER BY created_at DESC LIMIT 3')->fetchAll();
} catch (Exception $e) {}

// Featured influencers for homepage
$featuredInfluencers = [];
try {
    $featuredInfluencers = $pdo->query("SELECT * FROM influencers WHERE is_published = 1 AND consent_given = 1 ORDER BY is_premium DESC, is_verified DESC, name ASC LIMIT 4")->fetchAll();
} catch (Exception $e) {}

// Upcoming featured events for homepage
$featuredEvents = [];
try {
    $featuredEvents = $pdo->query("SELECT * FROM events WHERE is_published = 1 AND COALESCE(end_date, start_date) >= CURDATE() ORDER BY is_featured DESC, start_date ASC LIMIT 4")->fetchAll();
} catch (Exception $e) {}

// Featured campaigns for homepage
$featuredCampaigns = [];
try {
    $featuredCampaigns = $pdo->query("SELECT c.*, b.name AS business_name, b.slug AS business_slug FROM campaigns c LEFT JOIN businesses b ON b.id = c.business_id WHERE c.is_published = 1 AND COALESCE(c.end_date, c.start_date) >= CURDATE() ORDER BY c.is_featured DESC, c.created_at DESC LIMIT 4")->fetchAll();
} catch (Exception $e) {}

// List of Popular Districts for Search Console
$districts = seoGetSehirDistricts();
$defaultCity = seoGetDefaultCity();
$baseUrl = seoGetBaseUrl();
$homeSettings = seoGetSiteSettingsRow();
$homeReviewsSortAllowed = ['newest', 'oldest', 'rating_desc', 'rating_asc'];
$homeReviewsSort = in_array($_GET['review_sort'] ?? '', $homeReviewsSortAllowed, true)
    ? $_GET['review_sort']
    : 'newest';
$homeReviewsRating = isset($_GET['review_rating']) ? (int) $_GET['review_rating'] : 0;
$homeReviewsRating = ($homeReviewsRating >= 1 && $homeReviewsRating <= 5) ? $homeReviewsRating : 0;
$homeReviewsPage = max(1, (int) ($_GET['review_page'] ?? 1));
$homeReviewsPerPage = 6;
$homeReviews = [];
$homeReviewsTotal = 0;
$homeReviewsPageCount = 1;
$reviewModel = new Review();
$districtCounts = [];
try {
    $districtRows = $pdo->query("SELECT district, COUNT(*) AS cnt FROM businesses WHERE district IS NOT NULL AND district != '' AND is_deleted = 0 GROUP BY district")->fetchAll();
    foreach ($districtRows as $dr) {
        $districtCounts[$dr['district']] = (int) $dr['cnt'];
    }
} catch (Exception $e) {}

// Fetch Active Home Banners
$homeBanners = [];
try {
    $stmt = $pdo->query("SELECT * FROM advertisements WHERE active = 1 AND position = 'home_banner' ORDER BY id DESC");
    $homeBanners = $stmt->fetchAll();
} catch (Exception $e) {}

try {
    $homeReviewFilters = [
        'sort' => $homeReviewsSort,
        'rating' => $homeReviewsRating ?: null,
    ];
    $homeReviewsTotal = $reviewModel->countHomepageReviews($homeReviewFilters);
    $homeReviewsPageCount = max(1, (int) ceil($homeReviewsTotal / $homeReviewsPerPage));
    if ($homeReviewsPage > $homeReviewsPageCount) {
        $homeReviewsPage = $homeReviewsPageCount;
    }
    $homeReviews = $reviewModel->getHomepageReviews($homeReviewFilters, $homeReviewsPage, $homeReviewsPerPage);
} catch (Exception $e) {}

require_once __DIR__ . '/includes/seo-meta.php';
$resolveHomeText = static function ($value, $fallback, array $genericValues = []) {
    $value = trim((string) $value);
    if ($value === '' || in_array($value, $genericValues, true)) {
        return $fallback;
    }

    return $value;
};
$homeHeroSubtitle = $resolveHomeText($homeSettings['home_hero_subtitle'] ?? '', ' ' . $regionName . ' BÖLGESİNİN DİJİTAL İşLETME & ESNAF REHBERİ', ['Esnaf & İşletmelere Dijital Çözüm']);
$homeHeroTitle = $resolveHomeText($homeSettings['home_hero_title'] ?? '', 'Aradığınız Tüm İşletme, Esnaf ve Hizmetler <span style="color:var(--primary);">Tek Tıkla</span> Karşınızda', ['QR Kod ve Dijital Kartvizitiniz <em>Görünsün mü?</em>', 'QR Kod ve Dijital Kartvizitiniz Görünsün mü?']);
$homeHeroDescription = $resolveHomeText($homeSettings['home_hero_description'] ?? '', 'Avukat, oto servis, güzellik merkezi, diş hekimi, emlakçı veya aradığınız herhangi bir işletmeyi anında bulun; açık adres, telefon, WhatsApp ve yol tarifi bilgilerine saniyeler içinde ulaşın.', [
    $siteTitle . ' ile işletmenizi dijital vitrine taşıyın. Müşterileriniz QR kodu okutarak menünüze, telefonunuza, WhatsApp ve konumunuza saniyeler içinde ulaşsın.'
]);
$homeHeroPrimaryText = $resolveHomeText($homeSettings['home_hero_primary_text'] ?? '', 'İşletmeni Eklet', ['İşletmemi Ekle', 'İşletmemi Eklet']);
$homeHeroPrimaryUrl = !empty($homeSettings['home_hero_primary_url']) ? seoResolveAbsoluteUrl($homeSettings['home_hero_primary_url'], $baseUrl) : (rtrim($baseUrl, '/') . '/iletisim?subject=' . urlencode('Yeni İşletme Kaydı'));
$homeHeroSecondaryText = $resolveHomeText($homeSettings['home_hero_secondary_text'] ?? '', 'Hizmetleri İncele');
$homeHeroSecondaryUrl = !empty($homeSettings['home_hero_secondary_url']) ? seoResolveAbsoluteUrl($homeSettings['home_hero_secondary_url'], $baseUrl) : (rtrim($baseUrl, '/') . '/hizmetlerimiz');
$homeHeroConsumerText = $resolveHomeText($homeSettings['home_hero_consumer_text'] ?? '', $regionName . ' bölgesindeki işletmeleri arıyorsanız', ['Bölgedeki işletmeleri arıyorsanız']);
$homeHeroConsumerLinkText = $resolveHomeText($homeSettings['home_hero_consumer_link_text'] ?? '', 'rehberde keşfedin');
$homeSearchLabel = $resolveHomeText($homeSettings['home_search_label'] ?? '', $regionName . ' Rehber Arama', ['şehir Rehber Arama']);
$homeServicesTitle = $resolveHomeText($homeSettings['home_services_title'] ?? '', 'İşletmenizi Dijital Dünyada Büyütelim');
$homeServicesDesc = $resolveHomeText($homeSettings['home_services_desc'] ?? '', 'Google Harita kaydından QR menüye, sosyal medyadan premium vitrine — uçtan uca dijital çözümler sunuyoruz.');
$homeInfluencerTitle = $resolveHomeText($homeSettings['home_influencer_title'] ?? '', $regionName . ' İçerik Üreticileri', ['şehir İçerik Üreticileri']);
$homeInfluencerDesc = $resolveHomeText($homeSettings['home_influencer_desc'] ?? '', 'Doğrulanmış profiller Â· Manuel takipçi onayı Â· KVKK uyumlu');
$homeEventsTitle = $resolveHomeText($homeSettings['home_events_title'] ?? '', 'Yaklaşan ' . $regionName . ' Etkinlikleri', ['Yaklaşan şehir Etkinlikleri']);
$homeEventsDesc = $resolveHomeText($homeSettings['home_events_desc'] ?? '', 'Konser, festival, spor ve kültür programları');
$homeBlogTitle = $resolveHomeText($homeSettings['home_blog_title'] ?? '', $regionName . ' Rehberi Yazıları', ['şehir Rehberi Yazıları']);
$homeBlogDesc = $resolveHomeText($homeSettings['home_blog_desc'] ?? '', '');
$homeBannerFallbackTitle = $resolveHomeText($homeSettings['home_banner_fallback_title'] ?? '', 'Buraya Reklam Verebilirsiniz');
$homeBannerFallbackDescription = $resolveHomeText($homeSettings['home_banner_fallback_description'] ?? '', $regionName . ' bölgesinin dijital rehberinde yerinizi almak ve detaylı bilgi için tıklayın.', ['Bölgenin dijital rehberinde yerinizi almak ve detaylı bilgi için tıklayın.']);
$homeHeroVideo = $homeSettings['home_hero_video'] ?? '';
$homeHeroPoster = $homeSettings['home_hero_poster'] ?? '';
$homeHeroVideoUrl = $homeHeroVideo !== '' ? ((strpos($homeHeroVideo, 'http') === 0) ? $homeHeroVideo : seoResolveAbsoluteUrl('public/images/' . ltrim($homeHeroVideo, '/'), $baseUrl)) : seoResolveAbsoluteUrl('public/images/hatahrehbermedyatanitim.mp4', $baseUrl);
$homeHeroPosterUrl = $homeHeroPoster !== '' ? ((strpos($homeHeroPoster, 'http') === 0) ? $homeHeroPoster : seoResolveAbsoluteUrl('public/images/' . ltrim($homeHeroPoster, '/'), $baseUrl)) : seoResolveAbsoluteUrl('public/images/hatahrehbermedyatanitim-poster.jpg', $baseUrl);
$metaDescription = $regionName . ' esnaf ve işletme rehberi: tüm ilçelerdeki firmaların telefon, adres, WhatsApp ve harita bilgileri. Google Harita, QR menü ve dijital medya hizmetleri.';
$metaKeywords = $regionLower . ' esnaf, ' . $regionLower . ' işletmeler, ' . $regionLower . ' etkinlik, yerel işletme rehberi';
$canonicalUrl = $baseUrl . '/';
$homeReviewsBaseUrl = rtrim($baseUrl, '/') . '/';
$buildHomeReviewsUrl = static function (array $overrides = []) use ($homeReviewsSort, $homeReviewsRating, $homeReviewsPage, $homeReviewsBaseUrl) {
    $params = [
        'review_sort' => $homeReviewsSort !== 'newest' ? $homeReviewsSort : null,
        'review_rating' => $homeReviewsRating > 0 ? $homeReviewsRating : null,
        'review_page' => $homeReviewsPage > 1 ? $homeReviewsPage : null,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    if (($params['review_sort'] ?? null) === 'newest') {
        $params['review_sort'] = null;
    }
    if (empty($params['review_rating'])) {
        $params['review_rating'] = null;
    }
    if (empty($params['review_page']) || (int) $params['review_page'] <= 1) {
        $params['review_page'] = null;
    }

    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });

    $query = http_build_query($params);
    return $homeReviewsBaseUrl . ($query !== '' ? ('?' . $query) : '') . '#anasayfa-yorumlar';
};

require_once 'includes/header.php';
?>


<!-- ===== HERO CAROUSEL (Bootstrap 5, 3 Otomatik Geçişli Slayt) ===== -->
<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">

    <!-- Indicators (Alt Noktalar) -->
    <div class="carousel-indicators">
        <?php foreach ($heroSlides as $i => $slide): ?>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>"
            class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Slayt <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
    </div>

    <!-- Slides -->
    <div class="carousel-inner" style="min-height: 100vh;">
        <?php foreach ($heroSlides as $i => $slide): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>" style="min-height: 100vh;">
            <?php 
                $cleanPath = str_replace('/digitalrehber/', '/', $slide['image_path']);
                $resolvedHeroBg = (strpos($cleanPath, 'http') === 0) ? $cleanPath : seoResolveAbsoluteUrl(ltrim($cleanPath, '/'), $baseUrl);
            ?>
            <div class="hero-carousel-bg" style="background-image: url('<?= htmlspecialchars($resolvedHeroBg) ?>');"></div>
            <!-- Karanlık Overlay -->
            <div class="hero-carousel-overlay"></div>

            <!-- Slide İçeriği -->
            <div class="carousel-caption-custom d-flex align-items-start justify-content-center flex-column">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-12 col-lg-10 text-center">

                            <?php if (!empty($slide['subtitle'])): ?>
                            <span class="badge bg-danger text-white px-3 py-2 rounded-pill fw-semibold mb-3 shadow-sm d-inline-flex align-items-center gap-2"
                                style="font-size: 13px; letter-spacing: 0.5px;">
                                <?= htmlspecialchars($slide['subtitle']) ?>
                            </span>
                            <?php endif; ?>

                            <h1 class="text-white fw-bold mb-2 hero-carousel-title">
                                <?= htmlspecialchars($slide['title']) ?>
                            </h1>

                            <?php if (!empty($slide['description'])): ?>
                            <p class="text-white hero-carousel-desc mb-3">
                                <?= htmlspecialchars($slide['description']) ?>
                            </p>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 text-white small fw-semibold mb-4">
                                <span class="d-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded-pill"><i class="fa-solid fa-circle-check text-success"></i> <span>Doğrulanmış İşletmeler</span></span>
                                <span class="d-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded-pill"><i class="fa-solid fa-location-dot text-danger"></i> <span>Konum & Yol Tarifi</span></span>
                                <span class="d-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded-pill"><i class="fa-solid fa-phone text-info"></i> <span>Tek Tıkla Ara</span></span>
                            </div>

                            <!-- Arama Kutusu (her slayta gömülü) -->
                            <div class="welcome-hero-search-box mx-auto mb-4">
                                <form action="<?= seoResolveAbsoluteUrl('esnaflar', $baseUrl) ?>" method="GET" class="card border-0 shadow-lg p-2 p-md-3">
                                    <?php if (!empty($defaultCity)): ?>
                                    <input type="hidden" name="city" value="<?= htmlspecialchars($defaultCity) ?>">
                                    <?php endif; ?>
                                    <div class="row g-2 align-items-center">
                                        <div class="col-12 col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-0 text-danger"><i class="fa-solid fa-location-dot fs-5"></i></span>
                                                <select name="district" aria-label="İlçe seçimi" class="form-select bg-transparent border-0 fw-semibold text-dark" style="font-size: 15px; height: 52px; box-shadow: none;">
                                                    <option value="">Tüm İlçeler</option>
                                                    <?php foreach ($districts as $dist): ?>
                                                        <option value="<?= htmlspecialchars($dist) ?>"><?= htmlspecialchars($dist) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="d-none d-md-block border-end" style="width: 1px; height: 38px; background: #e2e8f0;"></div>
                                        <div class="col-12 col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-0 text-danger"><i class="fa-solid fa-tags fs-5"></i></span>
                                                <select name="category" aria-label="Sektör seçimi" class="form-select bg-transparent border-0 fw-semibold text-dark" style="font-size: 15px; height: 52px; box-shadow: none;">
                                                    <option value="">Tüm Sektörler</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="d-none d-md-block border-end" style="width: 1px; height: 38px; background: #e2e8f0;"></div>
                                        <div class="col-12 col-md-3 flex-grow-1">
                                            <div class="input-group">
                                                <span class="input-group-text bg-transparent border-0 text-danger"><i class="fa-solid fa-magnifying-glass fs-5"></i></span>
                                                <input type="text" name="q" aria-label="Arama terimi" class="form-control bg-transparent border-0 fw-medium text-dark" placeholder="İşletme adı, kuaför, avukat..." style="font-size: 15px; height: 52px; box-shadow: none;">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-auto">
                                            <button type="submit" class="btn btn-danger w-100 fw-bold px-4 shadow d-flex align-items-center justify-content-center gap-2" style="height: 52px; border-radius: 12px; font-size: 16px; background: #d32f2f; border-color: #d32f2f;">
                                                <i class="fa-solid fa-magnifying-glass"></i>
                                                <span>HEMEN ARA</span>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Sadece İlçe Linkleri (meslekler kaldırıldı) -->
                                    <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mt-3 pt-3 border-top">
                                        <span class="text-muted small fw-bold me-1"><i class="fa-solid fa-location-dot text-danger me-1"></i>İlçeler:</span>
                                        <?php foreach (array_slice($districts, 0, 6) as $dist):
                                            $distCount = isset($districtCounts[$dist]) ? $districtCounts[$dist] : 0;
                                        ?>
                                        <a href="<?= seoResolveAbsoluteUrl('esnaflar?district=' . urlencode($dist), $baseUrl) ?>" class="badge bg-light text-dark border text-decoration-none px-2 py-1">
                                            <?= htmlspecialchars($dist) ?><?php if ($distCount > 0): ?> (<?= (int)$distCount ?>)<?php endif; ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Slide CTA Butonları -->
                            <?php if (!empty($slide['button_text']) || !empty($slide['button2_text'])): ?>
                            <div class="d-flex flex-wrap gap-3 justify-content-center">
                                <?php if (!empty($slide['button_text'])): ?>
                                <a href="<?= htmlspecialchars(seoResolveAbsoluteUrl($slide['button_url'] ?? 'esnaflar', $baseUrl)) ?>" class="btn btn-danger fw-bold px-4 py-2 shadow" style="border-radius: 10px; font-size: 15px; background: #d32f2f; border-color: #d32f2f;">
                                    <i class="fa-solid fa-magnifying-glass me-2"></i><?= htmlspecialchars($slide['button_text']) ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($slide['button2_text'])): ?>
                                <?php 
                                    $btn2Url = $slide['button2_url'] ?? 'isletme';
                                    // Eğer link direkt isletme veya login'e gidiyorsa iletişim sayfasına yönlendir.
                                    if (trim($btn2Url) === 'isletme' || trim($btn2Url) === '/isletme' || strpos($btn2Url, 'isletme/login.php') !== false) {
                                        $btn2Url = 'iletisim?subject=' . urlencode('Yeni İşletme Kaydı');
                                    }
                                ?>
                                <a href="<?= htmlspecialchars(seoResolveAbsoluteUrl($btn2Url, $baseUrl)) ?>" class="btn btn-danger fw-bold px-4 py-2 shadow" style="border-radius: 10px; font-size: 15px; background: #d32f2f; border-color: #d32f2f;">
                                    <?php
                                    $btn2Text = $slide['button2_text'];
                                    if (in_array(trim($btn2Text), ['İşletmemi Ekle', 'İşletmemi Eklet'], true)) {
                                        $btn2Text = 'İşletmeni Eklet';
                                    }
                                    ?>
                                    <i class="fa-solid fa-plus me-2"></i><?= htmlspecialchars($btn2Text) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Prev / Next Oklar -->
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" style="width: 60px;">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Önceki</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" style="width: 60px;">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Sonraki</span>
    </button>
</div>


<!-- Stats Ticker -->
<div class="stats-ticker reveal-on-scroll" aria-label="Platform istatistikleri">
    <div class="stats-ticker__track">
        <div class="stats-ticker__item">
            <strong><?= htmlspecialchars(formatHomeStat($statBusinesses)) ?></strong>
            <span>Kayıtlı İşletme</span>
        </div>
        <div class="stats-ticker__item">
            <strong><?= htmlspecialchars(formatHomeStat($statDistricts)) ?></strong>
            <span>İlçe</span>
        </div>
        <div class="stats-ticker__item">
            <strong><?= htmlspecialchars(formatHomeStat($statCategories)) ?></strong>
            <span>Sektör & Kategori</span>
        </div>
        <div class="stats-ticker__item stats-ticker__item--accent">
            <strong><?= count($services) ?></strong>
            <span>Dijital Hizmet</span>
        </div>
    </div>
</div>

<?php if (!empty($premiumBusinesses)): ?>
<!-- Ustam Nerede Tarzı Vitrin -->
<section class="home-featured-biz" style="padding-top: 60px; padding-bottom: 60px; background: #f8fafc;">
    <div class="container">
        <header class="portal-section__head mb-4 text-center">
            <h2>Öne Çıkan İşletmeler</h2>
        </header>

        <div class="ustam-grid reveal-stagger">
            <?php foreach ($premiumBusinesses as $biz):
                $bizLogo = '';
                if (!empty($biz['logo_path']) && $biz['logo_path'] !== 'default_logo.png') {
                    $bizLogo = (strpos($biz['logo_path'], 'http') === 0)
                        ? $biz['logo_path']
                        : $siteUrl('public/images/' . $biz['logo_path']);
                }
                $bizCover = '';
                if (!empty($biz['cover_image']) && $biz['cover_image'] !== 'default_cover.jpg') {
                    $bizCover = (strpos($biz['cover_image'], 'http') === 0)
                        ? $biz['cover_image']
                        : $siteUrl('public/images/' . $biz['cover_image']);
                }
                
                $bizLetter = mb_strtoupper(mb_substr($biz['name'], 0, 1, 'UTF-8'), 'UTF-8');
                $bizColor = !empty($biz['theme_color']) ? htmlspecialchars($biz['theme_color']) : '#1F242B';
                
                $isFallbackCover = false;
                if (empty($bizCover) && !empty($bizLogo)) {
                    $bizCover = $bizLogo;
                    $isFallbackCover = true;
                }
                
                // Get rating
                $avgRating = 0;
                $reviewCount = 0;
                try {
                    $rStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rev_count FROM reviews WHERE business_id = ? AND status = 'approved'");
                    $rStmt->execute([$biz['id']]);
                    $rData = $rStmt->fetch();
                    $avgRating = round((float)($rData['avg_rating'] ?? 0), 1);
                    $reviewCount = (int)($rData['rev_count'] ?? 0);
                } catch(Exception $e) {}
            ?>
            <article class="ustam-card reveal-on-scroll">
                <div class="ustam-card__cover" style="background:<?= $isFallbackCover ? '#ffffff' : $bizColor ?>;">
                    <?php if (!empty($bizCover)): ?>
                        <img src="<?= SecurityHelper::escape($bizCover) ?>" alt="<?= SecurityHelper::escape($biz['name']) ?>" loading="lazy" decoding="async" <?= $isFallbackCover ? 'style="object-fit: contain; padding: 20px;"' : '' ?>>
                    <?php endif; ?>
                    <span class="ustam-card__badge" style="background: #F59E0B;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
                </div>
                <div class="ustam-card__body">
                    <h3 class="ustam-card__title">
                        <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" style="color:inherit;text-decoration:none;">
                            <?= SecurityHelper::escape($biz['name']) ?>
                        </a>
                    </h3>
                    <div class="ustam-card__meta-top">
                        <div class="ustam-card__rating">
                            <span class="ustam-card__rating-box"><?= $reviewCount > 0 ? number_format($avgRating, 1) : 'Yeni' ?></span>
                            <span class="ustam-card__rating-text" style="font-size: 11px; white-space: nowrap;"><?= $reviewCount > 0 ? $reviewCount . ' Yorum' : 'Değerlendirme yok' ?></span>
                        </div>
                        <?php if (!empty($biz['category_name'])): ?>
                            <span class="ustam-card__category"><?= SecurityHelper::escape($biz['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ustam-card__meta-mid">
                        <?php if (!empty($biz['district'])): ?>
                            <span class="ustam-card__location"><?= SecurityHelper::escape($biz['district']) ?><?= !empty($biz['city']) ? ' / ' . SecurityHelper::escape($biz['city']) : '' ?></span>
                        <?php endif; ?>
                        <span class="ustam-card__status">Hizmete Hazır</span>
                    </div>
                    <div class="ustam-card__footer">
                        <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" class="ustam-card__btn">Profili Gör</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="<?= seoGetBaseUrl() ?>/esnaflar" class="btn btn-outline-primary px-4 py-2 fw-bold" style="border-radius: 12px;">Tüm İşletmeleri Gör <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($homeBanners)): ?>
<section class="portal-section portal-section--light portal-section--compact">
    <div class="container">
        <?php foreach ($homeBanners as $banner):
            $bannerImg = (strpos($banner['image_path'], 'http') === 0) ? $banner['image_path'] : seoGetBaseUrl() . '/public/images/' . $banner['image_path'];
        ?>
            <div class="portal-banner reveal-on-scroll mb-4">
                <a href="<?= htmlspecialchars($banner['target_url'] ?: '#') ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?= htmlspecialchars($bannerImg) ?>" alt="<?= htmlspecialchars($banner['title'] ?: ($siteTitle . ' Reklam')) ?>" width="1200" height="300" loading="lazy" decoding="async" style="max-height: 300px; object-fit: cover; width: 100%; border-radius: 12px;">
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<section class="portal-section portal-section--light portal-section--compact">
    <div class="container">
        <div class="portal-banner reveal-on-scroll">
            <a href="<?= seoResolveAbsoluteUrl('isletme-basvuru.php', $baseUrl) ?>" class="d-flex flex-column align-items-center justify-content-center text-center p-4 shadow-sm" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b; text-decoration: none; min-height: 250px; transition: all 0.3s; width: 100%;">
                <i class="fa-solid fa-rectangle-ad fs-1 mb-3 text-danger" style="opacity: 0.85;"></i>
                <strong class="text-dark d-block mb-2 fs-5">Buraya Reklam Verebilirsiniz</strong>
                <span class="text-muted">İşletmenizi ana sayfada on binlerce kişiye ulaştırmak ve detaylı bilgi almak için bizimle iletişime geçin.</span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- --- PREMIUM VIP CTA BANNER (İŞLETMENİZİ EKLEYİN) --- -->
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
            <?php
            $vipWaPhone = preg_replace('/[^0-9]/', '', $siteSettings['contact_whatsapp'] ?? ($siteSettings['contact_phone'] ?? ''));
            $vipWaUrl = !empty($vipWaPhone) ? ('https://wa.me/' . $vipWaPhone . '?text=' . urlencode('İşletmemi ekletmek istiyorum bilgi alabilir miyim?')) : seoResolveAbsoluteUrl('isletme-basvuru.php', $baseUrl);
            ?>
            <div class="home-vip-cta__actions">
                <a href="<?= seoResolveAbsoluteUrl('isletme-basvuru.php', $baseUrl) ?>" class="home-vip-cta__btn-primary">
                    <i class="fa-solid fa-store"></i> Hemen İşletmeni Ekle
                </a>
                <a href="<?= $vipWaUrl ?>" class="home-vip-cta__btn-secondary" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i> Bilgi Al
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 02 Capability Stack — Medya Hizmetleri -->
<section class="portal-section portal-section--light" id="hizmetler">
    <div class="container">
        <header class="portal-section__head reveal-on-scroll" style="margin-bottom: 2rem;">
            <div class="portal-section__index">02</div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow">Esnaflarımıza Özel</span>
                <h2><?= htmlspecialchars($homeServicesTitle) ?></h2>
                <p class="portal-section__desc mb-0"><?= htmlspecialchars($homeServicesDesc) ?></p>
            </div>
        </header>

        <div class="home-srv-carousel-wrapper position-relative">
            <button class="srv-nav-btn srv-nav-prev" type="button" aria-label="Önceki hizmet">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="home-srv-grid reveal-stagger" id="homeSrvGrid">
                <?php foreach ($services as $i => $srv):
                    $ctaType = $srv['cta_type'] ?? 'iletisim';
                    $ctaUrl  = $srv['cta_url'] ?? '';
                    if ($ctaType === 'whatsapp' && !empty($ctaUrl)) {
                        $srvHref = htmlspecialchars($ctaUrl);
                        $srvTarget = ' target="_blank" rel="noopener noreferrer"';
                    } else {
                        $srvHref = seoResolveAbsoluteUrl('hizmetlerimiz/' . htmlspecialchars($srv['slug']), $baseUrl);
                        $srvTarget = '';
                    }
                ?>
                <a href="<?= $srvHref ?>"<?= $srvTarget ?> class="home-srv-card reveal-on-scroll">
                    <div class="home-srv-card__icon"><i class="<?= htmlspecialchars($srv['icon']) ?>"></i></div>
                    <div class="home-srv-card__body">
                        <strong><?= htmlspecialchars($srv['title']) ?></strong>
                        <small><?= htmlspecialchars(mb_strimwidth($srv['description'] ?? '', 0, 85, '…')) ?></small>
                    </div>
                    <span class="home-srv-card__arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="srv-nav-btn srv-nav-next" type="button" aria-label="Sonraki hizmet">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>

        <div class="text-center mt-4 pt-2 reveal-on-scroll d-flex align-items-center justify-content-center gap-3 flex-wrap">
            <a href="<?= seoResolveAbsoluteUrl('hizmetlerimiz', $baseUrl) ?>" class="btn btn-primary fw-semibold px-4 py-2">
                Tüm Hizmetler <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
            <a href="<?= seoResolveAbsoluteUrl('iletisim', $baseUrl) ?>" class="btn btn-outline-primary fw-semibold px-4 py-2">
                Teklif Alın
            </a>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var carousels = document.querySelectorAll('#homeSrvGrid, .event-grid--home, .portal-blog-grid, .influencer-grid--home');
    carousels.forEach(function(grid) {
        var wrapper = grid.parentElement;
        if (!wrapper) return;
        var prevBtn = wrapper.querySelector('.srv-nav-prev');
        var nextBtn = wrapper.querySelector('.srv-nav-next');
        
        function getScrollStep() {
            var card = grid.children[0];
            return card ? card.offsetWidth + 16 : 280;
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                grid.scrollBy({ left: -getScrollStep(), behavior: 'smooth' });
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var maxScroll = grid.scrollWidth - grid.clientWidth;
                if (grid.scrollLeft >= maxScroll - 10) {
                    grid.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    grid.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
                }
            });
        }

        var isHovered = false;
        wrapper.addEventListener('mouseenter', function() { isHovered = true; });
        wrapper.addEventListener('mouseleave', function() { isHovered = false; });
        wrapper.addEventListener('touchstart', function() { isHovered = true; }, {passive: true});
        wrapper.addEventListener('touchend', function() { 
            setTimeout(function() { isHovered = false; }, 3000);
        }, {passive: true});

        setInterval(function() {
            if (!isHovered && grid && grid.children.length > 1) {
                var maxScroll = grid.scrollWidth - grid.clientWidth;
                if (grid.scrollLeft >= maxScroll - 10) {
                    grid.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    grid.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
                }
            }
        }, 3500);
    });
});
</script>

<!-- 03 How It Works -->
<section class="portal-section portal-section--dark">
    <div class="container">
        <header class="portal-section__head portal-section__head--center reveal-on-scroll">
            <div class="portal-section__index portal-section__index--light">03</div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow portal-section__eyebrow--light">Nasıl Çalışır?</span>
                <h2 class="text-white">3 Adımda İşletmeye Ulaşın</h2>
                <p class="portal-section__desc portal-section__desc--light">QR kod ve dijital profil sayesinde menüye, iletişim bilgilerine ve sosyal medyaya saniyeler içinde erişin.</p>
            </div>
        </header>

        <div class="portal-steps reveal-stagger">
            <article class="portal-step reveal-on-scroll">
                <span class="portal-step__num">1</span>
                <div class="portal-step__icon"><i class="fa-solid fa-qrcode"></i></div>
                <h3>QR Kodu Tarayın</h3>
                <p>İşletmenin masasındaki veya vitrinindeki QR kodu telefonunuzun kamerasıyla okutun.</p>
            </article>
            <article class="portal-step reveal-on-scroll">
                <span class="portal-step__num">2</span>
                <div class="portal-step__icon"><i class="fa-solid fa-mobile-screen"></i></div>
                <h3>Profili ve Menüyü Görün</h3>
                <p>Dijital kartvizit açılır; menü, konum, Instagram ve diğer bağlantılar tek ekranda sunulur.</p>
            </article>
            <article class="portal-step reveal-on-scroll">
                <span class="portal-step__num">3</span>
                <div class="portal-step__icon"><i class="fa-solid fa-phone-volume"></i></div>
                <h3>Hemen İletişime Geçin</h3>
                <p>Tek dokunuşla arayın, WhatsApp mesajı gönderin veya yol tarifi alın.</p>
            </article>
        </div>
    </div>
</section>

<?php if (!empty($featuredInfluencers)): ?>
<section class="portal-section portal-section--muted">
    <div class="container">
        <header class="portal-section__head reveal-on-scroll">
            <div class="portal-section__index">04</div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow">Influencer</span>
                <h2><?= htmlspecialchars($homeInfluencerTitle) ?></h2>
                <p class="portal-section__desc mb-0"><?= htmlspecialchars($homeInfluencerDesc) ?></p>
            </div>
            <a href="<?= seoResolveAbsoluteUrl('influencerlar', $baseUrl) ?>" class="portal-section__action btn btn-outline-primary">Tüm Influencerlar <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </header>
        <div class="home-carousel-wrapper position-relative">
            <button class="srv-nav-btn srv-nav-prev" type="button" aria-label="Önceki">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="influencer-grid influencer-grid--home reveal-stagger">
                <?php foreach ($featuredInfluencers as $inf):
                    $avatar = getInfluencerImageUrl($inf['avatar_path']);
                    $letter = mb_strtoupper(mb_substr($inf['name'], 0, 1, 'UTF-8'), 'UTF-8');
                    $color = !empty($inf['theme_color']) ? htmlspecialchars($inf['theme_color']) : '#1F242B';
                ?>
                <article class="influencer-card <?= $inf['is_premium'] ? 'influencer-card--premium' : '' ?> reveal-on-scroll">
                    <a href="<?= seoResolveAbsoluteUrl('influencer/' . htmlspecialchars($inf['slug']), $baseUrl) ?>" class="influencer-card-link">
                        <div class="influencer-card-avatar" style="--inf-color: <?= $color ?>">
                            <?php if ($avatar): ?><img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($inf['name']) ?>"><?php else: ?><span><?= $letter ?></span><?php endif; ?>
                        </div>
                        <div class="influencer-card-body">
                            <h3><?= htmlspecialchars($inf['name']) ?></h3>
                            <?php if ($inf['is_verified']): ?><div class="mb-1"><?= renderInfluencerVerifiedBadge() ?></div><?php endif; ?>
                            <p class="influencer-card-niche"><?= htmlspecialchars(getInfluencerNicheLabel($inf['niche'])) ?></p>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <button class="srv-nav-btn srv-nav-next" type="button" aria-label="Sonraki">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredCampaigns)): ?>
<section class="portal-section portal-section--light">
    <div class="container">
        <header class="portal-section__head reveal-on-scroll">
            <div class="portal-section__index">05</div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow">Fırsat & İndirim</span>
                <h2>Öne Çıkan Kampanyalar</h2>
                <p class="portal-section__desc mb-0">Şehirdeki en özel indirimler, fırsatlar ve süreli kampanyalar.</p>
            </div>
            <a href="<?= seoResolveAbsoluteUrl('kampanyalar', $baseUrl) ?>" class="portal-section__action btn btn-outline-primary">Tüm Kampanyalar <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </header>
        <div class="home-carousel-wrapper position-relative">
            <button class="srv-nav-btn srv-nav-prev" type="button" aria-label="Önceki">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="event-grid event-grid--home reveal-stagger">
                <?php foreach ($featuredCampaigns as $camp):
                    $cover = getCampaignImageUrl($camp['cover_image_path'], 'https://images.unsplash.com/photo-1607083206869-4c6b6b127a1e?w=800&q=80');
                    $badge = formatCampaignDateBadge($camp['start_date']);
                ?>
                <article class="event-card <?= !empty($camp['is_featured']) ? 'event-card--featured' : '' ?> reveal-on-scroll">
                    <a href="<?= seoResolveAbsoluteUrl('kampanya/' . htmlspecialchars($camp['slug']), $baseUrl) ?>" class="event-card-link">
                        <div class="event-card-cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');">
                            <div class="event-card-date">
                                <strong><?= htmlspecialchars($badge['day']) ?></strong>
                                <span><?= htmlspecialchars($badge['month']) ?></span>
                            </div>
                            <?php if (!empty($camp['discount_label'])): ?>
                                <div class="position-absolute bottom-0 end-0 m-2 badge bg-danger text-white px-2 py-1 shadow-sm" style="z-index:2;">
                                    <?= htmlspecialchars($camp['discount_label']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="event-card-body">
                            <span class="event-card-category"><?= htmlspecialchars(getCampaignTypeLabel($camp['campaign_type'] ?? '')) ?></span>
                            <h3><?= htmlspecialchars($camp['title']) ?></h3>
                            <p class="event-card-venue"><i class="fa-solid fa-shop"></i> <?= htmlspecialchars($camp['business_name'] ?? 'İşletme') ?> · <?= htmlspecialchars($camp['district'] ?: 'Tümü') ?></p>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <button class="srv-nav-btn srv-nav-next" type="button" aria-label="Sonraki">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredEvents)): ?>
<section class="portal-section portal-section--light">
    <div class="container">
        <header class="portal-section__head reveal-on-scroll">
            <div class="portal-section__index"><?= !empty($featuredCampaigns) ? '06' : '05' ?></div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow">Etkinlik</span>
                <h2><?= htmlspecialchars($homeEventsTitle) ?></h2>
                <p class="portal-section__desc mb-0"><?= htmlspecialchars($homeEventsDesc) ?></p>
            </div>
            <a href="<?= seoResolveAbsoluteUrl('etkinlikler', $baseUrl) ?>" class="portal-section__action btn btn-outline-primary">Tüm Etkinlikler <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </header>
        <div class="home-carousel-wrapper position-relative">
            <button class="srv-nav-btn srv-nav-prev" type="button" aria-label="Önceki">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="event-grid event-grid--home reveal-stagger">
                <?php foreach ($featuredEvents as $ev):
                    $cover = getEventImageUrl($ev['cover_image_path'], 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&q=80');
                    $badge = formatEventDateBadge($ev['start_date']);
                ?>
                <article class="event-card <?= $ev['is_featured'] ? 'event-card--featured' : '' ?> reveal-on-scroll">
                    <a href="<?= seoResolveAbsoluteUrl('etkinlik/' . htmlspecialchars($ev['slug']), $baseUrl) ?>" class="event-card-link">
                        <div class="event-card-cover" style="background-image: url('<?= htmlspecialchars($cover) ?>');">
                            <div class="event-card-date">
                                <strong><?= htmlspecialchars($badge['day']) ?></strong>
                                <span><?= htmlspecialchars($badge['month']) ?></span>
                            </div>
                        </div>
                        <div class="event-card-body">
                            <span class="event-card-category"><?= htmlspecialchars(getEventCategoryLabel($ev['category'])) ?></span>
                            <h3><?= htmlspecialchars($ev['title']) ?></h3>
                            <p class="event-card-venue"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($ev['district']) ?></p>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <button class="srv-nav-btn srv-nav-next" type="button" aria-label="Sonraki">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($latestBlogs)): ?>
<section class="portal-section portal-section--muted">
    <div class="container">
        <header class="portal-section__head reveal-on-scroll">
            <div class="portal-section__index"><?= sprintf('%02d', 5 + (!empty($featuredCampaigns) ? 1 : 0) + (!empty($featuredEvents) ? 1 : 0)) ?></div>
            <div class="portal-section__titles">
                <span class="portal-section__eyebrow">Blog</span>
                <h2><?= htmlspecialchars($homeBlogTitle) ?></h2>
                <?php if ($homeBlogDesc !== ''): ?>
                <p class="portal-section__desc mb-0"><?= htmlspecialchars($homeBlogDesc) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= seoResolveAbsoluteUrl('blog', $baseUrl) ?>" class="portal-section__action btn btn-outline-primary">Tüm Yazılar <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </header>
        <div class="home-carousel-wrapper position-relative">
            <button class="srv-nav-btn srv-nav-prev" type="button" aria-label="Önceki">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="portal-blog-grid reveal-stagger">
                <?php foreach ($latestBlogs as $post):
                    $imgUrl = blogResolveImageUrl($post['image_path'] ?? '', 'card');
                ?>
                <article class="portal-blog-card reveal-on-scroll">
                    <a href="<?= seoResolveAbsoluteUrl('blog/' . htmlspecialchars($post['slug']), $baseUrl) ?>" class="portal-blog-card__media">
                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy" decoding="async">
                    </a>
                    <div class="portal-blog-card__body">
                        <time datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>"><i class="fa-regular fa-calendar me-1"></i><?= date('d.m.Y', strtotime($post['created_at'])) ?></time>
                        <h3><a href="<?= seoResolveAbsoluteUrl('blog/' . htmlspecialchars($post['slug']), $baseUrl) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                        <p><?= htmlspecialchars($post['summary']) ?></p>
                        <a href="<?= seoResolveAbsoluteUrl('blog/' . htmlspecialchars($post['slug']), $baseUrl) ?>" class="portal-blog-card__link">Devamını Oku <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <button class="srv-nav-btn srv-nav-next" type="button" aria-label="Sonraki">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
.home-review-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1d4ed8, #7c3aed);
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
}
</style>

<?php require_once 'includes/footer.php'; ?>
