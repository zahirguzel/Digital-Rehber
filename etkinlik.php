<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/event-helpers.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /etkinlikler');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE slug = ? AND is_published = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $event = $stmt->fetch();
} catch (Exception $e) {
    $event = false;
}

if (!$event) {
    header('HTTP/1.0 404 Not Found');
    header('Location: /404');
    exit;
}

$linkedBusinesses = [];
try {
    $stmtBiz = $pdo->prepare('SELECT b.*, c.name AS category_name FROM event_business_links ebl JOIN businesses b ON b.id = ebl.business_id LEFT JOIN categories c ON c.id = b.category_id WHERE ebl.event_id = ? ORDER BY b.name ASC');
    $stmtBiz->execute([$event['id']]);
    $linkedBusinesses = $stmtBiz->fetchAll();
} catch (Exception $e) {}

$cover = getEventImageUrl($event['cover_image_path'], 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&q=80');
$dateBadge = formatEventDateBadge($event['start_date']);
$isPast = isEventPast($event);
require_once 'includes/seo-meta.php';
$regionName = seoGetRegionName();
$regionLower = mb_strtolower($regionName, 'UTF-8');

$pageTitle = $event['title'] . ' — ' . $regionName . ' Etkinlik';
$metaDescription = !empty($event['meta_description']) ? $event['meta_description'] : $event['title'] . ' — ' . $event['district'] . ' etkinlik detayları, tarih, mekân ve bilet bilgisi.';
$metaKeywords = !empty($event['meta_keywords']) ? $event['meta_keywords'] : $regionLower . ' etkinlik, ' . $event['district'] . ' ' . $event['category'];
$canonicalUrl = seoGetBaseUrl() . '/etkinlik/' . $event['slug'];
$ogImage = $cover;
$ogImageAlt = $event['title'];
$ogType = 'website';

$schemaEvent = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $event['title'],
    'description' => strip_tags($event['description']),
    'startDate' => $event['start_date'] . (!empty($event['start_time']) ? 'T' . substr($event['start_time'], 0, 8) : 'T00:00:00'),
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'location' => [
        '@type' => 'Place',
        'name' => !empty($event['venue_name']) ? $event['venue_name'] : ($event['district'] . ', ' . $regionName),
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $event['district'],
            'addressRegion' => $regionName,
            'addressCountry' => 'TR',
            'streetAddress' => $event['address'] ?? '',
        ],
    ],
    'image' => seoResolveAbsoluteUrl($cover, seoGetBaseUrl()),
    'url' => $canonicalUrl,
];
if (!empty($event['organizer'])) {
    $schemaEvent['organizer'] = ['@type' => 'Organization', 'name' => $event['organizer']];
}
if (!empty($event['end_date'])) {
    $schemaEvent['endDate'] = $event['end_date'] . (!empty($event['end_time']) ? 'T' . substr($event['end_time'], 0, 8) : 'T23:59:59');
}
if (!empty($event['ticket_price'])) {
    $schemaEvent['offers'] = [
        '@type' => 'Offer',
        'price' => $event['ticket_price'],
        'priceCurrency' => 'TRY',
        'url' => !empty($event['ticket_url']) ? $event['ticket_url'] : $canonicalUrl,
        'availability' => 'https://schema.org/InStock',
    ];
}

require_once 'includes/header.php';
?>

