<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view report statistics']);
    exit;
}

try {
    // Generate the last 6 calendar months
    $trends = [];
    for ($i = 5; $i >= 0; $i--) {
        $m_key = date('Y-m', strtotime("-$i months"));
        $m_label = date('M Y', strtotime("-$i months"));
        $trends[$m_key] = [
            'label' => $m_label,
            'revenue' => 0.0
        ];
    }

    $sql = "
        SELECT month_str, SUM(total_val) as total_val 
        FROM (
            SELECT DATE_FORMAT(billing_completed_at, '%Y-%m') as month_str, SUM(service_value_internal) as total_val 
            FROM services 
            WHERE billing_completed_at IS NOT NULL AND status IN ('Closed', 'Delivered') AND billing_status = 'Payment Received'
            GROUP BY month_str
            UNION ALL
            SELECT DATE_FORMAT(billing_completed_at, '%Y-%m') as month_str, SUM(service_value_internal) as total_val 
            FROM user_service_requests 
            WHERE billing_completed_at IS NOT NULL AND status IN ('Closed', 'Delivered') AND billing_status = 'Payment Received'
            GROUP BY month_str
        ) as combined 
        GROUP BY month_str 
        ORDER BY month_str ASC
    ";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $m_key = $row['month_str'];
            if (isset($trends[$m_key])) {
                $trends[$m_key]['revenue'] = decodeServiceValue((int)$row['total_val']);
            }
        }
    }

    echo json_encode(['status' => 'success', 'data' => array_values($trends)]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
