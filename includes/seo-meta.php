<?php
require_once __DIR__ . '/../autoload.php';
if (!function_exists('seoGetBaseUrl')) {
    function seoGetBasePath() {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $basePath = rtrim(dirname($scriptName), '/\\');

        $basePath = preg_replace('#/(isletme|admin|api|includes)(/.*)?$#i', '', $basePath);

        if ($basePath === '.' || $basePath === '/') {
            return '';
        }

        return $basePath;
    }

    function seoGetBaseUrl() {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : 'localhost';
        $basePath = seoGetBasePath();

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = preg_replace('/^www\./', '', $host);

        return $protocol . '://' . $host . $basePath;
    }
}

if (!function_exists('seoGetCanonicalUrl')) {
    function seoGetCanonicalUrl($overrideUrl = null) {
        if (!empty($overrideUrl)) {
            return $overrideUrl;
        }

        $base = seoGetBaseUrl();
        $path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';

        if ($path === false || $path === '' || $path === '/index.php') {
            return rtrim($base, '/') . '/';
        }

        return rtrim($base, '/') . $path;
    }
}

if (!function_exists('seoListingPageMeta')) {
    /**
     * Filtreli liste sayfaları: canonical her zaman temiz URL, filtre varsa noindex.
     *
     * @return array{canonical: string, robots: string|null}
     */
    function seoListingPageMeta(string $basePath, bool $hasFilters): array {
        $canonical = rtrim(seoGetBaseUrl(), '/') . $basePath;

        return [
            'canonical' => $canonical,
            'robots' => $hasFilters ? 'noindex, follow' : null,
        ];
    }
}

if (!function_exists('seoGetLogoUrl')) {
    function seoGetLogoUrl($siteSettings, $baseUrl) {
        if (empty($siteSettings['site_logo'])) {
            return rtrim($baseUrl, '/') . '/public/images/default_favicon.png';
        }

        if (strpos($siteSettings['site_logo'], 'http') === 0) {
            return $siteSettings['site_logo'];
        }

        return rtrim($baseUrl, '/') . '/public/images/' . ltrim($siteSettings['site_logo'], '/');
    }
}

if (!function_exists('seoResolveAbsoluteUrl')) {
    function seoResolveAbsoluteUrl($path, $baseUrl) {
        if (empty($path)) {
            return '';
        }
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('seoGetShareImageUrl')) {
    function seoGetShareImageUrl($siteSettings, $baseUrl, $overrideImage = null) {
        if (!empty($overrideImage)) {
            return seoResolveAbsoluteUrl($overrideImage, $baseUrl);
        }
        if (!empty($siteSettings['site_logo'])) {
            return seoGetLogoUrl($siteSettings, $baseUrl);
        }
        $ogSharePath = dirname(__DIR__) . '/public/images/og-share.png';
        if (file_exists($ogSharePath)) {
            return rtrim($baseUrl, '/') . '/public/images/og-share.png';
        }
        return seoGetLogoUrl($siteSettings, $baseUrl);
    }
}

if (!function_exists('seoTruncateMetaDescription')) {
    /**
     * Meta description: kelime ortasında kesmez, sonuna ... ekler (varsayılan max 160).
     */
    function seoTruncateMetaDescription($text, $maxLength = 160) {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength - 3);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, '.,;:- ') . '...';
    }
}

if (!function_exists('seoDetectImageMimeType')) {
    function seoDetectImageMimeType($url) {
        $path = parse_url((string) $url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];

        return $map[$ext] ?? 'image/jpeg';
    }
}

