<?php
ob_start();
require_once __DIR__ . '/../includes/menu-helpers.php';
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
$db = Database::getInstance();

$pageTitle = 'Kategori Yönetimi — Dijital Menü';
$bizId     = intval($_SESSION['biz_id'] ?? 0);
$bizSlug   = $_SESSION['biz_slug'] ?? '';
$bizName   = $_SESSION['biz_name'] ?? 'İşletme';

if (!$bizId) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$successMsg = '';
$errorMsg   = '';

// Build base URL (works both locally and on production)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain   = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('isletme/menu-kategoriler.php', '', $_SERVER['PHP_SELF']), '/');
$baseUrl  = $protocol . $domain . $basePath;

// ── POST: save category ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_cat'])) {
    validateCSRF();

    $catId    = intval($_POST['cat_id'] ?? 0);
    $catName  = trim($_POST['cat_name'] ?? '');
    $catOrder = intval($_POST['sort_order'] ?? 0);
    $catIcon  = trim($_POST['icon'] ?? 'fa-utensils');

    $imagePath = resolveMenuItemImagePath(
        $_FILES['cat_image'] ?? [],
        $_POST['cat_image_url'] ?? '',
        $_POST['existing_cat_image'] ?? '',
        '../public/images/menu/'
    );

    if ($catName) {
        if ($imagePath === false) {
            $errorMsg = 'Geçerli bir görsel URL girin (https://…) veya JPG/PNG/WEBP dosyası yükleyin.';
        } else {
            try {
                if ($catId > 0) {
                    $db->getPDO()->prepare("UPDATE menu_categories SET name=?, sort_order=?, icon=?, image_path=? WHERE id=? AND business_id=?")
                        ->execute([$catName, $catOrder, $catIcon, $imagePath ?: null, $catId, $bizId]);
                    $successMsg = 'Kategori güncellendi.';
                } else {
                    $maxOrd = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM menu_categories WHERE business_id=?", [$bizId]);
                    $ord = $catOrder > 0 ? $catOrder : $maxOrd->fetchColumn();
                    $db->getPDO()->prepare("INSERT INTO menu_categories (business_id, name, sort_order, icon, image_path) VALUES (?,?,?,?,?)")
                        ->execute([$bizId, $catName, $ord, $catIcon, $imagePath ?: null]);
                    $successMsg = 'Yeni kategori eklendi.';
                }
            } catch (Exception $e) { $errorMsg = 'Veritabanı hatası: ' . $e->getMessage(); }
        }
    } else {
        $errorMsg = 'Kategori adı zorunludur.';
    }
}

// ── GET: delete category ──────────────────────────────────────
if (isset($_GET['del_cat'])) {
    try {
        $db->getPDO()->prepare("DELETE FROM menu_categories WHERE id=? AND business_id=?")
            ->execute([intval($_GET['del_cat']), $bizId]);
        $successMsg = 'Kategori silindi.';
    } catch (Exception $e) { $errorMsg = 'Silme hatası.'; }
}

// ── Fetch categories and item counts ─────────────────────────
$categories = [];
$items      = [];
$editCat    = null;

