<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/event-helpers.php';
require_once 'includes/seo-meta.php';

$district = trim($_GET['district'] ?? '');
$category = trim($_GET['category'] ?? '');
$when = trim($_GET['when'] ?? '');
$q = trim($_GET['q'] ?? '');
$view = trim($_GET['view'] ?? 'upcoming');
if (!in_array($view, ['upcoming', 'past'], true)) {
    $view = 'upcoming';
}

$categories = eventCategories();
$districts = eventDistricts();
$today = date('Y-m-d');

$sql = 'SELECT * FROM events WHERE is_published = 1';
$params = [];

if ($view === 'past') {
    $sql .= ' AND COALESCE(end_date, start_date) < :today';
} else {
    $sql .= ' AND COALESCE(end_date, start_date) >= :today';
}
$params[':today'] = $today;

if ($district !== '') {
    $sql .= ' AND district = :district';
    $params[':district'] = $district;
}
if ($category !== '' && isset($categories[$category])) {
    $sql .= ' AND category = :category';
    $params[':category'] = $category;
}
if ($when === 'today' && $view === 'upcoming') {
    $sql .= ' AND start_date <= :today AND COALESCE(end_date, start_date) >= :today2';
    $params[':today2'] = $today;
} elseif ($when === 'week' && $view === 'upcoming') {
    $weekEnd = date('Y-m-d', strtotime('+7 days'));
    $sql .= ' AND start_date <= :week_end AND COALESCE(end_date, start_date) >= :today3';
    $params[':week_end'] = $weekEnd;
    $params[':today3'] = $today;
} elseif ($when === 'month' && $view === 'upcoming') {
    $monthEnd = date('Y-m-t');
    $sql .= ' AND start_date <= :month_end AND COALESCE(end_date, start_date) >= :today4';
    $params[':month_end'] = $monthEnd;
    $params[':today4'] = $today;
}
if ($q !== '') {
    $sql .= ' AND (title LIKE :q OR description LIKE :q OR venue_name LIKE :q OR organizer LIKE :q OR district LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

if ($view === 'past') {
    $sql .= ' ORDER BY start_date DESC, is_featured DESC';
} else {
    $sql .= ' ORDER BY is_featured DESC, start_date ASC';
}

$events = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
} catch (Exception $e) {}

$activeCategoryName = $category !== '' ? getEventCategoryLabel($category) : '';
$resultCount = count($events);
$hasActiveFilters = $district !== '' || $category !== '' || $when !== '' || $q !== '';

if ($view === 'past') {
    $heroTitle = 'Geçmiş Etkinlikler';
} elseif ($when === 'today') {
    $heroTitle = 'Bugün şehrinda';
} elseif ($when === 'week') {
    $heroTitle = 'Bu Hafta şehrinda';
} elseif ($when === 'month') {
    $heroTitle = 'Bu Ay şehrinda';
} elseif ($district !== '' && $activeCategoryName !== '') {
    $heroTitle = SecurityHelper::escape($activeCategoryName) . ' — ' . SecurityHelper::escape($district);
} elseif ($district !== '') {
    $heroTitle = SecurityHelper::escape($district) . ' Etkinlikleri';
} elseif ($activeCategoryName !== '') {
    $heroTitle = SecurityHelper::escape($activeCategoryName);
} elseif ($q !== '') {
    $heroTitle = '"' . SecurityHelper::escape($q) . '" Arama Sonuçları';
} else {
    $heroTitle = 'Şehir Etkinlikleri';
}

