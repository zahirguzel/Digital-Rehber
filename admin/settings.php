<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/telegram-notify.php';

$successMsg = '';
$errorMsg = '';

// Helper function for uploading site logo
function handleUpload($fileKey, $fallbackUrlKey, $currentValue = '') {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . '/../public/images/';
        $processResult = processAndSaveImage($_FILES[$fileKey], $targetDir, 'site_');
        
        if ($processResult['success']) {
            return $processResult['filename'];
        }
    }
    
    if (!empty($_POST[$fallbackUrlKey])) {
        return trim($_POST[$fallbackUrlKey]);
    }
    
    return $currentValue;
}

function ensureSettingsColumns(PDO $pdo) {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
    $definitions = [
        'default_city' => "VARCHAR(150) NULL AFTER site_title",
        'default_seo_title' => "VARCHAR(255) NULL AFTER default_city",
        'default_seo_desc' => "TEXT NULL AFTER default_seo_title",
        'home_hero_video' => "VARCHAR(255) NULL AFTER site_logo",
        'home_hero_poster' => "VARCHAR(255) NULL AFTER home_hero_video",
        'home_hero_title' => "VARCHAR(255) NULL AFTER home_hero_poster",
        'home_hero_subtitle' => "VARCHAR(255) NULL AFTER home_hero_title",
        'home_hero_description' => "TEXT NULL AFTER home_hero_subtitle",
        'home_hero_primary_text' => "VARCHAR(120) NULL AFTER home_hero_description",
        'home_hero_primary_url' => "VARCHAR(255) NULL AFTER home_hero_primary_text",
        'home_hero_secondary_text' => "VARCHAR(120) NULL AFTER home_hero_primary_url",
        'home_hero_secondary_url' => "VARCHAR(255) NULL AFTER home_hero_secondary_text",
        'home_hero_consumer_text' => "VARCHAR(255) NULL AFTER home_hero_secondary_url",
        'home_hero_consumer_link_text' => "VARCHAR(120) NULL AFTER home_hero_consumer_text",
        'home_search_label' => "VARCHAR(160) NULL AFTER home_hero_consumer_link_text",
        'home_services_title' => "VARCHAR(160) NULL AFTER home_search_label",
        'home_services_desc' => "TEXT NULL AFTER home_services_title",
        'home_influencer_title' => "VARCHAR(160) NULL AFTER home_services_desc",
        'home_influencer_desc' => "TEXT NULL AFTER home_influencer_title",
        'home_events_title' => "VARCHAR(160) NULL AFTER home_influencer_desc",
        'home_events_desc' => "TEXT NULL AFTER home_events_title",
        'home_blog_title' => "VARCHAR(160) NULL AFTER home_events_desc",
        'home_blog_desc' => "TEXT NULL AFTER home_blog_title",
        'home_banner_fallback_title' => "VARCHAR(160) NULL AFTER home_blog_desc",
        'home_banner_fallback_description' => "TEXT NULL AFTER home_banner_fallback_title",
        // Mail ayarları
        'mail_host'        => "VARCHAR(255) NULL DEFAULT NULL",
        'mail_port'        => "SMALLINT UNSIGNED NULL DEFAULT 587",
        'mail_user'        => "VARCHAR(255) NULL DEFAULT NULL",
        'mail_pass'        => "VARCHAR(255) NULL DEFAULT NULL",
        'mail_from'        => "VARCHAR(255) NULL DEFAULT NULL",
        'mail_from_name'   => "VARCHAR(255) NULL DEFAULT NULL",
        'mail_admin_email' => "VARCHAR(255) NULL DEFAULT NULL",
    ];

    foreach ($definitions as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN $column $definition");
        }
    }

    $ensured = true;
}

// Fetch Settings (Single row ID = 1)
try {
    ensureSettingsColumns($db->getPDO());
    $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
    if (!$settings) {
        // Create a fallback row if somehow empty
        $db->getPDO()->exec("INSERT INTO settings (site_title) VALUES ('Şehir Rehberi')");
        $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
    }
} catch (Exception $e) {
    die("Ayarlar yüklenemedi: " . $e->getMessage());
}

