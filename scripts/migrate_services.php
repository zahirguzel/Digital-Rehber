<?php
require_once __DIR__ . '/../autoload.php';

$db = Database::getInstance();
$pdo = $db->getPDO();

// 1) Add cta_url and cta_type columns if not exist
try {
    $pdo->exec("ALTER TABLE services ADD COLUMN cta_url VARCHAR(500) DEFAULT NULL AFTER subject");
    echo "cta_url column added.\n";
} catch (Exception $e) {
    echo "cta_url column already exists or error: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE services ADD COLUMN cta_type VARCHAR(20) NOT NULL DEFAULT 'iletisim' AFTER cta_url");
    echo "cta_type column added.\n";
} catch (Exception $e) {
    echo "cta_type column already exists or error: " . $e->getMessage() . "\n";
}

// 2) Insert Zak Yazılım services (yazılım çözümleri)
$zakyazilimServices = [
    [
        'title'       => 'Barkod Sistemi',
        'slug'        => 'barkod-sistemi',
        'icon'        => 'fa-solid fa-barcode',
        'description' => 'Hızlı satış, stok takibi ve detaylı raporlama sunan profesyonel market ve mağaza çözümleri.',
        'subject'     => 'Barkod Sistemi Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=Barkod%20sistemi%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Restoran Yazılımı',
        'slug'        => 'restoran-yazilimi',
        'icon'        => 'fa-solid fa-utensils',
        'description' => 'Adisyon takibi, masa yönetimi ve paket servis sistemleri ile işletmenizi dijitalleştirin.',
        'subject'     => 'Restoran Yazılımı Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=Restoran%20yaz%C4%B1l%C4%B1m%C4%B1%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Kuyumcu Yazılımı',
        'slug'        => 'kuyumcu-yazilimi',
        'icon'        => 'fa-solid fa-gem',
        'description' => 'Hassas stok yönetimi, işçilik hesaplama ve anlık altın kuru entegrasyonlu özel çözümler.',
        'subject'     => 'Kuyumcu Yazılımı Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=Kuyumcu%20yaz%C4%B1l%C4%B1m%C4%B1%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'E-Ticaret Çözümleri',
        'slug'        => 'e-ticaret-cozumleri',
        'icon'        => 'fa-solid fa-cart-shopping',
        'description' => 'Güvenli ödeme sistemleri ve kullanıcı odaklı arayüzlerle online satış platformları.',
        'subject'     => 'E-Ticaret Çözümleri Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=E-Ticaret%20%C3%A7%C3%B6z%C3%BCmleriniz%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Rent a Car Yazılımı',
        'slug'        => 'rent-a-car-yazilimi',
        'icon'        => 'fa-solid fa-car',
        'description' => 'Araç kiralama takip, rezervasyon yönetimi ve sözleşme sistemleri ile filo kontrolü.',
        'subject'     => 'Rent a Car Yazılımı Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=Rent%20a%20car%20yaz%C4%B1l%C4%B1m%C4%B1%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Mobil Uygulama Geliştirme',
        'slug'        => 'mobil-uygulama',
        'icon'        => 'fa-solid fa-mobile-screen-button',
        'description' => 'Android ve iOS platformları için performanslı ve şık mobil uygulama geliştirme.',
        'subject'     => 'Mobil Uygulama Geliştirme Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=Mobil%20uygulama%20geli%C5%9Ftirme%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Güvenlik Sistemleri',
        'slug'        => 'guvenlik-sistemleri',
        'icon'        => 'fa-solid fa-shield-halved',
        'description' => 'Kamera ve alarm sistemleri ile iş yerinizi 7/24 güven altına alan teknolojik çözümler.',
        'subject'     => 'Güvenlik Sistemleri Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=G%C3%BCvenlik%20sistemleri%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
    [
        'title'       => 'Özel Yazılım Geliştirme',
        'slug'        => 'ozel-yazilim',
        'icon'        => 'fa-solid fa-laptop-code',
        'description' => 'İşletmenizin ihtiyaçlarına tam uyan, ölçeklenebilir ve modern yazılım projeleri.',
        'subject'     => 'Özel Yazılım Geliştirme Hakkında Bilgi',
        'cta_url'     => 'https://wa.me/905357839248?text=%C3%96zel%20yaz%C4%B1l%C4%B1m%20projeleri%20hakk%C4%B1nda%20bilgi%20almak%20istiyorum.',
        'cta_type'    => 'whatsapp',
    ],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO services (title, slug, icon, description, subject, cta_url, cta_type) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($zakyazilimServices as $srv) {
    $stmt->execute([
        $srv['title'],
        $srv['slug'],
        $srv['icon'],
        $srv['description'],
        $srv['subject'],
        $srv['cta_url'],
        $srv['cta_type'],
    ]);
    echo "Inserted: " . $srv['title'] . "\n";
}

echo "\nDone. Total services:\n";
$rows = $pdo->query("SELECT id, title, cta_type FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  [{$r['id']}] {$r['title']} ({$r['cta_type']})\n";
}
