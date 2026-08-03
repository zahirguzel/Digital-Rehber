<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once 'includes/totp.php';
require_once 'includes/logger.php';

$adminId = $_SESSION['admin_id'];

// Fetch current admin record
$stmt = $db->query("SELECT * FROM admins WHERE id = ?", [$adminId]);
$admin = $stmt->fetch();

$success = '';
$error   = '';

/* ── POST handling ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Activate 2FA ── */
    if ($action === 'activate') {
        $secret = trim($_POST['secret'] ?? '');
        $code   = trim($_POST['code']   ?? '');

        if (verifyCode($secret, $code)) {
            $db->getPDO()->prepare("UPDATE admins SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?")
                ->execute([$secret, $adminId]);
            logAction('2fa_enabled', 'auth', $admin['username'], $adminId);
            $success = '2FA başarıyla aktifleştirildi!';
            // Reload admin
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
        } else {
            $error = 'Kod geçersiz. Lütfen Google Authenticator\'dan doğru kodu girin.';
        }
    }

    /* ── Disable 2FA ── */
    if ($action === 'disable') {
        $password = $_POST['password'] ?? '';

        if (password_verify($password, $admin['password'])) {
            $db->getPDO()->prepare("UPDATE admins SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE id = ?")
                ->execute([$adminId]);
            logAction('2fa_disabled', 'auth', $admin['username'], $adminId);
            $success = '2FA devre dışı bırakıldı.';
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
        } else {
            $error = 'Şifreniz hatalı.';
        }
    }
}

/* ── Generate a temp secret for the setup form ──────────── */
$tempSecret = '';
if (!$admin['two_factor_enabled']) {
    // Keep temp secret across POST so the QR stays stable
    if (!isset($_SESSION['2fa_temp_secret'])) {
        $_SESSION['2fa_temp_secret'] = generateSecret();
    }
    $tempSecret = $_SESSION['2fa_temp_secret'];
    // If activation succeeded, clear temp
    if ($admin['two_factor_enabled']) {
        unset($_SESSION['2fa_temp_secret']);
    }
}
if ($admin['two_factor_enabled']) {
    unset($_SESSION['2fa_temp_secret']);
}

// Fetch site name for QR issuer label
$_issuer = 'DigitalRehber';
try {
    $_row = $db->query("SELECT site_title FROM settings LIMIT 1")->fetch();
    if ($_row && !empty($_row['site_title'])) $_issuer = $_row['site_title'];
} catch (Exception $e) {}

$qrUrl = $tempSecret ? getQrUrl($admin['username'], $tempSecret, $_issuer) : '';

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            <!-- Page title -->
            <div class="d-flex align-items-center mb-4">
                <a href="admins.php" class="btn btn-sm btn-outline-secondary me-3">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h4 class="mb-0 fw-bold">İki Faktörlü Doğrulama</h4>
                    <small class="text-muted">Google Authenticator ile hesap güvenliği</small>
                </div>
            </div>

            <?php
if ($success): ?>
                <div class="alert alert-success border-0 mb-4">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php
endif; ?>
            <?php
if ($error): ?>
                <div class="alert alert-danger border-0 mb-4">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php
endif; ?>

            <?php
if ($admin['two_factor_enabled']): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <span style="width:44px;height:44px;background:#d4edda;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                            <i class="fa-solid fa-shield-halved" style="color:#198754;font-size:18px;"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">2FA Aktif</div>
                            <small class="text-muted">Hesabınız iki faktörlü doğrulama ile korunuyor.</small>
                        </div>
                    </div>

                    <hr>

                    <p class="text-muted small mb-3">2FA'yı devre dışı bırakmak için şifrenizi girin:</p>
                    <form method="POST" autocomplete="off">
    <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="action" value="disable">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Şifreniz" required>
                        </div>
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fa-solid fa-shield-xmark me-2"></i>2FA'yı Devre Dışı Bırak
                        </button>
                    </form>
                </div>
            </div>

            <?php
else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <span style="width:44px;height:44px;background:#fff3cd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                            <i class="fa-solid fa-shield-halved" style="color:#856404;font-size:18px;"></i>
                        </span>
                        <div>
                            <div class="fw-semibold">2FA Pasif</div>
                            <small class="text-muted">Hesabınızda iki faktörlü doğrulama etkin değil.</small>
                        </div>
                    </div>

                    <hr>

                    <h6 class="fw-semibold mb-3">2FA Kurulum Adımları</h6>
                    <ol class="small text-muted mb-4" style="line-height:1.8;">
                        <li>Telefonunuza <strong>Google Authenticator</strong> uygulamasını kurun.</li>
                        <li>Uygulamada <strong>"+"</strong> → <strong>"QR kodu tara"</strong> seçeneğine tıklayın.</li>
                        <li>Aşağıdaki QR kodu tarayın veya gizli anahtarı manuel olarak girin.</li>
                        <li>Uygulamada görünen <strong>6 haneli kodu</strong> aşağıya girin ve onaylayın.</li>
                    </ol>

                    <!-- QR Code -->
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($qrUrl) ?>"
                             alt="QR Kod"
                             class="border p-2 rounded"
                             style="width:200px;height:200px;background:#fff;">
                    </div>

                    <!-- Manual secret -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Manuel Gizli Anahtar</label>
                        <div class="input-group">
                            <input type="text"
                                   id="secretDisplay"
                                   class="form-control font-monospace"
                                   value="<?= htmlspecialchars($tempSecret) ?>"
                                   readonly>
                            <button class="btn btn-outline-secondary"
                                    type="button"
                                    onclick="copySecret()"
                                    title="Kopyala">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <div class="form-text">Google Authenticator → Manuel giriş → bu kodu yapıştırın.</div>
                    </div>

                    <!-- Confirm code form -->
                    <form method="POST" autocomplete="off">
    <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="secret" value="<?= htmlspecialchars($tempSecret) ?>">

                        <label class="form-label small fw-semibold text-muted">Onay Kodu</label>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="text"
                                   name="code"
                                   class="form-control"
                                   maxlength="6"
                                   inputmode="numeric"
                                   pattern="\d{6}"
                                   placeholder="000000"
                                   required
                                   style="letter-spacing:4px;font-size:18px;text-align:center;">
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fa-solid fa-shield-halved me-2"></i>2FA'yı Aktifleştir
                        </button>
                    </form>
                </div>
            </div>
            <?php
endif; ?>

        </div>
    </div>
</div>

<script>
function copySecret() {
    var el = document.getElementById('secretDisplay');
    el.select();
    document.execCommand('copy');
    var btn = el.nextElementSibling;
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
    setTimeout(function(){ btn.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1500);
}
</script>

<?php
require_once 'includes/footer.php'; ?>
