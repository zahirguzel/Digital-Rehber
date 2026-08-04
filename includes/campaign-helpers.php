<?php
require_once __DIR__ . '/../autoload.php';
/**
 * Rehber kampanyalar modülü — ortak sabitler ve yardımcılar
 */

if (!function_exists('campaignTypes')) {
    function campaignTypes() {
        return [
            'indirim' => 'İndirim',
            'firsat' => 'Fırsat',
            'paket' => 'Paket / Set',
            'hediye' => 'Hediye',
            'ozel' => 'Özel Gün',
            'diger' => 'Diğer',
        ];
    }
}

if (!function_exists('campaignDistricts')) {
    function campaignDistricts() {
        if (function_exists('seoGetDistrictsByCity') && function_exists('seoGetDefaultCity')) {
            $d = seoGetDistrictsByCity(seoGetDefaultCity());
            if (!empty($d)) {
                return $d;
            }
        }
        return ['Girne', 'Lefkoşa', 'Gazimağusa', 'Güzelyurt', 'İskele', 'Lefke'];
    }
}

if (!function_exists('getCampaignTypeLabel')) {
    function getCampaignTypeLabel($slug) {
        $types = campaignTypes();
        return isset($types[$slug]) ? $types[$slug] : $slug;
    }
}

if (!function_exists('getCampaignImageUrl')) {
    function getCampaignImageUrl($path, $fallback = '') {
        if (empty($path)) {
            return $fallback;
        }
        // Harici URL ise doğrudan döndür
        if (strpos($path, 'http') === 0) {
            return $path;
        }

        // 1. Önce seoGetBaseUrl() ile tam base URL dene (en güvenilir)
        if (function_exists('seoGetBaseUrl')) {
            $base = rtrim(seoGetBaseUrl(), '/');
            return $base . '/public/images/' . ltrim($path, '/');
        }

        // 2. Fallback: DOCUMENT_ROOT ile proje kök yolunu hesapla
        // public/images/ klasörünün gerçek FS yolunu bul
        $imagesDir = __DIR__ . '/../public/images/';
        $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');

        // Gerçek path varsa relative URL hesapla
        $realImages = realpath($imagesDir);
        if ($realImages && $docRoot !== '') {
            $realDocRoot = realpath($docRoot);
            if ($realDocRoot && strpos($realImages, $realDocRoot) === 0) {
                $webPath = str_replace('\\', '/', substr($realImages, strlen($realDocRoot)));
                return rtrim($webPath, '/') . '/' . ltrim($path, '/');
            }
        }

        // 3. Son çare: SCRIPT_NAME tabanlı fallback
        $base = '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        foreach (['/admin/', '/isletme/'] as $segment) {
            $pos = strpos($scriptName, $segment);
            if ($pos !== false) {
                $base = rtrim(substr($scriptName, 0, $pos), '/');
                break;
            }
        }
        return $base . '/public/images/' . ltrim($path, '/');
    }
}

if (!function_exists('formatCampaignDateBadge')) {
    function formatCampaignDateBadge($date) {
        if (empty($date)) {
            return ['day' => '—', 'month' => ''];
        }
        $months = ['', 'OCA', 'ŞUB', 'MAR', 'NIS', 'MAY', 'HAZ', 'TEM', 'AĞU', 'EYL', 'EKI', 'KAS', 'ARA'];
        $ts = strtotime($date);
        return [
            'day' => date('d', $ts),
            'month' => $months[(int) date('n', $ts)],
        ];
    }
}

if (!function_exists('formatCampaignDateRange')) {
    function formatCampaignDateRange($startDate, $endDate = null) {
        if (empty($startDate)) {
            return '';
        }
        $startTs = strtotime($startDate);
        $start = date('d.m.Y', $startTs);
        if (empty($endDate) || $endDate === $startDate) {
            return $start;
        }
        $endTs = strtotime($endDate);
        if (date('mY', $startTs) === date('mY', $endTs)) {
            return date('d', $startTs) . '–' . date('d.m.Y', $endTs);
        }
        return $start . ' – ' . date('d.m.Y', $endTs);
    }
}

if (!function_exists('isCampaignPast')) {
    function isCampaignPast($campaign) {
        $end = !empty($campaign['end_date']) ? $campaign['end_date'] : $campaign['start_date'];
        return $end < date('Y-m-d');
    }
}

