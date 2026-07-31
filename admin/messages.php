<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$successMsg = '';
$errorMsg = '';

// 1. AJAX Handler to mark message as read in the background
if (isset($_GET['ajax_read'])) {
    header('Content-Type: application/json');
    $msgId = intval($_GET['ajax_read']);
    if ($msgId > 0) {
        try {
            $stmt = $db->query("UPDATE contact_messages SET is_read = 1 WHERE id = ?", [$msgId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

// 2. Delete Message Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $msgId = intval($_GET['id']);
    if ($msgId > 0) {
        try {
            $stmt = $db->query("DELETE FROM contact_messages WHERE id = ?", [$msgId]);
            $successMsg = "Mesaj başarıyla silindi.";
        } catch (Exception $e) {
            $errorMsg = "Mesaj silinirken hata oluştu: " . $e->getMessage();
        }
    }
}

// Fetch all messages
try {
    $stmtMsgs = $db->query("SELECT * FROM contact_messages ORDER BY id DESC");
    $messages = $stmtMsgs->fetchAll();
} catch (Exception $e) {
    die("Mesajlar yüklenemedi: " . $e->getMessage());
}

$pageTitle = 'Gelen Mesajlar';
include 'includes/header.php';
?>

<!-- Alerts -->
<?php
if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<?php
if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php
endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-inbox me-2 text-primary"></i> İletişim & Başvuru Kutusu</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 80px;">Durum</th>
                        <th>Gönderen</th>
                        <th>Konu</th>
                        <th>Tarih</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
if (empty($messages)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-envelope-open fs-2 d-block mb-3 opacity-30"></i>
                                Gelen kutunuz boş.
                            </td>
                        </tr>
                    <?php
else: ?>
                        <?php
foreach ($messages as $row): ?>
                            <tr id="row-<?= $row['id'] ?>" class="<?= !$row['is_read'] ? 'table-warning fw-bold' : 'text-muted' ?>" style="transition: all 0.3s ease;">
                                <!-- Read/Unread status badge -->
                                <td class="ps-4">
                                    <span id="badge-<?= $row['id'] ?>" class="badge <?= !$row['is_read'] ? 'bg-success text-white' : 'bg-light text-secondary border' ?>">
                                        <?= !$row['is_read'] ? 'YENİ' : 'OKUNDU' ?>
                                    </span>
                                </td>
                                
                                <!-- Sender details -->
                                <td>
                                    <div class="text-navy fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="small" style="font-size: 12px; opacity: 0.8;"><?= htmlspecialchars($row['email']) ?></div>
                                    <?php
if (!empty($row['phone'])): ?>
                                    <div class="small text-muted" style="font-size: 12px;"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($row['phone']) ?></div>
                                    <?php
endif; ?>
                                </td>
                                
                                <!-- Subject -->
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 300px;">
                                        <?= htmlspecialchars($row['subject']) ?>
                                    </span>
                                </td>
                                
                                <!-- Date -->
                                <td class="small" style="font-size: 13px;">
                                    <?= date('d.m.Y H:i', strtotime($row['created_at'])) ?>
                                </td>
                                
                                <!-- Action links -->
                                <td class="text-end pe-4">
                                    <div class="btn-group gap-2">
                                        <!-- View Detail Button with dynamic dataset -->
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-sm btn-view-message" 
                                                data-id="<?= $row['id'] ?>"
                                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                                data-email="<?= htmlspecialchars($row['email']) ?>"
                                                data-phone="<?= htmlspecialchars($row['phone'] ?? '') ?>"
                                                data-subject="<?= htmlspecialchars($row['subject']) ?>"
                                                data-date="<?= date('d.m.Y H:i', strtotime($row['created_at'])) ?>"
                                                data-message="<?= htmlspecialchars($row['message']) ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#messageModal">
                                            <i class="fa-solid fa-envelope-open-text me-1"></i> Oku
                                        </button>
                                        
                                        <!-- Delete Link -->
                                        <a href="messages.php?action=delete&id=<?= $row['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm confirm-btn"
                                           data-confirm="Bu mesajı kalıcı olarak silmek istediğinizden emin misiniz?"
                                           data-confirm-title="Mesajı Sil">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
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

<!-- SINGLE MESSAGE DETAIL MODAL -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy" id="messageModalLabel"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i> Mesaj Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="text-muted small d-block">Gönderen</span>
                    <strong id="modal-sender" class="text-navy"></strong> 
                    <span id="modal-email" class="text-muted small ms-1"></span>
                </div>
                
                <div class="mb-3">
                    <span class="text-muted small d-block">Telefon</span>
                    <strong id="modal-phone" class="text-navy"></strong>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Tarih</span>
                    <strong id="modal-date" style="font-size: 14px;"></strong>
                </div>
                
                <div class="mb-3">
                    <span class="text-muted small d-block">Konu</span>
                    <strong id="modal-subject" class="text-navy"></strong>
                </div>
                
                <hr>
                
                <div class="mb-0">
                    <span class="text-muted small d-block mb-1">Mesaj Detayı</span>
                    <div id="modal-text" class="p-3 bg-light rounded-1 text-muted border lh-lg" style="white-space: pre-wrap; font-size: 14px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.btn-view-message');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const subject = this.getAttribute('data-subject');
            const date = this.getAttribute('data-date');
            const message = this.getAttribute('data-message');
            
            // Populate Modal Content
            document.getElementById('modal-sender').textContent = name;
            document.getElementById('modal-email').textContent = '(' + email + ')';
            document.getElementById('modal-phone').textContent = phone || 'Belirtilmedi';
            document.getElementById('modal-date').textContent = date;
            document.getElementById('modal-subject').textContent = subject;
            document.getElementById('modal-text').textContent = message;
            
            // Mark as read asynchronously in the background
            const row = document.getElementById('row-' + id);
            if (row && row.classList.contains('table-warning')) {
                fetch('messages.php?ajax_read=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI row styles
                            row.classList.remove('table-warning', 'fw-bold');
                            row.classList.add('text-muted');
                            
                            // Update badge
                            const badge = document.getElementById('badge-' + id);
                            if (badge) {
                                badge.textContent = 'OKUNDU';
                                badge.classList.remove('bg-success');
                                badge.classList.add('bg-light', 'text-secondary', 'border');
                            }
                            
                            // Dynamically decrease sidebar count badge if present
                            const countBadge = document.getElementById('sidebar-unread-badge');
                            if (countBadge) {
                                let count = parseInt(countBadge.textContent);
                                if (count > 1) {
                                    countBadge.textContent = count - 1;
                                } else {
                                    countBadge.remove(); // Remove badge if no unread messages left
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error updating status:', err));
            }
        });
    });
});
</script>
</body>
</html>
