<?php
require_once '../autoload.php';
ob_start();
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();
require_once 'includes/logger.php';

$pageTitle = 'Kullanıcılar & Üyeler';
$currentAdminId = $_SESSION['admin_id'] ?? 0;

$successMsg = '';
$errorMsg   = '';

if (!empty($_SESSION['flash_success'])) {
    $successMsg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errorMsg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// ── 1. BAN / AKTİF ET (TOGGLE STATUS FOR USERS) ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $targetId = intval($_GET['id']);
    $type = $_GET['type'] ?? 'user';
    try {
        if ($type === 'business_user') {
            $stmt = $db->query("SELECT username, is_active FROM business_users WHERE id = ?", [$targetId]);
            $bRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bRow) {
                $newStatus = (!isset($bRow['is_active']) || intval($bRow['is_active']) === 1) ? 0 : 1;
                $db->getPDO()->prepare("UPDATE business_users SET is_active = ? WHERE id = ?")->execute([$newStatus, $targetId]);
                $statusText = ($newStatus == 1) ? 'aktif edildi' : 'banlandı (pasife alındı)';
                logAction('update_status', 'business_users', ($bRow['username'] ?? 'İşletme Kullanıcısı') . ' ' . $statusText, $targetId);
                $_SESSION['flash_success'] = '"' . htmlspecialchars($bRow['username'] ?? 'İşletme Kullanıcısı') . '" hesabı ' . $statusText . '.';
            }
        } else {
            $stmt = $db->query("SELECT name, is_active FROM users WHERE id = ?", [$targetId]);
            $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $newStatus = ($uRow['is_active'] == 1) ? 0 : 1;
                $db->getPDO()->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $targetId]);
                $statusText = ($newStatus == 1) ? 'aktif edildi' : 'banlandı (pasife alındı)';
                logAction('update_status', 'users', ($uRow['name'] ?? 'Kullanıcı') . ' ' . $statusText, $targetId);
                $_SESSION['flash_success'] = '"' . htmlspecialchars($uRow['name'] ?? 'Kullanıcı') . '" hesabı ' . $statusText . '.';
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Durum güncelleme işlemi başarısız: ' . $e->getMessage();
    }
    header('Location: users.php');
    exit;
}

// ── 2. SİLME İŞLEMİ (DELETE USER OR BUSINESS USER) ──────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $targetId = intval($_GET['id']);
    $type = $_GET['type'] ?? 'user';
    try {
        if ($type === 'business_user') {
            $stmt = $db->query("SELECT username FROM business_users WHERE id = ?", [$targetId]);
            $bRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $db->getPDO()->prepare("UPDATE business_users SET is_deleted = 1 WHERE id = ?")->execute([$targetId]);
            logAction('delete', 'business_users', 'Soft Delete: ' . ($bRow['username'] ?? 'İşletme Kullanıcısı'), $targetId);
            $_SESSION['flash_success'] = 'İşletme kullanıcısı başarıyla silindi (Çöp Kutusuna taşındı).';
        } else {
            $stmt = $db->query("SELECT name, email FROM users WHERE id = ?", [$targetId]);
            $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $db->getPDO()->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?")->execute([$targetId]);
            logAction('delete', 'users', 'Soft Delete: ' . ($uRow['name'] ?? 'Kullanıcı'), $targetId);
            $_SESSION['flash_success'] = 'Kullanıcı hesabı başarıyla silindi (Çöp Kutusuna taşındı).';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Silme işlemi başarısız: ' . $e->getMessage();
    }
    header('Location: users.php');
    exit;
}

// ── 3. ŞİFRE SIFIRLAMA (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset_password'])) {
    $targetId  = intval($_POST['target_id'] ?? 0);
    $type      = $_POST['type'] ?? 'user';
    $newPass   = trim($_POST['new_password'] ?? '');

    if ($targetId <= 0 || empty($newPass)) {
        $errorMsg = 'Geçerli bir kullanıcı ve yeni şifre belirtilmelidir.';
    } elseif (!SecurityHelper::validatePasswordStrength($newPass)) {
        $errorMsg = SecurityHelper::getPasswordStrengthMessage();
    } else {
        try {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            if ($type === 'business_user') {
                $db->getPDO()->prepare("UPDATE business_users SET password = ?, force_password_change = 1 WHERE id = ?")->execute([$hashed, $targetId]);
                $_SESSION['flash_success'] = 'İşletme sahibinin şifresi başarıyla sıfırlandı! Kullanıcı giriş yaptığında yeni şifre belirlemesi istenecektir. Yeni şifre: <strong>' . htmlspecialchars($newPass) . '</strong>';
            } else {
                $db->getPDO()->prepare("UPDATE users SET password = ?, force_password_change = 1, updated_at = NOW() WHERE id = ?")->execute([$hashed, $targetId]);
                $_SESSION['flash_success'] = 'Kullanıcının şifresi başarıyla sıfırlandı! Kullanıcı giriş yaptığında yeni şifre belirlemesi istenecektir. Yeni şifre: <strong>' . htmlspecialchars($newPass) . '</strong>';
            }
            header('Location: users.php');
            exit;
        } catch (Exception $e) {
            $errorMsg = 'Şifre sıfırlama başarısız: ' . $e->getMessage();
        }
    }
}

