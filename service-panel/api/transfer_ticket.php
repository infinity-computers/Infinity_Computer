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

$service_id      = trim($input['service_id'] ?? '');
$new_engineer    = trim($input['new_engineer'] ?? '');
$transfer_reason = trim($input['transfer_reason'] ?? '');
$remarks         = trim($input['remarks'] ?? '');
$transferred_by  = getStaffName();

$allowed_reasons = [
    'Leave', 'Emergency', 'Wrong Assignment', 
    'Skill Issue', 'Customer Requested Another Engineer', 'Area Change'
];

if (empty($service_id) || empty($new_engineer) || empty($transfer_reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID, New Engineer, and Transfer Reason are required']);
    exit;
}

if (!in_array($transfer_reason, $allowed_reasons)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid transfer reason selected']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');

    // 1. Update ticket table assigned engineer
    $conn->query("UPDATE services SET assigned_engineer = '$new_engineer', assigned_at = '$now' WHERE service_id = '$service_id'");
    $conn->query("UPDATE user_service_requests SET assigned_engineer = '$new_engineer' WHERE service_id = '$service_id'");

    // 2. Update existing active assignment status
    $conn->query("UPDATE ticket_engineer_assignments SET status = 'Transferred' WHERE service_id = '$service_id' AND assignment_type = 'Primary' AND status = 'Assigned'");

    // 3. Add new assignment
    $stmt = $conn->prepare("INSERT INTO ticket_engineer_assignments (service_id, engineer_name, assignment_type, assigned_by) VALUES (?, ?, 'Primary', ?)");
    $stmt->bind_param("sss", $service_id, $new_engineer, $transferred_by);
    $stmt->execute();
    $stmt->close();

    // 4. Log timeline event
    logTimelineEvent($conn, $service_id, 'Ticket Transferred', $transferred_by, [
        'new_engineer' => $new_engineer,
        'reason'       => $transfer_reason,
        'remarks'      => $remarks
    ]);

    echo json_encode(['status' => 'success', 'message' => "Ticket transferred to {$new_engineer} successfully"]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to transfer ticket: ' . $e->getMessage()]);
}
?>
