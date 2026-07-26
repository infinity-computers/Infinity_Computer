<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view reports']);
    exit;
}

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

try {
    // Fetch all engineers first
    $engRes = $conn->query("SELECT name FROM engineers ORDER BY name ASC");
    $engineers = [];
    while ($row = $engRes->fetch_assoc()) {
        $engineers[] = $row['name'];
    }

    $data = [];
    foreach ($engineers as $name) {
        $dateCond = "";
        $params = [];
        $types = "";

        if (!empty($start_date)) {
            $dateCond .= " AND DATE(s.created_at) >= ?";
            $params[] = $start_date;
            $types .= "s";
        }
        if (!empty($end_date)) {
            $dateCond .= " AND DATE(s.created_at) <= ?";
            $params[] = $end_date;
            $types .= "s";
        }

        // 1. Total Jobs Assigned
        $q_assigned = "SELECT COUNT(*) as count FROM services s WHERE s.assigned_engineer = ?" . $dateCond;
        $stmt = $conn->prepare($q_assigned);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $assigned = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        // 2. Jobs Completed (status = 'Closed' or status = 'Delivered')
        $q_completed = "SELECT COUNT(*) as count FROM services s WHERE s.assigned_engineer = ? AND s.status IN ('Closed', 'Delivered')" . $dateCond;
        $stmt = $conn->prepare($q_completed);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $completed = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        // 3. Jobs Pending
        $q_pending = "SELECT COUNT(*) as count FROM services s WHERE s.assigned_engineer = ? AND s.status NOT IN ('Closed', 'Delivered', 'Cancelled')" . $dateCond;
        $stmt = $conn->prepare($q_pending);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $pending = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        // 4. Avg Resolution Time (hours)
        $q_res_time = "
            SELECT AVG(TIMESTAMPDIFF(SECOND, s.work_started_at, s.closed_at)) as avg_sec 
            FROM services s 
            WHERE s.assigned_engineer = ? AND s.work_started_at IS NOT NULL AND s.closed_at IS NOT NULL" . $dateCond;
        $stmt = $conn->prepare($q_res_time);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $avg_sec = $stmt->get_result()->fetch_assoc()['avg_sec'];
        $avg_resolution = $avg_sec !== null ? round($avg_sec / 3600.0, 1) : null;
        $stmt->close();

        // 5. Avg First Response Time (minutes)
        $q_resp_time = "
            SELECT AVG(TIMESTAMPDIFF(MINUTE, s.created_at, s.first_response_at)) as avg_min 
            FROM services s 
            WHERE s.assigned_engineer = ? AND s.first_response_at IS NOT NULL" . $dateCond;
        $stmt = $conn->prepare($q_resp_time);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $avg_min = $stmt->get_result()->fetch_assoc()['avg_min'];
        $avg_response = $avg_min !== null ? round($avg_min, 1) : null;
        $stmt->close();

        // 6. Revenue Generated (SUM of service_value_internal * 100)
        $q_revenue = "
            SELECT SUM(s.service_value_internal) as sum_val 
            FROM services s 
            WHERE s.assigned_engineer = ? AND s.status IN ('Closed', 'Delivered')" . $dateCond;
        $stmt = $conn->prepare($q_revenue);
        $stmt->bind_param("s" . $types, $name, ...$params);
        $stmt->execute();
        $sum_val = $stmt->get_result()->fetch_assoc()['sum_val'];
        $revenue = decodeServiceValue((int)($sum_val ?? 0));
        $stmt->close();

        $data[] = [
            'name' => $name,
            'assigned' => $assigned,
            'completed' => $completed,
            'pending' => $pending,
            'avg_resolution' => $avg_resolution !== null ? $avg_resolution . 'h' : 'N/A',
            'avg_response' => $avg_response !== null ? $avg_response . 'm' : 'N/A',
            'revenue' => $revenue
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
