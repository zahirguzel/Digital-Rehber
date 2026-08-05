<?php
/**
 * Dijital menü — ortak görsel yardımcıları
 */

require_once __DIR__ . '/seo-meta.php';

if (!function_exists('menuResolveAssetUrl')) {
    function menuResolveAssetUrl($path, $prefix = '') {
        if (empty($path)) {
            return null;
        }

        if (menuItemImageIsRemote($path)) {
            return $path;
        }

        $normalized = ltrim((string) $path, '/');
        if ($prefix !== '' && strpos($normalized, 'public/') !== 0 && strpos($normalized, trim($prefix, '/\\')) !== 0) {
            $normalized = trim($prefix, '/\\') . '/' . $normalized;
        }

        if (function_exists('seoResolveAbsoluteUrl') && function_exists('seoGetBaseUrl')) {
            return seoResolveAbsoluteUrl($normalized, seoGetBaseUrl());
        }

        return '/' . $normalized;
    }
}

if (!function_exists('menuItemImageIsRemote')) {
    function menuItemImageIsRemote($path) {
        if (empty($path)) {
            return false;
        }
        return strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0;
    }
}

if (!function_exists('menuItemImageUrl')) {
    function menuItemImageUrl($path) {
        if (empty($path)) {
            return null;
        }
        return menuResolveAssetUrl($path, 'public/images/menu');
    }
}

if (!function_exists('menuBusinessImageUrl')) {
    function menuBusinessImageUrl($path) {
        if (empty($path)) {
            return null;
        }

        return menuResolveAssetUrl($path, 'public/images');
    }
}

if (!function_exists('menuItemIcon')) {
    function menuItemIcon($name) {
        $n = mb_strtolower((string) $name, 'UTF-8');
        $rules = [
            ['serpme', 'kahvalt', 'fa-plate-wheat'],
            ['menemen', 'fa-egg'],
            ['gözleme', 'gozleme', 'fa-bread-slice'],
            ['sucuklu', 'kaşarlı yumurta', 'kasarli yumurta', 'fa-egg'],
            ['dürüm', 'durum', 'bazlama', 'fa-bread-slice'],
            ['güveç', 'guvec', 'sucuk', 'fa-fire-burner'],
            ['büyük su', 'buyuk su', 'küçük su', 'kucuk su', 'fa-bottle-water'],
            ['pepsi', 'cola', 'fa-bottle-droplet'],
            ['soda', 'fa-glass-water'],
            ['ice tea', 'fa-glass-water'],
            ['portakal', 'fa-lemon'],
            ['nescafe', 'fa-mug-saucer'],
            ['kahve', 'kumda', 'fincan', 'fa-mug-hot'],
        ];
        foreach ($rules as $rule) {
            $icon = array_pop($rule);
            foreach ($rule as $needle) {
                if (strpos($n, $needle) !== false) {
                    return $icon;
                }
            }
        }
        return 'fa-utensils';
    }
}

if (!function_exists('resolveMenuItemImagePath')) {
    /**
     * Öncelik: dosya yükleme > URL > mevcut görsel.
     * Geçersiz URL için false döner.
     */
    function resolveMenuItemImagePath(array $file, $urlInput, $existing, $uploadDir) {
        if (!empty($file['name']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $processResult = processAndSaveImage($file, $uploadDir, 'item_');
            if ($processResult['success']) {
                return $processResult['filename'];
            }
            return false;
        }

        $url = trim((string) $urlInput);
        if ($url !== '') {
            if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }
            return $url;
        }

        $existing = trim((string) $existing);
        return $existing !== '' ? $existing : null;
    }
}

if (!function_exists('menuParseAllergenList')) {
    function menuParseAllergenList($value) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\r\n,;]+/', (string) $value);
        }

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $clean[mb_strtolower($item, 'UTF-8')] = $item;
        }

        return array_values($clean);
    }
}
