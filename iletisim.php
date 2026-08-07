<?php
require_once __DIR__ . '/autoload.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';
use App\Services\EmailService;

$successMsg = '';
$errorMsg = '';

$captchaActive = 1;
$siteSettings = [];
try {
    $setDb = $pdo->query('SELECT contact_captcha, contact_phone, contact_whatsapp, contact_email, contact_address FROM settings WHERE id = 1')->fetch();
    if ($setDb) {
        $captchaActive = (int) $setDb['contact_captcha'];
        $siteSettings = $setDb;
    }
} catch (Exception $e) {}

$contactPhone = $siteSettings['contact_phone'] ?? '0326 222 22 22';
$contactWhatsapp = $siteSettings['contact_whatsapp'] ?? '905551112233';
$contactEmail = $siteSettings['contact_email'] ?? '';
$contactAddress = !empty($siteSettings['contact_address']) ? $siteSettings['contact_address'] : ('Merkez, ' . seoGetRegionName());

$whatsappDisplay = $contactWhatsapp;
if (strlen($contactWhatsapp) === 12 && substr($contactWhatsapp, 0, 2) === '90') {
    $local = '0' . substr($contactWhatsapp, 2);
    $whatsappDisplay = substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7, 2) . ' ' . substr($local, 9, 2);
}

$name = '';
$email = '';
$phone = '';
$subject = trim($_GET['subject'] ?? 'Yeni İşletme Kaydı');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
    $correctAnswer = isset($_SESSION['captcha_result']) ? (int) $_SESSION['captcha_result'] : -2;

    if ($name === '' || $email === '' || $message === '') {
        $errorMsg = 'Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Geçerli bir e-posta adresi girin.';
    } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
        $errorMsg = 'Güvenlik doğrulaması (matematik işlemi) hatalı! Lütfen tekrar deneyin.';
    } else {
        try {
            try {
                $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, is_read) VALUES (?, ?, ?, ?, ?, 0)');
                $stmt->execute([$name, $email, $phone ?: null, $subject, $message]);
            } catch (Exception $e) {
                $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, is_read) VALUES (?, ?, ?, ?, 0)');
                $stmt->execute([$name, $email, $subject, $message]);
            }
            require_once __DIR__ . '/includes/telegram-notify.php';
            telegramNotifyContactForm($pdo, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
            ]);
            
            try {
                $emailService = new EmailService();
                $emailContent = "
                    <p><strong>Gönderen:</strong> {$name}</p>
                    <p><strong>E-posta:</strong> {$email}</p>
                    <p><strong>Telefon:</strong> {$phone}</p>
                    <p><strong>Konu:</strong> {$subject}</p>
                    <p><strong>Mesaj:</strong><br/>{$message}</p>
                ";
                $emailService->sendAdminNotification('Yeni İletişim Formu Mesajı', $emailContent, $email);
            } catch (\Throwable $e) {
                // Mail gönderilemese bile form veritabanına kaydedildiği için sorun yok
                error_log("Iletisim formu mail hatasi: " . $e->getMessage());
            }

            $successMsg = 'Mesajınız başarıyla iletildi. En kısa sürede sizinle iletişime geçilecektir.';
            $name = '';
            $email = '';
            $phone = '';
            $subject = 'Yeni İşletme Kaydı';
            $message = '';
        } catch (\Throwable $e) {
            $errorMsg = 'Mesaj gönderilirken hata oluştu. Lütfen tekrar deneyin.';
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

$_region = seoGetRegionName();
$_siteTitle = seoGetSiteTitle();
$pageTitle = 'İletişim';
$metaDescription = 'Bize ulaşın! ' . $_siteTitle . ' iletişim kanalları, destek hattı, WhatsApp numarası, adres bilgisi ve işletme kayıt başvuru formu.';
$metaKeywords = strtolower($_siteTitle) . ' iletişim, esnaf kaydı başvuru, ' . strtolower($_region) . ' firma kayıt, ' . strtolower($_region) . ' destek';
$canonicalUrl = seoGetBaseUrl() . '/iletisim';
require_once 'includes/header.php';
?>

<header class="directory-portal-hero directory-portal-hero--contact">
    <div class="directory-portal-hero__backdrop" aria-hidden="true">
        <div class="directory-portal-hero__panel directory-portal-hero__panel--guide"></div>
        <div class="directory-portal-hero__panel directory-portal-hero__panel--media"></div>
    </div>
    <div class="container directory-portal-hero__inner">
        <div class="directory-portal-hero__head reveal-on-scroll">
            <div>
                <span class="portal-eyebrow">Şehir Rehberi</span>
                <h1 class="directory-portal-hero__title">Bize Ulaşın</h1>
                <p class="directory-portal-hero__lead">Rehberde yer almak, bilgilerinizi güncellemek, dijital hizmet almak veya reklam yayını talep etmek için formu doldurun — size en kısa sürede dönüş yapalım.</p>
                <div class="directory-portal-hero__actions">
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone) ?>" class="btn btn-primary fw-semibold"><i class="fa-solid fa-phone me-2"></i> Hemen Ara</a>
                    <a href="https://wa.me/<?= SecurityHelper::escape(preg_replace('/[^0-9]/', '', $contactWhatsapp)) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary fw-semibold"><i class="fa-brands fa-whatsapp me-2"></i> WhatsApp</a>
                </div>
            </div>
            <div class="directory-portal-hero__stat">
                <strong>7/24</strong>
                <span>Form</span>
            </div>
        </div>
    </div>
