<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/district-helpers.php';
require_once __DIR__ . '/includes/blog-helpers.php';

$blogListCols = blogListSelectSql();

$search = trim($_GET['search'] ?? $_GET['q'] ?? '');

$blogs = [];
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT {$blogListCols} FROM blogs WHERE title LIKE ? OR summary LIKE ? ORDER BY created_at DESC");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmt = $pdo->query("SELECT {$blogListCols} FROM blogs ORDER BY created_at DESC");
    }
    $blogs = $stmt->fetchAll();
} catch (Exception $e) {}

$recentPosts = [];
try {
    $recentPosts = $pdo->query("SELECT {$blogListCols} FROM blogs ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

$resultCount = count($blogs);
$hasActiveFilters = $search !== '';
$regionName = seoGetRegionName();
$regionLower = mb_strtolower($regionName, 'UTF-8');

if ($search !== '') {
    $heroTitle = '"' . SecurityHelper::escape($search) . '" Arama Sonuçları';
} else {
    $heroTitle = $regionName . ' Rehberi Blogu';
}

$pageTitle = 'Blog';
$metaDescription = $regionName . ' kültürüne dair güncel makaleler, yerel yemek rehberi, gezilecek yerler ve işletme duyuruları blogumuzda.';
$metaKeywords = $regionLower . ' blog, ' . $regionLower . ' gezi rehberi, ' . $regionLower . ' yemekleri, ' . $regionLower . ' makaleleri';
$listingSeo = seoListingPageMeta('/blog', $hasActiveFilters);
$canonicalUrl = $listingSeo['canonical'];
$robotsMeta = $listingSeo['robots'];
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--blog">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Kültür & Rehber</span>
                <h1 class="directory-portal-hero__title"><?= $heroTitle ?></h1>
                <p class="directory-portal-hero__lead">Şehrin meşhur lezzetleri, tarihi çarşıları, saklı koyları ve yerel kültürü hakkında en güncel yazılar.</p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $resultCount ?></strong>
                <span>Yazı</span>
            </div>
        </div>

        <div class="search-dock search-dock--directory reveal-on-scroll">
            <div class="search-dock__head">
                <span class="search-dock__label"><i class="fa-solid fa-magnifying-glass"></i> Blog Ara</span>
            </div>
            <form action="<?= seoGetBaseUrl() ?>/blog" method="GET" class="search-dock__form search-dock__form--blog">
                <div class="search-dock__field search-dock__field--grow">
                    <label for="blog-search-keyword" class="visually-hidden">Makale ara</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="text" name="search" id="blog-search-keyword" class="form-control" placeholder="Başlık veya özet ara…" value="<?= SecurityHelper::escape($search) ?>">
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

            <aside class="directory-portal-sidebar">
                <nav class="portal-filter-panel blog-portal-sidebar" aria-label="Blog menüsü">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">S</span>
                        <div>
                            <span class="portal-section__eyebrow">Son Yazılar</span>
                            <h2 class="portal-filter-panel__title">Gündem</h2>
                        </div>
                    </div>

                    <?php if (empty($recentPosts)): ?>
                        <p class="blog-portal-sidebar__empty">Henüz yayınlanmış yazı yok.</p>
                    <?php else: ?>
                        <ul class="blog-portal-recent">
                            <?php foreach ($recentPosts as $recent):
                                $recentImgUrl = blogResolveImageUrl($recent['image_path'] ?? '', 'thumb');
                            ?>
                                <li>
                                    <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($recent['slug']) ?>" class="blog-portal-recent__item">
                                        <span class="blog-portal-recent__thumb">
                                            <img src="<?= SecurityHelper::escape($recentImgUrl) ?>" alt="" loading="lazy" decoding="async">
                                        </span>
                                        <span class="blog-portal-recent__body">
                                            <strong><?= SecurityHelper::escape($recent['title']) ?></strong>
                                            <small><i class="fa-regular fa-calendar"></i> <?= date('d.m.Y', strtotime($recent['created_at'])) ?></small>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="portal-trust-card">
                        <h4><i class="fa-solid fa-book-open"></i> <?= SecurityHelper::escape($regionName) ?> İlçe Rehberleri</h4>
                        <ul>
                            <?php 
                            $sidebarDistricts = array_slice(function_exists('seoGetSehirDistricts') ? seoGetSehirDistricts() : [], 0, 5);
                            foreach ($sidebarDistricts as $distName): ?>
                                <li><a href="<?= seoGetBaseUrl() ?>/ilce/<?= seoDistrictNameToSlug($distName) ?>"><?= SecurityHelper::escape($distName) ?> İşletme ve Gezi Rehberi</a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </nav>
            </aside>

            <div class="directory-portal-results">
                <?php if ($hasActiveFilters): ?>
                    <div class="directory-active-filters">
                        <span class="directory-active-filters__label">Aktif filtreler</span>
                        <div class="directory-active-filters__chips">
                            <span class="directory-filter-chip">
                                Arama: "<?= SecurityHelper::escape($search) ?>"
                                <a href="<?= seoGetBaseUrl() ?>/blog" aria-label="Arama filtresini kaldır"><i class="fa-solid fa-xmark"></i></a>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow">Yayınlar</span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $resultCount ?></strong> makale listeleniyor</h2>
                    </div>
                </header>

                <?php if (empty($blogs)): ?>
                    <div class="portal-empty directory-portal-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        <h3>Makale Bulunamadı</h3>
                        <p>Arama kriterlerinize uygun yazı bulunamadı. Farklı bir anahtar kelime deneyin.</p>
                        <a href="<?= seoGetBaseUrl() ?>/blog" class="btn btn-primary">Tüm Yazılar</a>
                    </div>
                <?php else: ?>
                    <div class="portal-blog-grid portal-blog-grid--listing">
                        <?php foreach ($blogs as $i => $post):
                            $imgUrl = blogResolveImageUrl($post['image_path'] ?? '', 'card');
                            $rank = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                        <article class="portal-blog-card portal-blog-card--listing">
                            <span class="portal-blog-card__rank"><?= $rank ?></span>
                            <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($post['slug']) ?>" class="portal-blog-card__media">
                                <img src="<?= SecurityHelper::escape($imgUrl) ?>" alt="<?= SecurityHelper::escape($post['title']) ?>" loading="lazy" decoding="async">
                            </a>
                            <div class="portal-blog-card__body">
                                <time datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>">
                                    <i class="fa-regular fa-calendar"></i> <?= date('d.m.Y', strtotime($post['created_at'])) ?>
                                </time>
                                <h3><a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($post['slug']) ?>"><?= SecurityHelper::escape($post['title']) ?></a></h3>
                                <p><?= SecurityHelper::escape($post['summary']) ?></p>
                                <a href="<?= seoGetBaseUrl() ?>/blog/<?= SecurityHelper::escape($post['slug']) ?>" class="portal-blog-card__link">Devamını Oku <i class="fa-solid fa-arrow-right"></i></a>
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
