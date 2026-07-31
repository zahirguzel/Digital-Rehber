<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/campaign-helpers.php';
require_once 'includes/seo-meta.php';

$seoBaseUrl = seoGetBaseUrl();
$seoBaseUrlTrimmed = rtrim($seoBaseUrl, '/');
$siteUrl = function ($path = '') use ($seoBaseUrlTrimmed) {
    if ($path === '' || $path === '/') {
        return $seoBaseUrlTrimmed . '/';
    }

    return $seoBaseUrlTrimmed . '/' . ltrim($path, '/');
};

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . $siteUrl('kampanyalar'));
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT ' . campaignListSelectSql() . campaignListJoinSql() . ' WHERE c.slug = ? LIMIT 1'
    );
    $stmt->execute([$slug]);
    $campaign = $stmt->fetch();
} catch (Exception $e) {
    $campaign = false;
}

if ($campaign && empty($campaign['is_published'])) {
    $canViewUnpublished = false;
    if (!empty($_SESSION['admin_logged_in']) || !empty($_SESSION['admin_id']) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['superadmin', 'admin'], true))) {
        $canViewUnpublished = true;
    } elseif (!empty($_SESSION['biz_id']) && (int)$_SESSION['biz_id'] === (int)($campaign['business_id'] ?? 0)) {
        $canViewUnpublished = true;
    }
    if (!$canViewUnpublished) {
        $campaign = false;
    }
}

if (!$campaign) {
    header('HTTP/1.0 404 Not Found');
    header('Location: ' . $siteUrl('404'));
    exit;
}

$business = getCampaignBusiness($pdo, $campaign['business_id'] ?? null);
$cover = getCampaignImageUrl($campaign['cover_image_path'], 'https://images.unsplash.com/photo-1607083206869-4c6b6b127a1e?w=1200&q=80');
$dateBadge = formatCampaignDateBadge($campaign['start_date']);
$isPast = isCampaignPast($campaign);

$_region = seoGetRegionName();
$_siteTitle = seoGetSiteTitle();
$pageTitle = $campaign['title'] . ' — ' . $_region . ' Kampanya';
$metaDescription = !empty($campaign['meta_description'])
    ? $campaign['meta_description']
    : ($campaign['summary'] ?: $campaign['title'] . ' — ' . $campaign['district'] . ' kampanya detayları.');
$metaKeywords = !empty($campaign['meta_keywords'])
    ? $campaign['meta_keywords']
    : strtolower($_region) . ' kampanya, ' . strtolower($campaign['district']) . ' indirim, ' . strtolower($_siteTitle);
$canonicalUrl = $siteUrl('kampanya/' . $campaign['slug']);
$ogImage = $cover;
$ogImageAlt = $campaign['title'];
$ogType = 'website';

$schemaOffer = [
    '@context' => 'https://schema.org',
    '@type' => 'Offer',
    'name' => $campaign['title'],
    'description' => strip_tags($campaign['description'] ?: ($campaign['summary'] ?? '')),
    'url' => $canonicalUrl,
    'validFrom' => $campaign['start_date'],
    'priceCurrency' => 'TRY',
    'availability' => $isPast ? 'https://schema.org/Discontinued' : 'https://schema.org/InStock',
];
if (!empty($campaign['end_date'])) {
    $schemaOffer['validThrough'] = $campaign['end_date'];
}
if ($business) {
    $schemaOffer['seller'] = [
        '@type' => 'LocalBusiness',
        'name' => $business['name'],
        'url' => $siteUrl('esnaf/' . $business['slug']),
    ];
}

require_once 'includes/header.php';
?>

