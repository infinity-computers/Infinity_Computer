<?php
session_start();
header('Content-Type: application/json');

// Ensure staff is logged in
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Check if current user is admin
$currentUserEmail = $_SESSION['staff_email'];
$adminCheckQuery = $conn->prepare("SELECT role FROM staff_users WHERE email = ?");
$adminCheckQuery->bind_param("s", $currentUserEmail);
$adminCheckQuery->execute();
$adminResult = $adminCheckQuery->get_result();
$adminData = $adminResult->fetch_assoc();

if (!$adminData || $adminData['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Admins only.']);
    exit;
}

// Fetch all staff users
$query = "SELECT id, name, email, role, created_at FROM staff_users ORDER BY id ASC";
$result = $conn->query($query);

$staffList = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

echo json_encode(['status' => 'success', 'data' => $staffList]);
?>
