<?php
// admin/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
require_once __DIR__ . '/../config/db.php';

// Authorized Admin Emails (Comma-separated)
define('ADMIN_EMAILS', 'pacifier2204@gmail.com,icc@infinitycomputer.in,omshah1628@gmail.com,rathorjatin70@gmail.com');

// OTP Security Settings
define('OTP_EXPIRY_SECONDS', 30);
define('MAX_RESEND_ATTEMPTS', 3);

/**
 * Check if admin is logged in
 */
function checkAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: admin_login.php');
        exit;
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

?>

