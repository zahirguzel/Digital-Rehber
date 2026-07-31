<?php
require_once '../config/db.php';
require_once 'includes/logger.php';
require_once '../autoload.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

// Fetch site name from settings
$siteName = 'HatayWeb';
try {
    $row = $pdo->query("SELECT site_title FROM settings LIMIT 1")->fetch();
    if ($row && !empty($row['site_title'])) $siteName = $row['site_title'];
} catch (Exception $e) {}

$error = '';

// Show 2FA failure message if redirected back
if (isset($_GET['error']) && $_GET['error'] === '2fa_failed') {
    $error = '3 hatalı 2FA denemesi. Lütfen tekrar giriş yapın.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (empty($username) || empty($password)) {
        $error = 'Lütfen kullanıcı adı ve şifrenizi girin.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Check if 2FA is enabled for this account
                if (!empty($admin['two_factor_enabled']) && !empty($admin['two_factor_secret'])) {
                    // Hold partial auth state — do not set admin_logged_in yet
                    $_SESSION['pending_2fa_id'] = $admin['id'];
                    $_SESSION['2fa_attempts']   = 0;
                    header('Location: 2fa-verify.php');
                    exit;
                }

                // No 2FA — open full session immediately
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $admin['id'];
                $_SESSION['admin_username']  = $admin['username'];

                // Update last_login timestamp
                try {
                    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
                        ->execute([$admin['id']]);
                } catch (Exception $e) {}

                try {
                    $pdo->prepare(
                        "INSERT INTO admin_logs (admin_id, admin_username, action, module, target_name, target_id, ip_address)
                         VALUES (?, ?, 'login', 'auth', ?, ?, ?)"
                    )->execute([$admin['id'], $admin['username'], $admin['username'], $admin['id'], $ip]);
                } catch (Exception $e) {}

                header("Location: index.php");
                exit;
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre!';

                // Log failed login attempt (no session — insert directly)
                try {
                    $pdo->prepare(
                        "INSERT INTO admin_logs (admin_id, admin_username, action, module, target_name, ip_address)
                         VALUES (NULL, 'system', 'failed_login', 'auth', ?, ?)"
                    )->execute([$username, $ip]);
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {
            $error = 'Veritabanı hatası oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi - <?= htmlspecialchars($siteName) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome & Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #E0533C;
            --navy: #1d3557;
            --radius: 4px;
        }
        
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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .login-header {
            background-color: var(--navy);
            color: #ffffff;
            padding: 30px;
            text-align: center;
            border-bottom: 4px solid var(--primary);
        }
        
        .login-header h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        
        .login-header h2 span {
            color: var(--primary);
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: var(--radius) !important;
            border: 1px solid #CBD5E0;
            padding: 12px 14px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(224, 83, 60, 0.12);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            border-radius: var(--radius) !important;
            padding: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #c84630;
            border-color: #c84630;
        }
        
        .back-to-site {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .back-to-site a {
            color: var(--navy);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-to-site a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- Logo Header -->
        <div class="login-header">
            <h2><i class="fa-solid fa-map-location-dot me-2"></i><?= htmlspecialchars($siteName) ?></h2>
            <p class="mb-0 text-white-50 small">Yönetici Giriş Paneli</p>
        </div>
        
        <!-- Form Body -->
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 small py-2 mb-3" style="border-radius: var(--radius);">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <!-- Username -->
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted small">Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control border-start-0 ps-3" required placeholder="admin" autofocus>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted small">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control border-start-0 ps-3" required placeholder="••••••••">
                    </div>
                </div>
                
                <!-- Submit -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket me-2"></i> Giriş Yap</button>
                </div>
            </form>
            
            <div class="back-to-site">
                <a href="../"><i class="fa-solid fa-arrow-left me-1"></i> Web Sitesine Dön</a>
            </div>

            <div style="margin-top:18px;padding:10px 14px;background:#fff8f0;border:1px solid #fde8d8;border-radius:var(--radius);text-align:center;">
                <p class="mb-0" style="font-size:11px;color:#92400e;">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    Tüm giriş denemeleri IP adresiyle birlikte kayıt altına alınmaktadır.
                </p>
            </div>
        </div>
    </div>

</body>
</html>
