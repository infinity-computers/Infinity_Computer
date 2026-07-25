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

$service_id      = trim($input['service_id'] ?? '');
$old_part_name   = trim($input['old_part_name'] ?? '');
$old_part_serial = trim($input['old_part_serial'] ?? '');
$new_part_name   = trim($input['new_part_name'] ?? '');
$new_part_serial = trim($input['new_part_serial'] ?? '');
$quantity        = (int)($input['quantity'] ?? 1);
$warranty_period = trim($input['warranty_period'] ?? '');
$cost_price      = (float)($input['cost_price'] ?? 0.0);
$selling_price   = (float)($input['selling_price'] ?? 0.0);
$replaced_by     = getStaffName();

if (empty($service_id) || empty($new_part_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID and New Part Name are required']);
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

    $stmt = $conn->prepare("INSERT INTO service_parts_replaced (service_id, old_part_name, old_part_serial, new_part_name, new_part_serial, quantity, warranty_period, cost_price, selling_price, photo_path, replaced_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssissdss", $service_id, $old_part_name, $old_part_serial, $new_part_name, $new_part_serial, $quantity, $warranty_period, $cost_price, $selling_price, $photo_path, $replaced_by);
    $stmt->execute();
    $stmt->close();

    logTimelineEvent($conn, $service_id, 'Parts Replaced', $replaced_by, [
        'old_part'   => $old_part_name,
        'new_part'   => $new_part_name,
        'new_serial' => $new_part_serial,
        'warranty'   => $warranty_period,
        'quantity'   => $quantity
    ]);

    echo json_encode(['status' => 'success', 'message' => "Part replacement ('{$new_part_name}') recorded successfully"]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to record part replacement: ' . $e->getMessage()]);
}
?>
