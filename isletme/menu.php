<?php
// Legacy menu.php router -> redirects to separated Kategori / Ürün sayfaları
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
if (isset($_GET['edit_cat']) || isset($_GET['del_cat'])) {
    header('Location: menu-kategoriler.php' . $qs);
} else {
    header('Location: menu-urunler.php' . $qs);
}
exit;
