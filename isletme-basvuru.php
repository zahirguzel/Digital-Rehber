<?php
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/seo-meta.php';
use App\Services\EmailService;

$db = Database::getInstance()->getPDO();
$successMsg = '';
$errorMsg = '';
$captchaActive = 1;
try {
    $captchaActive = (int) $db->query('SELECT contact_captcha FROM settings WHERE id = 1')->fetchColumn();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFMiddleware::validate()) {
        $errorMsg = "Güvenlik doğrulaması başarısız oldu.";
    } else {
        $businessName = trim($_POST['business_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : -1;
        $correctAnswer = isset($_SESSION['captcha_result']) ? (int) $_SESSION['captcha_result'] : -2;

        if (empty($businessName) || empty($city) || empty($district) || empty($contactName) || empty($phone)) {
            $errorMsg = "Lütfen zorunlu alanları doldurun.";
        } elseif ($captchaActive && $captchaAnswer !== $correctAnswer) {
            $errorMsg = "Güvenlik doğrulaması hatalı! Lütfen tekrar deneyin.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO business_applications (business_name, city, district, contact_name, phone, email, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$businessName, $city, $district, $contactName, $phone, $email, $description]);
                
                $emailService = new EmailService();
                $emailContent = "
                    <p><strong>İşletme Adı:</strong> {$businessName}</p>
                    <p><strong>Yetkili:</strong> {$contactName}</p>
                    <p><strong>Telefon:</strong> {$phone}</p>
                    <p><strong>Bölge/İlçe:</strong> {$city} / {$district}</p>
                    <p><strong>E-posta:</strong> {$email}</p>
                    <p><strong>Açıklama:</strong><br/>{$description}</p>
                ";
                $emailService->sendAdminNotification('Yeni İşletme Başvurusu', $emailContent, $email);

                $successMsg = "Başvurunuz başarıyla alınmıştır. En kısa sürede yetkili ekibimiz sizinle iletişime geçecektir.";
            } catch (Exception $e) {
                $errorMsg = "Başvuru sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
            }
        }
    }
}

// Fetch cities and districts for frontend
$citiesData = [];
try {
    $cRes = $db->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll();
    foreach ($cRes as $c) {
        $dRes = $db->prepare("SELECT name FROM districts WHERE city_id = ? ORDER BY name ASC");
        $dRes->execute([$c['id']]);
        $citiesData[$c['name']] = $dRes->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
$citiesJson = json_encode($citiesData, JSON_UNESCAPED_UNICODE);

$num1 = 0;
$num2 = 0;
if ($captchaActive) {
    $num1 = rand(2, 9);
    $num2 = rand(2, 9);
    $_SESSION['captcha_result'] = $num1 + $num2;
}

$pageTitle = "İşletme Başvurusu";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <h1 class="fw-bold text-navy">İşletmenizi Dijitale Taşıyın</h1>
                <p class="text-muted lead">Hemen ücretsiz başvuru yapın, kendi QR menünüzü ve dijital profilinizi oluşturun.</p>
            </div>
            
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="row g-0">
                    <div class="col-md-5 bg-primary text-white p-5 d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, var(--primary-color) 0%, #c84630 100%);">
                        <h3 class="fw-bold mb-4">Neden Katılmalısınız?</h3>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3 d-flex align-items-start"><i class="fa-solid fa-check-circle mt-1 me-3 fs-5 text-white-50"></i> Kendi Profilinizi Yönetin</li>
                            <li class="mb-3 d-flex align-items-start"><i class="fa-solid fa-check-circle mt-1 me-3 fs-5 text-white-50"></i> Dijital Karekod (QR) Menü Oluşturun</li>
                            <li class="mb-3 d-flex align-items-start"><i class="fa-solid fa-check-circle mt-1 me-3 fs-5 text-white-50"></i> Bölgenizde Öne Çıkın</li>
                            <li class="d-flex align-items-start"><i class="fa-solid fa-check-circle mt-1 me-3 fs-5 text-white-50"></i> Müşteri Yorumlarını Takip Edin</li>
                        </ul>
                    </div>
                    <div class="col-md-7 p-5">
                        
                        <?php if ($successMsg): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                                <div><?= htmlspecialchars($successMsg) ?></div>
                            </div>
                        <?php else: ?>
                        
                            <?php if ($errorMsg): ?>
                                <div class="alert alert-danger mb-4"><?= htmlspecialchars($errorMsg) ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <?= CSRFMiddleware::field() ?>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">İşletme Adı <span class="text-danger">*</span></label>
                                    <input type="text" name="business_name" class="form-control" required placeholder="İşletmenizin Resmi/Tabela Adı">
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Bölge / İl <span class="text-danger">*</span></label>
                                        <select name="city" id="citySelect" class="form-select" required>
                                            <option value="">-- Bölge Seçin --</option>
                                            <?php foreach($citiesData as $cName => $dists): ?>
                                                <option value="<?= htmlspecialchars($cName) ?>"><?= htmlspecialchars($cName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">İlçe <span class="text-danger">*</span></label>
                                        <select name="district" id="districtSelect" class="form-select" required>
                                            <option value="">-- Önce Bölge Seçin --</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Yetkili Kişi <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_name" class="form-control" required placeholder="Adınız Soyadınız">
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Telefon Numarası <span class="text-danger">*</span></label>
                                        <input type="text" name="phone" class="form-control" required placeholder="05XX...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">E-Posta Adresi</label>
                                        <input type="email" name="email" class="form-control" placeholder="Opsiyonel">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">İşletme Hakkında Kısa Bilgi</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Sektörünüz, ürünleriniz veya belirtmek istedikleriniz..."></textarea>
                                </div>
                                
                                <?php if ($captchaActive): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Güvenlik: <?= $num1 ?> + <?= $num2 ?> = ? <span class="text-danger">*</span></label>
                                    <input type="number" name="captcha_answer" class="form-control" required placeholder="İşlemin sonucu">
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                                    Başvurumu Tamamla <i class="fa-solid fa-arrow-right ms-2"></i>
                                </button>
                            </form>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const citiesData = <?= $citiesJson ?>;
    const citySelect = document.getElementById('citySelect');
    const districtSelect = document.getElementById('districtSelect');
    const defaultCity = <?= json_encode($siteSettings['default_city'] ?? seoGetRegionName()) ?>;

    function populateDistricts(selectedCity, selectedDistrict = '') {
        districtSelect.innerHTML = '<option value="">-- İlçe Seçin --</option>';
        if (selectedCity && citiesData[selectedCity]) {
            citiesData[selectedCity].forEach(dist => {
                const opt = document.createElement('option');
                opt.value = dist;
                opt.textContent = dist;
                if (dist === selectedDistrict) opt.selected = true;
                districtSelect.appendChild(opt);
            });
        }
    }

    citySelect?.addEventListener('change', function() {
        populateDistricts(this.value);
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        if (citySelect && defaultCity && citiesData[defaultCity]) {
            citySelect.value = defaultCity;
            populateDistricts(defaultCity);
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>