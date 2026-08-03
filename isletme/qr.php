<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'QR Kodum';
require_once __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getPDO();
$bizId   = (int)($_SESSION['biz_id'] ?? 0);
$bizSlug = $_SESSION['biz_slug'] ?? '';
$bizName = $_SESSION['biz_name'] ?? 'İşletme';

// Build base URL (works both locally and on production)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain   = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('isletme/qr.php', '', $_SERVER['PHP_SELF']), '/');
$baseUrl  = $protocol . $domain . $basePath;

// Business digital card URL — .htaccess routes /slug → qr.php?slug=slug
$profileUrl = $baseUrl . '/' . rawurlencode($bizSlug);

// Digital menu URL
$menuUrl = $baseUrl . '/menu/' . rawurlencode($bizSlug);

// Fetch business data
$biz = null;
$hasMenu = false;
try {
    $biz = $db->query("SELECT logo_path, cover_image_path, theme_color, menu_url FROM businesses WHERE id = $bizId")->fetch(PDO::FETCH_ASSOC);
    // Check if business has any active menu categories
    $menuCount = $db->prepare("SELECT COUNT(*) FROM menu_categories WHERE business_id = ? AND is_active = 1");
    $menuCount->execute([$bizId]);
    $hasMenu = $menuCount->fetchColumn() > 0;
    // Also accept external menu_url as "has menu"
    if (!$hasMenu && !empty($biz['menu_url'])) {
        $hasMenu = true;
        $menuUrl = $biz['menu_url'];
    }
} catch (Exception $e) {}

$themeColor = !empty($biz['theme_color']) ? $biz['theme_color'] : '#E0533C';

// Build QR code image URLs using qrserver.com (free, reliable, no API key)
$profileQr = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($profileUrl) . "&color=1E293B&bgcolor=FFFFFF&margin=10&format=png";
$menuQr    = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($menuUrl)    . "&color=1E293B&bgcolor=FFFFFF&margin=10&format=png";
?>


<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="mb-0 fw-bold text-navy"><i class="fa-solid fa-qrcode me-2 text-primary"></i> QR Kodlarım</h3>
        <p class="text-muted mb-0 mt-1 small">Dijital kartvizit ve menü QR kodlarınızı yönetin.</p>
    </div>
    <a href="<?= htmlspecialchars($profileUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-external-link-alt me-1"></i> Profilimi Gör
    </a>
</div>

<!-- QR Cards Row -->
<div class="row g-4">

