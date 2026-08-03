<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();
$pdo = $db->getPDO();

require_once '../includes/seo-meta.php';
require_once '../includes/district-helpers.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['district_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    
    if (empty($name) || empty($slug)) {
        $errorMsg = 'İlçe adı ve URL (Slug) zorunludur.';
    } else {
        try {
            $stmt = $db->query(
                'INSERT INTO district_pages (district_name, slug, is_published, sort_order) VALUES (?, ?, ?, 0)',
                [$name, $slug, $isPublished]
            );
            $newId = $db->getPDO()->lastInsertId();
            if (function_exists('logAction')) logAction('create', 'district_pages', $name, $newId);
            $successMsg = 'Yeni ilçe/bölge başarıyla eklendi.';
        } catch (Exception $e) {
            $errorMsg = 'Ekleme hatası: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int) $_GET['id'];
    try {
        $stmtName = $db->query("SELECT district_name FROM district_pages WHERE id = ?", [$delId]);
        $delName = $stmtName->fetchColumn() ?: 'İlçe ID: ' . $delId;
        
        $db->query('DELETE FROM district_pages WHERE id = ?', [$delId]);
        if (function_exists('logAction')) logAction('delete', 'district_pages', $delName, $delId);
        $successMsg = 'İlçe başarıyla silindi.';
    } catch (Exception $e) {
        $errorMsg = 'Silme hatası: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $tagline = trim($_POST['tagline'] ?? '');
    $intro = trim($_POST['intro'] ?? '');
    $highlights = trim($_POST['highlights'] ?? '');
    $blogSlug = trim($_POST['blog_slug'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;

    $faqs = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = trim($_POST['faq_q' . $i] ?? '');
        $a = trim($_POST['faq_a' . $i] ?? '');
        if ($q !== '' && $a !== '') {
            $faqs[] = ['q' => $q, 'a' => $a];
        }
    }

    if ($id <= 0 || $intro === '') {
        $errorMsg = 'Giriş metni zorunludur.';
        $action = 'edit';
    } else {
        try {
            $stmt = $db->query(
                'UPDATE district_pages
                 SET tagline = ?, intro = ?, highlights = ?, blog_slug = ?, faqs_json = ?, meta_description = ?, is_published = ?
                 WHERE id = ?'
            , [
                $tagline,
                $intro,
                $highlights,
                $blogSlug !== '' ? $blogSlug : null,
                json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $metaDescription !== '' ? $metaDescription : null,
                $isPublished,
                $id,
            ]);
            if (function_exists('logAction')) logAction('update', 'district_pages', 'ID: ' . $id, $id);
            $successMsg = 'İlçe rehber sayfası güncellendi.';
            $action = 'list';
        } catch (Exception $e) {
            $errorMsg = 'Kayıt hatası: ' . $e->getMessage();
            $action = 'edit';
        }
    }
}

$page = null;
$faqRows = getDistrictDefaultFaqs('Sizin Bölgeniz', null);
if ($action === 'edit' && $id > 0) {
    $row = getDistrictPageById($pdo, $id);
    if (!$row) {
        $errorMsg = 'İlçe kaydı bulunamadı.';
        $action = 'list';
    } else {
        $page = formatDistrictPageRow($row);
        $faqRows = $page['faqs'];
    }
}

$pages = [];
$blogs = [];
if ($action === 'list') {
    $pages = getDistrictPagesList($pdo);
    try {
        $blogs = $db->query('SELECT slug, title FROM blogs ORDER BY title ASC')->fetchAll();
    } catch (Exception $e) {
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$publicBase = rtrim($protocol . $host . str_replace('admin/ilceler.php', '', $_SERVER['PHP_SELF'] ?? '/'), '/');

$pageTitle = 'İlçe Rehber Sayfaları';
include 'includes/header.php';
?>

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

<?php
if ($action === 'list'):
    try {
        $settingsQuery = $db->query("SELECT default_city FROM settings WHERE id = 1")->fetch();
        $siteDefaultCity = $settingsQuery['default_city'] ?? 'Ana Bölgeniz';
    } catch (Exception $e) {
        $siteDefaultCity = 'Ana Bölgeniz';
    }
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i> Seçili Ana Bölge: <?= htmlspecialchars($siteDefaultCity) ?> (İlçe Rehber Sayfaları)</h5>
            <a href="<?= htmlspecialchars($publicBase) ?>/bolgeler" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Canlı Hub Sayfası
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">İlçe</th>
                            <th>URL</th>
                            <th>Kısa Açıklama</th>
                            <th>İşletme</th>
                            <th>Durum</th>
                            <th class="text-end pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
foreach ($pages as $item): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-navy"><?= htmlspecialchars($item['district_name']) ?></td>
                            <td class="font-monospace small text-muted">/ilce/<?= htmlspecialchars($item['slug']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_strimwidth($item['tagline'] ?? '', 0, 60, '...')) ?></td>
                            <td><span class="badge bg-secondary"><?= (int) $item['business_count'] ?></span></td>
                            <td>
                                <?php
if ((int) $item['is_published']): ?>
                                    <span class="badge bg-success">Yayında</span>
                                <?php
else: ?>
                                    <span class="badge bg-warning text-dark">Taslak</span>
                                <?php
endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group gap-2">
                                    <a href="<?= htmlspecialchars($publicBase) ?>/ilce/<?= htmlspecialchars($item['slug']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Önizle"><i class="fa-solid fa-eye"></i></a>
                                    <a href="ilceler.php?action=edit&id=<?= (int) $item['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php
endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
elseif ($action === 'edit' && $page): ?>
    <?php
if (empty($blogs)) {
        try {
            $blogs = $db->query('SELECT slug, title FROM blogs ORDER BY title ASC')->fetchAll();
        } catch (Exception $e) {
        }
    }
    while (count($faqRows) < 3) {
        $faqRows[] = ['q' => '', 'a' => ''];
    }
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid fa-pen text-primary me-2"></i>
                <?= htmlspecialchars($page['district_name']) ?> — İlçe Rehberi
            </h5>
            <a href="<?= htmlspecialchars($publicBase) ?>/ilce/<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-eye me-1"></i> Canlı Sayfayı Aç
            </a>
        </div>
        <div class="card-body p-4">
            <form method="POST">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">

                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Kısa Tanım (Tagline)</label>
                        <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($page['tagline']) ?>" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Blog Rehberi</label>
                        <select name="blog_slug" class="form-select">
                            <option value="">Blog bağlantısı yok</option>
                            <?php
foreach ($blogs as $blog): ?>
                            <option value="<?= htmlspecialchars($blog['slug']) ?>" <?= ($page['blog_slug'] ?? '') === $blog['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($blog['title']) ?>
                            </option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Giriş Metni (GEO) <span class="text-danger">*</span></label>
                        <textarea name="intro" class="form-control" rows="5" required><?= htmlspecialchars($page['intro']) ?></textarea>
                        <div class="form-text">AI arama motorları için net, alıntılanabilir ilçe tanımı.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Öne Çıkan Maddeler</label>
                        <textarea name="highlights" class="form-control" rows="4" placeholder="Her satıra bir madde"><?= htmlspecialchars(implode("\n", $page['highlights'] ?? [])) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">SEO Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2" maxlength="320"><?= htmlspecialchars($page['meta_description'] ?? '') ?></textarea>
                        <div class="form-text">Boş bırakılırsa otomatik üretilir.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" <?= (int) $page['is_published'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="is_published">Sayfa yayında</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold text-navy mb-3">SSS (FAQPage Schema)</h6>
                <?php
for ($i = 0; $i < 3; $i++): ?>
                <div class="border rounded p-3 mb-3 bg-light">
                    <label class="form-label fw-semibold small">Soru <?= $i + 1 ?></label>
                    <input type="text" name="faq_q<?= $i + 1 ?>" class="form-control mb-2" value="<?= htmlspecialchars($faqRows[$i]['q'] ?? '') ?>">
                    <label class="form-label fw-semibold small">Cevap <?= $i + 1 ?></label>
                    <textarea name="faq_a<?= $i + 1 ?>" class="form-control" rows="2"><?= htmlspecialchars($faqRows[$i]['a'] ?? '') ?></textarea>
                </div>
                <?php
endfor; ?>

                <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
                    <a href="ilceler.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Listeye Dön</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
<?php
endif; ?>

<?php
include 'includes/footer.php'; ?>
