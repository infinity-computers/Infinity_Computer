<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$service_id          = trim($input['service_id'] ?? '');
$primary_engineer    = trim($input['primary_engineer'] ?? '');
$supporting_engineers = $input['supporting_engineers'] ?? [];
$assigned_by         = getStaffName();

if (is_string($supporting_engineers)) {
    $supporting_engineers = array_filter(array_map('trim', explode(',', $supporting_engineers)));
}

if (empty($service_id) || empty($primary_engineer)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID and Primary Engineer are required']);
    exit;
}

try {
    $now = date('Y-m-d H:i:s');

    // 1. Update primary engineer on main ticket
    $conn->query("UPDATE services SET assigned_engineer = '$primary_engineer', assigned_at = '$now' WHERE service_id = '$service_id'");
    $conn->query("UPDATE user_service_requests SET assigned_engineer = '$primary_engineer' WHERE service_id = '$service_id'");

    // 2. Clear prior active assignments for this ticket
    $conn->query("UPDATE ticket_engineer_assignments SET status = 'Transferred' WHERE service_id = '$service_id' AND status = 'Assigned'");

    // 3. Insert primary assignment
    $stmt = $conn->prepare("INSERT INTO ticket_engineer_assignments (service_id, engineer_name, assignment_type, assigned_by) VALUES (?, ?, 'Primary', ?)");
    $stmt->bind_param("sss", $service_id, $primary_engineer, $assigned_by);
    $stmt->execute();
    $stmt->close();

    // 4. Insert supporting assignments
    if (!empty($supporting_engineers) && is_array($supporting_engineers)) {
        $stmtSup = $conn->prepare("INSERT INTO ticket_engineer_assignments (service_id, engineer_name, assignment_type, assigned_by) VALUES (?, ?, 'Supporting', ?)");
        foreach ($supporting_engineers as $supName) {
            $supName = trim($supName);
            if (!empty($supName) && $supName !== $primary_engineer) {
                $stmtSup->bind_param("sss", $service_id, $supName, $assigned_by);
                $stmtSup->execute();
            }
        }
        $stmtSup->close();
    }

    logTimelineEvent($conn, $service_id, 'Engineers Assigned', $assigned_by, [
        'primary'    => $primary_engineer,
        'supporting' => $supporting_engineers
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Engineers assigned successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to assign engineers: ' . $e->getMessage()]);
}
?>