if (!function_exists('isCampaignActive')) {
    function isCampaignActive($campaign) {
        $today = date('Y-m-d');
        $start = $campaign['start_date'];
        $end = !empty($campaign['end_date']) ? $campaign['end_date'] : $start;
        return $today >= $start && $today <= $end;
    }
}

if (!function_exists('campaignFilterUrl')) {
    function campaignFilterUrl($updates) {
        $params = $_GET;
        foreach ($updates as $key => $val) {
            if ($val === null || $val === '') {
                unset($params[$key]);
            } else {
                $params[$key] = $val;
            }
        }
        if (!function_exists('seoGetBaseUrl')) {
            require_once __DIR__ . '/seo-meta.php';
        }
        $query = http_build_query(array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        }));
        $url = rtrim(seoGetBaseUrl(), '/') . '/kampanyalar';
        if ($query !== '') {
            $url .= '?' . $query;
        }
        return $url;
    }
}

if (!function_exists('renderCampaignStatusBadge')) {
    function renderCampaignStatusBadge($campaign) {
        if (isCampaignActive($campaign)) {
            return '<span class="camp-portal-badge camp-portal-badge--active"><i class="fa-solid fa-bolt"></i> Aktif</span>';
        }
        if (isCampaignPast($campaign)) {
            return '<span class="camp-portal-badge camp-portal-badge--past">Sona Erdi</span>';
        }
        return '<span class="camp-portal-badge camp-portal-badge--soon">Yakında</span>';
    }
}

if (!function_exists('getCampaignBusiness')) {
    function getCampaignBusiness(PDO $pdo, $businessId) {
        if (!$businessId) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT b.*, c.name AS category_name
                 FROM businesses b
                 LEFT JOIN categories c ON c.id = b.category_id
                 WHERE b.id = ? AND b.is_deleted = 0
                 LIMIT 1'
            );
            $stmt->execute([(int) $businessId]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('campaignListSelectSql')) {
    function campaignListSelectSql() {
        return 'c.*, b.name AS business_name, b.slug AS business_slug, b.district AS business_district';
    }
}

if (!function_exists('formatCampaignPrice')) {
    function formatCampaignPrice($campaign) {
        $original = trim((string) ($campaign['original_price'] ?? ''));
        $sale = trim((string) ($campaign['sale_price'] ?? ''));
        if ($original === '' && $sale === '') {
            return '';
        }
        return ['original' => $original, 'sale' => $sale];
    }
}

if (!function_exists('renderCampaignPriceHtml')) {
    function renderCampaignPriceHtml($campaign, $class = 'camp-portal-price') {
        $price = formatCampaignPrice($campaign);
        if ($price === '') {
            return '';
        }
        $html = '<p class="' . SecurityHelper::escape($class) . '"><i class="fa-solid fa-tag"></i> ';
        if ($price['sale'] !== '') {
            $html .= '<strong class="camp-portal-price__sale">₺' . SecurityHelper::escape($price['sale']) . '</strong>';
            if ($price['original'] !== '') {
                $html .= '<span class="camp-portal-price__original">₺' . SecurityHelper::escape($price['original']) . '</span>';
            }
        } elseif ($price['original'] !== '') {
            $html .= '<strong class="camp-portal-price__sale">₺' . SecurityHelper::escape($price['original']) . '</strong>';
        }
        $html .= '</p>';
        return $html;
    }
}

if (!function_exists('renderCampaignDescriptionHtml')) {
    function renderCampaignDescriptionHtml($description) {
        $description = trim((string) $description);
        if ($description === '') {
            return '';
        }
        if (preg_match('/<[^>]+>/', $description)) {
            // Allow Quill-generated tags; use DOMDocument to strip dangerous attributes
            $allowed = '<p><br><strong><em><b><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><a><s>';
            $clean = strip_tags($description, $allowed);
            // Allow only safe inline style (color) via regex
            $clean = preg_replace('/style="[^"]*"/', '', $clean);
            return $clean;
        }
        return nl2br(SecurityHelper::escape($description));
    }
}

if (!function_exists('campaignListJoinSql')) {
    function campaignListJoinSql() {
        return ' FROM campaigns c LEFT JOIN businesses b ON b.id = c.business_id ';
    }
}
