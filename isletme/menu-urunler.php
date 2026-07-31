<?php
ob_start();
require_once __DIR__ . '/../includes/menu-helpers.php';
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';
$db = Database::getInstance();

$pageTitle = 'Ürün Yönetimi — Dijital Menü';
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

// Build base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain   = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('isletme/menu-urunler.php', '', $_SERVER['PHP_SELF']), '/');
$baseUrl  = $protocol . $domain . $basePath;

$presetAllergens = ['Gluten', 'Süt Ürünleri', 'Yumurta', 'Yer Fıstığı', 'Kabuklu Kuruyemiş', 'Soya', 'Balık', 'Kabuklu Deniz Ürünleri', 'Susam'];

// ── POST: save item ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_item'])) {
    validateCSRF();

    $itemId    = intval($_POST['item_id'] ?? 0);
    $catIdPost = intval($_POST['item_cat'] ?? 0);
    $itemName  = trim($_POST['item_name'] ?? '');
    $itemDesc  = trim($_POST['item_desc'] ?? '');
    $itemPrice = str_replace(',', '.', trim($_POST['item_price'] ?? '0'));
    $cookingTime = trim($_POST['cooking_time'] ?? '');
    $calories    = trim($_POST['calories'] ?? '');
    $selectedAllergens = !empty($_POST['allergens']) ? array_map('trim', (array) $_POST['allergens']) : [];
    $customAllergens = menuParseAllergenList($_POST['custom_allergens'] ?? '');
    $allergens = array_unique(array_filter(array_merge($selectedAllergens, $customAllergens)));
    $allergens = !empty($allergens) ? implode(', ', $allergens) : '';
    $isChefs     = isset($_POST['is_chefs_choice']) ? 1 : 0;
    $badge       = trim($_POST['badge'] ?? '');

    $imagePath = resolveMenuItemImagePath(
        $_FILES['item_image'] ?? [],
        $_POST['item_image_url'] ?? '',
        $_POST['existing_image'] ?? '',
        '../public/images/menu/'
    );

    if ($itemName && $catIdPost) {
        if ($imagePath === false) {
            $errorMsg = 'Geçerli bir görsel URL girin (https://…) veya JPG/PNG/WEBP dosyası yükleyin.';
        } else {
            try {
                $catCheck = $db->query("SELECT id FROM menu_categories WHERE id=? AND business_id=?", [$catIdPost, $bizId]);
                if (!$catCheck->fetch()) throw new Exception('Geçersiz kategori.');

                if ($itemId > 0) {
                    $db->getPDO()->prepare("UPDATE menu_items SET name=?,description=?,price=?,category_id=?,image_path=?,cooking_time=?,calories=?,allergens=?,is_chefs_choice=?,badge=? WHERE id=? AND business_id=?")
                        ->execute([$itemName, $itemDesc, $itemPrice, $catIdPost, $imagePath ?: null, $cookingTime ?: null, $calories ?: null, $allergens ?: null, $isChefs, $badge ?: null, $itemId, $bizId]);
                    $successMsg = 'Ürün başarıyla güncellendi.';
                } else {
                    $maxOrd = $db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM menu_items WHERE category_id=?", [$catIdPost]);
                    $db->getPDO()->prepare("INSERT INTO menu_items (category_id,business_id,name,description,price,image_path,cooking_time,calories,allergens,is_chefs_choice,badge,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$catIdPost, $bizId, $itemName, $itemDesc, $itemPrice, $imagePath ?: null, $cookingTime ?: null, $calories ?: null, $allergens ?: null, $isChefs, $badge ?: null, $maxOrd->fetchColumn()]);
                    $successMsg = 'Yeni ürün eklendi.';
                }
            } catch (Exception $e) { $errorMsg = 'Hata: ' . $e->getMessage(); }
        }
    } else { $errorMsg = 'Ürün adı ve kategori zorunludur.'; }
}

