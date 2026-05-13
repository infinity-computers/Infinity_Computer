<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (isset($_GET['q'])) {
    $q = $_GET['q'];
    
    $data = [];
    
    // Production Mode: Limit visibility to 30 days (43200 minutes)
    // Rule: Show if (created within 30 days) OR (status is still 'Pending' or 'Pending Approval')
    $visibilityCondition = "AND (TIMESTAMPDIFF(MINUTE, created_at, NOW()) <= 43200 OR status IN ('Pending Approval', 'Pending'))";
    $serviceVisibilityCondition = "AND (TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) <= 43200 OR s.status IN ('Pending Approval', 'Pending'))";

    try {
        // 1. Search user_service_requests
        $stmt1 = $conn->prepare("SELECT *, 'service' as request_type FROM user_service_requests WHERE (service_id = ? OR phone = ? OR name = ?) $visibilityCondition ORDER BY created_at DESC LIMIT 10");
        $stmt1->bind_param("sss", $q, $q, $q);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        while($row = $res1->fetch_assoc()) {
            $data[] = $row;
        }
        
        // 2. Search home_service_requests
        $stmt2 = $conn->prepare("SELECT *, 'home' as request_type FROM home_service_requests WHERE (service_id = ? OR phone = ? OR name = ?) $visibilityCondition ORDER BY created_at DESC LIMIT 10");
        $stmt2->bind_param("sss", $q, $q, $q);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while($row = $res2->fetch_assoc()) {
            $data[] = $row;
        }

        // 3. Search services (Admin/Engineer added tickets)
        $stmt3 = $conn->prepare("
            SELECT s.*, c.name, c.phone, 
            'service' as request_type,
            s.company as brand,
            s.device_name as model,
            s.service_type as device_type,
            1 as device_received
            FROM services s 
            JOIN customers c ON s.customer_id = c.id 
            WHERE (s.service_id = ? OR c.phone = ? OR c.name = ?) 
            $serviceVisibilityCondition 
            ORDER BY s.created_at DESC LIMIT 10
        ");
        $stmt3->bind_param("sss", $q, $q, $q);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while($row = $res3->fetch_assoc()) {
            $data[] = $row;
        }
        
        if (count($data) > 0) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No recent or active requests found.']);
        }
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Query parameter missing.']);
}
?>
