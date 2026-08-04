<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/influencer-helpers.php';
require_once 'includes/seo-meta.php';

$successMsg = '';
$errorMsg = '';
$prefSlug = trim($_GET['slug'] ?? '');

// Eski ?slug= URL'lerini temiz adrese yönlendir
if ($prefSlug !== '' && !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], '?slug=') !== false) {
    header('Location: ' . influencerRemovalRequestUrl($prefSlug), true, 301);
    exit;
}

$linkedProfile = null;
if ($prefSlug !== '') {
    try {
        $stmt = $pdo->prepare('SELECT id, name, slug, district, niche, avatar_path, instagram FROM influencers WHERE slug = ? LIMIT 1');
        $stmt->execute([$prefSlug]);
        $linkedProfile = $stmt->fetch() ?: null;
    } catch (Exception $e) {}
}

$captchaActive = 1;
try {
    $captchaActive = (int) $pdo->query("SELECT contact_captcha FROM settings WHERE id = 1")->fetchColumn();
} catch (Exception $e) {}

$profileName = $linkedProfile ? $linkedProfile['name'] : '';
$email = '';
$requestType = 'removal';
$reason = '';
$influencerId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileName = trim($_POST['profile_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $requestType = $_POST['request_type'] ?? 'removal';
    $reason = trim($_POST['reason'] ?? '');
    $slugPost = trim($_POST['profile_slug'] ?? '');
    $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
    $correctAnswer = isset($_SESSION['captcha_result']) ? (int) $_SESSION['captcha_result'] : -2;

    if (!in_array($requestType, ['removal', 'correction'], true)) {
        $requestType = 'removal';
    }

    if ($slugPost !== '') {
        try {
            $stmt = $pdo->prepare('SELECT id, name, slug, district, niche, avatar_path, instagram FROM influencers WHERE slug = ? LIMIT 1');
            $stmt->execute([$slugPost]);
            $infRow = $stmt->fetch();
            if ($infRow) {
                $influencerId = (int) $infRow['id'];
                $linkedProfile = $infRow;
                $prefSlug = $infRow['slug'];
            }
        } catch (Exception $e) {}
    }

    if ($profileName === '' || $email === '' || $reason === '') {
        $errorMsg = 'Profil adı, e-posta ve talep açıklaması zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Geçerli bir e-posta adresi girin.';
    } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
        $errorMsg = 'Güvenlik doğrulaması hatalı. Lütfen tekrar deneyin.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO influencer_removal_requests (influencer_id, profile_name, email, request_type, reason) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$influencerId, $profileName, $email, $requestType, $reason]);
            $successMsg = 'Talebiniz KVKK kapsamında alınmıştır. En geç 30 gün içinde değerlendirilip size dönüş yapılacaktır.';
            $profileName = $email = $reason = '';
            $requestType = 'removal';
            $linkedProfile = null;
            $prefSlug = '';
        } catch (Exception $e) {
            $errorMsg = 'Talep gönderilirken hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

$num1 = 0;
$num2 = 0;
if ($captchaActive) {
    $num1 = rand(2, 9);
    $num2 = rand(2, 9);
    $_SESSION['captcha_result'] = $num1 + $num2;
}

$slugInvalid = $prefSlug !== '' && !$linkedProfile;
$avatarUrl = $linkedProfile ? getInfluencerImageUrl($linkedProfile['avatar_path']) : '';
$profileLetter = $linkedProfile ? mb_strtoupper(mb_substr($linkedProfile['name'], 0, 1, 'UTF-8'), 'UTF-8') : '';

$_siteTitle = seoGetSiteTitle();
$pageTitle = $linkedProfile
    ? 'KVKK Talebi — ' . $linkedProfile['name']
    : 'Influencer Profil Kaldırma / Düzeltme Talebi';
$metaDescription = $linkedProfile
    ? $linkedProfile['name'] . ' profili için kaldırma veya düzeltme talebi. ' . $_siteTitle . ' KVKK formu.'
    : $_siteTitle . ' influencer profilinizin kaldırılması veya düzeltilmesi için KVKK kapsamında talep formu.';
$metaKeywords = 'influencer profil kaldırma, kvkk talep, ' . strtolower($_siteTitle);
// seo-meta.php already required above
$canonicalUrl = influencerRemovalRequestUrl($linkedProfile ? $linkedProfile['slug'] : null);
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--form directory-portal-hero--kvkk-form">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">KVKK</span>
                <h1 class="directory-portal-hero__title">
                    <?php if ($linkedProfile): ?>
                    <?= SecurityHelper::escape($linkedProfile['name']) ?> — Talep Formu
                    <?php else: ?>
                    Profil Kaldırma / Düzeltme
                    <?php endif; ?>
                </h1>
                <p class="directory-portal-hero__lead">
                    <?php if ($linkedProfile): ?>
                    <strong><?= SecurityHelper::escape($linkedProfile['name']) ?></strong> profili için kaldırma veya bilgi düzeltme talebinizi güvenle iletin.
                    <?php else: ?>
                    Rehberimizde yer alan profilinizle ilgili kaldırma veya bilgi düzeltme talebinizi güvenle iletin.
                    <?php endif; ?>
                </p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong>30</strong>
                <span>Gün</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted contact-portal-main">
    <div class="container">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="<?= seoGetBaseUrl() ?>/influencerlar">Influencerlar</a>
            <?php if ($linkedProfile): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= seoGetBaseUrl() ?>/influencer/<?= SecurityHelper::escape($linkedProfile['slug']) ?>"><?= SecurityHelper::escape($linkedProfile['name']) ?></a>
            <?php endif; ?>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current">KVKK Talebi</span>
        </nav>

        <div class="contact-portal-layout">
            <aside class="contact-portal-info reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">H</span>
                        <div>
                            <span class="portal-section__eyebrow">Haklarınız</span>
                            <h3>KVKK Kapsamında</h3>
                        </div>
                    </header>
                    <ul class="portal-form-rights">
                        <li>Profilinizin tamamen kaldırılmasını talep edebilirsiniz.</li>
                        <li>Bio, takipçi sayısı veya sosyal medya linklerinde düzeltme isteyebilirsiniz.</li>
                        <li>Talepleriniz en geç <strong>30 gün</strong> içinde değerlendirilir.</li>
                    </ul>
                </div>
                <div class="portal-trust-card">
                    <h4><i class="fa-solid fa-scale-balanced"></i> Veri Güvenliği</h4>
                    <ul>
                        <li>Talep kayıtları güvenli saklanır</li>
                        <li>Yalnızca KVKK amacıyla işlenir</li>
                        <li>E-posta ile dönüş yapılır</li>
                    </ul>
                </div>
            </aside>

            <div class="contact-portal-form-wrap reveal-on-scroll">
                <article class="biz-portal-panel contact-portal-form-panel">
                    <?php if ($successMsg): ?>
                    <div class="portal-form-success">
                        <div class="portal-form-success__icon"><i class="fa-solid fa-circle-check"></i></div>
                        <h2>Talebiniz Alındı</h2>
                        <p><?= SecurityHelper::escape($successMsg) ?></p>
                        <a href="<?= seoGetBaseUrl() ?>/influencerlar" class="btn btn-outline-primary fw-semibold">Influencer Rehberi</a>
                    </div>
                    <?php else: ?>
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">F</span>
                        <div>
                            <span class="portal-section__eyebrow">Talep</span>
                            <h2>KVKK Talep Formu</h2>
                        </div>
                    </header>

                    <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= SecurityHelper::escape($errorMsg) ?></div>
                    <?php endif; ?>

                    <?php if ($slugInvalid): ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <strong><?= SecurityHelper::escape($prefSlug) ?></strong> slug'ına sahip bir profil bulunamadı. Genel talep formunu kullanabilirsiniz.
                        <a href="<?= seoGetBaseUrl() ?>/influencer-kaldirma-talebi" class="alert-link ms-1">Genel forma git</a>
                    </div>
                    <?php endif; ?>

                    <?php if ($linkedProfile): ?>
                    <div class="portal-removal-profile">
                        <div class="portal-removal-profile__avatar">
                            <?php if ($avatarUrl): ?>
                            <img src="<?= SecurityHelper::escape($avatarUrl) ?>" alt="<?= SecurityHelper::escape($linkedProfile['name']) ?>">
                            <?php else: ?>
                            <span><?= SecurityHelper::escape($profileLetter) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="portal-removal-profile__body">
                            <h3><?= SecurityHelper::escape($linkedProfile['name']) ?></h3>
                            <p class="mb-1">
                                <i class="fa-solid fa-location-dot me-1"></i><?= SecurityHelper::escape($linkedProfile['district']) ?>, <?= SecurityHelper::escape(seoGetRegionName()) ?>
                                · <?= SecurityHelper::escape(getInfluencerNicheLabel($linkedProfile['niche'])) ?>
                            </p>
                            <p class="mb-0 small text-muted font-monospace"><?= SecurityHelper::escape(parse_url(seoGetBaseUrl(), PHP_URL_HOST) ?? $_SERVER['HTTP_HOST'] ?? '') ?>/influencer/<?= SecurityHelper::escape($linkedProfile['slug']) ?></p>
                        </div>
                        <a href="<?= seoGetBaseUrl() ?>/influencer/<?= SecurityHelper::escape($linkedProfile['slug']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Profili Gör
                        </a>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= SecurityHelper::escape(influencerRemovalRequestUrl($linkedProfile ? $linkedProfile['slug'] : null)) ?>" class="contact-portal-form">
                        <input type="hidden" name="profile_slug" value="<?= SecurityHelper::escape($linkedProfile ? $linkedProfile['slug'] : $prefSlug) ?>">

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-id-card"></i> Profil Bilgileri</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Profil / Sahne Adı *</label>
                                    <input type="text" name="profile_name" class="form-control" required
                                        value="<?= SecurityHelper::escape($profileName) ?>"
                                        placeholder="Rehberde görünen adınız"
                                        <?= $linkedProfile ? 'readonly' : '' ?>>
                                    <?php if ($linkedProfile): ?>
                                    <small class="text-muted d-block mt-1">Profil slug ile eşleştirildi; ad otomatik dolduruldu.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" name="email" class="form-control" required value="<?= SecurityHelper::escape($email) ?>" placeholder="ornek@email.com">
                                </div>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-list-check"></i> Talep Detayı</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Talep Türü *</label>
                                    <select name="request_type" class="form-select">
                                        <option value="removal" <?= $requestType === 'removal' ? 'selected' : '' ?>>Profilin tamamen kaldırılması</option>
                                        <option value="correction" <?= $requestType === 'correction' ? 'selected' : '' ?>>Bilgi düzeltme (takipçi, bio, link vb.)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Açıklama *</label>
                                    <textarea name="reason" class="form-control" rows="5" required placeholder="Talebinizi mümkün olduğunca detaylı yazın. Düzeltme taleplerinde doğru bilgileri belirtin."><?= SecurityHelper::escape($reason) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <?php if ($captchaActive): ?>
                        <div class="portal-form-section portal-form-section--captcha">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-lock"></i> Güvenlik Doğrulaması</h3>
                            <label class="form-label">Robot olmadığınızı doğrulayın *</label>
                            <div class="contact-portal-captcha">
                                <span class="contact-portal-captcha__prompt"><?= $num1 ?> + <?= $num2 ?> =</span>
                                <input type="number" name="captcha_answer" class="form-control" placeholder="Sonuç" required>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="portal-form-submit">
                            <button type="submit" class="btn btn-primary w-100 fw-semibold contact-portal-form__submit">
                                <i class="fa-solid fa-paper-plane me-2"></i> Talebi Gönder
                            </button>
                        </div>

                        <p class="portal-form-footer-note">
                            Detaylı bilgi: <a href="<?= seoGetBaseUrl() ?>/gizlilik-politikasi#influencer-kvkk">Gizlilik & KVKK — Influencer bölümü</a>
                            <?php if (!$linkedProfile): ?>
                            · Profilinizi biliyorsanız <a href="/influencerlar">rehberden</a> profil sayfasına gidip oradan da talep oluşturabilirsiniz.
                            <?php endif; ?>
                        </p>
                    </form>
                    <?php endif; ?>
                </article>
            </div>
        </div>
    </div>
</section>

<?php
$hideFooterCTA = true;
require_once 'includes/footer.php';
?>
