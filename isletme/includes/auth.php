<?php
/**
 * Business Panel Authentication & Authorization
 */

require_once __DIR__ . '/../../autoload.php';

// Initialize secure session
Session::start();

// Check if the business user is logged in
if (!Session::get('biz_logged_in') || Session::get('biz_logged_in') !== true) {
    Logger::security('Unauthorized business panel access attempt', [
        'file' => $_SERVER['PHP_SELF'] ?? 'unknown',
        'ip' => SecurityHelper::getClientIP()
    ]);
    header("Location: login.php");
    exit;
}

// Force password change check
$currentPage = basename($_SERVER['PHP_SELF']);
if (Session::get('force_password') && $currentPage !== 'force-password.php' && $currentPage !== 'logout.php') {
    header("Location: force-password.php");
    exit;
}

/**
 * Validate CSRF token (for POST requests)
 * Call this at the beginning of form submissions
 */
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRFMiddleware::validate()) {
            Logger::security('CSRF validation failed in business panel', [
                'business_user' => Session::get('biz_username') ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);

            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}