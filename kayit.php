<?php
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/models/User.php';

Session::start();

$redirect = SecurityHelper::normalizeLocalRedirect($_GET['redirect'] ?? 'profil.php', 'profil.php');
 
if (Session::get('user_logged_in') === true) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $passwordConfirm = trim($_POST['password_confirm'] ?? '');
        
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Lütfen tüm zorunlu alanları doldurun.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Lütfen geçerli bir e-posta adresi girin.';
        } elseif (!SecurityHelper::validatePasswordStrength($password)) {
            $error = SecurityHelper::getPasswordStrengthMessage();
        } elseif ($password !== $passwordConfirm) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            RateLimitMiddleware::checkRegistration();
            
            $userModel = new User();
            
            // Check if email exists
            if ($userModel->whereFirst('email', $email)) {
                $error = 'Bu e-posta adresi ile zaten bir hesap mevcut.';
            } else {
                try {
                    $userId = $userModel->register($name, $email, $password);
                    if ($userId) {
                        Logger::info('New user registered', ['user_id' => $userId, 'email' => $email]);
                        
                        Session::set('user_logged_in', true);
                        Session::set('user_id', $userId);
                        Session::set('user_name', $name);
                        Session::set('user_email', $email);
                        
                        $successRedirect = $redirect;
                        $successRedirect .= (strpos($successRedirect, '?') === false ? '?' : '&') . 'registered=1';
                        header('Location: ' . $successRedirect);
                        exit;
                    } else {
                        $error = 'Kayıt sırasında bir hata oluştu.';
                    }
                } catch (Exception $e) {
                    $error = 'Kayıt sırasında bir hata oluştu. ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Kayıt Ol';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <h3 class="fw-bold text-navy"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Kullanıcı Kaydı</h3>
                    <p class="text-muted small">Topluluğa katılın, mekanları puanlayın ve favorilerinizi listeleyin.</p>
                </div>
                <div class="card-body p-4 p-md-5 pt-3">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 small py-2 mb-4">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <?= CSRFMiddleware::field() ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Ad Soyad</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-user text-muted"></i></span>
                                <input type="text" name="name" class="form-control border-start-0 ps-2" required value="<?= SecurityHelper::escape($_POST['name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">E-posta Adresi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-2" required value="<?= SecurityHelper::escape($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Şifre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-2" required placeholder="En az 8 karakter (Büyük/Küçük harf, Rakam)">
                            </div>
                            <div class="form-text small text-muted">En az 8 karakter; büyük harf, küçük harf ve rakam içermelidir.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-muted">Şifre Tekrar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password_confirm" class="form-control border-start-0 ps-2" required>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                Kayıt Ol
                            </button>
                        </div>
                        
                        <div class="text-center text-muted small">
                            Zaten hesabınız var mı? <a href="giris.php<?= $redirect !== 'profil.php' ? '?redirect=' . urlencode($redirect) : '' ?>" class="text-primary text-decoration-none fw-semibold">Giriş Yapın</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
