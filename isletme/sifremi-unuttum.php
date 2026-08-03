<?php
/**
 * İşletme Paneli - Güvenli OTP Şifre Sıfırlama (E-posta Onaylı)
 */
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../middleware/CSRFMiddleware.php';
require_once __DIR__ . '/../services/PasswordResetService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$service = new PasswordResetService();
$step    = intval($_GET['step'] ?? 1);
$error   = '';
$success = '';

// Adım 1: OTP Kod Talebi (E-posta veya Kullanıcı Adı)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $result = $service->requestOtp($email);
        
        if ($result['success']) {
            $_SESSION['biz_reset_email']   = $email;
            $_SESSION['biz_dev_otp']       = $result['dev_otp'] ?? null;
            $_SESSION['biz_reset_expires'] = time() + 300; // 5 Dakika
            header('Location: sifremi-unuttum.php?step=2');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Adım 2: OTP Kodunu Doğrula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $otpCode = trim($_POST['otp_code'] ?? '');
        $email   = $_SESSION['biz_reset_email'] ?? '';
        
        $result = $service->verifyOtp($email, $otpCode);
        if ($result['success']) {
            $_SESSION['biz_reset_verified_otp'] = $otpCode;
            header('Location: sifremi-unuttum.php?step=3');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Adım 3: Yeni Şifreyi Belirle (Min. 8 karakter, A-Z, a-z, 0-9)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $newPw        = $_POST['new_password'] ?? '';
        $newPwConfirm = $_POST['new_password_confirm'] ?? '';
        $email        = $_SESSION['biz_reset_email'] ?? '';
        $otpCode      = $_SESSION['biz_reset_verified_otp'] ?? '';
        
        $result = $service->resetPassword($email, $otpCode, $newPw, $newPwConfirm);
        if ($result['success']) {
            unset($_SESSION['biz_reset_email'], $_SESSION['biz_dev_otp'], $_SESSION['biz_reset_expires'], $_SESSION['biz_reset_verified_otp']);
            $success = $result['message'];
            $step = 4; // Başarılı tamamlama ekranı
        } else {
            $error = $result['message'];
        }
    }
}

// Adım 2 ve 3 için oturum kontrolü
if (in_array($step, [2, 3]) && empty($_SESSION['biz_reset_email'])) {
    header('Location: sifremi-unuttum.php?step=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşletme Şifre Sıfırlama | Dijital Rehber</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #D62828;
            --primary-dark: #B71C1C;
            --primary-light: #FCE8E8;
            --text-dark: #1A1A1A;
            --text-muted: #6C757D;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1A1A1A 0%, #2D3748 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            position: relative;
        }

        .reset-card::top {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary);
        }

        .reset-header {
            padding: 40px 40px 20px;
            text-align: center;
        }

        .icon-wrapper {
            width: 70px;
            height: 70px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 16px var(--primary-light);
        }
        
        .reset-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .reset-header p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 0;
        }

        .reset-body {
            padding: 20px 40px 40px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1.5px solid #E2E8F0;
            font-size: 15px;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
            margin-top: 20px;
        }
        .back-link:hover {
            color: var(--primary);
        }

        .badge-timer {
            background-color: var(--primary);
            color: #ffffff;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(214, 40, 40, 0.3);
        }
    </style>
</head>
<body>
<div class="reset-card">
    <div class="reset-header">
        <div class="icon-wrapper">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h2>İşletme Şifre Yenileme</h2>
        <p>İşletmenizin e-posta adresine gönderilecek kod ile şifrenizi sıfırlayın</p>
    </div>

    <div class="reset-body">
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- ADIM 1: E-posta / Kullanıcı Adı Gir -->
            <form method="POST" action="sifremi-unuttum.php?step=1">
                <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="request_otp">
                
                <div class="mb-4">
                    <label class="form-label">İşletme E-posta Adresi veya Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="text" name="email" class="form-control border-start-0" placeholder="Örn: info@isletmem.com veya isletme_adi" required autofocus>
                    </div>
                    <div class="form-text small mt-2 text-muted">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        İşletme hesabınızla ilişkili e-posta adresine 6 haneli doğrulama kodu gönderilecektir.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Doğrulama Kodu Gönder <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <!-- ADIM 2: 6 Haneli Kodu Doğrula -->
            <?php if (!empty($_SESSION['biz_dev_otp'])): ?>
                <div class="alert alert-warning border-0 small mb-4">
                    <i class="fa-solid fa-bug me-1"></i> <strong>Geliştirici Modu:</strong> Doğrulama Kodunuz: <strong><?= htmlspecialchars($_SESSION['biz_dev_otp']) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="sifremi-unuttum.php?step=2">
                <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="verify_otp">
                
                <div class="mb-4 text-center">
                    <label class="form-label d-block mb-3">6 Haneli Doğrulama Kodunu Girin</label>
                    <input type="text" name="otp_code" class="form-control text-center fw-bold" style="font-size: 24px; letter-spacing: 6px;" maxlength="6" placeholder="••••••" required autofocus autocomplete="off">
                    
                    <div class="mt-3">
                        <span id="timer" class="badge badge-timer">Kalan Süre: 05:00</span>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Kodu Doğrula <i class="fa-solid fa-check ms-2"></i>
                    </button>
                </div>
            </form>

            <script>
                // 5 dakikalık geri sayım
                let seconds = <?= max(0, ($_SESSION['biz_reset_expires'] ?? time()) - time()) ?>;
                const timerEl = document.getElementById('timer');
                
                const interval = setInterval(() => {
                    if (seconds <= 0) {
                        clearInterval(interval);
                        timerEl.textContent = 'Süre Doldu';
                        timerEl.style.backgroundColor = '#6C757D';
                    } else {
                        seconds--;
                        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
                        const s = (seconds % 60).toString().padStart(2, '0');
                        timerEl.textContent = 'Kalan Süre: ' + m + ':' + s;
                    }
                }, 1000);
            </script>

        <?php elseif ($step === 3): ?>
            <!-- ADIM 3: Yeni Şifre Belirle -->
            <form method="POST" action="sifremi-unuttum.php?step=3">
                <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="reset_password">
                
                <div class="mb-3">
                    <label class="form-label">Yeni Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="new_password" class="form-control border-start-0" placeholder="Min. 8 karakter (A-Z, a-z, 0-9)" required minlength="8" autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Yeni Şifre (Tekrar)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="new_password_confirm" class="form-control border-start-0" placeholder="Yeni şifrenizi tekrar yazın" required minlength="8">
                    </div>
                    <div class="form-text small mt-2 text-muted">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Şifreniz en az 8 karakter uzunluğunda olmalı; büyük harf, küçük harf ve rakam içermelidir.
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Şifreyi Yenile <i class="fa-solid fa-check-double ms-2"></i>
                    </button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <!-- ADIM 4: Başarılı -->
            <div class="text-center py-4">
                <div class="mb-4 text-success" style="font-size: 60px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h4 class="fw-bold mb-3">Şifreniz Yenilendi!</h4>
                <p class="text-muted mb-4"><?= htmlspecialchars($success) ?></p>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-primary">
                        İşletme Girişine Git <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <a href="login.php" class="back-link">
                <i class="fa-solid fa-arrow-left me-2"></i> İşletme Girişine Geri Dön
            </a>
        </div>
    </div>
</div>
</body>
</html>
