<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

$role = getStaffRole();
$is_engineer = isEngineer();
$staff_name = getStaffName();
$staff_email = $_SESSION['staff_email'] ?? '';

try {
    // Helper function to build conditions
    function getQueryCondition($is_engineer, $base_cond) {
        if ($is_engineer) {
            return $base_cond . " AND (LOWER(TRIM(assigned_engineer)) = LOWER(TRIM(?)) OR LOWER(TRIM(assigned_engineer)) = LOWER(TRIM(?)))";
        }
        return $base_cond;
    }

    // 1. New Calls
    if ($is_engineer) {
        $new_calls = 0;
    } else {
        $q = "SELECT COUNT(*) as count FROM services WHERE status = 'Pending' AND (assigned_engineer IS NULL OR assigned_engineer = '')";
        $res = $conn->query($q);
        $new_calls = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 2. Assigned Calls
    $q_assigned = getQueryCondition($is_engineer, "SELECT COUNT(*) as count FROM services WHERE status = 'Assigned'");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_assigned);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $assigned = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $res = $conn->query($q_assigned);
        $assigned = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 3. In Progress
    $q_inprogress = getQueryCondition($is_engineer, "SELECT COUNT(*) as count FROM services WHERE status IN ('In Progress', 'On Call', 'On Site')");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_inprogress);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $inprogress = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $res = $conn->query($q_inprogress);
        $inprogress = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 4. Pending Admin Approval
    $q_approval = getQueryCondition($is_engineer, "SELECT COUNT(*) as count FROM services WHERE engineer_submitted = 1 AND verified_by_admin IS NULL");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_approval);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $pending_approval = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $res = $conn->query($q_approval);
        $pending_approval = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 5. Pending Billing
    $q_billing = getQueryCondition($is_engineer, "SELECT COUNT(*) as count FROM services WHERE billing_status IN ('Billing Pending', 'Invoice Generated', 'Payment Pending')");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_billing);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $pending_billing = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $res = $conn->query($q_billing);
        $pending_billing = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 6. Closed Today
    $q_closed_today = getQueryCondition($is_engineer, "SELECT COUNT(*) as count FROM services WHERE DATE(closed_at) = CURDATE()");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_closed_today);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $closed_today = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $res = $conn->query($q_closed_today);
        $closed_today = $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    // 7. Total Revenue Today
    $q_revenue = getQueryCondition($is_engineer, "SELECT SUM(service_value_internal) as sum_val FROM services WHERE DATE(billing_completed_at) = CURDATE()");
    if ($is_engineer) {
        $stmt = $conn->prepare($q_revenue);
        $stmt->bind_param("ss", $staff_name, $staff_email);
        $stmt->execute();
        $raw_rev = $stmt->get_result()->fetch_assoc()['sum_val'];
        $stmt->close();
    } else {
        $res = $conn->query($q_revenue);
        $raw_rev = $res ? $res->fetch_assoc()['sum_val'] : 0;
    }
    $revenue_today = decodeServiceValue((int)($raw_rev ?? 0));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'new_calls' => $new_calls,
            'assigned' => $assigned,
            'inprogress' => $inprogress,
            'pending_approval' => $pending_approval,
            'pending_billing' => $pending_billing,
            'closed_today' => $closed_today,
            'revenue_today' => $revenue_today
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to calculate stats: ' . $e->getMessage()]);
}
?>
