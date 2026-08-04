<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/district-helpers.php';

function getFilterUrl($updates) {
    $params = $_GET;
    foreach ($updates as $key => $val) {
        if ($val === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }
    $def = function_exists('seoGetDefaultCity') ? seoGetDefaultCity() : '';
    if (isset($params['city']) && $params['city'] === $def) {
        unset($params['city']);
    }
    $query = http_build_query(array_filter($params, function($v) {
        return $v !== '' && $v !== null;
    }));
    return 'esnaflar' . ($query !== '' ? '?' . $query : '');
}

$city = trim((string) ($_GET['city'] ?? ''));
if ($city === 'Şehir') $city = '';
$district = trim((string) ($_GET['district'] ?? ''));
$categorySlug = $_GET['category'] ?? '';
$q = $_GET['q'] ?? '';
$defaultCity = seoGetDefaultCity();
$cities = seoGetCities();

if ($city === '' && $defaultCity !== '') {
    $city = $defaultCity;
}

if ($city !== '' && $city !== $defaultCity && $defaultCity !== '') {
    $cityBizCount = 0;
    try {
        $cityBizCount = (int) $pdo->query("SELECT COUNT(*) FROM businesses WHERE city = " . $pdo->quote($city) . " AND is_deleted = 0")->fetchColumn();
    } catch (Exception $e) {}
    if ($cityBizCount === 0) {
        $city = $defaultCity;
    }
}

if ($city !== '' && !in_array($city, $cities, true)) {
    $cities[] = $city;
    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
}

if ($district !== '') {
    $districtCity = seoFindCityByDistrict($district, $city);
    if ($districtCity !== '') {
        $city = $districtCity;
    }
}

$_GET['city'] = $city;
$_GET['district'] = $district;

$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {}

$activeCategoryName = '';
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) {
        $activeCategoryName = $cat['name'];
        break;
    }
}

$districts = !empty($city) ? seoGetDistrictsByCity($city) : seoGetŞehirDistricts();

// İşletme sorgusu — ortalama puan join ile
$sql = "SELECT b.*, c.name as category_name, c.slug as category_slug,
               COALESCE(r.avg_rating, 0) as avg_rating,
               COALESCE(r.review_count, 0) as review_count
        FROM businesses b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN (
            SELECT business_id, AVG(rating) as avg_rating, COUNT(*) as review_count
            FROM reviews WHERE status = 'approved'
            GROUP BY business_id
        ) r ON b.id = r.business_id";
$whereClause = "";
$params = [];
if (!empty($city)) {
    $whereClause .= " WHERE b.city = :city AND b.is_deleted = 0";
    $params[':city'] = $city;
} else {
    $whereClause .= " WHERE b.is_deleted = 0";
}
if (!empty($district)) {
    $whereClause .= " AND b.district = :district";
    $params[':district'] = $district;
}
if (!empty($categorySlug)) {
    $whereClause .= " AND c.slug = :category_slug";
    $params[':category_slug'] = $categorySlug;
}
if (!empty($q)) {
    $whereClause .= " AND (b.name LIKE :q OR b.description LIKE :q OR b.address LIKE :q)";
    $params[':q'] = "%$q%";
}

$countSql = "SELECT COUNT(*) FROM businesses b LEFT JOIN categories c ON b.category_id = c.id" . $whereClause;
$totalRecords = 0;
try {
    $cStmt = $pdo->prepare($countSql);
    $cStmt->execute($params);
    $totalRecords = (int) $cStmt->fetchColumn();
} catch (Exception $e) {}

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql .= $whereClause . " ORDER BY b.is_premium DESC, avg_rating DESC, b.name ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$businesses = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll();
} catch (Exception $e) {}

$sidebarAds = [];
try {
    $stmtAds = $pdo->query("SELECT * FROM advertisements WHERE active = 1 AND position = 'sidebar' ORDER BY id DESC");
    $sidebarAds = $stmtAds->fetchAll();
} catch (Exception $e) {}

$resultCount = $totalRecords;
$hasActiveFilters = !empty($district) || !empty($categorySlug) || !empty($q) || (!empty($city) && $city !== $defaultCity);

if (!empty($activeCategoryName) && !empty($district)) {
    $heroTitle = SecurityHelper::escape($activeCategoryName) . ' — ' . SecurityHelper::escape($district);
} elseif (!empty($district)) {
    $heroTitle = SecurityHelper::escape($district) . ' İşletmeleri';
} elseif (!empty($activeCategoryName)) {
    $heroTitle = SecurityHelper::escape($activeCategoryName) . ' Firmaları';
} elseif (!empty($q)) {
    $heroTitle = '"' . SecurityHelper::escape($q) . '" Arama Sonuçları';
} else {
    $heroTitle = 'Tüm İşletmeler & Firmalar';
}