<script type="application/ld+json"><?= json_encode($schemaEvent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="biz-portal-hero evt-portal-hero" style="--biz-cover: url('<?= SecurityHelper::escape($cover) ?>');">
    <div class="biz-portal-hero__overlay" aria-hidden="true"></div>
    <div class="biz-portal-hero__backdrop" aria-hidden="true">
        <div class="biz-portal-hero__panel biz-portal-hero__panel--guide"></div>
        <div class="biz-portal-hero__panel biz-portal-hero__panel--media"></div>
    </div>
    <div class="container biz-portal-hero__inner">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="/">Ana Sayfa</a>
            <span aria-hidden="true">/</span>
            <a href="<?= seoGetBaseUrl() ?>/etkinlikler">Etkinlikler</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current"><?= SecurityHelper::escape($event['title']) ?></span>
        </nav>
    </div>
</header>

<div class="container evt-portal-profile-wrap">
    <article class="evt-portal-profile-card <?= $event['is_featured'] ? 'evt-portal-profile-card--featured' : '' ?> reveal-on-scroll">
        <div class="evt-portal-profile-card__top">
            <div class="evt-portal-profile-card__identity">
                <div class="evt-portal-profile-card__date">
                    <strong><?= SecurityHelper::escape($dateBadge['day']) ?></strong>
                    <span><?= SecurityHelper::escape($dateBadge['month']) ?></span>
                </div>

                <div class="evt-portal-profile-card__meta">
                    <div class="evt-portal-profile-card__badges">
                        <span class="evt-portal-profile-card__category"><?= SecurityHelper::escape(getEventCategoryLabel($event['category'])) ?></span>
                        <?= renderEventStatusBadge($event) ?>
                        <?php if ($event['is_featured']): ?>
                            <span class="badge-premium-inline"><i class="fa-solid fa-star me-1"></i>Öne Çıkan</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="evt-portal-profile-card__title"><?= SecurityHelper::escape($event['title']) ?></h1>
                    <p class="evt-portal-profile-card__location">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= SecurityHelper::escape($event['district']) ?> / <?= SecurityHelper::escape($event['city'] ?: $regionName) ?>
                        <?php if (!empty($event['venue_name'])): ?>
                            <span class="evt-portal-profile-card__venue"><?= SecurityHelper::escape($event['venue_name']) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="evt-portal-profile-card__schedule">
                        <i class="fa-regular fa-clock"></i>
                        <?= SecurityHelper::escape(formatEventDateRange($event['start_date'], $event['end_date'])) ?>
                        <?php if (!empty($event['start_time'])): ?>
                            · <?= SecurityHelper::escape(formatEventTimeRange($event['start_time'], $event['end_time'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="evt-portal-profile-card__actions">
                <?php if (!empty($event['ticket_url']) && !$isPast): ?>
                    <a href="<?= SecurityHelper::escape($event['ticket_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary evt-portal-profile-card__btn">
                        <i class="fa-solid fa-ticket"></i> Bilet / Kayıt
                    </a>
                <?php endif; ?>
                <a href="<?= seoGetBaseUrl() ?>/etkinlikler" class="btn btn-outline-primary evt-portal-profile-card__btn">
                    <i class="fa-solid fa-arrow-left"></i> Tüm Etkinlikler
                </a>
            </div>
        </div>

        <?php if (!empty($event['ticket_price']) || !empty($event['organizer'])): ?>
        <div class="evt-portal-profile-card__bar">
            <?php if (!empty($event['ticket_price'])): ?>
                <span class="evt-portal-profile-card__price"><i class="fa-solid fa-ticket"></i> <?= SecurityHelper::escape($event['ticket_price']) ?></span>
            <?php endif; ?>
            <?php if (!empty($event['organizer'])): ?>
                <span class="evt-portal-profile-card__organizer"><i class="fa-solid fa-users"></i> <?= SecurityHelper::escape($event['organizer']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
                            <span class="portal-section__eyebrow">Program</span>
                            <h2>Etkinlik Hakkında</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <div class="biz-portal-about event-content">
                            <?php 
                                $descHtml = $event['description'] ?? '';
                                if (strip_tags($descHtml) === $descHtml) {
                                    $paragraphs = preg_split('/\n\s*\n/', trim($descHtml));
                                    foreach ($paragraphs as $p) {
                                        if (trim($p) !== '') {
                                            echo '<p style="margin-bottom: 1.25rem; line-height: 1.8;">' . nl2br(htmlspecialchars(trim($p))) . '</p>';
                                        }
                                    }
                                } else {
                                    echo $descHtml;
                                }
                            ?>
                        </div>
                    </div>
                </article>

                <?php
                $contentSectionNum = 2;
                if (!empty($event['address'])):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Konum</span>
                            <h2>Adres</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <p class="mb-0"><?= nl2br(SecurityHelper::escape($event['address'])) ?></p>
                    </div>
                </article>
                <?php
                $contentSectionNum++;
                endif;

                if (!empty($linkedBusinesses)):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Rehber</span>
                            <h2>Mekân / İşletme</h2>
                        </div>
                    </header>
                    <div class="evt-portal-linked-biz">
                        <?php foreach ($linkedBusinesses as $biz): ?>
                            <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" class="evt-portal-linked-biz__item">
                                <strong><?= SecurityHelper::escape($biz['name']) ?></strong>
                                <small><?= SecurityHelper::escape($biz['district']) ?> · <?= SecurityHelper::escape($biz['category_name']) ?></small>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php
                $contentSectionNum++;
                endif;

                if (!empty($event['google_maps_url'])):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">Harita</span>
                            <h2>Konum</h2>
                        </div>
                    </header>
                    <div class="evt-portal-map">
                        <?php if (strpos($event['google_maps_url'], '<iframe') !== false): ?>
                            <?= $event['google_maps_url'] ?>
                        <?php elseif (strpos($event['google_maps_url'], 'embed') !== false): ?>
                            <iframe src="<?= SecurityHelper::escape($event['google_maps_url']) ?>" width="100%" height="320" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?= SecurityHelper::escape($event['title']) ?> konumu"></iframe>
                        <?php else: ?>
                            <a href="<?= SecurityHelper::escape($event['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary fw-semibold">
                                <i class="fa-solid fa-map-location-dot me-2"></i> Haritada Aç
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endif; ?>
            </div>

            <aside class="biz-portal-sidebar reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">D</span>
                        <div>
                            <span class="portal-section__eyebrow">Detay</span>
                            <h3>Etkinlik Bilgileri</h3>
                        </div>
                    </header>
                    <ul class="evt-portal-info-list">
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-regular fa-calendar"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Tarih</small>
                                <strong><?= SecurityHelper::escape(formatEventDateRange($event['start_date'], $event['end_date'])) ?></strong>
                            </span>
                        </li>
                        <?php if (!empty($event['start_time'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-regular fa-clock"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Saat</small>
                                <strong><?= SecurityHelper::escape(formatEventTimeRange($event['start_time'], $event['end_time'])) ?></strong>
                            </span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>İlçe</small>
                                <strong><?= SecurityHelper::escape($event['district']) ?>, <?= SecurityHelper::escape($event['city'] ?: $regionName) ?></strong>
                            </span>
                        </li>
                        <?php if (!empty($event['venue_name'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-building"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Mekân</small>
                                <strong><?= SecurityHelper::escape($event['venue_name']) ?></strong>
                            </span>
                        </li>
                        <?php endif; ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-tag"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Kategori</small>
                                <strong><?= SecurityHelper::escape(getEventCategoryLabel($event['category'])) ?></strong>
                            </span>
                        </li>
                        <?php if (!empty($event['organizer'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-users"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Organizatör</small>
                                <strong><?= SecurityHelper::escape($event['organizer']) ?></strong>
                            </span>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($event['ticket_price'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-ticket"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>Bilet</small>
                                <strong><?= SecurityHelper::escape($event['ticket_price']) ?></strong>
                            </span>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($event['contact_phone'])): ?>
                        <li>
                            <span class="evt-portal-info-list__icon"><i class="fa-solid fa-phone"></i></span>
                            <span class="evt-portal-info-list__body">
                                <small>İletişim</small>
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $event['contact_phone']) ?>"><?= SecurityHelper::escape($event['contact_phone']) ?></a>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <?php if (!empty($event['ticket_url']) && !$isPast): ?>
                        <a href="<?= SecurityHelper::escape($event['ticket_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary w-100 fw-semibold mt-3">
                            <i class="fa-solid fa-ticket me-2"></i> Bilet / Kayıt
                        </a>
                    <?php endif; ?>
                </div>

                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">T</span>
                        <div>
                            <span class="portal-section__eyebrow">Takvim</span>
                            <h3>Diğer Etkinlikler</h3>
                        </div>
                    </header>
                    <p class="evt-portal-sidebar-note"><?= SecurityHelper::escape($regionName) ?> bölgesindeki güncel konser, festival ve kültür programını keşfedin.</p>
                    <a href="<?= seoGetBaseUrl() ?>/etkinlikler" class="btn btn-outline-primary w-100 fw-semibold">
                        <i class="fa-solid fa-calendar-days me-2"></i> Etkinlik Takvimi
                    </a>
                    <a href="<?= seoGetBaseUrl() ?>/etkinlik-basvuru" class="btn btn-outline-primary w-100 fw-semibold mt-2">
                        <i class="fa-solid fa-calendar-plus me-2"></i> Etkinlik Başvurusu
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