if (!function_exists('seoRenderSocialMetaTags')) {
    function seoRenderSocialMetaTags($config) {
        $title = $config['title'] ?? '';
        $description = $config['description'] ?? '';
        $url = $config['url'] ?? '';
        $image = $config['image'] ?? '';
        $type = $config['type'] ?? 'website';
        $siteName = $config['siteName'] ?? '';
        $imageAlt = $config['imageAlt'] ?? $siteName;
        $locale = $config['locale'] ?? 'tr_TR';

        echo '<link rel="canonical" href="' . SecurityHelper::escape($url) . '">' . "\n";
        echo '<meta property="og:type" content="' . SecurityHelper::escape($type) . '">' . "\n";
        echo '<meta property="og:locale" content="' . SecurityHelper::escape($locale) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . SecurityHelper::escape($siteName) . '">' . "\n";
        echo '<meta property="og:title" content="' . SecurityHelper::escape($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . SecurityHelper::escape($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . SecurityHelper::escape($url) . '">' . "\n";

        if (!empty($image)) {
            echo '<meta property="og:image" content="' . SecurityHelper::escape($image) . '">' . "\n";
            echo '<meta property="og:image:secure_url" content="' . SecurityHelper::escape($image) . '">' . "\n";
            echo '<meta property="og:image:alt" content="' . SecurityHelper::escape($imageAlt) . '">' . "\n";
            echo '<meta property="og:image:type" content="' . SecurityHelper::escape(seoDetectImageMimeType($image)) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
        }

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . SecurityHelper::escape($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . SecurityHelper::escape($description) . '">' . "\n";
        if (!empty($url)) {
            echo '<meta name="twitter:url" content="' . SecurityHelper::escape($url) . '">' . "\n";
        }
        if (!empty($image)) {
            echo '<meta name="twitter:image" content="' . SecurityHelper::escape($image) . '">' . "\n";
            echo '<meta name="twitter:image:alt" content="' . SecurityHelper::escape($imageAlt) . '">' . "\n";
        }
    }
}

if (!function_exists('seoBuildOrganizationSchema')) {
    function seoBuildOrganizationSchema($siteSettings, $baseUrl, $canonicalUrl) {
        $_region = seoGetRegionName();
        $_firstDistrict = 'Merkez';
        if (function_exists('seoGetSehirDistricts')) {
            $d = seoGetSehirDistricts();
            if (!empty($d)) {
                $_firstDistrict = $d[0];
            }
        }
        $orgGeo = seoGetŞehirDistrictGeo($_firstDistrict);
        $organization = [
            '@type' => 'Organization',
            '@id' => rtrim($baseUrl, '/') . '/#organization',
            'name' => $siteSettings['site_title'] ?? 'Şehir Rehberi',
            'url' => rtrim($baseUrl, '/') . '/',
            'logo' => seoGetLogoUrl($siteSettings, $baseUrl),
            'description' => $siteSettings['site_description'] ?? '',
            'email' => !empty($siteSettings['contact_email']) ? $siteSettings['contact_email'] : null,
            'telephone' => !empty($siteSettings['contact_phone']) ? $siteSettings['contact_phone'] : null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $siteSettings['contact_address'] ?? ($_firstDistrict . ', ' . $_region),
                'addressLocality' => $_firstDistrict,
                'addressRegion' => $_region,
                'postalCode' => '',
                'addressCountry' => 'TR',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $orgGeo['latitude'],
                'longitude' => $orgGeo['longitude'],
            ],
            'areaServed' => seoBuildAreaServedGraph(),
            'knowsAbout' => [
                'Yerel esnaf rehberi',
                $_region . ' işletmeleri',
                $_firstDistrict . ' ve ' . $_region . ' yerel firmalar',
                'Etkinlik takvimi',
                'Influencer rehberi',
                'QR dijital kartvizit',
                'Dijital menü',
            ],
            'sameAs' => array_values(array_filter([
                (!empty($siteSettings['social_youtube']) && $siteSettings['social_youtube'] !== '#') ? $siteSettings['social_youtube'] : null,
                (!empty($siteSettings['social_facebook']) && $siteSettings['social_facebook'] !== '#') ? $siteSettings['social_facebook'] : null,
                (!empty($siteSettings['social_instagram']) && $siteSettings['social_instagram'] !== '#') ? $siteSettings['social_instagram'] : null,
                (!empty($siteSettings['social_tiktok']) && $siteSettings['social_tiktok'] !== '#') ? $siteSettings['social_tiktok'] : null,
            ])),
        ];

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite',
                    '@id' => rtrim($baseUrl, '/') . '/#website',
                    'url' => rtrim($baseUrl, '/') . '/',
                    'name' => $siteSettings['site_title'] ?? ($siteSettings['site_title'] ?? 'Şehir Rehberi'),
                    'description' => $siteSettings['site_description'] ?? '',
                    'publisher' => [
                        '@id' => rtrim($baseUrl, '/') . '/#organization',
                    ],
                    'inLanguage' => 'tr-TR',
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => rtrim($baseUrl, '/') . '/esnaflar?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl . '#webpage',
                    'url' => $canonicalUrl,
                    'name' => ($GLOBALS['pageTitle'] ?? ($siteSettings['site_title'] ?? ($siteSettings['site_title'] ?? 'Şehir Rehberi'))),
                    'description' => $GLOBALS['metaDescription'] ?? ($siteSettings['site_description'] ?? ''),
                    'isPartOf' => [
                        '@id' => rtrim($baseUrl, '/') . '/#website',
                    ],
                    'about' => [
                        '@id' => rtrim($baseUrl, '/') . '/#organization',
                    ],
                    'inLanguage' => 'tr-TR',
                ],
            ],
        ];

        return $schema;
    }
}

