<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/influencer-helpers.php';

$district = trim($_GET['district'] ?? '');
$niche = trim($_GET['niche'] ?? '');
$platform = trim($_GET['platform'] ?? '');
$q = trim($_GET['q'] ?? '');

$niches = influencerNiches();
$districts = influencerDistricts();
$platforms = influencerPlatforms();

$sql = "SELECT * FROM influencers WHERE is_published = 1 AND consent_given = 1";
$params = [];

if ($district !== '') {
    $sql .= " AND district = :district";
    $params[':district'] = $district;
}
if ($niche !== '' && isset($niches[$niche])) {
    $sql .= " AND niche = :niche";
    $params[':niche'] = $niche;
}
if ($platform === 'instagram') {
    $sql .= " AND instagram IS NOT NULL AND instagram != ''";
} elseif ($platform === 'tiktok') {
    $sql .= " AND tiktok IS NOT NULL AND tiktok != ''";
} elseif ($platform === 'youtube') {
    $sql .= " AND youtube IS NOT NULL AND youtube != ''";
}
if ($q !== '') {
    $sql .= " AND (name LIKE :q OR bio LIKE :q OR district LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$sql .= " ORDER BY is_premium DESC, is_verified DESC, name ASC";

$influencers = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $influencers = $stmt->fetchAll();
} catch (Exception $e) {}

$activeNicheName = $niche !== '' ? getInfluencerNicheLabel($niche) : '';
$resultCount = count($influencers);
$hasActiveFilters = $district !== '' || $niche !== '' || $platform !== '' || $q !== '';

if (!empty($activeNicheName) && $district !== '') {
    $heroTitle = SecurityHelper::escape($activeNicheName) . ' — ' . SecurityHelper::escape($district);
} elseif ($district !== '') {
    $heroTitle = SecurityHelper::escape($district) . ' Influencerları';
} elseif (!empty($activeNicheName)) {
    $heroTitle = SecurityHelper::escape($activeNicheName) . ' İçerik Üreticileri';
} elseif ($q !== '') {
    $heroTitle = '"' . SecurityHelper::escape($q) . '" Arama Sonuçları';
} else {
    $heroTitle = 'Influencer & İçerik Üreticileri';
}

