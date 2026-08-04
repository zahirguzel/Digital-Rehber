<?php
require "autoload.php";
require "config/db.php";
$db = Database::getInstance()->getPDO();

$title = "Hakkýmýzda - Þehrin Dijital Rehberi";
$meta_desc = "Þehrin en kapsamlý dijital iþletme, esnaf, etkinlik ve influencer rehberi. Yerel iþletmeleri keþfedin, en güncel etkinliklerden anýnda haberdar olun.";
$content = "<p class=\"lead\">Þehrin dijital dünyasýna hoþ geldiniz! <strong>Digital Rehber</strong>, yerel iþletmeleri, esnaflarý, etkinlikleri ve þehrin öne çýkan içerik üreticilerini (influencerlarý) tek bir çatý altýnda toplayan yenilikçi bir platformdur.</p>\n\n<h3 class=\"mt-4 mb-3 fw-bold text-navy\">Misyonumuz</h3>\n<p>Yerel ekonomiyi dijitalleþtirmek ve kullanýcýlarýn aradýklarý her þeye en hýzlý, en güvenilir þekilde ulaþmalarýný saðlamak birincil önceliðimizdir. Esnafýmýzla tüketiciyi þeffaf ve modern bir arayüzde buluþtururken, þehrin kültürel ve sosyal ritmini de yakýndan takip ediyoruz.</p>\n\n<h3 class=\"mt-4 mb-3 fw-bold text-navy\">Neler Sunuyoruz?</h3>\n<ul class=\"list-unstyled\">\n    <li class=\"mb-2\"><i class=\"fa-solid fa-check text-primary me-2\"></i> <strong>Detaylý Ýþletme Profilleri:</strong> Kategori ve bölge bazlý aramalarla aradýðýnýz mekana anýnda ulaþýn.</li>\n    <li class=\"mb-2\"><i class=\"fa-solid fa-check text-primary me-2\"></i> <strong>Influencer Ýþ Birlikleri:</strong> Markanýzý büyütecek doðru içerik üreticileriyle doðrudan iletiþim kurun.</li>\n    <li class=\"mb-2\"><i class=\"fa-solid fa-check text-primary me-2\"></i> <strong>Güncel Etkinlik Takvimi:</strong> Þehrinizdeki konser, tiyatro ve festivallerden ilk siz haberdar olun.</li>\n    <li class=\"mb-2\"><i class=\"fa-solid fa-check text-primary me-2\"></i> <strong>Dijital Kartvizit & QR Menü:</strong> Ýþletmelere özel modern çözümlerle dijitalleþmeye hýz katýyoruz.</li>\n</ul>\n\n<h3 class=\"mt-4 mb-3 fw-bold text-navy\">Neden Biz?</h3>\n<p>Klasik rehber anlayýþýný tamamen deðiþtirerek yapay zeka ve güncel dijital trendlerle donatýlmýþ bir altyapý sunuyoruz. Gerek iþletme sahipleri gerekse son kullanýcýlar için tamamen ücretsiz, þeffaf ve güvenli (KVKK uyumlu) bir deneyim inþa ettik. Her gün büyüyen veritabanýmýzla þehrin en büyük dijital arþivi olma yolunda emin adýmlarla ilerliyoruz.</p>";

$stmt = $db->prepare("UPDATE pages SET title=?, meta_description=?, content=? WHERE slug=\"hakkimizda\"");
$stmt->execute([$title, $meta_desc, $content]);
echo "UPDATED";

