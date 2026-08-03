<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

$db = Database::getInstance();
$bizId = (int) ($_SESSION['biz_id'] ?? 0);
$bizName = $_SESSION['biz_name'] ?? 'İşletme';
$bizSlug = $_SESSION['biz_slug'] ?? '';

// Mark all as read when page is visited
try {
    $db->query("UPDATE business_notifications SET is_read = 1 WHERE business_id = ? AND is_read = 0", [$bizId]);
} catch (Exception $e) {}

// Clear specific notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $db->query("DELETE FROM business_notifications WHERE id = ? AND business_id = ?", [$delId, $bizId]);
        header("Location: notifications.php");
        exit;
    } catch (Exception $e) {}
}

// Clear all notifications
if (isset($_GET['clear_all'])) {
    try {
        $db->query("DELETE FROM business_notifications WHERE business_id = ?", [$bizId]);
        header("Location: notifications.php");
        exit;
    } catch (Exception $e) {}
}

// Fetch notifications
$notifications = [];
try {
    $notifications = $db->query("SELECT * FROM business_notifications WHERE business_id = ? ORDER BY created_at DESC LIMIT 100", [$bizId])->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Bildirimler';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-navy">
        <i class="fa-regular fa-bell me-2 text-primary"></i> Bildirimler
    </h3>
    <?php if (!empty($notifications)): ?>
        <a href="notifications.php?clear_all=1" class="btn btn-outline-danger btn-sm px-3" onclick="return confirm('Tüm bildirimleri silmek istediğinize emin misiniz?');">
            <i class="fa-solid fa-trash-can me-1"></i> Tümünü Sil
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-regular fa-bell-slash fs-1 d-block mb-3 opacity-25"></i>
                <p class="mb-0 fw-semibold">Henüz hiç bildiriminiz bulunmuyor.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush" style="border-radius:12px;">
                <?php foreach ($notifications as $notif): 
                    $icon = 'fa-circle-info text-info';
                    $bg = '';
                    if ($notif['type'] === 'success') { $icon = 'fa-check-circle text-success'; $bg = 'bg-success bg-opacity-10'; }
                    elseif ($notif['type'] === 'warning') { $icon = 'fa-triangle-exclamation text-warning'; $bg = 'bg-warning bg-opacity-10'; }
                    elseif ($notif['type'] === 'error') { $icon = 'fa-circle-xmark text-danger'; $bg = 'bg-danger bg-opacity-10'; }
                ?>
                <div class="list-group-item list-group-item-action d-flex gap-3 py-3 align-items-start <?= $bg ?>" style="border-left: 4px solid transparent; <?= $notif['type'] === 'error' ? 'border-left-color: var(--bs-danger);' : ($notif['type'] === 'success' ? 'border-left-color: var(--bs-success);' : '') ?>">
                    <i class="fa-solid <?= $icon ?> fs-4 mt-1"></i>
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold <?= $notif['type'] === 'error' ? 'text-danger' : 'text-dark' ?>">
                                <?= htmlspecialchars($notif['title']) ?>
                            </h6>
                            <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?></small>
                        </div>
                        <p class="mb-0 text-secondary" style="font-size: 0.95rem;">
                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                        </p>
                    </div>
                    <a href="notifications.php?delete=<?= $notif['id'] ?>" class="text-muted ms-2 p-1 text-decoration-none" title="Sil">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
