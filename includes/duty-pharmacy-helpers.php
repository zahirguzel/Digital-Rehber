<?php

require_once __DIR__ . '/seo-meta.php';
require_once __DIR__ . '/district-helpers.php';

if (!function_exists('dutyPharmacyApiBaseUrl')) {
    function dutyPharmacyApiBaseUrl() {
        return 'https://eczaneapi.com/api/v1';
    }
}

if (!function_exists('dutyPharmacyNormalizeDistrictName')) {
    function dutyPharmacyNormalizeDistrictName($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
        }

        // EczaneAPI bazen ilçeleri farklı yazabilir. İhtiyaç halinde kendi ilçeleriniz için buraya eşleştirme ekleyebilirsiniz.
        // Örnek: 'merkez ilçe 2' => 'Merkez'
        $aliases = [
            // Kendi alias'larınızı buraya ekleyebilirsiniz...
        ];

        $lower = mb_strtolower($name, 'UTF-8');
        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        foreach (seoGetSehirDistricts() as $district) {
            if (mb_strtolower($district, 'UTF-8') === $lower) {
                return $district;
            }
        }

        foreach (seoGetSehirDistricts() as $district) {
            if (mb_stripos($name, $district) !== false) {
                return $district;
            }
        }

        return $name;
    }
}

if (!function_exists('dutyPharmacyTimezone')) {
    function dutyPharmacyTimezone() {
        return 'Europe/Istanbul';
    }
}

if (!function_exists('dutyPharmacySwitchHour')) {
    /** Nöbet listesi sabah bu saatte yeni güne geçer (Türkiye geneli). */
    function dutyPharmacySwitchHour() {
        return 8;
    }
}

if (!function_exists('dutyPharmacyNow')) {
    function dutyPharmacyNow() {
        return new DateTime('now', new DateTimeZone(dutyPharmacyTimezone()));
    }
}

if (!function_exists('dutyPharmacyTurkishDayName')) {
    function dutyPharmacyTurkishDayName(DateTime $date) {
        $days = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        return $days[(int) $date->format('w')];
    }
}

if (!function_exists('dutyPharmacyTurkishMonthName')) {
    function dutyPharmacyTurkishMonthName(DateTime $date) {
        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
            7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];
        return $months[(int) $date->format('n')];
    }
}

if (!function_exists('dutyPharmacyGetEffectiveToday')) {
    /**
     * Nöbet takvimi gece yarısı değişmez.
     * Örn. 9 Temmuz 02:42 → hâlâ 8 Temmuz akşamı nöbeti (8 Temmuz tarihli liste).
     */
    function dutyPharmacyGetEffectiveToday() {
        $now = dutyPharmacyNow();
        if ((int) $now->format('G') < dutyPharmacySwitchHour()) {
            $now->modify('-1 day');
        }
        return $now->format('Y-m-d');
    }
}

if (!function_exists('dutyPharmacyFormatPeriodLabel')) {
    function dutyPharmacyFormatPeriodLabel($dutyDate) {
        $start = DateTime::createFromFormat('Y-m-d', $dutyDate, new DateTimeZone(dutyPharmacyTimezone()));
        if (!$start) {
            return '';
        }
        $end = clone $start;
        $end->modify('+1 day');

        return sprintf(
            '%d %s %s akşamından %d %s %s sabahına kadar',
            (int) $start->format('j'),
            dutyPharmacyTurkishMonthName($start),
            dutyPharmacyTurkishDayName($start),
            (int) $end->format('j'),
            dutyPharmacyTurkishMonthName($end),
            dutyPharmacyTurkishDayName($end)
        );
    }
}

if (!function_exists('dutyPharmacyResolveDate')) {
    function dutyPharmacyResolveDate($when = 'today') {
        $when = strtolower(trim((string) $when));
        $base = DateTime::createFromFormat(
            'Y-m-d',
            dutyPharmacyGetEffectiveToday(),
            new DateTimeZone(dutyPharmacyTimezone())
        );

        if (in_array($when, ['tomorrow', 'yarin', 'yarın'], true)) {
            $base->modify('+1 day');
        }

        return $base->format('Y-m-d');
    }
}

if (!function_exists('dutyPharmacyGetSettings')) {
    function dutyPharmacyGetSettings(PDO $pdo) {
        try {
            $row = $pdo->query('SELECT eczane_api_key, duty_pharmacy_last_sync FROM settings WHERE id = 1 LIMIT 1')->fetch();
            return is_array($row) ? $row : ['eczane_api_key' => null, 'duty_pharmacy_last_sync' => null];
        } catch (Exception $e) {
            return ['eczane_api_key' => null, 'duty_pharmacy_last_sync' => null];
        }
    }
}

