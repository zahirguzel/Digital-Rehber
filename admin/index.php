<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/influencer-helpers.php';
require_once '../includes/event-helpers.php';

$pageTitle = 'Genel Bakış';

$unreadMessages = 0;
$pendingInfluencerRequests = 0;
$pendingEventSubmissions = 0;
$pendingCampaignRequests = 0;
$recentMessages = [];
$recentInfluencerItems = [];
$recentEventSubmissions = [];

// Fetch Statistics
try {
    $totalBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE is_deleted = 0")->fetchColumn();
    $premiumBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE is_premium = 1 AND is_deleted = 0")->fetchColumn();
    $totalCategories = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $activeAds = $db->query("SELECT COUNT(*) FROM advertisements WHERE active = 1")->fetchColumn();

    $unreadMessages = (int) $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    $pendingInfluencerRequests = getInfluencerPendingRequestsCount($db->getPDO());
    $pendingEventSubmissions = getEventPendingSubmissionsCount($db->getPDO());
    $pendingCampaignRequests = (int) $db->query("SELECT COUNT(*) FROM campaigns WHERE is_published = 0")->fetchColumn();

    $stmtRecent = $db->query("SELECT b.*, c.name as category_name FROM businesses b LEFT JOIN categories c ON b.category_id = c.id WHERE b.is_deleted = 0 ORDER BY b.id DESC LIMIT 5");
    $recentBusinesses = $stmtRecent->fetchAll();

    $recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC LIMIT 5")->fetchAll();

    $influencerFeed = [];

    $pendingApps = $db->query("SELECT id, name, email, district, created_at FROM influencer_applications WHERE status = 'pending' AND is_deleted = 0 ORDER BY created_at DESC LIMIT 4")->fetchAll();
    foreach ($pendingApps as $row) {
        $influencerFeed[] = [
            'type' => 'application',
            'id' => $row['id'],
            'title' => $row['name'],
            'subtitle' => $row['email'] . ' · ' . $row['district'],
            'created_at' => $row['created_at'],
            'tab' => 'applications',
        ];
    }

    $unreadCollabs = $db->query("SELECT c.id, c.contact_name, c.business_name, c.created_at, i.name AS influencer_name FROM influencer_collaboration_requests c JOIN influencers i ON i.id = c.influencer_id WHERE c.is_read = 0 ORDER BY c.created_at DESC LIMIT 4")->fetchAll();
    foreach ($unreadCollabs as $row) {
        $influencerFeed[] = [
            'type' => 'collab',
            'id' => $row['id'],
            'title' => $row['influencer_name'],
            'subtitle' => $row['business_name'] . ' · ' . $row['contact_name'],
            'created_at' => $row['created_at'],
            'tab' => 'collabs',
        ];
    }

    $pendingRemovals = $db->query("SELECT id, profile_name, email, request_type, created_at FROM influencer_removal_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 4")->fetchAll();
    foreach ($pendingRemovals as $row) {
        $influencerFeed[] = [
            'type' => 'removal',
            'id' => $row['id'],
            'title' => $row['profile_name'],
            'subtitle' => $row['email'] . ' · ' . getInfluencerRemovalRequestTypeLabel($row['request_type']),
            'created_at' => $row['created_at'],
            'tab' => 'removals',
        ];
    }

    usort($influencerFeed, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });
    $recentInfluencerItems = array_slice($influencerFeed, 0, 5);

    $recentEventSubmissions = $db->query("SELECT id, title, contact_name, district, start_date, created_at FROM event_submissions WHERE status = 'pending' AND is_deleted = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    die("İstatistik yükleme hatası: " . $e->getMessage());
}

function dashboardInfluencerTypeLabel($type) {
    $labels = [
        'application' => 'Profil Başvurusu',
        'collab' => 'İş Birliği',
        'removal' => 'KVKK Talebi',
    ];
    return isset($labels[$type]) ? $labels[$type] : $type;
}

include 'includes/header.php';
?>

<!-- Statistics Overview Cards -->
<div class="row g-4 mb-5">
    <!-- Total Businesses -->
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--navy) !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Toplam Esnaf</span>
                    <h3 class="fw-bold mb-0 text-navy"><?= $totalBusinesses ?></h3>
                </div>
                <div class="bg-navy bg-opacity-10 text-navy p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-shop fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Premium Businesses -->
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid var(--primary) !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Premium Esnaf</span>
                    <h3 class="fw-bold mb-0 text-primary"><?= $premiumBusinesses ?></h3>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-crown fs-4 text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Categories -->
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #4F5D44 !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Kategori Sayısı</span>
                    <h3 class="fw-bold mb-0" style="color: #4F5D44;"><?= $totalCategories ?></h3>
                </div>
                <div class="p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center; background-color: rgba(79,93,68,0.1); color: #4F5D44;">
                    <i class="fa-solid fa-tags fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Ads -->
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #4285F4 !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Aktif Reklamlar</span>
                    <h3 class="fw-bold mb-0 text-primary-emphasis"><?= $activeAds ?></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-rectangle-ad fs-4 text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages & Influencer Requests -->
