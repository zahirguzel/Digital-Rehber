<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$successMsg = '';
$errorMsg = '';

// ----------------------------------------------------
// PROCESS ACTIONS (Insert, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'edit')) {
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $icon = trim($_POST['icon']) ?: 'fa-store';
        
        if (empty($name) || empty($slug)) {
            $errorMsg = "Kategori adı ve slug alanları zorunludur.";
        } else {
            try {
                // Check if slug is unique
                $stmtCheck = $db->query("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?", [$slug, $id]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $errorMsg = "Bu URL (slug) başka bir kategori tarafından kullanılıyor.";
                } else {
                    if ($_POST['action'] === 'add') {
                        $stmtIns = $db->query("INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)", [$name, $slug, $icon]);
                        $successMsg = "Kategori başarıyla eklendi.";
                        $action = 'list';
                    } else {
                        $stmtUp = $db->query("UPDATE categories SET name = ?, slug = ?, icon = ? WHERE id = ?", [$name, $slug, $icon, $id]);
                        $successMsg = "Kategori başarıyla güncellendi.";
                        $action = 'list';
                    }
                }
            } catch (Exception $e) {
                $errorMsg = "Hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Delete Category
if ($action === 'delete' && $id > 0) {
    try {
        $stmtDel = $db->query("DELETE FROM categories WHERE id = ?", [$id]);
        $successMsg = "Kategori başarıyla silindi.";
    } catch (Exception $e) {
        $errorMsg = "Kategori silinemedi (Bu kategoriye bağlı işletmeler olabilir): " . $e->getMessage();
    }
    $action = 'list';
}

// ----------------------------------------------------
// FETCH VIEW DATA
// ----------------------------------------------------
$category = null;
if ($action === 'edit' && $id > 0) {
    try {
        $stmtCat = $db->query("SELECT * FROM categories WHERE id = ?", [$id]);
        $category = $stmtCat->fetch();
        if (!$category) {
            $errorMsg = "Düzenlenecek kategori bulunamadı.";
            $action = 'list';
        }
    } catch (Exception $e) {
        $errorMsg = "Kategori yükleme hatası.";
        $action = 'list';
    }
}

$categories = [];
if ($action === 'list') {
    try {
        $categories = $db->query("SELECT c.*, COUNT(b.id) as business_count FROM categories c LEFT JOIN businesses b ON c.id = b.category_id GROUP BY c.id ORDER BY c.name ASC")->fetchAll();
    } catch (Exception $e) {
        $errorMsg = "Kategori listesi yüklenemedi: " . $e->getMessage();
    }
}

$pageTitle = 'Kategori Yönetimi';
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

<!-- LIST VIEW -->
<?php
if ($action === 'list'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-tags me-2 text-primary"></i> Sektörler & Kategoriler</h5>
            <a href="categories.php?action=new" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-plus me-1"></i> Yeni Kategori Ekle</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Kategori İkonu</th>
                            <th>Kategori Adı</th>
                            <th>URL Slug</th>
                            <th>Kayıtlı Esnaf</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Henüz kategori eklenmemiş.</td>
                            </tr>
                        <?php
else: ?>
                            <?php
foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="bg-navy bg-opacity-10 text-navy rounded d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 20px;">
                                            <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-tag') ?>"></i>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-navy"><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="font-monospace text-muted small"><?= htmlspecialchars($cat['slug']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary px-3 py-2 rounded-1"><?= $cat['business_count'] ?> Esnaf</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group gap-2">
                                            <a href="categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                            <a href="categories.php?action=delete&id=<?= $cat['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu kategoriyi silmek istediğinizden emin misiniz? (Bu kategoriye bağlı esnaflar silinmeyecek fakat kategori bağları kopacaktır)" data-confirm-title="Kategoriyi Sil"><i class="fa-solid fa-trash"></i></a>
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

<!-- ADD NEW OR EDIT VIEW -->
<?php
elseif ($action === 'new' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid <?= $action === 'new' ? 'fa-plus text-success' : 'fa-pen text-primary' ?> me-2"></i>
                <?= $action === 'new' ? 'Yeni Kategori Ekle' : htmlspecialchars($category['name']) . ' - Düzenle' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'new' ? 'add' : 'edit' ?>">
                
                <div class="row g-4">
                    <!-- Category Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="cat_name" class="form-control" required value="<?= htmlspecialchars($category['name'] ?? '') ?>" placeholder="Örn: Restoran & Kafe">
                    </div>
                    
                    <!-- Slug URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Temiz URL / Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="cat_slug" class="form-control font-monospace" required value="<?= htmlspecialchars($category['slug'] ?? '') ?>" placeholder="örn-sektor-adi">
                        <span class="text-muted small" style="font-size: 11px;">Kategori filtrelemelerinde URL'de görünecek değer.</span>
                    </div>

                    <!-- Icon FontAwesome Class -->
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">FontAwesome İkon Sınıfı</label>
                        <input type="text" name="icon" class="form-control font-monospace" value="<?= htmlspecialchars($category['icon'] ?? '') ?>" placeholder="Örn: fa-utensils">
                        <span class="text-muted small" style="font-size: 11px;">Sektörü simgeleyen ikon kodu. Örnek: `fa-utensils` (Gıda), `fa-car` (Otomotiv), `fa-shirt` (Giyim).</span>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="mt-5 border-top pt-4 d-flex justify-content-end gap-2">
                    <a href="categories.php" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i> İptal</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS helper to auto-generate slugs -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('cat_name');
            const slugInput = document.getElementById('cat_slug');
            
            <?php
if ($action === 'new'): ?>
            nameInput.addEventListener('input', function() {
                let text = nameInput.value;
                slugInput.value = text.toLowerCase()
                                    .replace(/ğ/g, 'g')
                                    .replace(/ü/g, 'u')
                                    .replace(/ş/g, 's')
                                    .replace(/ı/g, 'i')
                                    .replace(/ö/g, 'o')
                                    .replace(/ç/g, 'c')
                                    .replace(/[^a-z0-9]/g, '-')
                                    .replace(/-+/g, '-')
                                    .replace(/^-|-$/g, '');
            });
            <?php
endif; ?>
        });
    </script>
<?php
endif; ?>

</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
