<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$service_id = $_GET['id'] ?? '';

if (empty($service_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

try {
    $events = [];

    // 1. Fetch from service_timeline_events
    $stmt1 = $conn->prepare("SELECT * FROM service_timeline_events WHERE service_id = ?");
    $stmt1->bind_param("s", $service_id);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($row = $res1->fetch_assoc()) {
        // Event data might be JSON, try to decode it
        $detail_data = json_decode($row['event_data'], true);
        if ($detail_data === null) {
            $detail_data = $row['event_data'];
        }
        $events[] = [
            'type' => 'Timeline',
            'title' => $row['event_type'],
            'performed_by' => $row['performed_by'],
            'created_at' => $row['created_at'],
            'details' => $detail_data
        ];
    }
    $stmt1->close();

    // 2. Fetch from call_attempts
    $stmt2 = $conn->prepare("SELECT * FROM call_attempts WHERE service_id = ?");
    $stmt2->bind_param("s", $service_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $events[] = [
            'type' => 'Call Log',
            'title' => 'Call Attempt: ' . $row['call_status'],
            'performed_by' => $row['called_by'],
            'created_at' => $row['created_at'],
            'details' => $row['notes']
        ];
    }
    $stmt2->close();

    // 3. Fetch from custody_transfers
    $stmt3 = $conn->prepare("SELECT * FROM custody_transfers WHERE service_id = ?");
    $stmt3->bind_param("s", $service_id);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) {
        $events[] = [
            'type' => 'Custody Transfer',
            'title' => $row['transfer_type'],
            'performed_by' => $row['from_user'],
            'created_at' => $row['created_at'],
            'details' => $row['remarks'],
            'extra' => [
                'to_user' => $row['to_user'],
                'photo_path' => $row['photo_path'],
                'device_condition' => $row['device_condition']
            ]
        ];
    }
    $stmt3->close();

    // Sort events by created_at chronologically
    usort($events, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    echo json_encode(['status' => 'success', 'data' => $events]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve timeline: ' . $e->getMessage()]);
}
?>