if (!function_exists('seoGetDefaultCity')) {
    function seoGetDefaultCity() {
        try {
            $db = class_exists('Database') ? Database::getInstance()->getPDO() : (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
            if ($db) {
                $settings = $db->query("SELECT default_city FROM settings WHERE id = 1")->fetch();
                return trim((string) ($settings['default_city'] ?? ''));
            }
        } catch (Exception $e) {}
        return '';
    }
}

if (!function_exists('seoGetSiteSettingsRow')) {
    function seoGetSiteSettingsRow() {
        static $settings = null;

        if ($settings !== null) {
            return $settings;
        }

        try {
            $db = class_exists('Database') ? Database::getInstance()->getPDO() : (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
            if ($db) {
                $row = $db->query('SELECT * FROM settings WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $settings = $row;
                    return $settings;
                }
            }
        } catch (Exception $e) {}

        $settings = [];
        return $settings;
    }
}

if (!function_exists('seoGetSiteSetting')) {
    function seoGetSiteSetting($key, $default = '') {
        $settings = seoGetSiteSettingsRow();
        $safeKey = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
        if ($safeKey !== '' && array_key_exists($safeKey, $settings)) {
            $value = $settings[$safeKey];
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return $default;
    }
}

if (!function_exists('seoGetSiteTitle')) {
    function seoGetSiteTitle() {
        return seoGetSiteSetting('site_title', 'Şehir Rehberi');
    }
}

if (!function_exists('seoGetRegionName')) {
    function seoGetRegionName() {
        $city = seoGetDefaultCity();
        return $city !== '' ? $city : 'Şehir';
    }
}

if (!function_exists('seoGetCities')) {
    function seoGetCities() {
        try {
            $db = class_exists('Database') ? Database::getInstance()->getPDO() : (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
            if ($db) {
                $rows = $db->query("SELECT name FROM cities ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($rows)) {
                    return array_values(array_filter(array_map('trim', $rows), function($item) {
                        return $item !== '';
                    }));
                }
            }
        } catch (Exception $e) {}
        return [];
    }
}

if (!function_exists('seoGetDistrictsByCity')) {
    function seoGetDistrictsByCity($cityName) {
        $cityName = trim((string) $cityName);
        if ($cityName === '') {
            return [];
        }

        try {
            $db = class_exists('Database') ? Database::getInstance()->getPDO() : (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
            if ($db) {
                $stmt = $db->prepare("
                    SELECT d.name
                    FROM districts d
                    JOIN cities c ON d.city_id = c.id
                    WHERE c.name = ?
                    ORDER BY d.name ASC
                ");
                $stmt->execute([$cityName]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($rows)) {
                    return array_values(array_filter(array_map('trim', $rows), function($item) {
                        return $item !== '';
                    }));
                }
            }
        } catch (Exception $e) {}
        return [];
    }
}

if (!function_exists('seoFindCityByDistrict')) {
    function seoFindCityByDistrict($districtName, $preferredCity = '') {
        $districtName = trim((string) $districtName);
        if ($districtName === '') {
            return '';
        }

        try {
            $db = class_exists('Database') ? Database::getInstance()->getPDO() : (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
            if ($db) {
                if ($preferredCity !== '') {
                    $stmtPref = $db->prepare("
                        SELECT c.name
                        FROM districts d
                        JOIN cities c ON d.city_id = c.id
                        WHERE d.name = ? AND c.name = ?
                        LIMIT 1
                    ");
                    $stmtPref->execute([$districtName, $preferredCity]);
                    $prefCityName = $stmtPref->fetchColumn();
                    if ($prefCityName) {
                        return trim((string) $prefCityName);
                    }
                }

                $defCity = seoGetDefaultCity();
                if ($defCity !== '' && $defCity !== $preferredCity) {
                    $stmtDef = $db->prepare("
                        SELECT c.name
                        FROM districts d
                        JOIN cities c ON d.city_id = c.id
                        WHERE d.name = ? AND c.name = ?
                        LIMIT 1
                    ");
                    $stmtDef->execute([$districtName, $defCity]);
                    $defCityName = $stmtDef->fetchColumn();
                    if ($defCityName) {
                        return trim((string) $defCityName);
                    }
                }

                $stmtBiz = $db->prepare("
                    SELECT c.name
                    FROM districts d
                    JOIN cities c ON d.city_id = c.id
                    WHERE d.name = ?
                    ORDER BY (SELECT COUNT(*) FROM businesses b WHERE b.city = c.name) DESC, (c.name = 'Kıbrıs') DESC, c.name ASC
                    LIMIT 1
                ");
                $stmtBiz->execute([$districtName]);
                $cityName = $stmtBiz->fetchColumn();
                if ($cityName) {
                    return trim((string) $cityName);
                }
            }
        } catch (Exception $e) {}
        return '';
    }
}

if (!function_exists('seoGetŞehirDistricts')) {
    function seoGetŞehirDistricts() {
        $cityDistricts = seoGetDistrictsByCity(seoGetDefaultCity());
        if (!empty($cityDistricts)) {
            return $cityDistricts;
        }

        // Fallback to district_pages if the above fails or no city is set
        if (function_exists('getAllDistrictsFromDB')) {
            return getAllDistrictsFromDB(true);
        }
        return [];
    }
}

if (!function_exists('seoGetSehirDistricts')) {
    function seoGetSehirDistricts() {
        return seoGetŞehirDistricts();
    }
}
if (!function_exists('seoGetÅžehirDistricts')) {
    function seoGetÅžehirDistricts() {
        return seoGetŞehirDistricts();
    }
}


if (!function_exists('seoGetŞehirDistrictGeo')) {
    function seoGetŞehirDistrictGeo($district) {
        $coords = [
            'Antakya' => ['latitude' => 36.2025, 'longitude' => 36.1606],
            'İskenderun' => ['latitude' => 36.5872, 'longitude' => 36.1733],
            'Payas' => ['latitude' => 36.7569, 'longitude' => 36.2167],
            'Defne' => ['latitude' => 36.1950, 'longitude' => 36.1400],
            'Samandağ' => ['latitude' => 36.0800, 'longitude' => 35.9700],
            'Arsuz' => ['latitude' => 36.4120, 'longitude' => 35.8920],
            'Reyhanlı' => ['latitude' => 36.2680, 'longitude' => 36.5670],
            'Kırıkhan' => ['latitude' => 36.4990, 'longitude' => 36.3570],
            'Dörtyol' => ['latitude' => 36.8390, 'longitude' => 36.2150],
            'Harbiye' => ['latitude' => 36.1450, 'longitude' => 36.0800],
            'Erzin' => ['latitude' => 36.9540, 'longitude' => 36.1990],
            'Belen' => ['latitude' => 36.4890, 'longitude' => 36.1940],
        ];
        return isset($coords[$district]) ? $coords[$district] : ['latitude' => 36.2025, 'longitude' => 36.1606];
    }
}

if (!function_exists('seoNormalizePhone')) {
    function seoNormalizePhone($phone) {
        if (empty($phone)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 10 && $digits[0] === '5') {
            $digits = '90' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = '90' . substr($digits, 1);
        }
        return $digits !== '' ? '+' . $digits : null;
    }
}

if (!function_exists('seoExtractGeoFromEmbed')) {
    function seoExtractGeoFromEmbed($embed) {
        if (empty($embed)) {
            return null;
        }
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $embed, $m)) {
            return ['latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
        }
        if (preg_match('/!3d(-?\d+\.\d+)!2d(-?\d+\.\d+)/', $embed, $m)) {
            return ['latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
        }
        if (preg_match('/!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)/', $embed, $m)) {
            return ['latitude' => (float) $m[2], 'longitude' => (float) $m[1]];
        }
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $embed, $m)) {
            return ['latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
        }
        if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $embed, $m)) {
            return ['latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
        }
        return null;
    }
}

if (!function_exists('seoBuildAreaServedGraph')) {
    function seoBuildAreaServedGraph() {
        $items = [];
        foreach (seoGetŞehirDistricts() as $district) {
            $items[] = [
                '@type' => 'AdministrativeArea',
                'name' => $district . ', Şehir',
                'containedInPlace' => [
                    '@type' => 'AdministrativeArea',
                    'name' => 'Şehir',
                    'containedInPlace' => [
                        '@type' => 'Country',
                        'name' => 'Türkiye',
                    ],
                ],
            ];
        }
        return $items;
    }
}

if (!function_exists('seoBuildLocalBusinessSchema')) {
    function seoBuildLocalBusinessSchema($business, $categoryName, $baseUrl, $pageUrl, $images = []) {
        $orgId = rtrim($baseUrl, '/') . '/#organization';
        $geo = seoExtractGeoFromEmbed($business['google_maps_iframe'] ?? '');
        if (!$geo && !empty($business['district'])) {
            $geo = seoGetŞehirDistrictGeo($business['district']);
        }

        $sameAs = array_values(array_filter([
            !empty($business['website']) ? $business['website'] : null,
            !empty($business['instagram']) ? $business['instagram'] : null,
            !empty($business['facebook']) ? $business['facebook'] : null,
            !empty($business['tiktok']) ? $business['tiktok'] : null,
        ]));

        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'LocalBusiness',
                    '@id' => $pageUrl . '#localbusiness',
                    'name' => $business['name'],
                    'url' => $pageUrl,
                    'telephone' => seoNormalizePhone($business['phone'] ?? ''),
                    'image' => !empty($images) ? $images : null,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => !empty($business['address']) ? $business['address'] : $business['district'],
                        'addressLocality' => $business['district'],
                        'addressRegion' => 'Şehir',
                        'addressCountry' => 'TR',
                    ],
                    'geo' => [
                        '@type' => 'GeoCoordinates',
                        'latitude' => $geo['latitude'],
                        'longitude' => $geo['longitude'],
                    ],
                    'areaServed' => [
                        '@type' => 'City',
                        'name' => $business['district'] . ', Şehir',
                    ],
                    'parentOrganization' => ['@id' => $orgId],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Ana Sayfa',
                            'item' => rtrim($baseUrl, '/') . '/',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Esnaf Rehberi',
                            'item' => rtrim($baseUrl, '/') . '/esnaflar',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $business['name'],
                            'item' => $pageUrl,
                        ],
                    ],
                ],
            ],
        ];

        $localBusiness = &$schema['@graph'][0];
        if (!empty($business['description'])) {
            $localBusiness['description'] = trim(preg_replace('/\s+/', ' ', strip_tags($business['description'])));
            if (mb_strlen($localBusiness['description']) > 500) {
                $localBusiness['description'] = mb_substr($localBusiness['description'], 0, 497) . '...';
            }
        }
        if (!empty($categoryName)) {
            $localBusiness['category'] = $categoryName;
        }
        if (!empty($sameAs)) {
            $localBusiness['sameAs'] = $sameAs;
        }
        if (!empty($business['whatsapp'])) {
            $localBusiness['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => seoNormalizePhone($business['whatsapp']),
                'contactType' => 'customer service',
                'availableLanguage' => ['Turkish'],
            ];
        }

        return $schema;
    }
}

if (!function_exists('seoBuildFAQPageSchema')) {
    function seoBuildFAQPageSchema($faqs, $pageUrl) {
        $mainEntity = [];
        foreach ($faqs as $faq) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            '@id' => $pageUrl . '#faqpage',
            'url' => $pageUrl,
            'mainEntity' => $mainEntity,
        ];
    }
}

if (!function_exists('seoBuildDistrictLandingSchema')) {
    function seoBuildDistrictLandingSchema($districtName, $districtSlug, $baseUrl, $pageUrl, $profile, $stats = []) {
        $geo = seoGetŞehirDistrictGeo($districtName);
        $orgId = rtrim($baseUrl, '/') . '/#organization';

        $graph = [
            [
                '@type' => 'WebPage',
                '@id' => $pageUrl . '#webpage',
                'url' => $pageUrl,
                'name' => $districtName . ' İşletmeleri ve Yerel Rehber | Şehir',
                'description' => $profile['intro'] ?? '',
                'isPartOf' => ['@id' => rtrim($baseUrl, '/') . '/#website'],
                'about' => [
                    '@type' => 'Place',
                    '@id' => $pageUrl . '#place',
                    'name' => $districtName . ', Şehir',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $districtName,
                        'addressRegion' => 'Şehir',
                        'addressCountry' => 'TR',
                    ],
                    'geo' => [
                        '@type' => 'GeoCoordinates',
                        'latitude' => $geo['latitude'],
                        'longitude' => $geo['longitude'],
                    ],
                    'containedInPlace' => [
                        '@type' => 'AdministrativeArea',
                        'name' => 'Şehir',
                        'containedInPlace' => [
                            '@type' => 'Country',
                            'name' => 'Türkiye',
                        ],
                    ],
                ],
                'publisher' => ['@id' => $orgId],
                'inLanguage' => 'tr-TR',
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $pageUrl . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Ana Sayfa',
                        'item' => rtrim($baseUrl, '/') . '/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'İlçeler',
                        'item' => rtrim($baseUrl, '/') . '/bolgeler',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $districtName,
                        'item' => $pageUrl,
                    ],
                ],
            ],
        ];

        if (!empty($profile['faqs'])) {
            $mainEntity = [];
            foreach ($profile['faqs'] as $faq) {
                $mainEntity[] = [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['a'],
                    ],
                ];
            }
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $pageUrl . '#faqpage',
                'url' => $pageUrl,
                'mainEntity' => $mainEntity,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}