<?php $colClass = $hasMenu ? 'col-lg-6' : 'col-lg-8 mx-auto'; ?>
    <!-- ─── Profil / Kartvizit QR ─── -->
    <?php /* always shown */ ?>
    <div class="<?= $colClass ?>">
        <div class="card border-0 h-100 shadow-sm" style="border-radius:16px; overflow:hidden;">
            <div class="card-header border-0 py-3 px-4" style="background: linear-gradient(135deg, <?= $themeColor ?> 0%, <?= $themeColor ?>cc 100%);">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="fa-solid fa-id-card fs-5"></i>
                    <div>
                        <div class="fw-bold">Dijital Kartvizit QR</div>
                        <div class="small" style="opacity:.8;">İşletme profil sayfanız</div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center">

                <!-- QR Code Box -->
                <div class="p-3 bg-white border rounded-4 shadow-sm mb-4 d-inline-block" style="border: 2px solid #E2E8F0;">
                    <img id="profileQrImg" src="<?= htmlspecialchars($profileQr) ?>" alt="Kartvizit QR Kod" style="width:220px; height:220px; display:block;">
                </div>

                <h6 class="fw-bold text-navy mb-1 text-center"><?= htmlspecialchars($bizName) ?></h6>
                <p class="text-muted small text-center mb-4">Müşterileriniz bu kodu okutarak işletme profilinize, fotoğraflarınıza ve iletişim bilgilerinize ulaşır.</p>

                <!-- URL Box -->
                <div class="input-group mb-3 w-100">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-link fa-sm"></i></span>
                    <input type="text" id="profileUrl" class="form-control bg-light border-start-0 small" value="<?= htmlspecialchars($profileUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('profileUrl', this)" title="Kopyala">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>

                <!-- Download Buttons -->
                <div class="d-flex gap-2 w-100 flex-wrap">
                    <a href="<?= htmlspecialchars($profileUrl) ?>" target="_blank" class="btn btn-outline-danger flex-fill fw-semibold">
                        <i class="fa-solid fa-eye me-1"></i> Sayfayı Aç
                    </a>
                    <a href="<?= htmlspecialchars($profileQr) ?>" download="kartvizit_qr_<?= htmlspecialchars($bizSlug) ?>.png" target="_blank"
                       class="btn btn-primary flex-fill fw-semibold">
                        <i class="fa-solid fa-download me-1"></i> PNG
                    </a>
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=800x800&data=<?= urlencode($profileUrl) ?>&color=1E293B&bgcolor=FFFFFF&margin=10&format=svg"
                       download="kartvizit_qr_<?= htmlspecialchars($bizSlug) ?>.svg" target="_blank"
                       class="btn btn-outline-secondary flex-fill fw-semibold">
                        <i class="fa-solid fa-vector-square me-1"></i> SVG
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Dijital Menü QR ─── -->
    <?php if ($hasMenu): ?>
    <div class="col-lg-6">
        <div class="card border-0 h-100 shadow-sm" style="border-radius:16px; overflow:hidden;">
            <div class="card-header border-0 py-3 px-4" style="background: linear-gradient(135deg, #1E293B 0%, #334155 100%);">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="fa-solid fa-utensils fs-5"></i>
                    <div>
                        <div class="fw-bold">Dijital Menü QR</div>
                        <div class="small" style="opacity:.8;">Ürün ve fiyat listeniz</div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center">

                <!-- QR Code Box -->
                <div class="p-3 bg-white border rounded-4 shadow-sm mb-4 d-inline-block" style="border: 2px solid #E2E8F0;">
                    <img id="menuQrImg" src="<?= htmlspecialchars($menuQr) ?>" alt="Menü QR Kod" style="width:220px; height:220px; display:block;">
                </div>

                <h6 class="fw-bold text-navy mb-1 text-center">Dijital Menü — <?= htmlspecialchars($bizName) ?></h6>
                <p class="text-muted small text-center mb-4">Masalara, vitrine veya kasaya asın. Müşteriler kodu okuttuğunda fiyat listenize anında ulaşır.</p>

                <!-- URL Box -->
                <div class="input-group mb-3 w-100">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-link fa-sm"></i></span>
                    <input type="text" id="menuUrl" class="form-control bg-light border-start-0 small" value="<?= htmlspecialchars($menuUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('menuUrl', this)" title="Kopyala">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>

                <!-- Download Buttons -->
                <div class="d-flex gap-2 w-100 flex-wrap">
                    <a href="<?= htmlspecialchars($menuUrl) ?>" target="_blank" class="btn btn-outline-danger flex-fill fw-semibold">
                        <i class="fa-solid fa-eye me-1"></i> Sayfayı Aç
                    </a>
                    <a href="<?= htmlspecialchars($menuQr) ?>" download="menu_qr_<?= htmlspecialchars($bizSlug) ?>.png" target="_blank"
                       class="btn btn-primary flex-fill fw-semibold">
                        <i class="fa-solid fa-download me-1"></i> PNG
                    </a>
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=800x800&data=<?= urlencode($menuUrl) ?>&color=1E293B&bgcolor=FFFFFF&margin=10&format=svg"
                       download="menu_qr_<?= htmlspecialchars($bizSlug) ?>.svg" target="_blank"
                       class="btn btn-outline-secondary flex-fill fw-semibold">
                        <i class="fa-solid fa-vector-square me-1"></i> SVG
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:16px;border:2px dashed #E2E8F0 !important;">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:#F1F5F9;">
                    <i class="fa-solid fa-utensils fs-3 text-muted"></i>
                </div>
                <h6 class="fw-bold text-navy mb-2">Henüz Menünüz Yok</h6>
                <p class="text-muted small mb-4">Dijital menü oluşturduğunuzda menü QR kodunuz otomatik olarak burada görünecektir.</p>
                <a href="menu.php" class="btn btn-primary rounded-pill px-4 fw-semibold">
                    <i class="fa-solid fa-plus me-2"></i>Menü Oluştur
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ─── Tips & How-to Section ─── -->
<div class="row g-4 mt-2">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:16px;">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold text-navy"><i class="fa-solid fa-lightbulb me-2 text-warning"></i> QR Kodu Nasıl Kullanırım?</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start p-3 rounded-3" style="background:#f8fafc;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white" style="width:38px;height:38px;background:<?= $themeColor ?>;font-size:16px;">
                                <i class="fa-solid fa-print"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small text-navy mb-1">Yazdır & Yapıştır</div>
                                <div class="text-muted" style="font-size:12px;">PNG veya SVG olarak indirip A4 veya standart etiket kâğıdına yazdırın. Masa, kasa ve vitrinine yapıştırın.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start p-3 rounded-3" style="background:#f8fafc;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white" style="width:38px;height:38px;background:#10B981;font-size:16px;">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small text-navy mb-1">Sosyal Medyada Paylaş</div>
                                <div class="text-muted" style="font-size:12px;">Profil linkini Instagram, WhatsApp ve Facebook biyografine ekleyerek müşterilerinizin sizi bulmasını kolaylaştırın.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start p-3 rounded-3" style="background:#f8fafc;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white" style="width:38px;height:38px;background:#3B82F6;font-size:16px;">
                                <i class="fa-solid fa-utensils"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small text-navy mb-1">Menü QR'ı Masaya Koy</div>
                                <div class="text-muted" style="font-size:12px;">Kâğıt menü yerine dijital menü QR kodunu masa üstlerine yerleştirerek her zaman güncel fiyat listesi sunun.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex gap-3 align-items-start p-3 rounded-3" style="background:#f8fafc;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white" style="width:38px;height:38px;background:#8B5CF6;font-size:16px;">
                                <i class="fa-solid fa-star"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small text-navy mb-1">Değerlendirme Toplayın</div>
                                <div class="text-muted" style="font-size:12px;">Müşterilerinizden QR kartvizit üzerinden profil sayfanızı ziyaret edip yorum yapmalarını isteyin.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-white h-100" style="border-radius:16px; background:linear-gradient(135deg, <?= $themeColor ?> 0%, <?= $themeColor ?>99 100%);">
            <div class="card-body p-4 d-flex flex-column justify-content-center">
                <i class="fa-solid fa-circle-check fs-1 mb-3" style="opacity:.9;"></i>
                <h5 class="fw-bold mb-2">Uygulama Gerektirmez</h5>
                <p class="mb-0" style="opacity:.85;font-size:14px;line-height:1.6;">
                    Müşterileriniz telefon kamerasıyla QR kodu okuttuğunda herhangi bir uygulama indirmeden menünüze ve profilinize anında erişir.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(inputId, btn) {
    const input = document.getElementById(inputId);
    navigator.clipboard.writeText(input.value).then(function() {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
        btn.classList.add('btn-success', 'text-white');
        btn.classList.remove('btn-outline-secondary');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('btn-success', 'text-white');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function() {
        input.select();
        document.execCommand('copy');
        alert('Link kopyalandı!');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>