$pageTitle = $view === 'past' ? 'Geçmiş ' . seoGetRegionName() . ' Etkinlikleri' : seoGetRegionName() . ' Etkinlikleri & Konserler';
$_region = seoGetRegionName();
$_regionLow = strtolower($_region);
$metaDescription = $_region . ' etkinlik takvimi: ' . $_region . ' ilçelerinde konser, festival, sergi, spor ve kültür etkinlikleri. Güncel program ve detaylar.';
$metaKeywords = $_regionLow . ' etkinlik, ' . $_regionLow . ' konser, ' . $_regionLow . ' festival, ' . strtolower(seoGetSiteTitle()) . ' etkinlik';
// seo-meta.php already loaded above
$listingSeo = seoListingPageMeta('/etkinlikler', $hasActiveFilters || $view === 'past');
$canonicalUrl = $listingSeo['canonical'];
$robotsMeta = $listingSeo['robots'];
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--event">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Şehir Etkinlik Takvimi</span>
                <h1 class="directory-portal-hero__title"><?= $heroTitle ?></h1>
                <p class="directory-portal-hero__lead">Konser, festival, sergi, spor ve kültür programları. İlçe ve tarihe göre filtreleyin; detay sayfasından bilet ve konum bilgisine ulaşın.</p>
                <div class="directory-portal-hero__actions">
                    <div class="evt-portal-view-tabs" role="tablist" aria-label="Etkinlik görünümü">
                        <a href="<?= eventFilterUrl(['view' => 'upcoming', 'when' => null]) ?>" class="evt-portal-view-tab <?= $view === 'upcoming' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $view === 'upcoming' ? 'true' : 'false' ?>">
                            <i class="fa-regular fa-calendar-check"></i> Yaklaşan
                        </a>
                        <a href="<?= eventFilterUrl(['view' => 'past', 'when' => null]) ?>" class="evt-portal-view-tab <?= $view === 'past' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $view === 'past' ? 'true' : 'false' ?>">
                            <i class="fa-solid fa-clock-rotate-left"></i> Geçmiş
                        </a>
                    </div>
                    <a href="<?= seoGetBaseUrl() ?>/etkinlik-basvuru" class="btn btn-primary fw-semibold"><i class="fa-solid fa-calendar-plus me-2"></i> Etkinliğimi Yayınlat</a>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Etkinlik</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-magnifying-glass"></i> Etkinlik Ara & Filtrele</span>
            </div>
            <form action="<?= seoGetBaseUrl() ?>/etkinlikler" method="GET" class="search-dock__form search-dock__form--event">
                <input type="hidden" name="view" value="<?= SecurityHelper::escape($view) ?>">

                <div class="search-dock__field">
                    <label for="evt-search-district" class="visually-hidden">İlçe seçin</label>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <select name="district" id="evt-search-district" class="form-select">
                        <option value="">Tüm İlçeler</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= SecurityHelper::escape($dist) ?>" <?= $district === $dist ? 'selected' : '' ?>><?= SecurityHelper::escape($dist) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field">
                    <label for="evt-search-category" class="visually-hidden">Kategori seçin</label>
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <select name="category" id="evt-search-category" class="form-select">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($categories as $slug => $label): ?>
                            <option value="<?= SecurityHelper::escape($slug) ?>" <?= $category === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($view === 'upcoming'): ?>
                <div class="search-dock__field">
                    <label for="evt-search-when" class="visually-hidden">Tarih aralığı</label>
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    <select name="when" id="evt-search-when" class="form-select">
                        <option value="">Tüm Tarihler</option>
                        <option value="today" <?= $when === 'today' ? 'selected' : '' ?>>Bugün</option>
                        <option value="week" <?= $when === 'week' ? 'selected' : '' ?>>Bu Hafta</option>
                        <option value="month" <?= $when === 'month' ? 'selected' : '' ?>>Bu Ay</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="search-dock__field search-dock__field--grow">
                    <label for="evt-search-keyword" class="visually-hidden">Anahtar kelime</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" name="q" id="evt-search-keyword" class="form-control" placeholder="Etkinlik, mekân veya organizatör ara…" value="<?= SecurityHelper::escape($q) ?>">
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
                <nav class="portal-filter-panel" aria-label="Etkinlik filtreleri">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">F</span>
                        <div>
                            <span class="portal-section__eyebrow">Filtreler</span>
                            <h2 class="portal-filter-panel__title">Daralt & Keşfet</h2>
                        </div>
                    </div>

                    <?php if ($view === 'upcoming'): ?>
                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-regular fa-calendar"></i> Tarih</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $when === '' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['when' => null]) ?>">Tümü</a>
                            </li>
                            <li class="<?= $when === 'today' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['when' => 'today']) ?>">Bugün</a>
                            </li>
                            <li class="<?= $when === 'week' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['when' => 'week']) ?>">Bu Hafta</a>
                            </li>
                            <li class="<?= $when === 'month' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['when' => 'month']) ?>">Bu Ay</a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-location-dot"></i> İlçeler</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $district === '' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['district' => null]) ?>">Tüm İlçeler</a>
                            </li>
                            <?php foreach ($districts as $dist): ?>
                                <li class="<?= $district === $dist ? 'is-active' : '' ?>">
                                    <a href="<?= eventFilterUrl(['district' => $dist]) ?>"><?= SecurityHelper::escape($dist) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-tag"></i> Kategori</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $category === '' ? 'is-active' : '' ?>">
                                <a href="<?= eventFilterUrl(['category' => null]) ?>">Tüm Kategoriler</a>
                            </li>
                            <?php foreach ($categories as $slug => $label): ?>
                                <li class="<?= $category === $slug ? 'is-active' : '' ?>">
                                    <a href="<?= eventFilterUrl(['category' => $slug]) ?>"><?= SecurityHelper::escape($label) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($hasActiveFilters): ?>
                        <a href="<?= seoGetBaseUrl() ?>/etkinlikler?view=<?= SecurityHelper::escape($view) ?>" class="btn btn-outline-primary w-100 portal-filter-clear">
                            <i class="fa-solid fa-eraser me-2"></i> Tümünü Temizle
                        </a>
                    <?php endif; ?>

                    <div class="portal-trust-card">
                        <h4><i class="fa-solid fa-calendar-days"></i> Etkinlik Rehberi</h4>
                        <ul>
                            <li>Admin onaylı yayın</li>
                            <li>Bilet linki ve konum bilgisi</li>
                            <li>Mekân = işletme bağlantısı</li>
                            <li>Geçmiş etkinlik arşivi</li>
                        </ul>
                        <a href="<?= seoGetBaseUrl() ?>/etkinlik-basvuru" class="btn btn-primary btn-sm w-100 mt-3"><i class="fa-solid fa-calendar-plus me-1"></i> Etkinliğimi Yayınlat</a>
                    </div>
                </nav>
            </aside>

            <div class="directory-portal-results reveal-on-scroll">
                <?php if ($hasActiveFilters): ?>
                    <div class="directory-active-filters">
                        <span class="directory-active-filters__label">Aktif filtreler</span>
                        <div class="directory-active-filters__chips">
                            <?php if ($when !== '' && $view === 'upcoming'): ?>
                                <span class="directory-filter-chip">
                                    Tarih: <?= $when === 'today' ? 'Bugün' : ($when === 'week' ? 'Bu Hafta' : 'Bu Ay') ?>
                                    <a href="<?= eventFilterUrl(['when' => null]) ?>" aria-label="Tarih filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($district !== ''): ?>
                                <span class="directory-filter-chip">
                                    İlçe: <?= SecurityHelper::escape($district) ?>
                                    <a href="<?= eventFilterUrl(['district' => null]) ?>" aria-label="İlçe filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($category !== ''): ?>
                                <span class="directory-filter-chip">
                                    Kategori: <?= SecurityHelper::escape($activeCategoryName) ?>
                                    <a href="<?= eventFilterUrl(['category' => null]) ?>" aria-label="Kategori filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($q !== ''): ?>
                                <span class="directory-filter-chip">
                                    Arama: "<?= SecurityHelper::escape($q) ?>"
                                    <a href="<?= eventFilterUrl(['q' => null]) ?>" aria-label="Arama filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow"><?= $view === 'past' ? 'Arşiv' : 'Program' ?></span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $resultCount ?></strong> etkinlik listeleniyor</h2>
                    </div>
                </header>

                <?php if (empty($events)): ?>
                    <div class="portal-empty directory-portal-empty">
                        <i class="fa-regular fa-calendar-xmark"></i>
                        <h3>Etkinlik Bulunamadı</h3>
                        <p>Filtreleri değiştirin veya <?= $view === 'past' ? 'yaklaşan etkinliklere' : 'geçmiş arşive' ?> göz atın.</p>
                        <a href="<?= seoGetBaseUrl() ?>/etkinlikler?view=<?= $view === 'past' ? 'upcoming' : 'past' ?>" class="btn btn-primary"><?= $view === 'past' ? 'Yaklaşan Etkinlikler' : 'Geçmiş Etkinlikler' ?></a>
                    </div>
                <?php else: ?>
                    <div class="portal-event-grid reveal-stagger">
                        <?php foreach ($events as $i => $ev):
                            $cover = getEventImageUrl($ev['cover_image_path'], 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&q=80');
                            $badge = formatEventDateBadge($ev['start_date']);
                            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                        <article class="portal-event-card <?= $ev['is_featured'] ? 'portal-event-card--featured' : '' ?> reveal-on-scroll">
                            <?php if ($ev['is_featured']): ?>
                                <span class="portal-event-card__featured"><i class="fa-solid fa-star"></i> Öne Çıkan</span>
                            <?php endif; ?>
                            <span class="portal-event-card__rank"><?= $rank ?></span>
                            <a href="<?= seoGetBaseUrl() ?>/etkinlik/<?= SecurityHelper::escape($ev['slug']) ?>" class="portal-event-card__link">
                                <div class="portal-event-card__cover" style="background-image: url('<?= SecurityHelper::escape($cover) ?>');">
                                    <div class="portal-event-card__date">
                                        <strong><?= SecurityHelper::escape($badge['day']) ?></strong>
                                        <span><?= SecurityHelper::escape($badge['month']) ?></span>
                                    </div>
                                </div>
                                <div class="portal-event-card__body">
                                    <div class="portal-event-card__meta">
                                        <?= renderEventStatusBadge($ev) ?>
                                        <span class="portal-event-card__category"><?= SecurityHelper::escape(getEventCategoryLabel($ev['category'])) ?></span>
                                    </div>
                                    <h3><?= SecurityHelper::escape($ev['title']) ?></h3>
                                    <p class="portal-event-card__venue">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= SecurityHelper::escape($ev['district']) ?><?php if (!empty($ev['venue_name'])): ?> · <?= SecurityHelper::escape($ev['venue_name']) ?><?php endif; ?>
                                    </p>
                                    <p class="portal-event-card__datetime">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= SecurityHelper::escape(formatEventDateRange($ev['start_date'], $ev['end_date'])) ?><?php if (!empty($ev['start_time'])): ?> · <?= SecurityHelper::escape(formatEventTimeRange($ev['start_time'], $ev['end_time'])) ?><?php endif; ?>
                                    </p>
                                    <?php if (!empty($ev['ticket_price'])): ?>
                                        <p class="portal-event-card__price"><i class="fa-solid fa-ticket"></i> <?= SecurityHelper::escape($ev['ticket_price']) ?></p>
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