if (!function_exists('seoBuildArticleSchema')) {
    function seoBuildArticleSchema($post, $siteSettings, $baseUrl, $pageUrl, $imageUrl) {
        $orgId = rtrim($baseUrl, '/') . '/#organization';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Article',
                    '@id' => $pageUrl . '#article',
                    'headline' => $post['title'],
                    'description' => !empty($post['summary']) ? $post['summary'] : ($post['meta_description'] ?? ''),
                    'image' => [$imageUrl],
                    'datePublished' => date('c', strtotime($post['created_at'])),
                    'dateModified' => date('c', strtotime($post['created_at'])),
                    'author' => ['@id' => $orgId],
                    'publisher' => [
                        '@type' => 'Organization',
                        '@id' => $orgId,
                        'name' => $siteSettings['site_title'] ?? ($siteSettings['site_title'] ?? 'Şehir Rehberi'),
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => seoGetLogoUrl($siteSettings, $baseUrl),
                        ],
                    ],
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $pageUrl . '#webpage',
                    ],
                    'inLanguage' => 'tr-TR',
                    'about' => [
                        '@type' => 'Place',
                        'name' => 'Şehir',
                        'address' => [
                            '@type' => 'PostalAddress',
                            'addressRegion' => 'Şehir',
                            'addressCountry' => 'TR',
                        ],
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Ana Sayfa',
                            'item' => rtrim($baseUrl, '/') . '/',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Blog',
                            'item' => rtrim($baseUrl, '/') . '/blog',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $post['title'],
                            'item' => $pageUrl,
                        ],
                    ],
                ],
            ],
        ];
    }
}