if (!function_exists('dutyPharmacyFetchFromApi')) {
    function dutyPharmacyFetchFromApi($apiKey, $date) {
        $_apiCity = function_exists('seoGetRegionName') ? mb_strtolower(seoGetRegionName(), 'UTF-8') : 'kibris';
        $url = dutyPharmacyApiBaseUrl() . '/pharmacies/on-duty?city=' . urlencode($_apiCity) . '&date=' . urlencode($date);

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL eklentisi sunucuda aktif değil.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-API-Key: ' . $apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('API bağlantı hatası: ' . $curlError);
        }

        $json = json_decode($body, true);
        if ($httpCode >= 400 || !is_array($json)) {
            $message = is_array($json) && !empty($json['error']) ? $json['error'] : ('HTTP ' . $httpCode);
            throw new RuntimeException('EczaneAPI hatası: ' . $message);
        }

        if (empty($json['success'])) {
            $message = !empty($json['error']) ? $json['error'] : 'Bilinmeyen API hatası';
            throw new RuntimeException($message);
        }

        return $json;
    }
}

if (!function_exists('dutyPharmacyNeedsSync')) {
    function dutyPharmacyNeedsSync(PDO $pdo, $date, $ttlSeconds = 21600) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, MAX(synced_at) AS last_sync FROM duty_pharmacies WHERE duty_date = ?');
            $stmt->execute([$date]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['cnt'] === 0) {
                return true;
            }
            if (empty($row['last_sync'])) {
                return true;
            }
            return (time() - strtotime($row['last_sync'])) >= $ttlSeconds;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('dutyPharmacyEnsureSynced')) {
    /**
     * Cron gerekmez: sayfa açılışında cache eskiyse veya boşsa API'den çeker.
     * Aynı anda tek istek için dosya kilidi kullanır.
     */
    function dutyPharmacyEnsureSynced(PDO $pdo, $date) {
        $settings = dutyPharmacyGetSettings($pdo);
        if (trim((string) ($settings['eczane_api_key'] ?? '')) === '') {
            return false;
        }

        $today = dutyPharmacyGetEffectiveToday();
        $ttlSeconds = ($date === $today) ? (6 * 3600) : (12 * 3600);
        if (!dutyPharmacyNeedsSync($pdo, $date, $ttlSeconds)) {
            return false;
        }

        $lockPath = sys_get_temp_dir() . '/rehber-duty-sync-' . md5($date) . '.lock';
        $fp = @fopen($lockPath, 'c+');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            return false;
        }

        try {
            if (!dutyPharmacyNeedsSync($pdo, $date, $ttlSeconds)) {
                return false;
            }
            dutyPharmacySync($pdo, $date);
            return true;
        } catch (Exception $e) {
            return false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}

if (!function_exists('dutyPharmacySync')) {
    function dutyPharmacySync(PDO $pdo, $date = null) {
        $settings = dutyPharmacyGetSettings($pdo);
        $apiKey = trim((string) ($settings['eczane_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('EczaneAPI anahtarı tanımlı değil. Admin panelinden kaydedin.');
        }

        $dutyDate = $date ?: dutyPharmacyGetEffectiveToday();
        $response = dutyPharmacyFetchFromApi($apiKey, $dutyDate);
        $pharmacies = $response['data']['pharmacies'] ?? [];
        if (!is_array($pharmacies)) {
            $pharmacies = [];
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM duty_pharmacies WHERE duty_date = ?')->execute([$dutyDate]);

            $insert = $pdo->prepare(
                'INSERT INTO duty_pharmacies
                (external_id, duty_date, name, address, phone, district, district_slug, latitude, longitude)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($pharmacies as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $districtName = dutyPharmacyNormalizeDistrictName($item['district']['name'] ?? ($item['district'] ?? (function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir')));
                $districtSlug = $item['district']['slug'] ?? seoDistrictNameToSlug($districtName);
                if (!$districtSlug && !empty($item['district']['slug'])) {
                    $districtSlug = $item['district']['slug'];
                }

                $insert->execute([
                    !empty($item['id']) ? (string) $item['id'] : null,
                    $dutyDate,
                    trim((string) ($item['name'] ?? 'Eczane')),
                    trim((string) ($item['address'] ?? '')),
                    trim((string) ($item['phone'] ?? '')),
                    $districtName,
                    $districtSlug,
                    isset($item['location']['latitude']) ? (float) $item['location']['latitude'] : null,
                    isset($item['location']['longitude']) ? (float) $item['location']['longitude'] : null,
                ]);
            }

            $pdo->prepare('UPDATE settings SET duty_pharmacy_last_sync = NOW() WHERE id = 1')->execute();
            $pdo->prepare(
                'INSERT INTO duty_pharmacy_sync_logs (duty_date, status, pharmacy_count, message)
                 VALUES (?, ?, ?, ?)'
            )->execute([
                $dutyDate,
                'success',
                count($pharmacies),
                count($pharmacies) . ' eczane kaydedildi.',
            ]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            try {
                $pdo->prepare(
                    'INSERT INTO duty_pharmacy_sync_logs (duty_date, status, pharmacy_count, message)
                     VALUES (?, ?, 0, ?)'
                )->execute([$dutyDate, 'error', $e->getMessage()]);
            } catch (Exception $ignored) {
            }
            throw $e;
        }

        return [
            'date' => $dutyDate,
            'count' => count($pharmacies),
        ];
    }
}

if (!function_exists('dutyPharmacyGetList')) {
    function dutyPharmacyGetList(PDO $pdo, $date, $districtSlug = '') {
        $sql = 'SELECT * FROM duty_pharmacies WHERE duty_date = :date';
        $params = [':date' => $date];

        if ($districtSlug !== '') {
            $districtName = seoDistrictSlugToName($districtSlug);
            $sql .= ' AND (district_slug = :slug OR district = :district)';
            $params[':slug'] = $districtSlug;
            $params[':district'] = $districtName ?: $districtSlug;
        }

        $sql .= ' ORDER BY district ASC, name ASC';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('dutyPharmacyGroupByDistrict')) {
    function dutyPharmacyGroupByDistrict(array $rows) {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['district'] ?: (function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir');
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $row;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        return $groups;
    }
}

if (!function_exists('dutyPharmacyGetDistrictCounts')) {
    function dutyPharmacyGetDistrictCounts(PDO $pdo, $date) {
        try {
            $stmt = $pdo->prepare('SELECT district, district_slug, COUNT(*) AS cnt FROM duty_pharmacies WHERE duty_date = ? GROUP BY district, district_slug ORDER BY district ASC');
            $stmt->execute([$date]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('dutyPharmacyGetRecentLogs')) {
    function dutyPharmacyGetRecentLogs(PDO $pdo, $limit = 10) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM duty_pharmacy_sync_logs ORDER BY id DESC LIMIT ' . (int) $limit);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('dutyPharmacyFilterUrl')) {
    function dutyPharmacyFilterUrl(array $overrides = []) {
        global $dutyPharmacyDistrictSlug, $dutyPharmacyWhen;

        $district = array_key_exists('district', $overrides) ? $overrides['district'] : ($dutyPharmacyDistrictSlug ?? '');
        $when = array_key_exists('when', $overrides) ? $overrides['when'] : ($dutyPharmacyWhen ?? 'today');

        $path = '/nobetci-eczane';
        if ($district !== '') {
            $path .= '/' . rawurlencode($district);
        }

        $query = [];
        if ($when !== '' && $when !== 'today') {
            $query['when'] = $when;
        }

        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return $path;
    }
}

if (!function_exists('dutyPharmacyMapsSearchQuery')) {
    function dutyPharmacyMapsSearchQuery(array $pharmacy) {
        $parts = [];

        $name = trim((string) ($pharmacy['name'] ?? ''));
        if ($name !== '') {
            $parts[] = $name;
        }

        $district = trim((string) ($pharmacy['district'] ?? ''));
        if ($district !== '') {
            $parts[] = $district;
        }

        $parts[] = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
        $parts[] = 'Türkiye';

        return implode(', ', $parts);
    }
}

if (!function_exists('dutyPharmacyMapsUrl')) {
    function dutyPharmacyMapsUrl(array $pharmacy) {
        // Koordinat yerine eczane adı + ilçe ile ara; API koordinatları sık hatalı oluyor.
        $query = dutyPharmacyMapsSearchQuery($pharmacy);

        $_region = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
        if ($query === $_region . ', Türkiye' || $query === 'Kıbrıs') {
            $fallback = trim((string) ($pharmacy['name'] ?? '') . ' ' . (string) ($pharmacy['address'] ?? ''));
            $query = $fallback !== '' ? $fallback : $_region . ' eczane';
        }

        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($query);
    }
}

if (!function_exists('dutyPharmacyPhoneHref')) {
    function dutyPharmacyPhoneHref($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits !== '' ? 'tel:' . $digits : '';
    }
}
