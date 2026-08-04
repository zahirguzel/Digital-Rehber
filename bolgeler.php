<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/district-helpers.php';

$regionName = seoGetRegionName();
$regionLower = mb_strtolower($regionName, 'UTF-8');
$pageTitle = $regionName . ' İlçeleri';
$metaDescription = $regionName . ' ilçe rehberleri: yerel işletme, esnaf ve gezi rehberlerini ilçe bazında keşfedin.';
$metaKeywords = $regionLower . ' ilçeleri, ' . $regionLower . ' esnaf, ' . $regionLower . ' yerel rehber';

$districtCounts = [];
try {
    $rows = $pdo->query("SELECT district, COUNT(*) AS cnt FROM businesses WHERE district IS NOT NULL AND district != '' AND is_deleted = 0 GROUP BY district")->fetchAll();
    foreach ($rows as $row) {
        $districtCounts[$row['district']] = (int) $row['cnt'];
    }
} catch (Exception $e) {}

$pages = getDistrictPagesList($pdo, true);
$totalBusinesses = array_sum($districtCounts);
$canonicalUrl = seoGetBaseUrl() . '/bolgeler';
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--districts">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Geo Rehber</span>
                <h1 class="directory-portal-hero__title"><?= SecurityHelper::escape($regionName) ?> İlçe Rehberleri</h1>
                <p class="directory-portal-hero__lead">Her ilçede yerel işletmeleri, gezi rotalarını ve etkinlikleri ayrı rehber sayfalarında keşfedin.</p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= count($pages) ?></strong>
                <span>İlçe</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted district-portal-main">
    <div class="container">
        <header class="directory-results-head reveal-on-scroll">
            <div>
                <span class="portal-section__eyebrow"><?= SecurityHelper::escape($regionName) ?> Haritası</span>
                <h2 class="directory-results-head__title">
                    <strong><?= count($pages) ?></strong> ilçe rehberi
                    <?php if ($totalBusinesses > 0): ?>
                        · <strong><?= number_format($totalBusinesses, 0, ',', '.') ?></strong> kayıtlı işletme
                    <?php endif; ?>
                </h2>
            </div>
        </header>

        <?php if (empty($pages)): ?>
            <div class="portal-empty directory-portal-empty reveal-on-scroll">
                <i class="fa-solid fa-map-location-dot"></i>
                <h3>İlçe Rehberi Bulunamadı</h3>
                <p>Henüz yayında ilçe rehberi bulunmuyor.</p>
            </div>
        <?php else: ?>
            <div class="portal-district-grid reveal-stagger">
                <?php foreach ($pages as $i => $item):
                    $district = $item['district_name'];
                    $slug = $item['slug'];
                    $count = (int) ($item['business_count'] ?? ($districtCounts[$district] ?? 0));
                    $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                ?>
                <article class="portal-district-card reveal-on-scroll">
                    <span class="portal-district-card__rank"><?= $rank ?></span>
                    <div class="portal-district-card__head">
                        <span class="portal-district-card__icon"><i class="fa-solid fa-location-dot"></i></span>
                        <div>
                            <h2><a href="<?= seoGetBaseUrl() ?>/ilce/<?= SecurityHelper::escape($slug) ?>"><?= SecurityHelper::escape($district) ?></a></h2>
                            <?php if ($count > 0): ?>
                                <span class="portal-district-card__count"><?= $count ?> işletme</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($item['tagline'])): ?>
                        <p class="portal-district-card__tagline"><?= SecurityHelper::escape($item['tagline']) ?></p>
                    <?php endif; ?>
                    <div class="portal-district-card__actions">
                        <a href="<?= seoGetBaseUrl() ?>/ilce/<?= SecurityHelper::escape($slug) ?>" class="btn btn-primary portal-district-card__cta">
                            İlçe Rehberi <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <div class="portal-district-card__links">
                            <a href="<?= seoGetBaseUrl() ?>/esnaflar?district=<?= urlencode($district) ?>"><i class="fa-solid fa-store"></i> İşletmeler</a>
                            <?php if (!empty($item['blog_slug'])): ?>
                                <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($item['blog_slug']) ?>.html"><i class="fa-solid fa-route"></i> Gezi Rehberi</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