// ── GET: delete item ──────────────────────────────────────────
if (isset($_GET['del_item'])) {
    try {
        $db->getPDO()->prepare("DELETE FROM menu_items WHERE id=? AND business_id=?")
            ->execute([intval($_GET['del_item']), $bizId]);
        $successMsg = 'Ürün silindi.';
    } catch (Exception $e) { $errorMsg = 'Silme hatası.'; }
}

// ── GET: remove allergen from list ────────────────────────────
if (isset($_GET['remove_allergen'])) {
    $toRemove = trim($_GET['remove_allergen']);
    if ($toRemove !== '') {
        try {
            $db->getPDO()->exec("ALTER TABLE businesses ADD COLUMN hidden_allergens TEXT NULL AFTER theme_color");
        } catch (Exception $e) {}

        try {
            $bizRow = $db->query("SELECT hidden_allergens FROM businesses WHERE id=?", [$bizId])->fetch();
            $hiddenList = !empty($bizRow['hidden_allergens']) ? json_decode($bizRow['hidden_allergens'], true) : [];
            if (!is_array($hiddenList)) $hiddenList = [];

            if (!in_array($toRemove, $hiddenList, true)) {
                $hiddenList[] = $toRemove;
                $db->getPDO()->prepare("UPDATE businesses SET hidden_allergens=? WHERE id=?")
                    ->execute([json_encode($hiddenList, JSON_UNESCAPED_UNICODE), $bizId]);
            }
            $successMsg = "'" . htmlspecialchars($toRemove) . "' alerjeni listeden kaldırıldı.";
        } catch (Exception $e) {}
    }
}

// ── Fetch data ────────────────────────────────────────────────
$categories = [];
$items      = [];
$editItem   = null;
$preselectedCatId = intval($_GET['add_cat'] ?? 0);

try {
    $cStmt = $db->query("SELECT * FROM menu_categories WHERE business_id=? ORDER BY sort_order ASC, id ASC", [$bizId]);
    $categories = $cStmt->fetchAll();

    $iStmt = $db->query("SELECT mi.*, mc.name as cat_name FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id=mc.id WHERE mi.business_id=? ORDER BY mc.sort_order ASC, mi.sort_order ASC", [$bizId]);
    $items = $iStmt->fetchAll();

    if (isset($_GET['edit_item'])) {
        $ei = $db->query("SELECT * FROM menu_items WHERE id=? AND business_id=?", [intval($_GET['edit_item']), $bizId]);
        $editItem = $ei->fetch();
    }
} catch (Exception $e) {
    $errorMsg = 'Veritabanı hatası.';
}

// Collect all unique allergens and badges from database + defaults
$dbAllergens = [];
$dbBadges    = ['Yeni', 'Popüler', 'Önerilen', 'Acılı', 'Vejetaryen', 'Glutensiz'];
foreach ($items as $it) {
    if (!empty($it['allergens'])) {
        $parts = menuParseAllergenList($it['allergens']);
        foreach ($parts as $p) {
            if (!in_array($p, $presetAllergens, true) && !in_array($p, $dbAllergens, true)) {
                $dbAllergens[] = $p;
            }
        }
    }
    if (!empty($it['badge']) && !in_array($it['badge'], $dbBadges, true)) {
        $dbBadges[] = trim($it['badge']);
    }
}

$hiddenAllergens = [];
try {
    $bizRow = $db->query("SELECT hidden_allergens FROM businesses WHERE id=?", [$bizId])->fetch();
    if (!empty($bizRow['hidden_allergens'])) {
        $hiddenAllergens = json_decode($bizRow['hidden_allergens'], true) ?: [];
    }
} catch (Exception $e) {}

$allAllergenOptions = array_values(array_diff(array_merge($presetAllergens, $dbAllergens), $hiddenAllergens));
$selectedAllergens  = menuParseAllergenList($editItem['allergens'] ?? '');
$categoryCounts     = [];

