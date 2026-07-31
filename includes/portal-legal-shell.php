<?php
require_once __DIR__ . '/../autoload.php';
if (!function_exists('renderPortalLegalPage')) {
    function renderPortalLegalPage(array $config) {
        $eyebrow = $config['eyebrow'] ?? 'Yasal';
        $title = $config['title'] ?? '';
        $lead = $config['lead'] ?? '';
        $introIcon = $config['intro_icon'] ?? 'fa-shield-halved';
        $introTitle = $config['intro_title'] ?? '';
        $introText = $config['intro_text'] ?? '';
        $sections = $config['sections'] ?? [];
        $footerIcon = $config['footer_icon'] ?? 'fa-envelope-open-text';
        $footerTitle = $config['footer_title'] ?? '';
        $footerText = $config['footer_text'] ?? '';
        $updated = $config['updated'] ?? date('d.m.Y');
        ?>
<header class="directory-portal-hero directory-portal-hero--legal">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow"><?= SecurityHelper::escape($eyebrow) ?></span>
                <h1 class="directory-portal-hero__title"><?= SecurityHelper::escape($title) ?></h1>
                <p class="directory-portal-hero__lead"><?= $lead ?></p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= str_pad((string) count($sections), 2, '0', STR_PAD_LEFT) ?></strong>
                <span>Bölüm</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted legal-portal-main">
    <div class="container">
        <div class="legal-portal-layout">
            <aside class="directory-portal-sidebar reveal-on-scroll">
                <nav class="portal-filter-panel legal-portal-toc" aria-label="İçindekiler">
                    <div class="portal-filter-panel__head">
                        <span class="portal-section__index">İ</span>
                        <div>
                            <span class="portal-section__eyebrow">Doküman</span>
                            <h2 class="portal-filter-panel__title">İçindekiler</h2>
                        </div>
                    </div>
                    <ul class="portal-filter-list legal-portal-toc__list">
                        <?php foreach ($sections as $section): ?>
                            <li><a href="#<?= SecurityHelper::escape($section['id']) ?>"><?= SecurityHelper::escape($section['num']) ?> · <?= SecurityHelper::escape($section['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="legal-portal-toc__updated"><i class="fa-regular fa-calendar"></i> Son güncelleme: <?= SecurityHelper::escape($updated) ?></p>
                </nav>
            </aside>

            <div class="legal-portal-content reveal-on-scroll">
                <article class="legal-document-card legal-portal-document">
                    <div class="legal-document-intro">
                        <div class="legal-document-intro-icon">
                            <i class="fa-solid <?= SecurityHelper::escape($introIcon) ?>"></i>
                        </div>
                        <div>
                            <h2><?= SecurityHelper::escape($introTitle) ?></h2>
                            <p><?= $introText ?></p>
                        </div>
                    </div>

                    <div class="legal-prose legal-portal-prose">
                        <?php foreach ($sections as $section): ?>
                        <article class="legal-section-block biz-portal-panel legal-portal-section" id="<?= SecurityHelper::escape($section['id']) ?>">
                            <header class="biz-portal-panel__head legal-portal-section__head">
                                <span class="portal-section__index"><?= SecurityHelper::escape($section['num']) ?></span>
                                <div>
                                    <span class="portal-section__eyebrow"><i class="fa-solid <?= SecurityHelper::escape($section['icon']) ?>"></i></span>
                                    <h2><?= SecurityHelper::escape($section['title']) ?></h2>
                                </div>
                            </header>
                            <div class="legal-section-body legal-portal-section__body">
                                <?= $section['content'] ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="legal-document-footer">
                        <div class="legal-document-footer-icon">
                            <i class="fa-solid <?= SecurityHelper::escape($footerIcon) ?>"></i>
                        </div>
                        <div>
                            <strong><?= SecurityHelper::escape($footerTitle) ?></strong>
                            <p class="mb-0"><?= $footerText ?></p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>
        <?php
    }
}
