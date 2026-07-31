<?php
require_once '../autoload.php';
/**
 * Admin Logout
 * Secure session termination with logging
 */

Session::start();

// Log logout action before destroying session
if (Session::get('admin_logged_in') === true) {
    $db = Database::getInstance();
    $admin_id = Session::get('admin_id');
    $admin_username = Session::get('admin_username');
    $ip = SecurityHelper::getClientIP();

    try {
        $db->query(
            "INSERT INTO admin_logs (admin_id, admin_username, action, module, ip_address)
             VALUES (?, ?, 'logout', 'auth', ?)",
            [$admin_id, $admin_username, $ip]
        );

        Logger::info('Admin logout', [
            'username' => $admin_username,
            'ip' => $ip
        ]);
    } catch (Exception $e) {
        Logger::error('Failed to log admin logout', [
            'error' => $e->getMessage()
        ]);
    }
}

// Destroy session securely
Session::destroy();

// Redirect to login page
header("Location: login.php");
exit;
