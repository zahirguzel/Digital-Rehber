<?php
// Veritabanı bağlantı ayarları
// Yerel geliştirme ortamı ve canlı sunucu için bilgileri buraya girin.
$host = 'localhost';
$dbname = 'hatayweb';      // Canlı ortam veritabanı adını buraya girin
$username = 'root';        // Canlı ortam kullanıcı adını buraya girin
$password = '';            // Canlı ortam veritabanı şifresini girin

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
