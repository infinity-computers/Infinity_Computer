<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view inventory reports']);
    exit;
}

try {
    $sql = "
        SELECT 
            new_part_name, 
            SUM(quantity) as total_qty, 
            SUM(cost_price * quantity) as total_cost, 
            SUM(selling_price * quantity) as total_selling, 
            SUM((selling_price - cost_price) * quantity) as total_profit 
        FROM service_parts_replaced 
        GROUP BY new_part_name 
        ORDER BY total_qty DESC
    ";
    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
