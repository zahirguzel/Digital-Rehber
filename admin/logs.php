<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$pageTitle = 'İşlem Kayıtları';

// Filters
$filterAdmin  = $_GET['admin']  ?? '';
$filterAction = $_GET['action'] ?? '';
$filterModule = $_GET['module'] ?? '';

// Build query
$sql    = "SELECT * FROM admin_logs WHERE 1=1";
$params = [];

if (!empty($filterAdmin)) {
    $sql .= " AND admin_username = ?";
    $params[] = $filterAdmin;
}
if (!empty($filterAction)) {
    $sql .= " AND action = ?";
    $params[] = $filterAction;
}
if (!empty($filterModule)) {
    $sql .= " AND module = ?";
    $params[] = $filterModule;
}

$sql .= " ORDER BY created_at DESC LIMIT 500";

$logs = [];
try {
    $stmt = $db->query($sql, $params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch distinct admins for filter dropdown
$adminList = [];
try {
    $adminList = $db->query("SELECT DISTINCT admin_username FROM admin_logs ORDER BY admin_username ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Action badge inline styles
$actionStyles = [
    'login'        => 'background:#f0fdf4;color:#15803d;border:1px solid #86efac;',
    'logout'       => 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1;',
    'create'       => 'background:#eff6ff;color:#1d4ed8;border:1px solid #93c5fd;',
    'update'       => 'background:#fffbeb;color:#b45309;border:1px solid #fcd34d;',
    'delete'       => 'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
    'view'         => 'background:#f0f9ff;color:#0369a1;border:1px solid #7dd3fc;',
    'approve'      => 'background:#f0fdf4;color:#15803d;border:1px solid #86efac;',
    'reject'       => 'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
    'failed_login' => 'background:#fff0f0;color:#b91c1c;border:1px solid #f87171;',
    '2fa_enabled'  => 'background:#f0fdf4;color:#166534;border:1px solid #4ade80;',
    '2fa_disabled' => 'background:#fffbeb;color:#92400e;border:1px solid #fbbf24;',
];
$actionLabels = [
    'login'        => 'Giriş',
    'logout'       => 'Çıkış',
    'create'       => 'Ekleme',
    'update'       => 'Güncelleme',
    'delete'       => 'Silme',
    'view'         => 'Görüntüleme',
    'approve'      => 'Onaylama',
    'reject'       => 'Reddetme',
    'failed_login' => 'Yetkisiz Giriş',
    '2fa_enabled'  => '2FA Aktif',
    '2fa_disabled' => '2FA Pasif',
];
$actionIcons = [
    'login'   => 'fa-right-to-bracket',
    'logout'  => 'fa-right-from-bracket',
    'create'  => 'fa-plus',
    'update'  => 'fa-pen',
    'delete'  => 'fa-trash',
    'view'    => 'fa-eye',
    'approve' => 'fa-check',
    'reject'  => 'fa-xmark',
];
$moduleLabels = [
    'businesses'      => 'Esnaf',
    'pending_changes' => 'Onay Bekleyenler',
    'categories'      => 'Kategori',
    'ads'             => 'Reklam',
    'blogs'           => 'Blog',
    'services'        => 'Hizmet',
    'settings'        => 'Ayarlar',
    'admins'          => 'Adminler',
    'messages'        => 'Mesajlar',
    'auth'            => 'Oturum',
];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold text-navy mb-1"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>İşlem Kayıtları</h5>
        <p class="text-muted small mb-0">Tüm admin işlemlerinin otomatik kaydı. Son 500 kayıt gösteriliyor.</p>
    </div>
    <?php
if (!empty($logs)): ?>
    <a href="logs.php?clear=1" class="btn btn-outline-danger btn-sm confirm-btn"
       data-confirm-title="Logları Temizle"
       data-confirm="Tüm işlem kayıtları silinecek. Bu işlem geri alınamaz!"
       data-confirm-btn="Evet, Temizle">
        <i class="fa-solid fa-trash me-1"></i> Tümünü Temizle
    </a>
    <?php
endif; ?>
</div>

<?php
// Handle clear action
if (isset($_GET['clear'])) {
    try {
        $pdo->exec("DELETE FROM admin_logs");
        echo '<div class="alert alert-success border-0 small mb-4"><i class="fa-solid fa-check me-1"></i> Tüm işlem kayıtları temizlendi.</div>';
        $logs = [];
    } catch (Exception $e) {}
}
?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Admin</label>
                <select name="admin" class="form-select form-select-sm">
                    <option value="">Tüm Adminler</option>
                    <?php
foreach ($adminList as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filterAdmin === $a ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">İşlem Türü</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Tüm İşlemler</option>
                    <option value="login"   <?= $filterAction === 'login'   ? 'selected' : '' ?>>Giriş</option>
                    <option value="logout"  <?= $filterAction === 'logout'  ? 'selected' : '' ?>>Çıkış</option>
                    <option value="create"  <?= $filterAction === 'create'  ? 'selected' : '' ?>>Ekleme</option>
                    <option value="update"  <?= $filterAction === 'update'  ? 'selected' : '' ?>>Güncelleme</option>
                    <option value="delete"  <?= $filterAction === 'delete'  ? 'selected' : '' ?>>Silme</option>
                    <option value="approve" <?= $filterAction === 'approve' ? 'selected' : '' ?>>Onaylama</option>
                    <option value="reject"  <?= $filterAction === 'reject'  ? 'selected' : '' ?>>Reddetme</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Modül</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">Tüm Modüller</option>
                    <?php
foreach ($moduleLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filterModule === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-filter me-1"></i>Filtrele</button>
                <a href="logs.php" class="btn btn-outline-secondary btn-sm px-3">Temizle</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Kayıtlar</span>
        <span class="badge bg-secondary"><?= count($logs) ?> kayıt</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Tarih & Saat</th>
                        <th>Admin</th>
                        <th>İşlem</th>
                        <th>Modül</th>
                        <th>Hedef Kayıt</th>
                        <th class="pe-4">IP Adresi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fs-2 mb-2 d-block"></i>
                                Henüz işlem kaydı bulunmuyor.
                            </td>
                        </tr>
                    <?php
else: ?>
                        <?php
foreach ($logs as $log):
                            $actionStyle = $actionStyles[$log['action']] ?? $actionStyles['view'];
                            $actionLabel = $actionLabels[$log['action']] ?? ucfirst($log['action']);
                            $icon        = $actionIcons[$log['action']]  ?? 'fa-circle';
                            $modLabel    = $moduleLabels[$log['module']] ?? $log['module'];
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small" style="white-space:nowrap;">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <span class="fw-semibold text-navy">
                                    <i class="fa-solid fa-user-shield me-1 text-muted" style="font-size:12px;"></i>
                                    <?= htmlspecialchars($log['admin_username']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size:11px;font-weight:600;border-radius:4px;padding:4px 10px;white-space:nowrap;<?= $actionStyle ?>">
                                    <i class="fa-solid <?= $icon ?> me-1"></i><?= $actionLabel ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">
                                    <?= htmlspecialchars($modLabel) ?>
                                </span>
                            </td>
                            <td>
                                <?php
if (!empty($log['target_name'])): ?>
                                    <span class="text-navy small">
                                        <?= htmlspecialchars($log['target_name']) ?>
                                        <?php
if (!empty($log['target_id'])): ?>
                                            <span class="text-muted">(#<?= $log['target_id'] ?>)</span>
                                        <?php
endif; ?>
                                    </span>
                                <?php
else: ?>
                                    <span class="text-muted">—</span>
                                <?php
endif; ?>
                            </td>
                            <td class="pe-4">
                                <code class="text-muted small"><?= htmlspecialchars($log['ip_address']) ?></code>
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

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
