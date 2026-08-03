<?php
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'İşletme Paneli';
$bizName = $_SESSION['biz_name'] ?? 'İşletme';
$bizSlug = $_SESSION['biz_slug'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — İşletme Paneli</title>
    <?= CSRFMiddleware::meta() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bs-primary: #E0533C; 
            --bs-primary-rgb: 224, 83, 60;
            --bs-success: #10B981; 
            --navy: #1E293B;
            --sidebar-width: 250px;
        }
        body { font-family: 'Outfit', sans-serif; background-color: #F8FAFC; color: #334155; }
        
        /* Sidebar Styling */
        .biz-sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0; bottom: 0; left: 0;
            background: #fff;
            border-right: 1px solid #E2E8F0;
            z-index: 1000;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy);
            text-decoration: none;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
        }
        .sidebar-brand i { color: var(--bs-primary); font-size: 1.5rem; margin-right: 10px; }
        
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; padding-top: 1rem; }
        .sidebar-menu li { padding: 0.25rem 1rem; }
        .sidebar-menu .nav-link {
            display: flex; align-items: center; gap: 12px;
            color: #64748B; font-weight: 500; padding: 0.75rem 1rem;
            border-radius: 8px; text-decoration: none; transition: all 0.2s;
        }
        .sidebar-menu .nav-link:hover { background: #F1F5F9; color: var(--navy); }
        .sidebar-menu .nav-link.active { background: rgba(224, 83, 60, 0.1); color: var(--bs-primary); font-weight: 600; }
        .sidebar-menu .nav-link.active i { color: var(--bs-primary); }
        
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid #E2E8F0; font-size: 0.85rem; color: #94A3B8; }
        
        /* Main Content */
        .biz-main {
            margin-left: var(--sidebar-width);
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .biz-header {
            background: #fff;
            height: 70px;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #E2E8F0;
            position: sticky; top: 0; z-index: 999;
        }
        
        .biz-content { padding: 2rem; flex-grow: 1; }
        
        @media (max-width: 991px) {
            .biz-sidebar { transform: translateX(-100%); }
            .biz-main { margin-left: 0; }
            .sidebar-open .biz-sidebar { transform: translateX(0); }
        }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card-header { background: #fff; border-bottom: 1px solid #F1F5F9; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0 !important; }
        
        .btn-primary { background: var(--bs-primary); border-color: var(--bs-primary); }
        .btn-primary:hover { background: #c84630; border-color: #c84630; }

        /* Business Panel Stat Cards */
        .biz-panel-stat-card {
            background: #fff;
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .biz-panel-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06);
        }
        .biz-panel-stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .biz-panel-stat-value {
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--navy);
            line-height: 1.2;
            margin-bottom: 2px;
        }
        .biz-panel-stat-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: #64748B;
        }

        /* Responsive Table & Layout Improvements */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            border-radius: 16px;
        }
        .table th {
            white-space: nowrap !important;
            font-weight: 600 !important;
            color: #475569 !important;
            background-color: #F8FAFC !important;
            padding: 1rem !important;
            border-bottom: 1px solid #E2E8F0 !important;
        }
        .table td {
            padding: 1rem !important;
            vertical-align: middle !important;
        }

        @media (max-width: 767.98px) {
            .biz-content {
                padding: 1rem !important;
            }
            .biz-header {
                padding: 0 1rem !important;
            }
            .biz-panel-stat-card {
                padding: 1rem 1.25rem !important;
                gap: 1rem !important;
            }
            .biz-panel-stat-icon {
                width: 44px !important;
                height: 44px !important;
                font-size: 1.25rem !important;
                border-radius: 12px !important;
            }
            .biz-panel-stat-value {
                font-size: 1.35rem !important;
            }
            .biz-panel-stat-label {
                font-size: 0.82rem !important;
            }
            .table th, .table td {
                padding: 0.75rem !important;
                font-size: 0.88rem !important;
            }
            .table td .btn {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.78rem !important;
            }
        }
    </style>
</head>
<body>
    
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="biz-main">
    <header class="biz-header">
        <div class="d-flex align-items-center">
            <button class="btn btn-light d-lg-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h4 class="mb-0 fw-bold d-none d-md-block text-navy"><?= htmlspecialchars($pageTitle) ?></h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php
            $unreadNotifCount = 0;
            $headerBizId = (int)($_SESSION['biz_id'] ?? ($bizId ?? 0));
            if ($headerBizId > 0) {
                try {
                    $pdo = Database::getInstance()->getPDO();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_notifications WHERE business_id = ? AND is_read = 0");
                    $stmt->execute([$headerBizId]);
                    $unreadNotifCount = $stmt->fetchColumn();
                } catch (Exception $e) {}
            }
            ?>
            <a href="notifications.php" class="btn btn-light rounded-circle position-relative p-2 d-flex align-items-center justify-content-center text-decoration-none" style="width: 38px; height: 38px; border: 1px solid #E2E8F0;" title="Bildirimler">
                <i class="fa-regular fa-bell text-navy"></i>
                <?php if ($unreadNotifCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                        <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="../esnaf/<?= htmlspecialchars($bizSlug) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fa-solid fa-eye me-1"></i> Profili Gör
            </a>
            <div class="dropdown">
                <a href="#" class="text-decoration-none text-navy fw-semibold dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; font-weight:700;">
                        <?= mb_substr($bizName, 0, 1) ?>
                    </div>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($bizName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm mt-2">
                    <li><a class="dropdown-item py-2" href="profile.php"><i class="fa-solid fa-store fa-fw me-2 text-muted"></i> Profilimi Düzenle</a></li>
                    <li><a class="dropdown-item py-2" href="settings.php"><i class="fa-solid fa-lock fa-fw me-2 text-muted"></i> Şifre Değiştir</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i> Güvenli Çıkış</a></li>
                </ul>
            </div>
        </div>
    </header>
    <main class="biz-content">