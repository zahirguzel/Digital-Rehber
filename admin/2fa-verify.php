<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once 'includes/totp.php';

// Must have a pending 2FA session, not a full login
if (!isset($_SESSION['pending_2fa_id'])) {
    header('Location: login.php');
    exit;
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['2fa_attempts'])) {
    $_SESSION['2fa_attempts'] = 0;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    // Fetch the admin's secret
    $stmt = $db->query("SELECT * FROM admins WHERE id = ?", [$_SESSION['pending_2fa_id']]);
    $admin = $stmt->fetch();

    if ($admin && verifyCode($admin['two_factor_secret'], $code)) {
        // Clear 2FA state and open full session
        unset($_SESSION['pending_2fa_id'], $_SESSION['2fa_attempts']);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];

        // Update last_login
        try {
            $db->getPDO()->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
                ->execute([$admin['id']]);
        } catch (Exception $e) {}

        // Log login
        require_once 'includes/logger.php';
        logAction('login', 'auth', $admin['username'], $admin['id']);

        header('Location: index.php');
        exit;
    } else {
        $_SESSION['2fa_attempts']++;
        if ($_SESSION['2fa_attempts'] >= 3) {
            unset($_SESSION['pending_2fa_id'], $_SESSION['2fa_attempts']);
            header('Location: login.php?error=2fa_failed');
            exit;
        }
        $remaining = 3 - $_SESSION['2fa_attempts'];
        $error = 'Geçersiz kod. ' . $remaining . ' deneme hakkınız kaldı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İki Faktörlü Doğrulama - Web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #E0533C; --navy: #1d3557; --radius: 4px; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f4f6f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-radius: var(--radius);
            box-shadow: 0 10px 25px rgba(0,0,0,.05);
            overflow: hidden;
        }
        .login-header {
            background-color: var(--navy);
            color: #fff;
            padding: 30px;
            text-align: center;
            border-bottom: 4px solid var(--primary);
        }
        .login-header h2 { font-size: 22px; font-weight: 700; margin-bottom: 5px; letter-spacing: -.5px; }
        .login-header h2 span { color: var(--primary); }
        .login-body { padding: 30px; }
        .form-control {
            border-radius: var(--radius) !important;
            border: 1px solid #CBD5E0;
            padding: 12px 14px;
            font-size: 22px;
            letter-spacing: 6px;
            text-align: center;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(224,83,60,.12); }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            border-radius: var(--radius) !important;
            padding: 12px;
            font-weight: 600;
            font-size: 15px;
        }
        .btn-primary:hover { background-color: #c84630; border-color: #c84630; }
        .back-link { text-align: center; margin-top: 20px; font-size: 13px; }
        .back-link a { color: var(--navy); text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        .otp-hint { font-size: 13px; color: #718096; margin-top: 8px; text-align: center; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h2><i class="fa-solid fa-shield-halved me-2"></i>İki Faktörlü<br><span>Doğrulama</span></h2>
        <p class="mb-0 text-white-50 small">Google Authenticator kodunuzu girin</p>
    </div>

    <div class="login-body">
        <?php
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

if (!empty($error)): ?>
            <div class="alert alert-danger border-0 small py-2 mb-3" style="border-radius:var(--radius);">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

endif; ?>

        <form action="" method="POST" autocomplete="off">
    <?= CSRFMiddleware::field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold text-muted small d-block text-center mb-2">6 Haneli Kod</label>
                <input
                    type="text"
                    name="code"
                    class="form-control"
                    maxlength="6"
                    inputmode="numeric"
                    pattern="\d{6}"
                    placeholder="000000"
                    autofocus
                    required>
                <p class="otp-hint"><i class="fa-brands fa-google me-1"></i> Google Authenticator uygulamanızı açın</p>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check me-2"></i> Doğrula
                </button>
            </div>
        </form>

        <div class="back-link">
            <a href="login.php"><i class="fa-solid fa-arrow-left me-1"></i> Girişe Dön</a>
        </div>
    </div>
</div>

</body>
</html>
