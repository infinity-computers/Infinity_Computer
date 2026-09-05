<?php
/**
 * Auth Guard
 * Protects pages by requiring a valid staff session.
 * Manually included at the top of protected .php files.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionTimeout = 60 * 60; // 1 hour

// Check if authenticated using the new variable name
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    if (strpos($_SERVER['PHP_SELF'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['staff_login_time']) && (time() - $_SESSION['staff_login_time']) > $sessionTimeout) {
    session_unset();
    session_destroy();
    if (strpos($_SERVER['PHP_SELF'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
        exit;
    }
    header('Location: login.php?expired=1');
    exit;
}

// Update last activity
$_SESSION['staff_last_activity'] = time();

// Inject client-side auto-redirect timer for session expiry cleanly at end of output (Pages only, not APIs)
if (strpos($_SERVER['PHP_SELF'], '/api/') === false) {
    register_shutdown_function(function() use ($sessionTimeout) {
        if (isset($_SESSION['staff_login_time'])) {
            $remainingMs = ($sessionTimeout - (time() - $_SESSION['staff_login_time'])) * 1000;
            if ($remainingMs > 0) {
                echo "<script>setTimeout(function(){window.location.href='login.php?expired=1';}," . $remainingMs . ");</script>\n";
            }
        }
    });
}

if (!function_exists('getStaffRole')) {
    function getStaffRole() {
        $email = strtolower($_SESSION['staff_email'] ?? '');
        if ($email === 'icc@infinitycomputer.in') return 'Super Admin';
        if ($email === 'suraj@staff.infinitycomputer.in') return 'Admin/Accounts';
        return $_SESSION['staff_role'] ?? 'Engineer';
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() {
        return getStaffRole() === 'Super Admin';
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        $role = getStaffRole();
        return $role === 'Super Admin' || $role === 'Admin/Accounts';
    }
}

if (!function_exists('isEngineer')) {
    function isEngineer() {
        return getStaffRole() === 'Engineer';
    }
}

if (!function_exists('getStaffName')) {
    function getStaffName() {
        if (!empty($_SESSION['staff_name']) && $_SESSION['staff_name'] !== 'Staff Member') {
            return $_SESSION['staff_name'];
        }
        $email = $_SESSION['staff_email'] ?? 'Staff';
        $parts = explode('@', $email);
        return ucfirst($parts[0]);
    }
}
?>
