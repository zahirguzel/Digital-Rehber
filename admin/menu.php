<?php
require_once '../autoload.php';
ob_start();
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

require_once '../includes/menu-helpers.php';
require_once 'includes/logger.php';

$pageTitle   = 'Menü Yönetimi';
$successMsg  = '';
$errorMsg    = '';

// Build base URL (works both locally and on production)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain   = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('admin/menu.php', '', $_SERVER['PHP_SELF']), '/');
$baseUrl  = $protocol . $domain . $basePath;

// ── Current business selection ─────────────────────────────────
$bizId = intval($_GET['biz'] ?? 0);

// ── POST: save category ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_cat'])) {
    $catId   = intval($_POST['cat_id'] ?? 0);
    $catName = trim($_POST['cat_name'] ?? '');
    $catBiz  = intval($_POST['cat_biz'] ?? 0);
    $bizId   = $catBiz;
    if ($catName && $catBiz) {
        try {
            if ($catId > 0) {
                $db->getPDO()->prepare("UPDATE menu_categories SET name=? WHERE id=? AND business_id=?")
                    ->execute([$catName, $catId, $catBiz]);
                $successMsg = 'Kategori güncellendi.';
            } else {
                $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM menu_categories WHERE business_id=?", [$catBiz]);
                $db->getPDO()->prepare("INSERT INTO menu_categories (business_id, name, sort_order) VALUES (?,?,?)")
                    ->execute([$catBiz, $catName, $maxOrder->fetchColumn()]);
                $successMsg = 'Kategori eklendi.';
            }
            logAction('create', 'menu_categories', $catName, $catBiz);
        } catch (Exception $e) { $errorMsg = 'Hata: ' . $e->getMessage(); }
    } else { $errorMsg = 'Kategori adı boş bırakılamaz.'; }
}

// ── POST: delete category ────────────────────────────────────
if (isset($_GET['del_cat'])) {
    $delCat = intval($_GET['del_cat']);
    try {
        $row = $db->query("SELECT business_id FROM menu_categories WHERE id=?", [$delCat]);
        $r = $row->fetch();
        $bizId = $r['business_id'] ?? $bizId;
        $db->getPDO()->prepare("DELETE FROM menu_categories WHERE id=?")->execute([$delCat]);
        logAction('delete', 'menu_categories', null, $delCat);
        $successMsg = 'Kategori silindi.';
    } catch (Exception $e) { $errorMsg = 'Silme hatası: ' . $e->getMessage(); }
}

