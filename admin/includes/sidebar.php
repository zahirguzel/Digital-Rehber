<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Ensure we have a PDO connection
$_sidebarPdo = isset($pdo) ? $pdo : (class_exists('Database') ? Database::getInstance()->getPDO() : null);

// Fetch unread messages count safely
$unreadCount = 0;
$influencerPendingCount = 0;
$eventPendingCount = 0;

if ($_sidebarPdo) {
    try {
        $unreadCount = intval($_sidebarPdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn());
    } catch (Exception $e) {}
    
    if (!function_exists('getInfluencerPendingRequestsCount') && file_exists(__DIR__ . '/../../includes/influencer-helpers.php')) {
        require_once __DIR__ . '/../../includes/influencer-helpers.php';
    }
    if (function_exists('getInfluencerPendingRequestsCount')) {
        $influencerPendingCount = getInfluencerPendingRequestsCount($_sidebarPdo);
    }
    
    if (!function_exists('getEventPendingSubmissionsCount') && file_exists(__DIR__ . '/../../includes/event-helpers.php')) {
        require_once __DIR__ . '/../../includes/event-helpers.php';
    }
    if (function_exists('getEventPendingSubmissionsCount')) {
        $eventPendingCount = getEventPendingSubmissionsCount($_sidebarPdo);
    }
    
    // Business apps count
    $businessAppsPendingCount = 0;
    try {
        $businessAppsPendingCount = $_sidebarPdo->query("SELECT COUNT(*) FROM business_applications WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {}

    // Business profile edits count
    $businessProfileEditsPendingCount = 0;
    try {
        $businessProfileEditsPendingCount = $_sidebarPdo->query("SELECT COUNT(*) FROM business_pending_changes WHERE status = 'pending'")->fetchColumn();
    } catch (Exception $e) {}

    // Campaigns pending approval count
    $campaignsPendingCount = 0;
    try {
        $campaignsPendingCount = (int)$_sidebarPdo->query("SELECT COUNT(*) FROM campaigns WHERE is_published = 0 AND (status = 'pending' OR status IS NULL)")->fetchColumn();
    } catch (Exception $e) {}
}

// Fetch site name from settings
$_sidebarSiteName = 'Şehir Rehberi';
if ($_sidebarPdo) {
    try {
        $_row = $_sidebarPdo->query("SELECT site_title FROM settings LIMIT 1")->fetch();
        if ($_row && !empty($_row['site_title'])) $_sidebarSiteName = $_row['site_title'];
    } catch (Exception $e) {}
}

$menu = [
    [
        'title' => 'Genel Bakış',
        'icon' => 'fa-solid fa-chart-pie',
        'url' => 'index.php'
    ],
    [
        'title' => 'İşletme & Modüller',
        'icon' => 'fa-solid fa-layer-group',
        'id' => 'isletme',
        'items' => [
            ['title' => 'Esnaf Yönetimi', 'icon' => 'fa-solid fa-shop', 'url' => 'businesses.php'],
            ['title' => 'Influencer Yönetimi', 'icon' => 'fa-solid fa-star', 'url' => 'influencers.php'],
            ['title' => 'Etkinlik Yönetimi', 'icon' => 'fa-solid fa-calendar-days', 'url' => 'events.php'],
            ['title' => 'Kampanya Yönetimi', 'icon' => 'fa-solid fa-tags', 'url' => 'campaigns.php'],
            ['title' => 'Menü Yönetimi', 'icon' => 'fa-solid fa-utensils', 'url' => 'menu.php'],
            ['title' => 'QR Kod Sistemi', 'icon' => 'fa-solid fa-qrcode', 'url' => 'qrcodes.php'],
            ['title' => 'Yorum Yönetimi', 'icon' => 'fa-solid fa-star-half-stroke', 'url' => 'reviews.php'],
            ['title' => 'Kategori Yönetimi', 'icon' => 'fa-solid fa-tags', 'url' => 'categories.php'],
            ['title' => 'Bölge & İlçe Yönetimi', 'icon' => 'fa-solid fa-map-location-dot', 'url' => 'regions.php'],
        ]
    ],
    [
        'title' => 'İçerik Yönetimi',
        'icon' => 'fa-solid fa-pen-to-square',
        'id' => 'icerik',
        'items' => [
            ['title' => 'Kurumsal Sayfalar', 'icon' => 'fa-solid fa-file-lines', 'url' => 'pages.php'],
            ['title' => 'Blog Yazıları', 'icon' => 'fa-solid fa-newspaper', 'url' => 'blogs.php'],
            ['title' => 'İlçe Rehberleri', 'icon' => 'fa-solid fa-map-location-dot', 'url' => 'ilceler.php'],
            ['title' => 'Hizmet Yönetimi', 'icon' => 'fa-solid fa-handshake-angle', 'url' => 'services.php'],
            ['title' => 'Hero Slaytlar', 'icon' => 'fa-solid fa-images', 'url' => 'hero-slides.php'],
            ['title' => 'Reklam Yönetimi', 'icon' => 'fa-solid fa-rectangle-ad', 'url' => 'ads.php'],
        ]
    ],
    [
        'title' => 'Gelen Kutusu',
        'icon' => 'fa-solid fa-inbox',
        'id' => 'gelenkutusu',
        'items' => [
            ['title' => 'Profil Değişiklikleri', 'icon' => 'fa-solid fa-code-pull-request', 'url' => 'pending-changes.php', 'badge' => $businessProfileEditsPendingCount],
            ['title' => 'İşletme Başvuruları', 'icon' => 'fa-solid fa-briefcase', 'url' => 'business-applications.php', 'badge' => $businessAppsPendingCount],
            ['title' => 'Kampanya Talepleri', 'icon' => 'fa-solid fa-tags', 'url' => 'campaigns.php', 'badge' => $campaignsPendingCount],
            ['title' => 'Etkinlik Başvuruları', 'icon' => 'fa-solid fa-calendar-plus', 'url' => 'event-talepler.php', 'badge' => $eventPendingCount],
            ['title' => 'Influencer Talepleri', 'icon' => 'fa-solid fa-envelope-open-text', 'url' => 'influencer-talepler.php', 'badge' => $influencerPendingCount],
            ['title' => 'Gelen Mesajlar', 'icon' => 'fa-solid fa-envelope', 'url' => 'messages.php', 'badge' => $unreadCount],
        ]
    ],
    [
        'title' => 'SEO & Dış Servisler',
        'icon' => 'fa-solid fa-globe',
        'id' => 'seo',
        'items' => [
            ['title' => 'SEO Yönetimi', 'icon' => 'fa-solid fa-magnifying-glass', 'url' => 'seo.php'],
            ['title' => 'Nöbetçi Eczane', 'icon' => 'fa-solid fa-prescription-bottle-medical', 'url' => 'nobetci-eczane.php'],
        ]
    ],
    [
        'title' => 'Sistem',
        'icon' => 'fa-solid fa-server',
        'id' => 'sistem',
        'items' => [
            ['title' => 'Site Ayarları', 'icon' => 'fa-solid fa-sliders', 'url' => 'settings.php'],
            ['title' => 'Kullanıcılar & Üyeler', 'icon' => 'fa-solid fa-users', 'url' => 'users.php'],
            ['title' => 'Adminler', 'icon' => 'fa-solid fa-users-gear', 'url' => 'admins.php'],
            ['title' => 'İşlem Kayıtları', 'icon' => 'fa-solid fa-clock-rotate-left', 'url' => 'logs.php'],
            ['title' => 'Çöp Kutusu', 'icon' => 'fa-solid fa-trash-can', 'url' => 'cop-kutusu.php'],
            ['title' => '2FA Ayarı', 'icon' => 'fa-solid fa-shield-halved', 'url' => '2fa-setup.php'],
        ]
    ]
];
?>
<div class="sidebar">
    <!-- Brand Title -->
    <a href="index.php" class="sidebar-brand">
        <i class="fa-solid fa-map-location-dot"></i> <?= htmlspecialchars($_sidebarSiteName) ?>
    </a>
    
    <!-- Sidebar Links -->
    <ul class="sidebar-menu accordion" id="sidebarAccordion">
        <?php foreach ($menu as $index => $item): ?>
            <?php if (isset($item['items'])): 
                // Check if any sub-item is active
                $isActiveGroup = false;
                $totalBadge = 0;
                foreach ($item['items'] as $sub) {
                    if ($current_page === $sub['url']) $isActiveGroup = true;
                    if (isset($sub['badge']) && $sub['badge'] > 0) $totalBadge += $sub['badge'];
                }
                $collapseId = "collapse_" . $item['id'];
            ?>
                <li class="nav-item mb-1">
                    <a class="nav-link <?= $isActiveGroup ? '' : 'collapsed' ?> d-flex align-items-center justify-content-between" 
                       data-bs-toggle="collapse" 
                       href="#<?= $collapseId ?>" 
                       role="button" 
                       aria-expanded="<?= $isActiveGroup ? 'true' : 'false' ?>" 
                       aria-controls="<?= $collapseId ?>"
                       style="<?= $isActiveGroup ? 'background: rgba(255,255,255,0.1); color: #fff;' : '' ?>">
                        <span>
                            <i class="<?= $item['icon'] ?>" style="width: 20px; text-align:center; margin-right: 8px;"></i>
                            <?= $item['title'] ?>
                        </span>
                        <span>
                            <?php if ($totalBadge > 0): ?>
                                <span class="badge bg-danger rounded-pill px-2 me-2" style="font-size: 10px;"><?= $totalBadge ?></span>
                            <?php endif; ?>
                            <i class="fa-solid fa-chevron-down" style="font-size: 10px; transition: transform 0.3s; <?= $isActiveGroup ? 'transform: rotate(180deg);' : '' ?>"></i>
                        </span>
                    </a>
                    <div class="collapse <?= $isActiveGroup ? 'show' : '' ?>" id="<?= $collapseId ?>" data-bs-parent="#sidebarAccordion">
                        <ul class="list-unstyled" style="padding-left: 1.5rem; margin-top: 0.25rem;">
                            <?php foreach ($item['items'] as $sub): 
                                $isActive = $current_page === $sub['url'];
                            ?>
                            <li class="mb-1">
                                <a href="<?= $sub['url'] ?>" class="nav-link <?= $isActive ? 'active' : '' ?> d-flex align-items-center justify-content-between py-2" style="font-size: 0.9em; padding-left: 1rem; border-radius: 4px; <?= $isActive ? 'color: #fff; font-weight: 600;' : 'color: #cbd5e1;' ?>">
                                    <span><i class="<?= $sub['icon'] ?> me-2" style="opacity: 0.7; font-size: 0.9em;"></i> <?= $sub['title'] ?></span>
                                    <?php if (isset($sub['badge']) && $sub['badge'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-2" style="font-size: 10px;"><?= $sub['badge'] ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php else: 
                $isActive = $current_page === $item['url'];
            ?>
                <li class="nav-item mb-1">
                    <a href="<?= $item['url'] ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                        <i class="<?= $item['icon'] ?>" style="width: 20px; text-align:center; margin-right: 8px;"></i> <?= $item['title'] ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Logout -->
        <li class="nav-item mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
            <a href="logout.php" class="nav-link text-danger confirm-btn" data-confirm="Çıkış yapmak istediğinizden emin misiniz?" data-confirm-title="Çıkış Yap" data-confirm-btn="Evet, Çıkış Yap">
                <i class="fa-solid fa-right-from-bracket text-danger" style="width: 20px; text-align:center; margin-right: 8px;"></i> Güvenli Çıkış
            </a>
        </li>
    </ul>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer" style="margin-top: auto; padding: 15px; border-top: 1px solid rgba(255,255,255,0.06);">
        <div style="color: #94a3b8; font-size: 12px;"><?= htmlspecialchars($_sidebarSiteName) ?> v2.0</div>
        <div class="mt-1" style="color: #64748b; font-size: 11px;">Yönetim Paneli</div>
    </div>
</div>
<script>
// Rotate chevron on collapse toggle
document.addEventListener('DOMContentLoaded', function() {
    var collapses = document.querySelectorAll('.collapse');
    collapses.forEach(function(col) {
        col.addEventListener('show.bs.collapse', function () {
            var icon = this.previousElementSibling.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = 'rotate(180deg)';
            this.previousElementSibling.style.background = 'rgba(255,255,255,0.1)';
            this.previousElementSibling.style.color = '#fff';
        });
        col.addEventListener('hide.bs.collapse', function () {
            var icon = this.previousElementSibling.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = 'rotate(0deg)';
            this.previousElementSibling.style.background = 'transparent';
            this.previousElementSibling.style.color = '';
        });
    });
});
</script>