</header>

<section class="portal-section portal-section--muted contact-portal-main">
    <div class="container">
        <div class="contact-portal-layout">

            <aside class="contact-portal-info reveal-on-scroll">
                <div class="biz-portal-widget">
                    <header class="biz-portal-widget__head">
                        <span class="portal-section__index">D</span>
                        <div>
                            <span class="portal-section__eyebrow">Destek</span>
                            <h3>İletişim Kanalları</h3>
                        </div>
                    </header>
                    <p class="contact-portal-info__intro">Şehir Rehberi ekibine kayıt, güncelleme ve medya hizmetleri için aşağıdaki kanallardan da ulaşabilirsiniz.</p>

                    <ul class="contact-portal-info-list">
                        <li>
                            <span class="contact-portal-info-list__icon"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="contact-portal-info-list__body">
                                <small>Adres</small>
                                <strong><?= SecurityHelper::escape($contactAddress) ?></strong>
                            </span>
                        </li>
                        <li>
                            <span class="contact-portal-info-list__icon"><i class="fa-solid fa-phone"></i></span>
                            <span class="contact-portal-info-list__body">
                                <small>Telefon</small>
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone) ?>"><?= SecurityHelper::escape($contactPhone) ?></a>
                            </span>
                        </li>
                        <li>
                            <span class="contact-portal-info-list__icon contact-portal-info-list__icon--whatsapp"><i class="fa-brands fa-whatsapp"></i></span>
                            <span class="contact-portal-info-list__body">
                                <small>WhatsApp</small>
                                <a href="https://wa.me/<?= SecurityHelper::escape(preg_replace('/[^0-9]/', '', $contactWhatsapp)) ?>" target="_blank" rel="noopener noreferrer"><?= SecurityHelper::escape($whatsappDisplay) ?></a>
                            </span>
                        </li>
                        <li>
                            <span class="contact-portal-info-list__icon"><i class="fa-solid fa-envelope"></i></span>
                            <span class="contact-portal-info-list__body">
                                <small>E-posta</small>
                                <a href="mailto:<?= SecurityHelper::escape($contactEmail) ?>"><?= SecurityHelper::escape($contactEmail) ?></a>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="portal-trust-card">
                    <h4><i class="fa-solid fa-clock"></i> Yanıt Süresi</h4>
                    <ul>
                        <li>Form başvurularına 1–2 iş günü</li>
                        <li>WhatsApp mesajlarına aynı gün</li>
                        <li>İşletme kayıtları admin onaylı</li>
                        <li>KVKK kapsamında veri güvenliği</li>
                    </ul>
                </div>
            </aside>

            <div class="contact-portal-form-wrap reveal-on-scroll">
                <article class="biz-portal-panel contact-portal-form-panel">
                    <header class="biz-portal-panel__head">
                        <span class="portal-section__index">B</span>
                        <div>
                            <span class="portal-section__eyebrow">Başvuru</span>
                            <h2>İletişim Formu</h2>
                        </div>
                    </header>

                    <?php if ($successMsg !== ''): ?>
                        <div class="alert alert-success"><?= SecurityHelper::escape($successMsg) ?></div>
                    <?php endif; ?>
                    <?php if ($errorMsg !== ''): ?>
                        <div class="alert alert-danger"><?= SecurityHelper::escape($errorMsg) ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" class="contact-portal-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Adınız Soyadınız / İşletme Yetkilisi *</label>
                                <input type="text" name="name" class="form-control" value="<?= SecurityHelper::escape($name) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-posta Adresiniz *</label>
                                <input type="email" name="email" class="form-control" value="<?= SecurityHelper::escape($email) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Telefon / WhatsApp</label>
                                <input type="text" name="phone" class="form-control" placeholder="05XX XXX XX XX" value="<?= SecurityHelper::escape($phone) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Başvuru Konusu *</label>
                                <select name="subject" class="form-select" required>
                                    <option value="Yeni İşletme Kaydı" <?= $subject === 'Yeni İşletme Kaydı' ? 'selected' : '' ?>>Yeni İşletme Kaydı Oluşturmak İstiyorum</option>
                                    <option value="Sosyal Medya Yönetimi" <?= $subject === 'Sosyal Medya Yönetimi' ? 'selected' : '' ?>>Sosyal Medya Yönetimi Hizmeti Almak İstiyorum</option>
                                    <option value="Google Haritalar Kurulumu" <?= $subject === 'Google Haritalar Kurulumu' ? 'selected' : '' ?>>Google Haritalar / Konum Kurulumu İstiyorum</option>
                                    <option value="Yapay Zeka Görsel Üretimi" <?= $subject === 'Yapay Zeka Görsel Üretimi' ? 'selected' : '' ?>>Yapay Zeka Destekli Görsel & Tasarım Hizmeti</option>
                                    <option value="Dijital Kartvizit & QR Menü" <?= $subject === 'Dijital Kartvizit & QR Menü' ? 'selected' : '' ?>>QR Kodlu Dijital Menü / Dijital Kartvizit Kurulumu</option>
                                    <option value="Bilgi Güncelleme" <?= $subject === 'Bilgi Güncelleme' ? 'selected' : '' ?>>Mevcut İşletme Bilgilerini Güncelleme</option>
                                    <option value="Reklam ve Sponsorluk" <?= $subject === 'Reklam ve Sponsorluk' ? 'selected' : '' ?>>Reklam ve Vitrin Sponsorluğu Hakkında</option>
                                    <option value="Genel Görüş/Öneri" <?= $subject === 'Genel Görüş/Öneri' ? 'selected' : '' ?>>Diğer Konular / Genel Görüş ve Öneri</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">İşletme Detayları / Mesajınız *</label>
                                <textarea name="message" rows="5" class="form-control" placeholder="İşletme adı, adres, telefon ve kısa açıklama yazabilirsiniz..." required><?= SecurityHelper::escape($message) ?></textarea>
                            </div>

                            <?php if ($captchaActive): ?>
                            <div class="col-12 portal-form-section portal-form-section--captcha">
                                <label class="form-label">Güvenlik Doğrulaması *</label>
                                <div class="contact-portal-captcha">
                                    <span class="contact-portal-captcha__prompt"><?= $num1 ?> + <?= $num2 ?> =</span>
                                    <input type="number" name="captcha_answer" class="form-control" placeholder="Sonuç" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="col-12 portal-form-submit">
                                <button type="submit" class="btn btn-primary w-100 fw-semibold contact-portal-form__submit">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Başvuruyu Gönder
                                </button>
                            </div>
                        </div>
                    </form>
                </article>
            </div>
        </div>
    </div>
</section>

<?php
$hideFooterCTA = true;
require_once 'includes/footer.php';
?>
