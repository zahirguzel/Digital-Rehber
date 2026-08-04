<?php
// Veritabanı bağlantı ayarları
// DİKKAT: Buradaki veritabanı bilgileriyle, ana dizindeki ".env" dosyası içindeki DB_ bilgileri BİREBİR AYNI olmalıdır!
$host = 'localhost';       // Genelde localhost kalır
$dbname = 'hatayweb';      // Canlı ortam veritabanı adını buraya girin (Örn: u12345_dbadi)
$username = 'root';        // Canlı ortam kullanıcı adını buraya girin (Örn: u12345_kullanici)
$password = '';            // Canlı ortam veritabanı şifresini girin

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
