<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/duty-pharmacy-helpers.php';
require_once 'includes/seo-meta.php';

$districtSlug = trim($_GET['district_slug'] ?? '');
$when = trim($_GET['when'] ?? 'today');
$dutyDate = dutyPharmacyResolveDate($when);
$districts = seoGetŞehirDistricts();
$dutyPharmacyDistrictSlug = $districtSlug;
$dutyPharmacyWhen = $when;

$activeDistrictName = $districtSlug !== '' ? seoDistrictSlugToName($districtSlug) : '';

// Cron yok: veri boş veya 6+ saat eskiyse otomatik API senkronu
dutyPharmacyEnsureSynced($pdo, $dutyDate);

$pharmacies = dutyPharmacyGetList($pdo, $dutyDate, $districtSlug);
$grouped = dutyPharmacyGroupByDistrict($pharmacies);
$resultCount = count($pharmacies);
$settings = dutyPharmacyGetSettings($pdo);
$lastSync = $settings['duty_pharmacy_last_sync'] ?? null;
$isTomorrow = in_array(strtolower($when), ['tomorrow', 'yarin', 'yarın'], true);
$dutyPeriodLabel = dutyPharmacyFormatPeriodLabel($dutyDate);

if ($activeDistrictName && $isTomorrow) {
    $heroTitle = $activeDistrictName . ' Yarın Nöbetçi Eczaneler';
} elseif ($activeDistrictName) {
    $heroTitle = $activeDistrictName . ' Nöbetçi Eczaneler';
} elseif ($isTomorrow) {
    $heroTitle = 'Yarın Nöbetçi Eczaneler';
} else {
    $heroTitle = 'Nöbetçi Eczaneler';
}

$pageTitle = $heroTitle;
$_region = seoGetRegionName();
$regionName = $_region;
$_regionLow = mb_strtolower($_region, 'UTF-8');
$metaDescription = $regionName . ' geneli ' . ($activeDistrictName ? $activeDistrictName . ' ' : '') . 'nöbetçi eczaneler listesi'
    . ': adres, telefon, harita ve yol tarifi. Tüm ' . $regionName . ' ilçeleri.';
$metaKeywords = $_regionLow . ' nöbetçi eczane, ' . ($activeDistrictName ? mb_strtolower($activeDistrictName, 'UTF-8') . ' nöbetçi eczane, ' : '') . $_regionLow . ' eczane, nöbetçi eczane ' . $_regionLow;

$canonicalPath = '/nobetci-eczane';
if ($districtSlug !== '') {
    $canonicalPath .= '/' . $districtSlug;
}
if ($isTomorrow) {
    $canonicalPath .= '?when=tomorrow';
}
$canonicalUrl = seoGetBaseUrl() . $canonicalPath;

