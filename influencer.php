<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/influencer-helpers.php';
use App\Services\EmailService;

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /influencerlar');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE slug = ? AND is_published = 1 AND consent_given = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $influencer = $stmt->fetch();
} catch (Exception $e) {
    $influencer = false;
}

if (!$influencer) {
    header('HTTP/1.0 404 Not Found');
    header('Location: /404');
    exit;
}

$linkedBusinesses = [];
try {
    $stmtBiz = $pdo->prepare("SELECT b.*, c.name AS category_name FROM influencer_business_links ibl JOIN businesses b ON b.id = ibl.business_id LEFT JOIN categories c ON c.id = b.category_id WHERE ibl.influencer_id = ? ORDER BY b.name ASC");
    $stmtBiz->execute([$influencer['id']]);
    $linkedBusinesses = $stmtBiz->fetchAll();
} catch (Exception $e) {}

$collabSuccess = '';
$collabError = '';
$collabTypes = influencerCollabTypes();
$platforms = influencerPlatforms();
$parsedCollab = parseInfluencerCollabTypes($influencer['collaboration_types']);
$featuredLinks = parseInfluencerFeaturedLinks($influencer['featured_links']);

$captchaActive = 1;
try {
    $captchaActive = (int) $pdo->query("SELECT contact_captcha FROM settings WHERE id = 1")->fetchColumn();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collab_submit'])) {
    $businessName = trim($_POST['business_name'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $collabType = trim($_POST['collab_type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
    $correctAnswer = isset($_SESSION['inf_captcha_result']) ? (int) $_SESSION['inf_captcha_result'] : -2;

    if ($businessName === '' || $contactName === '' || $email === '' || $message === '') {
        $collabError = 'Lütfen zorunlu alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $collabError = 'Geçerli bir e-posta adresi girin.';
    } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
        $collabError = 'Güvenlik doğrulaması hatalı. Lütfen tekrar deneyin.';
    } else {
        try {
            $ins = $pdo->prepare("INSERT INTO influencer_collaboration_requests (influencer_id, business_name, contact_name, email, phone, collab_type, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$influencer['id'], $businessName, $contactName, $email, $phone, $collabType, $message]);
            require_once __DIR__ . '/includes/telegram-notify.php';
            $collabData = [
                'business_name' => $businessName,
                'contact_name' => $contactName,
                'email' => $email,
                'phone' => $phone,
                'collab_type' => $collabType,
                'message' => $message,
            ];
            telegramNotifyInfluencerCollaboration($pdo, $collabData, $influencer);
            
            if (!empty($influencer['contact_email'])) {
                $emailService = new EmailService();
                $emailService->sendCollaborationEmail($influencer['contact_email'], $influencer['name'], $collabData);
            }

            $collabSuccess = 'İş birliği talebiniz alındı. En kısa sürede size dönüş yapılacaktır.';
        } catch (Exception $e) {
            $collabError = 'Talep gönderilirken hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

$num1 = 0;
$num2 = 0;
if ($captchaActive) {
    $num1 = rand(2, 9);
    $num2 = rand(2, 9);
    $_SESSION['inf_captcha_result'] = $num1 + $num2;
}

$avatar = getInfluencerImageUrl($influencer['avatar_path']);
$cover = getInfluencerImageUrl($influencer['cover_path'], 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=1200&q=80');
$letter = mb_strtoupper(mb_substr($influencer['name'], 0, 1, 'UTF-8'), 'UTF-8');
$color = !empty($influencer['theme_color']) ? SecurityHelper::escape($influencer['theme_color']) : '#1F242B';

$quickLinks = [
    [
        'label' => 'Dijital Kartvizit',
        'sublabel' => 'QR dijital kartvizit sayfası',
        'icon' => 'fa-qrcode',
        'url' => influencerQrUrl($influencer['slug']),
        'external' => false,
        'type' => 'qr',
    ],
];

require_once 'includes/seo-meta.php';
$pageTitle = $influencer['name'] . ' — ' . seoGetRegionName() . ' Influencer';
$metaDescription = !empty($influencer['meta_description']) ? $influencer['meta_description'] : $influencer['name'] . ' ' . seoGetRegionName() . ' ' . getInfluencerNicheLabel($influencer['niche']) . ' içerik üreticisi profili.';
$metaKeywords = !empty($influencer['meta_keywords']) ? $influencer['meta_keywords'] : strtolower(seoGetRegionName()) . ' influencer, ' . $influencer['district'] . ' fenomen';
$canonicalUrl = seoGetBaseUrl() . '/influencer/' . $influencer['slug'];
$ogImage = $avatar ?: $cover;
$ogImageAlt = $influencer['name'];
$ogType = 'profile';

$sameAs = [];
foreach ($platforms as $pData) {
    if (!empty($influencer[$pData['field']])) {
        $sameAs[] = $influencer[$pData['field']];
    }
}
$schemaPerson = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $influencer['name'],
    'description' => strip_tags($influencer['bio']),
    'url' => $canonicalUrl,
    'homeLocation' => [
        '@type' => 'Place',
        'name' => $influencer['district'] . ', Şehir, Türkiye',
    ],
];
if (!empty($sameAs)) {
    $schemaPerson['sameAs'] = $sameAs;
}

require_once 'includes/header.php';
?>

<script type="application/ld+json"><?= json_encode($schemaPerson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<header class="biz-portal-hero inf-portal-hero" style="--biz-cover: url('<?= SecurityHelper::escape($cover) ?>');">
    <div class="biz-portal-hero__overlay" aria-hidden="true"></div>
    <div class="biz-portal-hero__backdrop" aria-hidden="true">
        <div class="biz-portal-hero__panel biz-portal-hero__panel--guide"></div>
        <div class="biz-portal-hero__panel biz-portal-hero__panel--media"></div>
    </div>
    <div class="container biz-portal-hero__inner">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="<?= seoGetBaseUrl() ?>/index.php">Ana Sayfa</a>
            <span aria-hidden="true">/</span>
            <a href="<?= seoGetBaseUrl() ?>/influencerlar">Influencerlar</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current"><?= SecurityHelper::escape($influencer['name']) ?></span>
        </nav>
    </div>
</header>

<div class="container inf-portal-profile-wrap">
    <article class="inf-portal-profile-card <?= $influencer['is_premium'] ? 'inf-portal-profile-card--premium' : '' ?> reveal-on-scroll">
        <div class="inf-portal-profile-card__top">
            <div class="inf-portal-profile-card__identity">
                <div class="inf-portal-profile-card__avatar" style="--inf-color: <?= $color ?>">
                    <?php if ($avatar): ?>
                        <img src="<?= SecurityHelper::escape($avatar) ?>" alt="<?= SecurityHelper::escape($influencer['name']) ?>">
                    <?php else: ?>
                        <span><?= $letter ?></span>
                    <?php endif; ?>
                </div>

                <div class="inf-portal-profile-card__meta">
                    <div class="inf-portal-profile-card__badges">
                        <span class="inf-portal-profile-card__niche"><?= SecurityHelper::escape(getInfluencerNicheLabel($influencer['niche'])) ?></span>
                        <?php if ($influencer['is_verified']): ?>
                            <?= renderInfluencerVerifiedBadge(false) ?>
                        <?php endif; ?>
                        <?php if ($influencer['is_premium']): ?>
                            <span class="badge-premium-inline"><i class="fa-solid fa-crown me-1"></i>Premium</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="inf-portal-profile-card__title"><?= SecurityHelper::escape($influencer['name']) ?></h1>
                    <p class="inf-portal-profile-card__location">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= SecurityHelper::escape($influencer['district']) ?> / Şehir
                    </p>
                </div>
            </div>

            <?php
            $visiblePlatforms = [];
            foreach ($platforms as $pSlug => $pData) {
                $fCount = (int) $influencer[$pData['followers']];
                if (!empty($influencer[$pData['field']]) || $fCount > 0) {
                    $visiblePlatforms[$pSlug] = $pData;
                }
            }
            ?>
            <?php if (!empty($visiblePlatforms)): ?>
            <div class="inf-portal-profile-card__stats" aria-label="Platform istatistikleri">
                <?php foreach ($visiblePlatforms as $pSlug => $pData):
                    $fCount = (int) $influencer[$pData['followers']];
                ?>
                <a href="<?= SecurityHelper::escape($influencer[$pData['field']] ?: '#') ?>"
                   class="inf-portal-stat"
                   <?= !empty($influencer[$pData['field']]) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <span class="inf-portal-stat__icon"><i class="<?= $pData['icon'] ?>"></i></span>
                    <span class="inf-portal-stat__body">
                        <strong><?= $fCount > 0 ? formatInfluencerFollowers($fCount) : '—' ?></strong>
                        <small><?= SecurityHelper::escape($pData['label']) ?></small>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="inf-portal-profile-card__bar" style="--inf-accent: <?= $color ?>;">
            <?php $followerNote = renderInfluencerFollowerNote($influencer); ?>
            <?php if ($followerNote !== ''): ?>
                <div class="inf-portal-profile-card__note"><?= $followerNote ?></div>
            <?php endif; ?>
            <div class="inf-portal-profile-card__actions">
                <span class="inf-portal-profile-card__actions-label">Hızlı Erişim</span>
                <div class="inf-portal-profile-card__chips">
                    <?php foreach ($quickLinks as $link): ?>
                        <a href="<?= SecurityHelper::escape($link['url']) ?>"
                           class="inf-portal-profile-card__chip inf-portal-profile-card__chip--<?= SecurityHelper::escape($link['type']) ?>"
                           <?php if ($link['external']): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                            <i class="fa-solid <?= SecurityHelper::escape($link['icon']) ?>"></i>
                            <?= SecurityHelper::escape($link['label']) ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="#isbirligi" class="inf-portal-profile-card__chip inf-portal-profile-card__chip--collab">
                        <i class="fa-solid fa-handshake"></i> İş Birliği
                    </a>
                </div>
            </div>
        </div>

        <div class="inf-portal-profile-card__consent">
            <i class="fa-solid fa-file-signature"></i>
            <p>
                Profil, isim ve görsel kullanımı için içerik üreticisinden yazılı onay alınmıştır.
                <?php if (!empty($influencer['consent_date'])): ?>
                    <span>Onay: <?= date('d.m.Y', strtotime($influencer['consent_date'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
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
                            <span class="portal-section__eyebrow">Profil</span>
                            <h2>Hakkında</h2>
                        </div>
                    </header>
                    <div class="biz-portal-panel__body">
                        <?php if (!empty($influencer['bio'])): ?>
                            <div class="biz-portal-about"><?= nl2br(SecurityHelper::escape($influencer['bio'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Henüz biyografi eklenmemiş.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <?php
                $contentSectionNum = 2;
                if (!empty($parsedCollab)):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">İş Birliği</span>
                            <h2>İş Birliği Türleri</h2>
                        </div>
                    </header>
                    <div class="inf-portal-tags">
                        <?php foreach ($parsedCollab as $ct): ?>
                            <span class="inf-portal-tag"><?= SecurityHelper::escape(isset($collabTypes[$ct]) ? $collabTypes[$ct] : $ct) ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php
                $contentSectionNum++;
                endif;

                if (!empty($featuredLinks)):
                ?>
                <article class="biz-portal-panel reveal-on-scroll">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index"><?= str_pad((string) $contentSectionNum, 2, '0', STR_PAD_LEFT) ?></span>
                        <div>
                            <span class="portal-section__eyebrow">İçerik</span>
                            <h2>Öne Çıkan İçerikler</h2>
                        </div>
                    </header>
                    <ul class="inf-portal-featured-links">
                        <?php foreach ($featuredLinks as $link): ?>
                            <li>
                                <a href="<?= SecurityHelper::escape($link) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <?= SecurityHelper::escape($link) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
                            <h2>Tanıttığı İşletmeler</h2>
                        </div>
                    </header>
                    <div class="inf-portal-linked-biz">
                        <?php foreach ($linkedBusinesses as $biz): ?>
                            <a href="<?= seoGetBaseUrl() ?>/esnaf/<?= SecurityHelper::escape($biz['slug']) ?>" class="inf-portal-linked-biz__item">
                                <strong><?= SecurityHelper::escape($biz['name']) ?></strong>
                                <small><?= SecurityHelper::escape($biz['district']) ?> · <?= SecurityHelper::escape($biz['category_name']) ?></small>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endif; ?>
            </div>

            <aside class="biz-portal-sidebar reveal-on-scroll">
                <div class="biz-portal-widget" id="isbirligi">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">B</span>
                        <div>
                            <span class="portal-section__eyebrow">İş Birliği</span>
                            <h3>İş Birliği Talebi</h3>
                        </div>
                    </header>
                    <p class="inf-portal-collab-intro">Markanız veya işletmeniz için <strong><?= SecurityHelper::escape($influencer['name']) ?></strong> ile iş birliği talebi gönderin.</p>

                    <?php if ($collabSuccess): ?>
                        <div class="alert alert-success"><?= SecurityHelper::escape($collabSuccess) ?></div>
                    <?php endif; ?>
                    <?php if ($collabError): ?>
                        <div class="alert alert-danger"><?= SecurityHelper::escape($collabError) ?></div>
                    <?php endif; ?>

                    <?php if (!$collabSuccess): ?>
                    <form method="POST" class="inf-portal-collab-form">
                        <input type="hidden" name="collab_submit" value="1">
                        <div class="mb-3">
                            <label class="form-label">İşletme / Marka Adı *</label>
                            <input type="text" name="business_name" class="form-control" required value="<?= SecurityHelper::escape($_POST['business_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yetkili Ad Soyad *</label>
                            <input type="text" name="contact_name" class="form-control" required value="<?= SecurityHelper::escape($_POST['contact_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta *</label>
                            <input type="email" name="email" class="form-control" required value="<?= SecurityHelper::escape($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="phone" class="form-control" value="<?= SecurityHelper::escape($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İş Birliği Türü</label>
                            <select name="collab_type" class="form-select">
                                <option value="">Seçiniz</option>
                                <?php foreach ($collabTypes as $cSlug => $cLabel): ?>
                                    <option value="<?= SecurityHelper::escape($cSlug) ?>" <?= (($_POST['collab_type'] ?? '') === $cSlug) ? 'selected' : '' ?>><?= SecurityHelper::escape($cLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mesajınız *</label>
                            <textarea name="message" class="form-control" rows="4" required placeholder="Kampanya detayları, bütçe aralığı, tarih…"><?= SecurityHelper::escape($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <?php if ($captchaActive): ?>
                        <div class="mb-3">
                            <label class="form-label">Güvenlik: <?= $num1 ?> + <?= $num2 ?> = ?</label>
                            <input type="number" name="captcha_answer" class="form-control" required>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="fa-solid fa-paper-plane me-2"></i> Talebi Gönder
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <p class="inf-portal-kvkk-note">
                    Profiliniz mi? <a href="<?= influencerRemovalRequestUrl($influencer['slug']) ?>">Kaldırma veya düzeltme talebi</a> (KVKK)
                </p>
            </aside>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
