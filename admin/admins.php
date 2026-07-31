<?php
require_once '../autoload.php';
ob_start();
require_once 'includes/auth.php';

requireRole(['superadmin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once 'includes/logger.php';

$pageTitle = 'Admin Yönetimi';
$currentAdminId = $_SESSION['admin_id'] ?? 0;

$successMsg = '';
$errorMsg   = '';

// ── DELETE ─────────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = intval($_GET['id']);
    if ($delId === intval($currentAdminId)) {
        $errorMsg = 'Kendi hesabınızı silemezsiniz!';
    } else {
        try {
            $stmt = $db->query("SELECT username FROM admins WHERE id = ?", [$delId]);
            $delAdmin = $stmt->fetch();
            $db->getPDO()->prepare("DELETE FROM admins WHERE id = ?")->execute([$delId]);
            logAction('delete', 'admins', $delAdmin['username'] ?? 'Bilinmeyen', $delId);
            $successMsg = 'Admin başarıyla silindi.';
        } catch (Exception $e) {
            $errorMsg = 'Silme işlemi başarısız.';
        }
    }
}

// ── ADD / EDIT (POST) ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_save'])) {
    $postId       = intval($_POST['id'] ?? 0);
    $username     = trim($_POST['username'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $role         = in_array($_POST['role'] ?? '', ['superadmin','admin','editor']) ? $_POST['role'] : 'admin';

    if (empty($username)) {
        $errorMsg = 'Kullanıcı adı boş bırakılamaz.';
    } elseif ($postId === 0 && empty($password)) {
        $errorMsg = 'Yeni admin için şifre zorunludur.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $errorMsg = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            if ($postId > 0) {
                // UPDATE existing admin
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->query("UPDATE admins SET username=?, password=?, role=? WHERE id=?", [$username, $hashed, $role, $postId]);
                } else {
                    $stmt = $db->query("UPDATE admins SET username=?, role=? WHERE id=?", [$username, $role, $postId]);
                }
                logAction('update', 'admins', $username, $postId);
                $successMsg = '"' . htmlspecialchars($username) . '" admini güncellendi.';
            } else {
                // INSERT new admin
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->query("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)", [$username, $hashed, $role]);
                $newId = intval($db->getPDO()->lastInsertId());
                logAction('create', 'admins', $username, $newId);
                $successMsg = '"' . htmlspecialchars($username) . '" admini oluşturuldu.';
            }
        } catch (Exception $e) {
            // Duplicate username
            if (strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
                $errorMsg = 'Bu kullanıcı adı zaten kullanılıyor.';
            } else {
                $errorMsg = 'İşlem başarısız: ' . $e->getMessage();
            }
        }
    }
}

// ── Fetch edit target ────────────────────────────────────────────────────────
$editAdmin = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    try {
        $stmt = $db->query("SELECT id, username, role FROM admins WHERE id = ?", [intval($_GET['id'])]);
        $editAdmin = $stmt->fetch();
    } catch (Exception $e) {}
}

