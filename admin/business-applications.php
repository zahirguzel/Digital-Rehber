<?php
require_once '../autoload.php';
require_once 'includes/auth.php';
require_once '../includes/seo-meta.php';

requireRole(['superadmin', 'admin']);
$db = Database::getInstance()->getPDO();

// Read flash messages from session
$successMsg = '';
$errorMsg   = '';
$newPassword = '';
$newUsername = '';

if (isset($_SESSION['ba_success'])) {
    $successMsg = $_SESSION['ba_success'];
    unset($_SESSION['ba_success']);
}
if (isset($_SESSION['ba_error'])) {
    $errorMsg = $_SESSION['ba_error'];
    unset($_SESSION['ba_error']);
}
if (isset($_SESSION['ba_credentials'])) {
    $newUsername = $_SESSION['ba_credentials']['username'];
    $newPassword = $_SESSION['ba_credentials']['password'];
    unset($_SESSION['ba_credentials']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCSRF();
    
    $appId = (int)$_POST['app_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'reject') {
            $stmt = $db->prepare("UPDATE business_applications SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$appId]);
            if (function_exists('logAction')) logAction('reject', 'business_applications', 'Başvuru ID: ' . $appId, $appId);
            $_SESSION['ba_success'] = "Başvuru reddedildi.";
        } elseif ($action === 'approve') {
            $stmt = $db->prepare("SELECT * FROM business_applications WHERE id = ? AND status = 'pending'");
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($app) {
                // Generate slug
                $slugBase = strtolower(str_replace([' ', 'ğ', 'ü', 'ş', 'ı', 'ö', 'ç', 'Ğ', 'Ü', 'Ş', 'İ', 'Ö', 'Ç'], ['-', 'g', 'u', 's', 'i', 'o', 'c', 'g', 'u', 's', 'i', 'o', 'c'], trim($app['business_name'])));
                $slugBase = preg_replace('/[^a-z0-9-]/', '', $slugBase);
                
                // Ensure unique slug
                $slug = $slugBase;
                $counter = 1;
                while ($db->query("SELECT id FROM businesses WHERE slug = " . $db->quote($slug))->fetchColumn()) {
                    $slug = $slugBase . '-' . $counter;
                    $counter++;
                }
                
                // Ensure unique username
                $username = str_replace('-', '_', $slug);
                $ucounter = 1;
                while ($db->query("SELECT id FROM business_users WHERE username = " . $db->quote($username))->fetchColumn()) {
                    $username = str_replace('-', '_', $slugBase) . '_' . $ucounter;
                    $ucounter++;
                }

                $db->beginTransaction();
                
                // Insert into businesses
                $insBiz = $db->prepare("INSERT INTO businesses (category_id, city, district, name, slug, description, phone, email, is_premium) VALUES (1, ?, ?, ?, ?, ?, ?, ?, 0)");
                $insBiz->execute([$app['city'], $app['district'], $app['business_name'], $slug, $app['description'], $app['phone'], $app['email']]);
                $businessId = $db->lastInsertId();
                
                // Generate Password
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789!*?';
                $newPassword = substr(str_shuffle($chars), 0, 6);
                $hashedPw = SecurityHelper::hashPassword($newPassword);
                
                // Insert into business_users with force_password_change = 1
                $insUser = $db->prepare("INSERT INTO business_users (business_id, username, password, role, force_password_change) VALUES (?, ?, ?, 'business', 1)");
                $insUser->execute([$businessId, $username, $hashedPw]);
                
                // Update application status
                $updApp = $db->prepare("UPDATE business_applications SET status = 'approved' WHERE id = ?");
                $updApp->execute([$appId]);
                
                if (function_exists('logAction')) logAction('approve', 'business_applications', 'Başvuru ID: ' . $appId . ' -> İşletme: ' . $app['business_name'], $appId);
                
                $db->commit();
                
                $_SESSION['ba_success'] = "İşletme başarıyla onaylandı ve hesap oluşturuldu!";
                $_SESSION['ba_credentials'] = [
                    'username' => $username,
                    'password' => $newPassword,
                ];
            }
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM business_applications WHERE id = ?");
            $stmt->execute([$appId]);
            if (function_exists('logAction')) logAction('delete', 'business_applications', 'Başvuru ID: ' . $appId, $appId);
            $_SESSION['ba_success'] = "Başvuru kalıcı olarak silindi.";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['ba_error'] = "Hata oluştu: " . $e->getMessage();
    }
    
    // PRG: redirect to prevent form resubmission on refresh
    header('Location: business-applications.php');
    exit;
}

// Fetch pending applications
$pending = $db->query("SELECT * FROM business_applications WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
// Fetch history
$history = $db->query("SELECT * FROM business_applications WHERE status != 'pending' ORDER BY updated_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'İşletme Başvuruları';
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-inbox text-primary me-2"></i> İşletme Başvuruları</h2>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<?php if ($newPassword): ?>
    <div class="alert alert-warning border-warning shadow-sm" style="border-width: 2px;">
        <h4 class="alert-heading fw-bold"><i class="fa-solid fa-lock text-warning me-2"></i> Şifre Üretildi!</h4>
        <p>İşletme hesabı oluşturuldu. Lütfen aşağıdaki bilgileri işletmeye (örneğin WhatsApp'tan) iletin:</p>
        <hr>
        <div class="bg-white p-3 rounded border mb-0" style="font-family: monospace; font-size: 1.1rem;">
            <strong>Panel Linki:</strong> <?= seoGetBaseUrl() ?>/isletme/login.php<br>
            <strong>Kullanıcı Adı:</strong> <?= htmlspecialchars($newUsername) ?><br>
            <strong>Geçici Şifre:</strong> <span class="text-danger fw-bold"><?= htmlspecialchars($newPassword) ?></span>
        </div>
        <p class="mb-0 mt-2 small text-muted"><i class="fa-solid fa-info-circle"></i> Not: İşletme giriş yaptığı anda sistem otomatik olarak şifre değiştirmeye zorlayacaktır.</p>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="appTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
            Bekleyen Başvurular <span class="badge bg-danger ms-1"><?= count($pending) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">Geçmiş (Onay/Red)</button>
    </li>
</ul>

<div class="tab-content" id="appTabsContent">
    <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>İşletme Adı</th>
                                <th>Konum</th>
                                <th>Yetkili</th>
                                <th>İletişim</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Bekleyen başvuru bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending as $p): ?>
                                    <tr>
                                        <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                                        <td class="fw-bold text-navy"><?= htmlspecialchars($p['business_name']) ?></td>
                                        <td><?= htmlspecialchars($p['city']) ?> / <?= htmlspecialchars($p['district']) ?></td>
                                        <td><?= htmlspecialchars($p['contact_name']) ?></td>
                                        <td>
                                            <div><i class="fa-solid fa-phone text-muted small me-1"></i><?= htmlspecialchars($p['phone']) ?></div>
                                            <?php if ($p['email']): ?>
                                                <div><i class="fa-solid fa-envelope text-muted small me-1"></i><?= htmlspecialchars($p['email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form action="" method="POST" class="d-inline-block">
                                                <?= CSRFMiddleware::field() ?>
                                                <input type="hidden" name="app_id" value="<?= $p['id'] ?>">
                                                
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success shadow-sm" onclick="return confirm('İşletmeyi onaylayıp hesap oluşturmak istediğinize emin misiniz?');">
                                                    <i class="fa-solid fa-check me-1"></i> Onayla
                                                </button>
                                                
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger shadow-sm ms-1" onclick="return confirm('Başvuruyu reddetmek istediğinize emin misiniz?');">
                                                    <i class="fa-solid fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php if ($p['description']): ?>
                                        <tr class="bg-light">
                                            <td colspan="6" class="py-2 px-3 small border-bottom">
                                                <i class="fa-solid fa-quote-left text-muted me-2"></i> <em><?= htmlspecialchars($p['description']) ?></em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>İşletme Adı</th>
                                <th>Konum</th>
                                <th>İletişim</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Geçmiş işlem bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>
                                            <?php if ($h['status'] === 'approved'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success">Onaylandı</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Reddedildi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($h['updated_at'])) ?></td>
                                        <td class="fw-bold text-navy"><?= htmlspecialchars($h['business_name']) ?></td>
                                        <td class="small"><?= htmlspecialchars($h['city']) ?> / <?= htmlspecialchars($h['district']) ?></td>
                                        <td class="small"><?= htmlspecialchars($h['phone']) ?></td>
                                        <td class="text-end">
                                            <form action="" method="POST" class="d-inline-block">
                                                <?= CSRFMiddleware::field() ?>
                                                <input type="hidden" name="app_id" value="<?= $h['id'] ?>">
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-secondary border-0" onclick="return confirm('Kalıcı olarak silinecek. Emin misiniz?');">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>