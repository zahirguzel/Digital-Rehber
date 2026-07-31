<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/campaign-helpers.php';
require_once 'includes/seo-meta.php';

$district = trim($_GET['district'] ?? '');
$type = trim($_GET['type'] ?? '');
$q = trim($_GET['q'] ?? '');
$view = trim($_GET['view'] ?? 'active');
if (!in_array($view, ['active', 'past'], true)) {
    $view = 'active';
}

$types = campaignTypes();
$districts = campaignDistricts();
$today = date('Y-m-d');

$sql = 'SELECT ' . campaignListSelectSql() . campaignListJoinSql() . ' WHERE c.is_published = 1';
$params = [];

if ($view === 'past') {
    $sql .= ' AND COALESCE(c.end_date, c.start_date) < :today';
} else {
    $sql .= ' AND COALESCE(c.end_date, c.start_date) >= :today';
}
$params[':today'] = $today;

if ($district !== '') {
    $sql .= ' AND c.district = :district';
    $params[':district'] = $district;
}
if ($type !== '' && isset($types[$type])) {
    $sql .= ' AND c.campaign_type = :type';
    $params[':type'] = $type;
}
if ($q !== '') {
    $sql .= ' AND (c.title LIKE :q OR c.summary LIKE :q OR c.description LIKE :q OR c.discount_label LIKE :q OR b.name LIKE :q OR c.district LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

if ($view === 'past') {
    $sql .= ' ORDER BY c.end_date DESC, c.is_featured DESC, c.start_date DESC';
} else {
    $sql .= ' ORDER BY c.is_featured DESC, c.start_date ASC, c.title ASC';
}

$campaigns = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll();
} catch (Exception $e) {}

$activeTypeName = $type !== '' ? getCampaignTypeLabel($type) : '';
$resultCount = count($campaigns);
$hasActiveFilters = $district !== '' || $type !== '' || $q !== '';

if ($view === 'past') {
    $heroTitle = 'Geçmiş Kampanyalar';
} elseif ($district !== '' && $activeTypeName !== '') {
    $heroTitle = SecurityHelper::escape($activeTypeName) . ' — ' . SecurityHelper::escape($district);
} elseif ($district !== '') {
    $heroTitle = SecurityHelper::escape($district) . ' Kampanyaları';
} elseif ($activeTypeName !== '') {
    $heroTitle = SecurityHelper::escape($activeTypeName) . ' Kampanyaları';
} elseif ($q !== '') {
    $heroTitle = '"' . SecurityHelper::escape($q) . '" Arama Sonuçları';
} else {
    $heroTitle = 'Şehir İşletme Kampanyaları';
}

