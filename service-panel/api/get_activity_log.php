<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isSuperAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Super Admin access only.']);
    exit;
}

try {
    $result = $conn->query("
        SELECT id, service_id, event_type, performed_by, event_data, created_at 
        FROM service_timeline_events 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    
    $logs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $detail_data = json_decode($row['event_data'], true);
            if ($detail_data === null) {
                $detail_data = $row['event_data'];
            }
            $row['event_data'] = $detail_data;
            $logs[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $logs]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
