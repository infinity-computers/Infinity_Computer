<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can change engineer status']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$name   = trim($input['name'] ?? '');
$status = trim($input['status'] ?? '');

$allowed_statuses = ['Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty'];

if (empty($name) || empty($status)) {
    echo json_encode(['status' => 'error', 'message' => 'Engineer name and Status are required']);
    exit;
}

if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status selected']);
    exit;
}

try {
    // Verify engineer exists
    $stmt = $conn->prepare("SELECT id FROM engineers WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $eng = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$eng) {
        echo json_encode(['status' => 'error', 'message' => 'Engineer not found']);
        exit;
    }

    // Update status
    $upd = $conn->prepare("UPDATE engineers SET status = ?, last_activity_at = NOW() WHERE name = ?");
    $upd->bind_param("ss", $status, $name);
    $upd->execute();
    $upd->close();

    echo json_encode([
        'status' => 'success',
        'message' => "Status for engineer '{$name}' updated to '{$status}' successfully."
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status: ' . $e->getMessage()]);
}
?>
