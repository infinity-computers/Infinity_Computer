<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if(empty($query)) {
    echo json_encode(['status' => 'error', 'message' => 'Query is required']);
    exit;
}

try {
    $results = [];

    // 1. Search Active/Engineering Services
    if (isEngineer()) {
        $stmt = $conn->prepare("
            SELECT s.*, c.name, c.phone, 'engineering' as source_type
            FROM services s 
            JOIN customers c ON s.customer_id = c.id 
            WHERE (s.service_id = ? OR c.phone = ? OR c.name = ?) AND s.assigned_engineer = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->bind_param("ssss", $query, $query, $query, getStaffName());
    } else {
        $stmt = $conn->prepare("
            SELECT s.*, c.name, c.phone, 'engineering' as source_type
            FROM services s 
            JOIN customers c ON s.customer_id = c.id 
            WHERE s.service_id = ? OR c.phone = ? OR c.name = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->bind_param("sss", $query, $query, $query);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    
    while($row = $res->fetch_assoc()) {
        // Fetch logs for engineering services
        $log_stmt = $conn->prepare("SELECT * FROM service_status_logs WHERE service_id = ? ORDER BY updated_at DESC");
        $log_stmt->bind_param("i", $row['id']);
        $log_stmt->execute();
        $log_res = $log_stmt->get_result();
        $row['logs'] = [];
        while($log_row = $log_res->fetch_assoc()) {
            $row['logs'][] = $log_row;
        }
        // Fetch all images
        $row['images'] = [];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'service_images'");
        if ($tableCheck->num_rows > 0) {
            $img_stmt = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = ? AND source_table = 'services' ORDER BY id ASC");
            $img_stmt->bind_param("s", $row['service_id']);
            $img_stmt->execute();
            $img_res = $img_stmt->get_result();
            while ($img_row = $img_res->fetch_assoc()) {
                $row['images'][] = $img_row['image_path'];
            }
        }
        if (empty($row['images']) && !empty($row['image_path'])) {
            $row['images'][] = $row['image_path'];
        }
        // Stuck warnings
        $now_ts = time();
        $row['stuck_warning'] = '';
        $updated_ts = !empty($row['updated_at']) ? strtotime($row['updated_at']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : $now_ts);
        $diff_sec = $now_ts - $updated_ts;
        if ($row['status'] === 'Assigned' && $diff_sec > 2 * 3600) {
            $row['stuck_warning'] = 'No Engineer Response (>2h)';
        } elseif ($row['status'] === 'Engineer Submitted' && $diff_sec > 4 * 3600) {
            $row['stuck_warning'] = 'Pending Admin Verification (>4h)';
        }
        $results[] = $row;
    }

    // 2. Search User Service Requests (Web Requests)
    $stmt2 = $conn->prepare("
        SELECT *, 'web_request' as source_type 
        FROM user_service_requests 
        WHERE service_id = ? OR phone = ? OR name = ? 
        ORDER BY created_at DESC
    ");
    $stmt2->bind_param("sss", $query, $query, $query);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while($row = $res2->fetch_assoc()) {
        // Fetch all images for web requests
        $row['images'] = [];
        $tableCheck2 = $conn->query("SHOW TABLES LIKE 'service_images'");
        if ($tableCheck2->num_rows > 0) {
            $img_stmt2 = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = ? AND source_table = 'user_service_requests' ORDER BY id ASC");
            $img_stmt2->bind_param("s", $row['service_id']);
            $img_stmt2->execute();
            $img_res2 = $img_stmt2->get_result();
            while ($img_row2 = $img_res2->fetch_assoc()) {
                $row['images'][] = $img_row2['image_path'];
            }
        }
        if (empty($row['images']) && !empty($row['image_path'])) {
            $row['images'][] = $row['image_path'];
        }
        $results[] = $row;
    }

    // 3. Search Home Service Requests
    if (!isEngineer()) {
        $stmt3 = $conn->prepare("
            SELECT *, 'home' as source_type 
            FROM home_service_requests 
            WHERE service_id = ? OR phone = ? OR name = ? 
            ORDER BY created_at DESC
        ");
        $stmt3->bind_param("sss", $query, $query, $query);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while($row = $res3->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt3->close();
    }

    if(count($results) > 0) {
        echo json_encode(['status' => 'success', 'data' => $results]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No matching records found']);
    }
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
