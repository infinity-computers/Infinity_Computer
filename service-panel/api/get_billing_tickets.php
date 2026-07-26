<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view billing records']);
    exit;
}

$tab        = $_GET['tab'] ?? 'pending';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$query      = $_GET['q'] ?? '';

try {
    $data = [];

    if ($tab === 'pending') {
        $sql = "
            SELECT * FROM (
                SELECT s.service_id, s.device_name, s.billing_status, s.service_value_internal, s.status, s.problem, c.name as customer_name, s.assigned_engineer, s.created_at
                FROM services s 
                JOIN customers c ON s.customer_id = c.id
                WHERE s.billing_status IN ('Billing Pending', 'Invoice Generated', 'Payment Pending')
                UNION ALL
                SELECT u.service_id, CONCAT(u.brand, ' ', u.model) as device_name, u.billing_status, u.service_value_internal, u.status, u.problem, u.name as customer_name, u.assigned_engineer, u.created_at
                FROM user_service_requests u
                WHERE u.billing_status IN ('Billing Pending', 'Invoice Generated', 'Payment Pending')
            ) as combined
            ORDER BY created_at DESC
        ";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $row['service_value_rupees'] = decodeServiceValue($row['service_value_internal']);
            $data[] = $row;
        }
    } elseif ($tab === 'register') {
        $where_parts = ["invoice_number IS NOT NULL AND invoice_number != ''"];
        $params = [];
        $types = "";

        if (!empty($start_date)) {
            $where_parts[] = "DATE(billing_completed_at) >= ?";
            $params[] = $start_date;
            $types .= "s";
        }
        if (!empty($end_date)) {
            $where_parts[] = "DATE(billing_completed_at) <= ?";
            $params[] = $end_date;
            $types .= "s";
        }
        if (!empty($query)) {
            $where_parts[] = "(invoice_number LIKE ? OR service_id LIKE ? OR customer_name LIKE ?)";
            $q_like = "%" . $query . "%";
            $params[] = $q_like;
            $params[] = $q_like;
            $params[] = $q_like;
            $types .= "sss";
        }

        $where_clause = implode(" AND ", $where_parts);

        $sql = "
            SELECT * FROM (
                SELECT s.invoice_number, s.service_id, c.name as customer_name, s.billing_completed_at, s.service_value_internal, s.payment_mode, s.billing_verified_by
                FROM services s
                JOIN customers c ON s.customer_id = c.id
                UNION ALL
                SELECT u.invoice_number, u.service_id, u.name as customer_name, u.billing_completed_at, u.service_value_internal, u.payment_mode, u.billing_verified_by
                FROM user_service_requests u
            ) as combined
            WHERE $where_clause
            ORDER BY billing_completed_at DESC
        ";

        if (count($params) > 0) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }

        while ($row = $res->fetch_assoc()) {
            $row['service_value_rupees'] = decodeServiceValue($row['service_value_internal']);
            $data[] = $row;
        }
    } elseif ($tab === 'today') {
        $sql = "
            SELECT * FROM (
                SELECT s.invoice_number, s.service_id, c.name as customer_name, s.billing_completed_at, s.service_value_internal, s.payment_mode, s.billing_verified_by
                FROM services s
                JOIN customers c ON s.customer_id = c.id
                WHERE DATE(s.billing_completed_at) = CURDATE() AND s.billing_status = 'Payment Received'
                UNION ALL
                SELECT u.invoice_number, u.service_id, u.name as customer_name, u.billing_completed_at, u.service_value_internal, u.payment_mode, u.billing_verified_by
                FROM user_service_requests u
                WHERE DATE(u.billing_completed_at) = CURDATE() AND u.billing_status = 'Payment Received'
            ) as combined
            ORDER BY billing_completed_at DESC
        ";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $row['service_value_rupees'] = decodeServiceValue($row['service_value_internal']);
            $data[] = $row;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
