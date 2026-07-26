<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

try {
    $conn->query("CREATE TABLE IF NOT EXISTS home_service_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        service_type VARCHAR(100) NOT NULL,
        booking_date DATE NOT NULL,
        time_slot VARCHAR(50) NOT NULL,
        problem TEXT DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $data = [];
    if (!isEngineer()) {
        $stmt = $conn->prepare("SELECT * FROM home_service_requests ORDER BY created_at DESC");
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
