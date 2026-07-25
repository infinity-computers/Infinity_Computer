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

$service_id   = trim($input['service_id'] ?? '');
$work_remarks = trim($input['remarks'] ?? $input['work_remarks'] ?? '');
$engineerName = getStaffName();

if (empty($service_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Service ID is required']);
    exit;
}

if (empty($work_remarks)) {
    echo json_encode(['status' => 'error', 'message' => 'Mandatory: Please provide work completion remarks before submitting']);
    exit;
}

try {
    // 1. Verify ticket exists and check if images exist
    $stmt = $conn->prepare("SELECT service_id, status FROM services WHERE service_id = ?");
    $stmt->bind_param("s", $service_id);
    $stmt->execute();
    $svc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $tableTarget = 'services';
    if (!$svc) {
        $stmt = $conn->prepare("SELECT service_id, status FROM user_service_requests WHERE service_id = ?");
        $stmt->bind_param("s", $service_id);
        $stmt->execute();
        $svc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $tableTarget = 'user_service_requests';
    }

    if (!$svc) {
        echo json_encode(['status' => 'error', 'message' => 'Service ticket not found']);
        exit;
    }

    // 2. Validate photo proof upload requirement
    $imgRes = $conn->query("SELECT COUNT(*) as count FROM service_images WHERE service_id = '$service_id'");
    $imgCount = ($imgRes && $row = $imgRes->fetch_assoc()) ? (int)$row['count'] : 0;

    if ($imgCount === 0 && (!isset($_FILES['images']) || empty($_FILES['images']['name'][0]))) {
        echo json_encode(['status' => 'error', 'message' => 'Mandatory: Photo proof must be uploaded before submitting work']);
        exit;
    }

    // 3. Optional: Process additional uploaded photo proofs if passed
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        require_once __DIR__ . '/image_helper.php';
        $uploadDir = "../../uploads/images/";
        $category = trim($input['category'] ?? 'After Repair');
        
        $fileCount = min(count($_FILES['images']['name']), 5);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] == 0) {
                $singleFile = [
                    'name'     => $_FILES['images']['name'][$i],
                    'type'     => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error'    => $_FILES['images']['error'][$i],
                    'size'     => $_FILES['images']['size'][$i],
                ];
                $filename = processAndSaveImage($singleFile, $uploadDir);
                if ($filename) {
                    $path = "uploads/images/" . $filename;
                    $imgStmt = $conn->prepare("INSERT INTO service_images (service_id, image_path, source_table, category) VALUES (?, ?, ?, ?)");
                    $imgStmt->bind_param("ssss", $service_id, $path, $tableTarget, $category);
                    $imgStmt->execute();
                    $imgStmt->close();
                }
            }
        }
    }

    // 4. Update ticket status to 'Engineer Submitted' and record timestamp
    $now = date('Y-m-d H:i:s');
    $upd = $conn->prepare("UPDATE `$tableTarget` SET status = 'Engineer Submitted', engineer_submitted = 1, engineer_submitted_at = ? WHERE service_id = ?");
    $upd->bind_param("ss", $now, $service_id);
    $upd->execute();
    $upd->close();

    // 5. Insert log
    if ($tableTarget === 'services') {
        $pkRes = $conn->query("SELECT id FROM services WHERE service_id = '$service_id'");
        if ($pkRow = $pkRes->fetch_assoc()) {
            $pkId = $pkRow['id'];
            $logStmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, 'Engineer Submitted', ?)");
            $logRemarks = "Work submitted by Engineer ($engineerName): " . $work_remarks;
            $logStmt->bind_param("is", $pkId, $logRemarks);
            $logStmt->execute();
            $logStmt->close();
        }
    }

    // 6. Log immutable timeline event
    logTimelineEvent($conn, $service_id, 'Engineer Submitted', $engineerName, [
        'remarks' => $work_remarks,
        'submitted_at' => $now
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Work submitted successfully! Ticket is now waiting for Admin Verification.'
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit work: ' . $e->getMessage()]);
}
?>
