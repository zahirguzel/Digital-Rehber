<?php

if (!function_exists('getAllDistrictsFromDB')) {
    function getAllDistrictsFromDB($onlyNames = false) {
        static $districts = null;
        if ($districts === null) {
            try {
                if (class_exists('Database')) {
                    $pdo = Database::getInstance()->getPDO();
                } else {
                    global $pdo;
                }
                
                if ($pdo) {
                    $stmt = $pdo->query("SELECT * FROM district_pages ORDER BY sort_order ASC, district_name ASC");
                    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $districts = [];
                }
            } catch (Exception $e) {
                $districts = [];
            }
        }
        
        if ($onlyNames) {
            $names = [];
            foreach ($districts as $d) {
                $names[] = $d['district_name'];
            }
            return $names;
        }
        
        return $districts;
    }
}

if (!function_exists('seoDistrictNameToSlug')) {
    function seoDistrictSlugify($value) {
        return strtolower(str_replace([' ', 'ğ', 'ü', 'ş', 'ı', 'ö', 'ç', 'Ğ', 'Ü', 'Ş', 'İ', 'Ö', 'Ç'], ['-', 'g', 'u', 's', 'i', 'o', 'c', 'g', 'u', 's', 'i', 'o', 'c'], trim((string) $value)));
    }

    function seoDistrictNameToSlug($name) {
        $districts = getAllDistrictsFromDB();
        foreach ($districts as $d) {
            if (mb_strtolower($d['district_name']) === mb_strtolower($name)) {
                return $d['slug'];
            }
        }
        // Fallback for case insensitive simple match or replace spaces
        return seoDistrictSlugify($name);
    }
}

if (!function_exists('seoDistrictSlugToName')) {
    function seoDistrictSlugToName($slug) {
        $slug = seoDistrictSlugify($slug);
        $districts = getAllDistrictsFromDB();
        foreach ($districts as $d) {
            if (seoDistrictSlugify($d['slug']) === $slug) {
                return $d['district_name'];
            }
        }

        if (function_exists('seoGetŞehirDistricts')) {
            foreach (seoGetŞehirDistricts() as $districtName) {
                if (seoDistrictSlugify($districtName) === $slug) {
                    return $districtName;
                }
            }
        }

        return null;
    }
}

if (!function_exists('getDistrictDefaultFaqs')) {
    function getDistrictDefaultFaqs($districtName, $blogSlug = null) {
        $regionName = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
        $siteTitle = function_exists('seoGetSiteTitle') ? seoGetSiteTitle() : 'Şehir Rehberi';
        $blogAnswer = $blogSlug
            ? $districtName . ' gezilecek yerler, tarihi ve tur planı için blog rehberimizi inceleyebilirsiniz: /blog/' . $blogSlug
            : $districtName . ' ve ' . $regionName . ' geneli gezilecek yerler için blog bölümümüzde ilçe rehberleri ve tur planları yer alır.';

        return [
            [
                'q' => $districtName . ' ilçesinde hangi işletmeler listeleniyor?',
                'a' => $siteTitle . '\'de ' . $districtName . ' ilçesinde restoran, kafe, otomotiv, giyim, sağlık ve daha birçok kategoride yerel işletmeler telefon, adres, WhatsApp ve harita bilgileriyle listelenir.',
            ],
            [
                'q' => $districtName . ' esnaf rehberine işletme nasıl eklenir?',
                'a' => 'İletişim sayfamızdaki formu doldurarak veya destek hattımızı arayarak ' . $districtName . ' bölgesindeki işletmenizi rehbere ekletebilirsiniz. Onay sonrası profil yayına alınır.',
            ],
            [
                'q' => $districtName . ' için gezi ve tur rehberi var mı?',
                'a' => $blogAnswer,
            ],
        ];
    }
}

if (!function_exists('getDistrictFaqs')) {
    function getDistrictFaqs($districtName, $blogSlug = null) {
        return getDistrictDefaultFaqs($districtName, $blogSlug);
    }
}

if (!function_exists('getDistrictStaticProfiles')) {
    function getDistrictStaticProfiles() {
        return [];
    }
}

if (!function_exists('parseDistrictHighlights')) {
    function parseDistrictHighlights($text) {
        if ($text === null || $text === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = $line;
            }
        }
        return $items;
    }
}

if (!function_exists('encodeDistrictHighlights')) {
    function encodeDistrictHighlights(array $items) {
        return implode("\n", array_values(array_filter(array_map('trim', $items))));
    }
}