<script type="application/ld+json"><?= json_encode($schemaOffer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php if (empty($campaign['is_published'])): ?>
<div class="container mt-4">
    <div class="alert alert-warning d-flex align-items-center gap-3 shadow-sm" style="border-radius:14px;border-left:5px solid #F59E0B;" role="alert">
        <i class="fa-solid fa-clock-rotate-left fs-4 text-warning flex-shrink-0"></i>
        <div>
            <strong class="d-block mb-1">Onay Bekliyor — Henüz Yayında Değil</strong>
            <span class="small text-muted">Bu sayfayı yalnızca yönetici veya kampanyayı ekleyen işletme önizleyebilir. Onaylandıktan sonra herkese açılacak.</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hero: kapak görseli tam arka plan -->
<header class="camp-portal-hero" style="--biz-cover: url('<?= SecurityHelper::escape($cover) ?>');">
    <div class="camp-portal-hero__overlay" aria-hidden="true"></div>
    <div class="container camp-portal-hero__inner">
        <nav class="biz-portal-breadcrumb mb-3" aria-label="Konum">
            <a href="<?= SecurityHelper::escape($siteUrl('/')) ?>" class="text-white-50">Ana Sayfa</a>
            <span class="text-white-50 mx-1">/</span>
            <a href="<?= SecurityHelper::escape($siteUrl('kampanyalar')) ?>" class="text-white-50">Kampanyalar</a>
            <span class="text-white-50 mx-1">/</span>
            <span class="text-white-75"><?= SecurityHelper::escape($campaign['title']) ?></span>
        </nav>
    </div>
</header>

<!-- Kayan bilgi kartı -->
<div class="container evt-portal-profile-wrap">
    <article class="evt-portal-profile-card camp-portal-profile-card <?= $campaign['is_featured'] ? 'evt-portal-profile-card--featured' : '' ?> reveal-on-scroll">

        <div class="evt-portal-profile-card__top">
            <div class="evt-portal-profile-card__identity">
                <!-- Tarih kutusu -->
                <div class="evt-portal-profile-card__date">
                    <strong><?= SecurityHelper::escape($dateBadge['day']) ?></strong>
                    <span><?= SecurityHelper::escape($dateBadge['month']) ?></span>
                </div>

                <div class="evt-portal-profile-card__meta">
                    <!-- Badge'ler -->
                    <div class="evt-portal-profile-card__badges">
                        <span class="evt-portal-profile-card__category"><?= SecurityHelper::escape(getCampaignTypeLabel($campaign['campaign_type'])) ?></span>
                        <?= renderCampaignStatusBadge($campaign) ?>
                        <?php if ($campaign['is_featured']): ?>
                            <span class="badge-premium-inline"><i class="fa-solid fa-star me-1"></i>Öne Çıkan</span>
                        <?php endif; ?>
                    </div>

                    <!-- Gerçek kampanya başlığı -->
                    <h1 class="evt-portal-profile-card__title"><?= SecurityHelper::escape($campaign['title']) ?></h1>

                    <?php if (!empty($campaign['discount_label'])): ?>
                        <p class="camp-portal-profile-card__discount-label">
                            <?= SecurityHelper::escape($campaign['discount_label']) ?>
                        </p>
                    <?php endif; ?>

                    <p class="evt-portal-profile-card__location">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= SecurityHelper::escape($campaign['district']) ?> / <?= SecurityHelper::escape(seoGetRegionName()) ?>
                    </p>
                    <p class="evt-portal-profile-card__schedule">
                        <i class="fa-regular fa-calendar"></i>
                        <?= SecurityHelper::escape(formatCampaignDateRange($campaign['start_date'], $campaign['end_date'])) ?>
                    </p>
                </div>
            </div>

            <div class="evt-portal-profile-card__actions">
                <?php if ($business && !$isPast): ?>
                    <a href="<?= SecurityHelper::escape($siteUrl('esnaf/' . $business['slug'])) ?>" class="btn btn-primary evt-portal-profile-card__btn">
                        <i class="fa-solid fa-store"></i> İşletmeyi Gör
                    </a>
                <?php endif; ?>
                <?php if (!empty($campaign['cta_url']) && !$isPast): ?>
                    <a href="<?= SecurityHelper::escape($campaign['cta_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary evt-portal-profile-card__btn">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Kampanya Linki
                    </a>
                <?php endif; ?>
                <a href="<?= SecurityHelper::escape($siteUrl('kampanyalar')) ?>" class="btn btn-outline-primary evt-portal-profile-card__btn">
                    <i class="fa-solid fa-arrow-left"></i> Tüm Kampanyalar
                </a>
            </div>
        </div>

        <!-- Alt bar: fiyat + özet -->
        <?php
        $priceData = formatCampaignPrice($campaign);
        if (!empty($campaign['summary']) || $priceData !== ''):
        ?>
        <div class="evt-portal-profile-card__bar">
            <?php if ($priceData !== ''): ?>
                <?= renderCampaignPriceHtml($campaign, 'camp-portal-price camp-portal-price--hero') ?>
            <?php endif; ?>
            <?php if (!empty($campaign['summary'])): ?>
                <span class="evt-portal-profile-card__organizer">
                    <i class="fa-solid fa-bullhorn"></i>
                    <?= SecurityHelper::escape($campaign['summary']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </article>
</div>

<!-- Ana içerik + Sidebar -->
<section class="portal-section portal-section--muted biz-portal-main">
    <div class="container">
        <div class="biz-portal-layout">

            <div class="biz-portal-content">

                <?php if (!empty($campaign['description'])): ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">01</span>
                        <div>
                            <span class="portal-section__eyebrow">Detay</span>
                            <h2>Kampanya Hakkında</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <div class="biz-portal-about camp-portal-about"><?= renderCampaignDescriptionHtml($campaign['description']) ?></div>
                    </div>
                </article>
                <?php endif; ?>

                <?php if ($business): ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">02</span>
                        <div>
                            <span class="portal-section__eyebrow">İşletme</span>
                            <h2>Kampanya Sahibi</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <a href="<?= SecurityHelper::escape($siteUrl('esnaf/' . $business['slug'])) ?>" class="evt-portal-linked-biz__item camp-portal-linked-biz">
                            <div>
                                <strong><?= SecurityHelper::escape($business['name']) ?></strong>
                                <small><?= SecurityHelper::escape($business['district']) ?><?php if (!empty($business['category_name'])): ?> · <?= SecurityHelper::escape($business['category_name']) ?><?php endif; ?></small>
                            </div>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
                <?php endif; ?>
            </div>

            <aside class="biz-portal-sidebar reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">B</span>
                        <div>
                            <span class="portal-section__eyebrow">Bilgi</span>
                            <h3>Kampanya Özeti</h3>
                        </div>
                    </header>
                    <ul class="evt-portal-info-list">
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-tag"></i></span>
                            <div class="evt-portal-info-list__body">
                                <small>Tür</small>
                                <strong><?= SecurityHelper::escape(getCampaignTypeLabel($campaign['campaign_type'])) ?></strong>
                            </div>
                        </li>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-regular fa-calendar"></i></span>
                            <div class="evt-portal-info-list__body">
                                <small>Tarih</small>
                                <strong><?= SecurityHelper::escape(formatCampaignDateRange($campaign['start_date'], $campaign['end_date'])) ?></strong>
                            </div>
                        </li>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-location-dot"></i></span>
                            <div class="evt-portal-info-list__body">
                                <small>İlçe</small>
                                <strong><?= SecurityHelper::escape($campaign['district']) ?></strong>
                            </div>
                        </li>
                        <?php if (!empty($campaign['discount_label'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-percent"></i></span>
                            <div class="evt-portal-info-list__body">
                                <small>Fırsat</small>
                                <strong><?= SecurityHelper::escape($campaign['discount_label']) ?></strong>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if ($priceData !== ''): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-tags"></i></span>
                            <div class="evt-portal-info-list__body">
                                <small>Fiyat</small>
                                <strong>
                                    <?= SecurityHelper::escape($priceData['sale'] ?: $priceData['original']) ?>
                                    <?php if (!empty($priceData['original']) && !empty($priceData['sale'])): ?>
                                        <span class="camp-portal-price__original camp-portal-price__original--inline"><?= SecurityHelper::escape($priceData['original']) ?></span>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ($business && (!empty($business['phone']) || !empty($business['whatsapp']))): ?>
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">İ</span>
                        <div>
                            <span class="portal-section__eyebrow">İletişim</span>
                            <h3>İşletmeye Ulaşın</h3>
                        </div>
                    </header>
                    <div class="biz-portal-quick-tiles">
                        <?php if (!empty($business['phone'])): ?>
                        <a href="tel:<?= preg_replace('/\D+/', '', $business['phone']) ?>" class="biz-portal-quick-tile">
                            <span class="biz-portal-quick-tile__icon"><i class="fa-solid fa-phone"></i></span>
                            <span class="biz-portal-quick-tile__text">
                                <strong>Telefon</strong>
                                <small><?= SecurityHelper::escape($business['phone']) ?></small>
                            </span>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($business['whatsapp'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/\D+/', '', $business['whatsapp']) ?>" target="_blank" rel="noopener noreferrer" class="biz-portal-quick-tile biz-portal-quick-tile--whatsapp">
                            <span class="biz-portal-quick-tile__icon"><i class="fa-brands fa-whatsapp"></i></span>
                            <span class="biz-portal-quick-tile__text">
                                <strong>WhatsApp</strong>
                                <small>Hızlı mesaj</small>
                            </span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
