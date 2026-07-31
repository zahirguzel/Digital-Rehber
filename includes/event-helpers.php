<?php
/**
 * Hatay etkinlikler modülü — ortak sabitler ve yardımcılar
 */
require_once __DIR__ . '/seo-meta.php';

if (!function_exists('eventCategories')) {
    function eventCategories() {
        return [
            'konsert' => 'Konser & Müzik',
            'festival' => 'Festival',
            'sergi' => 'Sergi & Sanat',
            'spor' => 'Spor',
            'cocuk' => 'Çocuk & Aile',
            'kultur' => 'Kültür & Tiyatro',
            'diger' => 'Diğer',
        ];
    }
}

if (!function_exists('eventDistricts')) {
    function eventDistricts() {
        if (function_exists('seoGetSehirDistricts')) {
            $districts = seoGetSehirDistricts();
            if (!empty($districts)) {
                return $districts;
            }
        }
        return ['Merkez'];
    }
}

if (!function_exists('getEventCategoryLabel')) {
    function getEventCategoryLabel($slug) {
        $categories = eventCategories();
        return isset($categories[$slug]) ? $categories[$slug] : $slug;
    }
}

if (!function_exists('getEventImageUrl')) {
    function getEventImageUrl($path, $fallback = '') {
        $base = function_exists('seoGetBaseUrl') ? seoGetBaseUrl() : '';
        if (empty($path)) {
            return $fallback !== '' ? $fallback : $base . '/public/images/hero-slider.jpg';
        }
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        return $base . '/public/images/' . ltrim($path, '/');
    }
}

if (!function_exists('formatEventDateBadge')) {
    function formatEventDateBadge($date) {
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

if (!function_exists('formatEventDateRange')) {
    function formatEventDateRange($startDate, $endDate = null) {
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

if (!function_exists('formatEventTimeRange')) {
    function formatEventTimeRange($startTime, $endTime = null) {
        if (empty($startTime)) {
            return '';
        }
        $start = substr($startTime, 0, 5);
        if (empty($endTime)) {
            return $start;
        }
        return $start . ' – ' . substr($endTime, 0, 5);
    }
}

if (!function_exists('isEventPast')) {
    function isEventPast($event) {
        $end = !empty($event['end_date']) ? $event['end_date'] : $event['start_date'];
        return $end < date('Y-m-d');
    }
}

if (!function_exists('isEventToday')) {
    function isEventToday($event) {
        $today = date('Y-m-d');
        $start = $event['start_date'];
        $end = !empty($event['end_date']) ? $event['end_date'] : $start;
        return $today >= $start && $today <= $end;
    }
}

if (!function_exists('eventFilterUrl')) {
    function eventFilterUrl($updates) {
        $params = $_GET;
        foreach ($updates as $key => $val) {
            if ($val === null || $val === '') {
                unset($params[$key]);
            } else {
                $params[$key] = $val;
            }
        }
        $base = function_exists('seoGetBaseUrl') ? seoGetBaseUrl() : '';
        return $base . '/etkinlikler?' . http_build_query(array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        }));
    }
}

if (!function_exists('renderEventStatusBadge')) {
    function renderEventStatusBadge($event) {
        if (isEventToday($event)) {
            return '<span class="event-status-badge event-status-badge--today"><i class="fa-solid fa-bolt"></i> Bugün</span>';
        }
        if (isEventPast($event)) {
            return '<span class="event-status-badge event-status-badge--past">Geçmiş</span>';
        }
        return '<span class="event-status-badge event-status-badge--upcoming"><i class="fa-regular fa-calendar"></i> Yaklaşan</span>';
    }
}

if (!function_exists('suggestEventSlug')) {
    function suggestEventSlug($title) {
        $map = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u'];
        $slug = strtr(trim($title), $map);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}

if (!function_exists('getEventSubmissionStatusLabel')) {
    function getEventSubmissionStatusLabel($status) {
        $labels = [
            'pending' => 'Beklemede',
            'approved' => 'Yayınlandı',
            'rejected' => 'Reddedildi',
        ];
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}

if (!function_exists('getEventPendingSubmissionsCount')) {
    function getEventPendingSubmissionsCount($pdo) {
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM event_submissions WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('mapEventSubmissionToEvent')) {
    function mapEventSubmissionToEvent($submission) {
        if (!$submission) {
            return [];
        }
        return [
            'title' => $submission['title'],
            'slug' => suggestEventSlug($submission['title']),
            'district' => $submission['district'],
            'venue_name' => $submission['venue_name'],
            'address' => $submission['address'],
            'start_date' => $submission['start_date'],
            'end_date' => $submission['end_date'],
            'start_time' => $submission['start_time'],
            'end_time' => $submission['end_time'],
            'category' => $submission['category'],
            'description' => $submission['description'],
            'cover_image_path' => $submission['cover_image_url'],
            'ticket_url' => $submission['ticket_url'],
            'ticket_price' => $submission['ticket_price'],
            'organizer' => $submission['organizer'],
            'contact_phone' => $submission['contact_phone'],
            'contact_email' => $submission['contact_email'],
        ];
    }
}
