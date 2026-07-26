<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view billing statistics']);
    exit;
}

try {
    // 1. Daily Collection
    $q_daily = "
        SELECT SUM(service_value_internal) as daily_total, COUNT(*) as daily_count 
        FROM (
            SELECT service_value_internal, billing_completed_at FROM services WHERE DATE(billing_completed_at) = CURDATE() AND billing_status = 'Payment Received'
            UNION ALL
            SELECT service_value_internal, billing_completed_at FROM user_service_requests WHERE DATE(billing_completed_at) = CURDATE() AND billing_status = 'Payment Received'
        ) as combined
    ";
    $res_daily = $conn->query($q_daily);
    $row_daily = $res_daily ? $res_daily->fetch_assoc() : null;
    $daily_val = decodeServiceValue((int)($row_daily['daily_total'] ?? 0));
    $daily_count = (int)($row_daily['daily_count'] ?? 0);

    // 2. Weekly Collection
    $q_weekly = "
        SELECT SUM(service_value_internal) as weekly_total 
        FROM (
            SELECT service_value_internal, billing_completed_at FROM services WHERE YEARWEEK(billing_completed_at, 1) = YEARWEEK(CURDATE(), 1) AND billing_status = 'Payment Received'
            UNION ALL
            SELECT service_value_internal, billing_completed_at FROM user_service_requests WHERE YEARWEEK(billing_completed_at, 1) = YEARWEEK(CURDATE(), 1) AND billing_status = 'Payment Received'
        ) as combined
    ";
    $res_weekly = $conn->query($q_weekly);
    $row_weekly = $res_weekly ? $res_weekly->fetch_assoc() : null;
    $weekly_val = decodeServiceValue((int)($row_weekly['weekly_total'] ?? 0));

    // 3. Monthly Collection
    $q_monthly = "
        SELECT SUM(service_value_internal) as monthly_total 
        FROM (
            SELECT service_value_internal, billing_completed_at FROM services WHERE MONTH(billing_completed_at) = MONTH(CURDATE()) AND YEAR(billing_completed_at) = YEAR(CURDATE()) AND billing_status = 'Payment Received'
            UNION ALL
            SELECT service_value_internal, billing_completed_at FROM user_service_requests WHERE MONTH(billing_completed_at) = MONTH(CURDATE()) AND YEAR(billing_completed_at) = YEAR(CURDATE()) AND billing_status = 'Payment Received'
        ) as combined
    ";
    $res_monthly = $conn->query($q_monthly);
    $row_monthly = $res_monthly ? $res_monthly->fetch_assoc() : null;
    $monthly_val = decodeServiceValue((int)($row_monthly['monthly_total'] ?? 0));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'daily_total' => $daily_val,
            'daily_count' => $daily_count,
            'weekly_total' => $weekly_val,
            'monthly_total' => $monthly_val
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
