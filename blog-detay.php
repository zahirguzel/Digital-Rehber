<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/blog-helpers.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: /blog');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM blogs WHERE slug = ?');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: /blog');
        exit;
    }
} catch (Exception $e) {
    header('Location: /blog');
    exit;
}

$pageTitle = $post['title'];
$metaDescription = $post['meta_description'] ?: $post['summary'];
require_once __DIR__ . '/includes/seo-meta.php';
$_region = seoGetRegionName();
$_siteTitle = seoGetSiteTitle();
$metaKeywords = $post['meta_keywords'] ?: strtolower($_region) . ' rehber, ' . strtolower($_region) . ' blog, ' . strtolower($_siteTitle);
// seo-meta.php already included above
$canonicalUrl = seoGetBaseUrl() . '/blog/' . $post['slug'];
$postImg = $post['image_path'] ?: 'default_cover.jpg';
$imgUrl = blogResolveImageUrl($postImg, 'full');
$ogImage = $imgUrl;
$ogType = 'article';
$ogImageAlt = $post['title'];

$schemaSiteSettings = ['site_title' => seoGetSiteTitle(), 'site_logo' => ''];
try {
    $settingsRow = $pdo->query('SELECT site_title, site_logo FROM settings WHERE id = 1')->fetch();
    if ($settingsRow) {
        $schemaSiteSettings = $settingsRow;
    }
} catch (Exception $e) {}

$schemaArticle = seoBuildArticleSchema(
    $post,
    $schemaSiteSettings,
    seoGetBaseUrl(),
    $canonicalUrl,
    seoResolveAbsoluteUrl($ogImage, seoGetBaseUrl())
);

$recentPosts = [];
try {
    $blogListCols = blogListSelectSql();
    $stmtRecent = $pdo->prepare("SELECT {$blogListCols} FROM blogs WHERE id != ? ORDER BY created_at DESC LIMIT 5");
    $stmtRecent->execute([$post['id']]);
    $recentPosts = $stmtRecent->fetchAll();
} catch (Exception $e) {}

$sidebarAd = null;
try {
    $stmtAd = $pdo->query("SELECT * FROM advertisements WHERE active = 1 AND position = 'sidebar' ORDER BY RAND() LIMIT 1");
    $sidebarAd = $stmtAd->fetch();
} catch (Exception $e) {}

$shareUrl = $canonicalUrl;
require_once 'includes/header.php';
?>
<script type="application/ld+json"><?= json_encode($schemaArticle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="biz-portal-hero blog-portal-hero" style="--biz-cover: url('<?= SecurityHelper::escape($imgUrl) ?>');">
    <div class="biz-portal-hero__overlay" aria-hidden="true"></div>
    <div class="biz-portal-hero__backdrop" aria-hidden="true">
        <div class="biz-portal-hero__panel biz-portal-hero__panel--guide"></div>
        <div class="biz-portal-hero__panel biz-portal-hero__panel--media"></div>
    </div>
    <div class="container biz-portal-hero__inner">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="/">Ana Sayfa</a>
            <span aria-hidden="true">/</span>
            <a href="<?= seoGetBaseUrl() ?>/blog">Blog</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current"><?= SecurityHelper::escape($post['title']) ?></span>
        </nav>
    </div>
</header>

<div class="container blog-portal-head-wrap">
    <article class="blog-portal-head reveal-on-scroll">
        <div class="blog-portal-head__meta">
            <span class="blog-portal-head__badge">Rehber Yazısı</span>
            <time datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>">
                <i class="fa-regular fa-calendar"></i> <?= date('d.m.Y', strtotime($post['created_at'])) ?>
            </time>
        </div>
        <h1 class="blog-portal-head__title"><?= SecurityHelper::escape($post['title']) ?></h1>
        <?php if (!empty($post['summary'])): ?>
            <p class="blog-portal-head__lead"><?= SecurityHelper::escape($post['summary']) ?></p>
        <?php endif; ?>
    </article>
</div>

<section class="portal-section portal-section--muted blog-portal-main">
    <div class="container">
        <div class="biz-portal-layout">

            <div class="biz-portal-content">
                <article class="biz-portal-panel blog-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">01</span>
                        <div>
                            <span class="portal-section__eyebrow">İçerik</span>
                            <h2>Makale</h2>
                        </div>
                    </header>
                    <div class="blog-portal-panel__cover">
                        <img src="<?= SecurityHelper::escape($imgUrl) ?>" alt="<?= SecurityHelper::escape($post['title']) ?>" loading="lazy" decoding="async">
                    </div>
                    <div class="blog-portal-panel__body blog-content">
                        <?php 
                            $contentHtml = $post['content'] ?? '';
                            if (strip_tags($contentHtml) === $contentHtml) {
                                $paragraphs = preg_split('/\n\s*\n/', trim($contentHtml));
                                foreach ($paragraphs as $p) {
                                    echo '<p style="margin-bottom: 1.25rem; line-height: 1.8;">' . nl2br(htmlspecialchars(trim($p))) . '</p>';
                                }
                            } else {
                                echo $contentHtml;
                            }
                        ?>
                    </div>
                    <footer class="blog-portal-panel__footer">
                        <a href="<?= seoGetBaseUrl() ?>/blog" class="btn btn-outline-primary fw-semibold">
                            <i class="fa-solid fa-arrow-left me-2"></i> Diğer Yazılar
                        </a>
                        <div class="blog-portal-share">
                            <span>Paylaş</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook'ta paylaş"><i class="fa-brands fa-facebook-f"></i></a>
                            <a href="https://x.com/intent/post?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener noreferrer" aria-label="X'te paylaş" class="blog-portal-share__x"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a>
                            <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . $shareUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp'ta paylaş"><i class="fa-brands fa-whatsapp"></i></a>
                        </div>
                    </footer>
                </article>
            </div>

            <aside class="biz-portal-sidebar reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">S</span>
                        <div>
                            <span class="portal-section__eyebrow">Blog</span>
                            <h3>Son Yazılar</h3>
                        </div>
                    </header>
                    <?php if (empty($recentPosts)): ?>
                        <p class="blog-portal-sidebar__empty">Başka yazı bulunamadı.</p>
                    <?php else: ?>
                        <ul class="blog-portal-recent blog-portal-recent--sidebar">
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
                    <a href="<?= seoGetBaseUrl() ?>/blog" class="btn btn-outline-primary w-100 fw-semibold mt-3">
                        <i class="fa-solid fa-newspaper me-2"></i> Tüm Yazılar
                    </a>
                </div>

                <?php if ($sidebarAd):
                    $adImgUrl = (strpos($sidebarAd['image_path'], 'http') === 0) ? $sidebarAd['image_path'] : '/public/images/' . $sidebarAd['image_path'];
                ?>
                <div class="directory-portal-ad biz-portal-widget">
                    <span class="directory-portal-ad__label">Sponsorlu</span>
                    <a href="<?= SecurityHelper::escape($sidebarAd['target_url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" class="directory-portal-ad__link">
                        <img src="<?= SecurityHelper::escape($adImgUrl) ?>" alt="<?= SecurityHelper::escape($sidebarAd['title'] ?? 'Reklam') ?>" loading="lazy" decoding="async">
                    </a>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