<div class="row g-4 mb-5">
    <div class="col-xl-3 col-sm-6">
        <a href="messages.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm dashboard-inbox-card <?= $unreadMessages > 0 ? 'dashboard-inbox-card--alert-messages' : '' ?>" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Okunmamış Mesaj</span>
                        <h3 class="fw-bold mb-0 text-success"><?= $unreadMessages ?></h3>
                        <small class="text-muted">İletişim formu</small>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-envelope fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="influencer-talepler.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm dashboard-inbox-card <?= $pendingInfluencerRequests > 0 ? 'dashboard-inbox-card--alert-influencer' : '' ?>" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Influencer Talebi</span>
                        <h3 class="fw-bold mb-0" style="color: #d97706;"><?= $pendingInfluencerRequests ?></h3>
                        <small class="text-muted">Başvuru · iş birliği · KVKK</small>
                    </div>
                    <div class="p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center; background: rgba(245,158,11,0.12); color: #d97706;">
                        <i class="fa-solid fa-star fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="event-talepler.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm dashboard-inbox-card <?= $pendingEventSubmissions > 0 ? 'dashboard-inbox-card--alert-event' : '' ?>" style="border-left: 4px solid #0ea5e9 !important;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Etkinlik Başvurusu</span>
                        <h3 class="fw-bold mb-0 text-info"><?= $pendingEventSubmissions ?></h3>
                        <small class="text-muted">Yayınlama talebi</small>
                    </div>
                    <div class="p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center; background: rgba(14,165,233,0.12); color: #0284c7;">
                        <i class="fa-solid fa-calendar-plus fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-sm-6">
        <a href="campaigns.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm dashboard-inbox-card <?= $pendingCampaignRequests > 0 ? 'dashboard-inbox-card--alert-influencer' : '' ?>" style="border-left: 4px solid #10B981 !important;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted text-uppercase fw-semibold small d-block mb-1">Kampanya Talebi</span>
                        <h3 class="fw-bold mb-0" style="color: #059669;"><?= $pendingCampaignRequests ?></h3>
                        <small class="text-muted">Onay bekleyen kampanya</small>
                    </div>
                    <div class="p-3 rounded" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center; background: rgba(16,185,129,0.12); color: #059669;">
                        <i class="fa-solid fa-tags fs-4"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-envelope-open-text me-2 text-success"></i> Son İletişim Mesajları</h5>
                <a href="messages.php" class="btn btn-sm btn-outline-secondary">Tümü</a>
            </div>
            <div class="card-body p-0">
                <?php
