<?php
require 'c:/xampp/htdocs/digitalrehber/autoload.php';
$db = Database::getInstance()->getPDO();

// 1. Update SEO keywords and description
$desc = "Kıbrıs'taki en iyi restoranlar, tamirciler, eczaneler ve yerel işletmeler. Girne, Lefkoşa ve Mağusa'nın güncel dijital esnaf rehberi.";
$keywords = "kıbrıs mekanlar, girne restoranları, lefkoşa nöbetçi eczane, kıbrıs esnaf rehberi, mağusa tamirciler";

$stmt = $db->prepare("UPDATE settings SET site_description = ?, site_keywords = ? WHERE id = 1");
$stmt->execute([$desc, $keywords]);

// 2. Insert dummy blog post if not exists
$title = "Kıbrıs'ta Ne Yenir? En İyi Kıbrıs Restoranları Rehberi";
$slug = "kibrista-ne-yenir-en-iyi-kibris-restoranlari-rehberi";
$content = "<h2>Kıbrıs'ın Eşsiz Lezzetleri</h2><p>Kıbrıs, tarihi dokusu ve kültürel zenginliğinin yanı sıra muazzam yemek kültürüyle de öne çıkar. Hellim peyniri, şeftali kebabı ve fırın makarnası gibi yerel lezzetleri en iyi yapan restoranları arıyorsanız doğru yerdesiniz.</p><p>Özellikle Girne limanındaki restoranlar ve Lefkoşa'nın tarihi sokaklarındaki meyhaneler, Kıbrıs'a özgü tatları denemek için mükemmel seçenekler sunar. Dijital rehberimiz üzerinden Kıbrıs'taki en popüler ve yüksek puanlı yeme-içme mekanlarına ulaşabilir, konumlarını inceleyebilirsiniz.</p>";
$excerpt = "Kıbrıs'ın meşhur şeftali kebabından, hellim peynirine kadar en iyi yerel lezzetleri bulabileceğiniz restoran rehberi.";
$image_path = "default.png";
$is_published = 1;
$created_at = date('Y-m-d H:i:s');
$updated_at = $created_at;

$check = $db->prepare("SELECT id FROM blogs WHERE slug = ?");
$check->execute([$slug]);
if ($check->rowCount() == 0) {
    $stmt2 = $db->prepare("INSERT INTO blogs (title, slug, content, image_path, is_published, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt2->execute([$title, $slug, $content, $image_path, $is_published, $created_at, $updated_at]);
    echo "SEO güncellendi ve Blog eklendi.";
} else {
    echo "SEO güncellendi, Blog zaten var.";
}
