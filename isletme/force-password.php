<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';

// If they are not forced to change password, redirect to index
if (!Session::get('force_password')) {
    header("Location: index.php");
    exit;
}

$db = Database::getInstance()->getPDO();
$bizUserId = Session::get('biz_user_id');

$errorMsg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $pwNew     = trim($_POST['pw_new'] ?? '');
    $pwConfirm = trim($_POST['pw_confirm'] ?? '');

    if (empty($pwNew) || empty($pwConfirm)) {
        $errorMsg = 'Lütfen tüm alanları doldurun.';
    } elseif ($pwNew !== $pwConfirm) {
        $errorMsg = 'Şifreler eşleşmiyor.';
    } elseif (strlen($pwNew) < 6) {
        $errorMsg = 'Şifreniz en az 6 karakter olmalıdır.';
    } else {
        try {
            $hashed = SecurityHelper::hashPassword($pwNew);
            $stmt = $db->prepare("UPDATE business_users SET password = ?, force_password_change = 0 WHERE id = ?");
            $stmt->execute([$hashed, $bizUserId]);
            
            Session::set('force_password', false);
            $success = true;
        } catch (Exception $e) {
            $errorMsg = "Bir hata oluştu. Lütfen tekrar deneyin.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Yenileme — İşletme Paneli</title>
    <?= CSRFMiddleware::meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #F8FAFC; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); }
        .btn-primary { background: #E0533C; border-color: #E0533C; }
        .btn-primary:hover { background: #c84630; border-color: #c84630; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            <?php if ($success): ?>
                <div class="card overflow-hidden">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4 text-success" style="font-size: 60px;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Harika!</h4>
                        <p class="text-muted mb-4">Şifreniz başarıyla güncellendi. Artık yönetim paneline güvenle erişebilirsiniz.</p>
                        <a href="index.php" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Panele Git <i class="fa-solid fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card overflow-hidden">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 70px; height: 70px;">
                                <i class="fa-solid fa-shield-halved fs-2"></i>
                            </div>
                            <h4 class="fw-bold text-navy">Güvenlik Uyarısı</h4>
                            <p class="text-muted small">Hesabınıza sistem tarafından üretilen geçici şifreyle giriş yaptınız. Lütfen devam etmeden önce kalıcı şifrenizi belirleyin.</p>
                        </div>

                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger py-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($errorMsg) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?= CSRFMiddleware::field() ?>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Yeni Şifreniz</label>
                                <input type="password" name="pw_new" class="form-control" required placeholder="En az 6 karakter">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Yeni Şifreniz (Tekrar)</label>
                                <input type="password" name="pw_confirm" class="form-control" required placeholder="En az 6 karakter">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                                Şifremi Kaydet ve Devam Et
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="logout.php" class="text-muted text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Vazgeç ve Çıkış Yap</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>