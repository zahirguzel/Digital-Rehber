<?php
require_once __DIR__ . '/../autoload.php';

Session::start();

if (Session::get('biz_logged_in') === true) {
    header('Location: menu.php'); 
    exit;
}

$db = Database::getInstance();
$siteName = 'Dijital Rehber';

try {
    $r = $db->fetchOne("SELECT site_title FROM settings LIMIT 1");
    if ($r && !empty($r['site_title'])) $siteName = $r['site_title'];
} catch (Exception $e) {
    // Ignore error, fallback to Digital Rehber
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFMiddleware::validate()) {
        $error = 'Güvenlik tokeni doğrulanamadı. Lütfen sayfayı yenileyin.';
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = trim($_POST['password'] ?? '');
        
        if ($u && $p) {
            // Rate Limit
            if (!RateLimitMiddleware::checkLogin($u)) {
                $error = 'Çok fazla başarısız giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.';
                Logger::security('Business login rate limit exceeded', ['username' => $u, 'ip' => SecurityHelper::getClientIP()]);
            } else {
                try {
                    $bu = $db->fetchOne("SELECT bu.*, b.name as biz_name, b.slug as biz_slug FROM business_users bu JOIN businesses b ON bu.business_id = b.id WHERE bu.username = ? OR b.email = ?", [$u, $u]);
                    
                    if ($bu && SecurityHelper::verifyPassword($p, $bu['password'])) {
                        // Success
                        RateLimitMiddleware::resetLogin($u);
                        
                        Session::set('biz_logged_in', true);
                        Session::set('biz_user_id', $bu['id']);
                        Session::set('biz_id', $bu['business_id']);
                        Session::set('biz_username', $bu['username']);
                        Session::set('biz_name', $bu['biz_name']);
                        Session::set('biz_slug', $bu['biz_slug']);
                        Session::set('force_password', !empty($bu['force_password_change']));
                        
                        Logger::info('Business logged in', ['username' => $u, 'ip' => SecurityHelper::getClientIP()]);
                        
                        if (!empty($bu['force_password_change'])) {
                            header('Location: force-password.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit;
                    } else {
                        $error = 'Kullanıcı adı veya şifre hatalı.';
                        Logger::security('Failed business login attempt', ['username' => $u, 'ip' => SecurityHelper::getClientIP()]);
                    }
                } catch (Exception $e) {
                    $error = 'Bir hata oluştu.';
                    Logger::error('Business login error', ['error' => $e->getMessage()]);
                }
            }
        } else {
            $error = 'Lütfen tüm alanları doldurun.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşletme Girişi — <?= htmlspecialchars($siteName) ?></title>
    <?= CSRFMiddleware::meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #E0533C; 
            --primary-hover: #c84630;
            --primary-light: rgba(224, 83, 60, 0.1);
            --radius: 16px; 
            --text-dark: #1d3557;
            --text-muted: #64748b;
        }
        body { 
            font-family: 'Outfit', sans-serif; 
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
            position: relative;
            overflow: hidden;
        }
        /* Decorative Background Elements */
        body::before {
            content: '';
            position: absolute;
            top: -10%; left: -5%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, var(--primary-light) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }
        body::after {
            content: '';
            position: absolute;
            bottom: -15%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(29, 53, 87, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }
        
        .login-card { 
            width: 100%; 
            max-width: 440px; 
            background: #ffffff; 
            border: 1px solid rgba(255,255,255,0.8); 
            border-radius: var(--radius); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08); 
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        
        /* Top Accent Line */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary);
        }

        .login-header { 
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
        
        .login-header h2 { 
            font-size: 26px; 
            font-weight: 700; 
            color: var(--text-dark);
            margin-bottom: 8px; 
            letter-spacing: -0.5px;
        }
        .login-header p { 
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 0;
        }

        .login-body { 
            padding: 20px 40px 40px; 
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .input-group {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
            background: #fff;
        }
        
        .input-group-text {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding-left: 18px;
        }
        
        .form-control { 
            background: transparent;
            border: none; 
            padding: 14px 14px 14px 10px; 
            font-size: 15px;
            color: var(--text-dark);
        }
        .form-control:focus { 
            box-shadow: none;
            background: transparent;
        }
        
        .btn-primary { 
            background: var(--primary); 
            border: none; 
            border-radius: 10px; 
            padding: 14px; 
            font-weight: 600; 
            font-size: 16px;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 16px var(--primary-light);
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            background: var(--primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 10px 20px var(--primary-light);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            margin-top: 25px;
        }
        .back-link:hover {
            color: var(--primary);
        }
        
        .alert {
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="icon-wrapper">
            <i class="fa-solid fa-store"></i>
        </div>
        <h2>İşletme Yönetimi</h2>
        <p>Paneli kullanmak için giriş yapın</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?= CSRFMiddleware::field() ?>
            <div class="mb-4">
                <label class="form-label">Kullanıcı Adı veya E-posta</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Örn: isletme_adi veya mail@adres.com" required autofocus>
                </div>
            </div>
            <div class="mb-5">
                <label class="form-label">Şifre</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="text-end mt-1">
                    <a href="sifremi-unuttum.php" class="small text-decoration-none fw-semibold" style="font-size: 13px; color: #D62828;">Şifremi Unuttum?</a>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    Giriş Yap <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <div class="text-center">
            <a href="../index.php" class="back-link">
                <i class="fa-solid fa-arrow-left me-2"></i> Siteye Geri Dön
            </a>
        </div>
    </div>
</div>
</body>
</html>
