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
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can close tickets']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$service_id = trim($input['service_id'] ?? '');
$remarks    = trim($input['remarks'] ?? '');
$adminName  = getStaffName();

if (empty($service_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

try {
    // 1. Find the ticket and check its table
    $stmt = $conn->prepare("SELECT id, service_id, assigned_engineer, status FROM services WHERE service_id = ?");
    $stmt->bind_param("s", $service_id);
    $stmt->execute();
    $svc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $tableTarget = 'services';
    if (!$svc) {
        $stmt = $conn->prepare("SELECT id, service_id, assigned_engineer, status FROM user_service_requests WHERE service_id = ?");
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
    $assigned_engineer = $svc['assigned_engineer'];

    // 2. Update ticket status to 'Closed' and record timestamps
    $upd = $conn->prepare("UPDATE `$tableTarget` SET status = 'Closed', closed_at = ?, billing_completed_at = ? WHERE service_id = ?");
    $upd->bind_param("sss", $now, $now, $service_id);
    $upd->execute();
    $upd->close();

    // 3. Log status change (for services)
    if ($tableTarget === 'services') {
        $logStmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, 'Closed', ?)");
        $logRemarks = "Ticket closed by Admin ($adminName). Notes: " . ($remarks ?: 'None');
        $logStmt->bind_param("is", $svc['id'], $logRemarks);
        $logStmt->execute();
        $logStmt->close();
    }

    // 4. Update engineer status if assigned
    if (!empty($assigned_engineer)) {
        $engStmt = $conn->prepare("UPDATE engineers SET status = 'Active', current_ticket = NULL WHERE name = ?");
        $engStmt->bind_param("s", $assigned_engineer);
        $engStmt->execute();
        $engStmt->close();
    }

    // 5. Log timeline event
    logTimelineEvent($conn, $service_id, 'Ticket Closed', $adminName, [
        'remarks' => $remarks,
        'closed_at' => $now
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Ticket closed and engineer set to Active successfully!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to close ticket: ' . $e->getMessage()]);
}
?>
