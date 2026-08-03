<?php
/**
 * Dijital Rehber - Şifremi Unuttum (OTP Tabanlı Güvenli Sıfırlama)
 * 3 Adımlı Süreç:
 * Adım 1: E-posta Adresi Girişi (OTP Kod İsteği)
 * Adım 2: 6 Haneli OTP Kodunun Doğrulanması (5 Dakika Sayaçlı)
 * Adım 3: Yeni Şifre Belirlenmesi (Merkezi Şifre Standardı Kontrolü)
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';
require_once __DIR__ . '/services/PasswordResetService.php';

Session::start();

$service = new PasswordResetService();
$step    = intval($_GET['step'] ?? 1);
$error   = '';
$success = '';

// Adım 1 Form Gönderimi (OTP Kod Talebi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $result = $service->requestOtp($email);
        
        if ($result['success']) {
            $_SESSION['reset_email']   = $email;
            $_SESSION['dev_otp']       = $result['dev_otp'] ?? null;
            $_SESSION['reset_expires'] = time() + 300; // 5 Dakika
            header('Location: sifremi-unuttum.php?step=2');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Adım 2 Form Gönderimi (OTP Kodunu Doğrula)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $email   = $_SESSION['reset_email'] ?? '';
        $otpCode = trim($_POST['otp_code'] ?? '');
        
        if (empty($email)) {
            header('Location: sifremi-unuttum.php?step=1');
            exit;
        }

        $result = $service->verifyOtp($email, $otpCode);
        if ($result['success']) {
            $_SESSION['reset_verified_otp'] = $otpCode;
            $_SESSION['reset_user_type']    = $result['user_type'];
            header('Location: sifremi-unuttum.php?step=3');
            exit;
        } else {
            $error = $result['message'];
            $step = 2; // Adım 2'de kal
        }
    }
}

// Adım 3 Form Gönderimi (Yeni Şifre Belirle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyin.';
    } else {
        $email       = $_SESSION['reset_email'] ?? '';
        $verifiedOtp = $_SESSION['reset_verified_otp'] ?? '';
        $newPass     = trim($_POST['new_password'] ?? '');
        $newPassConfirm = trim($_POST['new_password_confirm'] ?? '');

        if (empty($email) || empty($verifiedOtp)) {
            header('Location: sifremi-unuttum.php?step=1');
            exit;
        }

        $result = $service->resetPassword($email, $verifiedOtp, $newPass, $newPassConfirm);
        if ($result['success']) {
            // Başarılı! Session sıfırla ve başarı ekranı göster
            $userType = $_SESSION['reset_user_type'] ?? 'user';
            unset($_SESSION['reset_email'], $_SESSION['dev_otp'], $_SESSION['reset_expires'], $_SESSION['reset_verified_otp'], $_SESSION['reset_user_type']);
            
            $success = $result['message'];
            $step = 4; // Başarı adımı
        } else {
            $error = $result['message'];
            $step = 3;
        }
    }
}

// Adım kontrolü (Eğer 2 veya 3. adımdaysa oturumda email olmalı)
if (($step === 2 || $step === 3) && empty($_SESSION['reset_email'])) {
    header('Location: sifremi-unuttum.php?step=1');
    exit;
}

$pageTitle = 'Şifremi Unuttum - Güvenli Şifre Yenileme';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <!-- Başlık -->
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: #FEF2F2; color: #D62828;">
                        <i class="fa-solid fa-shield-halved fs-3"></i>
                    </div>
                    <h4 class="fw-bold text-navy">Şifremi Unuttum</h4>
                    <p class="text-muted small">
                        <?php if ($step === 1): ?>
                            Hesabınıza bağlı e-posta adresinizi girin.
                        <?php elseif ($step === 2): ?>
                            E-postanıza gönderilen 6 haneli doğrulama kodunu girin.
                        <?php elseif ($step === 3): ?>
                            Hesabınız için yeni ve güvenli bir şifre belirleyin.
                        <?php elseif ($step === 4): ?>
                            Şifreniz başarıyla yenilendi!
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Adım İlerleme Çubuğu -->
                <?php if ($step <= 3): ?>
                <div class="px-4 mt-2">
                    <div class="progress" style="height: 6px; border-radius: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($step / 3) * 100 ?>%;"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1" style="font-size: 11px;">
                        <span class="<?= $step >= 1 ? 'fw-bold text-primary' : '' ?>">1. E-posta</span>
                        <span class="<?= $step >= 2 ? 'fw-bold text-primary' : '' ?>">2. Doğrulama (OTP)</span>
                        <span class="<?= $step >= 3 ? 'fw-bold text-primary' : '' ?>">3. Yeni Şifre</span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card-body p-4 p-md-5 pt-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 small py-3 mb-4 d-flex align-items-center">
                            <i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <!-- ── ADIM 1: E-POSTA ADRESİ GİRİŞ FORMU ──────────────────────────────── -->
                        <form method="POST" action="">
                            <?= CSRFMiddleware::field() ?>
                            <input type="hidden" name="action" value="request_otp">

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-muted">E-posta veya İşletme Kullanıcı Adı</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                    <input type="text" name="email" class="form-control border-start-0 ps-2" required autofocus placeholder="ornek@email.com veya isletme_kullanici" value="<?= SecurityHelper::escape($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="form-text small text-muted">Sistemimizde kayıtlı olan bireysel e-posta adresiniz veya işletme kullanıcı adınız.</div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                    Doğrulama Kodu Gönder <i class="fa-solid fa-paper-plane ms-2"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="giris.php" class="small text-muted text-decoration-none">
                                <i class="fa-solid fa-arrow-left me-1"></i> Giriş Sayfasına Dön
                            </a>
                        </div>

                    <?php elseif ($step === 2): ?>
                        <!-- ── ADIM 2: OTP DOĞRULAMA & GELİŞTİRİCİ TEST KUTUSU ─────────────── -->
                        <?php if (!empty($_SESSION['dev_otp'])): ?>
                            <div class="alert alert-warning border border-warning small p-3 mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <strong class="text-dark"><i class="fa-solid fa-code me-1"></i> Geliştirici Test Bilgisi (Localhost)</strong>
                                    <span class="badge bg-warning text-dark">DEV MODE</span>
                                </div>
                                <div class="text-dark mb-2">E-posta sunucusu localde aktif olmadığı için üretilen OTP test kodu:</div>
                                <div class="d-flex align-items-center justify-content-between bg-white p-2 rounded border">
                                    <span class="fs-5 fw-bold text-navy font-monospace" id="devOtpCode"><?= htmlspecialchars($_SESSION['dev_otp']) ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="copyDevOtp()">
                                        <i class="fa-regular fa-copy me-1"></i> Kopyala
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Kalan Süre Sayacı (5 Dakika / 300 Saniye) -->
                        <?php
                        $remainingSeconds = max(0, ($_SESSION['reset_expires'] ?? time()) - time());
                        ?>
                        <div class="text-center mb-4">
                            <span class="badge px-3 py-2 fs-6" style="background-color: #FEF2F2; color: #D62828; border: 1px solid #FECACA;">
                                <i class="fa-regular fa-clock me-1"></i> Kalan Süre: 
                                <span id="otpTimer" class="fw-bold font-monospace">--:--</span>
                            </span>
                        </div>

                        <form method="POST" action="">
                            <?= CSRFMiddleware::field() ?>
                            <input type="hidden" name="action" value="verify_otp">

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-muted">6 Haneli Doğrulama Kodu (OTP)</label>
                                <input type="text" name="otp_code" class="form-control text-center fs-4 fw-bold font-monospace" 
                                       required autofocus maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="••••••" style="letter-spacing: 0.5rem;" 
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                <div class="form-text small text-muted text-center mt-2">
                                    <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong> adresine gönderilen 6 haneli kodu yazın.
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                    Kodu Doğrula <i class="fa-solid fa-check ms-2"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="sifremi-unuttum.php?step=1" class="small text-muted text-decoration-none">
                                <i class="fa-solid fa-rotate me-1"></i> Farklı Bir E-posta Dene
                            </a>
                        </div>

                    <?php elseif ($step === 3): ?>
                        <!-- ── ADIM 3: YENİ ŞİFRE BELİRLEME (MERKEZİ STANDARD) ─────────────── -->
                        <div class="alert alert-info border-0 small py-3 mb-4">
                            <div class="fw-bold mb-1"><i class="fa-solid fa-lock me-1"></i> Güvenli Şifre Standardı:</div>
                            <ul class="mb-0 ps-3">
                                <li>En az <strong>8 karakter</strong> olmalıdır.</li>
                                <li>En az bir <strong>Büyük Harf (A-Z)</strong> içermelidir.</li>
                                <li>En az bir <strong>Küçük Harf (a-z)</strong> içermelidir.</li>
                                <li>En az bir <strong>Rakam (0-9)</strong> içermelidir.</li>
                            </ul>
                        </div>

                        <form method="POST" action="">
                            <?= CSRFMiddleware::field() ?>
                            <input type="hidden" name="action" value="reset_password">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-muted">Yeni Şifreniz</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                                    <input type="password" name="new_password" class="form-control border-start-0 ps-2" required autofocus placeholder="En az 8 karakter (Büyük/Küçük harf, Rakam)">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-muted">Yeni Şifreniz (Tekrar)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                                    <input type="password" name="new_password_confirm" class="form-control border-start-0 ps-2" required placeholder="Şifrenizi tekrar yazın">
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success py-2 fw-semibold">
                                    Şifremi Kaydet ve Güncelle <i class="fa-solid fa-shield-check ms-2"></i>
                                </button>
                            </div>
                        </form>

                    <?php elseif ($step === 4): ?>
                        <!-- ── ADIM 4: BAŞARI EKRANI ─────────────────────────────────────────── -->
                        <div class="text-center py-3">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px; background: #f0fdf4; color: #16a34a;">
                                <i class="fa-solid fa-circle-check fs-2"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-2">Tebrikler!</h5>
                            <p class="text-muted small mb-4"><?= htmlspecialchars($success) ?></p>

                            <div class="d-grid gap-2">
                                <a href="giris.php" class="btn btn-primary py-2 fw-semibold">
                                    Bireysel Giriş Yap <i class="fa-solid fa-right-to-bracket ms-1"></i>
                                </a>
                                <a href="isletme/login.php" class="btn btn-outline-secondary py-2 fw-semibold">
                                    İşletme Girişi Yap <i class="fa-solid fa-store ms-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($step === 2): ?>
<script>
// 5 Dakikalık OTP Geri Sayım Sayacı (JavaScript Timer)
let remainingSeconds = <?= intval($remainingSeconds) ?>;
const timerEl = document.getElementById('otpTimer');

function updateTimerDisplay() {
    if (remainingSeconds <= 0) {
        timerEl.textContent = "00:00 (Süre Doldu)";
        timerEl.classList.remove('text-navy');
        timerEl.classList.add('text-danger');
        return;
    }

    const m = Math.floor(remainingSeconds / 60);
    const s = remainingSeconds % 60;
    timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    remainingSeconds--;
}

updateTimerDisplay();
setInterval(updateTimerDisplay, 1000);

// Dev Mode OTP kodunu kopyalama fonksiyonu
function copyDevOtp() {
    const code = document.getElementById('devOtpCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        alert("OTP Kodu Kopyalandı: " + code);
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
