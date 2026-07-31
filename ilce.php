<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/district-helpers.php';

$slug = isset($_GET['slug']) ? strtolower(trim($_GET['slug'])) : '';
$districtName = seoDistrictSlugToName($slug);

if (!$districtName) {
    http_response_code(404);
    require_once '404.php';
    exit;
}

$profile = getDistrictLandingData($districtName, $pdo);
if (!$profile) {
    http_response_code(404);
    require_once '404.php';
    exit;
}

$stats = getDistrictBusinessStats($pdo, $districtName);
$businesses = getDistrictBusinesses($pdo, $districtName, 6);
$events = getDistrictEvents($pdo, $districtName, 3);

$baseUrl = seoGetBaseUrl();
$regionName = seoGetRegionName();
$regionLower = mb_strtolower($regionName, 'UTF-8');
$pageUrl = $baseUrl . '/ilce/' . $slug;
$pageTitle = $districtName . ' İşletmeleri ve Yerel Rehber';
$metaDescription = !empty($profile['meta_description'])
    ? $profile['meta_description']
    : ($districtName . ' ilçesindeki esnaf, restoran, kafe ve yerel işletmeler: telefon, adres, WhatsApp ve harita. ' . ($profile['tagline'] ?? ''));
$metaKeywords = $districtName . ' esnaf, ' . $districtName . ' işletmeler, ' . $regionLower . ' ' . $districtName . ' rehberi, ' . $districtName . ' restoran';

$schemaDistrict = seoBuildDistrictLandingSchema($districtName, $slug, $baseUrl, $pageUrl, $profile, $stats);
require_once 'includes/header.php';
?>

