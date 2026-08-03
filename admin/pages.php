<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);

$db = Database::getInstance();

$successMsg = '';
$errorMsg = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmtName = $db->getPDO()->prepare("SELECT title FROM pages WHERE id = ?");
        $stmtName->execute([$id]);
        $delTitle = $stmtName->fetchColumn() ?: 'Sayfa ID: ' . $id;
        
        $db->getPDO()->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
        if (function_exists('logAction')) logAction('delete', 'pages', $delTitle, $id);
        $successMsg = 'Sayfa başarıyla silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Hata: ' . $e->getMessage();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    
    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $meta_description = trim($_POST['meta_description'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    if (empty($title) || empty($slug)) {
        $errorMsg = 'Başlık ve URL kısımları zorunludur.';
    } else {
        try {
            if (!empty($id)) {
                // Update
                $stmt = $db->getPDO()->prepare("UPDATE pages SET title=?, slug=?, content=?, meta_description=?, is_published=? WHERE id=?");
                $stmt->execute([$title, $slug, $content, $meta_description, $is_published, $id]);
                if (function_exists('logAction')) logAction('update', 'pages', $title, $id);
                $successMsg = 'Sayfa başarıyla güncellendi.';
            } else {
                // Insert
                $stmt = $db->getPDO()->prepare("INSERT INTO pages (title, slug, content, meta_description, is_published) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $content, $meta_description, $is_published]);
                $newId = $db->getPDO()->lastInsertId();
                if (function_exists('logAction')) logAction('create', 'pages', $title, $newId);
                $successMsg = 'Yeni sayfa eklendi.';
            }
        } catch (Exception $e) {
            $errorMsg = 'Kayıt sırasında hata: ' . $e->getMessage();
        }
    }
}

// Fetch all pages
$pages = [];
try {
    $pages = $db->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll();
} catch (Exception $e) {}

// Edit Mode
$editPage = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $db->getPDO()->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$id]);
        $editPage = $stmt->fetch();
    } catch (Exception $e) {}
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-file-lines me-2 text-primary"></i> Kurumsal Sayfalar</h2>
    <?php if (isset($_GET['edit']) || isset($_GET['add'])): ?>
        <a href="pages.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-2"></i> Geri Dön</a>
    <?php else: ?>
        <a href="pages.php?add=1" class="btn btn-primary shadow-sm"><i class="fa-solid fa-plus me-2"></i> Yeni Sayfa Ekle</a>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-check-circle me-2"></i><?= SecurityHelper::escape($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= SecurityHelper::escape($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['edit']) || isset($_GET['add'])): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $editPage ? 'Sayfayı Düzenle' : 'Yeni Sayfa Ekle' ?></h6>
        </div>
        <div class="card-body p-4">
            <form action="pages.php" method="POST">
                <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="id" value="<?= $editPage['id'] ?? '' ?>">
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Sayfa Başlığı</label>
                        <input type="text" name="title" class="form-control" id="pageTitle" required value="<?= htmlspecialchars($editPage['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">URL (Slug)</label>
                        <input type="text" name="slug" class="form-control" id="pageSlug" required value="<?= htmlspecialchars($editPage['slug'] ?? '') ?>">
                        <small class="text-muted">Örn: vizyon-misyon, hakkimizda, kisisel-veriler vb.</small>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold">SEO Meta Açıklaması</label>
                        <textarea name="meta_description" class="form-control" rows="2" maxlength="160"><?= htmlspecialchars($editPage['meta_description'] ?? '') ?></textarea>
                        <small class="text-muted">Google arama sonuçlarında çıkacak kısa açıklama.</small>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold">Sayfa İçeriği</label>
                        <textarea name="content" id="editor" class="form-control" rows="15"><?= htmlspecialchars($editPage['content'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_published" id="isPublished" value="1" <?= (!isset($editPage) || $editPage['is_published'] == 1) ? 'checked' : '' ?> style="transform: scale(1.2);">
                            <label class="form-check-label ms-2 fw-semibold" for="isPublished" style="cursor: pointer;">Sayfayı Yayına Al</label>
                        </div>
                    </div>
                    
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success px-4 py-2"><i class="fa-solid fa-save me-2"></i> Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- TinyMCE Script -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#editor',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            toolbar_mode: 'floating',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image',
            height: 400,
            promotion: false,
            language: 'tr'
        });
        
        // Auto slug generator
        document.getElementById('pageTitle').addEventListener('input', function() {
            if (!document.getElementById('pageSlug').value || '<?= !isset($editPage) ? "1" : "" ?>') {
                let text = this.value.toLowerCase();
                let trMap = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u',' ':'-'};
                let slug = text.replace(/[çğıöşü ]/g, function(m) { return trMap[m]; })
                               .replace(/[^a-z0-9-]/g, '')
                               .replace(/-+/g, '-')
                               .replace(/^-|-$/g, '');
                if('<?= !isset($editPage) ? "1" : "" ?>') {
                    document.getElementById('pageSlug').value = slug;
                }
            }
        });
    </script>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Sayfa Başlığı</th>
                            <th>URL / Kısayol</th>
                            <th>Durum</th>
                            <th>Son Güncelleme</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pages)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Henüz hiç sayfa eklenmemiş.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pages as $p): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark"><?= SecurityHelper::escape($p['title']) ?></td>
                                    <td><a href="/<?= SecurityHelper::escape($p['slug']) ?>" target="_blank" class="text-decoration-none"><i class="fa-solid fa-link me-1"></i>/<?= SecurityHelper::escape($p['slug']) ?></a></td>
                                    <td>
                                        <?php if ($p['is_published']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Yayında</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1"><i class="fa-solid fa-eye-slash me-1"></i> Taslak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($p['updated_at'])) ?></td>
                                    <td class="text-end pe-4">
                                        <a href="pages.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i> Düzenle</a>
                                        <a href="pages.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Bu sayfayı silmek istediğinize emin misiniz?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
