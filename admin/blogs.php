<?php
require_once '../autoload.php';
require_once 'includes/auth.php';

requireRole(['superadmin', 'admin']);



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

$db = Database::getInstance();

$pageTitle = 'Blog Yazıları Yönetimi';
$successMsg = '';
$errorMsg = '';

// Helper function for uploading blog images
function handleBlogUpload($fileKey, $fallbackUrlKey, $currentValue = '') {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $fileName = 'blog_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($_FILES[$fileKey]['name']));
        $targetDir = '../public/uploads/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetDir . $fileName)) {
            return $fileName;
        }
    }
    
    if (!empty($_POST[$fallbackUrlKey])) {
        return trim($_POST[$fallbackUrlKey]);
    }
    
    return $currentValue;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete handler
if ($action === 'delete' && $id > 0) {
    try {
        $stmtCur = $db->query("SELECT title, image_path FROM blogs WHERE id = ?", [$id]);
        $curData = $stmtCur->fetch();
        $currentImage = $curData['image_path'] ?? '';
        $delTitle = $curData['title'] ?? 'Blog ID: ' . $id;

        $stmt = $db->query("DELETE FROM blogs WHERE id = ?", [$id]);

        if (!empty($currentImage) && strpos($currentImage, 'http') !== 0) {
            @unlink('../public/uploads/' . $currentImage);
        }

        if (function_exists('logAction')) logAction('delete', 'blogs', $delTitle, $id);

        header("Location: blogs.php?success=1");
        exit;
    } catch (Exception $e) {
        $errorMsg = "Yazı silinirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $successMsg = "İşlem başarıyla gerçekleştirildi.";
}

// Add / Edit form submit handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $summary = trim($_POST['summary']);
    $content = trim($_POST['content']);
    $meta_description = trim($_POST['meta_description']);
    $meta_keywords = trim($_POST['meta_keywords']);
    
    // Slug generation fallback
    if (empty($slug)) {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }

    if (empty($title) || empty($content)) {
        $errorMsg = "Başlık ve makale içeriği boş bırakılamaz.";
    } else {
        $remove_image = isset($_POST['remove_image']) ? 1 : 0;
        $currentImage = $_POST['current_image'] ?? '';

        if ($remove_image) {
            $image_path = '';
            if (!empty($currentImage) && strpos($currentImage, 'http') !== 0) {
                @unlink('../public/uploads/' . $currentImage);
            }
        } else {
            $image_path = handleBlogUpload('image_file', 'image_url', $currentImage);
            if ($image_path !== $currentImage && !empty($currentImage) && strpos($currentImage, 'http') !== 0) {
                @unlink('../public/uploads/' . $currentImage);
            }
        }
        
        // Map new action to add if submitted from form
        $action_post = $_POST['action'] ?? $action;
        
        if ($action_post === 'add' || $action === 'add') {
            try {
                // Check if slug is unique
                $stmtSlug = $db->query("SELECT COUNT(*) FROM blogs WHERE slug = ?", [$slug]);
                if ($stmtSlug->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }
                
                $stmt = $db->query("INSERT INTO blogs (title, slug, summary, content, image_path, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?)", [$title, $slug, $summary, $content, $image_path, $meta_description, $meta_keywords]);
                $newId = $db->getPDO()->lastInsertId();
                if (function_exists('logAction')) logAction('create', 'blogs', $title, $newId);
                header("Location: blogs.php?success=1");
                exit;
            } catch (Exception $e) {
                $errorMsg = "Yazı eklenirken hata oluştu: " . $e->getMessage();
            }
        } elseif ($action_post === 'edit' || ($action === 'edit' && $id > 0)) {
            try {
                // Check if slug is unique to other blogs
                $stmtSlug = $db->query("SELECT COUNT(*) FROM blogs WHERE slug = ? AND id != ?", [$slug, $id]);
                if ($stmtSlug->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }
                
                $stmt = $db->query("UPDATE blogs SET title = ?, slug = ?, summary = ?, content = ?, image_path = ?, meta_description = ?, meta_keywords = ? WHERE id = ?", [$title, $slug, $summary, $content, $image_path, $meta_description, $meta_keywords, $id]);
                if (function_exists('logAction')) logAction('update', 'blogs', $title, $id);
                header("Location: blogs.php?success=1");
                exit;
            } catch (Exception $e) {
                $errorMsg = "Yazı güncellenirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Fetch single blog post if editing
$blogData = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->query("SELECT * FROM blogs WHERE id = ?", [$id]);
    $blogData = $stmt->fetch();
    if (!$blogData) {
        header("Location: blogs.php");
        exit;
    }
}

// Fetch all posts for listing
$blogs = [];
if ($action === 'list') {
    try {
        $search = $_GET['search'] ?? '';
        if (!empty($search)) {
            $stmt = $db->query("SELECT * FROM blogs WHERE title LIKE ? OR summary LIKE ? ORDER BY created_at DESC", ["%$search%", "%$search%"]);
        } else {
            $stmt = $db->query("SELECT * FROM blogs ORDER BY created_at DESC");
        }
        $blogs = $stmt->fetchAll();
    } catch (Exception $e) {
        $errorMsg = "Blog yazıları yüklenirken hata oluştu.";
    }
}

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

<?php
if ($action === 'list'): ?>
    <!-- LISTING BLOCK -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-newspaper me-2 text-primary"></i> Blog Yazıları Listesi</h5>
            <a href="blogs.php?action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> Yeni Yazı Ekle</a>
        </div>
        <div class="card-body bg-light border-bottom p-3">
            <form action="" method="GET" class="row g-2">
                <div class="col-md-9">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Yazı başlığı veya özeti ara..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-navy btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Ara</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">Görsel</th>
                            <th>Yazı Bilgisi</th>
                            <th>Tarih</th>
                            <th class="text-end pe-4" style="width: 150px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
if (empty($blogs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">Henüz hiç blog yazısı eklenmemiş.</td>
                            </tr>
                        <?php
else: ?>
                            <?php
foreach ($blogs as $post): 
                                $img = $post['image_path'] ?: 'default_cover.jpg';
                                $imgUrl = (strpos($img, 'http') === 0) ? $img : '/public/uploads/' . $img;
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Cover" class="border" style="width: 50px; height: 35px; object-fit: cover; border-radius: var(--radius);">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-navy"><?= htmlspecialchars($post['title']) ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 500px;"><?= htmlspecialchars($post['summary']) ?></div>
                                    </td>
                                    <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group gap-2">
                                            <a href="../blog/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn btn-outline-info btn-sm" title="Önizle"><i class="fa-solid fa-eye"></i></a>
                                            <a href="blogs.php?action=edit&id=<?= $post['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Düzenle"><i class="fa-solid fa-pen"></i></a>
                                            <a href="blogs.php?action=delete&id=<?= $post['id'] ?>" class="btn btn-outline-danger btn-sm confirm-btn" data-confirm="Bu blog yazısını kalıcı olarak silmek istediğinizden emin misiniz?" data-confirm-title="Yazıyı Sil" title="Sil"><i class="fa-solid fa-trash"></i></a>
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

<?php
elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD / EDIT FORM BLOCK -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid <?= $action === 'add' ? 'fa-plus text-primary' : 'fa-pen text-success' ?> me-2"></i>
                <?= $action === 'add' ? 'Yeni Blog Yazısı Ekle' : 'Blog Yazısını Düzenle' ?>
            </h5>
            <a href="blogs.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Listeye Dön</a>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" enctype="multipart/form-data">
    <?= CSRFMiddleware::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'add' ? 'add' : 'edit' ?>">
                <input type="hidden" name="current_image" value="<?= htmlspecialchars($blogData['image_path'] ?? '') ?>">
                
                <div class="row g-3">
                    <!-- Title -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Makale Başlığı <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="blogTitle" class="form-control" placeholder="Örn:  Gezilecek En İyi 10 Yer" value="<?= htmlspecialchars($blogData['title'] ?? '') ?>" required>
                    </div>
                    
                    <!-- Slug -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">SEO URL (Slug)</label>
                        <input type="text" name="slug" id="blogSlug" class="form-control" placeholder="otomatik-olusturulur" value="<?= htmlspecialchars($blogData['slug'] ?? '') ?>">
                        <span class="text-muted small" style="font-size: 11px;">Boş bırakırsanız başlığa göre otomatik üretilir.</span>
                    </div>
                    
                    <!-- Cover Image -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kapak Görseli</label>
                        <input type="file" name="image_file" class="form-control mb-2">
                        <input type="text" name="image_url" class="form-control form-control-sm" placeholder="Veya harici bir resim URL'si girin..." value="<?= htmlspecialchars((!empty($blogData['image_path']) && strpos($blogData['image_path'], 'http') === 0) ? $blogData['image_path'] : '') ?>">
                        <?php if (!empty($blogData['image_path'])): 
                            $previewImg = (strpos($blogData['image_path'], 'http') === 0) ? $blogData['image_path'] : '/public/uploads/' . $blogData['image_path'];
                        ?>
                            <div class="mt-3 p-2 bg-light border rounded d-flex align-items-center gap-3">
                                <img src="<?= htmlspecialchars($previewImg) ?>" alt="Önizleme" style="width: 140px; height: 90px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid #ddd;">
                                <div>
                                    <div class="small fw-bold text-navy mb-1"><i class="fa-solid fa-image me-1 text-primary"></i> Şu Anki Kapak Görseli</div>
                                    <div class="small text-muted mb-2" style="word-break: break-all; font-size: 11px;"><?= htmlspecialchars($blogData['image_path']) ?></div>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" name="remove_image" id="removeImageCheck" value="1">
                                        <label class="form-check-label small text-danger fw-semibold" for="removeImageCheck" style="cursor: pointer;">
                                            <i class="fa-solid fa-trash-can me-1"></i> Mevcut Görseli Kaldır
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Excerpt / Summary -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Makale Özeti / Kısa Açıklama <span class="text-danger">*</span></label>
                        <textarea name="summary" class="form-control" rows="2" placeholder="Listeleme sayfasında ve SEO açıklamasında kullanılacak kısa özet metin..." required><?= htmlspecialchars($blogData['summary'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Makale İçeriği <span class="text-danger">*</span></label>
                        <textarea name="content" id="blogContent" class="form-control" rows="14" placeholder="Makale içeriğini buraya yazın..." required><?= htmlspecialchars($blogData['content'] ?? '') ?></textarea>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="fw-bold text-navy"><i class="fa-solid fa-magnifying-glass me-1"></i> Arama Motoru Optimizasyonu (SEO)</h6>
                    
                    <!-- Meta Description -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SEO Açıklaması (Meta Description)</label>
                        <input type="text" name="meta_description" class="form-control" placeholder="Arama motoru listelemesi için kısa özet..." value="<?= htmlspecialchars($blogData['meta_description'] ?? '') ?>">
                    </div>
                    
                    <!-- Meta Keywords -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SEO Anahtar Kelimeleri</label>
                        <input type="text" name="meta_keywords" class="form-control" placeholder="anahtar, kelimeler, hatay, gezi" value="<?= htmlspecialchars($blogData['meta_keywords'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="mt-4 border-top pt-4 d-flex justify-content-end gap-2">
                    <a href="blogs.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-xmark me-1"></i> İptal</a>
                    <button type="submit" class="btn btn-primary px-5"><i class="fa-solid fa-floppy-disk me-1"></i> Yazıyı Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS Helper for dynamic slugging and editor buttons -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('blogTitle');
            const slugInput = document.getElementById('blogSlug');
            
            if (titleInput && slugInput) {
                titleInput.addEventListener('input', function() {
                    // Only auto-slug if slug field is empty or was auto-generated
                    if (slugInput.dataset.edited !== 'true') {
                        slugInput.value = generateSlug(titleInput.value);
                    }
                });
                
                slugInput.addEventListener('input', function() {
                    slugInput.dataset.edited = 'true';
                });
            }
        });
        
        function generateSlug(text) {
            const trMap = {
                'ç': 'c', 'Ç': 'c', 'ğ': 'g', 'Ğ': 'g', 'ı': 'i', 'I': 'i', 'İ': 'i',
                'ö': 'o', 'Ö': 'o', 'ş': 's', 'Ş': 's', 'ü': 'u', 'Ü': 'u', ' ': '-'
            };
            let slug = text.toString().toLowerCase();
            for (let key in trMap) {
                slug = slug.replaceAll(key, trMap[key]);
            }
            return slug.replace(/[^a-z0-9-]/g, '')
                       .replace(/-+/g, '-')
                       .replace(/^-+|-+$/g, '');
        }
        
    </script>
    
    <!-- TinyMCE Rich Text Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof tinymce !== 'undefined') {
                tinymce.init({
                    selector: '#blogContent',
                    height: 440,
                    menubar: false,
                    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat | code',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 15px; color: #1e293b; line-height: 1.6; }'
                });
            }
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
