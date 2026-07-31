<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/seo-meta.php';

$activeSlug = trim($_GET['slug'] ?? '');

$pageTitle = 'Hizmetlerimiz';
$metaDescription = seoGetRegionName() . "'daki esnaflarımız için sunduğumuz Google Harita kaydı, sosyal medya yönetimi, yapay zeka görsel tasarımı, QR kod menü ve web tasarım hizmetleri.";
$metaKeywords = 'google harita kaydı ' . strtolower(seoGetRegionName()) . ', sosyal medya yönetimi, yapay zeka görsel üretimi, qr menü, dijital kartvizit, ' . strtolower(seoGetSiteTitle());
$canonicalUrl = seoGetBaseUrl() . '/hizmetlerimiz' . ($activeSlug !== '' ? '/' . $activeSlug : '');
require_once 'includes/header.php';

$serviceCount = count($services);
?>

<header class="directory-portal-hero directory-portal-hero--services">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow"><?= SecurityHelper::escape(seoGetSiteTitle()) ?></span>
                <h1 class="directory-portal-hero__title">Dijital Büyüme Hizmetleri</h1>
                <p class="directory-portal-hero__lead">Google Harita kaydından QR menüye, sosyal medyadan premium vitrine — işletmenizi dijital dünyada öne çıkaracak uçtan uca çözümler sunuyoruz.</p>
                <div class="directory-portal-hero__actions">
                    <a href="<?= SecurityHelper::escape($siteUrl('iletisim')) ?>" class="btn btn-primary fw-semibold"><i class="fa-solid fa-paper-plane me-2"></i> Teklif Alın</a>
                    <a href="<?= SecurityHelper::escape($siteUrl('iletisim?subject=' . urlencode('Yeni İşletme Kaydı'))) ?>" class="btn btn-outline-primary fw-semibold"><i class="fa-solid fa-store me-2"></i> İşletme Kaydı</a>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= (int) $serviceCount ?></strong>
                <span>Hizmet</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted srv-portal-main">
    <div class="container">
        <div class="directory-portal-layout">

            <aside class="directory-portal-sidebar reveal-on-scroll">
                <nav class="portal-filter-panel srv-portal-nav" aria-label="Hizmet menüsü">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">M</span>
                        <div>
                            <span class="portal-section__eyebrow">Menü</span>
                            <h2 class="portal-filter-panel__title">Hizmet Kataloğu</h2>
                        </div>
                    </div>

                    <ul class="portal-filter-list srv-portal-nav__list">
                        <?php foreach ($services as $i => $srv):
                            $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        ?>
                            <li class="<?= $activeSlug === $srv['slug'] ? 'is-active' : '' ?>">
                                <a href="<?= SecurityHelper::escape($siteUrl('hizmetlerimiz/' . $srv['slug'])) ?>">
                                    <span class="srv-portal-nav__num"><?= $num ?></span>
                                    <i class="<?= SecurityHelper::escape($srv['icon']) ?>"></i>
                                    <?= SecurityHelper::escape($srv['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="portal-trust-card">
                        <h4><i class="fa-solid fa-handshake"></i> Neden Biz?</h4>
                        <ul>
                            <li><?= SecurityHelper::escape(seoGetRegionName()) ?> yerel pazarını iyi biliyoruz</li>
                            <li>Rehber + medya tek çatı altında</li>
                            <li>Esnaflara özel paketler</li>
                            <li>Ölçülebilir dijital sonuçlar</li>
                        </ul>
                        <a href="<?= SecurityHelper::escape($siteUrl('iletisim')) ?>" class="btn btn-primary btn-sm w-100 mt-3"><i class="fa-solid fa-envelope me-1"></i> İletişime Geçin</a>
                    </div>
                </nav>
            </aside>

            <div class="directory-portal-results reveal-on-scroll">
                <header class="directory-results-head">
                    <div>
                        <span class="portal-section__eyebrow">Çözümler</span>
                        <h2 class="directory-results-head__title"><strong><?= (int) $serviceCount ?></strong> dijital hizmet paketi</h2>
                    </div>
                </header>

                <div class="portal-services-grid reveal-stagger">
                    <?php foreach ($services as $i => $srv):
                        $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                        $isHighlighted = $activeSlug === $srv['slug'];
                    ?>
                    <article class="portal-service-card <?= $isHighlighted ? 'portal-service-card--active' : '' ?> reveal-on-scroll" id="<?= SecurityHelper::escape($srv['slug']) ?>">
                        <span class="portal-service-card__num"><?= $num ?></span>
                        <div class="portal-service-card__icon"><i class="<?= SecurityHelper::escape($srv['icon']) ?>"></i></div>
                        <div class="portal-service-card__body">
                            <h3><?= SecurityHelper::escape($srv['title']) ?></h3>
                            <p><?= SecurityHelper::escape($srv['description']) ?></p>
                        </div>
                        <div class="portal-service-card__footer">
                            <?php
                            $ctaType = $srv['cta_type'] ?? 'iletisim';
                            $ctaUrl  = $srv['cta_url'] ?? '';
                            if ($ctaType === 'whatsapp' && !empty($ctaUrl)):
                            ?>
                                <a href="<?= SecurityHelper::escape($ctaUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary portal-service-card__btn">
                                    <i class="fa-brands fa-whatsapp me-1"></i> Hemen Bilgi Al
                                </a>
                            <?php else: ?>
                                <a href="<?= SecurityHelper::escape($siteUrl('iletisim?subject=' . urlencode($srv['subject']))) ?>" class="btn btn-primary portal-service-card__btn">
                                    Hemen Bilgi Al <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="portal-section portal-section--light srv-portal-cta">
    <div class="container">
        <div class="srv-portal-cta__grid reveal-on-scroll">
            <div class="srv-portal-cta__panel srv-portal-cta__panel--guide">
                <span class="srv-portal-cta__tag">Rehber</span>
                <h2>İşletmenizi Listeye Ekleyin</h2>
                <p><?= SecurityHelper::escape(seoGetSiteTitle()) ?>'de ücretsiz kayıt olun; müşteriler sizi kolayca bulsun.</p>
                <a href="<?= SecurityHelper::escape($siteUrl('iletisim?subject=' . urlencode('Yeni İşletme Kaydı'))) ?>" class="btn btn-light fw-semibold">Ücretsiz Kayıt</a>
            </div>
            <div class="srv-portal-cta__panel srv-portal-cta__panel--media">
                <span class="srv-portal-cta__tag">Medya</span>
                <h2>Özel Teklif İsteyin</h2>
                <p>İhtiyacınıza göre paket hazırlayalım; dijital büyüme planınızı birlikte oluşturalım.</p>
                <a href="<?= SecurityHelper::escape($siteUrl('iletisim')) ?>" class="btn btn-primary fw-semibold">Teklif Alın</a>
            </div>
        </div>
    </div>
</section>

<?php if ($activeSlug !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const slug = <?= json_encode($activeSlug) ?>;
    const element = document.getElementById(slug);
    if (!element) return;
    setTimeout(function() {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        element.classList.add('portal-service-card--pulse');
        setTimeout(function() {
            element.classList.remove('portal-service-card--pulse');
        }, 2600);
    }, 150);
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