require_once 'includes/seo-meta.php';
$pageTitle = seoGetRegionName() . ' Influencer & İçerik Üreticileri' . ($district !== '' ? ' — ' . $district : '');
$_region = seoGetRegionName();
$_regionLow = strtolower($_region);
$metaDescription = $_region . ' influencer rehberi: ' . $_region . ' ilçelerinde doğrulanmış içerik üreticileri. Yemek, gezi, yaşam ve moda fenomenleri.';
$metaKeywords = $_regionLow . ' influencer, ' . $_regionLow . ' fenomen, ' . $_regionLow . ' tiktok, ' . $_regionLow . ' instagram, içerik üretici ' . $_regionLow;
$listingSeo = seoListingPageMeta('/influencerlar', $hasActiveFilters);
$canonicalUrl = $listingSeo['canonical'];
$robotsMeta = $listingSeo['robots'];
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--influencer">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Şehir Dijital Vitrin</span>
                <h1 class="directory-portal-hero__title"><?= $heroTitle ?></h1>
                <p class="directory-portal-hero__lead">Şehir'da içerik üreten doğrulanmış profiller. Takipçi sayıları manuel onaylıdır; iş birliği için güvenilir rehberiniz.</p>
                <div class="directory-portal-hero__actions">
                    <a href="<?= seoGetBaseUrl() ?>/influencer-basvuru" class="btn btn-primary fw-semibold"><i class="fa-solid fa-user-plus me-2"></i> Profil Başvurusu</a>
                    <a href="<?= seoGetBaseUrl() ?>/blog/" class="btn btn-outline-primary fw-semibold"><i class="fa-solid fa-book-open me-2"></i> Influencer Rehberi</a>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Profil</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-magnifying-glass"></i> Influencer Ara & Filtrele</span>
            </div>
            <form action="/influencerlar" method="GET" class="search-dock__form search-dock__form--influencer">
                <div class="search-dock__field">
                    <label for="inf-search-district" class="visually-hidden">İlçe seçin</label>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <select name="district" id="inf-search-district" class="form-select">
                        <option value="">Tüm İlçeler</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= SecurityHelper::escape($dist) ?>" <?= $district === $dist ? 'selected' : '' ?>><?= SecurityHelper::escape($dist) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field">
                    <label for="inf-search-niche" class="visually-hidden">Niş seçin</label>
                    <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                    <select name="niche" id="inf-search-niche" class="form-select">
                        <option value="">Tüm Nişler</option>
                        <?php foreach ($niches as $slug => $label): ?>
                            <option value="<?= SecurityHelper::escape($slug) ?>" <?= $niche === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field">
                    <label for="inf-search-platform" class="visually-hidden">Platform seçin</label>
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    <select name="platform" id="inf-search-platform" class="form-select">
                        <option value="">Tüm Platformlar</option>
                        <?php foreach ($platforms as $pSlug => $pData): ?>
                            <option value="<?= SecurityHelper::escape($pSlug) ?>" <?= $platform === $pSlug ? 'selected' : '' ?>><?= SecurityHelper::escape($pData['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field search-dock__field--grow">
                    <label for="inf-search-keyword" class="visually-hidden">Anahtar kelime</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" name="q" id="inf-search-keyword" class="form-control" placeholder="İsim veya içerik ara…" value="<?= SecurityHelper::escape($q) ?>">
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
                <nav class="portal-filter-panel" aria-label="Influencer filtreleri">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">F</span>
                        <div>
                            <span class="portal-section__eyebrow">Filtreler</span>
                            <h2 class="portal-filter-panel__title">Daralt & Keşfet</h2>
                        </div>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-location-dot"></i> İlçeler</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $district === '' ? 'is-active' : '' ?>">
                                <a href="<?= influencerFilterUrl(['district' => null]) ?>">Tüm İlçeler</a>
                            </li>
                            <?php foreach ($districts as $dist): ?>
                                <li class="<?= $district === $dist ? 'is-active' : '' ?>">
                                    <a href="<?= influencerFilterUrl(['district' => $dist]) ?>"><?= SecurityHelper::escape($dist) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-hashtag"></i> İçerik Nişi</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= $niche === '' ? 'is-active' : '' ?>">
                                <a href="<?= influencerFilterUrl(['niche' => null]) ?>">Tüm Nişler</a>
                            </li>
                            <?php foreach ($niches as $slug => $label): ?>
                                <li class="<?= $niche === $slug ? 'is-active' : '' ?>">
                                    <a href="<?= influencerFilterUrl(['niche' => $slug]) ?>"><?= SecurityHelper::escape($label) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-layer-group"></i> Platform</h3>
                        <ul class="portal-filter-list portal-filter-list--platform">
                            <li class="<?= $platform === '' ? 'is-active' : '' ?>">
                                <a href="<?= influencerFilterUrl(['platform' => null]) ?>"><i class="fa-solid fa-layer-group"></i> Tümü</a>
                            </li>
                            <?php foreach ($platforms as $pSlug => $pData): ?>
                                <li class="<?= $platform === $pSlug ? 'is-active' : '' ?>">
                                    <a href="<?= influencerFilterUrl(['platform' => $pSlug]) ?>"><i class="<?= $pData['icon'] ?>"></i> <?= SecurityHelper::escape($pData['label']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($hasActiveFilters): ?>
                        <a href="<?= seoGetBaseUrl() ?>/influencerlar" class="btn btn-outline-primary w-100 portal-filter-clear">
                            <i class="fa-solid fa-eraser me-2"></i> Tümünü Temizle
                        </a>
                    <?php endif; ?>

                    <div class="portal-trust-card">
                        <h4><i class="fa-solid fa-shield-halved"></i> Güvenilir Liste</h4>
                        <ul>
                            <li>Takipçi sayıları manuel doğrulanır</li>
                            <li>Doğrulanmış profil rozeti</li>
                            <li>İsim kullanımı için onay alınır</li>
                            <li>KVKK kapsamında kaldırma talebi</li>
                        </ul>
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
                                    <a href="<?= influencerFilterUrl(['district' => null]) ?>" aria-label="İlçe filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($niche !== ''): ?>
                                <span class="directory-filter-chip">
                                    Niş: <?= SecurityHelper::escape($activeNicheName) ?>
                                    <a href="<?= influencerFilterUrl(['niche' => null]) ?>" aria-label="Niş filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($platform !== '' && isset($platforms[$platform])): ?>
                                <span class="directory-filter-chip">
                                    Platform: <?= SecurityHelper::escape($platforms[$platform]['label']) ?>
                                    <a href="<?= influencerFilterUrl(['platform' => null]) ?>" aria-label="Platform filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if ($q !== ''): ?>
                                <span class="directory-filter-chip">
                                    Arama: "<?= SecurityHelper::escape($q) ?>"
                                    <a href="<?= influencerFilterUrl(['q' => null]) ?>" aria-label="Arama filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow">Sonuçlar</span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $resultCount ?></strong> doğrulanmış profil listeleniyor</h2>
                    </div>
                </header>

                <?php if (empty($influencers)): ?>
                    <div class="portal-empty directory-portal-empty">
                        <i class="fa-regular fa-user"></i>
                        <h3>Profil Bulunamadı</h3>
                        <p>Filtreleri değiştirin veya profil başvurusu yapın.</p>
                        <a href="<?= seoGetBaseUrl() ?>/influencer-basvuru" class="btn btn-primary">Profil Başvurusu Yap</a>
                    </div>
                <?php else: ?>
                    <div class="portal-influencer-grid reveal-stagger">
                        <?php foreach ($influencers as $i => $inf):
                            $avatar = getInfluencerImageUrl($inf['avatar_path']);
                            $letter = mb_strtoupper(mb_substr($inf['name'], 0, 1, 'UTF-8'), 'UTF-8');
                            $color = !empty($inf['theme_color']) ? SecurityHelper::escape($inf['theme_color']) : '#1F242B';
                            $topFollower = max((int) $inf['follower_instagram'], (int) $inf['follower_tiktok'], (int) $inf['follower_youtube']);
                            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                        <article class="portal-influencer-card <?= $inf['is_premium'] ? 'portal-influencer-card--premium' : '' ?> reveal-on-scroll">
                            <span class="portal-influencer-card__rank"><?= $rank ?></span>
                            <a href="<?= seoGetBaseUrl() ?>/influencer/<?= SecurityHelper::escape($inf['slug']) ?>" class="portal-influencer-card__link">
                                <div class="portal-influencer-card__avatar" style="--inf-color: <?= $color ?>">
                                    <?php if ($avatar): ?>
                                        <img src="<?= SecurityHelper::escape($avatar) ?>" alt="<?= SecurityHelper::escape($inf['name']) ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span><?= $letter ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="portal-influencer-card__body">
                                    <h3><?= SecurityHelper::escape($inf['name']) ?></h3>
                                    <?php if ($inf['is_verified']): ?>
                                        <div class="mb-1"><?= renderInfluencerVerifiedBadge() ?></div>
                                    <?php endif; ?>
                                    <?php if ($inf['is_premium']): ?>
                                        <span class="badge-premium-inline mb-2"><i class="fa-solid fa-crown me-1"></i>Premium</span>
                                    <?php endif; ?>
                                    <p class="portal-influencer-card__niche"><i class="fa-solid fa-hashtag"></i> <?= SecurityHelper::escape(getInfluencerNicheLabel($inf['niche'])) ?></p>
                                    <p class="portal-influencer-card__meta"><i class="fa-solid fa-location-dot"></i> <?= SecurityHelper::escape($inf['district']) ?>, Şehir</p>
                                    <?php if ($topFollower > 0): ?>
                                        <p class="portal-influencer-card__followers"><i class="fa-solid fa-users"></i> <?= formatInfluencerFollowers($topFollower) ?>+ <span>(doğrulanmış)</span></p>
                                    <?php endif; ?>
                                    <?php if (!empty($inf['bio'])): ?>
                                        <p class="portal-influencer-card__bio"><?= SecurityHelper::escape(mb_substr(strip_tags($inf['bio']), 0, 90)) ?>…</p>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="portal-influencer-card__social">
                                <?php foreach ($platforms as $pSlug => $pData):
                                    if (empty($inf[$pData['field']])) continue;
                                ?>
                                    <a href="<?= SecurityHelper::escape($inf[$pData['field']]) ?>" target="_blank" rel="noopener noreferrer" title="<?= SecurityHelper::escape($pData['label']) ?>"><i class="<?= $pData['icon'] ?>"></i></a>
                                <?php endforeach; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