foreach ($items as $item) {
    $categoryId = (int) ($item['category_id'] ?? 0);
    if ($categoryId > 0) {
        $categoryCounts[$categoryId] = ($categoryCounts[$categoryId] ?? 0) + 1;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="color:var(--navy);">
            <i class="fa-solid fa-burger me-2" style="color:var(--primary);"></i>
            <?= htmlspecialchars($bizName) ?> — Ürün Yönetimi
        </h5>
        <p class="text-muted small mb-0">Menünüzün ürünlerini, fiyatlarını, açıklamalarını ve görsellerini yönetin.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="openNewItemModal()">
            <i class="fa-solid fa-plus me-1"></i> + Yeni Ürün Ekle
        </button>
        <a href="menu-kategoriler.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-layer-group me-1"></i> Kategori Yönetimi'ne Git
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

<!-- Full-Width Main Section (col-12) -->
<div class="row">
    <div class="col-12">

        <!-- Live Search & Category Filter Box -->
        <div class="card mb-4 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-body p-3 bg-white">
                <div class="d-flex flex-column flex-sm-row gap-2 align-items-center justify-content-between mb-3">
                    <div class="input-group input-group-lg w-100 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        <span class="input-group-text bg-light border-0 text-muted ps-3"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="menuSearchInput" class="form-control bg-light border-0 py-2" style="font-size: 15px;" placeholder="Ürün adı, açıklama, kategori veya etiket ara..." oninput="filterProductsList()" onkeydown="if(event.key==='Enter'){event.preventDefault(); filterProductsList();}">
                        <button class="btn btn-primary btn-sm px-3 fw-semibold" type="button" style="font-size: 13px;" onclick="filterProductsList()"><i class="fa-solid fa-magnifying-glass me-1"></i> Ara</button>
                        <button class="btn btn-outline-secondary btn-sm px-2" type="button" style="font-size: 13px;" onclick="clearMenuSearch()" title="Aramayı Temizle"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
                <!-- Category Dropdown Filter -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                    <div class="d-flex align-items-center gap-2">
                        <label for="catFilterSelect" class="text-muted small fw-bold mb-0">
                            <i class="fa-solid fa-filter me-1 text-primary"></i>Kategoriye Göre Filtrele:
                        </label>
                        <select id="catFilterSelect" class="form-select form-select-sm shadow-sm" style="min-width: 240px; max-width: 320px; border-radius: 10px;" onchange="handleCategoryFilterChange(this)">
                            <option value="all" data-category-name="Tüm Kategoriler">Tüm Kategoriler (<?= count($items) ?>)</option>
                            <?php foreach ($categories as $cat):
                                $catId = (int) $cat['id'];
                                $cnt = $categoryCounts[$catId] ?? 0;
                            ?>
                                <option value="<?= $catId ?>" data-category-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($cat['name']) ?> (<?= $cnt ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Quick active filter reset button if filtered -->
                    <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted p-0 d-none" id="resetCatFilterBtn" onclick="filterByCategory('all', 'Tüm Kategoriler')">
                        <i class="fa-solid fa-rotate-left me-1"></i>Filtreyi Sıfırla
                    </button>
                </div>
            </div>
        </div>

        <!-- Full-Width Product List Card -->
        <div class="card border-0 shadow-sm mb-5" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-navy" style="font-size: 16px;"><i class="fa-solid fa-burger me-2 text-primary"></i>Ürün Listesi</span>
                    <span class="badge bg-secondary ms-1" id="visibleProductBadge"><?= count($items) ?> Ürün</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="openNewItemModal()">
                    <i class="fa-solid fa-plus me-1"></i> Yeni Ürün Ekle
                </button>
            </div>

            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="py-5 text-center text-muted" id="emptyStateMsg">
                        <i class="fa-solid fa-utensils fs-1 mb-3 text-secondary opacity-50"></i>
                        <p class="mb-2">Henüz menünüze ürün eklemediniz.</p>
                        <button type="button" class="btn btn-primary btn-sm fw-bold px-4 mt-1" onclick="openNewItemModal()">+ İlk Ürünü Ekle</button>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="productsListContainer">
                        <?php foreach ($items as $item):
                            $imgSrc = !empty($item['image_path']) ? menuItemImageUrl($item['image_path']) : null;
                        ?>
                        <div class="list-group-item d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 p-3 product-list-item"
                             data-category-id="<?= $item['category_id'] ?>"
                             data-search-text="<?= htmlspecialchars(mb_strtolower($item['name'] . ' ' . ($item['description'] ?? '') . ' ' . ($item['cat_name'] ?? '') . ' ' . ($item['badge'] ?? '') . ' ' . ($item['allergens'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                             data-item-name="<?= SecurityHelper::escape(mb_strtolower($item['name'], 'UTF-8')) ?>"
                             data-item-desc="<?= SecurityHelper::escape(mb_strtolower($item['description'] ?? '', 'UTF-8')) ?>">
                            
                            <div class="d-flex align-items-center gap-3 w-100" style="min-width: 0;">
                                <?php if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="rounded-3 shadow-sm" style="width: 58px; height: 58px; object-fit: cover; flex-shrink: 0; border: 1px solid #eee;" alt="">
                                <?php else: ?>
                                    <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="width: 58px; height: 58px; flex-shrink: 0; border: 1px solid #eee;">
                                        <i class="fa-solid fa-utensils fs-5"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="min-width: 0; flex-grow: 1;">
                                    <div class="fw-bold text-navy text-truncate" style="font-size:15px;">
                                        <?= htmlspecialchars($item['name']) ?>
                                        <?php if (!empty($item['is_chefs_choice'])): ?>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size: 10px;">👨‍🍳 Şefin Seçimi</span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['badge'])): ?>
                                            <span class="badge bg-primary ms-1" style="font-size: 10px;"><?= htmlspecialchars($item['badge']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                        <span class="badge bg-light text-secondary border" style="font-size: 11px; font-weight: 600;">
                                            <i class="fa-solid fa-layer-group me-1 text-primary"></i><?= htmlspecialchars($item['cat_name']) ?>
                                        </span>
                                        <?php if (!empty($item['calories'])): ?>
                                            <span class="text-muted small" style="font-size: 11px;"><i class="fa-solid fa-fire-flame-curved me-1 text-warning"></i><?= htmlspecialchars($item['calories']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <div class="text-muted small text-truncate mt-1" style="max-width: 100%; font-size: 12px;"><?= htmlspecialchars($item['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-3 w-100 w-sm-auto pt-2 pt-sm-0 border-top border-sm-0 mt-1 mt-sm-0">
                                <span class="fw-bold text-primary" style="font-size: 16px;">
                                    <?= number_format((float)$item['price'], 2, ',', '.') ?> ₺
                                </span>
                                <div class="btn-group flex-shrink-0">
                                    <a href="menu-urunler.php?edit_item=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ürünü Düzenle">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="menu-urunler.php?del_item=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger confirm-btn"
                                       data-confirm="'<?= htmlspecialchars($item['name']) ?>' ürününü silmek istediğinizden emin misiniz?"
                                       data-confirm-title="Sil" data-confirm-btn="Evet, Sil" title="Sil">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- No Results Message when filtering -->
                    <div class="py-5 text-center text-muted" id="noProductMsg" style="display: none;">
                        <i class="fa-solid fa-magnifying-glass fs-2 mb-2 text-secondary opacity-50"></i>
                        <p class="mb-0">Seçilen kriterlere uygun ürün bulunamadı.</p>
                    </div>

                    <!-- Pagination Bar -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-top bg-light" id="productPaginationContainer" style="display: none;">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="small text-muted fw-semibold" id="paginationStatusText"></span>
                            <div class="d-flex align-items-center gap-2">
                                <label for="pageSizeSelect" class="small text-muted fw-semibold mb-0">Göster:</label>
                                <select id="pageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changePageSize(this.value)">
                                    <option value="5">5 kayıt</option>
                                    <option value="10">10 kayıt</option>
                                    <option value="20">20 kayıt</option>
                                    <option value="all">Tüm kayıtlar</option>
                                </select>
                            </div>
                        </div>
                        <ul class="pagination pagination-sm mb-0 shadow-sm" id="productPaginationList"></ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div><!-- /row -->

<!-- ── PRODUCT ADD / EDIT MODAL ─────────────────────────────── -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header bg-light py-3 border-bottom">
                <h6 class="modal-title fw-bold text-navy" id="itemModalTitle">
                    <i class="fa-solid fa-burger me-2 text-primary"></i>
                    <?= $editItem ? 'Ürün Düzenle: ' . htmlspecialchars($editItem['name']) : 'Yeni Ürün Ekle' ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-layer-group fs-2 mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-3">Önce en az bir kategori eklemelisiniz.</p>
                        <a href="menu-kategoriler.php" class="btn btn-primary btn-sm fw-bold">Kategori Ekle</a>
                    </div>
                <?php else: ?>
                <form method="POST" action="menu-urunler.php" enctype="multipart/form-data" id="modalItemForm">
                    <?= CSRFMiddleware::field() ?>
                    <input type="hidden" name="do_item" value="1">
                    <input type="hidden" name="item_id" id="modalItemId" value="<?= $editItem ? $editItem['id'] : 0 ?>">
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editItem['image_path'] ?? '') ?>">

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small text-muted">Kategori <span class="text-danger">*</span></label>
                            <select name="item_cat" id="modalItemCat" class="form-select" required>
                                <option value="">Seçin…</option>
                                <?php foreach ($categories as $cat):
                                    $isSelected = ($editItem['category_id'] ?? 0) == $cat['id'] || (!$editItem && $preselectedCatId == $cat['id']);
                                ?>
                                    <option value="<?= $cat['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small text-muted">Fiyat (₺) <span class="text-danger">*</span></label>
                            <input type="text" name="item_price" id="modalItemPrice" class="form-control" required
                                   value="<?= $editItem ? number_format((float)$editItem['price'],2,',','') : '' ?>"
                                   placeholder="örn: 120,00">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" id="modalItemName" class="form-control" required
                                   value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                                   placeholder="örn: Karışık Pizza">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Açıklama
                                <span class="text-muted fw-normal">(her satır menüde madde olarak gösterilir)</span>
                            </label>
                            <textarea name="item_desc" id="modalItemDesc" class="form-control" rows="3"
                                      placeholder="Örn: Domates, Mozzarella, Fesleğen&#10;Taş fırında pişirilir"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted"><i class="fa-regular fa-clock me-1"></i>Pişme Süresi</label>
                            <input type="text" name="cooking_time" id="modalItemTime" class="form-control"
                                   value="<?= htmlspecialchars($editItem['cooking_time'] ?? '') ?>"
                                   placeholder="15-20 dk">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted"><i class="fa-solid fa-fire-flame-curved me-1 text-warning"></i>Kalori</label>
                            <input type="text" name="calories" id="modalItemCal" class="form-control"
                                   value="<?= htmlspecialchars($editItem['calories'] ?? '') ?>"
                                   placeholder="450 kcal">
                        </div>

                        <!-- CUSTOM BADGE / ETIKET SUPPORT -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted"><i class="fa-solid fa-tag me-1 text-primary"></i>Etiket <span class="text-muted fw-normal">(seçin / yeni yazın)</span></label>
                            <input type="text" name="badge" id="modalItemBadge" class="form-control" list="badgeDatalist"
                                   value="<?= htmlspecialchars($editItem['badge'] ?? '') ?>"
                                   placeholder="Örn: Yeni, Özel Tarif...">
                            <datalist id="badgeDatalist">
                                <?php foreach ($dbBadges as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach (array_slice($dbBadges, 0, 5) as $b): ?>
                                    <span class="badge bg-light text-secondary border badge-pill-click" style="cursor:pointer; font-size:10px;"
                                          onclick="document.getElementById('modalItemBadge').value='<?= htmlspecialchars($b) ?>'"><?= htmlspecialchars($b) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                <input class="form-check-input" type="checkbox" name="is_chefs_choice" value="1" id="modalChefCheck"
                                       <?= !empty($editItem['is_chefs_choice']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="modalChefCheck">
                                    👨‍🍳 Şefin Seçimi olarak öne çıkar
                                </label>
                            </div>
                        </div>

                        <!-- CUSTOM ALLERGEN SUPPORT -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Alerjen Bilgisi <span class="text-muted">(seçim opsiyonel)</span></label>
                            <div class="p-2 border rounded-3 bg-white d-flex flex-wrap gap-2" style="max-height: 150px; overflow-y: auto;">
                                <?php foreach ($allAllergenOptions as $alg): ?>
                                    <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1 small">
                                        <div class="form-check mb-0 me-1">
                                            <input class="form-check-input allergen-cb" type="checkbox" name="allergens[]" value="<?= htmlspecialchars($alg) ?>"
                                                   id="alg_<?= md5($alg) ?>" <?= in_array($alg, $selectedAllergens, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="alg_<?= md5($alg) ?>"><?= htmlspecialchars($alg) ?></label>
                                        </div>
                                        <a href="menu-urunler.php?remove_allergen=<?= urlencode($alg) ?>"
                                           class="text-danger ms-1 text-decoration-none" title="Bu alerjeni listeden kaldır"
                                           onclick="return confirm('\'<?= htmlspecialchars(addslashes($alg)) ?>\' alerjeni seçeneklerden kaldırılsın mı?');">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Input to register new custom allergen on the fly -->
                            <div class="mt-2 input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-plus me-1"></i>Yeni Alerjen Ekle:</span>
                                <input type="text" name="custom_allergens" id="modalCustomAllergen" class="form-control"
                                       placeholder="Örn: Hardal, Kereviz (virgülle ayırarak yeni alerjen kaydı ekleyebilirsiniz)...">
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:11px;">Eklediğiniz yeni alerjenler veya etiketler sistem tarafından hatırlanır ve sonraki ürünlerde seçenek olarak sunulur.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Görsel <span class="text-muted">(opsiyonel)</span></label>
                            <?php
                            $editImagePreview = !empty($editItem['image_path']) ? menuItemImageUrl($editItem['image_path']) : null;
                            $editImageUrl = (!empty($editItem['image_path']) && menuItemImageIsRemote($editItem['image_path']))
                                ? $editItem['image_path']
                                : '';
                            ?>
                            <?php if ($editImagePreview): ?>
                                <div class="mb-2 d-flex align-items-center gap-2">
                                    <img src="<?= htmlspecialchars($editImagePreview) ?>"
                                         style="height:60px;width:60px;border-radius:10px;object-fit:cover;border:1px solid #eee;" alt="">
                                    <small class="text-muted">Mevcut görsel. Yeni dosya veya URL eklenirse değiştirilir.</small>
                                </div>
                            <?php endif; ?>
                            <input type="url" name="item_image_url" id="modalImageUrl" class="form-control mb-2"
                                   value="<?= htmlspecialchars($editImageUrl) ?>"
                                   placeholder="https://… görsel bağlantısı">
                            <input type="file" name="item_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-3">
                            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                <?= $editItem ? 'Ürünü Güncelle' : 'Ürünü Kaydet' ?>
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.product-list-item {
    transition: background-color 0.15s ease;
}
.product-list-item:hover {
    background-color: #f8fafc;
}
.cat-filter-btn {
    font-weight: 600;
    font-size: 13px;
    padding: 6px 14px;
}
.badge-pill-click:hover {
    background-color: #e2e8f0 !important;
    color: #1e293b !important;
}
.d-none-filter {
    display: none !important;
}
</style>

<script>
var activeCategoryFilter = 'all';
var PAGE_SIZE_ALL = 'all';

function openNewItemModal() {
    var modalEl = document.getElementById('itemModal');
    if (modalEl) {
        // Reset form fields for new item creation
        document.getElementById('modalItemId').value = '0';
        document.getElementById('modalItemName').value = '';
        document.getElementById('modalItemPrice').value = '';
        document.getElementById('modalItemDesc').value = '';
        document.getElementById('modalItemTime').value = '';
        document.getElementById('modalItemCal').value = '';
        document.getElementById('modalItemBadge').value = '';
        document.getElementById('modalImageUrl').value = '';
        document.getElementById('modalCustomAllergen').value = '';
        document.getElementById('modalChefCheck').checked = false;
        document.querySelectorAll('.allergen-cb').forEach(function(cb){ cb.checked = false; });

        document.getElementById('itemModalTitle').innerHTML = '<i class="fa-solid fa-burger me-2 text-primary"></i> Yeni Ürün Ekle';

        var bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
        setTimeout(function(){
            var nameInput = document.getElementById('modalItemName');
            if (nameInput) nameInput.focus();
        }, 300);
    }
}

// Automatically open modal if editing an item, preselected category, or form error
<?php if ($editItem || $preselectedCatId || !empty($errorMsg)): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('itemModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        var bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
});
<?php endif; ?>

function normalizeTurkish(str) {
    if (!str) return '';
    return str.toString()
        .replace(/İ/g, 'i')
        .replace(/I/g, 'i')
        .replace(/ı/g, 'i')
        .replace(/Ğ/g, 'g')
        .replace(/ğ/g, 'g')
        .replace(/Ü/g, 'u')
        .replace(/ü/g, 'u')
        .replace(/Ş/g, 's')
        .replace(/ş/g, 's')
        .replace(/Ö/g, 'o')
        .replace(/ö/g, 'o')
        .replace(/Ç/g, 'c')
        .replace(/ç/g, 'c')
        .toLowerCase();
}

var currentPage = 1;
var PAGE_SIZE = 5; // Sayfa başına 5 ürün göster (hem web hem mobil uyumlu sayfalama)

function filterProductsList(resetPage) {
    if (resetPage !== false) {
        currentPage = 1;
    }
    var rawQuery = (document.getElementById('menuSearchInput').value || '').trim();
    var query = normalizeTurkish(rawQuery);
    var queryWords = query.split(/\s+/).filter(Boolean);
    var items = Array.from(document.querySelectorAll('.product-list-item'));
    var matchingItems = [];

    items.forEach(function(item) {
        var catId = item.getAttribute('data-category-id');
        var searchText = normalizeTurkish(item.getAttribute('data-search-text') || '');
        var name = normalizeTurkish(item.getAttribute('data-item-name') || '');
        var desc = normalizeTurkish(item.getAttribute('data-item-desc') || '');

        var matchesCat = (activeCategoryFilter === 'all' || String(catId) === String(activeCategoryFilter));
        
        var matchesQuery = true;
        if (queryWords.length > 0) {
            for (var i = 0; i < queryWords.length; i++) {
                var w = queryWords[i];
                if (searchText.indexOf(w) === -1 && name.indexOf(w) === -1 && desc.indexOf(w) === -1) {
                    matchesQuery = false;
                    break;
                }
            }
        }

        if (matchesCat && matchesQuery) {
            matchingItems.push(item);
        } else {
            item.classList.add('d-none-filter');
            item.style.setProperty('display', 'none', 'important');
        }
    });

    var totalMatching = matchingItems.length;
    var showAll = PAGE_SIZE === PAGE_SIZE_ALL;
    var totalPages = showAll ? 1 : Math.max(1, Math.ceil(totalMatching / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;

    var startIdx = showAll ? 0 : (currentPage - 1) * PAGE_SIZE;
    var endIdx = showAll ? totalMatching : startIdx + PAGE_SIZE;

    matchingItems.forEach(function(item, idx) {
        if (idx >= startIdx && idx < endIdx) {
            item.classList.remove('d-none-filter');
            item.style.setProperty('display', 'flex', 'important');
        } else {
            item.classList.add('d-none-filter');
            item.style.setProperty('display', 'none', 'important');
        }
    });

    var badge = document.getElementById('visibleProductBadge');
    if (badge) {
        badge.textContent = totalMatching + ' Ürün';
    }

    var noMsg = document.getElementById('noProductMsg');
    if (noMsg) {
        noMsg.style.display = (totalMatching === 0) ? 'block' : 'none';
    }

    renderPagination(totalMatching, totalPages, startIdx, Math.min(endIdx, totalMatching), showAll);
}

function renderPagination(totalCount, totalPages, startIdx, endIdx, showAll) {
    var container = document.getElementById('productPaginationContainer');
    var statusText = document.getElementById('paginationStatusText');
    var list = document.getElementById('productPaginationList');
    var pageSizeSelect = document.getElementById('pageSizeSelect');
    if (!container || !statusText || !list || !pageSizeSelect) return;

    pageSizeSelect.value = showAll ? PAGE_SIZE_ALL : String(PAGE_SIZE);

    if (totalCount === 0) {
        container.style.setProperty('display', 'none', 'important');
        return;
    }

    container.style.setProperty('display', 'flex', 'important');
    if (showAll) {
        statusText.textContent = 'Toplam ' + totalCount + ' ürünün tamamı gösteriliyor';
        list.innerHTML = '';
        return;
    }

    statusText.textContent = totalCount + ' üründen ' + (startIdx + 1) + ' - ' + endIdx + ' arası gösteriliyor';

    var html = '';
    html += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">';
    html += '  <a class="page-link" href="javascript:void(0)" onclick="changePage(' + (currentPage - 1) + ')">&laquo; Önceki</a>';
    html += '</li>';

    for (var p = 1; p <= totalPages; p++) {
        html += '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">';
        html += '  <a class="page-link" href="javascript:void(0)" onclick="changePage(' + p + ')">' + p + '</a>';
        html += '</li>';
    }

    html += '<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '">';
    html += '  <a class="page-link" href="javascript:void(0)" onclick="changePage(' + (currentPage + 1) + ')">Sonraki &raquo;</a>';
    html += '</li>';

    list.innerHTML = html;
}

function changePage(p) {
    currentPage = p;
    filterProductsList(false);
    var containerEl = document.getElementById('productsListContainer');
    if (containerEl) {
        containerEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function changePageSize(value) {
    PAGE_SIZE = value === PAGE_SIZE_ALL ? PAGE_SIZE_ALL : Math.max(1, parseInt(value, 10) || 5);
    currentPage = 1;
    filterProductsList();
}

function clearMenuSearch() {
    var input = document.getElementById('menuSearchInput');
    if (input) {
        input.value = '';
        currentPage = 1;
        filterProductsList();
    }
}

function handleCategoryFilterChange(selectEl) {
    if (!selectEl) return;
    var selectedOption = selectEl.options[selectEl.selectedIndex];
    var catName = selectedOption ? (selectedOption.getAttribute('data-category-name') || selectedOption.text) : 'Tüm Kategoriler';
    filterByCategory(selectEl.value, catName);
}

function filterByCategory(catId, catName, linkEl) {
    activeCategoryFilter = catId;
    currentPage = 1;

    var selectEl = document.getElementById('catFilterSelect');
    var resetBtn = document.getElementById('resetCatFilterBtn');
    if (selectEl && String(selectEl.value) !== String(catId)) {
        selectEl.value = String(catId);
    }

    if (resetBtn) {
        resetBtn.classList.toggle('d-none', catId === 'all');
    }

    filterProductsList();
}

document.addEventListener('DOMContentLoaded', function() {
    filterProductsList(true);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
