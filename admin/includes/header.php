<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= CSRFMiddleware::meta() ?>
    <title><?= isset($pageTitle) ? SecurityHelper::escape($pageTitle) . ' - Yönetim Paneli' : 'Yönetim Paneli' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Favicon -->
    <?php $adminFavicon = !empty($siteSettings['site_logo']) ? '../public/images/' . $siteSettings['site_logo'] : '../public/images/default_favicon.png'; ?>
    <link rel="shortcut icon" href="<?= htmlspecialchars($adminFavicon) ?>" type="image/png">
    <!-- FontAwesome & Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                const button = e.target.closest('.confirm-btn');
                if (button) {
                    e.preventDefault();
                    const href = button.getAttribute('href');
                    const message = button.getAttribute('data-confirm') || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?';
                    const confirmButtonText = button.getAttribute('data-confirm-btn') || 'Evet, Devam Et';
                    const title = button.getAttribute('data-confirm-title') || 'Emin misiniz?';
                    const isDanger = button.classList.contains('btn-outline-danger') || button.classList.contains('text-danger') || button.classList.contains('btn-danger');
                    
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: isDanger ? 'warning' : 'question',
                        showCancelButton: true,
                        confirmButtonColor: isDanger ? '#E0533C' : '#0f172a',
                        cancelButtonColor: '#cbd5e1',
                        confirmButtonText: confirmButtonText,
                        cancelButtonText: 'İptal',
                        customClass: {
                            confirmButton: 'btn btn-primary px-4 py-2 me-2',
                            cancelButton: 'btn btn-outline-secondary px-4 py-2'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                }
            });
        });
    </script>
    
    <?php
    // Fetch admin panel primary color from settings
    $_adminColor = '#E0533C';
    if (isset($pdo)) {
        try {
            $_cs = $pdo->query("SELECT admin_primary_color FROM settings WHERE id=1 LIMIT 1")->fetch();
            if ($_cs && !empty($_cs['admin_primary_color'])) $_adminColor = $_cs['admin_primary_color'];
        } catch (Exception $e) {}
    }
    // Darken for hover (simple: append a darker shade via filter)
    ?>
    <style>
        :root {
            --primary: <?= htmlspecialchars($_adminColor) ?>;
            --primary-hover: <?= htmlspecialchars($_adminColor) ?>;
            --navy: #0f172a;
            --navy-light: #1e293b;
            --radius: 4px;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc; /* Premium off-white/light gray background */
            color: #1e293b;
            overflow-x: hidden;
        }
        
        /* Layout Grid */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background-color: var(--navy);
            color: #ffffff;
            flex-shrink: 0;
            transition: all 0.3s ease;
            box-shadow: 1px 0 0 0 #334155; /* Modern border-like separator instead of heavy shadow */
            display: flex;
            flex-direction: column;
            border-right: 4px solid var(--primary);
        }
        
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        /* Sidebar Navigation styling */
        .sidebar-brand {
            padding: 24px;
            font-size: 20px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-brand span {
            color: var(--primary);
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
            overflow-x: hidden;
            flex-grow: 1;
        }
        
        .sidebar-menu .nav-link {
            color: #94a3b8; /* Soft slate gray for unselected state */
            padding: 14px 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
        }
        
        .sidebar-menu .nav-link i {
            font-size: 16px;
            opacity: 0.8;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-menu .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.02);
            border-left-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-menu .nav-link.active {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.03);
            border-left-color: var(--primary);
            font-weight: 600;
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px;
            color: #64748b;
        }
        
        /* Top Navigation Bar */
        .top-navbar {
            background-color: #ffffff;
            height: 70px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            padding: 0 30px;
            justify-content: space-between;
        }
        
        /* Content area adjustments */
        .content-body {
            padding: 30px;
            flex-grow: 1;
        }
        
        /* Unified UI elements */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: var(--radius) !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05) !important;
            background-color: #ffffff;
        }
        
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 700;
            padding: 18px 24px;
            color: #0f172a;
        }

        .dashboard-inbox-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .dashboard-inbox-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08) !important;
        }

        .dashboard-inbox-card--alert-messages {
            background: linear-gradient(135deg, #ffffff 0%, #ecfdf5 100%);
        }

        .dashboard-inbox-card--alert-influencer {
            background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
        }

        .dashboard-inbox-card--alert-event {
            background: linear-gradient(135deg, #ffffff 0%, #ecfeff 100%);
        }

        .dashboard-inbox-item--message {
            background-color: #ecfdf5;
            border-left: 3px solid #10b981;
        }

        .dashboard-inbox-item--influencer {
            background-color: #fffbeb;
            border-left: 3px solid #f59e0b;
        }

        .dashboard-inbox-item--event {
            background-color: #ecfeff;
            border-left: 3px solid #0ea5e9;
        }

        .list-group-item.dashboard-inbox-item--message,
        .list-group-item.dashboard-inbox-item--influencer,
        .list-group-item.dashboard-inbox-item--event {
            border-top-left-radius: 0;
        }
        
        .btn {
            border-radius: var(--radius) !important;
            font-weight: 600;
            padding: 8px 18px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .btn-outline-secondary {
            border-color: #cbd5e1;
            color: #475569;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }
        
        .form-control, .form-select, .form-control-color {
            border-radius: var(--radius) !important;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            background-color: #ffffff;
            color: #0f172a;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(224, 83, 60, 0.08);
            background-color: #ffffff;
        }
        
        .table-responsive {
            border-radius: var(--radius);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .table td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        
        /* Badges status with premium transparent borders */
        .badge-configured {
            background-color: rgba(34, 197, 94, 0.08);
            color: #15803d;
            border: 1px solid rgba(34, 197, 94, 0.2);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .badge-empty {
            background-color: rgba(148, 163, 184, 0.08);
            color: #475569;
            border: 1px solid rgba(148, 163, 184, 0.15);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.4);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        /* Responsive tweaks */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -260px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
            }
            .sidebar.show {
                left: 0;
            }
            /* Menü başlığında boşluk için ufak ayar */
            .top-navbar .page-title-wrap {
                margin-left: 10px;
            }
        }
    </style>
</head>
<body>

    <div class="admin-wrapper">
        
        <!-- Mobile Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="document.querySelector('.sidebar').classList.remove('show'); this.classList.remove('show'); document.body.style.overflow = '';"></div>
        
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content Area -->
        <div class="main-content">
            
            <!-- Top Navbar Header -->
            <div class="top-navbar">
                <div class="d-flex align-items-center">
                    <!-- Mobile Hamburger -->
                    <button class="btn btn-outline-secondary d-md-none border-0 px-2 py-1 me-2" id="mobileSidebarToggle" onclick="document.querySelector('.sidebar').classList.toggle('show'); document.getElementById('sidebarOverlay').classList.toggle('show'); document.body.style.overflow = document.querySelector('.sidebar').classList.contains('show') ? 'hidden' : '';">
                        <i class="fa-solid fa-bars fs-4"></i>
                    </button>
                    <div class="page-title-wrap">
                        <h4 class="h5 mb-0 fw-bold text-navy"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Yönetim Paneli' ?></h4>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small d-none d-sm-inline"><i class="fa-solid fa-calendar me-1"></i> <?= date('d.m.Y') ?></span>
                    <a href="../" class="btn btn-outline-secondary btn-sm px-3" target="_blank"><i class="fa-solid fa-globe me-1"></i> Siteyi Aç</a>
                </div>
            </div>
            
            <!-- Content Wrapper -->
            <div class="content-body">