if (!empty($activeCategoryName) && !empty($district)) {
    $pageTitle = $activeCategoryName . ' Firmaları - ' . $district;
} elseif (!empty($activeCategoryName)) {
    $pageTitle = $activeCategoryName . ' Firmaları';
} elseif (!empty($district)) {
    $pageTitle = $district . ' İşletmeleri';
} elseif (!empty($q)) {
    $pageTitle = 'Arama: ' . $q;
} else {
    $pageTitle = 'İşletmeler ve Firmalar';
}

$metaDescription = (!empty($city) ? $city . ' ' : 'Şehir ') . (!empty($district) ? $district . ' ilçesindeki ' : '') . (!empty($activeCategoryName) ? $activeCategoryName : 'tüm yerel işletme ve firmaların') . ' adres, telefon, whatsapp ve detaylı yol tarifi bilgileri.';
$metaKeywords = 'işletmeler' . (!empty($city) ? ', ' . $city : '') . (!empty($district) ? ', ' . $district : '') . (!empty($activeCategoryName) ? ', ' . $activeCategoryName : '');
$listingSeo = seoListingPageMeta('/esnaflar', $hasActiveFilters);
$canonicalUrl = $listingSeo['canonical'];
$robotsMeta = $listingSeo['robots'];
require_once 'includes/header.php';
?>