$pageTitle = $view === 'past' ? 'Geçmiş ' . seoGetRegionName() . ' Kampanyaları' : seoGetRegionName() . ' Kampanyalar & Fırsatlar';
$_region = seoGetRegionName();
$_regionLow = strtolower($_region);
$metaDescription = seoGetSiteTitle() . ' kampanyaları: ' . $_region . ' işletmelerinin güncel indirim, fırsat ve özel gün kampanyaları. Güncel fırsatları keşfedin.';
$metaKeywords = $_regionLow . ' kampanya, ' . $_regionLow . ' indirim, ' . $_regionLow . ' fırsat, ' . strtolower(seoGetSiteTitle()) . ' kampanya';
// seo-meta.php already loaded above
$listingSeo = seoListingPageMeta('/kampanyalar', $hasActiveFilters || $view === 'past');
$canonicalUrl = $listingSeo['canonical'];
$robotsMeta = $listingSeo['robots'];
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--campaign">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Yerel Fırsatlar</span>
                <h1 class="directory-portal-hero__title"><?= $heroTitle ?></h1>
                <p class="directory-portal-hero__lead">Şehirdeki işletmelerin güncel indirim ve kampanyalarını keşfedin. İlçe ve kampanya türüne göre filtreleyin; detay sayfasından işletmeye ulaşın.</p>
                <div class="directory-portal-hero__actions">
                    <div class="camp-portal-view-tabs" role="tablist" aria-label="Kampanya görünümü">
                        <a href="<?= campaignFilterUrl(['view' => 'active']) ?>" class="camp-portal-view-tab <?= $view === 'active' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $view === 'active' ? 'true' : 'false' ?>">
                            <i class="fa-solid fa-tags"></i> Güncel
                        </a>
                        <a href="<?= campaignFilterUrl(['view' => 'past']) ?>" class="camp-portal-view-tab <?= $view === 'past' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $view === 'past' ? 'true' : 'false' ?>">
                            <i class="fa-solid fa-clock-rotate-left"></i> Geçmiş
                        </a>
                    </div>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Kampanya</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-magnifying-glass"></i> Kampanya Ara & Filtrele</span>
            </div>
            <form action="<?= SecurityHelper::escape($siteUrl('kampanyalar')) ?>" method="GET" class="search-dock__form search-dock__form--event">
                <input type="hidden" name="view" value="<?= SecurityHelper::escape($view) ?>">

                <div class="search-dock__field">
                    <label for="camp-search-district" class="visually-hidden">İlçe seçin</label>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <select name="district" id="camp-search-district" class="form-select">
                        <option value="">Tüm İlçeler</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= SecurityHelper::escape($dist) ?>" <?= $district === $dist ? 'selected' : '' ?>><?= SecurityHelper::escape($dist) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field">
                    <label for="camp-search-type" class="visually-hidden">Kampanya türü</label>
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <select name="type" id="camp-search-type" class="form-select">
                        <option value="">Tüm Türler</option>
                        <?php foreach ($types as $slug => $label): ?>
                            <option value="<?= SecurityHelper::escape($slug) ?>" <?= $type === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field search-dock__field--grow">
                    <label for="camp-search-keyword" class="visually-hidden">Anahtar kelime</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" name="q" id="camp-search-keyword" class="form-control" placeholder="Kampanya veya işletme ara…" value="<?= SecurityHelper::escape($q) ?>">
                </div>

                <button type="submit" class="btn btn-primary search-dock__submit">
                    <span>Ara</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted directory-portal-main">
    <div class="container">
        <div class="directory-portal-layout">

            <aside class="directory-portal-sidebar reveal-on-scroll">
                <nav class="portal-filter-panel" aria-label="Kampanya filtreleri">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">F</span>
                        <div>
                            <span class="portal-section__eyebrow">Filtreler</span>
                            <h2 class="portal-filter-panel__title">Daralt & Keşfet</h2>
                        </div>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-location-dot"></i> İlçe</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $district === '' ? 'is-active' : '' ?>">
                                <a href="<?= campaignFilterUrl(['district' => null]) ?>">Tümü</a>
                            </li>
                            <?php foreach ($districts as $dist): ?>
                                <li class="<?= $district === $dist ? 'is-active' : '' ?>">
                                    <a href="<?= campaignFilterUrl(['district' => $dist]) ?>"><?= SecurityHelper::escape($dist) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-tags"></i> Tür</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $type === '' ? 'is-active' : '' ?>">
                                <a href="<?= campaignFilterUrl(['type' => null]) ?>">Tümü</a>
                            </li>
                            <?php foreach ($types as $slug => $label): ?>
                                <li class="<?= $type === $slug ? 'is-active' : '' ?>">
                                    <a href="<?= campaignFilterUrl(['type' => $slug]) ?>"><?= SecurityHelper::escape($label) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($hasActiveFilters): ?>
                        <a href="<?= SecurityHelper::escape($siteUrl('kampanyalar?view=' . $view)) ?>" class="btn btn-outline-primary w-100 portal-filter-clear">
                            <i class="fa-solid fa-eraser me-2"></i> Tümünü Temizle
                        </a>
                    <?php endif; ?>

                    <div class="portal-trust-card">
                        <h4><i class="fa-solid fa-percent"></i> Kampanya Rehberi</h4>
                        <ul>
                            <li>Admin onaylı yayın</li>
                            <li>İşletme profiline bağlantı</li>
                            <li>İlçe ve tür filtreleri</li>
                            <li>Güncel ve geçmiş arşiv</li>
                        </ul>
                        <a href="<?= SecurityHelper::escape($siteUrl('iletisim?subject=' . urlencode('Kampanya Yayınlama'))) ?>" class="btn btn-primary btn-sm w-100 mt-3"><i class="fa-solid fa-bullhorn me-1"></i> Kampanyamı Eklet</a>
                    </div>
                </nav>
            </aside>

            <div class="directory-portal-results reveal-on-scroll">
                <?php if ($hasActiveFilters): ?>
                    <div class="directory-active-filters">
                        <span class="directory-active-filters__label">Aktif filtreler</span>
                        <div class="directory-active-filters__chips">
                            <?php if ($district !== ''): ?>
                                <span class="directory-filter-chip">
                                    İlçe: <?= SecurityHelper::escape($district) ?>
                                    <a href="<?= campaignFilterUrl(['district' => null]) ?>" aria-label="İlçe filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($type !== ''): ?>
                                <span class="directory-filter-chip">
                                    Tür: <?= SecurityHelper::escape($activeTypeName) ?>
                                    <a href="<?= campaignFilterUrl(['type' => null]) ?>" aria-label="Tür filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($q !== ''): ?>
                                <span class="directory-filter-chip">
                                    Arama: "<?= SecurityHelper::escape($q) ?>"
                                    <a href="<?= campaignFilterUrl(['q' => null]) ?>" aria-label="Arama filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow"><?= $view === 'past' ? 'Arşiv' : 'Fırsatlar' ?></span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $resultCount ?></strong> kampanya listeleniyor</h2>
                    </div>
                </header>

                <?php if (empty($campaigns)): ?>
                    <div class="portal-empty directory-portal-empty">
                        <i class="fa-solid fa-tags"></i>
                        <h3>Kampanya Bulunamadı</h3>
                        <p>Filtreleri değiştirin veya <?= $view === 'past' ? 'güncel kampanyalara' : 'geçmiş arşive' ?> göz atın.</p>
                        <a href="<?= SecurityHelper::escape($siteUrl('kampanyalar?view=' . ($view === 'past' ? 'active' : 'past'))) ?>" class="btn btn-primary"><?= $view === 'past' ? 'Güncel Kampanyalar' : 'Geçmiş Kampanyalar' ?></a>
                    </div>
                <?php else: ?>
                    <div class="portal-event-grid camp-portal-grid reveal-stagger">
                        <?php foreach ($campaigns as $i => $camp):
                            $cover = getCampaignImageUrl($camp['cover_image_path'], 'https://images.unsplash.com/photo-1607083206869-4c6b6b127a1e?w=800&q=80');
                            $badge = formatCampaignDateBadge($camp['start_date']);
                            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                        <article class="portal-event-card camp-portal-card <?= $camp['is_featured'] ? 'portal-event-card--featured' : '' ?> reveal-on-scroll">
                            <?php if ($camp['is_featured']): ?>
                                <span class="portal-event-card__featured"><i class="fa-solid fa-star"></i> Öne Çıkan</span>
                            <?php endif; ?>
                            <span class="portal-event-card__rank"><?= $rank ?></span>
                            <a href="<?= SecurityHelper::escape($siteUrl('kampanya/' . $camp['slug'])) ?>" class="portal-event-card__link">
                                <div class="portal-event-card__cover" style="background-image: url('<?= SecurityHelper::escape($cover) ?>');">
                                    <div class="portal-event-card__date">
                                        <strong><?= SecurityHelper::escape($badge['day']) ?></strong>
                                        <span><?= SecurityHelper::escape($badge['month']) ?></span>
                                    </div>
                                    <?php if (!empty($camp['discount_label'])): ?>
                                        <span class="camp-portal-card__discount"><?= SecurityHelper::escape($camp['discount_label']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="portal-event-card__body">
                                    <div class="portal-event-card__meta">
                                        <?= renderCampaignStatusBadge($camp) ?>
                                        <span class="portal-event-card__category"><?= SecurityHelper::escape(getCampaignTypeLabel($camp['campaign_type'])) ?></span>
                                    </div>
                                    <h3><?= SecurityHelper::escape($camp['title']) ?></h3>
                                    <?php if (!empty($camp['business_name'])): ?>
                                        <p class="portal-event-card__venue">
                                            <i class="fa-solid fa-store"></i>
                                            <?= SecurityHelper::escape($camp['business_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="portal-event-card__venue">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= SecurityHelper::escape($camp['district']) ?>
                                    </p>
                                    <p class="portal-event-card__datetime">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= SecurityHelper::escape(formatCampaignDateRange($camp['start_date'], $camp['end_date'])) ?>
                                    </p>
                                    <?= renderCampaignPriceHtml($camp, 'camp-portal-price camp-portal-card__price') ?>
                                    <?php if (!empty($camp['summary'])): ?>
                                        <p class="camp-portal-card__summary"><?= SecurityHelper::escape($camp['summary']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
