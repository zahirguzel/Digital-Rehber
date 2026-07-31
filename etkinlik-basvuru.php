<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once 'includes/event-helpers.php';
require_once 'includes/seo-meta.php';

$successMsg = '';
$errorMsg = '';
$categories = eventCategories();
$districts = eventDistricts();

$captchaActive = 1;
try {
    $captchaActive = (int) $pdo->query('SELECT contact_captcha FROM settings WHERE id = 1')->fetchColumn();
} catch (Exception $e) {}

$contact_name = '';
$contact_email = '';
$contact_phone = '';
$title = '';
$district = '';
$venue_name = '';
$address = '';
$start_date = '';
$end_date = '';
$start_time = '';
$end_time = '';
$category = 'diger';
$description = '';
$ticket_url = '';
$ticket_price = '';
$organizer = '';
$cover_image_url = '';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $venue_name = trim($_POST['venue_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $category = trim($_POST['category'] ?? 'diger');
    $description = trim($_POST['description'] ?? '');
    $ticket_url = trim($_POST['ticket_url'] ?? '');
    $ticket_price = trim($_POST['ticket_price'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $cover_image_url = trim($_POST['cover_image_url'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $consentKvkk = isset($_POST['consent_kvkk']) ? 1 : 0;
    $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
    $correctAnswer = isset($_SESSION['captcha_result']) ? (int) $_SESSION['captcha_result'] : -2;

    if ($contact_name === '' || $contact_email === '' || $title === '' || $district === '' || $start_date === '' || $description === '') {
        $errorMsg = 'İletişim bilgileri, etkinlik adı, ilçe, tarih ve açıklama alanları zorunludur.';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Geçerli bir e-posta adresi girin.';
    } elseif (!isset($categories[$category])) {
        $errorMsg = 'Geçerli bir kategori seçin.';
    } elseif (!$consentKvkk) {
        $errorMsg = 'KVKK aydınlatma metnini okuduğunuzu onaylamanız gerekir.';
    } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
        $errorMsg = 'Güvenlik doğrulaması hatalı. Lütfen tekrar deneyin.';
    } else {
        try {
            if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES['cover_image_file']['name']));
                $targetDir = __DIR__ . '/public/images/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['cover_image_file']['tmp_name'], $targetDir . $fileName)) {
                    $cover_image_url = $fileName;
                }
            }

            $stmt = $pdo->prepare('INSERT INTO event_submissions (contact_name, contact_email, contact_phone, title, district, venue_name, address, start_date, end_date, start_time, end_time, category, description, ticket_url, ticket_price, organizer, cover_image_url, notes, consent_kvkk) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $contact_name,
                $contact_email,
                $contact_phone !== '' ? $contact_phone : null,
                $title,
                $district,
                $venue_name !== '' ? $venue_name : null,
                $address !== '' ? $address : null,
                $start_date,
                $end_date !== '' ? $end_date : null,
                $start_time !== '' ? $start_time : null,
                $end_time !== '' ? $end_time : null,
                $category,
                $description,
                $ticket_url !== '' ? $ticket_url : null,
                $ticket_price !== '' ? $ticket_price : null,
                $organizer !== '' ? $organizer : null,
                $cover_image_url !== '' ? $cover_image_url : null,
                $notes !== '' ? $notes : null,
                $consentKvkk,
            ]);
            $successMsg = 'Etkinlik başvurunuz alındı. Editör ekibimiz inceledikten sonra takvimde yayınlanacaktır.';
            $contact_name = $contact_email = $contact_phone = $title = $venue_name = $address = '';
            $start_date = $end_date = $start_time = $end_time = $description = '';
            $ticket_url = $ticket_price = $organizer = $cover_image_url = $notes = '';
            $district = '';
            $category = 'diger';
        } catch (Exception $e) {
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

$pageTitle = 'Etkinlik Yayınlama Başvurusu';
$_region = seoGetRegionName();
$metaDescription = $_region . ' etkinlik takvimine etkinliğinizi ekletin. Konser, festival, sergi ve kültür etkinlikleri için başvuru formu.';
$metaKeywords = strtolower($_region) . ' etkinlik başvuru, etkinlik ekle, konser duyuru ' . strtolower($_region) . ', festival başvuru';
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--form directory-portal-hero--event-form">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Etkinlik Takvimi</span>
                <h1 class="directory-portal-hero__title">Etkinliğinizi Yayınlatın</h1>
                <p class="directory-portal-hero__lead">Konser, festival, sergi veya kültür etkinliğiniz varsa formu doldurun; onay sonrası <?= SecurityHelper::escape(seoGetRegionName()) ?> etkinlik takviminde yer alır.</p>
                <div class="portal-form-trust-pills">
                    <span><i class="fa-solid fa-circle-check"></i> Editör onayı</span>
                    <span><i class="fa-solid fa-calendar-check"></i> <?= SecurityHelper::escape(seoGetRegionName()) ?> takvimi</span>
                    <span><i class="fa-solid fa-shield-halved"></i> KVKK uyumlu</span>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong><?= count($categories) ?></strong>
                <span>Kategori</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted contact-portal-main">
    <div class="container">
        <nav class="biz-portal-breadcrumb reveal-on-scroll" aria-label="Konum">
            <a href="<?= seoGetBaseUrl() ?>/etkinlikler">Etkinlikler</a>
            <span aria-hidden="true">/</span>
            <span class="biz-portal-breadcrumb__current">Yayınlama Başvurusu</span>
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
                        <li>Formu doldurup gönderin; başvurunuz yönetim paneline düşer.</li>
                        <li>Editör ekibimiz tarih, mekân ve içerik bilgilerini kontrol eder.</li>
                        <li>Onaylanan etkinlikler <strong>/etkinlikler</strong> takviminde yayınlanır.</li>
                        <li>Gerekirse sizinle e-posta veya telefon üzerinden iletişime geçilir.</li>
                    </ol>
                </div>
                <div class="biz-portal-widget">
                    <span class="portal-section__eyebrow">Kategoriler</span>
                    <h3 class="portal-form-widget-title">Desteklenen Türler</h3>
                    <div class="portal-form-chips">
                        <?php foreach ($categories as $slug => $label): ?>
                        <span class="portal-form-chip"><?= SecurityHelper::escape($label) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <div class="contact-portal-form-wrap reveal-on-scroll">
                <article class="biz-portal-panel contact-portal-form-panel">
                    <?php if ($successMsg): ?>
                    <div class="portal-form-success">
                        <div class="portal-form-success__icon"><i class="fa-solid fa-circle-check"></i></div>
                        <h2>Başvurunuz Alındı</h2>
                        <p><?= SecurityHelper::escape($successMsg) ?></p>
                        <a href="<?= seoGetBaseUrl() ?>/etkinlikler" class="btn btn-primary fw-semibold">Etkinlik Takvimine Dön</a>
                    </div>
                    <?php else: ?>
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">B</span>
                        <div>
                            <span class="portal-section__eyebrow">Başvuru</span>
                            <h2>Etkinlik Başvuru Formu</h2>
                        </div>
                    </header>

                    <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= SecurityHelper::escape($errorMsg) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="contact-portal-form" enctype="multipart/form-data">
                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-user"></i> İletişim Bilgileri</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ad Soyad / Yetkili *</label>
                                    <input type="text" name="contact_name" class="form-control" required value="<?= SecurityHelper::escape($contact_name) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" name="contact_email" class="form-control" required value="<?= SecurityHelper::escape($contact_email) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefon / WhatsApp</label>
                                    <input type="text" name="contact_phone" class="form-control" placeholder="05XX XXX XX XX" value="<?= SecurityHelper::escape($contact_phone) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Düzenleyen / Organizasyon</label>
                                    <input type="text" name="organizer" class="form-control" placeholder="Belediye, dernek, işletme..." value="<?= SecurityHelper::escape($organizer) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-calendar-days"></i> Etkinlik Bilgileri</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Etkinlik Adı *</label>
                                    <input type="text" name="title" class="form-control" required placeholder="Örn: Bahar Müzik Gecesi" value="<?= SecurityHelper::escape($title) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kategori *</label>
                                    <select name="category" class="form-select" required>
                                        <?php foreach ($categories as $slug => $label): ?>
                                        <option value="<?= SecurityHelper::escape($slug) ?>" <?= $category === $slug ? 'selected' : '' ?>><?= SecurityHelper::escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                <div class="col-md-6">
                                    <label class="form-label">Mekân Adı</label>
                                    <input type="text" name="venue_name" class="form-control" placeholder="Kültür Merkezi, Açık Hava..." value="<?= SecurityHelper::escape($venue_name) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kapak Görseli (Yükle veya URL)</label>
                                    <input type="file" name="cover_image_file" class="form-control mb-2" accept="image/*">
                                    <input type="url" name="cover_image_url" class="form-control" placeholder="https://..." value="<?= SecurityHelper::escape($cover_image_url) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Adres</label>
                                    <textarea name="address" class="form-control" rows="2" placeholder="Tam adres veya yön tarifi"><?= SecurityHelper::escape($address) ?></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Başlangıç Tarihi *</label>
                                    <input type="date" name="start_date" class="form-control" required value="<?= SecurityHelper::escape($start_date) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Bitiş Tarihi</label>
                                    <input type="date" name="end_date" class="form-control" value="<?= SecurityHelper::escape($end_date) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Başlangıç Saati</label>
                                    <input type="time" name="start_time" class="form-control" value="<?= SecurityHelper::escape($start_time) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Bitiş Saati</label>
                                    <input type="time" name="end_time" class="form-control" value="<?= SecurityHelper::escape($end_time) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bilet / Ücret</label>
                                    <input type="text" name="ticket_price" class="form-control" placeholder="Ücretsiz, 150 TL..." value="<?= SecurityHelper::escape($ticket_price) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bilet Linki</label>
                                    <input type="url" name="ticket_url" class="form-control" placeholder="https://biletix.com/..." value="<?= SecurityHelper::escape($ticket_url) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Etkinlik Açıklaması *</label>
                                    <textarea name="description" class="form-control" rows="5" required placeholder="Program, sanatçılar, yaş sınırı, park yeri vb."><?= SecurityHelper::escape($description) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ek Notlar</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Editör için ek bilgi (isteğe bağlı)"><?= SecurityHelper::escape($notes) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="portal-form-section">
                            <h3 class="portal-form-section__title"><i class="fa-solid fa-shield-halved"></i> Onay</h3>
                            <div class="portal-form-consent">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="consent_kvkk" id="consent_kvkk" value="1" required>
                                    <label class="form-check-label" for="consent_kvkk">
                                        <a href="<?= seoGetBaseUrl() ?>/gizlilik-politikasi#etkinlik-kvkk" target="_blank">Gizlilik & KVKK — Etkinlik bölümü</a> metnini okudum; ilettiğim bilgilerin etkinlik duyurusu amacıyla işlenmesini kabul ediyorum.
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

                        <p class="portal-form-footer-note">
                            Detaylı bilgi: <a href="<?= seoGetBaseUrl() ?>/gizlilik-politikasi#etkinlik-kvkk">Gizlilik & KVKK — Etkinlik bölümü</a>
                            · Mevcut etkinliklere <a href="<?= seoGetBaseUrl() ?>/etkinlikler">etkinlik takviminden</a> göz atabilirsiniz.
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
