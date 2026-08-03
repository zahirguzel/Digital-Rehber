<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/seo-meta.php';
/**
 * Hatay influencer modülü — ortak sabitler ve yardımcılar
 */

if (!function_exists('influencerNiches')) {
    function influencerNiches() {
        return [
            'yemek' => 'Yemek & Gastronomi',
            'gezi' => 'Gezi & Turizm',
            'yasam' => 'Yaşam & Günlük',
            'moda' => 'Moda & Stil',
            'spor' => 'Spor & Fitness',
            'haber' => 'Haber & Gündem',
            'diger' => 'Diğer',
        ];
    }
}

if (!function_exists('influencerPlatforms')) {
    function influencerPlatforms() {
        return [
            'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram', 'field' => 'instagram', 'followers' => 'follower_instagram'],
            'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok', 'field' => 'tiktok', 'followers' => 'follower_tiktok'],
            'youtube' => ['label' => 'YouTube', 'icon' => 'fa-brands fa-youtube', 'field' => 'youtube', 'followers' => 'follower_youtube'],
        ];
    }
}

if (!function_exists('influencerDistricts')) {
    function influencerDistricts() {
        if (function_exists('seoGetSehirDistricts')) {
            $districts = seoGetSehirDistricts();
            if (!empty($districts)) {
                return $districts;
            }
        }
        if (class_exists('Database')) {
            try {
                $pdo = Database::getInstance()->getPDO();
                $stmt = $pdo->query("SELECT DISTINCT title FROM district_pages WHERE is_active = 1 ORDER BY title ASC");
                $districts = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                if (!empty($districts)) {
                    return $districts;
                }
            } catch (Exception $e) {}
        }
        return ['Merkez'];
    }
}

if (!function_exists('influencerCollabTypes')) {
    function influencerCollabTypes() {
        return [
            'restoran' => 'Restoran / Kafe Tanıtımı',
            'urun' => 'Ürün Tanıtımı',
            'etkinlik' => 'Etkinlik & Lansman',
            'reel' => 'Reel / Video Çekimi',
            'marka' => 'Marka İş Birliği',
            'diger' => 'Diğer',
        ];
    }
}

if (!function_exists('getInfluencerNicheLabel')) {
    function getInfluencerNicheLabel($slug) {
        $niches = influencerNiches();
        return isset($niches[$slug]) ? $niches[$slug] : $slug;
    }
}

if (!function_exists('formatInfluencerFollowers')) {
    function formatInfluencerFollowers($count) {
        $count = (int) $count;
        if ($count <= 0) {
            return '';
        }
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }
        return number_format($count, 0, ',', '.');
    }
}

if (!function_exists('getInfluencerImageUrl')) {
    function getInfluencerImageUrl($path, $fallback = '') {
        if (empty($path)) {
            return $fallback;
        }
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        return '/public/images/' . ltrim($path, '/');
    }
}

if (!function_exists('parseInfluencerCollabTypes')) {
    function parseInfluencerCollabTypes($raw) {
        if (empty($raw)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }
}

if (!function_exists('parseInfluencerFeaturedLinks')) {
    function parseInfluencerFeaturedLinks($raw) {
        if (empty($raw)) {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $links = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
                $links[] = $line;
            }
        }
        return $links;
    }
}

if (!function_exists('influencerFilterUrl')) {
    function influencerFilterUrl($updates) {
        $params = $_GET;
        foreach ($updates as $key => $val) {
            if ($val === null || $val === '') {
                unset($params[$key]);
            } else {
                $params[$key] = $val;
            }
        }
        return '/influencerlar?' . http_build_query(array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        }));
    }
}

if (!function_exists('renderInfluencerVerifiedBadge')) {
    function renderInfluencerVerifiedBadge($inline = true) {
        $class = $inline ? 'influencer-verified-badge' : 'influencer-verified-badge influencer-verified-badge--lg';
        $siteTitle = function_exists('seoGetSiteTitle') ? seoGetSiteTitle() : 'Şehir Rehberi';
        return '<span class="' . $class . '" title="Doğrulanmış ' . htmlspecialchars($siteTitle) . ' profili"><i class="fa-solid fa-circle-check"></i> Doğrulanmış Profil</span>';
    }
}

if (!function_exists('renderInfluencerFollowerNote')) {
    function renderInfluencerFollowerNote($influencer) {
        if (empty($influencer['followers_verified_at'])) {
            return '';
        }
        $date = date('m/Y', strtotime($influencer['followers_verified_at']));
        $by = !empty($influencer['followers_verified_by']) ? SecurityHelper::escape($influencer['followers_verified_by']) : (function_exists('seoGetSiteTitle') ? SecurityHelper::escape(seoGetSiteTitle()) : 'Şehir Rehberi');
        return '<span class="influencer-follower-note"><i class="fa-solid fa-shield-halved"></i> Takipçi sayıları manuel doğrulandı · ' . $date . '</span>';
    }
}

if (!function_exists('getInfluencerApplicationStatusLabel')) {
    function getInfluencerApplicationStatusLabel($status) {
        $labels = [
            'pending' => 'Beklemede',
            'approved' => 'Onaylandı',
            'rejected' => 'Reddedildi',
        ];
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}

if (!function_exists('getInfluencerApplicationStatusBadgeClass')) {
    function getInfluencerApplicationStatusBadgeClass($status) {
        $classes = [
            'pending' => 'warning text-dark',
            'approved' => 'success',
            'rejected' => 'secondary',
        ];
        return isset($classes[$status]) ? $classes[$status] : 'secondary';
    }
}

if (!function_exists('getInfluencerRemovalStatusLabel')) {
    function getInfluencerRemovalStatusLabel($status) {
        return $status === 'pending' ? 'Beklemede' : 'İşlendi';
    }
}

if (!function_exists('getInfluencerRemovalStatusBadgeClass')) {
    function getInfluencerRemovalStatusBadgeClass($status) {
        return $status === 'pending' ? 'warning text-dark' : 'secondary';
    }
}

if (!function_exists('getInfluencerRemovalRequestTypeLabel')) {
    function getInfluencerRemovalRequestTypeLabel($type) {
        return $type === 'removal' ? 'Profil Kaldırma' : 'Bilgi Düzeltme';
    }
}

if (!function_exists('getInfluencerPendingRequestsCount')) {
    function getInfluencerPendingRequestsCount($pdo) {
        try {
            $apps = (int) $pdo->query("SELECT COUNT(*) FROM influencer_applications WHERE status = 'pending'")->fetchColumn();
            $collabs = (int) $pdo->query('SELECT COUNT(*) FROM influencer_collaboration_requests WHERE is_read = 0')->fetchColumn();
            $removals = (int) $pdo->query("SELECT COUNT(*) FROM influencer_removal_requests WHERE status = 'pending'")->fetchColumn();
            return $apps + $collabs + $removals;
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('influencerRemovalRequestUrl')) {
    function influencerRemovalRequestUrl($slug = null) {
        if ($slug === null || $slug === '') {
            return '/influencer-kaldirma-talebi';
        }
        return '/influencer-kaldirma-talebi/' . rawurlencode($slug);
    }
}

if (!function_exists('influencerQrUrl')) {
    function influencerQrUrl($slug) {
        return '/i/' . rawurlencode($slug);
    }
}
