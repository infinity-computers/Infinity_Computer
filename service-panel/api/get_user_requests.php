<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

try {
    // Make sure table exists so we don't error out on empty DB
    $conn->query("CREATE TABLE IF NOT EXISTS user_service_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        device_type VARCHAR(100) NOT NULL,
        brand VARCHAR(100) DEFAULT NULL,
        model VARCHAR(100) DEFAULT NULL,
        problem TEXT NOT NULL,
        image_path VARCHAR(255),
        status VARCHAR(50) DEFAULT 'Pending Approval',
        device_received BOOLEAN DEFAULT 0,
        assigned_engineer VARCHAR(100) DEFAULT 'Suraj',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $res = $conn->query("SHOW COLUMNS FROM user_service_requests LIKE 'assigned_engineer'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE user_service_requests ADD COLUMN assigned_engineer VARCHAR(100) DEFAULT 'Suraj' AFTER device_received");
    }
    
    // Fetch pending and approved requests
    $stmt = $conn->prepare("SELECT * FROM user_service_requests ORDER BY created_at DESC");
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
