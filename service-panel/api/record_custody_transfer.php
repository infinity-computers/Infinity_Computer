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

$service_id       = trim($input['service_id'] ?? '');
$transfer_type    = trim($input['transfer_type'] ?? '');
$from_user        = trim($input['from_user'] ?? getStaffName());
$to_user          = trim($input['to_user'] ?? '');
$device_condition = trim($input['device_condition'] ?? '');
$remarks          = trim($input['remarks'] ?? '');

$allowed_types = ['Customer -> Engineer', 'Engineer -> Workshop', 'Workshop -> Engineer', 'Engineer -> Customer'];

if (empty($service_id) || empty($transfer_type) || empty($to_user)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID, Transfer Type, and Recipient are required']);
    exit;
}

if (!in_array($transfer_type, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid transfer type selected']);
    exit;
}

try {
    $photo_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        require_once __DIR__ . '/image_helper.php';
        $uploadDir = "../../uploads/images/";
        $filename = processAndSaveImage($_FILES['image'], $uploadDir);
        if ($filename) {
            $photo_path = "uploads/images/" . $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO custody_transfers (service_id, transfer_type, from_user, to_user, photo_path, device_condition, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $service_id, $transfer_type, $from_user, $to_user, $photo_path, $device_condition, $remarks);
    $stmt->execute();
    $stmt->close();

    logTimelineEvent($conn, $service_id, 'Custody Transfer', getStaffName(), [
        'transfer_type' => $transfer_type,
        'from_user'     => $from_user,
        'to_user'       => $to_user,
        'condition'     => $device_condition,
        'remarks'       => $remarks
    ]);

    echo json_encode(['status' => 'success', 'message' => "Custody transfer ('{$transfer_type}') recorded successfully"]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to record custody transfer: ' . $e->getMessage()]);
}
?>