// ── POST: save item ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_item'])) {
    $itemId   = intval($_POST['item_id'] ?? 0);
    $catIdPost= intval($_POST['item_cat'] ?? 0);
    $itemBiz  = intval($_POST['item_biz'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $itemDesc = trim($_POST['item_desc'] ?? '');
    $itemPrice= str_replace(',', '.', trim($_POST['item_price'] ?? '0'));
    $bizId    = $itemBiz;

    // Görsel: dosya yükleme veya URL
    $imagePath = resolveMenuItemImagePath(
        $_FILES['item_image'] ?? [],
        $_POST['item_image_url'] ?? '',
        $_POST['existing_image'] ?? '',
        '../public/images/menu/'
    );

    if ($itemName && $catIdPost && $itemBiz) {
        if ($imagePath === false) {
            $errorMsg = 'Geçerli bir görsel URL girin (https://…) veya JPG/PNG/WEBP dosyası yükleyin.';
        } else {
        try {
            if ($itemId > 0) {
                $db->getPDO()->prepare("UPDATE menu_items SET name=?,description=?,price=?,category_id=?,image_path=? WHERE id=? AND business_id=?")
                    ->execute([$itemName, $itemDesc, $itemPrice, $catIdPost, $imagePath ?: null, $itemId, $itemBiz]);
                $successMsg = 'Ürün güncellendi.';
            } else {
                $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM menu_items WHERE category_id=?", [$catIdPost]);
                $db->getPDO()->prepare("INSERT INTO menu_items (category_id,business_id,name,description,price,image_path,sort_order) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$catIdPost, $itemBiz, $itemName, $itemDesc, $itemPrice, $imagePath ?: null, $maxOrder->fetchColumn()]);
                $successMsg = 'Ürün eklendi.';
            }
            logAction('create', 'menu_items', $itemName, $itemBiz);
        } catch (Exception $e) { $errorMsg = 'Hata: ' . $e->getMessage(); }
        }
    } else { $errorMsg = 'Ürün adı ve kategori zorunludur.'; }
}

// ── POST: delete item ─────────────────────────────────────────
if (isset($_GET['del_item'])) {
    $delItem = intval($_GET['del_item']);
    try {
        $row = $db->query("SELECT business_id FROM menu_items WHERE id=?", [$delItem]);
        $r = $row->fetch();
        $bizId = $r['business_id'] ?? $bizId;
        $db->getPDO()->prepare("DELETE FROM menu_items WHERE id=?")->execute([$delItem]);
        $successMsg = 'Ürün silindi.';
    } catch (Exception $e) { $errorMsg = 'Silme hatası: ' . $e->getMessage(); }
}

// ── POST: business user (create / reset pw) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_bizuser'])) {
    $buBiz  = intval($_POST['bu_biz'] ?? 0);
    $buUser = trim($_POST['bu_username'] ?? '');
    $buPass = trim($_POST['bu_password'] ?? '');
    $bizId  = $buBiz;
    if ($buBiz && $buUser && strlen($buPass) >= 6) {
        try {
            $hashed = password_hash($buPass, PASSWORD_DEFAULT);
            $check = $db->query("SELECT id FROM business_users WHERE business_id=?", [$buBiz]);
            if ($check->fetch()) {
                $db->getPDO()->prepare("UPDATE business_users SET username=?,password=? WHERE business_id=?")
                    ->execute([$buUser, $hashed, $buBiz]);
                $successMsg = 'İşletme kullanıcısı güncellendi.';
            } else {
                $db->getPDO()->prepare("INSERT INTO business_users (business_id,username,password) VALUES (?,?,?)")
                    ->execute([$buBiz, $buUser, $hashed]);
                $successMsg = 'İşletme kullanıcısı oluşturuldu.';
            }
            logAction('create', 'business_users', $buUser, $buBiz);
        } catch (Exception $e) {
            $errorMsg = strpos($e->getMessage(), '1062') !== false ? 'Bu kullanıcı adı zaten alınmış.' : 'Hata: ' . $e->getMessage();
        }
    } else { $errorMsg = 'Kullanıcı adı ve en az 8 karakterli şifre giriniz.'; }
}

// ── Fetch data ────────────────────────────────────────────────
$businesses = [];
try {
    $businesses = $db->query("SELECT id, name, slug FROM businesses ORDER BY name ASC")->fetchAll();
} catch (Exception $e) { $errorMsg = 'DB hatası: ' . $e->getMessage(); }

$categories = [];
$items      = [];
$bizUser    = null;
$editCat    = null;
$editItem   = null;
$currentBiz = null;

if ($bizId > 0 && empty($errorMsg)) {
    try {
        $bStmt = $db->query("SELECT id, name, slug FROM businesses WHERE id=?", [$bizId]);
        $currentBiz = $bStmt->fetch();

        $cStmt = $db->query("SELECT * FROM menu_categories WHERE business_id=? ORDER BY sort_order ASC, id ASC", [$bizId]);
        $categories = $cStmt->fetchAll();

        $iStmt = $db->query("SELECT mi.*, mc.name as cat_name FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id=mc.id WHERE mi.business_id=? ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.id ASC", [$bizId]);
        $items = $iStmt->fetchAll();

        $bizUserStmt = $db->query("SELECT * FROM business_users WHERE business_id=?", [$bizId]);
        $bizUser = $bizUserStmt->fetch();

        if (isset($_GET['edit_cat'])) {
            $ecStmt = $db->query("SELECT * FROM menu_categories WHERE id=? AND business_id=?", [intval($_GET['edit_cat']), $bizId]);
            $editCat = $ecStmt->fetch();
        }
        if (isset($_GET['edit_item'])) {
            $eiStmt = $db->query("SELECT * FROM menu_items WHERE id=? AND business_id=?", [intval($_GET['edit_item']), $bizId]);
            $editItem = $eiStmt->fetch();
        }
    } catch (Exception $e) {
        $errorMsg = 'Veritabanı hatası (tablolar oluşturuldu mu?): ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold text-navy mb-1"><i class="fa-solid fa-utensils me-2 text-primary"></i>Menü Yönetimi</h5>
        <p class="text-muted small mb-0">İşletme seçin, kategori ve ürün ekleyin.</p>
    </div>
    <?php
require_once '../autoload.php';
if ($currentBiz): ?>
        <a href="<?= $baseUrl ?>/menu/<?= htmlspecialchars($currentBiz['slug']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-eye me-1"></i> Menüyü Gör
        </a>
    <?php
require_once '../autoload.php';
endif; ?>
</div>

<?php
require_once '../autoload.php';
if ($successMsg): ?>
    <div class="alert alert-success border-0 small mb-3"><i class="fa-solid fa-circle-check me-1"></i> <?= $successMsg ?></div>
<?php
require_once '../autoload.php';
endif; ?>
<?php
require_once '../autoload.php';
if ($errorMsg): ?>
    <div class="alert alert-danger border-0 small mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($errorMsg) ?></div>
<?php
require_once '../autoload.php';
endif; ?>

<!-- Business Selector -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label fw-semibold small text-muted">İşletme Seç</label>
                <select name="biz" class="form-select" onchange="this.form.submit()">
                    <option value="">-- İşletme seçin --</option>
                    <?php
require_once '../autoload.php';
foreach ($businesses as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $bizId == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php
require_once '../autoload.php';
endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../autoload.php';
if ($bizId > 0 && $currentBiz): ?>

<div class="row g-4">

    <!-- LEFT: Categories + Items -->
    <div class="col-lg-8">

        <!-- Add/Edit Category -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3">
                <span class="fw-bold text-navy"><i class="fa-solid fa-layer-group me-2 text-primary"></i>
                    <?= $editCat ? 'Kategori Düzenle' : 'Yeni Kategori Ekle' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="do_cat" value="1">
                    <input type="hidden" name="cat_biz" value="<?= $bizId ?>">
                    <input type="hidden" name="cat_id" value="<?= $editCat ? $editCat['id'] : 0 ?>">
                    <div class="input-group">
                        <input type="text" name="cat_name" class="form-control"
                               value="<?= htmlspecialchars($editCat['name'] ?? '') ?>"
                               placeholder="örn: Başlangıçlar, Pizzalar, İçecekler…" required>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-<?= $editCat ? 'floppy-disk' : 'plus' ?> me-1"></i>
                            <?= $editCat ? 'Güncelle' : 'Ekle' ?>
                        </button>
                        <?php
require_once '../autoload.php';
if ($editCat): ?>
                            <a href="menu.php?biz=<?= $bizId ?>" class="btn btn-outline-secondary">İptal</a>
                        <?php
require_once '../autoload.php';
endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories list -->
        <?php
require_once '../autoload.php';
if (!empty($categories)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Kategoriler</span>
                <span class="badge bg-secondary"><?= count($categories) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kategori</th>
                            <th>Ürün Sayısı</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
require_once '../autoload.php';
foreach ($categories as $cat):
                            $thisCatId = $cat['id'];
                            $catItemCount = count(array_filter($items, function($i) use ($thisCatId) { return $i['category_id'] == $thisCatId; }));
                        ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= $catItemCount ?></span></td>
                            <td class="text-end pe-4">
                                <a href="menu.php?biz=<?= $bizId ?>&edit_cat=<?= $cat['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                <a href="menu.php?biz=<?= $bizId ?>&del_cat=<?= $cat['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn"
                                   data-confirm="'<?= htmlspecialchars($cat['name']) ?>' kategorisini ve tüm ürünlerini silmek istediğinizden emin misiniz?"
                                   data-confirm-title="Kategori Sil" data-confirm-btn="Evet, Sil">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php
require_once '../autoload.php';
endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
require_once '../autoload.php';
endif; ?>

        <!-- Add/Edit Item -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3">
                <span class="fw-bold text-navy"><i class="fa-solid fa-burger me-2 text-primary"></i>
                    <?= $editItem ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="do_item" value="1">
                    <input type="hidden" name="item_biz" value="<?= $bizId ?>">
                    <input type="hidden" name="item_id" value="<?= $editItem ? $editItem['id'] : 0 ?>">
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editItem['image_path'] ?? '') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">Kategori <span class="text-danger">*</span></label>
                            <select name="item_cat" class="form-select" required>
                                <option value="">Kategori seçin</option>
                                <?php
require_once '../autoload.php';
foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($editItem['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php
require_once '../autoload.php';
endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control" required
                                   value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                                   placeholder="örn: Karışık Pizza">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-muted">Açıklama</label>
                            <input type="text" name="item_desc" class="form-control"
                                   value="<?= htmlspecialchars($editItem['description'] ?? '') ?>"
                                   placeholder="Malzemeler veya kısa açıklama…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">Fiyat (₺) <span class="text-danger">*</span></label>
                            <input type="text" name="item_price" class="form-control" required
                                   value="<?= $editItem ? number_format((float)$editItem['price'], 2, ',', '') : '' ?>"
                                   placeholder="0,00">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small text-muted">Ürün Görseli <span class="text-muted small">(opsiyonel)</span></label>
                            <?php
require_once '../autoload.php';
$editImagePreview = !empty($editItem['image_path']) ? menuItemImageUrl($editItem['image_path']) : null;
                            $editImageUrl = (!empty($editItem['image_path']) && menuItemImageIsRemote($editItem['image_path']))
                                ? $editItem['image_path']
                                : '';
                            ?>
                            <?php
require_once '../autoload.php';
if ($editImagePreview): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($editImagePreview) ?>"
                                         style="height:60px;border-radius:8px;object-fit:cover;" alt="">
                                    <small class="text-muted ms-2">Yeni görsel seçilir veya URL girilirse değişir.</small>
                                </div>
                            <?php
require_once '../autoload.php';
endif; ?>
                            <input type="url" name="item_image_url" class="form-control mb-2"
                                   value="<?= htmlspecialchars($editImageUrl) ?>"
                                   placeholder="https://… görsel bağlantısı">
                            <input type="file" name="item_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">Harici URL veya dosya yükleyebilirsiniz. Dosya yükleme önceliklidir.</small>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                <?= $editItem ? 'Güncelle' : 'Ürün Ekle' ?>
                            </button>
                            <?php
require_once '../autoload.php';
if ($editItem): ?>
                                <a href="menu.php?biz=<?= $bizId ?>" class="btn btn-outline-secondary px-4">İptal</a>
                            <?php
require_once '../autoload.php';
endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Items list -->
        <?php
require_once '../autoload.php';
if (!empty($items)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Ürünler</span>
                <span class="badge bg-secondary"><?= count($items) ?> ürün</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:60px;">Foto</th>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
require_once '../autoload.php';
foreach ($items as $item): ?>
                        <tr>
                            <td class="ps-4">
                                <?php
require_once '../autoload.php';
$itemImgSrc = menuItemImageUrl($item['image_path'] ?? null); ?>
                                <?php
require_once '../autoload.php';
if ($itemImgSrc): ?>
                                    <img src="<?= htmlspecialchars($itemImgSrc) ?>"
                                         style="width:44px;height:44px;border-radius:8px;object-fit:cover;" alt="">
                                <?php
require_once '../autoload.php';
else: ?>
                                    <div style="width:44px;height:44px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ccc;"><i class="fa-solid fa-utensils"></i></div>
                                <?php
require_once '../autoload.php';
endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                <?php
require_once '../autoload.php';
if ($item['description']): ?>
                                    <small class="text-muted"><?= htmlspecialchars(mb_substr($item['description'], 0, 50)) ?>…</small>
                                <?php
require_once '../autoload.php';
endif; ?>
                            </td>
                            <td><span style="font-size:11px;background:#eff6ff;color:#1d4ed8;border:1px solid #93c5fd;border-radius:4px;padding:2px 8px;"><?= htmlspecialchars($item['cat_name']) ?></span></td>
                            <td class="fw-bold" style="color:var(--primary)"><?= number_format((float)$item['price'], 2, ',', '.') ?> ₺</td>
                            <td class="text-end pe-4">
                                <a href="menu.php?biz=<?= $bizId ?>&edit_item=<?= $item['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                <a href="menu.php?biz=<?= $bizId ?>&del_item=<?= $item['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn"
                                   data-confirm="'<?= htmlspecialchars($item['name']) ?>' ürününü silmek istediğinizden emin misiniz?"
                                   data-confirm-title="Ürün Sil" data-confirm-btn="Evet, Sil">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php
require_once '../autoload.php';
endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
require_once '../autoload.php';
endif; ?>

    </div><!-- /col-lg-12 changed from 8 to 12 since right column is gone -->


</div><!-- /row -->

<?php
require_once '../autoload.php';
endif; ?>

<?php
require_once '../autoload.php';
require_once 'includes/footer.php'; ?>