require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--services">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Sağlık Rehberi</span>
                <h1 class="directory-portal-hero__title"><?= SecurityHelper::escape($heroTitle) ?></h1>
                <p class="directory-portal-hero__lead">
                    <?= SecurityHelper::escape($dutyPeriodLabel) ?> Şehir genelindeki nöbetçi eczaneleri ilçe bazında görüntüleyin.
                </p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Eczane</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-pills"></i> Nöbetçi Eczane Filtrele</span>
            </div>
            <form action="<?= seoGetBaseUrl() ?>/nobetci-eczane" method="GET" class="search-dock__form">
                <div class="search-dock__field">
                    <label for="duty-search-district" class="visually-hidden">İlçe seçin</label>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <select name="district_slug" id="duty-search-district" class="form-select" onchange="this.form.submit()">
                        <option value="">Tüm Bölge</option>
                        <?php foreach ($districts as $dist):
                            $slug = seoDistrictNameToSlug($dist);
                            if (!$slug) continue;
                        ?>
                            <option value="<?= SecurityHelper::escape($slug) ?>" <?= $districtSlug === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($dist) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-dock__field">
                    <label for="duty-search-when" class="visually-hidden">Tarih</label>
                    <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                    <select name="when" id="duty-search-when" class="form-select" onchange="this.form.submit()">
                        <option value="today" <?= !$isTomorrow ? 'selected' : '' ?>>Bugün</option>
                        <option value="tomorrow" <?= $isTomorrow ? 'selected' : '' ?>>Yarın</option>
                    </select>
                </div>
                <a href="<?= SecurityHelper::escape(dutyPharmacyFilterUrl(['district' => null, 'when' => 'today'])) ?>" class="btn btn-outline-primary search-dock__submit">
                    <span>Temizle</span>
                </a>
            </form>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted directory-portal-main">
    <div class="container">
        <div class="directory-portal-layout">
            <aside class="directory-portal-sidebar reveal-on-scroll">
                <nav class="portal-filter-panel" aria-label="İlçe filtresi">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">İ</span>
                        <div>
                            <span class="portal-section__eyebrow">İlçeler</span>
                            <h2 class="portal-filter-panel__title">İlçeler</h2>
                        </div>
                    </div>
                    <ul class="portal-filter-list">
                        <li class="<?= $districtSlug === '' ? 'is-active' : '' ?>">
                            <a href="<?= SecurityHelper::escape(dutyPharmacyFilterUrl(['district' => null])) ?>">Tüm Bölge</a>
                        </li>
                        <?php foreach ($districts as $dist):
                            $slug = seoDistrictNameToSlug($dist);
                            if (!$slug) continue;
                        ?>
                            <li class="<?= $districtSlug === $slug ? 'is-active' : '' ?>">
                                <a href="<?= SecurityHelper::escape(dutyPharmacyFilterUrl(['district' => $slug])) ?>"><?= SecurityHelper::escape($dist) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <div class="biz-portal-widget duty-pharmacy-side-panel">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">R</span>
                        <div>
                            <span class="portal-section__eyebrow">Resmi</span>
                            <h3>Resmi Kaynaklar</h3>
                        </div>
                    </header>
                    <div class="biz-portal-quick-tiles">
                        <a href="https://www.teb.org.tr/" target="_blank" rel="noopener noreferrer" class="biz-portal-quick-tile biz-portal-quick-tile--website">
                            <span class="biz-portal-quick-tile__icon"><i class="fa-solid fa-building-columns"></i></span>
                            <span class="biz-portal-quick-tile__text">
                                <strong>Eczacı Odası</strong>
                                <small>Günlük resmi liste</small>
                            </span>
                            <i class="fa-solid fa-arrow-right biz-portal-quick-tile__arrow"></i>
                        </a>
                        <a href="https://www.turkiye.gov.tr/saglik-titck-nobetci-eczane-sorgulama" target="_blank" rel="noopener noreferrer" class="biz-portal-quick-tile biz-portal-quick-tile--website">
                            <span class="biz-portal-quick-tile__icon"><i class="fa-solid fa-landmark"></i></span>
                            <span class="biz-portal-quick-tile__text">
                                <strong>e-Devlet TİTCK</strong>
                                <small>Resmi sorgulama</small>
                            </span>
                            <i class="fa-solid fa-arrow-right biz-portal-quick-tile__arrow"></i>
                        </a>
                    </div>
                </div>
            </aside>

            <div class="directory-portal-results">
                <?php if (empty($pharmacies)): ?>
                    <div class="portal-empty directory-portal-empty reveal-on-scroll">
                        <i class="fa-solid fa-prescription-bottle-medical"></i>
                        <?php if ($activeDistrictName): ?>
                            <h3><?= SecurityHelper::escape($activeDistrictName) ?> için kayıt bulunamadı</h3>
                            <p>
                                <?= SecurityHelper::escape($dutyPeriodLabel) ?>
                                tarihinde bu ilçe için nöbetçi eczane listesi görüntülenemiyor.
                                Farklı bir ilçe seçebilir veya resmi kaynaklardan güncel bilgiye ulaşabilirsiniz.
                            </p>
                        <?php else: ?>
                            <h3>Nöbetçi eczane listesi görüntülenemiyor</h3>
                            <p>
                                Seçili tarih için liste şu an yüklenemedi. Lütfen aşağıdaki resmi kaynaklardan
                                güncel nöbetçi eczane bilgisine ulaşın veya kısa süre sonra tekrar deneyin.
                            </p>
                        <?php endif; ?>
                        <div class="duty-pharmacy-empty-actions">
                            <a href="https://www.teb.org.tr/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
                                <i class="fa-solid fa-building-columns me-2"></i>Eczacı Odası
                            </a>
                            <a href="https://www.turkiye.gov.tr/saglik-titck-nobetci-eczane-sorgulama" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                                <i class="fa-solid fa-landmark me-2"></i>e-Devlet Sorgula
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <header class="directory-results-head reveal-on-scroll">
                        <div>
                            <span class="portal-section__eyebrow">Sonuçlar</span>
                            <h2 class="directory-results-head__title">
                                <strong><?= (int) $resultCount ?></strong> nöbetçi eczane listeleniyor
                            </h2>
                            <?php if ($lastSync): ?>
                                <p class="directory-results-head__meta">Son güncelleme: <?= date('d.m.Y H:i', strtotime($lastSync)) ?></p>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php foreach ($grouped as $districtName => $items): ?>
                        <section class="duty-pharmacy-district biz-portal-panel reveal-on-scroll">
                            <header class="biz-portal-panel__head">
                                <span class="portal-section__index"><i class="fa-solid fa-location-dot"></i></span>
                                <div>
                                    <span class="portal-section__eyebrow"><?= SecurityHelper::escape($districtName) ?></span>
                                    <h2><?= count($items) ?> Nöbetçi Eczane</h2>
                                </div>
                            </header>
                            <div class="duty-pharmacy-grid">
                                <?php foreach ($items as $pharmacy): ?>
                                    <article class="duty-pharmacy-card">
                                        <div class="duty-pharmacy-card__icon" aria-hidden="true">
                                            <i class="fa-solid fa-prescription-bottle-medical"></i>
                                        </div>
                                        <div class="duty-pharmacy-card__body">
                                            <h3 class="duty-pharmacy-card__title"><?= SecurityHelper::escape($pharmacy['name']) ?></h3>
                                            <ul class="duty-pharmacy-card__meta">
                                                <?php if (!empty($pharmacy['address'])): ?>
                                                    <li>
                                                        <i class="fa-solid fa-location-dot"></i>
                                                        <span><?= SecurityHelper::escape($pharmacy['address']) ?></span>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (!empty($pharmacy['phone'])): ?>
                                                    <li>
                                                        <i class="fa-solid fa-phone"></i>
                                                        <span><?= SecurityHelper::escape($pharmacy['phone']) ?></span>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <div class="duty-pharmacy-card__actions">
                                            <?php if (!empty($pharmacy['phone']) && dutyPharmacyPhoneHref($pharmacy['phone'])): ?>
                                                <a href="<?= SecurityHelper::escape(dutyPharmacyPhoneHref($pharmacy['phone'])) ?>" class="btn btn-primary btn-sm">
                                                    <i class="fa-solid fa-phone"></i> Ara
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= SecurityHelper::escape(dutyPharmacyMapsUrl($pharmacy)) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                <i class="fa-solid fa-diamond-turn-right"></i> Yol Tarifi
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="duty-pharmacy-note reveal-on-scroll">
                    <i class="fa-solid fa-circle-info"></i>
                    <p>
                        Nöbetçi eczane bilgileri resmi kaynaklardan derlenerek güncellenir. Acil sağlık durumlarında 112’yi arayın.
                        Doğrulama için Eczacı Odası ve e-Devlet TİTCK hizmetini kullanabilirsiniz.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