// ── Fetch all admins ─────────────────────────────────────────────────────────
$admins = [];
try {
    $admins = $db->query("SELECT * FROM admins ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {}

$roleLabels  = ['superadmin' => 'Süper Admin', 'admin' => 'Admin', 'editor' => 'Editör'];
$roleStyles  = [
    'superadmin' => 'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
    'admin'      => 'background:#eff6ff;color:#1d4ed8;border:1px solid #93c5fd;',
    'editor'     => 'background:#f0fdf4;color:#15803d;border:1px solid #86efac;',
];

include 'includes/header.php';
?>

<div class="mb-1">
    <h5 class="fw-bold text-navy mb-1"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Admin Yönetimi</h5>
    <p class="text-muted small mb-0">Yönetici hesaplarını buradan ekleyin, düzenleyin veya silin.</p>
</div>

<?php
if (!empty($successMsg)): ?>
    <div class="alert alert-success border-0 small my-3"><i class="fa-solid fa-circle-check me-1"></i> <?= $successMsg ?></div>
<?php
endif; ?>
<?php
if (!empty($errorMsg)): ?>
    <div class="alert alert-danger border-0 small my-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($errorMsg) ?></div>
<?php
endif; ?>

<!-- Add / Edit Form — always visible -->
<div class="card border-0 shadow-sm mb-4 mt-3">
    <div class="card-header py-3">
        <span class="fw-bold text-navy">
            <i class="fa-solid fa-<?= $editAdmin ? 'pen' : 'user-plus' ?> me-2 text-primary"></i>
            <?= $editAdmin ? '"' . htmlspecialchars($editAdmin['username']) . '" Düzenle' : 'Yeni Admin Ekle' ?>
        </span>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="">
    <?= CSRFMiddleware::field() ?>
            <input type="hidden" name="id" value="<?= $editAdmin ? intval($editAdmin['id']) : 0 ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-muted">Kullanıcı Adı <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($editAdmin['username'] ?? '') ?>"
                           required placeholder="ornek: editor01">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-muted">
                        Şifre <?= $editAdmin ? '<span class="text-muted">(boş bırakılırsa değişmez)</span>' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" name="password" class="form-control"
                           <?= ($editAdmin ? '' : 'required') ?>
                           placeholder="<?= $editAdmin ? 'Değiştirmek için girin' : 'Min. 6 karakter' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-muted">Rol <span class="text-danger">*</span></label>
                    <select name="role" class="form-select">
                        <option value="editor"     <?= ($editAdmin['role'] ?? '') === 'editor'     ? 'selected' : '' ?>>Editör — İçerik yönetebilir</option>
                        <option value="admin"      <?= ($editAdmin['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Admin — Tam yetki</option>
                        <option value="superadmin" <?= ($editAdmin['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Süper Admin — Admin yönetimi dahil</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" name="do_save" value="1" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        <?= $editAdmin ? 'Güncelle' : 'Admin Ekle' ?>
                    </button>
                    <?php
if ($editAdmin): ?>
                    <a href="admins.php" class="btn btn-outline-secondary px-4">İptal</a>
                    <?php
endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Admins Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Kayıtlı Adminler</span>
        <span class="badge bg-secondary"><?= count($admins) ?> admin</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Kullanıcı Adı</th>
                        <th>Rol</th>
                        <th>2FA</th>
                        <th>Son Giriş</th>
                        <th>Kayıt Tarihi</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
if (empty($admins)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Admin bulunamadı.</td></tr>
                    <?php
else: ?>
                        <?php
foreach ($admins as $adm):
                            $isMe      = intval($adm['id']) === intval($currentAdminId);
                            $roleKey   = $adm['role'] ?? 'admin';
                            $roleStyle = $roleStyles[$roleKey]  ?? $roleStyles['admin'];
                            $roleLabel = $roleLabels[$roleKey]  ?? 'Admin';
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $adm['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                         style="width:36px;height:36px;font-size:15px;flex-shrink:0;background:#eff6ff;color:#1d4ed8;">
                                        <?= mb_strtoupper(mb_substr($adm['username'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold text-navy"><?= htmlspecialchars($adm['username']) ?></span>
                                        <?php
if ($isMe): ?>
                                            <span style="font-size:11px;background:#f0fdf4;color:#15803d;border:1px solid #86efac;border-radius:4px;padding:1px 7px;font-weight:600;">
                                                <i class="fa-solid fa-circle-dot me-1" style="font-size:7px;"></i>Siz
                                            </span>
                                        <?php
endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:11px;font-weight:600;border-radius:4px;padding:4px 10px;<?= $roleStyle ?>">
                                    <?= $roleLabel ?>
                                </span>
                            </td>
                            <td>
                                <?php
if (!empty($adm['two_factor_enabled'])): ?>
                                    <span title="2FA Aktif" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:4px;padding:3px 8px;">
                                        <i class="fa-solid fa-shield-halved"></i> Aktif
                                    </span>
                                <?php
else: ?>
                                    <span title="2FA Pasif" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;background:#f8f9fa;color:#6c757d;border:1px solid #dee2e6;border-radius:4px;padding:3px 8px;">
                                        <i class="fa-solid fa-shield"></i> Pasif
                                    </span>
                                <?php
endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?php
if (!empty($adm['last_login'])): ?>
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('d.m.Y H:i', strtotime($adm['last_login'])) ?>
                                <?php
else: ?>
                                    <span class="text-muted">—</span>
                                <?php
endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= date('d.m.Y', strtotime($adm['created_at'])) ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="admins.php?action=edit&id=<?= $adm['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fa-solid fa-pen-to-square"></i> Düzenle
                                    </a>
                                    <?php
if (!$isMe): ?>
                                        <a href="admins.php?action=delete&id=<?= $adm['id'] ?>"
                                           class="btn btn-outline-danger btn-sm confirm-btn"
                                           data-confirm-title="Admin Sil"
                                           data-confirm="'<?= htmlspecialchars($adm['username']) ?>' adlı admini silmek istediğinizden emin misiniz?"
                                           data-confirm-btn="Evet, Sil">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    <?php
endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
endforeach; ?>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php'; ?>
