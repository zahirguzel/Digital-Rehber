<?php
require_once __DIR__ . '/../autoload.php';

$db = Database::getInstance()->getPDO();

echo "Reading cities.json...\n";
$json = file_get_contents(__DIR__ . '/../cities.json');
$data = json_decode($json, true);

if (!is_array($data) || empty($data)) {
    die("Invalid JSON data.\n");
}

function titleCaseTr($string) {
    // lowercase all first (with TR support)
    $string = mb_convert_case($string, MB_CASE_LOWER, "UTF-8");
    $words = explode(" ", $string);
    $result = [];
    foreach ($words as $w) {
        $first = mb_substr($w, 0, 1, "UTF-8");
        $first = mb_convert_case($first, MB_CASE_UPPER, "UTF-8");
        $rest = mb_substr($w, 1, null, "UTF-8");
        $result[] = $first . $rest;
    }
    return implode(" ", $result);
}

$db->beginTransaction();

try {
    foreach ($data as $item) {
        $cityName = titleCaseTr(isset($item['name']) ? $item['name'] : '');
        $districts = isset($item['counties']) ? $item['counties'] : [];
        
        if (empty($cityName)) continue;
        
        // Check if city exists
        $stmtCityCheck = $db->prepare("SELECT id FROM cities WHERE name = ?");
        $stmtCityCheck->execute([$cityName]);
        $cityId = $stmtCityCheck->fetchColumn();
        
        if (!$cityId) {
            $stmtInsertCity = $db->prepare("INSERT INTO cities (name) VALUES (?)");
            $stmtInsertCity->execute([$cityName]);
            $cityId = $db->lastInsertId();
        }
        
        // Insert districts
        foreach ($districts as $dist) {
            $distName = titleCaseTr($dist);
            if (empty($distName)) continue;
            
            $stmtDistCheck = $db->prepare("SELECT id FROM districts WHERE city_id = ? AND name = ?");
            $stmtDistCheck->execute([$cityId, $distName]);
            if (!$stmtDistCheck->fetchColumn()) {
                $stmtInsertDist = $db->prepare("INSERT INTO districts (city_id, name) VALUES (?, ?)");
                $stmtInsertDist->execute([$cityId, $distName]);
            }
        }
    }
    
    // Also ensure Kıbrıs is there
    $kibris = 'Kıbrıs';
    $stmtCityCheck = $db->prepare("SELECT id FROM cities WHERE name = ?");
    $stmtCityCheck->execute([$kibris]);
    $cityId = $stmtCityCheck->fetchColumn();
    if (!$cityId) {
        $stmtInsertCity = $db->prepare("INSERT INTO cities (name) VALUES (?)");
        $stmtInsertCity->execute([$kibris]);
        $cityId = $db->lastInsertId();
    }
    $kibrisDistricts = ['Lefkoşa', 'Girne', 'Gazimağusa', 'Güzelyurt', 'İskele', 'Lefke'];
    foreach ($kibrisDistricts as $distName) {
        $stmtDistCheck = $db->prepare("SELECT id FROM districts WHERE city_id = ? AND name = ?");
        $stmtDistCheck->execute([$cityId, $distName]);
        if (!$stmtDistCheck->fetchColumn()) {
            $stmtInsertDist = $db->prepare("INSERT INTO districts (city_id, name) VALUES (?, ?)");
            $stmtInsertDist->execute([$cityId, $distName]);
        }
    }

    $db->commit();
    echo "Successfully seeded 81 cities and districts + Kıbrıs.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
