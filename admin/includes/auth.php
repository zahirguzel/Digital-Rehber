<?php
/**
 * Admin Authentication & Authorization
 * Enterprise-level security with role-based access control
 */

require_once __DIR__ . '/../../autoload.php';

// Initialize secure session
Session::start();

// If the user has passed password check but not 2FA, keep them on the 2FA page
if (isset($_SESSION['pending_2fa_id']) && !isset($_SESSION['admin_logged_in'])) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile !== '2fa-verify.php') {
        header('Location: 2fa-verify.php');
        exit;
    }
}

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    Logger::security('Unauthorized admin access attempt', [
        'file' => $_SERVER['PHP_SELF'] ?? 'unknown',
        'ip' => SecurityHelper::getClientIP()
    ]);
    header("Location: login.php");
    exit;
}

// Load admin data from session
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

/**
 * Check if admin has required role
 * @param array $allowedRoles Array of allowed roles (e.g., ['superadmin', 'admin'])
 */
function requireRole($allowedRoles = []) {
    global $admin_role, $admin_username;

    if (empty($allowedRoles)) {
        return true; // No specific role required
    }

    if (!in_array($admin_role, $allowedRoles)) {
        Logger::security('Insufficient permissions', [
            'user' => $admin_username,
            'role' => $admin_role,
            'required' => implode(', ', $allowedRoles),
            'file' => $_SERVER['PHP_SELF'] ?? 'unknown'
        ]);

        http_response_code(403);
        die('Access Denied: Insufficient permissions for this action.');
    }

    return true;
}

/**
 * Log admin activity
 * @param string $action (create, update, delete, login, logout)
 * @param string $module (businesses, categories, ads, etc.)
 * @param string $targetName Optional name of affected item
 * @param int $targetId Optional ID of affected item
 */
function logAction($action, $module, $targetName = null, $targetId = null) {
    global $admin_id, $admin_username;

    try {
        $db = Database::getInstance();
        $ip = SecurityHelper::getClientIP();

        $db->query(
            "INSERT INTO admin_logs (admin_id, admin_username, action, module, target_name, target_id, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$admin_id, $admin_username, $action, $module, $targetName, $targetId, $ip]
        );

        // Also log to file for critical actions
        if (in_array($action, ['delete', 'login', 'logout'])) {
            Logger::info("Admin action: $action on $module", [
                'admin' => $admin_username,
                'target' => $targetName,
                'ip' => $ip
            ]);
        }
    } catch (Exception $e) {
        Logger::error('Failed to log admin activity', [
            'error' => $e->getMessage(),
            'action' => $action,
            'module' => $module
        ]);
    }
}

/**
 * Validate CSRF token (for POST requests)
 * Call this at the beginning of form submissions
 */
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRFMiddleware::validate()) {
            Logger::security('CSRF validation failed in admin panel', [
                'admin' => $_SESSION['admin_username'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);

            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

/**
 * Check if current admin is superadmin
 */
function isSuperAdmin() {
    global $admin_role;
    return $admin_role === 'superadmin';
}

/**
 * Check if current admin can edit/delete item
 * Editors can only edit, not delete
 */
function canDelete() {
    global $admin_role;
    return in_array($admin_role, ['superadmin', 'admin']);
}

/**
 * Check if current admin can manage admins
 */
function canManageAdmins() {
    return isSuperAdmin();
}

/**
 * Get admin info array
 */
function getAdminInfo() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'Unknown',
        'role' => $_SESSION['admin_role'] ?? 'admin'
    ];
}