require_once '../autoload.php';
if (empty($recentMessages)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-envelope fs-2 d-block mb-2 opacity-50"></i>
                    Henüz iletişim mesajı yok.
                </div>
                <?php
require_once '../autoload.php';
else: ?>
                <div class="list-group list-group-flush">
                    <?php
require_once '../autoload.php';
foreach ($recentMessages as $msg): ?>
                    <a href="messages.php#row-<?= (int) $msg['id'] ?>" class="list-group-item list-group-item-action py-3 px-4 <?= !$msg['is_read'] ? 'dashboard-inbox-item--message' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <?php
require_once '../autoload.php';
if (!$msg['is_read']): ?><span class="badge bg-success">Yeni</span><?php
require_once '../autoload.php';
endif; ?>
                                    <strong class="text-navy text-truncate"><?= htmlspecialchars($msg['name']) ?></strong>
                                </div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($msg['subject'] ?: 'Konu belirtilmedi') ?></div>
                            </div>
                            <small class="text-muted flex-shrink-0"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></small>
                        </div>
                    </a>
                    <?php
require_once '../autoload.php';
endforeach; ?>
                </div>
                <?php
require_once '../autoload.php';
endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-inbox me-2 text-warning"></i> Influencer Talepleri</h5>
                <a href="influencer-talepler.php" class="btn btn-sm btn-outline-secondary">Tümü</a>
            </div>
            <div class="card-body p-0">
                <?php
require_once '../autoload.php';
if (empty($recentInfluencerItems)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-circle-check fs-2 d-block mb-2 opacity-50"></i>
                    Bekleyen influencer talebi yok.
                </div>
                <?php
require_once '../autoload.php';
else: ?>
                <div class="list-group list-group-flush">
                    <?php
require_once '../autoload.php';
foreach ($recentInfluencerItems as $item): ?>
                    <a href="influencer-talepler.php?tab=<?= htmlspecialchars($item['tab']) ?>" class="list-group-item list-group-item-action py-3 px-4 dashboard-inbox-item--influencer">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="badge bg-warning text-dark"><?= htmlspecialchars(dashboardInfluencerTypeLabel($item['type'])) ?></span>
                                    <strong class="text-navy text-truncate"><?= htmlspecialchars($item['title']) ?></strong>
                                </div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($item['subtitle']) ?></div>
                            </div>
                            <small class="text-muted flex-shrink-0"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></small>
                        </div>
                    </a>
                    <?php
require_once '../autoload.php';
endforeach; ?>
                </div>
                <?php
require_once '../autoload.php';
endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-calendar-plus me-2 text-info"></i> Etkinlik Başvuruları</h5>
                <a href="event-talepler.php" class="btn btn-sm btn-outline-secondary">Tümü</a>
            </div>
            <div class="card-body p-0">
                <?php
require_once '../autoload.php';
if (empty($recentEventSubmissions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-regular fa-calendar fs-2 d-block mb-2 opacity-50"></i>
                    Bekleyen etkinlik başvurusu yok.
                </div>
                <?php
require_once '../autoload.php';
else: ?>
                <div class="list-group list-group-flush">
                    <?php
require_once '../autoload.php';
foreach ($recentEventSubmissions as $sub): ?>
                    <a href="event-talepler.php#row-<?= (int) $sub['id'] ?>" class="list-group-item list-group-item-action py-3 px-4 dashboard-inbox-item--event">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="badge bg-info text-dark">Yeni</span>
                                    <strong class="text-navy text-truncate"><?= htmlspecialchars($sub['title']) ?></strong>
                                </div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($sub['contact_name']) ?> · <?= htmlspecialchars($sub['district']) ?></div>
                            </div>
                            <small class="text-muted flex-shrink-0"><?= date('d.m.Y', strtotime($sub['start_date'])) ?></small>
                        </div>
                    </a>
                    <?php
require_once '../autoload.php';
endforeach; ?>
                </div>
                <?php
require_once '../autoload.php';
endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Panel -->
<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="card border-0 shadow-sm p-4">
            <h5 class="fw-bold mb-3 text-navy"><i class="fa-solid fa-bolt me-2 text-primary"></i> Hızlı İşlemler</h5>
            <div class="d-flex flex-wrap gap-3">
                <a href="businesses.php?action=new" class="btn btn-primary px-4 py-2"><i class="fa-solid fa-plus me-1"></i> Yeni Esnaf Ekle</a>
                <a href="messages.php" class="btn btn-outline-success px-4 py-2"><i class="fa-solid fa-envelope me-1"></i> Gelen Mesajlar<?php
require_once '../autoload.php';
if ($unreadMessages > 0): ?> <span class="badge bg-success ms-1"><?= $unreadMessages ?></span><?php
require_once '../autoload.php';
endif; ?></a>
                <a href="influencer-talepler.php" class="btn btn-outline-warning px-4 py-2"><i class="fa-solid fa-star me-1"></i> Influencer Talepleri<?php
require_once '../autoload.php';
if ($pendingInfluencerRequests > 0): ?> <span class="badge bg-warning text-dark ms-1"><?= $pendingInfluencerRequests ?></span><?php
require_once '../autoload.php';
endif; ?></a>
                <a href="event-talepler.php" class="btn btn-outline-info px-4 py-2"><i class="fa-solid fa-calendar-plus me-1"></i> Etkinlik Başvuruları<?php
require_once '../autoload.php';
if ($pendingEventSubmissions > 0): ?> <span class="badge bg-info text-dark ms-1"><?= $pendingEventSubmissions ?></span><?php
require_once '../autoload.php';
endif; ?></a>
                <a href="categories.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-tag me-1"></i> Sektör / Kategori Ekle</a>
                <a href="ads.php" class="btn btn-outline-secondary px-4 py-2"><i class="fa-solid fa-ad me-1"></i> Reklam Alanı Ekle</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Added Businesses -->
<div class="row">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Son Eklenen İşletmeler</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Esnaf Adı</th>
                                <th>Kategori</th>
                                <th>İlçe</th>
                                <th>Premium Durumu</th>
                                <th class="text-end pe-4">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
require_once '../autoload.php';
if (empty($recentBusinesses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Kayıtlı esnaf bulunmuyor.</td>
                                </tr>
                            <?php
require_once '../autoload.php';
else: ?>
                                <?php
require_once '../autoload.php';
foreach ($recentBusinesses as $biz): ?>
                                    <tr>
                                        <td class="ps-4 py-3 fw-bold text-navy"><?= htmlspecialchars($biz['name']) ?></td>
                                        <td><?= htmlspecialchars($biz['category_name']) ?></td>
                                        <td><i class="fa-solid fa-location-dot text-primary me-2"></i><?= htmlspecialchars($biz['district']) ?></td>
                                        <td>
                                            <?php
require_once '../autoload.php';
if ($biz['is_premium']): ?>
                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i> Premium</span>
                                            <?php
require_once '../autoload.php';
else: ?>
                                                <span class="badge bg-secondary">Standart</span>
                                            <?php
require_once '../autoload.php';
endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="businesses.php?action=edit&id=<?= $biz['id'] ?>" class="btn btn-outline-secondary btn-sm px-3">
                                                <i class="fa-solid fa-pen-to-square"></i> Düzenle
                                            </a>
                                        </td>
                                    </tr>
                                <?php
require_once '../autoload.php';
endforeach; ?>
                            <?php
require_once '../autoload.php';
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
