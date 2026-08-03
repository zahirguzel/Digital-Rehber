<?php
/**
 * Dijital Rehber - Bireysel Kullanıcı Güvenli Şifre Değiştirme & Zorunlu Yenileme Ekranı
 * Yönetici tarafından şifresi sıfırlanan veya şifresini değiştirmek isteyen
 * normal kullanıcılar (users) için merkezi şifre standardına uygun yenileme sayfası.
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';
require_once __DIR__ . '/models/User.php';

Session::start();

if (!Session::get('user_logged_in')) {
    header('Location: giris.php');
    exit;
}

$userId   = (int) Session::get('user_id');
$db       = Database::getInstance();
$userRow  = $db->fetchOne("SELECT id, email, name, password, force_password_change FROM users WHERE id = ?", [$userId]);

if (!$userRow) {
    header('Location: cikis.php');
    exit;
}

$isForced = !empty($userRow['force_password_change']) || isset($_GET['force']);
$error    = '';
$success  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $currentPass  = trim($_POST['current_password'] ?? '');
        $newPass      = trim($_POST['new_password'] ?? '');
        $confirmPass  = trim($_POST['new_password_confirm'] ?? '');

        // 1. Zorunlu olmayan durumlarda mevcut şifre kontrolü
        if (!$isForced) {
            if (empty($currentPass)) {
                $error = 'Lütfen mevcut şifrenizi girin.';
            } elseif (!SecurityHelper::verifyPassword($currentPass, $userRow['password'])) {
                $error = 'Mevcut şifreniz hatalı.';
            }
        }

        // 2. Yeni şifre kuralları
        if (empty($error)) {
            if (empty($newPass) || empty($confirmPass)) {
                $error = 'Lütfen yeni şifrenizi ve tekrarını girin.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'Yeni şifreler birbiriyle eşleşmiyor.';
            } elseif (!SecurityHelper::validatePasswordStrength($newPass)) {
                $error = SecurityHelper::getPasswordStrengthMessage();
            } else {
                try {
                    $hashed = SecurityHelper::hashPassword($newPass);
                    $db->query("UPDATE users SET password = ?, force_password_change = 0, updated_at = NOW() WHERE id = ?", [$hashed, $userId]);
                    
                    Session::set('user_force_password', false);

                    header('Location: profil.php?pw_success=1');
                    exit;
                } catch (Exception $e) {
                    $error = 'Şifre güncellenirken bir hata oluştu: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = $isForced ? 'Zorunlu Şifre Yenileme' : 'Güvenli Şifre Değiştir';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: #FEF2F2; color: #D62828;">
                        <i class="fa-solid fa-key fs-3"></i>
                    </div>
                    <h4 class="fw-bold text-navy">
                        <?= $isForced ? 'Yeni Şifrenizi Belirleyin' : 'Güvenli Şifre Değiştir' ?>
                    </h4>
                    <p class="text-muted small">
                        <?php if ($isForced): ?>
                            Yönetici tarafından şifreniz sıfırlanmıştır. Güvenliğiniz için lütfen yeni bir şifre oluşturun.
                        <?php else: ?>
                            Hesap güvenliğiniz için şifrenizi güçlü standartlara uygun olarak güncelleyin.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="card-body p-4 p-md-5 pt-3">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 small py-3 mb-4 d-flex align-items-center">
                            <i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isForced): ?>
                        <div class="alert alert-warning border border-warning small p-3 mb-4">
                            <i class="fa-solid fa-shield-halved me-1 text-dark"></i>
                            <strong>Güvenlik Uyarısı:</strong> Mevcut şifreniz geçici olarak sıfırlandığından yeni bir şifre belirleyene kadar işlem yapamazsınız.
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info border-0 small py-3 mb-4">
                        <div class="fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> Güvenli Şifre Standardı:</div>
                        <ul class="mb-0 ps-3">
                            <li>En az <strong>8 karakter</strong> uzunluğunda olmalıdır.</li>
                            <li>En az bir <strong>Büyük Harf (A-Z)</strong> içermelidir.</li>
                            <li>En az bir <strong>Küçük Harf (a-z)</strong> içermelidir.</li>
                            <li>En az bir <strong>Rakam (0-9)</strong> içermelidir.</li>
                        </ul>
                    </div>

                    <form method="POST" action="">
                        <?= CSRFMiddleware::field() ?>

                        <?php if (!$isForced): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-muted">Mevcut Şifreniz</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock-open text-muted"></i></span>
                                    <input type="password" name="current_password" class="form-control border-start-0 ps-2" required placeholder="Şu anki şifrenizi girin">
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Yeni Şifreniz</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="new_password" class="form-control border-start-0 ps-2" required autofocus placeholder="En az 8 karakter (Büyük/Küçük harf, Rakam)">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-muted">Yeni Şifreniz (Tekrar)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="new_password_confirm" class="form-control border-start-0 ps-2" required placeholder="Yeni şifrenizi tekrar yazın">
                            </div>
                        </div>

                        <div class="d-grid mb-2">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <?= $isForced ? 'Yeni Şifremi Kaydet <i class="fa-solid fa-check ms-1"></i>' : 'Şifremi Güncelle <i class="fa-solid fa-shield-check ms-1"></i>' ?>
                            </button>
                        </div>
                    </form>

                    <?php if (!$isForced): ?>
                        <div class="text-center mt-3">
                            <a href="profil.php" class="small text-muted text-decoration-none">
                                <i class="fa-solid fa-arrow-left me-1"></i> Profilime Dön
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
