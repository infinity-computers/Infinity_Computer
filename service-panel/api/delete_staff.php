<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

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

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid staff ID.']);
    exit;
}

// Prevent admins from deleting themselves
$selfCheckStmt = $conn->prepare("SELECT email FROM staff_users WHERE id = ?");
$selfCheckStmt->bind_param("i", $id);
$selfCheckStmt->execute();
$selfResult = $selfCheckStmt->get_result();
$targetUser = $selfResult->fetch_assoc();

if ($targetUser && $targetUser['email'] === $currentUserEmail) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot delete yourself.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM staff_users WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Staff deleted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete staff.']);
}
?>
