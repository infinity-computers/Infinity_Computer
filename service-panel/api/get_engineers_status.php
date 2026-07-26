<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $result = $conn->query("SELECT id, name, status, current_ticket, last_activity_at FROM engineers ORDER BY name ASC");
    $list = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $list]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