// Fetch Active Admin Info
$admin = null;
try {
    $stmtAdmin = $db->query("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
    $admin = $stmtAdmin->fetch();
} catch (Exception $e) {
    // Fallback
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Mail Ayarlarını Kaydet ────────────────────────────────────────
    if (isset($_POST['update_mail'])) {
        $mail_host        = trim($_POST['mail_host'] ?? '');
        $mail_port        = (int)($_POST['mail_port'] ?? 587);
        $mail_user        = trim($_POST['mail_user'] ?? '');
        $mail_pass_new    = trim($_POST['mail_pass'] ?? '');
        $mail_from        = trim($_POST['mail_from'] ?? '');
        $mail_from_name   = trim($_POST['mail_from_name'] ?? '');
        $mail_admin_email = trim($_POST['mail_admin_email'] ?? '');

        // Şifre boş bırakıldıysa eski şifreyi koru
        if ($mail_pass_new === '') {
            $mail_pass_new = $settings['mail_pass'] ?? '';
        }

        if ($mail_port < 1 || $mail_port > 65535) {
            $errorMsg = 'Geçersiz port numarası (1-65535 arası olmalıdır).';
        } elseif (!empty($mail_from) && !filter_var($mail_from, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Gönderici e-posta adresi geçersiz.';
        } elseif (!empty($mail_admin_email) && !filter_var($mail_admin_email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Yönetici e-posta adresi geçersiz.';
        } else {
            try {
                $db->getPDO()->prepare(
                    'UPDATE settings SET mail_host=?, mail_port=?, mail_user=?, mail_pass=?, mail_from=?, mail_from_name=?, mail_admin_email=? WHERE id=1'
                )->execute([$mail_host, $mail_port, $mail_user, $mail_pass_new, $mail_from, $mail_from_name, $mail_admin_email]);
                if (function_exists('logAction')) logAction('update', 'settings', 'Mail Ayarları', 1);
                $successMsg = 'Mail ayarları başarıyla kaydedildi.';
                $settings   = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
            } catch (Exception $e) {
                $errorMsg = 'Mail ayarları kaydedilemedi: ' . $e->getMessage();
            }
        }
    }

    // ── Mail Test Gönder ────────────────────────────────────────────
    if (isset($_POST['test_mail'])) {
        $testTo = trim($_POST['mail_admin_email'] ?? ($settings['mail_admin_email'] ?? ''));
        if (empty($testTo)) {
            $errorMsg = 'Test için Yönetici E-posta adresi boş olamaz.';
        } else {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                $tMail = new PHPMailer\PHPMailer\PHPMailer(true);
                $tMail->isSMTP();
                $tMail->Host       = $settings['mail_host'] ?? '';
                $tMail->SMTPAuth   = true;
                $tMail->Username   = $settings['mail_user'] ?? '';
                $tMail->Password   = $settings['mail_pass'] ?? '';
                $tMail->Port       = (int)($settings['mail_port'] ?? 587);
                $tMail->SMTPSecure = ((int)($settings['mail_port'] ?? 587) === 465)
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                
                // Hata tespiti için debug modunu açalım (sadece hata durumunda mesajı görmek için yakalayacağız)
                $tMail->SMTPDebug = 2;
                $debugLog = '';
                $tMail->Debugoutput = function($str, $level) use (&$debugLog) {
                    $debugLog .= htmlspecialchars($str) . "<br>";
                };

                $tMail->setFrom(
                    $settings['mail_from'] ?: ($settings['mail_user'] ?? 'noreply@example.com'),
                    $settings['mail_from_name'] ?: ($settings['site_title'] ?? 'Sistem')
                );
                $tMail->addAddress($testTo, 'Yönetici');
                $tMail->CharSet = 'UTF-8';
                $tMail->isHTML(true);
                $tMail->Subject = 'Test Maili — ' . ($settings['site_title'] ?? 'Site');
                $tMail->Body    = '<h2>✅ SMTP Bağlantısı Başarılı!</h2><p>Bu mail admin panelinden gönderilmiş bir test mailidir.</p>';
                $tMail->send();
                $successMsg = '✅ Test maili <strong>' . htmlspecialchars($testTo) . '</strong> adresine başarıyla gönderildi!';
            } catch (\Exception $e) {
                $errorMsg = '❌ Mail gönderilemedi: ' . htmlspecialchars($e->getMessage()) . '<hr><strong>Detaylı Bağlantı Hatası:</strong><br><small>' . $debugLog . '</small>';
            }
        }
    }

    if (isset($_POST['update_telegram'])) {
        $telegram_enabled = isset($_POST['telegram_enabled']) ? 1 : 0;
        $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
        $telegram_chat_id_1 = trim($_POST['telegram_chat_id_1'] ?? '');
        $telegram_chat_id_2 = trim($_POST['telegram_chat_id_2'] ?? '');

        try {
            $db->getPDO()->prepare('UPDATE settings SET telegram_enabled = ?, telegram_bot_token = ?, telegram_chat_id_1 = ?, telegram_chat_id_2 = ? WHERE id = 1')
                ->execute([$telegram_enabled, $telegram_bot_token, $telegram_chat_id_1, $telegram_chat_id_2]);
            if (function_exists('logAction')) logAction('update', 'settings', 'Telegram Ayarları', 1);
            $successMsg = 'Telegram bildirim ayarları kaydedildi.';
            $settings = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
        } catch (Exception $e) {
            $errorMsg = 'Telegram ayarları kaydedilemedi. Migration dosyasını çalıştırdınız mı? Hata: ' . $e->getMessage();
        }
    }

    if (isset($_POST['test_telegram'])) {
        $telegram_enabled = isset($_POST['telegram_enabled']) ? 1 : 0;
        $telegram_bot_token = trim($_POST['telegram_bot_token'] ?? '');
        $telegram_chat_id_1 = trim($_POST['telegram_chat_id_1'] ?? '');
        $telegram_chat_id_2 = trim($_POST['telegram_chat_id_2'] ?? '');

        try {
            $db->getPDO()->prepare('UPDATE settings SET telegram_enabled = ?, telegram_bot_token = ?, telegram_chat_id_1 = ?, telegram_chat_id_2 = ? WHERE id = 1')
                ->execute([$telegram_enabled, $telegram_bot_token, $telegram_chat_id_1, $telegram_chat_id_2]);
            $settings = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
        } catch (Exception $e) {
            $errorMsg = 'Telegram ayarları kaydedilemedi: ' . $e->getMessage();
        }

        if (empty($errorMsg)) {
            $result = telegramSendTestNotification($pdo, true);
            if ($result['sent'] > 0) {
                $successMsg = 'Test bildirimi gönderildi (' . $result['sent'] . '/' . $result['total'] . ' alıcı).';
            } else {
                $errorMsg = 'Test bildirimi gönderilemedi. Bot token, chat ID ve bot ile sohbet başlatmayı kontrol edin.';
            }
        }
    }

    if (isset($_POST['update_color'])) {
        $color = trim($_POST['admin_primary_color'] ?? '#D62828');
        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $errorMsg = 'Geçersiz renk kodu.';
        } else {
            try {
                $db->getPDO()->prepare("UPDATE settings SET admin_primary_color=? WHERE id=1")->execute([$color]);
                if (function_exists('logAction')) logAction('update', 'settings', 'Panel Rengi', 1);
                $successMsg = 'Panel rengi güncellendi.';
                $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
            } catch (Exception $e) { $errorMsg = 'Hata: ' . $e->getMessage(); }
        }
    }

    if (isset($_POST['update_settings'])) {
        $site_title = trim($_POST['site_title']);
        $default_city = trim($_POST['default_city'] ?? '');
        $default_seo_title = trim($_POST['default_seo_title'] ?? '');
        $default_seo_desc = trim($_POST['default_seo_desc'] ?? '');
        $contact_email = trim($_POST['contact_email']);
        $contact_phone = trim($_POST['contact_phone']);
        $contact_whatsapp = trim($_POST['contact_whatsapp']);
        $contact_address = trim($_POST['contact_address']);
        $social_instagram = trim($_POST['social_instagram']);
        if (!empty($social_instagram) && strpos($social_instagram, 'http://') !== 0 && strpos($social_instagram, 'https://') !== 0) {
            $social_instagram = 'https://' . $social_instagram;
        }

        $social_facebook = trim($_POST['social_facebook']);
        if (!empty($social_facebook) && strpos($social_facebook, 'http://') !== 0 && strpos($social_facebook, 'https://') !== 0) {
            $social_facebook = 'https://' . $social_facebook;
        }

        $social_tiktok = trim($_POST['social_tiktok']);
        if (!empty($social_tiktok) && strpos($social_tiktok, 'http://') !== 0 && strpos($social_tiktok, 'https://') !== 0) {
            $social_tiktok = 'https://' . $social_tiktok;
        }

        $social_youtube = trim($_POST['social_youtube']);
        if (!empty($social_youtube) && strpos($social_youtube, 'http://') !== 0 && strpos($social_youtube, 'https://') !== 0) {
            $social_youtube = 'https://' . $social_youtube;
        }
        $contact_captcha = isset($_POST['contact_captcha']) ? 1 : 0;
        
        // Logo Upload
        $remove_logo = isset($_POST['remove_logo']) ? 1 : 0;
        $currentLogo = $settings['site_logo'] ?? '';

        if ($remove_logo) {
            $site_logo = '';
            if (!empty($currentLogo) && strpos($currentLogo, 'http') !== 0) {
                @unlink('../public/images/' . $currentLogo);
            }
        } else {
            $site_logo = handleUpload('logo_file', 'logo_url', $currentLogo);
            if ($site_logo !== $currentLogo && !empty($currentLogo) && strpos($currentLogo, 'http') !== 0) {
                @unlink('../public/images/' . $currentLogo);
            }
        }

        try {
            $sql = "UPDATE settings SET 
                site_title = ?, 
                default_city = ?,
                default_seo_title = ?,
                default_seo_desc = ?,
                site_logo = ?, 
                contact_email = ?, 
                contact_phone = ?, 
                contact_whatsapp = ?, 
                contact_address = ?, 
                social_instagram = ?, 
                social_facebook = ?, 
                social_tiktok = ?, 
                social_youtube = ?,
                contact_captcha = ?
                WHERE id = 1";
                
            $stmt = $db->query($sql, [
                $site_title,
                $default_city,
                $default_seo_title,
                $default_seo_desc,
                $site_logo,
                $contact_email,
                $contact_phone,
                $contact_whatsapp,
                $contact_address,
                $social_instagram,
                $social_facebook,
                $social_tiktok,
                $social_youtube,
                $contact_captcha
            ]);
            
            if (function_exists('logAction')) logAction('update', 'settings', 'Genel Ayarlar', 1);
            $successMsg = "Site ayarları başarıyla güncellendi.";
            
            // Refresh settings local data
            $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
        } catch (Exception $e) {
            $errorMsg = "Veriler güncellenirken hata oluştu: " . $e->getMessage();
        }
        } elseif (isset($_POST['update_home_media'])) {
        $currentVideo = $settings['home_hero_video'] ?? '';
        $currentPoster = $settings['home_hero_poster'] ?? '';
        
        $home_hero_video = handleUpload('home_hero_video_file', 'home_hero_video_url', $currentVideo);
        $home_hero_poster = handleUpload('home_hero_poster_file', 'home_hero_poster_url', $currentPoster);
        $home_hero_title = trim($_POST['home_hero_title'] ?? '');
        $home_hero_subtitle = trim($_POST['home_hero_subtitle'] ?? '');
        $home_hero_description = trim($_POST['home_hero_description'] ?? '');
        $home_hero_primary_text = trim($_POST['home_hero_primary_text'] ?? '');
        $home_hero_primary_url = trim($_POST['home_hero_primary_url'] ?? '');
        $home_hero_secondary_text = trim($_POST['home_hero_secondary_text'] ?? '');
        $home_hero_secondary_url = trim($_POST['home_hero_secondary_url'] ?? '');
        $home_hero_consumer_text = trim($_POST['home_hero_consumer_text'] ?? '');
        $home_hero_consumer_link_text = trim($_POST['home_hero_consumer_link_text'] ?? '');
        $home_search_label = trim($_POST['home_search_label'] ?? '');
        $home_services_title = trim($_POST['home_services_title'] ?? '');
        $home_services_desc = trim($_POST['home_services_desc'] ?? '');
        $home_influencer_title = trim($_POST['home_influencer_title'] ?? '');
        $home_influencer_desc = trim($_POST['home_influencer_desc'] ?? '');
        $home_events_title = trim($_POST['home_events_title'] ?? '');
        $home_events_desc = trim($_POST['home_events_desc'] ?? '');
        $home_blog_title = trim($_POST['home_blog_title'] ?? '');
        $home_blog_desc = trim($_POST['home_blog_desc'] ?? '');
        $home_banner_fallback_title = trim($_POST['home_banner_fallback_title'] ?? '');
        $home_banner_fallback_description = trim($_POST['home_banner_fallback_description'] ?? '');
         
        try {
            $db->getPDO()->prepare("UPDATE settings SET home_hero_video = ?, home_hero_poster = ?, home_hero_title = ?, home_hero_subtitle = ?, home_hero_description = ?, home_hero_primary_text = ?, home_hero_primary_url = ?, home_hero_secondary_text = ?, home_hero_secondary_url = ?, home_hero_consumer_text = ?, home_hero_consumer_link_text = ?, home_search_label = ?, home_services_title = ?, home_services_desc = ?, home_influencer_title = ?, home_influencer_desc = ?, home_events_title = ?, home_events_desc = ?, home_blog_title = ?, home_blog_desc = ?, home_banner_fallback_title = ?, home_banner_fallback_description = ? WHERE id = 1")->execute([$home_hero_video, $home_hero_poster, $home_hero_title, $home_hero_subtitle, $home_hero_description, $home_hero_primary_text, $home_hero_primary_url, $home_hero_secondary_text, $home_hero_secondary_url, $home_hero_consumer_text, $home_hero_consumer_link_text, $home_search_label, $home_services_title, $home_services_desc, $home_influencer_title, $home_influencer_desc, $home_events_title, $home_events_desc, $home_blog_title, $home_blog_desc, $home_banner_fallback_title, $home_banner_fallback_description]);
            if (function_exists('logAction')) logAction('update', 'settings', 'Ana Sayfa İçerik', 1);
            $successMsg = "Ana sayfa medya ve içerik ayarları güncellendi.";
            $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
        } catch (Exception $e) {
            $errorMsg = "Hata: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_account'])) {
        $new_username = trim($_POST['new_username']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_username)) {
            $errorMsg = "Kullanıcı adı boş bırakılamaz.";
        } elseif (empty($current_password)) {
            $errorMsg = "İşlemi onaylamak için mevcut şifrenizi girmeniz gerekmektedir.";
        } else {
            // Verify current password
            if (!$admin || !password_verify($current_password, $admin['password'])) {
                $errorMsg = "Mevcut şifreniz hatalı!";
            } else {
                // Check username taken
                try {
                    $stmtUserCheck = $db->query("SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?", [$new_username, $_SESSION['admin_id']]);
                    $taken = $stmtUserCheck->fetchColumn();
                    
                    if ($taken > 0) {
                        $errorMsg = "Bu kullanıcı adı zaten başka bir yönetici tarafından kullanılıyor.";
                    } else {
                        if (!empty($new_password)) {
                            if ($new_password !== $confirm_password) {
                                $errorMsg = "Yeni şifreler uyuşmuyor.";
                            } elseif (strlen($new_password) < 8) {
                                $errorMsg = "Yeni şifre en az 8 karakter olmalıdır.";
                            } else {
                                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmtUpdate = $db->query("UPDATE admins SET username = ?, password = ? WHERE id = ?", [$new_username, $hashed, $_SESSION['admin_id']]);
                                if (function_exists('logAction')) logAction('update', 'admins', 'Hesap Ayarları (Şifre Dahil)', $_SESSION['admin_id']);
                                $successMsg = "Kullanıcı adı ve şifreniz başarıyla güncellendi.";
                                $_SESSION['admin_username'] = $new_username;
                            }
                        } else {
                            $stmtUpdate = $db->query("UPDATE admins SET username = ? WHERE id = ?", [$new_username, $_SESSION['admin_id']]);
                            if (function_exists('logAction')) logAction('update', 'admins', 'Hesap Ayarları', $_SESSION['admin_id']);
                            $successMsg = "Kullanıcı adınız başarıyla güncellendi.";
                            $_SESSION['admin_username'] = $new_username;
                        }
                    }
                } catch (Exception $e) {
                    $errorMsg = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
                }
            }
        }
        
        // Refresh admin local data
        try {
            $stmtAdmin = $db->query("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
            $admin = $stmtAdmin->fetch();
        } catch (Exception $e) {
            // Fallback
        }
    }
}

$pageTitle = 'Site & SEO Ayarları';
include 'includes/header.php';
?>

<!-- Alerts -->
<?php
if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<?php
if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-sliders me-2 text-primary"></i> Portal Yapılandırma Alanı</h5>
    </div>
    <div class="card-body p-4">
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold text-navy" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab">
                    <i class="fa-solid fa-circle-info me-1"></i> Genel & İletişim
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-navy" id="color-tab" data-bs-toggle="tab" data-bs-target="#color-pane" type="button" role="tab">
                    <i class="fa-solid fa-palette me-1"></i> Renk & Görünüm
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-navy" id="telegram-tab" data-bs-toggle="tab" data-bs-target="#telegram-pane" type="button" role="tab">
                    <i class="fa-brands fa-telegram me-1"></i> Telegram
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-navy" id="mail-tab" data-bs-toggle="tab" data-bs-target="#mail-pane" type="button" role="tab">
                    <i class="fa-solid fa-envelope me-1"></i> E-posta
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-navy" id="account-tab" data-bs-toggle="tab" data-bs-target="#account-pane" type="button" role="tab">
                    <i class="fa-solid fa-user-gear me-1"></i> Yönetici Hesabı
                </button>
            </li>
                    <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-navy" id="home-media-tab" data-bs-toggle="tab" data-bs-target="#home-media-pane" type="button" role="tab">
                    <i class="fa-solid fa-photo-film me-1"></i> Ana Sayfa İçerik
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="settingsTabContent">
            
            <!-- TAB 1: GENERAL & CONTACT -->
            <div class="tab-pane fade show active" id="general-pane" role="tabpanel" aria-labelledby="general-tab" tabindex="0">
                <form action="" method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_settings" value="1">
                    <div class="row g-3">
                        <!-- Site Title -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Site / Portal Adı</label>
                            <input type="text" name="site_title" class="form-control" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" placeholder="Proje Adı (Örn: Kıbrıs Rehberi)">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Proje Ana Bölgesi (İl/Şehir)</label>
                            <select name="default_city" class="form-select">
                                <option value="" <?= empty($settings['default_city']) ? 'selected' : '' ?>>-- Şehir Seçiniz --</option>
                                <?php
                                try {
                                    $dbCities = $db->query("SELECT name FROM cities ORDER BY name ASC")->fetchAll();
                                    $currentCity = mb_strtolower($settings['default_city'] ?? '');
                                    foreach ($dbCities as $dbCity) {
                                        $cityName = $dbCity['name'];
                                        $selected = (mb_strtolower($cityName) === $currentCity) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($cityName) . '" ' . $selected . '>' . htmlspecialchars($cityName) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0">Varsayılan SEO Başlığı</label>
                                <span class="badge bg-light text-dark border" id="seoTitleCounterBadge">0 / 60 Karakter</span>
                            </div>
                            <input type="text" name="default_seo_title" id="defaultSeoTitleInput" class="form-control" value="<?= htmlspecialchars($settings['default_seo_title'] ?? '') ?>" placeholder="Örn: Kıbrıs Rehberi | Dijital İşletme Rehberi" maxlength="100" oninput="updateSeoCounters()">
                            <small class="text-muted d-block mt-1"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Google arama sonuçlarında önerilen başlık uzunluğu: <strong>50-60 karakter</strong>.</small>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0">Varsayılan SEO Açıklaması (Description)</label>
                                <span class="badge bg-light text-dark border" id="seoDescCounterBadge">0 / 160 Karakter</span>
                            </div>
                            <textarea name="default_seo_desc" id="defaultSeoDescInput" class="form-control" rows="2" placeholder="Tüm yerel esnaf ve firmaların adres, telefon ve harita bilgileri..." maxlength="250" oninput="updateSeoCounters()"><?= htmlspecialchars($settings['default_seo_desc'] ?? '') ?></textarea>
                            <small class="text-muted d-block mt-1"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Google arama sonuçlarında önerilen açıklama uzunluğu: <strong>150-160 karakter</strong>.</small>
                        </div>
                        
                        <!-- Site Logo -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Site Logo Görseli</label>

                            <!-- Canlı Önizleme -->
                            <div id="logo-preview-box" class="mb-3 p-3 rounded border bg-light d-flex align-items-center justify-content-center" style="min-height:90px;">
                                <?php if (!empty($settings['site_logo'])): 
                                    $previewLogoUrl = (strpos($settings['site_logo'], 'http') === 0)
                                        ? htmlspecialchars($settings['site_logo'])
                                        : '../public/images/' . htmlspecialchars($settings['site_logo']);
                                ?>
                                    <img id="logo-preview-img" src="<?= $previewLogoUrl ?>" alt="Logo Önizleme" style="max-height:80px; max-width:100%; object-fit:contain;">
                                <?php else: ?>
                                    <span id="logo-preview-placeholder" class="text-muted small"><i class="fa-solid fa-image me-1"></i> Henüz logo yüklenmedi</span>
                                    <img id="logo-preview-img" src="" alt="" style="max-height:80px; max-width:100%; object-fit:contain; display:none;">
                                <?php endif; ?>
                            </div>

                            <input type="file" name="logo_file" id="logo_file_input" class="form-control mb-2" accept="image/*">
                            <input type="text" name="logo_url" id="logo_url_input" class="form-control form-control-sm" placeholder="Veya harici logo URL'si girin..." value="<?= htmlspecialchars((!empty($settings['site_logo']) && strpos($settings['site_logo'], 'http') === 0) ? $settings['site_logo'] : '') ?>">
                            <?php if (!empty($settings['site_logo']) && strpos($settings['site_logo'], 'http') !== 0): ?>
                                <div class="small text-success mt-1"><i class="fa-solid fa-file-image me-1"></i> Aktif logo: <?= htmlspecialchars($settings['site_logo']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($settings['site_logo'])): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" id="removeLogoCheck" value="1">
                                    <label class="form-check-label small text-danger fw-semibold" for="removeLogoCheck" style="cursor: pointer;">
                                        <i class="fa-solid fa-trash-can me-1"></i> Mevcut Logoyu Kaldır
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contact Captcha Challenge Switch -->
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch bg-light p-3 border rounded-3 d-flex align-items-center justify-content-between" style="border-radius: var(--radius) !important;">
                                <div>
                                    <label class="form-check-label fw-bold text-navy mb-1" for="contactCaptchaSwitch" style="cursor:pointer;"><i class="fa-solid fa-shield-halved text-primary me-2"></i> İletişim Formunda Matematiksel İşlem (Spam Koruması)</label>
                                    <div class="text-muted small">Aktif edilirse, iletişim sayfasında spam mesajları engellemek için toplama sorusu sorulur.</div>
                                </div>
                                <input class="form-check-input" type="checkbox" name="contact_captcha" id="contactCaptchaSwitch" <?= ($settings['contact_captcha'] ?? 1) ? 'checked' : '' ?> style="transform: scale(1.3); margin-right: 15px; cursor:pointer;">
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="fw-bold text-navy"><i class="fa-solid fa-envelope me-1"></i> İletişim Bilgileri (Alt Bilgi/Footer ve İletişim Sayfası için)</h6>
                        
                        <!-- Contact Phone -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sabit Telefon</label>
                            <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" placeholder="0326 222 22 22">
                        </div>
                        
                        <!-- Contact Whatsapp -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Destek WhatsApp Hattı</label>
                            <input type="text" name="contact_whatsapp" class="form-control" value="<?= htmlspecialchars($settings['contact_whatsapp'] ?? '') ?>" placeholder="905551112233">
                        </div>
                        
                        <!-- Contact Email -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">İletişim E-Postası</label>
                            <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" placeholder="bilgi@domain.com">
                        </div>
                        
                        <!-- Contact Address -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Fiziksel Adres</label>
                            <input type="text" name="contact_address" class="form-control" value="<?= htmlspecialchars($settings['contact_address'] ?? '') ?>" placeholder="Örn: Merkez, İstanbul">
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="fw-bold text-navy"><i class="fa-solid fa-share-nodes me-1"></i> Portal Sosyal Medya Sayfaları</h6>
                        
                        <!-- YouTube -->
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label fw-semibold"><i class="fa-brands fa-youtube me-1 text-danger"></i> YouTube URL</label>
                            <input type="text" name="social_youtube" class="form-control" value="<?= htmlspecialchars($settings['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/@...">
                        </div>

                        <!-- Instagram -->
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label fw-semibold"><i class="fa-brands fa-instagram me-1 text-danger"></i> Instagram URL</label>
                            <input type="text" name="social_instagram" class="form-control" value="<?= htmlspecialchars($settings['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
                        </div>
                        
                        <!-- Facebook -->
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label fw-semibold"><i class="fa-brands fa-facebook-f me-1 text-primary"></i> Facebook URL</label>
                            <input type="text" name="social_facebook" class="form-control" value="<?= htmlspecialchars($settings['social_facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
                        </div>
                        
                        <!-- TikTok -->
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label fw-semibold"><i class="fa-brands fa-tiktok me-1 text-dark"></i> TikTok URL</label>
                            <input type="text" name="social_tiktok" class="form-control" value="<?= htmlspecialchars($settings['social_tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@...">
                        </div>
                    </div>
                    <div class="mt-4 border-top pt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-floppy-disk me-1"></i> Ayarları Kaydet</button>
                    </div>
                </form>
            </div>

            
            <!-- TAB 2: COLOR & APPEARANCE -->
            <div class="tab-pane fade" id="color-pane" role="tabpanel" tabindex="0">
                <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_color" value="1">
                    <div class="row g-4">
                        <div class="col-12">
                            <h6 class="fw-bold text-navy mb-1"><i class="fa-solid fa-swatchbook me-2 text-primary"></i>Panel Ana Rengi</h6>
                            <p class="text-muted small">Admin panelinin buton, badge ve vurgu rengi. Değiştirdiğinizde tüm sayfalar güncellenir.</p>
                        </div>

                        <!-- Color Picker -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Renk Seç</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" name="admin_primary_color" id="colorPicker"
                                       value="<?= htmlspecialchars($settings['admin_primary_color'] ?? '#D62828') ?>"
                                       style="width:54px;height:54px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;padding:4px;">
                                <div>
                                    <div class="fw-semibold" id="colorHexLabel"><?= htmlspecialchars($settings['admin_primary_color'] ?? '#D62828') ?></div>
                                    <div class="text-muted small">Mevcut panel rengi</div>
                                </div>
                            </div>
                        </div>

                        <!-- Preset Colors -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hazır Renkler</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
$presets = [
                                    '#D62828' => 'Marka Kırmızısı (Logo)',
                                    '#1F242B' => 'Antrasit (Logo)',
                                    '#2563EB' => 'Mavi',
                                    '#16a34a' => 'Yeşil',
                                    '#7c3aed' => 'Mor',
                                    '#0f172a' => 'Koyu Lacivert',
                                    '#dc2626' => 'Kırmızı',
                                    '#0891b2' => 'Camgöbeği',
                                    '#d97706' => 'Amber',
                                    '#374151' => 'Gri',
                                ];
                                foreach ($presets as $hex => $name): ?>
                                    <button type="button" class="preset-color"
                                            onclick="setColor('<?= $hex ?>')"
                                            title="<?= $name ?>"
                                            style="width:32px;height:32px;border-radius:8px;background:<?= $hex ?>;border:2px solid transparent;cursor:pointer;transition:transform .1s,border-color .1s;"
                                            onmouseover="this.style.transform='scale(1.15)'"
                                            onmouseout="this.style.transform='scale(1)'">
                                    </button>
                                <?php
endforeach; ?>
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Önizleme</label>
                            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                    <button class="btn btn-sm preview-btn" id="previewPrimary"
                                            style="background:var(--preview-color,#D62828);color:#fff;border:none;">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Kaydet
                                    </button>
                                    <span class="badge preview-badge" id="previewBadge"
                                          style="background:var(--preview-color,#D62828);color:#fff;">Aktif</span>
                                    <a href="#" class="preview-link"
                                       id="previewLink" style="color:var(--preview-color,#D62828);font-weight:600;">Bağlantı</a>
                                    <div class="preview-bar rounded"
                                         id="previewBar" style="height:6px;width:80px;background:var(--preview-color,#D62828);border-radius:4px;"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="mt-4 border-top pt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 py-2">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Rengi Kaydet
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB: TELEGRAM NOTIFICATIONS -->
            <div class="tab-pane fade" id="telegram-pane" role="tabpanel" tabindex="0">
                <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_telegram" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-navy mb-1"><i class="fa-brands fa-telegram me-2 text-primary"></i>Telegram Bildirimleri</h6>
                            <p class="text-muted small mb-0">İletişim formu, influencer başvurusu ve influencer iş birliği teklifleri geldiğinde aşağıdaki <strong>2 alıcıya</strong> anlık mesaj gider.</p>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="form-check form-switch bg-light p-3 border rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <label class="form-check-label fw-bold text-navy mb-1" for="telegramEnabledSwitch" style="cursor:pointer;">Telegram bildirimlerini aktif et</label>
                                    <div class="text-muted small">Kapalıyken formlar normal çalışır, Telegram mesajı gitmez.</div>
                                </div>
                                <input class="form-check-input" type="checkbox" name="telegram_enabled" id="telegramEnabledSwitch" <?= !empty($settings['telegram_enabled']) ? 'checked' : '' ?> style="transform: scale(1.3); margin-right: 15px; cursor:pointer;">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Bot Token</label>
                            <input type="text" name="telegram_bot_token" class="form-control" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" placeholder="123456789:AAH...">
                            <div class="form-text">@BotFather üzerinden oluşturduğunuz bot token.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">1. Alıcı Chat ID</label>
                            <input type="text" name="telegram_chat_id_1" class="form-control" value="<?= htmlspecialchars($settings['telegram_chat_id_1'] ?? '') ?>" placeholder="123456789">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">2. Alıcı Chat ID</label>
                            <input type="text" name="telegram_chat_id_2" class="form-control" value="<?= htmlspecialchars($settings['telegram_chat_id_2'] ?? '') ?>" placeholder="987654321">
                        </div>

                        <div class="col-12">
                            <div class="alert alert-light border small mb-0">
                                <strong>Kurulum:</strong>
                                <ol class="mb-0 ps-3">
                                    <li>Telegram'da @BotFather ile bot oluşturun, token'ı buraya yapıştırın.</li>
                                    <li>Her iki kişi de bota <code>/start</code> yazsın.</li>
                                    <li>Chat ID için @userinfobot veya @getidsbot kullanın.</li>
                                    <li>Test butonuyla her iki alıcıya mesaj gidiyor mu kontrol edin.</li>
                                </ol>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="small text-muted">
                                Bildirim gönderilen formlar: İletişim formu · Influencer başvurusu · Influencer iş birliği teklifi
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-top pt-4 d-flex justify-content-between flex-wrap gap-2">
                        <button type="submit" formaction="" name="test_telegram" value="1" class="btn btn-outline-primary px-4 py-2">
                            <i class="fa-solid fa-paper-plane me-1"></i> Test Bildirimi Gönder
                        </button>
                        <button type="submit" class="btn btn-primary px-5 py-2">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Telegram Ayarlarını Kaydet
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB: E-POSTA AYARLARI -->
            <div class="tab-pane fade" id="mail-pane" role="tabpanel" aria-labelledby="mail-tab" tabindex="0">

                <!-- Kaydetme Formu -->
                <form action="" method="POST">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_mail" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-navy mb-1"><i class="fa-solid fa-envelope me-2 text-primary"></i>E-posta (SMTP) Ayarları</h6>
                            <p class="text-muted small mb-0">Tüm sistem mailleri (şifre sıfırlama, başvuru bildirimi vb.) bu ayarlar üzerinden gönderilir. Şifre alanını boş bırakırsanız mevcut şifre korunur.</p>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <!-- SMTP Sunucu & Port -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">SMTP Sunucu (Host)</label>
                            <input type="text" name="mail_host" class="form-control" value="<?= htmlspecialchars($settings['mail_host'] ?? '') ?>" placeholder="Örn: smtp.gmail.com veya smtp.hostinger.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="mail_port" class="form-control" list="port-options" value="<?= htmlspecialchars((string)($settings['mail_port'] ?? 587)) ?>" min="1" max="65535" placeholder="Örn: 587 veya 465">
                            <datalist id="port-options">
                                <option value="587">587 — TLS</option>
                                <option value="465">465 — SSL</option>
                                <option value="25">25 — Standart</option>
                                <option value="2525">2525 — Alternatif</option>
                            </datalist>
                        </div>

                        <!-- Kullanıcı Adı & Şifre -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SMTP Kullanıcı Adı (E-posta)</label>
                            <input type="email" name="mail_user" class="form-control" value="<?= htmlspecialchars($settings['mail_user'] ?? '') ?>" placeholder="mail@domain.com" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">SMTP Şifresi</label>
                            <input type="password" name="mail_pass" class="form-control" placeholder="Boş bırakırsanız mevcut şifre korunur" autocomplete="new-password">
                            <?php if (!empty($settings['mail_pass'])): ?>
                                <small class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Şifre kayıtlı.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <!-- Gönderici Adı & Adresi -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gönderici E-posta Adresi</label>
                            <input type="email" name="mail_from" class="form-control" value="<?= htmlspecialchars($settings['mail_from'] ?? '') ?>" placeholder="noreply@domain.com">
                            <small class="text-muted">Maillerin &quot;From&quot; alanında görünen adres.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gönderici Adı</label>
                            <input type="text" name="mail_from_name" class="form-control" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '') ?>" placeholder="Örn: Kıbrıs Rehberim">
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <!-- Yönetici Bildirimleri -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold"><i class="fa-solid fa-user-shield me-1 text-warning"></i>Yönetici Bildirim E-postası</label>
                            <input type="email" name="mail_admin_email" class="form-control" value="<?= htmlspecialchars($settings['mail_admin_email'] ?? '') ?>" placeholder="admin@domain.com">
                            <small class="text-muted">İletişim formu, işletme başvurusu gibi tüm site bildirimleri bu adrese gelir.</small>
                        </div>

                        <div class="col-12 mt-3 d-flex gap-2">
                            <button type="submit" name="update_mail" class="btn btn-primary px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Test Mail Formu (ayrı form) -->
                <form action="" method="POST" class="mt-4 pt-3 border-top">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="test_mail" value="1">
                    <div class="row g-3 align-items-end">
                        <div class="col-12">
                            <h6 class="fw-bold text-navy mb-1"><i class="fa-solid fa-paper-plane me-2 text-success"></i>SMTP Bağlantı Testi</h6>
                            <p class="text-muted small mb-0">Kaydettiğiniz ayarların çalışıp çalışmadığını test edin. Mail, Yönetici Bildirim E-postası adresine gönderilir.</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Test Gönderilecek Adres</label>
                            <input type="email" name="mail_admin_email" class="form-control" value="<?= htmlspecialchars($settings['mail_admin_email'] ?? '') ?>" placeholder="admin@domain.com">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fa-solid fa-vial me-1"></i> Test Maili Gönder
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="tab-pane fade" id="account-pane" role="tabpanel" aria-labelledby="account-tab" tabindex="0">
                <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_account" value="1">
                    <div class="row g-3">
                        <h6 class="fw-bold text-navy"><i class="fa-solid fa-user-shield me-1 text-primary"></i> Yönetici Giriş Bilgileri Güncelleme</h6>
                        <p class="text-muted small mb-2">Güvenliğiniz için varsayılan giriş bilgilerini değiştirmeniz ve güçlü bir şifre belirlemeniz önerilir.</p>
                        
                        <!-- Username -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Yönetici Kullanıcı Adı <span class="text-danger">*</span></label>
                            <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($admin['username'] ?? 'admin') ?>" required autocomplete="off">
                        </div>
                        
                        <!-- Current Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mevcut Şifre <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" placeholder="Mevcut şifrenizi girin..." required>
                        </div>
                        
                        <!-- New Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                            <span class="text-muted small" style="font-size: 11px;">En az 8 karakter olmalıdır.</span>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Yeni Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Yeni şifrenizi tekrar yazın">
                        </div>
                    </div>
                    
                    <div class="mt-4 border-top pt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success px-5 py-2"><i class="fa-solid fa-user-check me-1"></i> Hesabı Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
        
            <!-- TAB 5: HOME MEDIA & CONTENT -->
            <div class="tab-pane fade" id="home-media-pane" role="tabpanel" tabindex="0">
                <form action="" method="POST" enctype="multipart/form-data">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="update_home_media" value="1">
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <h6 class="fw-bold text-navy mb-1"><i class="fa-solid fa-photo-film me-2 text-primary"></i>Ana Sayfa İçerik & Medya Ayarları</h6>
                            <p class="text-muted small">Ana sayfadaki hero alanını, CTA butonlarını, bölüm başlıklarını ve medya dosyalarını buradan güncelleyebilirsiniz.</p>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Ana Başlık</label>
                            <input type="text" name="home_hero_title" class="form-control" value="<?= htmlspecialchars($settings['home_hero_title'] ?? '') ?>" placeholder="Örn: QR Kod ve Dijital Kartvizitiniz Görünsün mü?">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Üst Başlık (Eyebrow)</label>
                            <input type="text" name="home_hero_subtitle" class="form-control" value="<?= htmlspecialchars($settings['home_hero_subtitle'] ?? '') ?>" placeholder="Örn: Esnaf & İşletmelere Dijital Çözüm">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Hakkımızda / Açıklama Metni</label>
                            <textarea name="home_hero_description" class="form-control" rows="3"><?= htmlspecialchars($settings['home_hero_description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Birincil Buton Metni</label>
                            <input type="text" name="home_hero_primary_text" class="form-control" value="<?= htmlspecialchars($settings['home_hero_primary_text'] ?? '') ?>" placeholder="Örn: İşletmemi Eklet">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Birincil Buton Linki</label>
                            <input type="text" name="home_hero_primary_url" class="form-control" value="<?= htmlspecialchars($settings['home_hero_primary_url'] ?? '') ?>" placeholder="Örn: /iletisim?subject=Yeni%20%C4%B0%C5%9Fletme%20Kayd%C4%B1">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">İkincil Buton Metni</label>
                            <input type="text" name="home_hero_secondary_text" class="form-control" value="<?= htmlspecialchars($settings['home_hero_secondary_text'] ?? '') ?>" placeholder="Örn: Hizmetleri İncele">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">İkincil Buton Linki</label>
                            <input type="text" name="home_hero_secondary_url" class="form-control" value="<?= htmlspecialchars($settings['home_hero_secondary_url'] ?? '') ?>" placeholder="Örn: /hizmetlerimiz">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hero Alt Metni</label>
                            <input type="text" name="home_hero_consumer_text" class="form-control" value="<?= htmlspecialchars($settings['home_hero_consumer_text'] ?? '') ?>" placeholder="Örn: Bölgedeki işletmeleri arıyorsanız">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hero Alt Link Metni</label>
                            <input type="text" name="home_hero_consumer_link_text" class="form-control" value="<?= htmlspecialchars($settings['home_hero_consumer_link_text'] ?? '') ?>" placeholder="Örn: rehberde keşfedin">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Arama Kutusu Başlığı</label>
                            <input type="text" name="home_search_label" class="form-control" value="<?= htmlspecialchars($settings['home_search_label'] ?? '') ?>" placeholder="Örn: Kıbrıs Rehber Arama">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fa-solid fa-video text-muted me-1"></i> Video Yükle</label>
                            <input type="file" name="home_hero_video_file" class="form-control mb-2" accept="video/mp4,video/webm">
                            <input type="text" name="home_hero_video_url" class="form-control form-control-sm" placeholder="Veya URL girin..." value="<?= htmlspecialchars((!empty($settings['home_hero_video']) && strpos($settings['home_hero_video'], 'http') === 0) ? $settings['home_hero_video'] : '') ?>">
                            <?php if (!empty($settings['home_hero_video']) && strpos($settings['home_hero_video'], 'http') !== 0): ?>
                                <div class="small text-success mt-1"><i class="fa-solid fa-check me-1"></i> Mevcut video: <?= htmlspecialchars($settings['home_hero_video']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="fa-solid fa-image text-muted me-1"></i> Video Önizleme Fotoğrafı (Poster)</label>
                            <input type="file" name="home_hero_poster_file" class="form-control mb-2" accept="image/jpeg,image/png,image/webp">
                            <input type="text" name="home_hero_poster_url" class="form-control form-control-sm" placeholder="Veya URL girin..." value="<?= htmlspecialchars((!empty($settings['home_hero_poster']) && strpos($settings['home_hero_poster'], 'http') === 0) ? $settings['home_hero_poster'] : '') ?>">
                            <?php if (!empty($settings['home_hero_poster']) && strpos($settings['home_hero_poster'], 'http') !== 0): ?>
                                <div class="small text-success mt-1"><i class="fa-solid fa-check me-1"></i> Mevcut poster: <?= htmlspecialchars($settings['home_hero_poster']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hizmetler Bölüm Başlığı</label>
                            <input type="text" name="home_services_title" class="form-control" value="<?= htmlspecialchars($settings['home_services_title'] ?? '') ?>" placeholder="Örn: İşletmenizi Dijital Dünyada Büyütelim">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hizmetler Bölüm Açıklaması</label>
                            <textarea name="home_services_desc" class="form-control" rows="2"><?= htmlspecialchars($settings['home_services_desc'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Influencer Bölüm Başlığı</label>
                            <input type="text" name="home_influencer_title" class="form-control" value="<?= htmlspecialchars($settings['home_influencer_title'] ?? '') ?>" placeholder="Örn: Kıbrıs İçerik Üreticileri">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Influencer Bölüm Açıklaması</label>
                            <textarea name="home_influencer_desc" class="form-control" rows="2"><?= htmlspecialchars($settings['home_influencer_desc'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Etkinlik Bölüm Başlığı</label>
                            <input type="text" name="home_events_title" class="form-control" value="<?= htmlspecialchars($settings['home_events_title'] ?? '') ?>" placeholder="Örn: Yaklaşan Kıbrıs Etkinlikleri">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Etkinlik Bölüm Açıklaması</label>
                            <textarea name="home_events_desc" class="form-control" rows="2"><?= htmlspecialchars($settings['home_events_desc'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Blog Bölüm Başlığı</label>
                            <input type="text" name="home_blog_title" class="form-control" value="<?= htmlspecialchars($settings['home_blog_title'] ?? '') ?>" placeholder="Örn: Kıbrıs Rehberi Yazıları">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Blog Bölüm Açıklaması</label>
                            <textarea name="home_blog_desc" class="form-control" rows="2"><?= htmlspecialchars($settings['home_blog_desc'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reklam Alanı Başlığı</label>
                            <input type="text" name="home_banner_fallback_title" class="form-control" value="<?= htmlspecialchars($settings['home_banner_fallback_title'] ?? '') ?>" placeholder="Örn: Buraya Reklam Verebilirsiniz">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reklam Alanı Açıklaması</label>
                            <textarea name="home_banner_fallback_description" class="form-control" rows="2"><?= htmlspecialchars($settings['home_banner_fallback_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4 border-top pt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 py-2"><i class="fa-solid fa-floppy-disk me-1"></i> İçerikleri Kaydet</button>
                    </div>
                </form>
            </div>
            
        </div>
</div>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setColor(hex) {
    document.getElementById('colorPicker').value = hex;
    updatePreview(hex);
}
function updatePreview(hex) {
    document.getElementById('colorHexLabel').textContent = hex;
    var els = ['previewPrimary','previewBadge'];
    els.forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.style.background = hex;
    });
    var link = document.getElementById('previewLink');
    if (link) link.style.color = hex;
    var bar = document.getElementById('previewBar');
    if (bar) bar.style.background = hex;
}
var picker = document.getElementById('colorPicker');
if (picker) {
    picker.addEventListener('input', function(){ updatePreview(this.value); });
    updatePreview(picker.value);
}
// Open color tab if update_color was submitted
<?php
if (isset($_POST['update_color'])): ?>
document.addEventListener('DOMContentLoaded', function(){
    var tab = document.getElementById('color-tab');
    if (tab) { var bsTab = new bootstrap.Tab(tab); bsTab.show(); }
});
<?php
endif; ?>
<?php
if (isset($_POST['update_telegram']) || isset($_POST['test_telegram'])): ?>
document.addEventListener('DOMContentLoaded', function(){
    var tab = document.getElementById('telegram-tab');
    if (tab) { var bsTab = new bootstrap.Tab(tab); bsTab.show(); }
});
<?php
endif; ?>
<?php if (isset($_POST['update_home_media'])): ?>
document.addEventListener('DOMContentLoaded', function(){
    var tab = document.getElementById('home-media-tab');
    if (tab) { var bsTab = new bootstrap.Tab(tab); bsTab.show(); }
});
<?php endif; ?>

function updateSeoCounters() {
    const titleInput = document.getElementById('defaultSeoTitleInput');
    const descInput = document.getElementById('defaultSeoDescInput');
    const titleBadge = document.getElementById('seoTitleCounterBadge');
    const descBadge = document.getElementById('seoDescCounterBadge');
    
    if (titleInput && titleBadge) {
        const len = titleInput.value.length;
        titleBadge.textContent = len + ' / 60 Karakter';
        titleBadge.className = 'badge border ';
        if (len === 0) {
            titleBadge.className += 'bg-light text-dark';
        } else if (len >= 50 && len <= 60) {
            titleBadge.className += 'bg-success text-white';
        } else if (len > 60) {
            titleBadge.className += 'bg-danger text-white';
        } else {
            titleBadge.className += 'bg-warning text-dark';
        }
    }
    
    if (descInput && descBadge) {
        const len = descInput.value.length;
        descBadge.textContent = len + ' / 160 Karakter';
        descBadge.className = 'badge border ';
        if (len === 0) {
            descBadge.className += 'bg-light text-dark';
        } else if (len >= 140 && len <= 160) {
            descBadge.className += 'bg-success text-white';
        } else if (len > 160) {
            descBadge.className += 'bg-danger text-white';
        } else {
            descBadge.className += 'bg-warning text-dark';
        }
    }
}
document.addEventListener('DOMContentLoaded', updateSeoCounters);

// Logo canlı önizleme
(function() {
    const fileInput = document.getElementById('logo_file_input');
    const urlInput  = document.getElementById('logo_url_input');
    const previewImg = document.getElementById('logo-preview-img');
    const placeholder = document.getElementById('logo-preview-placeholder');

    function showPreview(src) {
        if (!previewImg) return;
        previewImg.src = src;
        previewImg.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { showPreview(e.target.result); };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (urlInput) {
        let timer;
        urlInput.addEventListener('input', function() {
            clearTimeout(timer);
            const val = this.value.trim();
            if (val) {
                timer = setTimeout(function() { showPreview(val); }, 600);
            }
        });
    }
})();

// Sekme (Tab) hatırlama özelliği
document.addEventListener('DOMContentLoaded', function() {
    const settingsTabs = document.querySelectorAll('#settingsTab button[data-bs-toggle="tab"]');
    if (settingsTabs.length > 0) {
        settingsTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', event => {
                localStorage.setItem('activeSettingsTab', event.target.id);
            });
        });
        
        const activeTabId = localStorage.getItem('activeSettingsTab');
        if (activeTabId) {
            const activeTab = document.getElementById(activeTabId);
            if (activeTab) {
                const bootstrapTab = new bootstrap.Tab(activeTab);
                bootstrapTab.show();
            }
        }
    }
});
</script>
</body>
</html>
