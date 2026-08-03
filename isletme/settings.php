<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getInstance()->getPDO();
$bizUserId = $_SESSION['biz_user_id'] ?? 0;

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_pw'])) {
    validateCSRF();
    
    $pwCurrent = $_POST['pw_current'] ?? '';
    $pwNew     = trim($_POST['pw_new'] ?? '');
    $pwConfirm = trim($_POST['pw_confirm'] ?? '');

    if ($pwCurrent && $pwNew && $pwConfirm) {
        if ($pwNew !== $pwConfirm) {
            $errorMsg = 'Yeni şifreler eşleşmiyor.';
        } elseif (!SecurityHelper::validatePasswordStrength($pwNew)) {
            $errorMsg = SecurityHelper::getPasswordStrengthMessage();
        } else {
            // Verify current
            $stmt = $db->prepare("SELECT password FROM business_users WHERE id = ?");
            $stmt->execute([$bizUserId]);
            $user = $stmt->fetch();
            
            if ($user && SecurityHelper::verifyPassword($pwCurrent, $user['password'])) {
                $hashed = SecurityHelper::hashPassword($pwNew);
                $db->prepare("UPDATE business_users SET password = ? WHERE id = ?")->execute([$hashed, $bizUserId]);
                $successMsg = 'Şifreniz başarıyla güncellendi.';
            } else {
                $errorMsg = 'Mevcut şifreniz hatalı.';
            }
        }
    } else {
        $errorMsg = 'Lütfen tüm alanları doldurun.';
    }
}

$pageTitle = 'Güvenlik Ayarları';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-shield-halved me-2 text-primary"></i> Güvenlik Ayarları</h3>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h6 class="fw-bold text-navy"><i class="fa-solid fa-key me-2 text-primary"></i> Şifre Değiştir</h6>
            </div>
            <div class="card-body">
                <?php if ($successMsg): ?>
                    <div class="alert alert-success border-0 small"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger border-0 small"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?></div>
                <?php endif; ?>
                
                <form action="settings.php" method="POST">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="do_pw" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mevcut Şifre</label>
                        <input type="password" name="pw_current" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Yeni Şifre</label>
                        <input type="password" name="pw_new" class="form-control" required minlength="8">
                        <div class="form-text">En az 8 karakter olmalı; en az 1 büyük harf, 1 küçük harf ve 1 rakam içermelidir.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Yeni Şifre (Tekrar)</label>
                        <input type="password" name="pw_confirm" class="form-control" required minlength="8">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-save me-2"></i> Şifreyi Güncelle</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm bg-light mt-4 mt-lg-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-navy mb-3"><i class="fa-solid fa-circle-info text-info me-2"></i> Şifre Güvenliği</h6>
                <p class="small text-muted mb-2">Hesabınızın güvenliği için şifrenizi belirlerken aşağıdaki kurallara dikkat edin:</p>
                <ul class="small text-muted mb-0">
                    <li class="mb-1">En az 8 karakter uzunluğunda olmalı, en az 1 büyük harf, 1 küçük harf ve 1 rakam içermelidir.</li>
                    <li class="mb-1">Basit ve tahmin edilebilir kelimeler (123456, admin vb.) kullanmaktan kaçının.</li>
                    <li class="mb-1">Belirli aralıklarla şifrenizi güncelleyin.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>