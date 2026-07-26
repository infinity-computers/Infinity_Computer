<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can manage billing']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$service_id   = trim($input['service_id'] ?? '');
$payment_mode = trim($input['payment_mode'] ?? 'UPI');
$adminName    = getStaffName();

if (empty($service_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

try {
    // Determine target table
    $stmt = $conn->prepare("SELECT service_id, status FROM services WHERE service_id = ?");
    $stmt->bind_param("s", $service_id);
    $stmt->execute();
    $svc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $tableTarget = 'services';
    if (!$svc) {
        $stmt = $conn->prepare("SELECT service_id, status FROM user_service_requests WHERE service_id = ?");
        $stmt->bind_param("s", $service_id);
        $stmt->execute();
        $svc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $tableTarget = 'user_service_requests';
    }

    if (!$svc) {
        echo json_encode(['status' => 'error', 'message' => 'Service ticket not found']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    // Update billing status to 'Payment Received'
    $updSql = "UPDATE `$tableTarget` SET billing_status = 'Payment Received', payment_mode = ?, billing_completed_at = ?, billing_verified_by = ? WHERE service_id = ?";
    $upd = $conn->prepare($updSql);
    $upd->bind_param("ssss", $payment_mode, $now, $adminName, $service_id);
    $upd->execute();
    $upd->close();

    // Log timeline event
    logTimelineEvent($conn, $service_id, 'Payment Received', $adminName, [
        'payment_mode' => $payment_mode,
        'billing_completed_at' => $now
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Payment marked as received successfully!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark payment: ' . $e->getMessage()]);
}
?>
