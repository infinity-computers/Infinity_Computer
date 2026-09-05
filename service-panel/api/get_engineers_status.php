<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $result = $conn->query("SELECT * FROM engineers ORDER BY name ASC");
    $list = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = [
                'id' => $row['id'] ?? null,
                'name' => $row['name'] ?? '',
                'status' => $row['status'] ?? 'Active',
                'current_ticket' => $row['current_ticket'] ?? null,
                'last_activity_at' => $row['last_activity_at'] ?? null
            ];
        }
    }
    echo json_encode(['status' => 'success', 'data' => $list]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
