<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$service_id  = trim($input['service_id'] ?? '');
$call_status = trim($input['call_status'] ?? '');
$notes       = trim($input['notes'] ?? '');
$called_by   = getStaffName();

$allowed_statuses = [
    'Answered', 'No Answer', 'Busy', 'Wrong Number', 
    'Switched Off', 'Customer Requested Callback', 
    'Customer Busy', 'Customer Cancelled', 'Customer Will Visit Office'
];

if (empty($service_id) || empty($call_status)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID and Call Status are required']);
    exit;
}

if (!in_array($call_status, $allowed_statuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid call status selected']);
    exit;
}

try {
    // 1. Record call attempt
    $stmt = $conn->prepare("INSERT INTO call_attempts (service_id, called_by, call_status, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $service_id, $called_by, $call_status, $notes);
    $stmt->execute();
    $stmt->close();

    // 2. Update first_response_at if not set yet
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE services SET first_response_at = '$now' WHERE service_id = '$service_id' AND first_response_at IS NULL");
    $conn->query("UPDATE user_service_requests SET first_response_at = '$now' WHERE service_id = '$service_id' AND first_response_at IS NULL");

    // 3. Log timeline event
    logTimelineEvent($conn, $service_id, 'Call Attempt', $called_by, [
        'call_status' => $call_status,
        'notes' => $notes
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => "Call attempt ('{$call_status}') logged successfully."
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to log call attempt: ' . $e->getMessage()]);
}
?>
