<?php

if (!function_exists('blogListSelectSql')) {
    function blogListSelectSql(): string
    {
        return 'id, title, slug, summary, image_path, created_at, meta_description, meta_keywords';
    }
}

if (!function_exists('blogResolveImageUrl')) {
    /**
     * @param 'thumb'|'card'|'full' $size thumb=sidebar, card=listing, full=detail hero
     */
    function blogResolveImageUrl(?string $path, string $size = 'card'): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            $path = 'default_cover.jpg';
        }

        if (strpos($path, 'http') === 0) {
            if ($size !== 'full' && strpos($path, 'images.unsplash.com') !== false) {
                $width = $size === 'thumb' ? 120 : 480;
                $base = preg_replace('/\?.*$/', '', $path);
                return $base . '?w=' . $width . '&q=75&auto=format&fit=crop';
            }

            return $path;
        }

        return '/public/uploads/' . $path;
    }
}