if (!function_exists('formatDistrictPageRow')) {
    function formatDistrictPageRow(array $row) {
        $blogSlug = !empty($row['blog_slug']) ? $row['blog_slug'] : null;
        $faqs = [];
        if (!empty($row['faqs_json'])) {
            $decoded = json_decode($row['faqs_json'], true);
            if (is_array($decoded)) {
                $faqs = $decoded;
            }
        }
        if (empty($faqs)) {
            $faqs = getDistrictDefaultFaqs($row['district_name'], $blogSlug);
        }

        return [
            'id' => (int) $row['id'],
            'district_name' => $row['district_name'],
            'slug' => $row['slug'],
            'tagline' => $row['tagline'] ?? '',
            'intro' => $row['intro'] ?? '',
            'highlights' => parseDistrictHighlights($row['highlights'] ?? ''),
            'blog_slug' => $blogSlug,
            'meta_description' => $row['meta_description'] ?? '',
            'is_published' => (int) ($row['is_published'] ?? 1),
            'faqs' => $faqs,
        ];
    }
}

if (!function_exists('getDistrictPageByName')) {
    function getDistrictPageByName($pdo, $districtName) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM district_pages WHERE district_name = ? LIMIT 1');
            $stmt->execute([$districtName]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('getDistrictPageById')) {
    function getDistrictPageById($pdo, $id) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM district_pages WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $id]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('getDistrictPagesList')) {
    function getDistrictPagesList($pdo, $publishedOnly = false) {
        try {
            $sql = 'SELECT dp.*, (SELECT COUNT(*) FROM businesses b WHERE b.district = dp.district_name) AS business_count
                    FROM district_pages dp';
            if ($publishedOnly) {
                $sql .= ' WHERE dp.is_published = 1';
            }
            $sql .= ' ORDER BY dp.sort_order ASC, dp.district_name ASC';
            return $pdo->query($sql)->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getDistrictLandingData')) {
    function getDistrictLandingData($districtName, $pdo = null) {
        if (!$pdo) {
            if (class_exists('Database')) {
                $pdo = Database::getInstance()->getPDO();
            } else {
                global $pdo;
            }
        }

        $row = getDistrictPageByName($pdo, $districtName);
        if ($row && (int) $row['is_published']) {
            return formatDistrictPageRow($row);
        }

        if (function_exists('seoGetŞehirDistricts')) {
            $availableDistricts = seoGetŞehirDistricts();
            if (!in_array($districtName, $availableDistricts, true)) {
                return null;
            }
        }

        $_siteTitle = function_exists('seoGetSiteTitle') ? seoGetSiteTitle() : 'Şehir Rehberi';
        $_region = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';

        return [
            'id' => 0,
            'district_name' => $districtName,
            'slug' => seoDistrictNameToSlug($districtName),
            'tagline' => $_region . ' - ' . $districtName . ' ilçesindeki işletme, esnaf ve yerel hizmet rehberi',
            'intro' => $_siteTitle . ', ' . $districtName . ' ilçesindeki restoran, kafe, esnaf, alışveriş ve yerel hizmet işletmelerini güncel telefon, adres, WhatsApp ve konum bilgileriyle dijital vitrinde listeler. ' . $districtName . ' ilçesinde aradığınız tüm firmalara kolayca ulaşın.',
            'highlights' => [
                $districtName . ' ilçesindeki yerel işletmeler ve esnaf listesi',
                'Telefon, adres, WhatsApp ve harita konumu ile hızlı iletişim',
                $_region . ' genelinde ilçe ve kategori bazlı filtreli arama',
            ],
            'blog_slug' => null,
            'meta_description' => $districtName . ' ilçesindeki esnaf, restoran, kafe, alışveriş ve hizmet firmalarının adres, telefon ve konum bilgileri ' . $_siteTitle . ' üzerinde.',
            'is_published' => 1,
            'faqs' => getDistrictDefaultFaqs($districtName, null),
        ];
    }
}

if (!function_exists('getDistrictBusinessStats')) {
    function getDistrictBusinessStats($pdo, $districtName) {
        $stats = ['total' => 0, 'premium' => 0, 'categories' => 0];
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE district = ?');
            $stmt->execute([$districtName]);
            $stats['total'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM businesses WHERE district = ? AND is_premium = 1');
            $stmt->execute([$districtName]);
            $stats['premium'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT category_id) FROM businesses WHERE district = ?');
            $stmt->execute([$districtName]);
            $stats['categories'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
        }
        return $stats;
    }
}

if (!function_exists('getDistrictBusinesses')) {
    function getDistrictBusinesses($pdo, $districtName, $limit = 6) {
        try {
            $stmt = $pdo->prepare(
                'SELECT b.*, c.name AS category_name
                 FROM businesses b
                 LEFT JOIN categories c ON b.category_id = c.id
                 WHERE b.district = ?
                 ORDER BY b.is_premium DESC, b.name ASC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute([$districtName]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getDistrictEvents')) {
    function getDistrictEvents($pdo, $districtName, $limit = 3) {
        try {
            $stmt = $pdo->prepare(
                'SELECT title, slug, start_date, district
                 FROM events
                 WHERE is_published = 1 AND district = ?
                 ORDER BY start_date ASC
                 LIMIT ' . (int) $limit
            );
            $stmt->execute([$districtName]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
