<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view report statistics']);
    exit;
}

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

try {
    $where_parts = ["status IN ('Closed', 'Delivered')"];
    $params = [];
    $types = "";

    if (!empty($start_date)) {
        $where_parts[] = "DATE(created_at) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    if (!empty($end_date)) {
        $where_parts[] = "DATE(created_at) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    $where_clause = implode(" AND ", $where_parts);
    $sql = "
        SELECT service_type, COUNT(*) as count, SUM(service_value_internal) as total_val 
        FROM services 
        WHERE $where_clause
        GROUP BY service_type 
        ORDER BY count DESC
    ";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $row['revenue_rupees'] = decodeServiceValue($row['total_val']);
        $data[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
