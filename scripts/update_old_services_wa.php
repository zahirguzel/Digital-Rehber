<?php
require_once __DIR__ . '/../autoload.php';
$pdo = Database::getInstance()->getPDO();

// Get site WhatsApp number
$wp = $pdo->query("SELECT contact_whatsapp FROM settings WHERE id=1")->fetchColumn();
// Normalize to international format (strip leading 0, add 90 country code)
$wpNum = preg_replace('/\D/', '', $wp);
if (strlen($wpNum) === 10 && $wpNum[0] === '5') {
    $wpNum = '90' . $wpNum;
}
echo "WhatsApp number: $wpNum\n";

// Build per-service WA texts for the old 6 services
$serviceWA = [
    1 => rawurlencode('Google Harita kaydı hizmetiniz hakkında bilgi almak istiyorum.'),
    2 => rawurlencode('Sosyal medya yönetimi hizmetiniz hakkında bilgi almak istiyorum.'),
    3 => rawurlencode('Görsel ve AI tasarım hizmetiniz hakkında bilgi almak istiyorum.'),
    4 => rawurlencode('QR Menü ve Dijital Kartvizit hizmetiniz hakkında bilgi almak istiyorum.'),
    5 => rawurlencode('Web tasarımı hizmetiniz hakkında bilgi almak istiyorum.'),
    6 => rawurlencode('Premium Rehber Vitrini hizmetiniz hakkında bilgi almak istiyorum.'),
];

foreach ($serviceWA as $id => $text) {
    $url = 'https://wa.me/' . $wpNum . '?text=' . $text;
    $pdo->prepare("UPDATE services SET cta_url=?, cta_type='whatsapp' WHERE id=?")->execute([$url, $id]);
    echo "Updated service #$id with WA URL.\n";
}

echo "\nDone.\n";
