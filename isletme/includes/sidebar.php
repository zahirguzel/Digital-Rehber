<?php
$currentPage = basename($_SERVER['PHP_SELF']);

function navItem($title, $icon, $link, $current) {
    $active = $current === $link ? 'active' : '';
    return "<li><a href=\"{$link}\" class=\"nav-link {$active}\"><i class=\"{$icon} fa-fw\"></i> {$title}</a></li>";
}
?>
<aside class="biz-sidebar">
    <a href="index.php" class="sidebar-brand">
        <i class="fa-solid fa-store"></i>
        <span>İşletme Paneli</span>
    </a>
    <ul class="sidebar-menu">
        <?= navItem('Genel Bakış', 'fa-solid fa-chart-pie', 'index.php', $currentPage) ?>
        <li class="pt-3 pb-1 px-3 text-uppercase text-muted" style="font-size: 11px; font-weight: 600; letter-spacing: 0.5px;">Yönetim</li>
        <?= navItem('Profil Ayarları', 'fa-solid fa-id-card', 'profile.php', $currentPage) ?>
        <?= navItem('Fotoğraf Galerisi', 'fa-solid fa-images', 'gallery.php', $currentPage) ?>
        <?= navItem('Yorumlar', 'fa-solid fa-comments', 'reviews.php', $currentPage) ?>
        <?= navItem('Kampanyalarım', 'fa-solid fa-tags', 'campaigns.php', $currentPage) ?>
        <?php
        $menuPages = ['menu.php', 'menu-kategoriler.php', 'menu-urunler.php'];
        $isMenuOpen = in_array($currentPage, $menuPages);
        ?>
        <li class="sidebar-dropdown">
            <a href="javascript:void(0);" class="nav-link d-flex justify-content-between align-items-center <?= $isMenuOpen ? 'active' : '' ?>" onclick="var sm=document.getElementById('sideSubMenu'); sm.style.display=(sm.style.display==='none'?'block':'none');">
                <span><i class="fa-solid fa-utensils fa-fw"></i> Dijital Menü</span>
                <i class="fa-solid fa-chevron-down" style="font-size:11px;"></i>
            </a>
            <ul id="sideSubMenu" style="display: <?= $isMenuOpen ? 'block' : 'none' ?>; list-style: none; padding-left: 1.5rem; margin-top: 4px; margin-bottom: 4px;">
                <li class="mb-1">
                    <a href="menu-kategoriler.php" class="nav-link py-1 <?= $currentPage === 'menu-kategoriler.php' ? 'active' : '' ?>" style="font-size: 13.5px;">
                        <i class="fa-solid fa-layer-group fa-fw"></i> Kategori Yönetimi
                    </a>
                </li>
                <li>
                    <a href="menu-urunler.php" class="nav-link py-1 <?= ($currentPage === 'menu-urunler.php' || $currentPage === 'menu.php') ? 'active' : '' ?>" style="font-size: 13.5px;">
                        <i class="fa-solid fa-burger fa-fw"></i> Ürün Yönetimi
                    </a>
                </li>
            </ul>
        </li>
        <?= navItem('QR Kodum', 'fa-solid fa-qrcode', 'qr.php', $currentPage) ?>
        
        <li class="pt-3 pb-1 px-3 text-uppercase text-muted" style="font-size: 11px; font-weight: 600; letter-spacing: 0.5px;">Hesap</li>
        <?= navItem('Güvenlik', 'fa-solid fa-shield-halved', 'settings.php', $currentPage) ?>
    </ul>
    <div class="sidebar-footer">
        <div><strong><?= htmlspecialchars($_SESSION['biz_name'] ?? 'İşletme') ?></strong></div>
        <div class="mt-1"><a href="logout.php" class="text-danger text-decoration-none"><i class="fa-solid fa-right-from-bracket"></i> Çıkış Yap</a></div>
    </div>
</aside>