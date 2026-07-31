<?php
require_once __DIR__ . '/includes/seo-meta.php';
header('Content-Type: text/plain; charset=utf-8');

$baseUrl = rtrim(function_exists('seoGetBaseUrl') ? seoGetBaseUrl() : 'http://localhost', '/');
?>
User-agent: *
Allow: /
Allow: /llms.txt
Disallow: /admin/
Disallow: /config/
Disallow: /includes/
Disallow: /temp_unzip/

# LLM site index (llms.txt spec): <?= $baseUrl ?>/llms.txt

Sitemap: <?= $baseUrl ?>/sitemap.xml