<script type="application/ld+json"><?= json_encode($schemaDistrict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="directory-portal-hero directory-portal-hero--district-detail">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <nav class="biz-portal-breadcrumb biz-portal-breadcrumb--hero reveal-on-scroll" aria-label="Konum">
            <a href="/">Ana Sayfa</a>
            <span aria-hidden="true">/</span>
            <a href="/bolgeler">İlçeler</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current"><?= SecurityHelper::escape($districtName) ?></span>
        </nav>
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow"><?= SecurityHelper::escape($regionName) ?> İlçe Rehberi</span>
                <h1 class="directory-portal-hero__title"><?= SecurityHelper::escape($districtName) ?></h1>
                <p class="directory-portal-hero__lead"><?= SecurityHelper::escape($profile['tagline']) ?></p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $stats['total'] ?></strong>
                <span>İşletme</span>
            </div>
        </div>
        <div class="directory-portal-hero__actions reveal-on-scroll">
            <a href="<?= seoGetBaseUrl() ?>/esnaflar?district=<?= urlencode($districtName) ?>" class="btn btn-primary fw-semibold"><i class="fa-solid fa-store me-2"></i> Tüm İşletmeler</a>
            <?php if (!empty($profile['blog_slug'])): ?>
            <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($profile['blog_slug']) ?>.html" class="btn btn-outline-primary fw-semibold"><i class="fa-solid fa-route me-2"></i> Gezi Rehberi</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted district-detail-main">
    <div class="container district-detail-container">
        <nav class="district-detail-nav reveal-on-scroll" aria-label="Hızlı erişim">
            <a href="<?= seoGetBaseUrl() ?>/esnaflar?district=<?= urlencode($districtName) ?>"><i class="fa-solid fa-store"></i> İşletmeler</a>
            <a href="<?= seoGetBaseUrl() ?>/esnaflar?district=<?= urlencode($districtName) ?>&category=yeme-icme"><i class="fa-solid fa-utensils"></i> Restoran & Kafe</a>
            <a href="<?= seoGetBaseUrl() ?>/etkinlikler"><i class="fa-solid fa-calendar-days"></i> Etkinlikler</a>
            <?php if (!empty($profile['blog_slug'])): ?>
            <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($profile['blog_slug']) ?>.html"><i class="fa-solid fa-map-location-dot"></i> Gezilecek Yerler</a>
            <?php endif; ?>
        </nav>

        <article class="district-detail-intro reveal-on-scroll">
            <h2><?= SecurityHelper::escape($districtName) ?> Hakkında</h2>
            <p><?= SecurityHelper::escape($profile['intro']) ?></p>
            <?php if (!empty($profile['highlights'])): ?>
            <ul class="district-detail-highlights">
                <?php foreach ($profile['highlights'] as $item): ?>
                <li><?= SecurityHelper::escape($item) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </article>

        <?php if (!empty($businesses)): ?>
        <section class="district-detail-block reveal-on-scroll">
            <div class="district-detail-block__head">
                <div>
                    <h2>Öne Çıkan İşletmeler</h2>
                    <p><?= SecurityHelper::escape($districtName) ?> ilçesinden seçili kayıtlar.</p>
                </div>
                <a href="<?= seoGetBaseUrl() ?>/esnaflar?district=<?= urlencode($districtName) ?>" class="btn btn-outline-primary btn-sm fw-semibold">Tümünü Gör</a>
            </div>
            <div class="district-detail-biz-grid">
                <?php foreach ($businesses as $biz):
                    $bizLogo = '';
                    if (!empty($biz['logo_path']) && $biz['logo_path'] !== 'default_logo.png') {
                        $bizLogo = (strpos($biz['logo_path'], 'http') === 0)
                            ? $biz['logo_path']
                            : '/public/images/' . $biz['logo_path'];
                    }
                    $bizLetter = mb_strtoupper(mb_substr($biz['name'], 0, 1, 'UTF-8'), 'UTF-8');
                    $bizColor = !empty($biz['theme_color']) ? SecurityHelper::escape($biz['theme_color']) : '#1F242B';
                ?>
                <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" class="district-detail-biz-card">
                    <span class="district-detail-biz-card__logo">
                        <?php if (!empty($bizLogo)): ?>
                        <img src="<?= SecurityHelper::escape($bizLogo) ?>" alt="" loading="lazy" decoding="async" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span class="district-detail-biz-card__letter" style="display:none;background:<?= $bizColor ?>;"><?= $bizLetter ?></span>
                        <?php else: ?>
                        <span class="district-detail-biz-card__letter" style="background:<?= $bizColor ?>;"><?= $bizLetter ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="district-detail-biz-card__body">
                        <strong><?= SecurityHelper::escape($biz['name']) ?></strong>
                        <small><?= SecurityHelper::escape($biz['category_name'] ?? '') ?></small>
                        <?php if (!empty($biz['phone'])): ?>
                        <small class="district-detail-biz-card__phone"><i class="fa-solid fa-phone"></i> <?= SecurityHelper::escape($biz['phone']) ?></small>
                        <?php endif; ?>
                    </span>
                    <?php if ($biz['is_premium']): ?>
                    <span class="district-detail-biz-card__badge"><i class="fa-solid fa-crown"></i></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
        <section class="district-detail-block reveal-on-scroll">
            <div class="district-detail-block__head">
                <div>
                    <h2>Yaklaşan Etkinlikler</h2>
                    <p><?= SecurityHelper::escape($districtName) ?> ve çevresindeki etkinlikler.</p>
                </div>
                <a href="<?= seoGetBaseUrl() ?>/etkinlikler" class="btn btn-outline-primary btn-sm fw-semibold">Takvim</a>
            </div>
            <div class="district-detail-events-row">
                <?php foreach ($events as $ev): ?>
                <a href="<?= seoGetBaseUrl() ?>/etkinlik/<?= SecurityHelper::escape($ev['slug']) ?>" class="district-detail-event-card">
                    <strong><?= SecurityHelper::escape($ev['title']) ?></strong>
                    <span><i class="fa-regular fa-calendar"></i> <?= date('d.m.Y', strtotime($ev['start_date'])) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($profile['faqs'])): ?>
        <section class="district-detail-block reveal-on-scroll">
            <div class="district-detail-block__head">
                <div>
                    <h2>Sık Sorulan Sorular</h2>
                    <p><?= SecurityHelper::escape($districtName) ?> rehberi hakkında kısa yanıtlar.</p>
                </div>
            </div>
            <div class="district-detail-faq" id="districtFaq">
                <?php foreach ($profile['faqs'] as $i => $faq): ?>
                <article class="district-detail-faq-item">
                    <h3>
                        <button class="district-detail-faq-item__toggle<?= $i === 0 ? ' is-open' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#districtFaqCollapse<?= $i ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                            <?= SecurityHelper::escape($faq['q']) ?>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </h3>
                    <div id="districtFaqCollapse<?= $i ?>" class="collapse<?= $i === 0 ? ' show' : '' ?>" data-bs-parent="#districtFaq">
                        <div class="district-detail-faq-item__body">
                            <?= SecurityHelper::escape($faq['a']) ?>
                            <?php if ($i === 2 && !empty($profile['blog_slug'])): ?>
                            <p class="mb-0 mt-2"><a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($profile['blog_slug']) ?>.html">Gezi rehberini okuyun <i class="fa-solid fa-arrow-right ms-1"></i></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <footer class="district-detail-footer reveal-on-scroll">
            <span>Diğer ilçeler:</span>
            <div class="district-detail-districts">
                <?php foreach (seoGetŞehirDistricts() as $other):
                    if ($other === $districtName) continue;
                    $otherSlug = seoDistrictNameToSlug($other);
                ?>
                <a href="<?= seoGetBaseUrl() ?>/ilce/<?= SecurityHelper::escape($otherSlug) ?>"><?= SecurityHelper::escape($other) ?></a>
                <?php endforeach; ?>
            </div>
        </footer>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
