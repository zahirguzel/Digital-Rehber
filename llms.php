<?php
require_once __DIR__ . '/includes/seo-meta.php';
require_once __DIR__ . '/includes/district-helpers.php';
header('Content-Type: text/markdown; charset=utf-8');

$siteTitle = function_exists('seoGetSiteTitle') ? seoGetSiteTitle() : 'Şehir Rehberi';
$region = function_exists('seoGetRegionName') ? seoGetRegionName() : 'Şehir';
$baseUrl = rtrim(function_exists('seoGetBaseUrl') ? seoGetBaseUrl() : 'http://localhost', '/');
$districts = function_exists('seoGetSehirDistricts') ? seoGetSehirDistricts() : ['Merkez'];
?>
# <?= $siteTitle ?>

> <?= $region ?>'ın en güncel esnaf, işletme, etkinlik ve influencer dijital rehberi. <?= implode(', ', array_slice($districts, 0, 4)) ?> ve tüm ilçelerde yerel firmaların telefon, adres, WhatsApp ve harita bilgileri.

<?= $siteTitle ?> (<?= $baseUrl ?>), <?= $region ?> bölgesindeki yerel işletmeleri, etkinlikleri ve içerik üreticilerini tek platformda listeler. Platform; restoran, kafe, otomotiv, sağlık, giyim ve diğer sektörlerde arama, filtreleme ve dijital vitrin hizmeti sunar.

## Ana Bölümler

- [Ana Sayfa](<?= $baseUrl ?>/): <?= $region ?> esnaf arama, ilçe filtreleri, premium vitrinler, etkinlik ve blog özetleri
- [İşletmeler / Esnaflar](<?= $baseUrl ?>/esnaflar): Tüm <?= $region ?> işletmeleri - ilçe, kategori ve anahtar kelime ile filtreleme
- [İlçeler](<?= $baseUrl ?>/ilceler): <?= count($districts) ?> ilçe için yerel işletme ve gezi rehberi hub sayfası
- [Etkinlikler](<?= $baseUrl ?>/etkinlikler): <?= $region ?> konser, festival, kültür ve yerel etkinlik takvimi
- [Influencerlar](<?= $baseUrl ?>/influencerlar): Doğrulanmış <?= $region ?> içerik üreticileri rehberi
- [Blog](<?= $baseUrl ?>/blog): Gezilecek yerler, mutfak, ilçe rehberleri ve tur planları
- [Sıkça Sorulan Sorular](<?= $baseUrl ?>/sikca-sorulan-sorular): İşletme kaydı, premium vitrin, QR profil, dijital menü
- [İletişim](<?= $baseUrl ?>/iletisim): İşletme ekleme ve destek formu

## Hizmetler

- [Google Harita Kaydı](<?= $baseUrl ?>/hizmetlerimiz/google-harita)
- [Sosyal Medya Yönetimi](<?= $baseUrl ?>/hizmetlerimiz/sosyal-medya)
- [QR Menü & Dijital Kartvizit](<?= $baseUrl ?>/hizmetlerimiz/qr-menu)
- [Premium Rehber Vitrini](<?= $baseUrl ?>/hizmetlerimiz/esnaf-vitrini)
- [Tüm Hizmetler](<?= $baseUrl ?>/hizmetlerimiz)

## İlçe Rehberleri

Her ilçe sayfası; yerel işletme listesi, istatistikler, gezi rehberi bağlantısı ve SSS içerir.

<?php foreach ($districts as $district): ?>
- [<?= $district ?>](<?= $baseUrl ?>/ilce/<?= seoDistrictNameToSlug($district) ?>): <?= $region ?> - <?= $district ?> yerel işletme ve rehber sayfası
<?php endforeach; ?>

## Öne Çıkan Blog Rehberleri

- [<?= $region ?>'da Gezilecek Yerler](<?= $baseUrl ?>/blog/): Kapsamlı <?= $region ?> tur planı ve rehberleri