<header class="directory-portal-hero">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Firma Rehberi</span>
                <h1 class="directory-portal-hero__title"><?= $heroTitle ?></h1>
                <p class="directory-portal-hero__lead">Kayıtlı işletmeleri ilçe, kategori veya isimle filtreleyin. Hemen iletişime geçin.</p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Sonuç</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-magnifying-glass"></i> İşletme Ara & Filtrele</span>
            </div>
            <form action="esnaflar.php" method="GET" class="search-dock__form">
                <?php if (!empty($defaultCity)): ?>
                <input type="hidden" name="city" value="<?= SecurityHelper::escape($defaultCity) ?>">
                <?php endif; ?>

                <div class="search-dock__field">
                    <label for="directory-search-district" class="visually-hidden">İlçe seçin</label>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <select name="district" id="directory-search-district" class="form-select" onchange="this.form.submit()">
                        <option value="">Tüm İlçeler</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= SecurityHelper::escape($dist) ?>" <?= $district === $dist ? 'selected' : '' ?>><?= SecurityHelper::escape($dist) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field">
                    <label for="directory-search-category" class="visually-hidden">Kategori seçin</label>
                    <i class="fa-solid fa-tags" aria-hidden="true"></i>
                    <select name="category" id="directory-search-category" class="form-select" onchange="this.form.submit()">
                        <option value="">Tüm Sektörler</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= SecurityHelper::escape($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'selected' : '' ?>><?= SecurityHelper::escape($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-dock__field search-dock__field--grow">
                    <label for="directory-search-keyword" class="visually-hidden">Anahtar kelime</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" name="q" id="directory-search-keyword" class="form-control" placeholder="Firma, hizmet ara…" value="<?= SecurityHelper::escape($q) ?>">
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

            <!-- Sidebar Filtreler -->
            <!-- Mobil filtre toggle butonu -->
            <button class="mobile-filter-toggle" id="mobileFilterToggle" aria-expanded="false" aria-controls="directoryFilterSidebar">
                <span><i class="fa-solid fa-sliders me-2"></i> Filtrele &amp; Sırala</span>
                <i class="fa-solid fa-chevron-down toggle-icon"></i>
            </button>
            <aside class="directory-portal-sidebar reveal-on-scroll" id="directoryFilterSidebar">
                <?php if (!empty($sidebarAds)): ?>
                    <div class="directory-portal-ad">
                        <span class="directory-portal-ad__label">Sponsorlu Bağlantı</span>
                        <?php foreach ($sidebarAds as $ad):
                            if (empty($ad['image_path'])) continue;
                            $adImg = (strpos($ad['image_path'], 'http') === 0) ? $ad['image_path'] : seoGetBaseUrl() . '/public/images/' . ltrim($ad['image_path'], '/');
                        ?>
                            <a href="<?= SecurityHelper::escape($ad['target_url'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" class="directory-portal-ad__link">
                                <img src="<?= SecurityHelper::escape($adImg) ?>" alt="<?= SecurityHelper::escape($ad['title'] ?: 'Şehir Rehberi Reklam') ?>" loading="lazy" decoding="async">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="directory-portal-ad directory-portal-ad--empty">
                        <span class="directory-portal-ad__label">Reklam Alanı</span>
                        <a href="<?= seoGetBaseUrl() ?>/iletisim" class="directory-portal-ad__link d-flex flex-column align-items-center justify-content-center text-center p-4" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b; text-decoration: none; min-height: 200px; transition: all 0.3s;">
                            <i class="fa-solid fa-rectangle-ad fs-1 mb-2 text-primary" style="opacity: 0.5;"></i>
                            <strong class="text-dark d-block mb-1">Buraya Reklam Verebilirsiniz</strong>
                            <small>Detaylı bilgi için tıklayın</small>
                        </a>
                    </div>
                <?php endif; ?>

                <nav class="portal-filter-panel" aria-label="Filtreler">
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
                            <li class="<?= empty($district) ? 'is-active' : '' ?>">
                                <a href="<?= getFilterUrl(['district' => null]) ?>">Tüm İlçeler</a>
                            </li>
                            <?php foreach ($districts as $dist): ?>
                                <li class="<?= $district === $dist ? 'is-active' : '' ?>">
                                    <a href="<?= getFilterUrl(['district' => $dist]) ?>"><?= SecurityHelper::escape($dist) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="portal-filter-group">
                        <h3 class="portal-filter-group__title"><i class="fa-solid fa-tags"></i> Kategoriler</h3>
                        <ul class="portal-filter-list">
                            <li class="<?= empty($categorySlug) ? 'is-active' : '' ?>">
                                <a href="<?= getFilterUrl(['category' => null]) ?>">Tüm Sektörler</a>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                                <li class="<?= $categorySlug === $cat['slug'] ? 'is-active' : '' ?>">
                                    <a href="<?= getFilterUrl(['category' => $cat['slug']]) ?>"><?= SecurityHelper::escape($cat['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($hasActiveFilters): ?>
                        <a href="esnaflar" class="btn btn-outline-primary w-100 portal-filter-clear">
                            <i class="fa-solid fa-eraser me-2"></i> Tümünü Temizle
                        </a>
                    <?php endif; ?>
                </nav>
            </aside>

            <!-- Sonuç Listesi -->
            <div class="directory-portal-results reveal-on-scroll">
                <?php if ($hasActiveFilters): ?>
                    <div class="directory-active-filters">
                        <span class="directory-active-filters__label">Aktif filtreler</span>
                        <div class="directory-active-filters__chips">
                            <?php if (!empty($district)): ?>
                                <span class="directory-filter-chip">
                                    İlçe: <?= SecurityHelper::escape($district) ?>
                                    <a href="<?= getFilterUrl(['district' => null]) ?>" aria-label="İlçe filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($city) && $city !== $defaultCity): ?>
                                <span class="directory-filter-chip">
                                    Şehir: <?= SecurityHelper::escape($city) ?>
                                    <a href="<?= getFilterUrl(['city' => null]) ?>" aria-label="Şehir filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($categorySlug)): ?>
                                <span class="directory-filter-chip">
                                    Kategori: <?= SecurityHelper::escape($activeCategoryName) ?>
                                    <a href="<?= getFilterUrl(['category' => null]) ?>" aria-label="Kategori filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($q)): ?>
                                <span class="directory-filter-chip">
                                    Arama: "<?= SecurityHelper::escape($q) ?>"
                                    <a href="<?= getFilterUrl(['q' => null]) ?>" aria-label="Arama filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow">Sonuçlar</span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $resultCount ?></strong> işletme listeleniyor</h2>
                    </div>
                </header>

                <?php if (empty($businesses)): ?>
                    <div class="portal-empty directory-portal-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        <h3>Sonuç Bulunamadı</h3>
                        <p>Arama kriterlerinize uyan bir işletme bulunamadı. Filtreleri değiştirmeyi veya farklı bir arama terimi denemeyi deneyin.</p>
                        <a href="esnaflar" class="btn btn-primary"><i class="fa-solid fa-rotate-left me-2"></i> Aramayı Sıfırla</a>
                    </div>
                <?php else: ?>
                    <div class="portal-directory-list reveal-stagger">
                        <?php foreach ($businesses as $i => $biz):
                            $bizLogo = '';
                            if (!empty($biz['logo_path']) && $biz['logo_path'] !== 'default_logo.png') {
                                $p = ltrim($biz['logo_path'], '/');
                                if (strpos($p, 'http') === 0) {
                                    $bizLogo = $p;
                                } elseif (strpos($p, 'public/images/') === 0) {
                                    $bizLogo = $p;
                                } else {
                                    $bizLogo = 'public/images/' . $p;
                                }
                            }
                            $bizLetter = mb_strtoupper(mb_substr($biz['name'], 0, 1, 'UTF-8'), 'UTF-8');
                            $bizColor  = !empty($biz['theme_color']) ? htmlspecialchars($biz['theme_color']) : '#1F242B';
                            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                        <article class="portal-directory-item <?= $biz['is_premium'] ? 'portal-directory-item--premium' : '' ?> reveal-on-scroll">
                            <span class="portal-directory-item__rank"><?= $rank ?></span>

                            <div class="portal-directory-item__logo">
                                <?php if (!empty($bizLogo)): ?>
                                    <img src="<?= htmlspecialchars($bizLogo) ?>"
                                         alt="<?= htmlspecialchars($biz['name']) ?>"
                                         loading="lazy"
                                         decoding="async"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div class="portal-directory-item__letter" style="display:none;background:<?= $bizColor ?>;"><?= $bizLetter ?></div>
                                <?php else: ?>
                                    <div class="portal-directory-item__letter" style="background:<?= $bizColor ?>;"><?= $bizLetter ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="portal-directory-item__body">
                                <div class="portal-directory-item__head">
                                    <h3><a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" target="_blank" rel="noopener noreferrer"><?= SecurityHelper::escape($biz['name']) ?></a></h3>
                                    <?php if ($biz['is_premium']): ?>
                                        <span class="badge-premium-inline"><i class="fa-solid fa-crown me-1"></i>Premium</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($biz['description'])): ?>
                                    <p class="portal-directory-item__desc"><?= htmlspecialchars($biz['description']) ?></p>
                                <?php endif; ?>
                                <ul class="portal-directory-item__meta">
                                    <li><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($biz['category_name']) ?></li>
                                    <li><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($biz['district']) ?><?php if (!empty($biz['city'])): ?>, <?= htmlspecialchars($biz['city']) ?><?php endif; ?></li>
                                    <?php if (!empty($biz['phone'])): ?>
                                        <li><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($biz['phone']) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="portal-directory-item__actions">
                                <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm fw-semibold">
                                    Profil <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                                <?php if (!empty($biz['phone'])): ?>
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $biz['phone']) ?>" class="btn btn-call-premium btn-sm"><i class="fa-solid fa-phone"></i> Ara</a>
                                <?php endif; ?>
                                <?php if (!empty($biz['whatsapp'])): ?>
                                    <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $biz['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp-premium btn-sm"><i class="fa-brands fa-whatsapp"></i></a>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Sayfalama" class="mt-5 d-flex justify-content-center">
                        <ul class="pagination pagination-md shadow-sm" style="gap: 6px;">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link rounded px-3 py-2 fw-semibold text-dark border" href="<?= getFilterUrl(['page' => $page - 1]) ?>" aria-label="Önceki" style="text-decoration: none;">
                                        <i class="fa-solid fa-chevron-left me-1"></i> Önceki
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link rounded px-3 py-2 fw-bold <?= $p === $page ? 'bg-danger border-danger text-white' : 'text-dark border' ?>" href="<?= getFilterUrl(['page' => $p]) ?>" style="text-decoration: none;">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link rounded px-3 py-2 fw-semibold text-dark border" href="<?= getFilterUrl(['page' => $page + 1]) ?>" aria-label="Sonraki" style="text-decoration: none;">
                                        Sonraki <i class="fa-solid fa-chevron-right ms-1"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- --- PREMIUM VIP CTA BANNER (İŞLETMENİZİ EKLEYİN) --- -->
<section class="portal-section py-4">
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
            $vipWaUrl = !empty($vipWaPhone) ? ('https://wa.me/' . $vipWaPhone . '?text=' . urlencode('İşletmemi ekletmek istiyorum bilgi alabilir miyim?')) : seoResolveAbsoluteUrl('iletisim.php', seoGetBaseUrl());
            ?>
            <div class="home-vip-cta__actions">
                <a href="<?= seoResolveAbsoluteUrl('iletisim.php', seoGetBaseUrl()) ?>" class="home-vip-cta__btn-primary">
                    <i class="fa-solid fa-store"></i> Hemen İşletmeni Ekle
                </a>
                <a href="<?= $vipWaUrl ?>" class="home-vip-cta__btn-secondary" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i> Bilgi Al
                </a>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('mobileFilterToggle');
    const sidebar = document.getElementById('directoryFilterSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            const isOpen = sidebar.classList.toggle('is-open');
            toggleBtn.classList.toggle('is-open', isOpen);
            toggleBtn.setAttribute('aria-expanded', isOpen);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
