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
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            $error = 'Lütfen e-posta ve şifrenizi girin.';
        } else {
            if (!RateLimitMiddleware::checkLogin($email)) {
                $error = 'Çok fazla başarısız giriş denemesi. Lütfen 15 dakika bekleyip tekrar deneyin.';
            } else {
                $userModel = new User();
                $user = $userModel->login($email, $password);
                
                if ($user) {
                    RateLimitMiddleware::resetLogin($email);
                    
                    Session::set('user_logged_in', true);
                    Session::set('user_id', $user['id']);
                    Session::set('user_name', $user['name']);
                    Session::set('user_email', $user['email']);
                    
                    Logger::info('User logged in', ['user_id' => $user['id']]);
                    
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'E-posta adresi veya şifre hatalı.';
                }
            }
        }
    }
}

$pageTitle = 'Giriş Yap';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <h3 class="fw-bold text-navy"><i class="fa-solid fa-right-to-bracket me-2 text-primary"></i>Kullanıcı Girişi</h3>
                    <p class="text-muted small">İşletmeleri favoriye almak ve yorum yapmak için giriş yapın.</p>
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
                            <label class="form-label fw-semibold small text-muted">E-posta Adresi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-2" required autofocus value="<?= SecurityHelper::escape($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-muted">Şifre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-2" required>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                Giriş Yap
                            </button>
                        </div>
                        
                        <div class="text-center text-muted small">
                            Hesabınız yok mu? <a href="kayit.php<?= $redirect !== 'profil.php' ? '?redirect=' . urlencode($redirect) : '' ?>" class="text-primary text-decoration-none fw-semibold">Hemen Kayıt Olun</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
