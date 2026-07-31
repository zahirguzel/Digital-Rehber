<?php
/**
 * Nöbetçi Eczane Örnek / Yerel Seed Scripti
 * API anahtarı olmadan veya test amacıyla veritabanını bugünün ve yarının örnek nöbetçi eczaneleri ile doldurur.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/duty-pharmacy-helpers.php';
require_once __DIR__ . '/../includes/seo-meta.php';

$today = dutyPharmacyGetEffectiveToday();
$tomorrow = (new DateTime($today))->modify('+1 day')->format('Y-m-d');
$region = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Kıbrıs';

$samplePharmacies = [
    // Bugünün Nöbetçi Eczaneleri
    [
        'duty_date' => $today,
        'name' => 'Lefkoşa Merkez Eczanesi',
        'address' => 'Bedrettin Demirel Caddesi No:45, Kumsal, Lefkoşa',
        'phone' => '0392 227 12 34',
        'district' => 'Lefkoşa',
        'district_slug' => 'lefkosa',
        'latitude' => 35.1954,
        'longitude' => 33.3610,
    ],
    [
        'duty_date' => $today,
        'name' => 'Girne Liman Eczanesi',
        'address' => 'Ziya Rızkı Caddesi No:18, Girne Merkez, Girne',
        'phone' => '0392 815 44 55',
        'district' => 'Girne',
        'district_slug' => 'girne',
        'latitude' => 35.3375,
        'longitude' => 33.3218,
    ],
    [
        'duty_date' => $today,
        'name' => 'Mağusa Şifa Eczanesi',
        'address' => 'Salamis Yolu 15 Ağustos Bulvarı No:88, Gazimağusa',
        'phone' => '0392 365 77 88',
        'district' => 'Gazimağusa',
        'district_slug' => 'gazimagusa',
        'latitude' => 35.1250,
        'longitude' => 33.9420,
    ],
    [
        'duty_date' => $today,
        'name' => 'Güzelyurt Hayat Eczanesi',
        'address' => 'Ecevit Caddesi No:12, Güzelyurt Merkez',
        'phone' => '0392 714 22 11',
        'district' => 'Güzelyurt',
        'district_slug' => 'guzelyurt',
        'latitude' => 35.1985,
        'longitude' => 32.9925,
    ],
    // Yarının Nöbetçi Eczaneleri
    [
        'duty_date' => $tomorrow,
        'name' => 'Lefkoşa Güven Eczanesi',
        'address' => 'Dereboyu Caddesi No:112, Köşklüçiftlik, Lefkoşa',
        'phone' => '0392 228 99 00',
        'district' => 'Lefkoşa',
        'district_slug' => 'lefkosa',
        'latitude' => 35.1880,
        'longitude' => 33.3550,
    ],
    [
        'duty_date' => $tomorrow,
        'name' => 'Girne Yeni Eczane',
        'address' => 'Semih Sancar Caddesi No:64, Girne',
        'phone' => '0392 815 88 99',
        'district' => 'Girne',
        'district_slug' => 'girne',
        'latitude' => 35.3340,
        'longitude' => 33.3280,
    ],
    [
        'duty_date' => $tomorrow,
        'name' => 'Salamis Eczanesi',
        'address' => 'Doğu Akdeniz Üniversitesi Karşısı, Salamis Yolu, Gazimağusa',
        'phone' => '0392 365 43 21',
        'district' => 'Gazimağusa',
        'district_slug' => 'gazimagusa',
        'latitude' => 35.1440,
        'longitude' => 33.9050,
    ]
];

$pdo->beginTransaction();
try {
    // Bugün ve yarın için eski kayıtları temizle
    $stmtDel = $pdo->prepare('DELETE FROM duty_pharmacies WHERE duty_date IN (?, ?)');
    $stmtDel->execute([$today, $tomorrow]);

    $insert = $pdo->prepare(
        'INSERT INTO duty_pharmacies 
        (duty_date, name, address, phone, district, district_slug, latitude, longitude, external_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $count = 0;
    foreach ($samplePharmacies as $p) {
        $insert->execute([
            $p['duty_date'],
            $p['name'],
            $p['address'],
            $p['phone'],
            $p['district'],
            $p['district_slug'],
            $p['latitude'],
            $p['longitude'],
            'local-' . md5($p['name'] . $p['duty_date'])
        ]);
        $count++;
    }

    $pdo->prepare('UPDATE settings SET duty_pharmacy_last_sync = NOW() WHERE id = 1')->execute();
    $pdo->commit();

    echo "=== {$count} adet örnek nöbetçi eczane veritabanına başarıyla eklendi! ===\n";
    echo "Bugün: {$today} | Yarın: {$tomorrow}\n";
    echo "Artık /nobetci-eczane adresinde örnek listeleme çalışıyor.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Hata: " . $e->getMessage() . "\n";
}
