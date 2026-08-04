<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();
$pdo = $db->getPDO();

require_once '../includes/duty-pharmacy-helpers.php';

$pageTitle = 'Nöbetçi Eczane Yönetimi';
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_api_key'])) {
        $apiKey = trim($_POST['eczane_api_key'] ?? '');
        try {
            $db->getPDO()->prepare('UPDATE settings SET eczane_api_key = ? WHERE id = 1')->execute([$apiKey !== '' ? $apiKey : null]);
            $successMsg = 'EczaneAPI anahtarı kaydedildi.';
        } catch (Exception $e) {
            $errorMsg = 'API anahtarı kaydedilemedi. Migration dosyasını çalıştırdınız mı? ' . $e->getMessage();
        }
    }

    if (isset($_POST['sync_today']) || isset($_POST['sync_tomorrow'])) {
        $date = dutyPharmacyResolveDate(isset($_POST['sync_tomorrow']) ? 'tomorrow' : 'today');
        try {
            $result = dutyPharmacySync($pdo, $date);
            $successMsg = $result['date'] . ' tarihi için ' . $result['count'] . ' eczane senkronize edildi.';
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }

    if (isset($_POST['load_sample_pharmacies'])) {
        try {
            ob_start();
            require __DIR__ . '/../scripts/seed_duty_pharmacies.php';
            ob_end_clean();
            $successMsg = 'Bugün ve yarın için 7 adet örnek nöbetçi eczane verileri başarıyla yüklendi!';
        } catch (Exception $e) {
            $errorMsg = 'Örnek veriler yüklenirken hata oluştu: ' . $e->getMessage();
        }
    }
}

$settings = dutyPharmacyGetSettings($pdo);
$todayDate = dutyPharmacyResolveDate('today');
$tomorrowDate = dutyPharmacyResolveDate('tomorrow');
$todayCount = 0;
$tomorrowCount = 0;
$districtCounts = [];
$logs = [];

try {
    $stmtToday = $db->query('SELECT COUNT(*) FROM duty_pharmacies WHERE duty_date = ?', [$todayDate]);
    $todayCount = (int) $stmtToday->fetchColumn();
    $stmtTomorrow = $db->query('SELECT COUNT(*) FROM duty_pharmacies WHERE duty_date = ?', [$tomorrowDate]);
    $tomorrowCount = (int) $stmtTomorrow->fetchColumn();
    $districtCounts = dutyPharmacyGetDistrictCounts($pdo, $todayDate);
    $logs = dutyPharmacyGetRecentLogs($pdo, 8);
} catch (Exception $e) {
    if ($errorMsg === '') {
        $errorMsg = 'Veritabanı tabloları bulunamadı. migrations/add_duty_pharmacies_system.sql dosyasını çalıştırın.';
    }
}

include 'includes/header.php';
?>

<?php
if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<?php
if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-key me-2 text-primary"></i> EczaneAPI Ayarları</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">
                    Ücretsiz hesap: <a href="https://eczaneapi.com" target="_blank" rel="noopener">eczaneapi.com</a>.
                    Cron gerekmez — ziyaretçi sayfayı açınca veri otomatik yenilenir (en fazla 6 saatte bir API isteği).
                </p>
                <form method="POST" class="mb-4">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="save_api_key" value="1">
                    <label class="form-label fw-semibold">API Anahtarı (X-API-Key)</label>
                    <input type="text" name="eczane_api_key" class="form-control font-monospace" placeholder="eczane_api_..." value="<?= htmlspecialchars($settings['eczane_api_key'] ?? '') ?>">
                    <div class="form-text">Anahtar sunucuda saklanır; ziyaretçilere gösterilmez.</div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="fa-solid fa-floppy-disk me-2"></i>Kaydet</button>
                </form>

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST">
    <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="sync_today" value="1">
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-rotate me-2"></i>Bugünü Senkronize Et</button>
                    </form>
                    <form method="POST">
    <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="sync_tomorrow" value="1">
                        <button type="submit" class="btn btn-outline-success"><i class="fa-solid fa-calendar-plus me-2"></i>Yarını Senkronize Et</button>
                    </form>
                    <a href="../nobetci-eczane" target="_blank" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Canlı Sayfayı Aç</a>
                    <form method="POST" class="d-inline">
                        <?= CSRFMiddleware::field() ?>
                        <input type="hidden" name="load_sample_pharmacies" value="1">
                        <button type="submit" class="btn btn-outline-warning" title="API anahtarı olmadan test/demo nöbetçi eczanelerini yükle"><i class="fa-solid fa-vials me-2"></i>Örnek Eczane Yükle (API'siz)</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-chart-simple me-2 text-primary"></i> Özet</h6>
            </div>
            <div class="card-body p-4">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Aktif nöbet (<?= htmlspecialchars($todayDate) ?>):</strong> <?= $todayCount ?> eczane</li>
                    <li class="mb-2"><strong>Sonraki nöbet (<?= htmlspecialchars($tomorrowDate) ?>):</strong> <?= $tomorrowCount ?> eczane</li>
                    <li class="mb-2"><strong>Son senkron:</strong>
                        <?= !empty($settings['duty_pharmacy_last_sync']) ? date('d.m.Y H:i', strtotime($settings['duty_pharmacy_last_sync'])) : '—' ?>
                    </li>
                    <li><strong>Otomatik güncelleme:</strong> /nobetci-eczane ziyaretinde (6 saatte bir)</li>
                    <li><strong>Manuel:</strong> aşağıdaki senkron butonları</li>
                </ul>
            </div>
        </div>

        <?php
if (!empty($districtCounts)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy">Bugün İlçe Dağılımı</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>İlçe</th><th>Adet</th></tr></thead>
                        <tbody>
                        <?php
foreach ($districtCounts as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['district']) ?></td>
                                <td><?= (int) $row['cnt'] ?></td>
                            </tr>
                        <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header py-3">
                <h6 class="mb-0 fw-bold text-navy">Son Senkron Kayıtları</h6>
            </div>
            <div class="card-body p-0">
                <?php
if (empty($logs)): ?>
                    <p class="text-muted small p-3 mb-0">Henüz senkron kaydı yok.</p>
                <?php
else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Tarih</th><th>Durum</th><th>Adet</th></tr></thead>
                            <tbody>
                            <?php
foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['duty_date']) ?><br><small class="text-muted"><?= date('d.m H:i', strtotime($log['created_at'])) ?></small></td>
                                    <td><span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($log['status']) ?></span></td>
                                    <td><?= (int) $log['pharmacy_count'] ?></td>
                                </tr>
                            <?php
endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php'; ?>
