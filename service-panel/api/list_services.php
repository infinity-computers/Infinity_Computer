<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $filter = $_GET['filter'] ?? '';
    $conditions = [];
    $params = [];
    $types = "";

    // Enforce role-based isolation: Engineers only see their own tickets
    if (isEngineer()) {
        $conditions[] = "(LOWER(TRIM(s.assigned_engineer)) = LOWER(TRIM(?)) OR LOWER(TRIM(s.assigned_engineer)) = LOWER(TRIM(?)))";
        $params[] = getStaffName();
        $params[] = $_SESSION['staff_email'] ?? '';
        $types .= "ss";
    }

    // Apply dashboard stats filters if clicked
    if ($filter === 'new_calls') {
        $conditions[] = "s.status = 'Pending' AND (s.assigned_engineer IS NULL OR s.assigned_engineer = '')";
    } elseif ($filter === 'assigned') {
        $conditions[] = "s.status = 'Assigned'";
    } elseif ($filter === 'inprogress') {
        $conditions[] = "s.status IN ('In Progress', 'On Call', 'On Site')";
    } elseif ($filter === 'pending_approval') {
        $conditions[] = "s.engineer_submitted = 1 AND s.verified_by_admin IS NULL";
    } elseif ($filter === 'pending_billing') {
        $conditions[] = "s.billing_status IN ('Billing Pending', 'Invoice Generated', 'Payment Pending')";
    } elseif ($filter === 'closed_today') {
        $conditions[] = "DATE(s.closed_at) = CURDATE()";
    }

    $whereClause = "";
    if (count($conditions) > 0) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $sql = "
        SELECT s.*, c.name, c.phone 
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        $whereClause
        ORDER BY s.created_at DESC 
        LIMIT 100
    ";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $services = [];
    if($res) {
        $now_ts = time();
        while($row = $res->fetch_assoc()) {
            $row['stuck_warning'] = '';
            $updated_ts = !empty($row['updated_at']) ? strtotime($row['updated_at']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : $now_ts);
            $diff_sec = $now_ts - $updated_ts;
            if ($row['status'] === 'Assigned' && $diff_sec > 2 * 3600) {
                $row['stuck_warning'] = 'No Engineer Response (>2h)';
            } elseif ($row['status'] === 'Engineer Submitted' && $diff_sec > 4 * 3600) {
                $row['stuck_warning'] = 'Pending Admin Verification (>4h)';
            }
            $services[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $services]);
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
