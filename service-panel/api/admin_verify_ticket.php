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
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can verify or close tickets']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$service_id          = trim($input['service_id'] ?? '');
$action              = trim($input['action'] ?? 'Approve'); // 'Approve', 'Return', 'Close'
$remarks             = trim($input['remarks'] ?? '');
$billing_status      = trim($input['billing_status'] ?? 'Payment Received');
$invoice_number      = trim($input['invoice_number'] ?? '');
$payment_mode        = trim($input['payment_mode'] ?? 'Cash');
$service_val_rupees  = isset($input['service_value_rupees']) ? (float)$input['service_value_rupees'] : null;
$sales_val_rupees    = isset($input['sales_value_rupees']) ? (float)$input['sales_value_rupees'] : null;
$adminName           = getStaffName();

if (empty($service_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

try {
    // 1. Identify target table
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

    if ($action === 'Return') {
        if (empty($remarks)) {
            echo json_encode(['status' => 'error', 'message' => 'Please state the reason for returning the ticket to Engineer']);
            exit;
        }

        $upd = $conn->prepare("UPDATE `$tableTarget` SET status = 'Repair in Progress', engineer_submitted = 0 WHERE service_id = ?");
        $upd->bind_param("s", $service_id);
        $upd->execute();
        $upd->close();

        logTimelineEvent($conn, $service_id, 'Returned to Engineer', $adminName, ['remarks' => $remarks]);

        echo json_encode(['status' => 'success', 'message' => 'Ticket returned to Engineer for revisions.']);
        exit;
    }

    // Prepare internal revenue updates if passed
    $revFields = "";
    if ($service_val_rupees !== null) {
        $svcInternal = encodeServiceValue($service_val_rupees);
        $revFields .= ", service_value_internal = $svcInternal";
    }
    if ($sales_val_rupees !== null) {
        $salesInternal = encodeSalesValue($sales_val_rupees);
        $revFields .= ", sales_value_internal = $salesInternal";
    }

    if ($action === 'Approve') {
        $newStatus = 'Ready for Pickup';
        $updSql = "UPDATE `$tableTarget` SET status = '$newStatus', verified_by_admin = ?, admin_approved_at = '$now' $revFields WHERE service_id = ?";
        $upd = $conn->prepare($updSql);
        $upd->bind_param("ss", $adminName, $service_id);
        $upd->execute();
        $upd->close();

        logTimelineEvent($conn, $service_id, 'Admin Approved', $adminName, ['remarks' => $remarks]);

        echo json_encode(['status' => 'success', 'message' => 'Ticket successfully verified and approved by Admin.']);
        exit;
    }

    if ($action === 'Close') {
        $newStatus = ($tableTarget === 'services') ? 'Delivered' : 'Closed';
        $todayDate = date('Y-m-d');
        
        if ($tableTarget === 'services') {
            $updSql = "UPDATE services SET status = '$newStatus', date_completed = '$todayDate', closed_at = '$now', billing_completed_at = '$now', billing_verified_by = ?, billing_status = ?, invoice_number = ?, payment_mode = ? $revFields WHERE service_id = ?";
            $upd = $conn->prepare($updSql);
            $upd->bind_param("sssss", $adminName, $billing_status, $invoice_number, $payment_mode, $service_id);
            $upd->execute();
            $upd->close();
        } else {
            $updSql = "UPDATE user_service_requests SET status = '$newStatus', closed_at = '$now', billing_completed_at = '$now', billing_verified_by = ?, billing_status = ?, invoice_number = ?, payment_mode = ? $revFields WHERE service_id = ?";
            $upd = $conn->prepare($updSql);
            $upd->bind_param("sssss", $adminName, $billing_status, $invoice_number, $payment_mode, $service_id);
            $upd->execute();
            $upd->close();
        }

        // Insert log
        if ($tableTarget === 'services') {
            $pkRes = $conn->query("SELECT id FROM services WHERE service_id = '$service_id'");
            if ($pkRow = $pkRes->fetch_assoc()) {
                $pkId = $pkRow['id'];
                $logStmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, ?, ?)");
                $logRemarks = "Ticket verified and Closed by Admin/Accounts ($adminName). Billing status: $billing_status";
                $logStmt->bind_param("iss", $pkId, $newStatus, $logRemarks);
                $logStmt->execute();
                $logStmt->close();
            }
        }

        logTimelineEvent($conn, $service_id, 'Closed', $adminName, [
            'billing_status' => $billing_status,
            'invoice_number' => $invoice_number,
            'payment_mode'   => $payment_mode,
            'remarks'        => $remarks
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Ticket closed and billing verified successfully!']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Verification failed: ' . $e->getMessage()]);
}
?>
