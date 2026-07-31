<?php
header("Content-Type: application/xml; charset=utf-8");
require_once 'config/db.php';
require_once __DIR__ . '/includes/seo-meta.php';

// Resolve base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $domain . str_replace('sitemap.php', '', $_SERVER['PHP_SELF'] ?? '/');
if (substr($baseUrl, -1) !== '/') {
    $baseUrl .= '/';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Static Pages -->
    <url>
        <loc><?= $baseUrl ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>hakkimizda</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>hizmetlerimiz</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php
    try {
        $servicesSitemap = $pdo->query('SELECT slug FROM services ORDER BY id ASC')->fetchAll();
        foreach ($servicesSitemap as $srv) {
            echo "    <url>\n";
            echo '        <loc>' . $baseUrl . 'hizmetlerimiz/' . htmlspecialchars($srv['slug']) . "</loc>\n";
            echo "        <changefreq>monthly</changefreq>\n";
            echo "        <priority>0.75</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>
    <url>
        <loc><?= $baseUrl ?>iletisim</loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>blog</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>vizyon-misyon</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>sikca-sorulan-sorular</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>bolgeler</loc>
        <changefreq>weekly</changefreq>
        <priority>0.85</priority>
    </url>
    <?php
    require_once __DIR__ . '/includes/district-helpers.php';
    foreach (seoGetŞehirDistricts() as $district) {
        $districtSlug = seoDistrictNameToSlug($district);
        if (!$districtSlug) {
            continue;
        }
        echo "    <url>\n";
        echo "        <loc>" . $baseUrl . "ilce/" . htmlspecialchars($districtSlug) . "</loc>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.8</priority>\n";
        echo "    </url>\n";
    }
    ?>
    <url>
        <loc><?= $baseUrl ?>gizlilik-politikasi</loc>
        <changefreq>yearly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>kullanim-kosullari</loc>
        <changefreq>yearly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>esnaflar</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>influencerlar</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>influencer-basvuru</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>etkinlikler</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>kampanyalar</loc>
        <changefreq>daily</changefreq>
        <priority>0.85</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>nobetci-eczane</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <?php
    foreach (seoGetŞehirDistricts() as $district) {
        $districtSlug = seoDistrictNameToSlug($district);
        if (!$districtSlug) {
            continue;
        }
        echo "    <url>\n";
        echo "        <loc>" . $baseUrl . "nobetci-eczane/" . htmlspecialchars($districtSlug) . "</loc>\n";
        echo "        <changefreq>daily</changefreq>\n";
        echo "        <priority>0.85</priority>\n";
        echo "    </url>\n";
    }
    ?>

    <!-- Businesses -->
    <?php
    try {
        $businesses = $pdo->query("SELECT slug FROM businesses")->fetchAll();
        foreach ($businesses as $biz) {
            echo "    <url>\n";
            echo "        <loc>" . $baseUrl . "esnaf/" . htmlspecialchars($biz['slug']) . "</loc>\n";
            echo "        <changefreq>weekly</changefreq>\n";
            echo "        <priority>0.9</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <!-- Influencers -->
    <?php
    try {
        $influencers = $pdo->query("SELECT slug FROM influencers WHERE is_published = 1 AND consent_given = 1")->fetchAll();
        foreach ($influencers as $inf) {
            echo "    <url>\n";
            echo "        <loc>" . $baseUrl . "influencer/" . htmlspecialchars($inf['slug']) . "</loc>\n";
            echo "        <changefreq>weekly</changefreq>\n";
            echo "        <priority>0.85</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <!-- Events -->
    <?php
    try {
        $eventsSitemap = $pdo->query("SELECT slug FROM events WHERE is_published = 1")->fetchAll();
        foreach ($eventsSitemap as $ev) {
            echo "    <url>\n";
            echo "        <loc>" . $baseUrl . "etkinlik/" . htmlspecialchars($ev['slug']) . "</loc>\n";
            echo "        <changefreq>weekly</changefreq>\n";
            echo "        <priority>0.85</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <!-- Campaigns -->
    <?php
    try {
        $campaignsSitemap = $pdo->query("SELECT slug FROM campaigns WHERE is_published = 1")->fetchAll();
        foreach ($campaignsSitemap as $camp) {
            echo "    <url>\n";
            echo "        <loc>" . $baseUrl . "kampanya/" . htmlspecialchars($camp['slug']) . "</loc>\n";
            echo "        <changefreq>weekly</changefreq>\n";
            echo "        <priority>0.8</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>

    <!-- Blogs -->
    <?php
    try {
        $blogs = $pdo->query("SELECT slug FROM blogs ORDER BY created_at DESC")->fetchAll();
        foreach ($blogs as $blog) {
            echo "    <url>\n";
            echo "        <loc>" . $baseUrl . "blog/" . htmlspecialchars($blog['slug']) . ".html</loc>\n";
            echo "        <changefreq>weekly</changefreq>\n";
            echo "        <priority>0.8</priority>\n";
            echo "    </url>\n";
        }
    } catch (Exception $e) {}
    ?>
</urlset>
