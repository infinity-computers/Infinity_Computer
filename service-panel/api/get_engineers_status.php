<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Self-healing: Ensure columns exist
    @$conn->query("ALTER TABLE `engineers` ADD COLUMN `status` ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'");
    @$conn->query("ALTER TABLE `engineers` ADD COLUMN `current_ticket` VARCHAR(50) DEFAULT NULL");
    @$conn->query("ALTER TABLE `engineers` ADD COLUMN `last_activity_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $result = $conn->query("SELECT id, name, status, current_ticket, last_activity_at FROM engineers ORDER BY name ASC");
    $list = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $list]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
