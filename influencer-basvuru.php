<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/influencer-helpers.php';
require_once 'includes/seo-meta.php';
use App\Services\EmailService;

$successMsg = '';
$errorMsg = '';
$niches = influencerNiches();
$districts = influencerDistricts();

$captchaActive = 1;
try {
    $captchaActive = (int) $pdo->query("SELECT contact_captcha FROM settings WHERE id = 1")->fetchColumn();
} catch (Exception $e) {}

$name = '';
$email = '';
$phone = '';
$district = '';
$niche = 'diger';
$instagram = '';
$tiktok = '';
$youtube = '';
$bio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $niche = trim($_POST['niche'] ?? 'diger');
    $instagram = trim($_POST['instagram'] ?? '');
    $tiktok = trim($_POST['tiktok'] ?? '');
    $youtube = trim($_POST['youtube'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $consentProfile = isset($_POST['consent_profile']) ? 1 : 0;
    $consentKvkk = isset($_POST['consent_kvkk']) ? 1 : 0;
    $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
    $correctAnswer = isset($_SESSION['captcha_result']) ? (int) $_SESSION['captcha_result'] : -2;

    if ($name === '' || $email === '' || $district === '' || $bio === '') {
        $errorMsg = 'Ad, e-posta, ilçe ve kısa bio alanları zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Geçerli bir e-posta adresi girin.';
    } elseif (!$consentProfile) {
        $errorMsg = 'Profil yayını ve isim/görsel kullanım onayı zorunludur.';
    } elseif (!$consentKvkk) {
        $errorMsg = 'KVKK aydınlatma metnini okuduğunuzu onaylamanız gerekir.';
    } elseif ($instagram === '' && $tiktok === '' && $youtube === '') {
        $errorMsg = 'En az bir sosyal medya hesabı linki girin.';
    } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
        $errorMsg = 'Güvenlik doğrulaması hatalı. Lütfen tekrar deneyin.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO influencer_applications (name, email, phone, district, niche, instagram, tiktok, youtube, bio, consent_profile, consent_kvkk) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $district, $niche, $instagram, $tiktok, $youtube, $bio, $consentProfile, $consentKvkk]);
            require_once __DIR__ . '/includes/telegram-notify.php';
            telegramNotifyInfluencerApplication($pdo, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'district' => $district,
                'niche' => $niche,
                'instagram' => $instagram,
                'tiktok' => $tiktok,
                'youtube' => $youtube,
                'bio' => $bio,
            ]);

            try {
                $emailService = new EmailService();
                $emailContent = "
                    <p><strong>Ad Soyad:</strong> {$name}</p>
                    <p><strong>E-posta:</strong> {$email}</p>
                    <p><strong>Telefon:</strong> {$phone}</p>
                    <p><strong>İlçe:</strong> {$district}</p>
                    <p><strong>Kategori:</strong> {$niche}</p>
                    <p><strong>Instagram:</strong> {$instagram}</p>
                    <p><strong>TikTok:</strong> {$tiktok}</p>
                    <p><strong>YouTube:</strong> {$youtube}</p>
                    <p><strong>Bio:</strong><br/>{$bio}</p>
                ";
                $emailService->sendAdminNotification('Yeni Influencer Başvurusu', $emailContent, $email);
            } catch (\Throwable $e) {
                error_log("Influencer başvuru mail hatası: " . $e->getMessage());
            }

            $successMsg = 'Başvurunuz alındı. Profiliniz incelendikten ve onayınız doğrulandıktan sonra yayına alınacaktır.';
            $name = $email = $phone = $instagram = $tiktok = $youtube = $bio = '';
            $district = '';
            $niche = 'diger';
        } catch (\Throwable $e) {
            $errorMsg = 'Başvuru gönderilirken hata oluştu. Lütfen tekrar deneyin.';
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

$pageTitle = 'Influencer & İçerik Üreticisi Başvurusu';
$_siteTitle = seoGetSiteTitle();
$_region = seoGetRegionName();
$metaDescription = $_siteTitle . ' influencer rehberine profil başvurusu. Doğrulanmış vitrin, manuel takipçi doğrulama ve KVKK uyumlu yayın.';
$metaKeywords = strtolower($_region) . ' influencer başvuru, içerik üretici kayıt, ' . strtolower($_region) . ' fenomen listesi';
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--form directory-portal-hero--influencer-form">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Influencer Rehberi</span>
                <h1 class="directory-portal-hero__title">Profil Başvurusu</h1>
                <p class="directory-portal-hero__lead"><?= SecurityHelper::escape(seoGetRegionName()) ?>'da içerik üretiyorsanız rehberimize katılın. Profiliniz yalnızca onay ve yazılı izninizle yayınlanır.</p>
            </div>
            <div class="directory-portal-hero__stat">
                <strong>4</strong>
                <span>Adım</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted contact-portal-main">
    <div class="container">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="<?= seoGetBaseUrl() ?>/influencerlar">Influencerlar</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current">Profil Başvurusu</span>
        </nav>

        <div class="contact-portal-layout">
            <aside class="contact-portal-info reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">S</span>
                        <div>
                            <span class="portal-section__eyebrow">Süreç</span>
                            <h3>Başvuru Adımları</h3>
                        </div>
                    </header>
                    <ol class="portal-form-steps">
                        <li>Başvurunuz editör ekibimiz tarafından incelenir.</li>
                        <li>Takipçi sayıları manuel doğrulanır; sahte rakam kabul edilmez.</li>
                        <li>İsim ve görsel kullanım onayınız kayıt altına alınır.</li>
                        <li>Onay sonrası profilinizde <strong>Doğrulanmış <?= SecurityHelper::escape(seoGetSiteTitle()) ?></strong> rozeti yer alır.</li>
                    </ol>
                </div>
                <div class="portal-trust-card">
                    <h4><i class="fa-solid fa-shield-halved"></i> KVKK Uyumlu</h4>
                    <ul>
                        <li>Yazılı profil yayın onayı zorunlu</li>
                        <li>İstediğiniz zaman kaldırma talebi</li>
                        <li>Manuel editör doğrulaması</li>
                    </ul>
                </div>
            </aside>

            <div class="contact-portal-form-wrap reveal-on-scroll">
                <article class="biz-portal-panel contact-portal-form-panel">
                    <?php if ($successMsg): ?>
                    <div class="portal-form-success">
                        <div class="portal-form-success__icon"><i class="fa-solid fa-circle-check"></i></div>
                        <h2>Başvurunuz Alındı</h2>
                        <p><?= SecurityHelper::escape($successMsg) ?></p>
                        <a href="<?= seoGetBaseUrl() ?>/influencerlar" class="btn btn-primary fw-semibold">Influencer Rehberine Dön</a>
                    </div>
                    <?php else: ?>
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">B</span>
                        <div>
                            <span class="portal-section__eyebrow">Başvuru</span>
                            <h2>Başvuru Formu</h2>
                        </div>
                    </header>

                    <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= SecurityHelper::escape($errorMsg) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="contact-portal-form">
                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-user"></i> Kişisel Bilgiler</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ad Soyad / Sahne Adı *</label>
                                    <input type="text" name="name" class="form-control" required value="<?= SecurityHelper::escape($name) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" name="email" class="form-control" required value="<?= SecurityHelper::escape($email) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefon / WhatsApp</label>
                                    <input type="text" name="phone" class="form-control" placeholder="05XX XXX XX XX" value="<?= SecurityHelper::escape($phone) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">İlçe *</label>
                                    <select name="district" class="form-select" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($districts as $d): ?>
                                        <option value="<?= SecurityHelper::escape($d) ?>" <?= $district === $d ? 'selected' : '' ?>><?= SecurityHelper::escape($d) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">İçerik Nişi *</label>
                                    <select name="niche" class="form-select" required>
                                        <?php foreach ($niches as $slug => $label): ?>
                                        <option value="<?= SecurityHelper::escape($slug) ?>" <?= $niche === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-share-nodes"></i> Sosyal Medya Hesapları</h3>
                            <p class="portal-form-section__hint">En az bir platform linki girin.</p>
                            <div class="portal-form-social">
                                <div class="portal-form-social-field portal-form-social-field--instagram">
                                    <label class="form-label">Instagram</label>
                                    <i class="fa-brands fa-instagram portal-form-social-field__icon"></i>
                                    <input type="url" name="instagram" class="form-control" placeholder="instagram.com/kullanici" value="<?= SecurityHelper::escape($instagram) ?>">
                                </div>
                                <div class="portal-form-social-field portal-form-social-field--tiktok">
                                    <label class="form-label">TikTok</label>
                                    <i class="fa-brands fa-tiktok portal-form-social-field__icon"></i>
                                    <input type="url" name="tiktok" class="form-control" placeholder="tiktok.com/@kullanici" value="<?= SecurityHelper::escape($tiktok) ?>">
                                </div>
                                <div class="portal-form-social-field portal-form-social-field--youtube">
                                    <label class="form-label">YouTube</label>
                                    <i class="fa-brands fa-youtube portal-form-social-field__icon"></i>
                                    <input type="url" name="youtube" class="form-control" placeholder="youtube.com/@kanal" value="<?= SecurityHelper::escape($youtube) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-align-left"></i> Kısa Bio</h3>
                            <div class="col-12">
                                <label class="form-label"><?= SecurityHelper::escape(seoGetRegionName()) ?>'da ne tür içerik üretiyorsunuz? *</label>
                                <textarea name="bio" class="form-control" rows="5" required placeholder="Örn: Sokak lezzetleri, yerel işletme tanıtımları ve gezi reel'leri üretiyorum..."><?= SecurityHelper::escape($bio) ?></textarea>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-shield-halved"></i> Onaylar</h3>
                            <div class="portal-form-consent">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="consent_profile" id="consent_profile" value="1" required>
                                    <label class="form-check-label" for="consent_profile">
                                        <strong>Profil ve isim kullanım onayı:</strong> Adım, fotoğrafım, sosyal medya linklerim ve biyografim <?= SecurityHelper::escape(seoGetSiteTitle()) ?> sitesinde yayınlanması için yazılı iznimi veriyorum. İstediğim zaman kaldırma talebinde bulunabileceğimi biliyorum.
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consent_kvkk" id="consentKvkk" required>
                                    <label class="form-check-label text-muted" for="consentKvkk" style="font-size: 0.95rem;">
                                        <a href="<?= seoGetBaseUrl() ?>/gizlilik-politikasi#influencer-kvkk" target="_blank">Gizlilik & KVKK</a> metnini okudum; kişisel verilerimin influencer rehberi hizmeti kapsamında işlenmesini kabul ediyorum.
                                    </label>
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
                                <i class="fa-solid fa-paper-plane me-2"></i> Başvuruyu Gönder
                            </button>
                        </div>
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