// ── SAYFALAMA VE ARAMA AYARLARI ──────────────────────────────────────────
$limit = 15;
$activeTab = $_GET['tab'] ?? 'normal'; // 'normal' veya 'business'

// Normal Üyeler (Tab 1)
$page_u = max(1, intval($_GET['page_u'] ?? 1));
$offset_u = ($page_u - 1) * $limit;
$search_u = trim($_GET['search_u'] ?? '');

$sql_u_base = "FROM users WHERE is_deleted = 0";
$params_u = [];
if ($search_u !== '') {
    $sql_u_base .= " AND (name LIKE ? OR email LIKE ?)";
    $params_u[] = "%$search_u%";
    $params_u[] = "%$search_u%";
}
$total_u = 0;
$users = [];
try {
    $total_u = $db->query("SELECT COUNT(*) " . $sql_u_base, $params_u)->fetchColumn();
    $users = $db->query("SELECT * " . $sql_u_base . " ORDER BY id DESC LIMIT $limit OFFSET $offset_u", $params_u)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$totalPages_u = ceil($total_u / $limit);

// İşletme Kullanıcıları (Tab 2)
$page_b = max(1, intval($_GET['page_b'] ?? 1));
$offset_b = ($page_b - 1) * $limit;
$search_b = trim($_GET['search_b'] ?? '');

$sql_b_base = "FROM business_users bu LEFT JOIN businesses b ON bu.business_id = b.id WHERE bu.is_deleted = 0";
$params_b = [];
if ($search_b !== '') {
    $sql_b_base .= " AND (bu.username LIKE ? OR b.name LIKE ?)";
    $params_b[] = "%$search_b%";
    $params_b[] = "%$search_b%";
}
$total_b = 0;
$businessUsers = [];
try {
    $total_b = $db->query("SELECT COUNT(*) " . $sql_b_base, $params_b)->fetchColumn();
    $businessUsers = $db->query("SELECT bu.*, b.name AS business_name " . $sql_b_base . " ORDER BY bu.id DESC LIMIT $limit OFFSET $offset_b", $params_b)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$totalPages_b = ceil($total_b / $limit);

include 'includes/header.php';
?>

<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-navy mb-1">
                <i class="fa-solid fa-users me-2 text-primary"></i>Kullanıcılar & Üyeler Yönetimi
            </h5>
            <p class="text-muted small mb-0">
                Sitenize kayıtlı normal üyeleri ve işletme paneli sahiplerini inceleyebilir, yönetebilirsiniz.
            </p>
        </div>
    </div>
</div>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success border-0 small my-3 shadow-sm">
        <i class="fa-solid fa-circle-check me-2"></i><?= $successMsg ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger border-0 small my-3 shadow-sm">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="usersTab" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link fw-semibold <?= $activeTab === 'normal' ? 'active' : '' ?>" href="users.php?tab=normal">
            <i class="fa-solid fa-user me-2"></i>Normal Üyeler (<?= $total_u ?>)
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link fw-semibold <?= $activeTab === 'business' ? 'active' : '' ?>" href="users.php?tab=business">
            <i class="fa-solid fa-store me-2"></i>İşletme Sahipleri (<?= $total_b ?>)
        </a>
    </li>
</ul>

<div class="tab-content" id="usersTabContent">
    <!-- TAB 1: NORMAL USERS -->
    <div class="tab-pane fade <?= $activeTab === 'normal' ? 'show active' : '' ?>" id="normal-users-pane" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white flex-wrap gap-2">
                <div>
                    <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Kayıtlı Normal Kullanıcılar</span>
                    <span class="badge bg-secondary ms-2"><?= $total_u ?> üye</span>
                </div>
                <form method="GET" action="users.php" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="normal">
                    <input type="text" name="search_u" class="form-control form-control-sm" style="width: 200px;" placeholder="İsim, E-posta Ara..." value="<?= htmlspecialchars($search_u) ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if($search_u): ?>
                        <a href="users.php?tab=normal" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="normalUsersTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Kullanıcı</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Durum</th>
                                <th>Kayıt Tarihi</th>
                                <th class="text-end pe-4">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Kayıtlı üye bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u):
                                    $isActive = !isset($u['is_active']) || intval($u['is_active']) === 1;
                                    $initials = mb_strtoupper(mb_substr($u['name'] ?: 'U', 0, 1, 'UTF-8'), 'UTF-8');
                                ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= intval($u['id']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                                 style="width:36px;height:36px;font-size:15px;flex-shrink:0;background:#eff6ff;color:#1d4ed8;">
                                                <?= $initials ?>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-navy d-block"><?= htmlspecialchars($u['name']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($u['role'] ?? 'user') ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="fa-solid fa-circle-check me-1"></i>Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="fa-solid fa-ban me-1"></i>Yasaklı (Banlı)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= !empty($u['created_at']) ? date('d.m.Y H:i', strtotime($u['created_at'])) : '-' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Ban / Aktif Et -->
                                            <a href="users.php?action=toggle_status&id=<?= intval($u['id']) ?>"
                                               class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                               title="<?= $isActive ? 'Banla / Pasife Al' : 'Yasağı Kaldır / Aktif Et' ?>"
                                               onclick="return confirm('Bu kullanıcının hesap durumunu değiştirmek istediğinize emin misiniz?');">
                                                <i class="fa-solid <?= $isActive ? 'fa-ban' : 'fa-check' ?>"></i>
                                                <?= $isActive ? 'Banla' : 'Aktif Et' ?>
                                            </a>

                                            <!-- Şifre Sıfırla Modal Aç -->
                                            <button type="button" class="btn btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#resetPassModal"
                                                    data-id="<?= intval($u['id']) ?>"
                                                    data-type="user"
                                                    data-name="<?= htmlspecialchars($u['name'] ?: $u['email']) ?>"
                                                    title="Şifreyi Sıfırla">
                                                <i class="fa-solid fa-key me-1"></i>Şifre
                                            </button>

                                            <!-- Sil -->
                                            <a href="users.php?action=delete&type=user&id=<?= intval($u['id']) ?>"
                                               class="btn btn-outline-danger"
                                               title="Kullanıcıyı Sil"
                                               onclick="return confirm('<?= htmlspecialchars(addslashes($u['name'])) ?> kullanıcısını kalıcı olarak silmek istediğinize emin misiniz?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages_u > 1): ?>
            <div class="card-footer bg-white py-3 border-top-0">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= ($page_u <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=normal&page_u=<?= $page_u - 1 ?>&search_u=<?= urlencode($search_u) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages_u; $i++): ?>
                            <li class="page-item <?= ($i == $page_u) ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=normal&page_u=<?= $i ?>&search_u=<?= urlencode($search_u) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page_u >= $totalPages_u) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=normal&page_u=<?= $page_u + 1 ?>&search_u=<?= urlencode($search_u) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- TAB 2: BUSINESS USERS -->
    <div class="tab-pane fade <?= $activeTab === 'business' ? 'show active' : '' ?>" id="business-users-pane" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white flex-wrap gap-2">
                <div>
                    <span class="fw-bold text-navy"><i class="fa-solid fa-store me-2 text-primary"></i>İşletme Sahipleri (Kullanıcıları)</span>
                    <span class="badge bg-secondary ms-2"><?= $total_b ?> kullanıcı</span>
                </div>
                <form method="GET" action="users.php" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="business">
                    <input type="text" name="search_b" class="form-control form-control-sm" style="width: 200px;" placeholder="Kullanıcı, İşletme Ara..." value="<?= htmlspecialchars($search_b) ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if($search_b): ?>
                        <a href="users.php?tab=business" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="businessUsersTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Kullanıcı Adı</th>
                                <th>Bağlı İşletme</th>
                                <th>Rol</th>
                                <th>Durum</th>
                                <th>Kayıt Tarihi</th>
                                <th class="text-end pe-4">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($businessUsers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">İşletme kullanıcısı bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($businessUsers as $bu):
                                    $initials = mb_strtoupper(mb_substr($bu['username'] ?: 'B', 0, 1, 'UTF-8'), 'UTF-8');
                                    $isBuActive = !isset($bu['is_active']) || intval($bu['is_active']) === 1;
                                ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= intval($bu['id']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                                 style="width:36px;height:36px;font-size:15px;flex-shrink:0;background:#f0fdf4;color:#15803d;">
                                                <?= $initials ?>
                                            </div>
                                            <span class="fw-semibold text-navy"><?= htmlspecialchars($bu['username']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($bu['business_name'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($bu['business_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">İşletme #<?= intval($bu['business_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                            <?= htmlspecialchars($bu['role'] ?? 'business') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isBuActive): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="fa-solid fa-circle-check me-1"></i>Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="fa-solid fa-ban me-1"></i>Yasaklı (Banlı)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= !empty($bu['created_at']) ? date('d.m.Y H:i', strtotime($bu['created_at'])) : '-' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Ban / Aktif Et -->
                                            <a href="users.php?action=toggle_status&type=business_user&id=<?= intval($bu['id']) ?>"
                                               class="btn <?= $isBuActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                               title="<?= $isBuActive ? 'Banla / Pasife Al' : 'Yasağı Kaldır / Aktif Et' ?>"
                                               onclick="return confirm('Bu işletme hesabının durumunu değiştirmek istediğinize emin misiniz?');">
                                                <i class="fa-solid <?= $isBuActive ? 'fa-ban' : 'fa-check' ?>"></i>
                                                <?= $isBuActive ? 'Banla' : 'Aktif Et' ?>
                                            </a>

                                            <!-- Şifre Sıfırla Modal Aç -->
                                            <button type="button" class="btn btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#resetPassModal"
                                                    data-id="<?= intval($bu['id']) ?>"
                                                    data-type="business_user"
                                                    data-name="<?= htmlspecialchars($bu['username']) ?>"
                                                    title="Şifreyi Sıfırla">
                                                <i class="fa-solid fa-key me-1"></i>Şifre
                                            </button>

                                            <!-- Sil -->
                                            <a href="users.php?action=delete&type=business_user&id=<?= intval($bu['id']) ?>"
                                               class="btn btn-outline-danger"
                                               title="Hesabı Sil"
                                               onclick="return confirm('<?= htmlspecialchars(addslashes($bu['username'])) ?> işletme hesabını silmek istediğinize emin misiniz?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages_b > 1): ?>
            <div class="card-footer bg-white py-3 border-top-0">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= ($page_b <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=business&page_b=<?= $page_b - 1 ?>&search_b=<?= urlencode($search_b) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages_b; $i++): ?>
                            <li class="page-item <?= ($i == $page_b) ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=business&page_b=<?= $i ?>&search_b=<?= urlencode($search_b) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page_b >= $totalPages_b) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=business&page_b=<?= $page_b + 1 ?>&search_b=<?= urlencode($search_b) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- ŞİFRE SIFIRLAMA MODALI -->
<div class="modal fade" id="resetPassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= CSRFMiddleware::field() ?>
            <input type="hidden" name="do_reset_password" value="1">
            <input type="hidden" name="target_id" id="modalTargetId" value="">
            <input type="hidden" name="type" id="modalTargetType" value="user">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-navy">
                        <i class="fa-solid fa-key text-primary me-2"></i>Şifre Sıfırla: <span id="modalTargetName" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-muted">Yeni Şifre <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="new_password" id="modalNewPassword" class="form-control" placeholder="En az 8 karakter (A-Z, a-z, 0-9)" required minlength="8">
                            <button class="btn btn-outline-secondary" type="button" id="btnGenPassword" title="Rastgele Güçlü Şifre Üret">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Rastgele
                            </button>
                        </div>
                        <div class="form-text small text-muted">
                            Bu işlemi onayladığınızda kullanıcının şifresi hemen güncellenecek ve ekranda görebileceksiniz.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="fa-solid fa-check me-1"></i>Şifreyi Sıfırla ve Kaydet
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Şifre modalını doldur
var resetModal = document.getElementById('resetPassModal');
if (resetModal) {
    resetModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var type = button.getAttribute('data-type');
        var name = button.getAttribute('data-name');
        
        document.getElementById('modalTargetId').value = id;
        document.getElementById('modalTargetType').value = type;
        document.getElementById('modalTargetName').textContent = name;
        document.getElementById('modalNewPassword').value = '';
    });
}

// Rastgele Şifre Üret butonu
document.getElementById('btnGenPassword')?.addEventListener('click', function() {
    var chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$";
    var pass = "";
    for (var i = 0; i < 9; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('modalNewPassword').value = pass;
});
</script>

<?php include 'includes/footer.php'; ?>
