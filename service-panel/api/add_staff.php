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
$name = trim($input['name'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$role = isset($input['role']) && $input['role'] === 'admin' ? 'admin' : 'staff';

if (empty($name) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO staff_users (name, email, role) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

$stmt->bind_param("sss", $name, $email, $role);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Staff added successfully.']);
} else {
    if ($conn->errno === 1062) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add staff.']);
    }
}
?>
