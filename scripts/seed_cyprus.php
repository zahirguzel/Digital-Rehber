<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../config/db.php';

$districts = [
    'Lefkoşa',
    'Girne',
    'Gazimağusa',
    'Güzelyurt',
    'İskele',
    'Lefke'
];

function createSlug($text) {
    $trMap = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',' '=>'-','Ç'=>'C','Ğ'=>'G','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U'];
    $text = mb_strtolower(strtr($text, $trMap));
    return preg_replace('/[^a-z0-9-]/', '', $text);
}

try {
    // 1. Insert Region into cities
    $cityName = 'Kıbrıs';
    $stmt = $pdo->prepare("INSERT IGNORE INTO cities (name) VALUES (?)");
    $stmt->execute([$cityName]);
    
    $cityId = $pdo->query("SELECT id FROM cities WHERE name = 'Kıbrıs'")->fetchColumn();

    // 2. Insert into districts and district_pages
    foreach ($districts as $d) {
        $slug = createSlug($d);
        
        // Admin dropdown districts
        if ($cityId) {
            $stmtDist = $pdo->prepare("INSERT IGNORE INTO districts (city_id, name) VALUES (?, ?)");
            $stmtDist->execute([$cityId, $d]);
        }
        
        // Frontend district pages
        $check = $pdo->prepare("SELECT id FROM district_pages WHERE slug = ?");
        $check->execute([$slug]);
        if (!$check->fetchColumn()) {
            $stmtPage = $pdo->prepare("
                INSERT INTO district_pages (district_name, slug, is_published, intro, tagline) 
                VALUES (?, ?, 1, ?, ?)
            ");
            $intro = "Kıbrıs'ın gözbebeği $d bölgesindeki en iyi işletmeleri keşfedin. Kafelerden restoranlara, güzellik merkezlerinden oto servislere kadar aradığınız her şey burada.";
            $tagline = "$d rehberi";
            $stmtPage->execute([$d, $slug, $intro, $tagline]);
        }
    }
    
    echo "Kıbrıs bölgesi ve ilçeleri (Lefkoşa, Girne, vb.) başarıyla veritabanına eklendi.\n";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