try {
    $cStmt = $db->query("SELECT * FROM menu_categories WHERE business_id=? ORDER BY sort_order ASC, id ASC", [$bizId]);
    $categories = $cStmt->fetchAll();

    $iStmt = $db->query("SELECT id, category_id FROM menu_items WHERE business_id=?", [$bizId]);
    $items = $iStmt->fetchAll();

    if (isset($_GET['edit_cat'])) {
        $ec = $db->query("SELECT * FROM menu_categories WHERE id=? AND business_id=?", [intval($_GET['edit_cat']), $bizId]);
        $editCat = $ec->fetch();
    }
} catch (Exception $e) {
    $errorMsg = 'Veritabanı hatası.';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--navy);">
            <i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>
            <?= htmlspecialchars($bizName) ?> — Kategori Yönetimi
        </h5>
        <p class="text-muted small mb-0">Menünüzün kategorilerini ve kapak fotoğraflarını buradan yönetin.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="menu-urunler.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-burger me-1"></i> Ürün Yönetimi'ne Git
        </a>
        <a href="<?= $baseUrl ?>/menu/<?= htmlspecialchars($bizSlug) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-eye me-1"></i> Menüyü Gör
        </a>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success border-0 small mb-3"><i class="fa-solid fa-circle-check me-1"></i> <?= $successMsg ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger border-0 small mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- LEFT: Category Form -->
    <div class="col-lg-5">
        <div class="card mb-4 border-0 shadow-sm" id="catForm" style="border-radius: 16px; <?= $editCat ? 'border: 2px solid var(--primary) !important;' : '' ?>">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-navy">
                    <i class="fa-solid fa-layer-group me-2 text-primary"></i>
                    <?= $editCat ? 'Kategori Düzenle' : 'Yeni Kategori Ekle' ?>
                </span>
                <?php if ($editCat): ?>
                    <a href="menu-kategoriler.php" class="btn btn-sm btn-outline-secondary">İptal</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="do_cat" value="1">
                    <input type="hidden" name="cat_id" value="<?= $editCat ? $editCat['id'] : 0 ?>">
                    <input type="hidden" name="existing_cat_image" value="<?= htmlspecialchars($editCat['image_path'] ?? '') ?>">

                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold small text-muted">Kategori Adı <span class="text-danger">*</span></label>
                            <input type="text" name="cat_name" class="form-control" required
                                   value="<?= htmlspecialchars($editCat['name'] ?? '') ?>"
                                   placeholder="örn: Başlangıçlar, Ana Yemekler, İçecekler…">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small text-muted">Sıra No</label>
                            <input type="number" name="sort_order" class="form-control"
                                   value="<?= htmlspecialchars($editCat['sort_order'] ?? '') ?>"
                                   placeholder="0 = Otomatik">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">İkon (FontAwesome)</label>
                            <select name="icon" class="form-select">
                                <?php
                                $icons = [
                                    'fa-utensils'       => '🍽️ Genel Çatal Bıçak (fa-utensils)',
                                    'fa-burger'         => '🍔 Burger / Fast Food (fa-burger)',
                                    'fa-pizza-slice'    => '🍕 Pizza (fa-pizza-slice)',
                                    'fa-bowl-food'      => '🍲 Sulu Yemek / Çorba (fa-bowl-food)',
                                    'fa-fish'           => '🐟 Balık / Deniz Ürünü (fa-fish)',
                                    'fa-drumstick-bite' => '🍗 Et / Tavuk (fa-drumstick-bite)',
                                    'fa-leaf'           => '🥗 Salata / Vejetaryen (fa-leaf)',
                                    'fa-cake-candles'   => '🍰 Tatlı / Pasta (fa-cake-candles)',
                                    'fa-ice-cream'      => '🍨 Dondurma / Tatlı (fa-ice-cream)',
                                    'fa-mug-hot'        => '☕ Sıcak İçecek / Kahve (fa-mug-hot)',
                                    'fa-wine-glass'     => '🍷 Soğuk İçecek / Alkol (fa-wine-glass)',
                                    'fa-bottle-water'   => '🥤 Meşrubat / Su (fa-bottle-water)',
                                    'fa-bread-slice'    => '🥖 Ekmek / Hamur İşi (fa-bread-slice)',
                                    'fa-egg'            => '🍳 Kahvaltı (fa-egg)',
                                    'fa-pepper-hot'     => '🌶️ Acılı / Sos (fa-pepper-hot)'
                                ];
                                $curIcon = $editCat['icon'] ?? 'fa-utensils';
                                foreach ($icons as $val => $lbl):
                                ?>
                                    <option value="<?= $val ?>" <?= $curIcon === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Kategori Fotoğrafı <span class="text-muted">(opsiyonel)</span></label>
                            <?php
                            $editCatImagePreview = !empty($editCat['image_path']) ? menuItemImageUrl($editCat['image_path']) : null;
                            $editCatImageUrl = (!empty($editCat['image_path']) && menuItemImageIsRemote($editCat['image_path']))
                                ? $editCat['image_path']
                                : '';
                            ?>
                            <?php if ($editCatImagePreview): ?>
                                <div class="mb-2 d-flex align-items-center gap-2">
                                    <img src="<?= htmlspecialchars($editCatImagePreview) ?>"
                                         style="height:60px;width:60px;border-radius:10px;object-fit:cover;border:1px solid #eee;" alt="">
                                    <small class="text-muted">Mevcut görsel. Yeni dosya seçerseniz veya URL girerseniz değiştirilir.</small>
                                </div>
                            <?php endif; ?>
                            <input type="url" name="cat_image_url" class="form-control mb-2"
                                   value="<?= htmlspecialchars($editCatImageUrl) ?>"
                                   placeholder="https://… görsel bağlantısı">
                            <input type="file" name="cat_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted d-block mt-1">Görsel seçerseniz dijital menüde kategorinin üzerinde kapak fotoğrafı olarak görünür.</small>
                        </div>

                        <div class="col-12 d-flex gap-2 pt-2">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                <?= $editCat ? 'Kategori Güncelle' : 'Kategori Ekle' ?>
                            </button>
                            <?php if ($editCat): ?>
                                <a href="menu-kategoriler.php" class="btn btn-outline-secondary">İptal</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Categories List -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-navy"><i class="fa-solid fa-list me-2 text-primary"></i>Kategori Listesi ve Fotoğrafları</span>
                <span class="badge bg-secondary"><?= count($categories) ?> Kategori</span>
            </div>
            <div class="card-body p-3">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-layer-group fs-1 mb-3 text-secondary opacity-50"></i>
                        <p class="mb-0">Henüz kategori eklemediniz. Sol taraftaki formu kullanarak ilk kategorinizi ekleyin.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($categories as $cat):
                            $thisCatId = $cat['id'];
                            $cnt = count(array_filter($items, function($i) use ($thisCatId) { return $i['category_id'] == $thisCatId; }));
                            $catImg = !empty($cat['image_path']) ? menuItemImageUrl($cat['image_path']) : null;
                        ?>
                        <div class="card border-0 shadow-sm p-3 d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3"
                             style="border-radius: 14px; background: #fff; border: 1px solid rgba(0,0,0,0.05) !important;">
                            <div class="d-flex align-items-center gap-3 w-100" style="min-width: 0;">
                                <?php if ($catImg): ?>
                                    <img src="<?= htmlspecialchars($catImg) ?>" class="rounded-3 shadow-sm" style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #eee;" alt="">
                                <?php else: ?>
                                    <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="width: 60px; height: 60px; border: 1px solid #eee;">
                                        <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-utensils') ?> fs-4"></i>
                                    </div>
                                <?php endif; ?>
                                <div style="min-width: 0; flex-grow: 1;">
                                    <h6 class="fw-bold text-navy mb-1 text-truncate" style="font-size: 16px;">
                                        <?= htmlspecialchars($cat['name']) ?>
                                        <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 11px;"><?= $cnt ?> Ürün</span>
                                    </h6>
                                    <span class="text-muted small"><i class="fa-solid fa-arrows-up-down me-1"></i>Sıra: <?= (int)$cat['sort_order'] ?></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-2 w-100 w-sm-auto pt-2 pt-sm-0 border-top border-sm-0 mt-1 mt-sm-0">
                                <a href="menu-urunler.php?add_cat=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-success fw-bold" title="Bu kategoriye ürün ekle">
                                    <i class="fa-solid fa-plus me-1"></i>Ürün Ekle
                                </a>
                                <div class="btn-group flex-shrink-0">
                                    <a href="menu-kategoriler.php?edit_cat=<?= $cat['id'] ?>#catForm" class="btn btn-sm btn-outline-secondary" title="Düzenle">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="menu-kategoriler.php?del_cat=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger confirm-btn"
                                       data-confirm="'<?= htmlspecialchars($cat['name']) ?>' kategorisini ve içindeki tüm ürünleri silmek istediğinizden emin misiniz?"
                                       data-confirm-title="Sil" data-confirm-btn="Evet, Sil" title="Sil">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
