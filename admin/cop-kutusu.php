<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);
$db = Database::getInstance();
$isSuperAdmin = ($_SESSION['admin_role'] ?? '') === 'superadmin';

$action = $_GET['action'] ?? '';
$module = $_GET['module'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$successMsg = '';
$errorMsg = '';

$validModules = [
    'businesses' => ['table' => 'businesses', 'title' => 'İşletmeler', 'name_col' => 'name'],
    'business_applications' => ['table' => 'business_applications', 'title' => 'İşletme Başvuruları', 'name_col' => 'business_name'],
    'events' => ['table' => 'events', 'title' => 'Etkinlikler', 'name_col' => 'title'],
    'event_submissions' => ['table' => 'event_submissions', 'title' => 'Etkinlik Başvuruları', 'name_col' => 'title'],
    'influencers' => ['table' => 'influencers', 'title' => 'Influencerlar', 'name_col' => 'name'],
    'influencer_applications' => ['table' => 'influencer_applications', 'title' => 'Influencer Başvuru.', 'name_col' => 'name'],
    'users' => ['table' => 'users', 'title' => 'Kullanıcılar', 'name_col' => 'name'],
    'business_users' => ['table' => 'business_users', 'title' => 'İşletme Sahipleri', 'name_col' => 'username'],
    'blogs' => ['table' => 'blogs', 'title' => 'Blog Yazıları', 'name_col' => 'title']
];

// Handle Actions (Restore / Hard Delete)
if (($action === 'restore' || $action === 'hard_delete') && $id > 0 && array_key_exists($module, $validModules)) {
    $table = $validModules[$module]['table'];
    try {
        if ($action === 'restore') {
            $stmt = $db->getPDO()->prepare("UPDATE `$table` SET is_deleted = 0 WHERE id = ?");
            $stmt->execute([$id]);
            if (function_exists('logAction')) logAction('restore', $table, 'Geri Yüklendi ID: ' . $id, $id);
            $successMsg = 'Kayıt başarıyla geri yüklendi.';
        } elseif ($action === 'hard_delete') {
            if (!$isSuperAdmin) {
                throw new Exception('Kalıcı silme işlemi için Superadmin yetkisi gereklidir.');
            }
            $stmt = $db->getPDO()->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
            if (function_exists('logAction')) logAction('delete', $table, 'Kalıcı Silindi ID: ' . $id, $id);
            $successMsg = 'Kayıt kalıcı olarak veritabanından silindi.';
        }
    } catch (Exception $e) {
        $errorMsg = 'İşlem başarısız: ' . $e->getMessage();
    }
}

$activeTab = $_GET['tab'] ?? 'businesses';
if (!array_key_exists($activeTab, $validModules)) {
    $activeTab = 'businesses';
}

// Filters
$searchQuery = trim($_GET['q'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// Fetch deleted items for the active tab
$deletedItems = [];
$totalRecords = 0;
$totalPages = 1;

try {
    $activeTable = $validModules[$activeTab]['table'];
    $nameField = $validModules[$activeTab]['name_col'];
    
    $where = ["is_deleted = 1"];
    $params = [];
    
    if ($searchQuery !== '') {
        $where[] = "`$nameField` LIKE :q";
        $params[':q'] = "%$searchQuery%";
    }
    
    if ($dateFilter !== '') {
        // Check if created_at exists to prevent errors
        $hasCreatedAt = $db->getPDO()->query("SHOW COLUMNS FROM `$activeTable` LIKE 'created_at'")->rowCount() > 0;
        if ($hasCreatedAt) {
            $where[] = "DATE(created_at) = :date";
            $params[':date'] = $dateFilter;
        }
    }
    
    $whereStr = implode(' AND ', $where);
    
    // Pagination count
    $stmtCount = $db->getPDO()->prepare("SELECT COUNT(*) FROM `$activeTable` WHERE $whereStr");
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    
    // Fetch data
    $stmt = $db->getPDO()->prepare("SELECT * FROM `$activeTable` WHERE $whereStr ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $deletedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMsg = "Veriler yüklenemedi: " . $e->getMessage();
}

$pageTitle = 'Çöp Kutusu (Silinenler)';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">
        <i class="fa-solid fa-trash-can text-danger me-2"></i> Çöp Kutusu
    </h2>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-3 pb-0 border-bottom-0">
        <ul class="nav nav-tabs border-bottom" role="tablist">
            <?php foreach ($validModules as $modKey => $modData): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $activeTab === $modKey ? 'active fw-bold text-danger' : 'text-muted' ?>" href="cop-kutusu.php?tab=<?= $modKey ?>">
                        <?= $modData['title'] ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="cop-kutusu.php" class="mb-4">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Kayıt Adı / Başlık ara..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i> Filtrele</button>
                </div>
            </div>
        </form>
        <?php if (empty($deletedItems)): ?>
            <div class="text-center py-5">
                <div class="display-1 text-muted mb-3"><i class="fa-regular fa-folder-open"></i></div>
                <h5 class="text-muted">Bu modülde silinmiş kayıt bulunmuyor.</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th>Kayıt Adı / Başlık</th>
                            <th width="250" class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletedItems as $item): 
                            $nameField = $validModules[$activeTab]['name_col'];
                            $displayName = $item[$nameField] ?? 'İsimsiz Kayıt';
                        ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= $item['id'] ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($displayName) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="cop-kutusu.php?tab=<?= $activeTab ?>&module=<?= $activeTab ?>&action=restore&id=<?= $item['id'] ?>" class="btn btn-sm btn-success shadow-sm text-nowrap" onclick="return confirm('Bu kaydı geri yüklemek istediğinize emin misiniz?');">
                                            <i class="fa-solid fa-arrow-rotate-left"></i> Geri Yükle
                                        </a>
                                        
                                        <?php if ($isSuperAdmin): ?>
                                            <a href="cop-kutusu.php?tab=<?= $activeTab ?>&module=<?= $activeTab ?>&action=hard_delete&id=<?= $item['id'] ?>" class="btn btn-sm btn-danger shadow-sm text-nowrap" onclick="return confirm('DİKKAT! Bu kayıt kalıcı olarak veritabanından silinecektir ve geri dönüşü yoktur. Emin misiniz?');">
                                                <i class="fa-solid fa-trash"></i> Kalıcı Sil
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary shadow-sm text-nowrap" disabled title="Sadece Süper Admin kalıcı silebilir">
                                                <i class="fa-solid fa-trash"></i> Kalıcı Sil
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=<?= $activeTab ?>&q=<?= urlencode($searchQuery) ?>&date=<?= urlencode($dateFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
