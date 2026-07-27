<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

if(empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT s.*, c.name, c.phone 
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.service_id = ?
    ");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $svc = $res->fetch_assoc();

    if($svc) {
        $assigned = trim($svc['assigned_engineer'] ?? '');
        $staff_name = trim(getStaffName());
        $staff_email = trim($_SESSION['staff_email'] ?? '');
        if (isEngineer() && !empty($assigned) && strcasecmp($assigned, $staff_name) !== 0 && strcasecmp($assigned, $staff_email) !== 0) {
            echo json_encode(['status' => 'error', 'message' => 'Access Denied: You are not assigned to this service ticket.']);
            exit;
        }
        $log_stmt = $conn->prepare("SELECT * FROM service_status_logs WHERE service_id = ? ORDER BY updated_at DESC");
        $log_stmt->bind_param("i", $svc['id']);
        $log_stmt->execute();
        $log_res = $log_stmt->get_result();
        $svc['logs'] = [];
        while($row = $log_res->fetch_assoc()) {
            $svc['logs'][] = $row;
        }

        // Fetch all images from service_images table
        $svc['images'] = [];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'service_images'");
        if ($tableCheck->num_rows > 0) {
            $img_stmt = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = ? AND source_table = 'services' ORDER BY id ASC");
            $img_stmt->bind_param("s", $id);
            $img_stmt->execute();
            $img_res = $img_stmt->get_result();
            while ($row = $img_res->fetch_assoc()) {
                $svc['images'][] = $row['image_path'];
            }
        }
        // Fallback: if no images in service_images, use image_path
        if (empty($svc['images']) && !empty($svc['image_path'])) {
            $svc['images'][] = $svc['image_path'];
        }

        echo json_encode(['status' => 'success', 'data' => $svc]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Service not found']);
    }
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
